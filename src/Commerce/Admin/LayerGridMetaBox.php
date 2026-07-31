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

		<?php $this->render_inline_js( $post_id, $sizes, $fractions, $cells, $currency, $placeholders ); ?>
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
	 * Output a small inline script that wires Add Size, Add Coverage, and
	 * Clear buttons to the layer grid table.
	 *
	 * The grid markup uses #pztc-grid-table and #pztc-grid-wrap;
	 * the layer grid uses its own IDs so both can coexist on a page without
	 * conflict. This script handles the layer-specific IDs directly.
	 *
	 * @param int                 $post_id
	 * @param string[]            $sizes
	 * @param string[]            $fractions
	 * @param array<string,float> $cells
	 * @param string              $currency
	 */
	private function render_inline_js(
		int $post_id,
		array $sizes,
		array $fractions,
		array $cells,
		string $currency,
		array $placeholders = []
	): void {
		// Resolve the global-grid context once so the JS can use it when
		// adding new rows / columns or rebuilding the table from CSV / a
		// copied product grid.
		$global_slug = '';
		$has_global  = false;
		$post_obj    = get_post( $post_id );
		if ( $post_obj instanceof \WP_Post ) {
			$global_slug = preg_replace( '/^pizzatier_/', '', (string) $post_obj->post_type );
			$has_global  = $this->grid->has_global_layer_grid( $global_slug );
		}
		$data = wp_json_encode( [
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
		?>
		<script>
		/* PizzaTier — Layer grid meta box interactions */
		( function () {
			'use strict';

			var DATA         = <?php echo $data; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
			var currency     = DATA.currency || '$';
			var i18n         = DATA.i18n || {};
			var AJAX         = DATA.ajaxUrl || '';
			var NONCE        = DATA.nonce || '';
			var PLACEHOLDERS = DATA.placeholders || {};
			var HAS_GLOBAL   = !! DATA.hasGlobal;
			var layerProductsLoaded = false;

			// Per-cell placeholder lookup. Falls back to '0.00' when the
			// global grid has no value for this cell — keeps the input
			// looking the same as before when no global fallback applies.
			function placeholderFor( key ) {
				if ( PLACEHOLDERS && Object.prototype.hasOwnProperty.call( PLACEHOLDERS, key ) ) {
					var v = PLACEHOLDERS[ key ];
					if ( v !== '' && v !== null && typeof v !== 'undefined' ) {
						return String( v );
					}
				}
				return '0.00';
			}

			// ── Helpers ────────────────────────────────────────────────────────

			function cellKey( size, fraction ) {
				return size + '|' + fraction;
			}

			function getCurrentSizes() {
				var sizes = [];
				document.querySelectorAll( '#pztc-layer-grid-table .pztc-size-label-input' ).forEach( function ( inp ) {
					if ( inp.value ) sizes.push( inp.value );
				} );
				return sizes;
			}

			function getCurrentFractions() {
				var fractions = [];
				document.querySelectorAll( '#pztc-layer-grid-table .pztc-fraction-label-input' ).forEach( function ( inp ) {
					if ( inp.value ) fractions.push( inp.value );
				} );
				return fractions;
			}

			function makePriceCell( size, fraction, value ) {
				var key = cellKey( size, fraction );
				var td  = document.createElement( 'td' );
				td.className = 'pztc-grid-cell';
				td.setAttribute( 'data-size', size );
				td.setAttribute( 'data-fraction', fraction );
				td.innerHTML =
					'<div class="pztc-cell-wrap">' +
					'<span class="pztc-cell-currency">' + currency + '</span>' +
					'<input type="number"' +
					' name="pizzatier_commerce_layer_grid[cells][' + key + ']"' +
					' value="' + ( value || '' ) + '"' +
					' class="pztc-price-input"' +
					' min="0" step="0.01" placeholder="' + escAttr( placeholderFor( key ) ) + '"' +
					' data-key="' + key + '" />' +
					'</div>';
				return td;
			}

			function makeRemoveBtn( attr, val, labelAttr ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'pztc-grid-remove-btn';
				btn.setAttribute( attr, val );
				btn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
				return btn;
			}

			// ── Add Size row ────────────────────────────────────────────────────

			var addSizeBtn = document.getElementById( 'pztc-layer-add-size-row' );
			if ( addSizeBtn ) {
				addSizeBtn.addEventListener( 'click', function () {
					var label = prompt( i18n.newSize || 'New Size' );
					if ( ! label ) return;
					label = label.trim();
					if ( ! label ) return;

					var fractions = getCurrentFractions();
					var tbody = document.querySelector( '#pztc-layer-grid-table tbody' );
					if ( ! tbody ) return;

					var tr = document.createElement( 'tr' );
					tr.className = 'pztc-grid-row';
					tr.setAttribute( 'data-size', label );

					// Row header cell
					var th = document.createElement( 'th' );
					th.className = 'pztc-grid-th pztc-grid-th--size';
					var wrap = document.createElement( 'div' );
					wrap.className = 'pztc-header-cell';

					var span = document.createElement( 'span' );
					span.className = 'pztc-header-label pztc-editable-label';
					span.setAttribute( 'contenteditable', 'true' );
					span.setAttribute( 'data-type', 'size' );
					span.setAttribute( 'data-original', label );
					span.textContent = label;

					var hiddenInput = document.createElement( 'input' );
					hiddenInput.type = 'hidden';
					hiddenInput.name = 'pizzatier_commerce_layer_grid[sizes][]';
					hiddenInput.value = label;
					hiddenInput.className = 'pztc-size-label-input';

					var removeBtn = makeRemoveBtn( 'data-size', label );
					removeBtn.className += ' pztc-remove-row';
					removeBtn.addEventListener( 'click', function () {
						if ( confirm( i18n.confirmRemoveRow || 'Remove this row?' ) ) {
							tr.remove();
						}
					} );

					// Wire contenteditable → hidden input sync
					span.addEventListener( 'input', function () {
						hiddenInput.value = span.textContent.trim();
						tr.setAttribute( 'data-size', span.textContent.trim() );
						// Re-key all cell inputs in this row
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							var frac = inp.closest( 'td' ).getAttribute( 'data-fraction' );
							var newKey = cellKey( span.textContent.trim(), frac );
							inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
							inp.setAttribute( 'data-key', newKey );
						} );
					} );

					wrap.appendChild( span );
					wrap.appendChild( hiddenInput );
					wrap.appendChild( removeBtn );
					th.appendChild( wrap );
					tr.appendChild( th );

					// Price cells for each existing fraction column
					fractions.forEach( function ( frac ) {
						tr.appendChild( makePriceCell( label, frac, '' ) );
					} );

					tbody.appendChild( tr );
				} );
			}

			// ── Add Coverage column ─────────────────────────────────────────────

			var addFracBtn = document.getElementById( 'pztc-layer-add-fraction-col' );
			if ( addFracBtn ) {
				addFracBtn.addEventListener( 'click', function () {
					var label = prompt( i18n.newFraction || 'New Coverage' );
					if ( ! label ) return;
					label = label.trim();
					if ( ! label ) return;

					var table = document.getElementById( 'pztc-layer-grid-table' );
					if ( ! table ) return;

					// Add header <th>
					var headerRow = table.querySelector( 'thead tr' );
					if ( ! headerRow ) return;

					var th = document.createElement( 'th' );
					th.className = 'pztc-grid-th pztc-grid-th--fraction';
					th.setAttribute( 'data-fraction', label );

					var wrap = document.createElement( 'div' );
					wrap.className = 'pztc-header-cell';

					var span = document.createElement( 'span' );
					span.className = 'pztc-header-label pztc-editable-label';
					span.setAttribute( 'contenteditable', 'true' );
					span.setAttribute( 'data-type', 'fraction' );
					span.setAttribute( 'data-original', label );
					span.textContent = label;

					var hiddenInput = document.createElement( 'input' );
					hiddenInput.type  = 'hidden';
					hiddenInput.name  = 'pizzatier_commerce_layer_grid[fractions][]';
					hiddenInput.value = label;
					hiddenInput.className = 'pztc-fraction-label-input';

					var removeBtn = makeRemoveBtn( 'data-fraction', label );
					removeBtn.className += ' pztc-remove-col';
					removeBtn.addEventListener( 'click', function () {
						if ( confirm( i18n.confirmRemoveCol || 'Remove this column?' ) ) {
							var frac = label;
							th.remove();
							table.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
								var cell = row.querySelector( 'td[data-fraction="' + frac + '"]' );
								if ( cell ) cell.remove();
							} );
						}
					} );

					// Sync contenteditable → hidden input + re-key cells in this column
					span.addEventListener( 'input', function () {
						var newLabel = span.textContent.trim();
						hiddenInput.value = newLabel;
						th.setAttribute( 'data-fraction', newLabel );
						table.querySelectorAll( 'tbody td[data-fraction="' + label + '"]' ).forEach( function ( td ) {
							var size   = td.getAttribute( 'data-size' );
							var newKey = cellKey( size, newLabel );
							td.setAttribute( 'data-fraction', newLabel );
							var inp = td.querySelector( '.pztc-price-input' );
							if ( inp ) {
								inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
								inp.setAttribute( 'data-key', newKey );
							}
						} );
						label = newLabel;
					} );

					wrap.appendChild( span );
					wrap.appendChild( hiddenInput );
					wrap.appendChild( removeBtn );
					th.appendChild( wrap );
					headerRow.appendChild( th );

					// Add price cell to every existing body row
					var sizes = getCurrentSizes();
					var bodyRows = table.querySelectorAll( 'tbody tr' );
					bodyRows.forEach( function ( row, idx ) {
						row.appendChild( makePriceCell( sizes[ idx ] || '', label, '' ) );
					} );
				} );
			}

			// ── Remove row / column (delegate for initial rows) ─────────────────

			var tableWrap = document.getElementById( 'pztc-layer-grid-table-wrap' );
			if ( tableWrap ) {
				tableWrap.addEventListener( 'click', function ( e ) {
					var btn = e.target.closest( '.pztc-remove-row' );
					if ( btn ) {
						if ( confirm( i18n.confirmRemoveRow || 'Remove this row?' ) ) {
							var row = btn.closest( 'tr' );
							if ( row ) row.remove();
						}
						return;
					}
					btn = e.target.closest( '.pztc-remove-col' );
					if ( btn ) {
						if ( confirm( i18n.confirmRemoveCol || 'Remove this column?' ) ) {
							var frac  = btn.getAttribute( 'data-fraction' );
							var table = document.getElementById( 'pztc-layer-grid-table' );
							if ( ! table ) return;
							var colTh = table.querySelector( 'th[data-fraction="' + frac + '"]' );
							if ( colTh ) colTh.remove();
							table.querySelectorAll( 'td[data-fraction="' + frac + '"]' ).forEach( function ( td ) {
								td.remove();
							} );
						}
					}
				} );

				// Sync contenteditable size/fraction labels → hidden inputs (initial rows)
				tableWrap.addEventListener( 'input', function ( e ) {
					var span = e.target.closest( '.pztc-editable-label' );
					if ( ! span ) return;
					var type   = span.getAttribute( 'data-type' );
					var newVal = span.textContent.trim();

					if ( type === 'size' ) {
						var hiddenInp = span.closest( 'th' ).querySelector( '.pztc-size-label-input' );
						if ( hiddenInp ) hiddenInp.value = newVal;
						var row = span.closest( 'tr' );
						if ( row ) {
							row.setAttribute( 'data-size', newVal );
							row.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
								var frac   = inp.closest( 'td' ).getAttribute( 'data-fraction' );
								var newKey = cellKey( newVal, frac );
								inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
								inp.setAttribute( 'data-key', newKey );
							} );
						}
					} else if ( type === 'fraction' ) {
						var hiddenInp = span.closest( 'th' ).querySelector( '.pztc-fraction-label-input' );
						if ( hiddenInp ) hiddenInp.value = newVal;
						var oldFrac = span.getAttribute( 'data-original' );
						var table   = document.getElementById( 'pztc-layer-grid-table' );
						if ( table ) {
							table.querySelectorAll( 'td[data-fraction="' + oldFrac + '"]' ).forEach( function ( td ) {
								td.setAttribute( 'data-fraction', newVal );
								var inp = td.querySelector( '.pztc-price-input' );
								if ( inp ) {
									var size   = td.getAttribute( 'data-size' );
									var newKey = cellKey( size, newVal );
									inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
									inp.setAttribute( 'data-key', newKey );
								}
							} );
							var colTh = table.querySelector( 'th[data-fraction="' + oldFrac + '"]' );
							if ( colTh ) colTh.setAttribute( 'data-fraction', newVal );
						}
						span.setAttribute( 'data-original', newVal );
					}
				} );
			}

			// ── Clear custom prices ─────────────────────────────────────────────

			var clearBtn  = document.getElementById( 'pztc-layer-grid-clear' );
			var clearFlag = document.getElementById( 'pztc-layer-grid-clear-flag' );
			if ( clearBtn && clearFlag ) {
				clearBtn.addEventListener( 'click', function () {
					if ( ! confirm( i18n.confirmClear || 'Remove all custom prices for this ingredient?' ) ) {
						return;
					}
					clearFlag.value = '1';

					// Visually zero-out all cells so the admin can see the state.
					document.querySelectorAll( '#pztc-layer-grid-table .pztc-price-input' ).forEach( function ( inp ) {
						inp.value = '';
					} );

					// Update badge to fallback state. Prefer the global-fallback
					// styling/text when a site-wide grid exists for this layer
					// type — that's where the engine will actually source
					// prices once this clear is saved.
					var badge = document.getElementById( 'pztc-layer-grid-badge' );
					if ( badge ) {
						var fbColor, fbIcon, fbText;
						if ( HAS_GLOBAL ) {
							fbColor = '#2563eb';
							fbIcon  = 'dashicons-admin-site-alt3';
							fbText  = i18n.badgeGlobal || 'Using global fallback.';
						} else {
							fbColor = '#d97706';
							fbIcon  = 'dashicons-info-outline';
							fbText  = i18n.badgeFallback || 'Using product fallback.';
						}
						badge.style.background  = fbColor + '1a';
						badge.style.borderColor = fbColor;
						badge.style.color       = fbColor;
						var icon = badge.querySelector( '.dashicons' );
						if ( icon ) {
							icon.className     = 'dashicons ' + fbIcon;
							icon.style.color   = fbColor;
						}
						badge.childNodes[ badge.childNodes.length - 1 ].textContent = ' ' + fbText;
					}

					clearBtn.disabled = true;
				} );
			}

			// ── Feedback helper ────────────────────────────────────────────────

			function showFeedback( message, type ) {
				var fb = document.getElementById( 'pztc-layer-grid-feedback' );
				if ( ! fb ) {
					fb = document.createElement( 'div' );
					fb.id = 'pztc-layer-grid-feedback';
					fb.className = 'pztc-grid-import-feedback';
					fb.style.cssText = 'display:none;';
					var wrap = document.getElementById( 'pztc-layer-grid-table-wrap' );
					if ( wrap && wrap.parentNode ) {
						wrap.parentNode.insertBefore( fb, wrap );
					}
				}
				fb.className = 'pztc-grid-import-feedback is-' + type;
				fb.textContent = message;
				fb.style.display = 'block';
				if ( type === 'success' ) {
					setTimeout( function () { fb.style.display = 'none'; }, 5000 );
				}
			}

			// ── Panel helpers ──────────────────────────────────────────────────

			function closePanels( keepId ) {
				var panels = [
					'pztc-layer-paste-csv-panel',
					'pztc-layer-copy-product-panel',
					'pztc-layer-set-all-panel',
				];
				panels.forEach( function ( id ) {
					if ( id !== keepId ) {
						var el = document.getElementById( id );
						if ( el ) el.style.display = 'none';
					}
				} );
			}

			function togglePanel( id ) {
				var el = document.getElementById( id );
				if ( ! el ) return;
				closePanels( id );
				el.style.display = ( el.style.display === 'none' || el.style.display === '' ) ? 'block' : 'none';
			}

			// ── CSV builder from current layer grid ────────────────────────────

			function buildCsvFromLayerGrid() {
				var table = document.getElementById( 'pztc-layer-grid-table' );
				if ( ! table ) return '';

				var fractions = [];
				table.querySelectorAll( 'thead tr th.pztc-grid-th--fraction' ).forEach( function ( th ) {
					fractions.push( th.getAttribute( 'data-fraction' ) || '' );
				} );

				var rows = [ [ 'Size' ].concat( fractions ) ];

				table.querySelectorAll( 'tbody tr.pztc-grid-row' ).forEach( function ( tr ) {
					var size = tr.getAttribute( 'data-size' ) || '';
					var row  = [ size ];
					fractions.forEach( function ( frac ) {
						var cell = tr.querySelector( 'td[data-fraction="' + frac + '"]' );
						var inp  = cell ? cell.querySelector( '.pztc-price-input' ) : null;
						row.push( inp ? ( inp.value || '0.00' ) : '0.00' );
					} );
					rows.push( row );
				} );

				if ( rows.length < 2 ) return '';

				return rows.map( function ( row ) {
					return row.map( function ( cell ) {
						var s = String( cell );
						if ( s.indexOf( ',' ) !== -1 || s.indexOf( '"' ) !== -1 ) {
							return '"' + s.replace( /"/g, '""' ) + '"';
						}
						return s;
					} ).join( ',' );
				} ).join( '\n' );
			}

			// ── AJAX helper ────────────────────────────────────────────────────

			function ajaxPost( params, success, fail ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', AJAX );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function () {
					try {
						var resp = JSON.parse( xhr.responseText );
						if ( resp && resp.success ) {
							success( resp );
						} else {
							fail( resp );
						}
					} catch ( e ) {
						fail( null );
					}
				};
				xhr.onerror = function () { fail( null ); };

				var body = Object.keys( params ).map( function ( k ) {
					return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
				} ).join( '&' );
				xhr.send( body );
			}

			// ── Rebuild layer grid table from data ─────────────────────────────

			function rebuildLayerGrid( sizes, fractions, cells ) {
				var wrap  = document.getElementById( 'pztc-layer-grid-table-wrap' );
				if ( ! wrap ) return;

				// Build header.
				var theadHtml = '<thead><tr>';
				theadHtml += '<th class="pztc-grid-corner"><span class="pztc-grid-corner-row">Size</span><span class="pztc-grid-corner-sep">&#8595;/&#8594;</span><span class="pztc-grid-corner-col">Coverage</span></th>';
				fractions.forEach( function ( frac ) {
					var safe = escAttr( frac );
					theadHtml += '<th class="pztc-grid-th pztc-grid-th--fraction" data-fraction="' + safe + '">'
						+ '<div class="pztc-header-cell">'
						+ '<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="fraction" data-original="' + safe + '">' + escHtml( frac ) + '</span>'
						+ '<input type="hidden" name="pizzatier_commerce_layer_grid[fractions][]" value="' + safe + '" class="pztc-fraction-label-input" />'
						+ '<button type="button" class="pztc-remove-col pztc-grid-remove-btn"><span class="dashicons dashicons-no-alt"></span></button>'
						+ '</div></th>';
				} );
				theadHtml += '</tr></thead>';

				// Build tbody.
				var tbodyHtml = '<tbody>';
				sizes.forEach( function ( size ) {
					var safeSize = escAttr( size );
					tbodyHtml += '<tr class="pztc-grid-row" data-size="' + safeSize + '">'
						+ '<th class="pztc-grid-th pztc-grid-th--size">'
						+ '<div class="pztc-header-cell">'
						+ '<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="size" data-original="' + safeSize + '">' + escHtml( size ) + '</span>'
						+ '<input type="hidden" name="pizzatier_commerce_layer_grid[sizes][]" value="' + safeSize + '" class="pztc-size-label-input" />'
						+ '<button type="button" class="pztc-remove-row pztc-grid-remove-btn"><span class="dashicons dashicons-no-alt"></span></button>'
						+ '</div></th>';
					fractions.forEach( function ( frac ) {
						var key   = size + '|' + frac;
						var price = cells && cells[ key ] !== undefined ? cells[ key ] : '';
						tbodyHtml += '<td class="pztc-grid-cell" data-size="' + escAttr( size ) + '" data-fraction="' + escAttr( frac ) + '">'
							+ '<div class="pztc-cell-wrap">'
							+ '<span class="pztc-cell-currency">' + escHtml( currency ) + '</span>'
							+ '<input type="number" name="pizzatier_commerce_layer_grid[cells][' + escAttr( key ) + ']"'
							+ ' value="' + escAttr( price ) + '"'
							+ ' class="pztc-price-input" min="0" step="0.01" placeholder="' + escAttr( placeholderFor( key ) ) + '"'
							+ ' data-key="' + escAttr( key ) + '" />'
							+ '</div></td>';
					} );
					tbodyHtml += '</tr>';
				} );
				tbodyHtml += '</tbody>';

				var newTable = document.createElement( 'div' );
				newTable.className = 'pztc-grid-table-container';
				newTable.innerHTML = '<table class="pztc-grid-table pztc-grid-table--layer" id="pztc-layer-grid-table">' + theadHtml + tbodyHtml + '</table>';

				var oldContainer = wrap.querySelector( '.pztc-grid-table-container' );
				if ( oldContainer ) {
					wrap.replaceChild( newTable, oldContainer );
				} else {
					wrap.appendChild( newTable );
				}
			}

			function escHtml( str ) {
				return String( str )
					.replace( /&/g, '&amp;' )
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' )
					.replace( /"/g, '&quot;' )
					.replace( /'/g, '&#039;' );
			}
			function escAttr( str ) { return escHtml( str ); }

			// ── Copy CSV ────────────────────────────────────────────────────────

			var copyCsvBtn = document.getElementById( 'pztc-layer-copy-csv' );
			if ( copyCsvBtn ) {
				copyCsvBtn.addEventListener( 'click', function () {
					var csv = buildCsvFromLayerGrid();
					if ( ! csv ) {
						showFeedback( i18n.copyCsvFail || 'Nothing to copy.', 'error' );
						return;
					}
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( csv ).then( function () {
							showFeedback( i18n.copyCsvSuccess || 'Copied to clipboard.', 'success' );
						} ).catch( function () {
							showFeedback( i18n.copyCsvFail || 'Could not copy.', 'error' );
						} );
					} else {
						var ta = document.createElement( 'textarea' );
						ta.value = csv;
						ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
						document.body.appendChild( ta );
						ta.select();
						try {
							document.execCommand( 'copy' );
							showFeedback( i18n.copyCsvSuccess || 'Copied to clipboard.', 'success' );
						} catch ( e ) {
							showFeedback( i18n.copyCsvFail || 'Could not copy.', 'error' );
						}
						document.body.removeChild( ta );
					}
				} );
			}

			// ── Paste CSV ───────────────────────────────────────────────────────

			var pasteCsvToggle = document.getElementById( 'pztc-layer-paste-csv-toggle' );
			if ( pasteCsvToggle ) {
				pasteCsvToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-paste-csv-panel' );
				} );
			}

			var pasteCsvCancel = document.getElementById( 'pztc-layer-paste-csv-cancel' );
			if ( pasteCsvCancel ) {
				pasteCsvCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-paste-csv-panel' ).style.display = 'none';
					document.getElementById( 'pztc-layer-paste-csv-text' ).value = '';
				} );
			}

			var pasteCsvApply = document.getElementById( 'pztc-layer-paste-csv-apply' );
			if ( pasteCsvApply ) {
				pasteCsvApply.addEventListener( 'click', function () {
					var csvText = document.getElementById( 'pztc-layer-paste-csv-text' ).value.trim();
					if ( ! csvText ) {
						showFeedback( i18n.pasteCsvError || 'No CSV text.', 'error' );
						return;
					}
					pasteCsvApply.disabled = true;

					ajaxPost(
						{ action: 'pizzatier_commerce_validate_csv_text', nonce: NONCE, csv_text: csvText },
						function ( resp ) {
							var d = resp.data;
							rebuildLayerGrid( d.sizes, d.fractions, d.cells );
							showFeedback( i18n.pasteCsvSuccess || 'Grid applied. Review and save.', 'success' );
							document.getElementById( 'pztc-layer-paste-csv-panel' ).style.display = 'none';
							document.getElementById( 'pztc-layer-paste-csv-text' ).value = '';
							pasteCsvApply.disabled = false;
						},
						function ( resp ) {
							var msg = ( resp && resp.data && resp.data.message ) || 'Error.';
							showFeedback( ( i18n.pasteCsvError || 'Error: ' ) + msg, 'error' );
							pasteCsvApply.disabled = false;
						}
					);
				} );
			}

			// ── Copy from Product ───────────────────────────────────────────────

			var copyProdToggle = document.getElementById( 'pztc-layer-copy-product-toggle' );
			if ( copyProdToggle ) {
				copyProdToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-copy-product-panel' );

					if ( ! layerProductsLoaded ) {
						layerProductsLoaded = true;
						var sel  = document.getElementById( 'pztc-layer-copy-product-select' );
						var applyBtn = document.getElementById( 'pztc-layer-copy-product-apply' );
						sel.disabled = true;
						applyBtn.disabled = true;

						ajaxPost(
							{ action: 'pizzatier_commerce_get_pizza_products', nonce: NONCE, exclude_id: 0 },
							function ( resp ) {
								var products = resp.data.products || [];
								sel.innerHTML = '';
								if ( products.length ) {
									var opt0 = document.createElement( 'option' );
									opt0.value = '';
									opt0.textContent = '— Select a product —';
									sel.appendChild( opt0 );
									products.forEach( function ( p ) {
										var opt = document.createElement( 'option' );
										opt.value = p.id;
										opt.textContent = p.title + ( p.hasGrid ? '' : ' (no grid)' );
										sel.appendChild( opt );
									} );
								} else {
									var optNone = document.createElement( 'option' );
									optNone.value = '';
									optNone.textContent = i18n.noGridProducts || '— No Pizza products found —';
									sel.appendChild( optNone );
								}
								sel.disabled = false;
								applyBtn.disabled = false;
							},
							function () {
								sel.innerHTML = '<option value="">— Error loading products —</option>';
								sel.disabled = false;
							}
						);
					}
				} );
			}

			var copyProdCancel = document.getElementById( 'pztc-layer-copy-product-cancel' );
			if ( copyProdCancel ) {
				copyProdCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-copy-product-panel' ).style.display = 'none';
				} );
			}

			var copyProdApply = document.getElementById( 'pztc-layer-copy-product-apply' );
			if ( copyProdApply ) {
				copyProdApply.addEventListener( 'click', function () {
					var sourceId = parseInt( document.getElementById( 'pztc-layer-copy-product-select' ).value, 10 );
					if ( ! sourceId ) {
						showFeedback( i18n.copyProductNone || 'Please select a product.', 'error' );
						return;
					}
					if ( ! confirm( i18n.confirmCopyProduct || 'Replace the current grid?' ) ) {
						return;
					}
					copyProdApply.disabled = true;

					ajaxPost(
						{ action: 'pizzatier_commerce_get_product_grid', nonce: NONCE, product_id: sourceId },
						function ( resp ) {
							var d = resp.data;
							rebuildLayerGrid( d.sizes, d.fractions, d.cells );
							showFeedback( i18n.copyProductSuccess || 'Grid copied. Review and save.', 'success' );
							document.getElementById( 'pztc-layer-copy-product-panel' ).style.display = 'none';
							copyProdApply.disabled = false;
						},
						function () {
							showFeedback( i18n.copyProductError || 'Could not load grid.', 'error' );
							copyProdApply.disabled = false;
						}
					);
				} );
			}

			// ── Set All ─────────────────────────────────────────────────────────

			var setAllToggle = document.getElementById( 'pztc-layer-set-all-toggle' );
			if ( setAllToggle ) {
				setAllToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-set-all-panel' );
					setTimeout( function () {
						var inp = document.getElementById( 'pztc-layer-set-all-value' );
						if ( inp ) inp.focus();
					}, 50 );
				} );
			}

			var setAllCancel = document.getElementById( 'pztc-layer-set-all-cancel' );
			if ( setAllCancel ) {
				setAllCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-set-all-panel' ).style.display = 'none';
				} );
			}

			var setAllApply = document.getElementById( 'pztc-layer-set-all-apply' );
			if ( setAllApply ) {
				setAllApply.addEventListener( 'click', function () {
					var valStr = document.getElementById( 'pztc-layer-set-all-value' ).value.trim();
					if ( valStr === '' ) {
						showFeedback( i18n.setAllNoValue || 'Please enter a price.', 'error' );
						return;
					}
					var price = parseFloat( valStr );
					if ( isNaN( price ) || price < 0 ) {
						showFeedback( i18n.setAllNoValue || 'Please enter a valid price.', 'error' );
						return;
					}
					var formatted = price.toFixed( 2 );
					document.querySelectorAll( '#pztc-layer-grid-table .pztc-price-input' ).forEach( function ( inp ) {
						inp.value = formatted;
					} );
					showFeedback( i18n.setAllSuccess || 'All cells updated.', 'success' );
					document.getElementById( 'pztc-layer-set-all-panel' ).style.display = 'none';
				} );
			}

			var setAllInput = document.getElementById( 'pztc-layer-set-all-value' );
			if ( setAllInput ) {
				setAllInput.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						if ( setAllApply ) setAllApply.click();
					}
				} );
			}

		} () );
		</script>
		<?php
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
