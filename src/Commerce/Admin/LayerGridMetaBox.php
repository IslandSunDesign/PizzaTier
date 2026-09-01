<?php
/**
 * LayerGridMetaBox — per-ingredient pricing grid on all PizzaTier CPT edit screens.
 *
 * Adds a "Pricing Grid" meta box to all 7 PizzaTier CPT post types:
 *   pizzatier_toppings, pizzatier_crusts, pizzatier_sauces,
 *   pizzatier_cheeses, pizzatier_drizzles, pizzatier_cuts, pizzatier_sizes
 *
 * Each layer item can carry its own size × coverage price table stored as
 * _pizzatier_commerce_layer_grid post meta.  When set, PriceCalculator uses this grid
 * in preference to the product-level fallback grid.
 *
 * UI design:
 *   - Header badge: green "Custom prices set" or orange "Using product fallback"
 *   - Single size × coverage table (same layout as the product price grid)
 *   - Add Size / Add Coverage toolbar buttons
 *   - "Clear custom prices" button to delete the layer grid and revert to fallback
 *
 * Assets: reuses pizzatier-commerce-admin (admin.css) and the price-grid JS that
 * ProductTab already enqueues on product edit screens.  On CPT edit screens we
 * enqueue the same handles so the table interactions work identically.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceGrid\Grid;
use PizzaTier\Commerce\PriceGrid\GridRenderer;

class LayerGridMetaBox {

	/** All 7 PizzaTier CPT slugs that receive a pricing grid meta box. */
	const APPLICABLE_TYPES = [
		'pizzatier_toppings',
		'pizzatier_crusts',
		'pizzatier_sauces',
		'pizzatier_cheeses',
		'pizzatier_drizzles',
		'pizzatier_cuts',
		'pizzatier_sizes',
	];

	const NONCE_ACTION = 'pizzatier_commerce_layer_grid_save';
	const NONCE_FIELD  = '_pizzatier_commerce_layer_grid_nonce';

	/** @var Grid */
	private $grid;

	public function __construct( Grid $grid ) {
		$this->grid = $grid;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'add_meta_boxes',        [ $this, 'add_meta_box' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	public function add_meta_box(): void {
		foreach ( self::APPLICABLE_TYPES as $post_type ) {
			add_meta_box(
				'pizzatier_commerce_layer_pricing_grid',
				'<span class="dashicons dashicons-grid-view" style="color:#ff6b35;margin-right:6px;vertical-align:middle;"></span>'
					. __( 'Pricing Grid', 'pizzatier' ),
				[ $this, 'render' ],
				$post_type,
				'normal',
				'high'
			);
		}
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render( \WP_Post $post ): void {
		$post_id    = $post->ID;
		$has_grid   = $this->grid->has_layer_grid( $post_id );
		$layer_grid = $this->grid->get_layer_grid( $post_id );

		// Resolve the matching global grid for this CPT (if any). The CPT slug
		// is 'pizzatier_toppings' / 'pizzatier_crusts' / etc.; strip the
		// 'pizzatier_' prefix to get the canonical global-grid layer-type
		// slug ('toppings', 'crusts', …) used by Grid::*_global_layer_grid().
		$global_slug   = preg_replace( '/^pizzatier_/', '', (string) $post->post_type );
		$global_grid   = $this->grid->get_global_layer_grid( $global_slug );
		$has_global    = null !== $global_grid;

		// Resolve display values:
		//  • If a per-ingredient grid is saved → use it as-is (it always wins).
		//  • Else if a global grid is configured → adopt its sizes/fractions so
		//    placeholders line up with the same cells the engine will use when
		//    saving leaves the cell blank.
		//  • Else fall back to the plugin-wide default size/fraction labels.
		if ( $layer_grid ) {
			$sizes     = $layer_grid['sizes'];
			$fractions = $layer_grid['fractions'];
			$cells     = $layer_grid['cells'];
		} elseif ( $has_global ) {
			$sizes     = $global_grid['sizes'];
			$fractions = $global_grid['fractions'];
			$cells     = [];
		} else {
			$sizes     = $this->grid->default_sizes();
			$fractions = $this->grid->default_fractions();
			$cells     = [];
		}

		// Build the per-cell placeholders map from the global grid (if any).
		// Keys are cell_key( $size, $fraction ); values are pre-formatted
		// "1.50"-style strings ready for the input's placeholder attribute.
		$placeholders = [];
		if ( $has_global ) {
			$gcells = isset( $global_grid['cells'] ) && is_array( $global_grid['cells'] ) ? $global_grid['cells'] : [];
			foreach ( $sizes as $sz ) {
				foreach ( $fractions as $fr ) {
					$ck = $this->grid->cell_key( $sz, $fr );
					if ( isset( $gcells[ $ck ] ) && '' !== $gcells[ $ck ] && null !== $gcells[ $ck ] ) {
						$placeholders[ $ck ] = number_format( (float) $gcells[ $ck ], 2, '.', '' );
					}
				}
			}
		}

		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? ( get_woocommerce_currency_symbol() ?: '$' )
			: '$';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="pztc-layer-grid-wrap"
		     data-global-slug="<?php echo esc_attr( $global_slug ); ?>"
		     data-has-global="<?php echo $has_global ? '1' : '0'; ?>">

			<?php $this->render_status_badge( $has_grid, $has_global ); ?>

			<p class="pztc-field-description" style="margin:0 0 12px;">
				<?php esc_html_e(
					'Set prices for this ingredient by size and coverage. When saved, these override the product-level fallback grid for this specific ingredient.',
					'pizzatier'
				); ?>
			</p>

			<!-- Toolbar: Add Size / Add Coverage / Clear / Bulk tools -->
			<div class="pztc-grid-toolbar pztc-layer-grid-toolbar">
				<div class="pztc-grid-toolbar__left">
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-add-size-row">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Size', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-add-fraction-col">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Coverage', 'pizzatier' ); ?>
					</button>
				</div>
				<div class="pztc-grid-toolbar__right">
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-copy-csv"
						title="<?php esc_attr_e( 'Copy current grid as CSV text to clipboard', 'pizzatier' ); ?>">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy CSV', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-paste-csv-toggle"
						title="<?php esc_attr_e( 'Paste CSV text to populate the grid', 'pizzatier' ); ?>">
						<span class="dashicons dashicons-editor-paste-text"></span>
						<?php esc_html_e( 'Paste CSV', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-copy-product-toggle"
						title="<?php esc_attr_e( 'Copy pricing grid from a Pizza product', 'pizzatier' ); ?>">
						<span class="dashicons dashicons-admin-page"></span>
						<?php esc_html_e( 'Copy from Product', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-set-all-toggle"
						title="<?php esc_attr_e( 'Set all cells to the same price', 'pizzatier' ); ?>">
						<span class="dashicons dashicons-editor-table"></span>
						<?php esc_html_e( 'Set All', 'pizzatier' ); ?>
					</button>
					<?php if ( $has_grid ) : ?>
						<button type="button"
							class="button pztc-btn-sm pztc-btn--danger"
							id="pztc-layer-grid-clear"
							title="<?php esc_attr_e( 'Remove custom prices — this ingredient will use the product-level fallback grid', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear custom prices', 'pizzatier' ); ?>
						</button>
						<input type="hidden" name="pizzatier_commerce_layer_grid_clear" id="pztc-layer-grid-clear-flag" value="0" />
					<?php endif; ?>
				</div>
			</div>

			<!-- Paste CSV panel -->
			<div class="pztc-grid-panel" id="pztc-layer-paste-csv-panel" style="display:none;">
				<p class="pztc-panel-label">
					<strong><?php esc_html_e( 'Paste CSV text', 'pizzatier' ); ?></strong>
					<span class="pztc-panel-hint"><?php esc_html_e( 'First row: Size header + coverage columns. Subsequent rows: size name + prices.', 'pizzatier' ); ?></span>
				</p>
				<textarea id="pztc-layer-paste-csv-text" rows="5"
					placeholder="Size,Whole,Half,Quarter&#10;Small,1.50,0.80,0.45&#10;Medium,2.00,1.10,0.60"
					style="width:100%;font-family:monospace;font-size:12px;resize:vertical;"></textarea>
				<div class="pztc-panel-actions">
					<button type="button" class="button button-primary pztc-btn-sm" id="pztc-layer-paste-csv-apply">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Apply', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-paste-csv-cancel">
						<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
					</button>
				</div>
			</div>

			<!-- Copy from product panel -->
			<div class="pztc-grid-panel" id="pztc-layer-copy-product-panel" style="display:none;">
				<p class="pztc-panel-label">
					<strong><?php esc_html_e( 'Copy from a Pizza product', 'pizzatier' ); ?></strong>
					<span class="pztc-panel-hint"><?php esc_html_e( 'Replaces the current grid with the selected product\'s fallback pricing grid.', 'pizzatier' ); ?></span>
				</p>
				<div class="pztc-panel-row">
					<select id="pztc-layer-copy-product-select" style="min-width:220px;">
						<option value=""><?php esc_html_e( '— Loading products… —', 'pizzatier' ); ?></option>
					</select>
					<button type="button" class="button button-primary pztc-btn-sm" id="pztc-layer-copy-product-apply" disabled>
						<span class="dashicons dashicons-admin-page"></span>
						<?php esc_html_e( 'Copy Grid', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-copy-product-cancel">
						<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
					</button>
				</div>
			</div>

			<!-- Set all panel -->
			<div class="pztc-grid-panel" id="pztc-layer-set-all-panel" style="display:none;">
				<p class="pztc-panel-label">
					<strong><?php esc_html_e( 'Set all cells to the same price', 'pizzatier' ); ?></strong>
				</p>
				<div class="pztc-panel-row">
					<span class="pztc-cell-currency pztc-set-all-currency" id="pztc-layer-set-all-currency-sym"><?php echo esc_html( $currency ); ?></span>
					<input type="number" id="pztc-layer-set-all-value" min="0" step="0.01" placeholder="0.00"
						style="width:100px;" />
					<button type="button" class="button button-primary pztc-btn-sm" id="pztc-layer-set-all-apply">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Apply to All', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button pztc-btn-sm" id="pztc-layer-set-all-cancel">
						<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
					</button>
				</div>
			</div>

			<!-- Grid table -->
			<div class="pztc-grid-wrap" id="pztc-layer-grid-table-wrap">
				<?php
				$renderer = new GridRenderer( $this->grid );
				$renderer->render_layer_table( $sizes, $fractions, $cells, $currency, $placeholders );
				?>
			</div>

			<p class="pztc-field-description" style="margin-top:8px;">
				<?php esc_html_e( 'Coverage labels must match those configured in the product-level fallback grid (e.g. Whole, Half, Quarter).', 'pizzatier' ); ?>
			</p>

		</div><!-- .pztc-layer-grid-wrap -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Status badge
	// -------------------------------------------------------------------------

	/**
	 * Render the status badge.
	 *
	 * Three states:
	 *  • Green — a per-ingredient grid is saved (always wins)
	 *  • Blue  — no per-ingredient grid, but a global per-layer-type grid is
	 *            configured on the Pricing page and will be used as the
	 *            fallback (its prices are shown as placeholders in the
	 *            cells below)
	 *  • Orange — no per-ingredient grid and no global grid; the engine
	 *             will fall through to the product-level grid (or no charge
	 *             when nothing is configured anywhere)
	 *
	 * @param bool $has_grid   Whether a per-ingredient _pizzatier_commerce_layer_grid is saved.
	 * @param bool $has_global Whether a global grid for this layer type exists.
	 */
	private function render_status_badge( bool $has_grid, bool $has_global = false ): void {
		if ( $has_grid ) {
			$color   = '#16a34a';
			$icon    = 'dashicons-yes-alt';
			$message = __( 'Custom prices set — this ingredient uses its own pricing grid.', 'pizzatier' );
		} elseif ( $has_global ) {
			$color   = '#2563eb';
			$icon    = 'dashicons-admin-site-alt3';
			$message = __( 'Using global fallback — prices below are inherited from the site-wide Pricing page. Override individual cells here if you want this ingredient to differ.', 'pizzatier' );
		} else {
			$color   = '#d97706';
			$icon    = 'dashicons-info-outline';
			$message = __( 'Using product fallback — no custom or global prices set for this ingredient yet.', 'pizzatier' );
		}
		?>
		<p id="pztc-layer-grid-badge" class="pztc-layer-grid-badge" style="
			display:flex;align-items:center;gap:6px;
			margin:0 0 10px;padding:7px 10px;
			background:<?php echo esc_attr( $color ); ?>1a;
			border-left:3px solid <?php echo esc_attr( $color ); ?>;
			border-radius:3px;font-size:13px;color:<?php echo esc_attr( $color ); ?>;
		">
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color:<?php echo esc_attr( $color ); ?>;"></span>
			<?php echo esc_html( $message ); ?>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Inline JS — wire toolbar buttons to the layer grid table
	// -------------------------------------------------------------------------

	/**
	 * Build the boot payload consumed by commerce-layer-grid-metabox.js.
	 *
	 * Attached to the enqueued script via wp_add_inline_script() as
	 * `window.pizzatierLayerGridData` — replaces the former inline <script>
	 * block (WordPress.org compliance, v2.3.0). Derives the same
	 * sizes / fractions / cells / placeholders the meta box HTML renders,
	 * so the client model always matches the table.
	 *
	 * @param \WP_Post $post The ingredient post being edited.
	 * @return string JSON payload.
	 */
	private function boot_json( \WP_Post $post ): string {
		$post_id    = $post->ID;
		$layer_grid = $this->grid->get_layer_grid( $post_id );

		$global_slug = preg_replace( '/^pizzatier_/', '', (string) $post->post_type );
		$global_grid = $this->grid->get_global_layer_grid( $global_slug );
		$has_global  = null !== $global_grid;

		if ( $layer_grid ) {
			$sizes     = $layer_grid['sizes'];
			$fractions = $layer_grid['fractions'];
			$cells     = $layer_grid['cells'];
		} elseif ( $has_global ) {
			$sizes     = $global_grid['sizes'];
			$fractions = $global_grid['fractions'];
			$cells     = [];
		} else {
			$sizes     = $this->grid->default_sizes();
			$fractions = $this->grid->default_fractions();
			$cells     = [];
		}

		$placeholders = [];
		if ( $has_global ) {
			$gcells = isset( $global_grid['cells'] ) && is_array( $global_grid['cells'] ) ? $global_grid['cells'] : [];
			foreach ( $sizes as $sz ) {
				foreach ( $fractions as $fr ) {
					$ck = $this->grid->cell_key( $sz, $fr );
					if ( isset( $gcells[ $ck ] ) && '' !== $gcells[ $ck ] && null !== $gcells[ $ck ] ) {
						$placeholders[ $ck ] = number_format( (float) $gcells[ $ck ], 2, '.', '' );
					}
				}
			}
		}

		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? ( get_woocommerce_currency_symbol() ?: '$' )
			: '$';

		return (string) wp_json_encode( [
			'postId'           => $post_id,
			'sizes'            => array_values( $sizes ),
			'fractions'        => array_values( $fractions ),
			'cells'            => (object) $cells,
			'currency'         => $currency,
			'placeholders'     => (object) $placeholders,
			'globalSlug'       => $global_slug,
			'hasGlobal'        => $has_global,
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'pizzatier_commerce_admin' ),
			'i18n'             => [
				'newSize'              => __( 'New Size', 'pizzatier' ),
				'newFraction'          => __( 'New Coverage', 'pizzatier' ),
				'confirmRemoveRow'     => __( 'Remove this size row?', 'pizzatier' ),
				'confirmRemoveCol'     => __( 'Remove this coverage column?', 'pizzatier' ),
				'confirmClear'         => __( 'Remove all custom prices for this ingredient? It will revert to the global or product-level fallback grid.', 'pizzatier' ),
				'badgeCustom'          => __( 'Custom prices set — this ingredient uses its own pricing grid.', 'pizzatier' ),
				'badgeGlobal'          => __( 'Using global fallback — prices below are inherited from the site-wide Pricing page. Override individual cells here if you want this ingredient to differ.', 'pizzatier' ),
				'badgeFallback'        => __( 'Using product fallback — no custom or global prices set for this ingredient yet.', 'pizzatier' ),
				'copyCsvSuccess'       => __( 'Grid CSV copied to clipboard.', 'pizzatier' ),
				'copyCsvFail'          => __( 'Could not copy to clipboard.', 'pizzatier' ),
				'pasteCsvSuccess'      => __( 'Grid applied from pasted CSV. Review and save.', 'pizzatier' ),
				'pasteCsvError'        => __( 'CSV error: ', 'pizzatier' ),
				'copyProductNone'      => __( 'Please select a product.', 'pizzatier' ),
				'copyProductSuccess'   => __( 'Grid copied from product. Review and save.', 'pizzatier' ),
				'copyProductError'     => __( 'Could not load that product\'s grid.', 'pizzatier' ),
				'confirmCopyProduct'   => __( 'Replace the current grid with the selected product\'s grid?', 'pizzatier' ),
				'setAllSuccess'        => __( 'All cells updated.', 'pizzatier' ),
				'setAllNoValue'        => __( 'Please enter a price.', 'pizzatier' ),
				'loadingProducts'      => __( '— Loading… —', 'pizzatier' ),
				'noGridProducts'       => __( '— No Pizza products found —', 'pizzatier' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin.css on the 7 CPT edit screens.
	 * No shared grid script is enqueued here — the product-
	 * screen IDs (#pztc-grid-table etc.) and would conflict.  The layer grid
	 * uses its own IDs and the inline script handles all interactions.
	 *
	 * @param string $hook  Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::APPLICABLE_TYPES, true ) ) {
			return;
		}

		$url = PIZZATIER_PLUGIN_URL;
		$ver = PIZZATIER_VERSION;

		// Admin stylesheet — shared with ProductTab; enqueue with same handle
		// so WordPress deduplicates if both are on the same page (unlikely but safe).
		wp_enqueue_style(
			'pizzatier-commerce-admin',
			$url . 'assets/css/admin.css',
			[],
			$ver
		);

		// Meta box interactions — formerly an inline <script> block in the
		// meta box render; moved to a file per WordPress.org guidelines.
		wp_enqueue_script(
			'pizzatier-commerce-layer-grid',
			$url . 'assets/js/admin/commerce-layer-grid-metabox.js',
			[],
			$ver,
			true
		);

		$post = get_post();
		if ( $post instanceof \WP_Post ) {
			wp_add_inline_script(
				'pizzatier-commerce-layer-grid',
				'window.pizzatierLayerGridData = ' . $this->boot_json( $post ) . ';',
				'before'
			);
		}
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save the layer grid from $_POST on post save.
	 *
	 * Runs on the generic save_post hook and bails early for autosaves,
	 * revisions, wrong post types, missing nonce, and insufficient capability.
	 *
	 * Handles two actions:
	 *   1. pizzatier_commerce_layer_grid_clear = 1 → delete the layer grid meta entirely
	 *   2. pizzatier_commerce_layer_grid[...] present → validate and save
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// ── Guards ─────────────────────────────────────────────────────────────

		// Nonce verification — also gates autosave / REST / programmatic saves.
		if (
			! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) )                return;

		if ( ! in_array( $post->post_type, self::APPLICABLE_TYPES, true ) ) return;

		// Capability: edit_post on this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// ── Clear action ────────────────────────────────────────────────────────

		$clear = isset( $_POST['pizzatier_commerce_layer_grid_clear'] )
			? absint( $_POST['pizzatier_commerce_layer_grid_clear'] )
			: 0;

		if ( $clear ) {
			$this->grid->delete_layer_grid( $post_id );
			return;
		}

		// ── Save action ─────────────────────────────────────────────────────────

		// Bail if the grid form fields are absent (e.g. a quick-edit save that
		// doesn't render the meta box).
		if ( ! isset( $_POST['pizzatier_commerce_layer_grid'] ) || ! is_array( $_POST['pizzatier_commerce_layer_grid'] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['pizzatier_commerce_layer_grid'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Normalise the raw POST array into the shape Grid::validate() expects.
		// Sizes and fractions arrive as indexed arrays; cells as an associative
		// array keyed by "Size|Fraction".
		$data = [
			'sizes'     => isset( $raw['sizes'] )     && is_array( $raw['sizes'] )     ? $raw['sizes']     : [],
			'fractions' => isset( $raw['fractions'] ) && is_array( $raw['fractions'] ) ? $raw['fractions'] : [],
			'cells'     => isset( $raw['cells'] )     && is_array( $raw['cells'] )     ? $raw['cells']     : [],
		];

		// Bail silently if both sizes and fractions are empty — the admin cleared
		// the table without clicking "Clear custom prices", so treat as a no-op.
		if ( empty( $data['sizes'] ) && empty( $data['fractions'] ) ) {
			return;
		}

		$result = $this->grid->save_layer_grid( $post_id, $data );

		// On validation error, store the error message in a transient so it can
		// be surfaced as an admin notice on the next page load.
		if ( is_wp_error( $result ) ) {
			set_transient(
				'pizzatier_commerce_layer_grid_error_' . $post_id,
				$result->get_error_message(),
				45
			);
		}
	}
}
