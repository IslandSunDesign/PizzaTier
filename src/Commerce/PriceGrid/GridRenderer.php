<?php
/**
 * Renders the Price Grid editor UI inside the Pizza Price Grid meta box.
 *
 * Deliberately flat: toolbar → feedback bar → table → import help.
 * No wizard, no copy modal, no mode-context banner.  All dynamic behaviour
 * (add row/col, remove, inline rename, CSV import/export) is handled entirely
 * by price-grid-import-export.js which is already enqueued by ProductTab.
 *
 * @package PizzaTier\Commerce\PriceGrid
 */

namespace PizzaTier\Commerce\PriceGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GridRenderer {

	/** @var Grid */
	private $grid_model;

	public function __construct( Grid $grid_model ) {
		$this->grid_model = $grid_model;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Output the full price grid section for the given product.
	 *
	 * Renders two tables:
	 *  1. Fraction grid  — cheese, sauce, drizzle (columns = coverage fractions).
	 *  2. Flat grid      — crust, cut, topping, and any other types (one price per size).
	 *
	 * @param int $product_id  0 on Add-New screen — falls back to defaults.
	 */
	public function render( int $product_id ): void {
		$grid      = $product_id ? $this->grid_model->get( $product_id ) : null;
		$sizes     = $grid ? $grid['sizes']     : $this->grid_model->default_sizes();
		$fractions = $grid ? $grid['fractions'] : $this->grid_model->default_fractions();
		$cells     = $grid ? $grid['cells']     : [];

		$flat_grid   = $product_id ? $this->grid_model->get_flat( $product_id ) : null;
		$flat_types  = $flat_grid ? $flat_grid['layer_types'] : $this->grid_model->default_flat_layer_types();
		$flat_sizes  = $flat_grid ? $flat_grid['sizes']       : $sizes; // mirror main sizes by default
		$flat_cells  = $flat_grid ? $flat_grid['cells']       : [];

		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? ( get_woocommerce_currency_symbol() ?: '$' )
			: '$';
		?>
		<div class="pztc-pg-wrap">

			<?php $this->render_toolbar(); ?>

			<div class="pztc-grid-import-feedback" id="pztc-import-feedback" style="display:none;"></div>

			<!-- Section 1: Fraction grid (cheese, sauce, drizzle) -->
			<h4 class="pztc-grid-section-heading">
				<?php esc_html_e( 'Fraction-Based Pricing', 'pizzatier' ); ?>
				<span class="pztc-grid-section-desc"><?php esc_html_e( 'Cheeses, sauces, drizzles — price varies by coverage (Whole / Half / Quarter, etc.)', 'pizzatier' ); ?></span>
			</h4>
			<div class="pztc-grid-wrap" id="pztc-grid-wrap">
				<?php $this->render_table( $sizes, $fractions, $cells, $currency ); ?>
			</div>

			<!-- Section 2: Flat grid (crust, cut, topping, and others) -->
			<h4 class="pztc-grid-section-heading pztc-grid-section-heading--flat" style="margin-top:24px;">
				<?php esc_html_e( 'Flat Pricing by Size', 'pizzatier' ); ?>
				<span class="pztc-grid-section-desc"><?php esc_html_e( 'Crusts, cuts, toppings, and other layers — one price per size regardless of coverage.', 'pizzatier' ); ?></span>
			</h4>
			<div class="pztc-grid-toolbar pztc-grid-toolbar--flat">
				<div class="pztc-grid-toolbar__left">
					<button type="button" class="button pztc-btn-sm" id="pztc-add-flat-type-row">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Layer Type', 'pizzatier' ); ?>
					</button>
				</div>
			</div>
			<div class="pztc-grid-wrap pztc-grid-wrap--flat" id="pztc-flat-grid-wrap">
				<?php $this->render_flat_table( $flat_types, $flat_sizes, $flat_cells, $currency ); ?>
			</div>
			<p class="pztc-field-description" style="margin-top:6px;">
				<?php esc_html_e( 'Layer type names must match the types in PizzaTier (e.g. crust, cut, topping). The sizes above are shared with the fraction grid.', 'pizzatier' ); ?>
			</p>

			<?php $this->render_import_help( $product_id ); ?>

		</div><!-- .pztc-pg-wrap -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Toolbar
	// -------------------------------------------------------------------------

	protected function render_toolbar(): void {
		?>
		<div class="pztc-grid-toolbar">
			<div class="pztc-grid-toolbar__left">
				<button type="button" class="button pztc-btn-sm" id="pztc-add-size-row">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Size', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-add-fraction-col">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Coverage', 'pizzatier' ); ?>
				</button>
			</div>
			<div class="pztc-grid-toolbar__right">
				<button type="button" class="button pztc-btn-sm pztc-btn--accent" id="pztc-export-csv"
					title="<?php esc_attr_e( 'Download current grid as CSV file', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export CSV', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-copy-csv"
					title="<?php esc_attr_e( 'Copy current grid as CSV text to clipboard', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy CSV', 'pizzatier' ); ?>
				</button>
				<label class="button pztc-btn-sm" for="pztc-import-csv-file" style="cursor:pointer;margin:0;"
					title="<?php esc_attr_e( 'Import grid from a CSV file', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Import CSV', 'pizzatier' ); ?>
				</label>
				<input
					type="file"
					id="pztc-import-csv-file"
					accept=".csv,text/csv"
					style="display:none;"
				/>
				<button type="button" class="button pztc-btn-sm" id="pztc-paste-csv-toggle"
					title="<?php esc_attr_e( 'Paste CSV text directly', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-editor-paste-text"></span>
					<?php esc_html_e( 'Paste CSV', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-copy-product-toggle"
					title="<?php esc_attr_e( 'Copy pricing grid from another pizza product', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Copy from Product', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-set-all-toggle"
					title="<?php esc_attr_e( 'Set all cells to the same price', 'pizzatier' ); ?>">
					<span class="dashicons dashicons-editor-table"></span>
					<?php esc_html_e( 'Set All', 'pizzatier' ); ?>
				</button>
			</div>
		</div>

		<!-- Paste CSV panel -->
		<div class="pztc-grid-panel" id="pztc-paste-csv-panel" style="display:none;">
			<p class="pztc-panel-label">
				<strong><?php esc_html_e( 'Paste CSV text', 'pizzatier' ); ?></strong>
				<span class="pztc-panel-hint"><?php esc_html_e( 'First row: Size header + coverage columns. Subsequent rows: size name + prices.', 'pizzatier' ); ?></span>
			</p>
			<textarea id="pztc-paste-csv-text" rows="5"
				placeholder="Size,Whole,Half,Quarter&#10;Small,1.50,0.80,0.45&#10;Medium,2.00,1.10,0.60"
				style="width:100%;font-family:monospace;font-size:12px;resize:vertical;"></textarea>
			<div class="pztc-panel-actions">
				<button type="button" class="button button-primary pztc-btn-sm" id="pztc-paste-csv-apply">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Apply', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-paste-csv-cancel">
					<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
				</button>
			</div>
		</div>

		<!-- Copy from product panel -->
		<div class="pztc-grid-panel" id="pztc-copy-product-panel" style="display:none;">
			<p class="pztc-panel-label">
				<strong><?php esc_html_e( 'Copy from another Pizza product', 'pizzatier' ); ?></strong>
				<span class="pztc-panel-hint"><?php esc_html_e( 'Replaces the current grid with the selected product\'s grid.', 'pizzatier' ); ?></span>
			</p>
			<div class="pztc-panel-row">
				<select id="pztc-copy-product-select" style="min-width:220px;">
					<option value=""><?php esc_html_e( '— Loading products… —', 'pizzatier' ); ?></option>
				</select>
				<button type="button" class="button button-primary pztc-btn-sm" id="pztc-copy-product-apply" disabled>
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Copy Grid', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-copy-product-cancel">
					<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
				</button>
			</div>
		</div>

		<!-- Set all panel -->
		<div class="pztc-grid-panel" id="pztc-set-all-panel" style="display:none;">
			<p class="pztc-panel-label">
				<strong><?php esc_html_e( 'Set all cells to the same price', 'pizzatier' ); ?></strong>
			</p>
			<div class="pztc-panel-row">
				<span class="pztc-cell-currency pztc-set-all-currency" id="pztc-set-all-currency-sym">$</span>
				<input type="number" id="pztc-set-all-value" min="0" step="0.01" placeholder="0.00"
					style="width:100px;" />
				<button type="button" class="button button-primary pztc-btn-sm" id="pztc-set-all-apply">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Apply to All', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button pztc-btn-sm" id="pztc-set-all-cancel">
					<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Table
	// -------------------------------------------------------------------------

	/**
	 * Render the price grid as an HTML table.
	 *
	 * @param string[]            $sizes
	 * @param string[]            $fractions
	 * @param array<string,float> $cells
	 * @param string              $currency
	 */
	public function render_table(
		array $sizes,
		array $fractions,
		array $cells,
		string $currency = '$'
	): void {
		?>
		<div class="pztc-grid-table-container">
		<table class="pztc-grid-table" id="pztc-grid-table"
			   data-currency="<?php echo esc_attr( $currency ); ?>">
			<thead>
				<tr>
					<th class="pztc-grid-corner">
						<span class="pztc-grid-corner-row"><?php esc_html_e( 'Size', 'pizzatier' ); ?></span>
						<span class="pztc-grid-corner-sep">&#8595; / &#8594;</span>
						<span class="pztc-grid-corner-col"><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></span>
					</th>

					<?php foreach ( $fractions as $fraction ) : ?>
						<th class="pztc-grid-th pztc-grid-th--fraction"
							data-fraction="<?php echo esc_attr( $fraction ); ?>">
							<div class="pztc-header-cell">
								<span
									class="pztc-header-label pztc-editable-label"
									contenteditable="true"
									data-type="fraction"
									data-original="<?php echo esc_attr( $fraction ); ?>"
									title="<?php esc_attr_e( 'Click to rename', 'pizzatier' ); ?>"
								><?php echo esc_html( $fraction ); ?></span>
								<input
									type="hidden"
									name="pizzatier_commerce_price_grid[fractions][]"
									value="<?php echo esc_attr( $fraction ); ?>"
									class="pztc-fraction-label-input"
								/>
								<button
									type="button"
									class="pztc-remove-col pztc-grid-remove-btn"
									data-fraction="<?php echo esc_attr( $fraction ); ?>"
									title="<?php esc_attr_e( 'Remove this column', 'pizzatier' ); ?>"
								>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sizes as $size ) : ?>
					<tr class="pztc-grid-row" data-size="<?php echo esc_attr( $size ); ?>">
						<th class="pztc-grid-th pztc-grid-th--size">
							<div class="pztc-header-cell">
								<span
									class="pztc-header-label pztc-editable-label"
									contenteditable="true"
									data-type="size"
									data-original="<?php echo esc_attr( $size ); ?>"
									title="<?php esc_attr_e( 'Click to rename', 'pizzatier' ); ?>"
								><?php echo esc_html( $size ); ?></span>
								<input
									type="hidden"
									name="pizzatier_commerce_price_grid[sizes][]"
									value="<?php echo esc_attr( $size ); ?>"
									class="pztc-size-label-input"
								/>
								<button
									type="button"
									class="pztc-remove-row pztc-grid-remove-btn"
									data-size="<?php echo esc_attr( $size ); ?>"
									title="<?php esc_attr_e( 'Remove this row', 'pizzatier' ); ?>"
								>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</th>

						<?php foreach ( $fractions as $fraction ) :
							$key       = $this->grid_model->cell_key( $size, $fraction );
							$raw_price = isset( $cells[ $key ] ) ? $cells[ $key ] : '';
							$price_str = ( '' !== $raw_price ) ? number_format( (float) $raw_price, 2, '.', '' ) : '';
							?>
							<td class="pztc-grid-cell"
								data-size="<?php echo esc_attr( $size ); ?>"
								data-fraction="<?php echo esc_attr( $fraction ); ?>">
								<div class="pztc-cell-wrap">
									<span class="pztc-cell-currency"><?php echo esc_html( $currency ); ?></span>
									<input
										type="number"
										name="pizzatier_commerce_price_grid[cells][<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( $price_str ); ?>"
										class="pztc-price-input"
										min="0"
										step="0.01"
										placeholder="0.00"
										data-key="<?php echo esc_attr( $key ); ?>"
									/>
								</div>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Flat table (one price per layer type per size)
	// -------------------------------------------------------------------------

	/**
	 * Render the flat price table — rows = layer types, columns = sizes.
	 *
	 * @param string[]            $layer_types
	 * @param string[]            $sizes
	 * @param array<string,float> $cells
	 * @param string              $currency
	 */
	public function render_flat_table(
		array $layer_types,
		array $sizes,
		array $cells,
		string $currency = '$'
	): void {
		?>
		<div class="pztc-grid-table-container">
		<table class="pztc-grid-table pztc-grid-table--flat" id="pztc-flat-grid-table"
			   data-currency="<?php echo esc_attr( $currency ); ?>">
			<?php foreach ( $sizes as $size ) : ?>
				<input type="hidden" name="pizzatier_commerce_flat_grid[sizes][]" value="<?php echo esc_attr( $size ); ?>" />
			<?php endforeach; ?>
			<thead>
				<tr>
					<th class="pztc-grid-corner">
						<span class="pztc-grid-corner-row"><?php esc_html_e( 'Layer Type', 'pizzatier' ); ?></span>
						<span class="pztc-grid-corner-sep">&#8595; / &#8594;</span>
						<span class="pztc-grid-corner-col"><?php esc_html_e( 'Size', 'pizzatier' ); ?></span>
					</th>
					<?php foreach ( $sizes as $size ) : ?>
						<th class="pztc-grid-th pztc-grid-th--size-col"
							data-flat-size="<?php echo esc_attr( $size ); ?>">
							<div class="pztc-header-cell">
								<span class="pztc-header-label"><?php echo esc_html( $size ); ?></span>
							</div>
						</th>
					<?php endforeach; ?>
					<th class="pztc-grid-th pztc-grid-th--actions"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $layer_types as $layer_type ) : ?>
					<tr class="pztc-flat-grid-row" data-layer-type="<?php echo esc_attr( $layer_type ); ?>">
						<th class="pztc-grid-th pztc-grid-th--size">
							<div class="pztc-header-cell">
								<span
									class="pztc-header-label pztc-editable-label pztc-flat-type-label"
									contenteditable="true"
									data-type="flat-layer-type"
									data-original="<?php echo esc_attr( $layer_type ); ?>"
									title="<?php esc_attr_e( 'Click to rename', 'pizzatier' ); ?>"
								><?php echo esc_html( $layer_type ); ?></span>
								<input
									type="hidden"
									name="pizzatier_commerce_flat_grid[layer_types][]"
									value="<?php echo esc_attr( $layer_type ); ?>"
									class="pztc-flat-type-label-input"
								/>
								<button
									type="button"
									class="pztc-remove-flat-row pztc-grid-remove-btn"
									data-layer-type="<?php echo esc_attr( $layer_type ); ?>"
									title="<?php esc_attr_e( 'Remove this row', 'pizzatier' ); ?>"
								>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</th>
						<?php foreach ( $sizes as $size ) :
							$key       = $layer_type . '|' . $size;
							$raw_price = $cells[ $key ] ?? '';
							$price_str = ( '' !== $raw_price ) ? number_format( (float) $raw_price, 2, '.', '' ) : '';
							?>
							<td class="pztc-grid-cell pztc-flat-grid-cell"
								data-layer-type="<?php echo esc_attr( $layer_type ); ?>"
								data-flat-size="<?php echo esc_attr( $size ); ?>">
								<div class="pztc-cell-wrap">
									<span class="pztc-cell-currency"><?php echo esc_html( $currency ); ?></span>
									<input
										type="number"
										name="pizzatier_commerce_flat_grid[cells][<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( $price_str ); ?>"
										class="pztc-price-input"
										min="0"
										step="0.01"
										placeholder="0.00"
										data-flat-key="<?php echo esc_attr( $key ); ?>"
									/>
								</div>
							</td>
						<?php endforeach; ?>
						<td class="pztc-grid-th pztc-grid-th--actions"></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Import help
	// -------------------------------------------------------------------------

	protected function render_import_help( int $product_id ): void {
		?>
		<div class="pztc-import-help">
			<p class="pztc-field-description">
				<?php esc_html_e( 'Set prices per size and coverage fraction. Click any label to rename it.', 'pizzatier' ); ?>
				&nbsp;
				<a href="#" id="pztc-download-blank-template"><?php esc_html_e( 'Download blank CSV template', 'pizzatier' ); ?></a>.
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Per-layer CPT grid table (uses pizzatier_commerce_layer_grid field names)
	// -------------------------------------------------------------------------

	/**
	 * Render a size × coverage price table for a PizzaTier CPT post
	 * (topping, crust, sauce, cheese, drizzle, cut, size).
	 *
	 * Identical layout to render_table() but uses the pizzatier_commerce_layer_grid[]
	 * POST key prefix so save handlers can distinguish it from the product-
	 * level grid.
	 *
	 * @param string[]            $sizes
	 * @param string[]            $fractions
	 * @param array<string,float> $cells
	 * @param string              $currency
	 */
	/**
	 * Render the per-ingredient (CPT) price grid editor.
	 *
	 * @param array  $sizes        Row labels (sizes).
	 * @param array  $fractions    Column labels (coverage fractions).
	 * @param array  $cells        Saved cell values keyed by Grid::cell_key().
	 * @param string $currency     Currency symbol to render in each cell.
	 * @param array  $placeholders Optional. Map of cell_key → formatted
	 *                             placeholder string (e.g. "1.50") rendered
	 *                             in cells with no saved value. Used by
	 *                             LayerGridMetaBox to surface the global
	 *                             grid's fallback price as a hint inside
	 *                             empty cells. Defaults to "0.00" for any
	 *                             cell key not present in this array.
	 */
	public function render_layer_table(
		array $sizes,
		array $fractions,
		array $cells,
		string $currency = '$',
		array $placeholders = []
	): void {
		?>
		<div class="pztc-grid-table-container">
		<table class="pztc-grid-table pztc-grid-table--layer" id="pztc-layer-grid-table"
			   data-currency="<?php echo esc_attr( $currency ); ?>">
			<thead>
				<tr>
					<th class="pztc-grid-corner">
						<span class="pztc-grid-corner-row"><?php esc_html_e( 'Size', 'pizzatier' ); ?></span>
						<span class="pztc-grid-corner-sep">&#8595; / &#8594;</span>
						<span class="pztc-grid-corner-col"><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></span>
					</th>

					<?php foreach ( $fractions as $fraction ) : ?>
						<th class="pztc-grid-th pztc-grid-th--fraction"
							data-fraction="<?php echo esc_attr( $fraction ); ?>">
							<div class="pztc-header-cell">
								<span
									class="pztc-header-label pztc-editable-label"
									contenteditable="true"
									data-type="fraction"
									data-original="<?php echo esc_attr( $fraction ); ?>"
									title="<?php esc_attr_e( 'Click to rename', 'pizzatier' ); ?>"
								><?php echo esc_html( $fraction ); ?></span>
								<input
									type="hidden"
									name="pizzatier_commerce_layer_grid[fractions][]"
									value="<?php echo esc_attr( $fraction ); ?>"
									class="pztc-fraction-label-input"
								/>
								<button
									type="button"
									class="pztc-remove-col pztc-grid-remove-btn"
									data-fraction="<?php echo esc_attr( $fraction ); ?>"
									title="<?php esc_attr_e( 'Remove this column', 'pizzatier' ); ?>"
								>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sizes as $size ) : ?>
					<tr class="pztc-grid-row" data-size="<?php echo esc_attr( $size ); ?>">
						<th class="pztc-grid-th pztc-grid-th--size">
							<div class="pztc-header-cell">
								<span
									class="pztc-header-label pztc-editable-label"
									contenteditable="true"
									data-type="size"
									data-original="<?php echo esc_attr( $size ); ?>"
									title="<?php esc_attr_e( 'Click to rename', 'pizzatier' ); ?>"
								><?php echo esc_html( $size ); ?></span>
								<input
									type="hidden"
									name="pizzatier_commerce_layer_grid[sizes][]"
									value="<?php echo esc_attr( $size ); ?>"
									class="pztc-size-label-input"
								/>
								<button
									type="button"
									class="pztc-remove-row pztc-grid-remove-btn"
									data-size="<?php echo esc_attr( $size ); ?>"
									title="<?php esc_attr_e( 'Remove this row', 'pizzatier' ); ?>"
								>
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</th>

						<?php foreach ( $fractions as $fraction ) :
							$key       = $this->grid_model->cell_key( $size, $fraction );
							$raw_price = isset( $cells[ $key ] ) ? $cells[ $key ] : '';
							$price_str = ( '' !== $raw_price ) ? number_format( (float) $raw_price, 2, '.', '' ) : '';
							// Per-cell placeholder — if a global grid is configured for
							// this layer type and has a value for this size/fraction
							// cell, surface it here so editors see the fallback price
							// they'll inherit if they leave this cell blank.
							$placeholder = isset( $placeholders[ $key ] ) && '' !== $placeholders[ $key ]
								? (string) $placeholders[ $key ]
								: '0.00';
							?>
							<td class="pztc-grid-cell"
								data-size="<?php echo esc_attr( $size ); ?>"
								data-fraction="<?php echo esc_attr( $fraction ); ?>">
								<div class="pztc-cell-wrap">
									<span class="pztc-cell-currency"><?php echo esc_html( $currency ); ?></span>
									<input
										type="number"
										name="pizzatier_commerce_layer_grid[cells][<?php echo esc_attr( $key ); ?>]"
										value="<?php echo esc_attr( $price_str ); ?>"
										class="pztc-price-input"
										min="0"
										step="0.01"
										placeholder="<?php echo esc_attr( $placeholder ); ?>"
										data-key="<?php echo esc_attr( $key ); ?>"
									/>
								</div>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Standalone render (used by NewPizzaWizard step 4)
	// -------------------------------------------------------------------------

	/**
	 * Render just the table without the section wrapper or toolbar.
	 * Used in NewPizzaWizard::render_step_4().
	 */
	public function render_table_standalone(
		array $sizes,
		array $fractions,
		array $cells,
		string $currency = '$'
	): void {
		?>
		<div class="pztc-grid-wrap" id="pztc-grid-wrap">
			<?php $this->render_table( $sizes, $fractions, $cells, $currency ); ?>
		</div>
		<?php
	}
}
