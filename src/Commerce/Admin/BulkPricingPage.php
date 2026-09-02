<?php
/**
 * PizzaTier — Bulk Pricing admin page.
 *
 * A single-page, no-refresh editor for the per-ingredient price grids stored on
 * every PizzaTier CPT post (_pizzatier_commerce_layer_grid). Where the "Pricing" page owns
 * site-wide *defaults* (the global per-layer-type grids), this page lets a store
 * owner fill in the *actual* per-item prices in bulk, across one of three scopes:
 *
 *   • Individual layers      — tick specific items and edit only those.
 *   • A whole layer type      — every Topping (or Crust, Sauce, …) at once.
 *   • All layer items site-wide — every ingredient across all 7 CPTs.
 *
 * The editor renders a table per layer type: one row per ingredient, one column
 * per size (and, for coverage-priced types — toppings, sauces, cheeses,
 * drizzles — one column per size × coverage fraction). Every cell is an editable
 * price input, and a "fill" toolbar lets a whole column, a whole table, or just
 * the selected rows be set to one value in a single click.
 *
 * Nothing here reloads the page. Items load over AJAX when a scope is chosen,
 * and Save posts every changed item in one batched request that persists each
 * grid through Grid::save_layer_grid() (the same validation the per-ingredient
 * meta box and CSV importer use, so all constraints stay identical).
 *
 * Menu slug note:
 *   PAGE_SLUG is 'pizzatier-bulk-pricing'. It was originally chosen to avoid
 *   the bare '-pricing' suffix that the Freemius SDK reserved for its own
 *   upgrade/checkout page; Freemius was removed in 1.10.0, so that constraint
 *   no longer applies. The slug is unchanged regardless, to avoid breaking
 *   existing links — see PricingPage::PAGE_SLUG for the same reasoning.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceGrid\Grid;

class BulkPricingPage {

	/** Admin menu slug — see class docblock for why it isn't '…-pricing'. */
	const PAGE_SLUG = 'pizzatier-bulk-pricing';

	/** Shared nonce action for both AJAX endpoints on this page. */
	const NONCE_ACTION = 'pizzatier_commerce_bulk_pricing';

	/** AJAX actions. */
	const ACTION_LOAD = 'pizzatier_commerce_bulk_load_items';
	const ACTION_SAVE = 'pizzatier_commerce_bulk_save_prices';

	/** Max ingredients loaded per type in one pass (keeps the table usable). */
	const ITEM_CAP = 500;

	/** @var Grid */
	private $grid;

	public function __construct( Grid $grid ) {
		$this->grid = $grid;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Hook the AJAX handlers and the asset guard. The menu item itself is
	 * registered by PizzaTier\Admin\AdminMenu (which owns the whole
	 * PizzaTier menu since 2.0.0), so this runs from Plugin::init() to make sure the
	 * AJAX endpoints exist on admin-ajax.php requests too — those never fire
	 * admin_menu.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION_LOAD, [ $this, 'ajax_load_items' ] );
		add_action( 'wp_ajax_' . self::ACTION_SAVE, [ $this, 'ajax_save_prices' ] );
		add_action( 'admin_enqueue_scripts',        [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Layer-type metadata
	// -------------------------------------------------------------------------

	/**
	 * Canonical layer-type descriptors: display label, CPT slug, and whether
	 * the type is priced by coverage fraction (Whole/Half/Quarter). This mirrors
	 * the fraction flags used by the Pricing page's Global Price Grids tab so the
	 * two editors agree on which types get coverage columns.
	 *
	 * Flat types (crusts, cuts, sizes) still store a valid grid — a single
	 * 'Whole' fraction — so Grid::save_layer_grid()'s "at least one fraction"
	 * constraint is satisfied and the engine resolves them at fraction 'Whole'.
	 *
	 * @return array<string,array{label:string,cpt:string,fraction:bool,desc:string}>
	 */
	private function types(): array {
		return [
			'toppings' => [
				'label'    => __( 'Toppings', 'pizzatier' ),
				'cpt'      => 'pizzatier_toppings',
				'fraction' => true,
				'desc'     => __( 'Priced by size and coverage — half-and-half builds use the coverage columns.', 'pizzatier' ),
			],
			'crusts' => [
				'label'    => __( 'Crusts', 'pizzatier' ),
				'cpt'      => 'pizzatier_crusts',
				'fraction' => false,
				'desc'     => __( 'One price per size — applied once per pizza.', 'pizzatier' ),
			],
			'sauces' => [
				'label'    => __( 'Sauces', 'pizzatier' ),
				'cpt'      => 'pizzatier_sauces',
				'fraction' => true,
				'desc'     => __( 'Priced by size and coverage.', 'pizzatier' ),
			],
			'cheeses' => [
				'label'    => __( 'Cheeses', 'pizzatier' ),
				'cpt'      => 'pizzatier_cheeses',
				'fraction' => true,
				'desc'     => __( 'Priced by size and coverage.', 'pizzatier' ),
			],
			'drizzles' => [
				'label'    => __( 'Drizzles', 'pizzatier' ),
				'cpt'      => 'pizzatier_drizzles',
				'fraction' => true,
				'desc'     => __( 'Finishing touches priced by size and coverage.', 'pizzatier' ),
			],
			'cuts' => [
				'label'    => __( 'Cuts', 'pizzatier' ),
				'cpt'      => 'pizzatier_cuts',
				'fraction' => false,
				'desc'     => __( 'One price per size — a fixed add-on for the slicing style.', 'pizzatier' ),
			],
			'sizes' => [
				'label'    => __( 'Sizes', 'pizzatier' ),
				'cpt'      => 'pizzatier_sizes',
				'fraction' => false,
				'desc'     => __( 'One price per size, if you sell the size selection itself as an upgrade.', 'pizzatier' ),
			],
		];
	}

	/**
	 * Resolve the size and fraction axes for a type. Prefers the type's saved
	 * global grid (so the bulk editor lines up with the site-wide default the
	 * engine already uses), then falls back to the plugin-wide defaults.
	 * Flat types always collapse to a single 'Whole' fraction.
	 *
	 * @param string $type   Canonical plural slug.
	 * @param bool   $is_fraction
	 * @return array{sizes:string[],fractions:string[]}
	 */
	private function axes_for( string $type, bool $is_fraction ): array {
		$global = $this->grid->get_global_layer_grid( $type );

		$sizes = ( $global && ! empty( $global['sizes'] ) )
			? $global['sizes']
			: $this->grid->default_sizes();

		if ( ! $is_fraction ) {
			$fractions = [ 'Whole' ];
		} elseif ( $global && ! empty( $global['fractions'] ) ) {
			$fractions = $global['fractions'];
		} else {
			$fractions = $this->grid->default_fractions();
		}

		return [
			'sizes'     => array_values( $sizes ),
			'fractions' => array_values( $fractions ),
		];
	}

	// -------------------------------------------------------------------------
	// AJAX: load items for one type (or all)
	// -------------------------------------------------------------------------

	/**
	 * Return the ingredients + current per-item cells for the requested scope.
	 *
	 * Request:  type = one canonical slug, or 'all'.
	 * Response: { types: [ { type, label, desc, is_fraction, sizes, fractions,
	 *            global_cells, capped, items: [ {id,title,has_grid,cells} ] } ] }
	 */
	public function ajax_load_items(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$requested = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'all';
		$all_types = $this->types();

		if ( 'all' === $requested ) {
			$slugs = array_keys( $all_types );
		} elseif ( isset( $all_types[ $requested ] ) ) {
			$slugs = [ $requested ];
		} else {
			wp_send_json_error( [ 'message' => __( 'Unknown layer type.', 'pizzatier' ) ] );
			return;
		}

		$out = [];
		foreach ( $slugs as $slug ) {
			$meta        = $all_types[ $slug ];
			$is_fraction = (bool) $meta['fraction'];
			$axes        = $this->axes_for( $slug, $is_fraction );

			// Global grid cells → used as greyed placeholders in the editor.
			$global       = $this->grid->get_global_layer_grid( $slug );
			$global_cells = ( $global && isset( $global['cells'] ) && is_array( $global['cells'] ) )
				? $this->stringify_cells( $global['cells'] )
				: [];

			$query = new \WP_Query( [
				'post_type'      => $meta['cpt'],
				'post_status'    => [ 'publish', 'private' ],
				'posts_per_page' => self::ITEM_CAP,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
				'fields'         => 'ids',
			] );

			$items  = [];
			$capped = ( (int) $query->found_posts > self::ITEM_CAP );

			foreach ( $query->posts as $pid ) {
				$pid  = (int) $pid;
				$grid = $this->grid->get_layer_grid( $pid );
				$items[] = [
					'id'       => $pid,
					'title'    => html_entity_decode( get_the_title( $pid ), ENT_QUOTES ),
					'has_grid' => ( null !== $grid ),
					'cells'    => ( $grid && isset( $grid['cells'] ) ) ? $this->stringify_cells( $grid['cells'] ) : new \stdClass(),
				];
			}

			$out[] = [
				'type'         => $slug,
				'label'        => $meta['label'],
				'desc'         => $meta['desc'],
				'is_fraction'  => $is_fraction,
				'sizes'        => $axes['sizes'],
				'fractions'    => $axes['fractions'],
				'global_cells' => empty( $global_cells ) ? new \stdClass() : $global_cells,
				'capped'       => $capped,
				'cap'          => self::ITEM_CAP,
				'items'        => $items,
			];
		}

		wp_send_json_success( [ 'types' => $out ] );
	}

	/**
	 * Format a cell map's float values as fixed "1.50"-style strings for the UI.
	 *
	 * @param array<string,mixed> $cells
	 * @return array<string,string>
	 */
	private function stringify_cells( array $cells ): array {
		$out = [];
		foreach ( $cells as $k => $v ) {
			if ( '' === $v || null === $v ) {
				continue;
			}
			$out[ (string) $k ] = number_format( (float) $v, 2, '.', '' );
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// AJAX: save a batch of per-item grids
	// -------------------------------------------------------------------------

	/**
	 * Persist a batch of edited (or cleared) ingredient grids in one request.
	 *
	 * Request:  items = JSON array of
	 *             { post_id, sizes[], fractions[], cells{key:val} }  (save), or
	 *             { post_id, clear:true }                            (revert).
	 * Response: { saved, cleared, failed, errors:[{id,title,message}] }
	 */
	public function ajax_save_prices(): void {
		$this->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper. Sanitized immediately below, per element, before use.
		$raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
		if ( '' === $raw ) {
			wp_send_json_error( [ 'message' => __( 'No changes were received.', 'pizzatier' ) ] );
			return;
		}

		$items = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $items ) ) {
			wp_send_json_error( [ 'message' => __( 'The changes could not be read. Please reload and try again.', 'pizzatier' ) ] );
			return;
		}

		$allowed_cpts = wp_list_pluck( $this->types(), 'cpt' );

		$saved   = 0;
		$cleared = 0;
		$failed  = 0;
		$errors  = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id = absint( $item['post_id'] ?? 0 );
			$post    = $post_id ? get_post( $post_id ) : null;
			$title   = $post ? get_the_title( $post_id ) : ( '#' . $post_id );

			// Validate the target is a real, editable PizzaTier ingredient.
			if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, $allowed_cpts, true ) ) {
				$failed++;
				$errors[] = [ 'id' => $post_id, 'title' => $title, 'message' => __( 'Not a PizzaTier ingredient.', 'pizzatier' ) ];
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$failed++;
				$errors[] = [ 'id' => $post_id, 'title' => $title, 'message' => __( 'You cannot edit this ingredient.', 'pizzatier' ) ];
				continue;
			}

			// Clear op → revert to the fallback (global / product) grid.
			if ( ! empty( $item['clear'] ) ) {
				$this->grid->delete_layer_grid( $post_id );
				$cleared++;
				continue;
			}

			$data = [
				'sizes'     => isset( $item['sizes'] ) && is_array( $item['sizes'] ) ? array_map( 'strval', $item['sizes'] ) : [],
				'fractions' => isset( $item['fractions'] ) && is_array( $item['fractions'] ) ? array_map( 'strval', $item['fractions'] ) : [],
				'cells'     => isset( $item['cells'] ) && is_array( $item['cells'] ) ? $item['cells'] : [],
			];

			$result = $this->grid->save_layer_grid( $post_id, $data );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$errors[] = [ 'id' => $post_id, 'title' => $title, 'message' => $result->get_error_message() ];
				continue;
			}
			$saved++;
		}

		wp_send_json_success( [
			'saved'   => $saved,
			'cleared' => $cleared,
			'failed'  => $failed,
			'errors'  => $errors,
		] );
	}

	// -------------------------------------------------------------------------
	// Security
	// -------------------------------------------------------------------------

	private function verify(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please reload the page.', 'pizzatier' ) ], 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage pricing.', 'pizzatier' ) ], 403 );
		}
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pizzatier' ) );
		}

		// Type list for the scope selector. The client boot config (currency,
		// nonce, i18n, this same list) is attached to the enqueued script in
		// enqueue_assets() via wp_add_inline_script().
		$types = $this->scope_types();
		?>
		<div class="wrap pztc-page-wrap pztc-bulk-page">


			<!-- ══ Header ═════════════════════════════════════════════════ -->
			<div class="pztc-header">
				<div class="pztc-header__brand">
					<span class="dashicons dashicons-editor-table pztc-header__icon" aria-hidden="true"></span>
					<div>
						<h1 class="pztc-header__title"><?php esc_html_e( 'Bulk Pricing', 'pizzatier' ); ?></h1>
						<p class="pztc-header__tagline">
							<?php esc_html_e( 'Set per-ingredient prices by size in one table — one item, a whole type, or every ingredient at once', 'pizzatier' ); ?>
							&mdash; v<?php echo esc_html( PIZZATIER_VERSION ); ?>
						</p>
					</div>
				</div>
				<div class="pztc-header__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'Dashboard', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . PricingPage::PAGE_SLUG ) ); ?>" class="button">
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Pricing defaults', 'pizzatier' ); ?>
					</a>
				</div>
			</div>

			<!-- ══ Scope bar ══════════════════════════════════════════════ -->
			<div class="pztc-card pztc-bulk-scope">
				<div class="pztc-bulk-scope__row">
					<label class="pztc-bulk-scope__label" for="pztc-bulk-type">
						<?php esc_html_e( 'Which ingredients?', 'pizzatier' ); ?>
					</label>
					<select id="pztc-bulk-type" class="pztc-select-input pztc-bulk-type">
						<option value="all"><?php esc_html_e( 'All ingredients (site-wide)', 'pizzatier' ); ?></option>
						<?php foreach ( $types as $t ) : ?>
							<option value="<?php echo esc_attr( $t['slug'] ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
						<?php endforeach; ?>
					</select>

					<label class="pztc-bulk-scope__check">
						<input type="checkbox" id="pztc-bulk-selected-only" />
						<?php esc_html_e( 'Only edit ticked items', 'pizzatier' ); ?>
					</label>

					<div class="pztc-bulk-scope__spacer"></div>

					<div class="pztc-bulk-scope__search-wrap">
						<span class="dashicons dashicons-search"></span>
						<input type="search" id="pztc-bulk-search" class="pztc-bulk-search"
							placeholder="<?php esc_attr_e( 'Filter by name…', 'pizzatier' ); ?>" />
					</div>
				</div>

				<p class="pztc-bulk-scope__hint">
					<?php esc_html_e( 'Type a price into any cell to set that item, or use a column\'s "Set all" box to fill every row at once. Blank cells count as $0.00. Nothing is saved until you click Save changes.', 'pizzatier' ); ?>
				</p>
			</div>

			<!-- ══ Tables mount ═══════════════════════════════════════════ -->
			<div id="pztc-bulk-mount" class="pztc-bulk-mount" aria-live="polite">
				<div class="pztc-bulk-loading"><span class="pztc-spinner"></span> <?php esc_html_e( 'Loading ingredients…', 'pizzatier' ); ?></div>
			</div>

			<!-- ══ Sticky save bar ════════════════════════════════════════ -->
			<div class="pztc-bulk-savebar" id="pztc-bulk-savebar">
				<div class="pztc-bulk-savebar__status" id="pztc-bulk-status">
					<span class="pztc-bulk-savebar__count" id="pztc-bulk-dirtycount">0</span>
					<?php esc_html_e( 'unsaved change(s)', 'pizzatier' ); ?>
				</div>
				<div class="pztc-bulk-savebar__actions">
					<button type="button" class="button" id="pztc-bulk-reset" disabled>
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Discard changes', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button button-primary pztc-save-btn" id="pztc-bulk-save" disabled>
						<?php esc_html_e( 'Save changes', 'pizzatier' ); ?>
					</button>
				</div>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Type list for the scope selector — shared by the page HTML and the
	 * client boot config.
	 *
	 * @return array<int,array{slug:string,label:string,fraction:bool}>
	 */
	private function scope_types(): array {
		$types = [];
		foreach ( $this->types() as $slug => $meta ) {
			$types[] = [ 'slug' => $slug, 'label' => $meta['label'], 'fraction' => (bool) $meta['fraction'] ];
		}
		return $types;
	}

	/**
	 * Boot config consumed by commerce-bulk-pricing.js as
	 * window.PIZZATIER_COMMERCE_BULK. Formerly printed by an inline
	 * bootstrap <script> during render; attached to the enqueued script
	 * via wp_add_inline_script() (WordPress.org compliance, v2.3.0).
	 *
	 * @return array<string,mixed>
	 */
	private function boot_data(): array {
		// get_woocommerce_currency_symbol() returns an HTML entity (e.g. "&#36;"
		// for USD, "&pound;" for GBP). It's meant to be printed as HTML so the
		// browser decodes it, but the editor inserts it via textContent (which
		// escapes it), so the raw entity — "&#36;" — would show in every input.
		// Decode to the actual character here so the client renders "$", "£", etc.
		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol() ?: '$', ENT_QUOTES, 'UTF-8' )
			: '$';

		return [

			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			'actions'  => [ 'load' => self::ACTION_LOAD, 'save' => self::ACTION_SAVE ],
			'currency' => $currency,
			'types'    => $this->scope_types(),
			'i18n'     => [
				'loading'      => __( 'Loading ingredients…', 'pizzatier' ),
				'noItems'      => __( 'No ingredients found for this type yet. Add some under the PizzaTier menu first.', 'pizzatier' ),
				'saving'       => __( 'Saving…', 'pizzatier' ),
				/* translators: %s: value inserted into the message. */
				'savedN'       => __( 'Saved %d ingredient(s).', 'pizzatier' ),
				/* translators: %s: value inserted into the message. */
				'clearedN'     => __( 'Reverted %d ingredient(s) to fallback pricing.', 'pizzatier' ),
				/* translators: %s: value inserted into the message. */
				'failedN'      => __( '%d ingredient(s) could not be saved.', 'pizzatier' ),
				'nothing'      => __( 'No changes to save.', 'pizzatier' ),
				'dirtyLeave'   => __( 'You have unsaved price changes. Leave this page and discard them?', 'pizzatier' ),
				'confirmClear' => __( 'Clear custom prices for this ingredient and revert to the fallback grid? This is saved when you click Save changes.', 'pizzatier' ),
				'fallback'     => __( 'Using fallback', 'pizzatier' ),
				'custom'       => __( 'Custom prices', 'pizzatier' ),
				'edited'       => __( 'Edited', 'pizzatier' ),
				'cleared'      => __( 'Will revert', 'pizzatier' ),
				/* translators: %s: value inserted into the message. */
				'cappedNote'   => __( 'Showing the first %d ingredients. Narrow the scope to a single type to see the rest.', 'pizzatier' ),
			],
		];
	}

	/**
	 * Enqueue the Bulk Pricing page stylesheet and editor script.
	 * Both were inline <style>/<script> blocks before v2.3.0.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'pizzatier_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		$v = PIZZATIER_VERSION;
		wp_enqueue_style(
			'pizzatier-commerce-admin',
			PIZZATIER_PLUGIN_URL . 'assets/css/admin.css',
			[],
			$v
		);
		wp_enqueue_style(
			'pizzatier-commerce-bulk-pricing',
			PIZZATIER_PLUGIN_URL . 'assets/css/admin/commerce-bulk-pricing.css',
			[ 'pizzatier-commerce-admin' ],
			$v
		);
		wp_enqueue_script(
			'pizzatier-commerce-bulk-pricing',
			PIZZATIER_PLUGIN_URL . 'assets/js/admin/commerce-bulk-pricing.js',
			[],
			$v,
			true
		);
		wp_add_inline_script(
			'pizzatier-commerce-bulk-pricing',
			'window.PIZZATIER_COMMERCE_BULK = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}
}
