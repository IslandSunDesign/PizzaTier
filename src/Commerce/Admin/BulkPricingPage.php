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
	 * registered by Dashboard::register_menus() (which owns the whole
	 * PizzaTier menu), so this runs from Plugin::init() to make sure the
	 * AJAX endpoints exist on admin-ajax.php requests too — those never fire
	 * admin_menu.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION_LOAD, [ $this, 'ajax_load_items' ] );
		add_action( 'wp_ajax_' . self::ACTION_SAVE, [ $this, 'ajax_save_prices' ] );
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

		// get_woocommerce_currency_symbol() returns an HTML entity (e.g. "&#36;"
		// for USD, "&pound;" for GBP). It's meant to be printed as HTML so the
		// browser decodes it, but the editor inserts it via textContent (which
		// escapes it), so the raw entity — "&#36;" — would show in every input.
		// Decode to the actual character here so the client renders "$", "£", etc.
		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol() ?: '$', ENT_QUOTES, 'UTF-8' )
			: '$';

		// Type list for the scope selector.
		$types = [];
		foreach ( $this->types() as $slug => $meta ) {
			$types[] = [ 'slug' => $slug, 'label' => $meta['label'], 'fraction' => (bool) $meta['fraction'] ];
		}

		$boot = [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
			'actions'  => [ 'load' => self::ACTION_LOAD, 'save' => self::ACTION_SAVE ],
			'currency' => $currency,
			'types'    => $types,
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
		?>
		<div class="wrap pztc-page-wrap pztc-bulk-page">

			<?php $this->render_styles(); ?>

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

			<script type="text/javascript">
				window.PIZZATIER_COMMERCE_BULK = <?php echo wp_json_encode( $boot ); ?>;
			</script>
			<?php $this->render_script(); ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		.pztc-bulk-page { max-width: 1180px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

		/* Header (mirrors the Pricing page header) */
		.pztc-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; background: linear-gradient(135deg,#1a1e23 0%,#2d3748 100%); border-radius: 10px; padding: 20px 24px; margin: 16px 0 20px; border-bottom: 3px solid #ff6b35; }
		.pztc-header__brand { display: flex; align-items: center; gap: 14px; }
		.pztc-header__icon { font-size: 30px !important; width: 30px !important; height: 30px !important; color: #ff6b35; }
		.pztc-header__title { margin: 0; color: #fff; font-size: 21px; font-weight: 700; line-height: 1.2; }
		.pztc-header__tagline { margin: 3px 0 0; color: rgba(255,255,255,.55); font-size: 12px; }
		.pztc-header__actions { display: flex; gap: 8px; flex-wrap: wrap; }
		.pztc-header__actions .button { display: inline-flex; align-items: center; gap: 4px; }

		.pztc-card { background: #fff; border: 1px solid #e0e3e7; border-radius: 10px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom: 18px; }

		/* Scope bar */
		.pztc-bulk-scope { padding: 16px 20px; }
		.pztc-bulk-scope__row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
		.pztc-bulk-scope__label { font-size: 13px; font-weight: 600; color: #1d2023; }
		.pztc-select-input { border: 1px solid #8c8f94; border-radius: 4px; font-size: 13px; padding: 6px 10px; background: #fff; min-width: 220px; }
		.pztc-select-input:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.15); }
		.pztc-bulk-scope__check { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #4b5563; user-select: none; }
		.pztc-bulk-scope__spacer { flex: 1 1 auto; }
		.pztc-bulk-scope__search-wrap { position: relative; display: inline-flex; align-items: center; }
		.pztc-bulk-scope__search-wrap .dashicons { position: absolute; left: 8px; color: #9ca3af; font-size: 16px; width: 16px; height: 16px; }
		.pztc-bulk-search { border: 1px solid #8c8f94; border-radius: 4px; font-size: 13px; padding: 6px 10px 6px 28px; min-width: 200px; }
		.pztc-bulk-search:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.15); }
		.pztc-bulk-scope__hint { margin: 12px 0 0; color: #646970; font-size: 12px; line-height: 1.5; }

		/* Mount + loading */
		.pztc-bulk-mount { margin-bottom: 90px; }
		.pztc-bulk-loading { display: flex; align-items: center; gap: 10px; padding: 40px; color: #646970; font-size: 13px; justify-content: center; }
		.pztc-spinner { width: 16px; height: 16px; border: 2px solid #e5e7eb; border-top-color: #ff6b35; border-radius: 50%; animation: pztc-spin .7s linear infinite; display: inline-block; }
		@keyframes pztc-spin { to { transform: rotate(360deg); } }

		.pztc-bulk-empty { padding: 40px; text-align: center; color: #646970; font-size: 13px; }

		/* Per-type section */
		.pztc-bulk-section { background: #fff; border: 1px solid #e0e3e7; border-radius: 10px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom: 20px; overflow: hidden; }
		.pztc-bulk-section__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px 18px; border-bottom: 1px solid #eef0f2; background: #fbfbfc; flex-wrap: wrap; }
		.pztc-bulk-section__title { margin: 0; font-size: 15px; font-weight: 700; color: #1d2023; display: flex; align-items: center; gap: 8px; }
		.pztc-bulk-section__title .dashicons { color: #ff6b35; }
		.pztc-bulk-section__count { font-size: 11px; font-weight: 600; color: #6b7280; background: #f3f4f6; border-radius: 20px; padding: 2px 9px; }
		.pztc-bulk-section__desc { margin: 3px 0 0; font-size: 12px; color: #646970; }
		.pztc-bulk-section__tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
		.pztc-bulk-section__tools .button { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; }
		.pztc-bulk-section__tools .dashicons { font-size: 15px !important; width: 15px !important; height: 15px !important; }

		.pztc-bulk-capnote { margin: 0; padding: 8px 18px; background: #fff7ed; color: #9a3412; font-size: 12px; border-bottom: 1px solid #fed7aa; }

		/* Table */
		.pztc-bulk-table-wrap { overflow-x: auto; }
		.pztc-bulk-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
		.pztc-bulk-table th, .pztc-bulk-table td { padding: 7px 8px; border-bottom: 1px solid #eef0f2; text-align: center; white-space: nowrap; }
		.pztc-bulk-table thead th { background: #f9fafb; font-size: 11px; font-weight: 700; color: #374151; position: sticky; top: 32px; z-index: 2; }
		.pztc-bulk-table thead tr.pztc-bulk-sizes th { text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid #e5e7eb; }
		.pztc-bulk-table thead tr.pztc-bulk-fracs th { font-size: 10px; color: #6b7280; font-weight: 600; padding-top: 3px; padding-bottom: 5px; }
		.pztc-bulk-col-size { border-left: 2px solid #f0e6df; }
		.pztc-bulk-th-item { text-align: left; min-width: 200px; position: sticky; left: 0; background: #f9fafb; z-index: 3; }
		.pztc-bulk-td-item { text-align: left; position: sticky; left: 0; background: #fff; z-index: 1; }
		.pztc-bulk-table tbody tr:hover .pztc-bulk-td-item { background: #fff9f5; }
		.pztc-bulk-table tbody tr:hover td { background: #fff9f5; }
		.pztc-bulk-table tbody tr.is-hidden { display: none; }

		.pztc-bulk-itemcell { display: flex; align-items: center; gap: 8px; }
		.pztc-bulk-itemcell__name { font-weight: 600; color: #1d2023; }
		.pztc-bulk-check { margin: 0; }

		.pztc-bulk-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 20px; text-transform: uppercase; letter-spacing: .02em; }
		.pztc-bulk-badge--fallback { background: #fff4e5; color: #b26a00; }
		.pztc-bulk-badge--custom   { background: #e7f6ec; color: #1a7a3e; }
		.pztc-bulk-badge--edited   { background: #fdecef; color: #b4245a; }
		.pztc-bulk-badge--cleared  { background: #eef1f4; color: #52606d; }

		.pztc-bulk-rowclear { border: none; background: transparent; color: #b91c1c; cursor: pointer; padding: 2px; border-radius: 4px; line-height: 1; opacity: .55; }
		.pztc-bulk-rowclear:hover { opacity: 1; background: #fee2e2; }
		.pztc-bulk-rowclear .dashicons { font-size: 16px; width: 16px; height: 16px; }

		/* Price inputs */
		.pztc-price-cell { position: relative; }
		.pztc-price-input { width: 74px; padding: 4px 6px 4px 16px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 13px; text-align: right; background: #fff; transition: border-color .12s, box-shadow .12s; }
		.pztc-price-input:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.15); }
		.pztc-price-input.is-edited { border-color: #ff6b35; background: #fff8f4; font-weight: 600; }
		.pztc-price-cell__cur { position: absolute; left: 5px; top: 50%; transform: translateY(-50%); font-size: 11px; color: #9ca3af; pointer-events: none; }
		tr.is-cleared .pztc-price-input { opacity: .4; text-decoration: line-through; }

		/* Fill row (Set all) */
		tr.pztc-bulk-fillrow th, tr.pztc-bulk-fillrow td { background: #fcfaf8; border-bottom: 1px dashed #e5d9cf; padding-top: 6px; padding-bottom: 6px; }
		.pztc-bulk-fill-label { text-align: left; font-size: 11px; font-weight: 700; color: #9a5a2a; text-transform: uppercase; letter-spacing: .03em; }
		.pztc-bulk-fill-input { width: 74px; padding: 4px 6px 4px 16px; border: 1px dashed #d8b48f; border-radius: 5px; font-size: 12px; text-align: right; background: #fff; }
		.pztc-bulk-fill-input:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.12); }

		/* Sticky save bar */
		.pztc-bulk-savebar { position: fixed; left: 160px; right: 0; bottom: 0; z-index: 9990; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; background: #fff; border-top: 1px solid #e0e3e7; box-shadow: 0 -4px 16px rgba(16,24,40,.06); transform: translateY(0); transition: transform .2s; }
		.folded-menu .pztc-bulk-savebar { left: 36px; }
		@media (max-width: 960px) { .pztc-bulk-savebar { left: 0; } }
		.pztc-bulk-savebar__status { font-size: 13px; color: #52606d; }
		.pztc-bulk-savebar__count { display: inline-block; min-width: 22px; text-align: center; font-weight: 700; color: #fff; background: #ff6b35; border-radius: 20px; padding: 1px 8px; margin-right: 4px; }
		.pztc-bulk-savebar__count.is-zero { background: #c3c4c7; }
		.pztc-bulk-savebar__actions { display: flex; align-items: center; gap: 10px; }
		.pztc-bulk-savebar__actions .button { display: inline-flex; align-items: center; gap: 5px; }
		.pztc-save-btn.button-primary { background: #ff6b35 !important; border-color: #cf5519 !important; color: #fff !important; height: 34px; font-weight: 600; padding: 0 20px !important; }
		.pztc-save-btn.button-primary:hover:not(:disabled) { background: #cf5519 !important; }
		.pztc-save-btn.button-primary:disabled { background: #f3b39c !important; border-color: #f3b39c !important; cursor: default; }

		/* Toast + inline result */
		.pztc-bulk-toast { position: fixed; right: 24px; bottom: 74px; z-index: 9995; max-width: 380px; background: #1d2023; color: #fff; font-size: 13px; padding: 12px 16px; border-radius: 8px; box-shadow: 0 8px 30px rgba(16,24,40,.25); opacity: 0; transform: translateY(8px); transition: opacity .2s, transform .2s; pointer-events: none; }
		.pztc-bulk-toast.is-shown { opacity: 1; transform: translateY(0); }
		.pztc-bulk-toast--ok { border-left: 4px solid #16a34a; }
		.pztc-bulk-toast--warn { border-left: 4px solid #f59e0b; }
		.pztc-bulk-toast--err { border-left: 4px solid #ef4444; }
		.pztc-bulk-errlist { margin: 6px 0 0; padding-left: 16px; font-size: 12px; color: #fca5a5; }
		.pztc-bulk-errlist li { margin: 2px 0; }

		@media (max-width: 782px) {
			.pztc-header { flex-direction: column; align-items: flex-start; }
			.pztc-bulk-th-item, .pztc-bulk-td-item { min-width: 150px; }
		}
		@media (prefers-reduced-motion: reduce) {
			.pztc-spinner { animation: none; }
			.pztc-bulk-toast { transition: none; }
		}
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Client script (self-contained — no build step, no external deps)
	// -------------------------------------------------------------------------

	private function render_script(): void {
		?>
		<script type="text/javascript">
		( function () {
			'use strict';
			var CFG = window.PIZZATIER_COMMERCE_BULK || {};
			var SEP = '|';

			var mount   = document.getElementById( 'pztc-bulk-mount' );
			var typeSel = document.getElementById( 'pztc-bulk-type' );
			var selOnly = document.getElementById( 'pztc-bulk-selected-only' );
			var search  = document.getElementById( 'pztc-bulk-search' );
			var saveBtn = document.getElementById( 'pztc-bulk-save' );
			var resetBtn= document.getElementById( 'pztc-bulk-reset' );
			var dirtyEl = document.getElementById( 'pztc-bulk-dirtycount' );

			// In-memory model of what's currently rendered: type slug -> payload.
			var MODEL = {};

			function cellKey( size, frac ) { return size + SEP + frac; }
			function esc( s ) { var d = document.createElement( 'div' ); d.textContent = s == null ? '' : String( s ); return d.innerHTML; }
			function sprintf1( tpl, n ) { return String( tpl ).replace( '%d', n ); }

			function ajax( action, extra ) {
				var body = new URLSearchParams();
				body.set( 'action', CFG.actions[ action ] );
				body.set( 'nonce', CFG.nonce );
				Object.keys( extra || {} ).forEach( function ( k ) { body.set( k, extra[ k ] ); } );
				return fetch( CFG.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} ).then( function ( r ) { return r.json(); } );
			}

			// ── Load a scope ──────────────────────────────────────────────
			function load() {
				mount.innerHTML = '<div class="pztc-bulk-loading"><span class="pztc-spinner"></span> ' + esc( CFG.i18n.loading ) + '</div>';
				ajax( 'load', { type: typeSel.value } ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						mount.innerHTML = '<div class="pztc-bulk-empty">' + esc( ( res && res.data && res.data.message ) || 'Error' ) + '</div>';
						return;
					}
					MODEL = {};
					mount.innerHTML = '';
					var any = false;
					res.data.types.forEach( function ( t ) {
						MODEL[ t.type ] = t;
						mount.appendChild( renderSection( t ) );
						if ( t.items.length ) { any = true; }
					} );
					if ( ! any ) {
						mount.innerHTML = '<div class="pztc-bulk-section"><div class="pztc-bulk-empty">' + esc( CFG.i18n.noItems ) + '</div></div>';
					}
					applyFilter();
					recount();
				} ).catch( function () {
					mount.innerHTML = '<div class="pztc-bulk-empty">Network error. Please reload.</div>';
				} );
			}

			// ── Render one type section ───────────────────────────────────
			function renderSection( t ) {
				var sizes = t.sizes, fracs = t.fractions, isFrac = t.is_fraction;
				var cols = [];
				sizes.forEach( function ( s ) { fracs.forEach( function ( f ) { cols.push( { size: s, frac: f } ); } ); } );

				var sec = document.createElement( 'div' );
				sec.className = 'pztc-bulk-section';
				sec.setAttribute( 'data-type', t.type );

				// Head
				var head = '<div class="pztc-bulk-section__head">' +
					'<div><h2 class="pztc-bulk-section__title"><span class="dashicons dashicons-grid-view"></span>' +
					esc( t.label ) + ' <span class="pztc-bulk-section__count">' + t.items.length + '</span></h2>' +
					'<p class="pztc-bulk-section__desc">' + esc( t.desc ) + '</p></div>' +
					'<div class="pztc-bulk-section__tools">' +
					'<button type="button" class="button pztc-bulk-selall"><span class="dashicons dashicons-yes"></span>' + esc( 'Tick all' ) + '</button>' +
					'<button type="button" class="button pztc-bulk-clearcol"><span class="dashicons dashicons-dismiss"></span>' + esc( 'Revert all' ) + '</button>' +
					'</div></div>';

				var capNote = t.capped ? '<p class="pztc-bulk-capnote">' + esc( sprintf1( CFG.i18n.cappedNote, t.cap ) ) + '</p>' : '';

				// Header rows
				var thItem = '<th class="pztc-bulk-th-item"><label class="pztc-bulk-itemcell"><input type="checkbox" class="pztc-bulk-check pztc-bulk-checkall" /> ' + esc( 'Ingredient' ) + '</label></th>';
				var sizeRow = '<tr class="pztc-bulk-sizes">' + thItem;
				sizes.forEach( function ( s ) {
					sizeRow += '<th class="pztc-bulk-col-size" colspan="' + fracs.length + '">' + esc( s ) + '</th>';
				} );
				sizeRow += '<th></th></tr>';

				var fracRow = '';
				if ( isFrac ) {
					fracRow = '<tr class="pztc-bulk-fracs"><th class="pztc-bulk-th-item"></th>';
					sizes.forEach( function ( s ) {
						fracs.forEach( function ( f, i ) {
							fracRow += '<th class="' + ( i === 0 ? 'pztc-bulk-col-size' : '' ) + '">' + esc( f ) + '</th>';
						} );
					} );
					fracRow += '<th></th></tr>';
				}

				// Fill row ("Set all" per column)
				var fillRow = '<tr class="pztc-bulk-fillrow"><th class="pztc-bulk-th-item pztc-bulk-fill-label">' + esc( 'Set all →' ) + '</th>';
				cols.forEach( function ( c, i ) {
					var firstOfSize = ( i % fracs.length === 0 );
					fillRow += '<td class="' + ( firstOfSize ? 'pztc-bulk-col-size' : '' ) + '">' +
						'<input type="text" inputmode="decimal" class="pztc-bulk-fill-input" data-key="' + esc( cellKey( c.size, c.frac ) ) + '" placeholder="—" aria-label="' + esc( 'Set all ' + c.size + ' ' + c.frac ) + '" />' +
						'</td>';
				} );
				fillRow += '<td></td></tr>';

				// Body
				var body = '';
				t.items.forEach( function ( it ) {
					body += renderRow( t, it, cols, fracs.length );
				} );

				sec.innerHTML = head + capNote +
					'<div class="pztc-bulk-table-wrap"><table class="pztc-bulk-table"><thead>' +
					sizeRow + fracRow + fillRow +
					'</thead><tbody>' + body + '</tbody></table></div>';

				wireSection( sec, t, cols );
				return sec;
			}

			function renderRow( t, it, cols, fracCount ) {
				var cells = ( it.cells && typeof it.cells === 'object' ) ? it.cells : {};
				var globals = ( t.global_cells && typeof t.global_cells === 'object' ) ? t.global_cells : {};
				var badge = it.has_grid
					? '<span class="pztc-bulk-badge pztc-bulk-badge--custom" data-role="badge">' + esc( CFG.i18n.custom ) + '</span>'
					: '<span class="pztc-bulk-badge pztc-bulk-badge--fallback" data-role="badge">' + esc( CFG.i18n.fallback ) + '</span>';

				var row = '<tr data-id="' + it.id + '" data-hasgrid="' + ( it.has_grid ? '1' : '0' ) + '">' +
					'<td class="pztc-bulk-td-item"><div class="pztc-bulk-itemcell">' +
						'<input type="checkbox" class="pztc-bulk-check pztc-bulk-rowcheck" />' +
						'<span class="pztc-bulk-itemcell__name">' + esc( it.title ) + '</span>' +
						badge +
						'<button type="button" class="pztc-bulk-rowclear" title="' + esc( CFG.i18n.confirmClear ) + '"><span class="dashicons dashicons-trash"></span></button>' +
					'</div></td>';

				cols.forEach( function ( c, i ) {
					var key = cellKey( c.size, c.frac );
					var val = ( cells[ key ] != null ) ? cells[ key ] : '';
					var ph  = ( globals[ key ] != null ) ? globals[ key ] : '0.00';
					var firstOfSize = ( i % fracCount === 0 );
					row += '<td class="pztc-price-cell ' + ( firstOfSize ? 'pztc-bulk-col-size' : '' ) + '">' +
						'<span class="pztc-price-cell__cur">' + esc( CFG.currency ) + '</span>' +
						'<input type="text" inputmode="decimal" class="pztc-price-input" ' +
							'data-key="' + esc( key ) + '" data-orig="' + esc( val ) + '" ' +
							'value="' + esc( val ) + '" placeholder="' + esc( ph ) + '" ' +
							'aria-label="' + esc( it.title + ' ' + c.size + ' ' + c.frac ) + '" />' +
					'</td>';
				} );
				row += '<td></td></tr>';
				return row;
			}

			// ── Wire interactions for a section ───────────────────────────
			function wireSection( sec, t, cols ) {
				// Price input edits
				sec.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
					inp.addEventListener( 'input', function () { markInput( inp ); recount(); } );
					inp.addEventListener( 'blur', function () { normalise( inp ); } );
				} );

				// Per-column "Set all"
				sec.querySelectorAll( '.pztc-bulk-fill-input' ).forEach( function ( fill ) {
					fill.addEventListener( 'change', function () { fillColumn( sec, fill.getAttribute( 'data-key' ), fill.value ); } );
					fill.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { fillColumn( sec, fill.getAttribute( 'data-key' ), fill.value ); } } );
				} );

				// Row clear buttons
				sec.querySelectorAll( '.pztc-bulk-rowclear' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () { toggleClear( btn.closest( 'tr' ) ); } );
				} );

				// Tick-all in header + tools
				var checkAll = sec.querySelector( '.pztc-bulk-checkall' );
				var selAll   = sec.querySelector( '.pztc-bulk-selall' );
				var clearAll = sec.querySelector( '.pztc-bulk-clearcol' );
				function setAllChecks( on ) {
					sec.querySelectorAll( '.pztc-bulk-rowcheck' ).forEach( function ( c ) {
						if ( ! c.closest( 'tr' ).classList.contains( 'is-hidden' ) ) { c.checked = on; }
					} );
					if ( checkAll ) { checkAll.checked = on; }
				}
				if ( checkAll ) { checkAll.addEventListener( 'change', function () { setAllChecks( checkAll.checked ); } ); }
				if ( selAll )   { selAll.addEventListener( 'click', function () { setAllChecks( true ); } ); }
				if ( clearAll ) {
					clearAll.addEventListener( 'click', function () {
						sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
							if ( tr.classList.contains( 'is-hidden' ) ) { return; }
							if ( selOnly.checked && ! tr.querySelector( '.pztc-bulk-rowcheck' ).checked ) { return; }
							if ( tr.getAttribute( 'data-hasgrid' ) === '1' && ! tr.classList.contains( 'is-cleared' ) ) { toggleClear( tr ); }
						} );
						recount();
					} );
				}
			}

			// ── Editing helpers ───────────────────────────────────────────
			function markInput( inp ) {
				var tr = inp.closest( 'tr' );
				if ( tr.classList.contains( 'is-cleared' ) ) { return; } // ignore edits on rows queued for revert
				var changed = ( inp.value.trim() !== ( inp.getAttribute( 'data-orig' ) || '' ).trim() );
				inp.classList.toggle( 'is-edited', changed );
				refreshRowBadge( tr );
			}

			function normalise( inp ) {
				var v = inp.value.trim().replace( /[^0-9.]/g, '' );
				if ( v === '' ) { inp.value = ''; markInput( inp ); return; }
				var n = parseFloat( v );
				if ( isNaN( n ) || n < 0 ) { n = 0; }
				inp.value = n.toFixed( 2 );
				markInput( inp );
			}

			function fillColumn( sec, key, raw ) {
				var v = String( raw ).trim().replace( /[^0-9.]/g, '' );
				if ( v === '' ) { return; }
				var val = parseFloat( v ); if ( isNaN( val ) || val < 0 ) { val = 0; }
				val = val.toFixed( 2 );
				sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
					if ( tr.classList.contains( 'is-hidden' ) || tr.classList.contains( 'is-cleared' ) ) { return; }
					if ( selOnly.checked && ! tr.querySelector( '.pztc-bulk-rowcheck' ).checked ) { return; }
					var inp = tr.querySelector( '.pztc-price-input[data-key="' + cssEsc( key ) + '"]' );
					if ( inp ) { inp.value = val; markInput( inp ); }
				} );
				recount();
			}

			function toggleClear( tr ) {
				var willClear = ! tr.classList.contains( 'is-cleared' );
				// Only meaningful if the item currently has a saved grid.
				if ( willClear && tr.getAttribute( 'data-hasgrid' ) !== '1' ) { return; }
				tr.classList.toggle( 'is-cleared', willClear );
				refreshRowBadge( tr );
				recount();
			}

			function refreshRowBadge( tr ) {
				var badge = tr.querySelector( '[data-role="badge"]' );
				if ( ! badge ) { return; }
				badge.className = 'pztc-bulk-badge';
				badge.setAttribute( 'data-role', 'badge' );
				if ( tr.classList.contains( 'is-cleared' ) ) {
					badge.classList.add( 'pztc-bulk-badge--cleared' ); badge.textContent = CFG.i18n.cleared;
				} else if ( rowEdited( tr ) ) {
					badge.classList.add( 'pztc-bulk-badge--edited' ); badge.textContent = CFG.i18n.edited;
				} else if ( tr.getAttribute( 'data-hasgrid' ) === '1' ) {
					badge.classList.add( 'pztc-bulk-badge--custom' ); badge.textContent = CFG.i18n.custom;
				} else {
					badge.classList.add( 'pztc-bulk-badge--fallback' ); badge.textContent = CFG.i18n.fallback;
				}
			}

			function rowEdited( tr ) {
				return !! tr.querySelector( '.pztc-price-input.is-edited' );
			}
			function rowDirty( tr ) {
				return tr.classList.contains( 'is-cleared' ) || rowEdited( tr );
			}

			function cssEsc( s ) { return String( s ).replace( /["\\]/g, '\\$&' ); }

			// ── Dirty accounting + save enable ────────────────────────────
			function dirtyRows() {
				return Array.prototype.filter.call( document.querySelectorAll( '.pztc-bulk-table tbody tr' ), rowDirty );
			}
			function recount() {
				var n = dirtyRows().length;
				dirtyEl.textContent = n;
				dirtyEl.classList.toggle( 'is-zero', n === 0 );
				saveBtn.disabled = ( n === 0 );
				resetBtn.disabled = ( n === 0 );
			}

			// ── Filtering ─────────────────────────────────────────────────
			function applyFilter() {
				var q = ( search.value || '' ).trim().toLowerCase();
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					var name = tr.querySelector( '.pztc-bulk-itemcell__name' );
					var show = ! q || ( name && name.textContent.toLowerCase().indexOf( q ) !== -1 );
					tr.classList.toggle( 'is-hidden', ! show );
				} );
			}

			// ── Collect + save ────────────────────────────────────────────
			function collect() {
				var payload = [];
				Object.keys( MODEL ).forEach( function ( type ) {
					var t = MODEL[ type ];
					var sec = mount.querySelector( '.pztc-bulk-section[data-type="' + cssEsc( type ) + '"]' );
					if ( ! sec ) { return; }
					sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
						if ( ! rowDirty( tr ) ) { return; }
						var id = parseInt( tr.getAttribute( 'data-id' ), 10 );
						if ( tr.classList.contains( 'is-cleared' ) ) {
							payload.push( { post_id: id, clear: true } );
							return;
						}
						var cells = {};
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							var key = inp.getAttribute( 'data-key' );
							var v = inp.value.trim();
							cells[ key ] = ( v === '' ) ? 0 : parseFloat( v );
						} );
						payload.push( { post_id: id, sizes: t.sizes, fractions: t.fractions, cells: cells } );
					} );
				} );
				return payload;
			}

			function save() {
				var payload = collect();
				if ( ! payload.length ) { toast( CFG.i18n.nothing, 'warn' ); return; }
				saveBtn.disabled = true; saveBtn.textContent = CFG.i18n.saving;
				ajax( 'save', { items: JSON.stringify( payload ) } ).then( function ( res ) {
					saveBtn.textContent = 'Save changes';
					if ( ! res || ! res.success ) {
						toast( ( res && res.data && res.data.message ) || 'Save failed', 'err' );
						saveBtn.disabled = false;
						return;
					}
					var d = res.data;
					// Commit successfully-saved rows into the new baseline in place.
					commitSaved();
					var parts = [];
					if ( d.saved )   { parts.push( sprintf1( CFG.i18n.savedN, d.saved ) ); }
					if ( d.cleared ) { parts.push( sprintf1( CFG.i18n.clearedN, d.cleared ) ); }
					var kind = 'ok';
					var extra = '';
					if ( d.failed ) {
						parts.push( sprintf1( CFG.i18n.failedN, d.failed ) );
						kind = d.saved || d.cleared ? 'warn' : 'err';
						if ( d.errors && d.errors.length ) {
							extra = '<ul class="pztc-bulk-errlist">' + d.errors.slice( 0, 6 ).map( function ( e ) {
								return '<li>' + esc( e.title ) + ': ' + esc( e.message ) + '</li>';
							} ).join( '' ) + '</ul>';
						}
					}
					toast( ( parts.join( ' ' ) || 'Done' ) + extra, kind, !! extra );
					recount();
				} ).catch( function () {
					saveBtn.textContent = 'Save changes';
					saveBtn.disabled = false;
					toast( 'Network error while saving.', 'err' );
				} );
			}

			// After a save, treat current values as the saved baseline (no reload).
			function commitSaved() {
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					if ( tr.classList.contains( 'is-cleared' ) ) {
						// Reverted to fallback: clear values + baseline.
						tr.classList.remove( 'is-cleared' );
						tr.setAttribute( 'data-hasgrid', '0' );
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							inp.value = ''; inp.setAttribute( 'data-orig', '' ); inp.classList.remove( 'is-edited' );
						} );
						refreshRowBadge( tr );
					} else if ( rowEdited( tr ) ) {
						tr.setAttribute( 'data-hasgrid', '1' );
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							inp.setAttribute( 'data-orig', inp.value.trim() );
							inp.classList.remove( 'is-edited' );
						} );
						refreshRowBadge( tr );
					}
				} );
			}

			function reset() {
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					tr.classList.remove( 'is-cleared' );
					tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
						inp.value = inp.getAttribute( 'data-orig' ) || '';
						inp.classList.remove( 'is-edited' );
					} );
					refreshRowBadge( tr );
				} );
				recount();
			}

			// ── Toast ─────────────────────────────────────────────────────
			var toastEl;
			function toast( html, kind, sticky ) {
				if ( ! toastEl ) {
					toastEl = document.createElement( 'div' );
					toastEl.className = 'pztc-bulk-toast';
					document.body.appendChild( toastEl );
				}
				toastEl.className = 'pztc-bulk-toast pztc-bulk-toast--' + ( kind || 'ok' );
				toastEl.innerHTML = html;
				requestAnimationFrame( function () { toastEl.classList.add( 'is-shown' ); } );
				clearTimeout( toastEl._t );
				toastEl._t = setTimeout( function () { toastEl.classList.remove( 'is-shown' ); }, sticky ? 9000 : 4000 );
			}

			// ── Events ────────────────────────────────────────────────────
			typeSel.addEventListener( 'change', function () {
				if ( dirtyRows().length && ! window.confirm( CFG.i18n.dirtyLeave ) ) {
					return;
				}
				load();
			} );
			search.addEventListener( 'input', applyFilter );
			saveBtn.addEventListener( 'click', save );
			resetBtn.addEventListener( 'click', reset );

			window.addEventListener( 'beforeunload', function ( e ) {
				if ( dirtyRows().length ) { e.preventDefault(); e.returnValue = ''; }
			} );

			// Reflect collapsed admin menu for the sticky bar offset.
			if ( document.body.classList.contains( 'folded' ) ) {
				document.body.classList.add( 'folded-menu' );
			}

			// Initial load.
			load();
		} )();
		</script>
		<?php
	}
}
