<?php
/**
 * PizzaTier — Pricing admin page.
 *
 * Consolidates everything pricing-related into a single dedicated submenu page
 * under the PizzaTier top-level menu. Replaces the old "Pricing Engine"
 * and "Grid Defaults" tabs that used to live on the main settings page.
 *
 * Tabs:
 *   • Pricing Engine    — pricing mode + engine-specific fields. Reuses the
 *                          existing pizzatier_options option via the
 *                          Settings API field renderers so behaviour is
 *                          unchanged from the previous Settings-page tab.
 *   • Default Values    — default size / fraction labels used when a new
 *                          grid is created on a product or ingredient.
 *   • Global Price Grids — one editable size × coverage grid per layer
 *                          type (toppings, crusts, sauces, cheeses,
 *                          drizzles, cuts, sizes). These act as a
 *                          site-wide fallback consulted by
 *                          Grid::get_layer_price() between the per-
 *                          ingredient grid and the per-product grid.
 *
 * Form architecture:
 *   • The Pricing Engine and Default Values tabs submit via WordPress's
 *     standard Settings API to options.php — same flow as the existing
 *     SettingsPage. Fields are registered against a new page slug
 *     ('pizzatier-pricing') so the rendering machinery (do_settings_fields)
 *     finds them on this page.
 *   • The Global Price Grids tab is a separate, non-Settings-API form that
 *     posts to admin-post.php (action 'pizzatier_commerce_save_global_grids') and is
 *     gated by both a nonce and a JS confirmation modal before submit.
 *
 * Storage:
 *   • Pricing Engine + Default Values fields → 'pizzatier_options'
 *     option (unchanged) — same keys, same pizzatier_get_option() lookups
 *     everywhere else continue to work without modification.
 *   • Global grids → 'pizzatier_global_price_grids' option via
 *     Grid::save_global_layer_grid().
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceGrid\Grid;

class PricingPage {

	/**
	 * Admin menu slug used for the Pricing page.
	 *
	 * The '-config' suffix is historical. It exists because the Freemius SDK
	 * used to reserve the bare 'pizzatier-commerce-pricing' route for its own
	 * upgrade/checkout page and would shadow any custom page registered on
	 * that exact slug. Freemius was removed in 1.10.0, so the suffix no longer
	 * serves a purpose — but the slug is kept as-is here because changing it
	 * would break existing bookmarks and any links pointing at the page.
	 *
	 * It is retired along with every other 'pizzatier-commerce-*' page slug when this
	 * plugin merges into PizzaTier; that is the right moment to simplify it,
	 * behind a redirect, rather than churning the route twice.
	 */
	const PAGE_SLUG = 'pizzatier-pricing';

	/** Settings API page identifier — separate from Settings::PAGE_SLUG so the
	 *  registered fields render only on this page. */
	const FIELDS_PAGE = 'pizzatier-pricing-fields';

	/** Admin-post action used by the Global Price Grids form. */
	const SAVE_GRIDS_ACTION = 'pizzatier_commerce_save_global_grids';

	/** Nonce action / field name for the global-grids form. */
	const GRIDS_NONCE_ACTION = 'pizzatier_commerce_save_global_grids';
	const GRIDS_NONCE_FIELD  = '_pizzatier_commerce_grids_nonce';

	/** @var Grid */
	private $grid;

	/**
	 * Human-readable labels for each canonical layer-type slug used by the
	 * Global Price Grids tab. Keys match Grid::GLOBAL_LAYER_TYPES.
	 *
	 * Built lazily inside the renderer so translation strings are resolved
	 * at request time rather than at class load time.
	 *
	 * @return array<string,array{label:string,desc:string,fraction:bool}>
	 */
	private function layer_type_meta(): array {
		return [
			'toppings' => [
				'label'    => __( 'Toppings', 'pizzatier' ),
				'desc'     => __( 'Pepperoni, mushrooms, peppers, and every other topping ingredient.', 'pizzatier' ),
				'fraction' => true,
			],
			'crusts' => [
				'label'    => __( 'Crusts', 'pizzatier' ),
				'desc'     => __( 'Thin, thick, stuffed, gluten-free — applied once per pizza.', 'pizzatier' ),
				'fraction' => false,
			],
			'sauces' => [
				'label'    => __( 'Sauces', 'pizzatier' ),
				'desc'     => __( 'Marinara, white, pesto, BBQ — typically applied by coverage fraction.', 'pizzatier' ),
				'fraction' => true,
			],
			'cheeses' => [
				'label'    => __( 'Cheeses', 'pizzatier' ),
				'desc'     => __( 'Mozzarella, cheddar, vegan blends — also support coverage fractions.', 'pizzatier' ),
				'fraction' => true,
			],
			'drizzles' => [
				'label'    => __( 'Drizzles', 'pizzatier' ),
				'desc'     => __( 'Garlic oil, hot honey, balsamic — finishing touches applied by coverage.', 'pizzatier' ),
				'fraction' => true,
			],
			'cuts' => [
				'label'    => __( 'Cuts', 'pizzatier' ),
				'desc'     => __( 'Slicing style — square, wedges, party-cut — usually a fixed add-on per size.', 'pizzatier' ),
				'fraction' => false,
			],
			'sizes' => [
				'label'    => __( 'Sizes', 'pizzatier' ),
				'desc'     => __( 'Base size pricing if your store sells the size selection itself as an upgrade.', 'pizzatier' ),
				'fraction' => false,
			],
		];
	}

	public function __construct( Grid $grid ) {
		$this->grid = $grid;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_init',                                    [ $this, 'register_fields' ] );
		add_action( 'admin_post_' . self::SAVE_GRIDS_ACTION,        [ $this, 'handle_save_global_grids' ] );
		add_action( 'admin_enqueue_scripts',                         [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Settings API field registration (Pricing Engine + Default Values tabs)
	// -------------------------------------------------------------------------

	/**
	 * Register every pricing-engine and default-values field against the
	 * Pricing page's own FIELDS_PAGE slug. The fields themselves still write
	 * to the existing 'pizzatier_options' option via the Settings
	 * instance's sanitize callback (which already handles every key).
	 *
	 * We deliberately re-use the public field-renderer methods on the
	 * Settings instance rather than duplicating them. That keeps the visual
	 * design (toggles, mode cards, layout picker, calculator) identical to
	 * what users saw before this page existed.
	 */
	public function register_fields(): void {
		$settings = new Settings();

		// ── Section: Pricing Engine ────────────────────────────────────────
		add_settings_section(
			'pizzatier_commerce_pricing_section_engine',
			__( 'Pricing Engine', 'pizzatier' ),
			'__return_false',
			self::FIELDS_PAGE
		);

		add_settings_field(
			'pricing_mode',
			__( 'Global pricing mode', 'pizzatier' ),
			[ $settings, 'field_pricing_mode' ],
			self::FIELDS_PAGE,
			'pizzatier_commerce_pricing_section_engine'
		);

		add_settings_field(
			'pricing_example_calculator',
			__( 'Example calculator', 'pizzatier' ),
			[ $settings, 'field_pricing_calculator' ],
			self::FIELDS_PAGE,
			'pizzatier_commerce_pricing_section_engine'
		);

		$engine_fields = $this->engine_field_defs();
		foreach ( $engine_fields as $f ) {
			$this->add_field( $settings, $f, 'pizzatier_commerce_pricing_section_engine' );
		}

		// ── Section: Default Values ────────────────────────────────────────
		add_settings_section(
			'pizzatier_commerce_pricing_section_defaults',
			__( 'Default Grid Values', 'pizzatier' ),
			'__return_false',
			self::FIELDS_PAGE
		);

		$default_fields = [
			[ 'default_sizes', __( 'Default sizes', 'pizzatier' ),
			  __( 'One size label per line. Used as row headers in every new price grid. Example: Small, Medium, Large, XL.', 'pizzatier' ),
			  'tag_list', "Small\nMedium\nLarge\nXL" ],
			[ 'default_fractions', __( 'Default coverage fractions', 'pizzatier' ),
			  __( 'One fraction label per line. Used as column headers in every new price grid. Example: Whole, Half, Quarter.', 'pizzatier' ),
			  'tag_list', "Whole\nHalf\nQuarter" ],
		];
		foreach ( $default_fields as $f ) {
			$this->add_field( $settings, $f, 'pizzatier_commerce_pricing_section_defaults' );
		}
	}

	/**
	 * Field definitions for the Pricing Engine section. Mirrors the field
	 * list that used to live in Settings::register_section_pricing() so
	 * existing behaviour and saved values are unchanged.
	 *
	 * @return array
	 */
	private function engine_field_defs(): array {
		return [
			[ 'free_toppings_count', __( 'Free toppings included', 'pizzatier' ),
			  __( 'Number of toppings included at no charge. Active in Add-on per layer and Free first N modes. Set 0 to disable.', 'pizzatier' ),
			  'text', '0' ],
			[ 'tiered_topping_thresholds', __( 'Tier thresholds', 'pizzatier' ),
			  __( 'Comma-separated topping counts for Tiered by Count mode. E.g. "3,6" -> Tier1=1-3, Tier2=4-6, Tier3=7+. Grid column labels must be Tier1, Tier2, Tier3.', 'pizzatier' ),
			  'text', '3,6' ],
			[ 'min_topping_price', __( 'Minimum add-on per topping', 'pizzatier' ),
			  __( 'Price floor per paid topping. If the grid cell is below this amount, this minimum is charged instead. Leave blank to disable.', 'pizzatier' ),
			  'text', '' ],
			[ 'max_topping_price', __( 'Maximum add-on per topping', 'pizzatier' ),
			  __( 'Price ceiling per paid topping. If the grid cell exceeds this amount, this maximum is charged instead. Leave blank to disable.', 'pizzatier' ),
			  'text', '' ],
			[ 'non_topping_pricing', __( 'Non-topping layer pricing', 'pizzatier' ),
			  __( 'How crusts, sauces, cheeses, drizzles, and cuts are priced.', 'pizzatier' ),
			  'select', '', [
				'grid'  => __( 'Same grid as toppings (Size x Coverage cell)', 'pizzatier' ),
				'fixed' => __( 'Fixed add-on amount per layer type', 'pizzatier' ),
				'free'  => __( 'Always free (no charge)', 'pizzatier' ),
			] ],
			[ 'crust_fixed_price',   __( 'Crust fixed add-on',   'pizzatier' ), __( 'Flat price added when a crust is selected (fixed add-on mode only).', 'pizzatier' ), 'text', '0.00' ],
			[ 'sauce_fixed_price',   __( 'Sauce fixed add-on',   'pizzatier' ), __( 'Flat price for sauce (fixed add-on mode only).', 'pizzatier' ),                  'text', '0.00' ],
			[ 'cheese_fixed_price',  __( 'Cheese fixed add-on',  'pizzatier' ), __( 'Flat price for cheese (fixed add-on mode only).', 'pizzatier' ),                 'text', '0.00' ],
			[ 'drizzle_fixed_price', __( 'Drizzle fixed add-on', 'pizzatier' ), __( 'Flat price for drizzle (fixed add-on mode only).', 'pizzatier' ),                'text', '0.00' ],
			[ 'size_price_multipliers', __( 'Per-size add-on multipliers', 'pizzatier' ),
			  __( 'Optional multipliers applied to the grid add-on total per size. One per line: SizeLabel=multiplier. E.g. "XL=1.50" makes XL add-ons 50% more expensive. Leave blank to apply no multiplier.', 'pizzatier' ),
			  'tag_list', "Small=1.00\nMedium=1.00\nLarge=1.25\nXL=1.50" ],
			[ 'discount_threshold', __( 'Bulk discount - topping threshold', 'pizzatier' ),
			  __( 'Apply a percentage discount to add-ons when this many toppings are selected. Set 0 to disable.', 'pizzatier' ),
			  'text', '0' ],
			[ 'discount_percent', __( 'Bulk discount %', 'pizzatier' ),
			  __( 'Percentage discount applied to the add-on subtotal when the bulk threshold is reached. E.g. "10" = 10% off add-ons.', 'pizzatier' ),
			  'text', '0' ],
			[ 'discount_max_amount', __( 'Bulk discount max saving', 'pizzatier' ),
			  __( 'Cap the bulk discount at this dollar amount. Leave blank for no cap.', 'pizzatier' ),
			  'text', '' ],
			[ 'price_rounding', __( 'Price rounding', 'pizzatier' ),
			  __( 'How to round the final calculated total.', 'pizzatier' ),
			  'select', '', [
				''          => __( 'WooCommerce decimals (default)', 'pizzatier' ),
				'up'        => __( 'Always round up (ceiling)', 'pizzatier' ),
				'nearest5'  => __( 'Nearest $0.05', 'pizzatier' ),
				'nearest25' => __( 'Nearest $0.25', 'pizzatier' ),
				'nearest50' => __( 'Nearest $0.50', 'pizzatier' ),
				'nearest1'  => __( 'Nearest $1.00', 'pizzatier' ),
			] ],
			[ 'price_includes_tax', __( 'Grid prices are tax-inclusive', 'pizzatier' ),
			  __( 'Check if the prices you entered in price grids already include tax. PizzaTier will not add further tax on top of grid add-on amounts.', 'pizzatier' ),
			  'checkbox' ],
			[ 'show_per_topping_price', __( 'Show per-topping price in builder', 'pizzatier' ),
			  __( 'Display the add-on price next to each topping option in the frontend builder (e.g. Pepperoni +$1.50). Requires the active PizzaTier template to support this.', 'pizzatier' ),
			  'checkbox' ],
			[ 'fallback_price_label', __( 'Fallback price label', 'pizzatier' ),
			  __( 'Text shown in the price bar when a grid cell is missing or the total cannot be calculated.', 'pizzatier' ),
			  'text', __( 'Price calculated on selection', 'pizzatier' ) ],
		];
	}

	/**
	 * Register a single field against the Pricing page.
	 *
	 * Mirrors Settings::add_field() so the array-of-arrays field-def shape
	 * stays identical and translation strings don't drift between the two
	 * registration paths.
	 */
	private function add_field( Settings $settings, array $f, string $section ): void {
		[ $key, $label, $desc, $type ] = $f;
		$ph      = $f[4] ?? '';
		$options = $f[5] ?? [];

		$cb_map = [
			'tag_list'      => 'field_tag_list',
			'textarea_wide' => 'field_textarea_wide',
		];
		$cb = isset( $cb_map[ $type ] ) ? $cb_map[ $type ] : 'field_' . $type;

		add_settings_field(
			$key,
			$label,
			[ $settings, $cb ],
			self::FIELDS_PAGE,
			$section,
			[
				'key'         => $key,
				'description' => $desc,
				'placeholder' => $ph,
				'options'     => $options,
				'show_for'    => '',
				'nt_show'     => '',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pizzatier' ) );
		}

		// Tabs for this page
		$tabs = [
			'engine'   => [ 'label' => __( 'Pricing Engine',  'pizzatier' ), 'icon' => 'dashicons-chart-bar'      ],
			'defaults' => [ 'label' => __( 'Default Values',  'pizzatier' ), 'icon' => 'dashicons-admin-settings' ],
			'grids'    => [ 'label' => __( 'Global Price Grids', 'pizzatier' ), 'icon' => 'dashicons-grid-view'   ],
		];
		?>
		<div class="wrap pztc-page-wrap pztc-pricing-page">

			<?php $this->render_styles(); ?>

			<!-- ══ Header ═════════════════════════════════════════════════ -->
			<div class="pztc-header">
				<div class="pztc-header__brand">
					<span class="dashicons dashicons-money-alt pztc-header__icon" aria-hidden="true"></span>
					<div>
						<h1 class="pztc-header__title"><?php esc_html_e( 'Pricing', 'pizzatier' ); ?></h1>
						<p class="pztc-header__tagline">
							<?php esc_html_e( 'Engine mode, default grid values, and site-wide global price grids', 'pizzatier' ); ?>
							&mdash; v<?php echo esc_html( PIZZATIER_VERSION ); ?>
						</p>
					</div>
				</div>
				<div class="pztc-header__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'PizzaTier Dashboard', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) ); ?>" class="button">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Other Settings', 'pizzatier' ); ?>
					</a>
				</div>
			</div>

			<?php
			// Surface validation notices left behind by the global-grids save.
			$grid_error = get_transient( 'pizzatier_commerce_pricing_grids_error_' . get_current_user_id() );
			if ( $grid_error ) {
				delete_transient( 'pizzatier_commerce_pricing_grids_error_' . get_current_user_id() );
				echo '<div class="notice notice-error is-dismissible"><p>'
					. esc_html( $grid_error )
					. '</p></div>';
			}
			$grid_success = get_transient( 'pizzatier_commerce_pricing_grids_success_' . get_current_user_id() );
			if ( $grid_success ) {
				delete_transient( 'pizzatier_commerce_pricing_grids_success_' . get_current_user_id() );
				echo '<div class="notice notice-success is-dismissible"><p>'
					. esc_html( $grid_success )
					. '</p></div>';
			}
			settings_errors( Settings::OPTION_NAME );
			?>

			<!-- ══ Tab nav (outside the form so it can switch panels for either form) ══ -->
			<div class="pztc-card pztc-card--tabs">
				<div class="pztc-card__head">
					<h2 class="pztc-card__title">
						<span class="dashicons dashicons-money-alt"></span>
						<?php esc_html_e( 'Pricing Configuration', 'pizzatier' ); ?>
					</h2>
					<p class="pztc-card__subtitle">
						<?php esc_html_e( 'Everything that controls how pizza prices are calculated, in one place.', 'pizzatier' ); ?>
					</p>
				</div>

				<nav class="pztc-tabnav" id="pztc-pricing-tabs" role="tablist">
					<?php $first = true; foreach ( $tabs as $id => $tab ) : ?>
					<button
						class="pztc-tab<?php echo $first ? ' pztc-tab--active' : ''; ?>"
						data-tab="<?php echo esc_attr( $id ); ?>"
						role="tab"
						aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
						aria-controls="pztc-pricing-panel-<?php echo esc_attr( $id ); ?>"
						type="button"
					>
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php echo esc_html( $tab['label'] ); ?>
					</button>
					<?php $first = false; endforeach; ?>
				</nav>

				<div class="pztc-panels">

					<!-- ══ Engine + Defaults panels live inside the Settings-API form ══ -->
					<form method="post" action="options.php" class="pztc-settings-form" id="pztc-pricing-engine-form">
						<?php settings_fields( Settings::OPTION_GROUP ); ?>

						<!-- Pricing Engine panel -->
						<div class="pztc-panel pztc-panel--active"
							 id="pztc-pricing-panel-engine"
							 role="tabpanel">
							<div class="pztc-panel__body">
								<p class="pztc-section-desc">
									<?php esc_html_e( 'Choose how the pricing engine calculates the total for each pizza order, and configure engine-specific options. Individual products can override the pricing mode on their Pizza Configurator tab.', 'pizzatier' ); ?>
								</p>
								<table class="form-table pztc-form-table" role="presentation">
									<?php do_settings_fields( self::FIELDS_PAGE, 'pizzatier_commerce_pricing_section_engine' ); ?>
								</table>
							</div>
						</div>

						<!-- Default Values panel -->
						<div class="pztc-panel"
							 id="pztc-pricing-panel-defaults"
							 role="tabpanel">
							<div class="pztc-panel__body">
								<p class="pztc-section-desc">
									<?php esc_html_e( 'Default size and coverage labels used as the starting point for every new product or ingredient price grid. Existing grids are not affected when these defaults change.', 'pizzatier' ); ?>
								</p>
								<table class="form-table pztc-form-table" role="presentation">
									<?php do_settings_fields( self::FIELDS_PAGE, 'pizzatier_commerce_pricing_section_defaults' ); ?>
								</table>
							</div>
						</div>

						<!-- Save row for engine + defaults form -->
						<div class="pztc-save-row pztc-pricing-save-row" data-form="engine">
							<?php submit_button( __( 'Save Pricing Settings', 'pizzatier' ), 'primary pztc-save-btn', 'submit', false ); ?>
							<span class="pztc-save-row__version">
								<?php esc_html_e( 'Pricing Engine + Default Values', 'pizzatier' ); ?>
							</span>
						</div>
					</form>

					<!-- ══ Global Grids panel lives in its own form ══ -->
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						  id="pztc-global-grids-form"
						  class="pztc-settings-form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_GRIDS_ACTION ); ?>" />
						<?php wp_nonce_field( self::GRIDS_NONCE_ACTION, self::GRIDS_NONCE_FIELD ); ?>

						<div class="pztc-panel"
							 id="pztc-pricing-panel-grids"
							 role="tabpanel">
							<div class="pztc-panel__body">
								<?php $this->render_global_grids_tab(); ?>
							</div>
						</div>

						<!-- Save row for global grids form -->
						<div class="pztc-save-row pztc-pricing-save-row" data-form="grids">
							<button type="submit" class="button button-primary pztc-save-btn" id="pztc-grids-save-btn">
								<?php esc_html_e( 'Save Global Price Grids', 'pizzatier' ); ?>
							</button>
							<span class="pztc-save-row__version">
								<?php esc_html_e( 'Site-wide grids — applies to all pizza products that do not override', 'pizzatier' ); ?>
							</span>
						</div>
					</form>

				</div><!-- /.pztc-panels -->
			</div><!-- /.pztc-card--tabs -->

			<!-- ══ Credits ════════════════════════════════════════════════ -->
			<div class="pztc-credits">
				PizzaTier v<?php echo esc_html( PIZZATIER_VERSION ); ?> &mdash;
				crafted by <strong>Ryan Bishop</strong> /
				<a href="https://islandsundesign.com" target="_blank" rel="noopener">Island Sun Design</a>
			</div>

		</div><!-- /.pztc-page-wrap -->

		<!-- ══ Confirmation modal (rendered once, used by JS) ════════════ -->
		<?php $this->render_confirm_modal(); ?>

		<?php $this->render_tab_script(); ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Global Grids tab body
	// -------------------------------------------------------------------------

	private function render_global_grids_tab(): void {
		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? ( get_woocommerce_currency_symbol() ?: '$' )
			: '$';
		$meta     = $this->layer_type_meta();
		$default_sizes     = $this->grid->default_sizes();
		$default_fractions = $this->grid->default_fractions();
		?>
		<div class="pztc-grids-intro">
			<p class="pztc-section-desc">
				<?php esc_html_e( 'Set site-wide default prices for each layer type. These are consulted automatically when an individual ingredient or product does not have its own price grid configured.', 'pizzatier' ); ?>
			</p>
			<p class="pztc-section-desc">
				<strong><?php esc_html_e( 'Resolution order:', 'pizzatier' ); ?></strong>
				<?php esc_html_e( 'per-ingredient grid → global grid (this page) → per-product grid → no charge.', 'pizzatier' ); ?>
			</p>
			<div class="pztc-grids-callout">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Heads up — these are site-wide changes.', 'pizzatier' ); ?></strong>
					<span><?php esc_html_e( 'Saving a global grid affects every pizza product that does not have its own grid override. You will be asked to confirm before the change is committed.', 'pizzatier' ); ?></span>
				</div>
			</div>
		</div>

		<div class="pztc-grids-nav">
			<?php $first = true; foreach ( $meta as $slug => $info ) :
				$has = $this->grid->has_global_layer_grid( $slug );
			?>
				<button type="button"
				        class="pztc-grid-pill<?php echo $first ? ' pztc-grid-pill--active' : ''; ?>"
				        data-type="<?php echo esc_attr( $slug ); ?>">
					<?php echo esc_html( $info['label'] ); ?>
					<?php if ( $has ) : ?>
						<span class="pztc-grid-pill__dot" title="<?php esc_attr_e( 'Custom grid saved', 'pizzatier' ); ?>"></span>
					<?php endif; ?>
				</button>
			<?php $first = false; endforeach; ?>
		</div>

		<div class="pztc-grids-bodies">
			<?php $first = true; foreach ( $meta as $slug => $info ) :
				$saved   = $this->grid->get_global_layer_grid( $slug );
				$has     = null !== $saved;
				$sizes     = $saved ? $saved['sizes']     : $default_sizes;
				$fractions = $saved ? $saved['fractions'] : $default_fractions;
				$cells     = $saved ? $saved['cells']     : [];
			?>
				<div class="pztc-grid-body<?php echo $first ? ' pztc-grid-body--active' : ''; ?>"
				     id="pztc-grid-body-<?php echo esc_attr( $slug ); ?>"
				     data-type="<?php echo esc_attr( $slug ); ?>">

					<div class="pztc-grid-body__head">
						<div>
							<h3 class="pztc-grid-body__title"><?php echo esc_html( $info['label'] ); ?></h3>
							<p class="pztc-grid-body__desc"><?php echo esc_html( $info['desc'] ); ?></p>
						</div>
						<?php $this->render_grid_status_badge( $has ); ?>
					</div>

					<?php $this->render_editable_grid( $slug, $sizes, $fractions, $cells, $currency ); ?>

					<div class="pztc-grid-body__actions">
						<button type="button"
						        class="button pztc-grid-set-all-btn"
						        data-type="<?php echo esc_attr( $slug ); ?>">
							<span class="dashicons dashicons-editor-table"></span>
							<?php esc_html_e( 'Set all cells to…', 'pizzatier' ); ?>
						</button>
						<?php if ( $has ) : ?>
							<button type="button"
							        class="button pztc-btn--danger pztc-grid-clear-btn"
							        data-type="<?php echo esc_attr( $slug ); ?>"
							        title="<?php esc_attr_e( 'Remove this global grid — products will fall through to their own grids or no charge.', 'pizzatier' ); ?>">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear this grid', 'pizzatier' ); ?>
							</button>
						<?php endif; ?>
						<!-- Hidden clear flag, toggled by the clear button via JS. -->
						<input type="hidden"
						       name="pizzatier_commerce_global_grids[clear][<?php echo esc_attr( $slug ); ?>]"
						       id="pztc-global-grids-clear-<?php echo esc_attr( $slug ); ?>"
						       value="0" />
					</div>
				</div>
			<?php $first = false; endforeach; ?>
		</div>
		<?php
	}

	private function render_grid_status_badge( bool $has ): void {
		if ( $has ) {
			$color = '#16a34a';
			$icon  = 'dashicons-yes-alt';
			$msg   = __( 'Global grid configured', 'pizzatier' );
		} else {
			$color = '#9ca3af';
			$icon  = 'dashicons-info-outline';
			$msg   = __( 'Not yet configured', 'pizzatier' );
		}
		?>
		<span class="pztc-grid-badge" style="
			display:inline-flex;align-items:center;gap:6px;
			padding:5px 10px;
			background:<?php echo esc_attr( $color ); ?>1a;
			border-left:3px solid <?php echo esc_attr( $color ); ?>;
			border-radius:3px;font-size:12px;color:<?php echo esc_attr( $color ); ?>;
		">
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color:<?php echo esc_attr( $color ); ?>;font-size:14px;width:14px;height:14px;"></span>
			<?php echo esc_html( $msg ); ?>
		</span>
		<?php
	}

	/**
	 * Render an editable size × fraction grid for a specific layer type.
	 *
	 * Inputs are named so they group neatly under
	 * pizzatier_commerce_global_grids[grids][{slug}][...] in $_POST. We deliberately
	 * keep the markup lighter than the per-product GridRenderer (no
	 * add-row / add-column UI here) since global defaults are intentionally
	 * a small fixed grid that should align with the Default Values tab.
	 */
	private function render_editable_grid( string $slug, array $sizes, array $fractions, array $cells, string $currency ): void {
		$name_base = 'pizzatier_commerce_global_grids[grids][' . $slug . ']';
		?>
		<div class="pztc-grid-table-container">
		<table class="pztc-grid-table pztc-global-grid-table"
		       data-currency="<?php echo esc_attr( $currency ); ?>"
		       data-type="<?php echo esc_attr( $slug ); ?>">
			<thead>
				<tr>
					<th class="pztc-grid-corner">
						<span class="pztc-grid-corner-row"><?php esc_html_e( 'Size', 'pizzatier' ); ?></span>
						<span class="pztc-grid-corner-sep">&#8595; / &#8594;</span>
						<span class="pztc-grid-corner-col"><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></span>
					</th>
					<?php foreach ( $fractions as $fraction ) : ?>
						<th class="pztc-grid-th pztc-grid-th--fraction">
							<div class="pztc-header-cell">
								<span class="pztc-header-label"><?php echo esc_html( $fraction ); ?></span>
								<input type="hidden"
								       name="<?php echo esc_attr( $name_base ); ?>[fractions][]"
								       value="<?php echo esc_attr( $fraction ); ?>" />
							</div>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sizes as $size ) : ?>
					<tr class="pztc-grid-row">
						<th class="pztc-grid-th pztc-grid-th--size">
							<div class="pztc-header-cell">
								<span class="pztc-header-label"><?php echo esc_html( $size ); ?></span>
								<input type="hidden"
								       name="<?php echo esc_attr( $name_base ); ?>[sizes][]"
								       value="<?php echo esc_attr( $size ); ?>" />
							</div>
						</th>
						<?php foreach ( $fractions as $fraction ) :
							$key       = $this->grid->cell_key( $size, $fraction );
							$raw       = isset( $cells[ $key ] ) ? $cells[ $key ] : '';
							$price_str = ( '' !== $raw && null !== $raw ) ? number_format( (float) $raw, 2, '.', '' ) : '';
						?>
							<td class="pztc-grid-cell"
							    data-size="<?php echo esc_attr( $size ); ?>"
							    data-fraction="<?php echo esc_attr( $fraction ); ?>">
								<div class="pztc-cell-wrap">
									<span class="pztc-cell-currency"><?php echo esc_html( $currency ); ?></span>
									<input type="number"
									       name="<?php echo esc_attr( $name_base ); ?>[cells][<?php echo esc_attr( $key ); ?>]"
									       value="<?php echo esc_attr( $price_str ); ?>"
									       class="pztc-price-input pztc-grid-input"
									       data-original="<?php echo esc_attr( $price_str ); ?>"
									       data-type="<?php echo esc_attr( $slug ); ?>"
									       min="0" step="0.01" placeholder="0.00" />
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
	// Confirmation modal
	// -------------------------------------------------------------------------

	private function render_confirm_modal(): void {
		?>
		<div id="pztc-confirm-modal"
		     class="pztc-modal"
		     role="dialog"
		     aria-modal="true"
		     aria-labelledby="pztc-confirm-modal-title"
		     aria-describedby="pztc-confirm-modal-body"
		     hidden>
			<div class="pztc-modal__backdrop" data-action="cancel"></div>
			<div class="pztc-modal__dialog" role="document">
				<div class="pztc-modal__head">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<h2 id="pztc-confirm-modal-title" class="pztc-modal__title">
						<?php esc_html_e( 'Apply site-wide pricing change?', 'pizzatier' ); ?>
					</h2>
				</div>
				<div id="pztc-confirm-modal-body" class="pztc-modal__body">
					<p>
						<?php esc_html_e( 'You are about to update the global price grid for the following layer types:', 'pizzatier' ); ?>
					</p>
					<ul id="pztc-confirm-modal-list" class="pztc-modal__list"></ul>
					<p class="pztc-modal__warn">
						<?php esc_html_e( 'These prices apply to every pizza product that does not have its own grid override. Existing customer carts are not retroactively re-priced.', 'pizzatier' ); ?>
					</p>
				</div>
				<div class="pztc-modal__foot">
					<button type="button" class="button pztc-modal__btn" data-action="cancel">
						<?php esc_html_e( 'Cancel', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button button-primary pztc-save-btn pztc-modal__btn" data-action="confirm">
						<?php esc_html_e( 'Yes, apply globally', 'pizzatier' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save handler — global grids form
	// -------------------------------------------------------------------------

	public function handle_save_global_grids(): void {
		// Capability + nonce checks
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'pizzatier' ), '', [ 'response' => 403 ] );
		}
		$nonce = isset( $_POST[ self::GRIDS_NONCE_FIELD ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::GRIDS_NONCE_FIELD ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::GRIDS_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pizzatier' ), '', [ 'response' => 403 ] );
		}

		$raw = isset( $_POST['pizzatier_commerce_global_grids'] ) && is_array( $_POST['pizzatier_commerce_global_grids'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
			? wp_unslash( $_POST['pizzatier_commerce_global_grids'] )
			: [];

		$grids_in = isset( $raw['grids'] ) && is_array( $raw['grids'] ) ? $raw['grids'] : [];
		$clear_in = isset( $raw['clear'] ) && is_array( $raw['clear'] ) ? $raw['clear'] : [];

		$saved   = [];
		$cleared = [];
		$errors  = [];

		foreach ( Grid::GLOBAL_LAYER_TYPES as $slug ) {
			// 1) Honour explicit clear flag first.
			if ( ! empty( $clear_in[ $slug ] ) ) {
				$this->grid->delete_global_layer_grid( $slug );
				$cleared[] = $slug;
				continue;
			}

			// 2) Otherwise validate-and-save anything submitted for this type.
			if ( ! isset( $grids_in[ $slug ] ) || ! is_array( $grids_in[ $slug ] ) ) {
				continue;
			}
			$data = [
				'sizes'     => isset( $grids_in[ $slug ]['sizes'] )     && is_array( $grids_in[ $slug ]['sizes'] )     ? $grids_in[ $slug ]['sizes']     : [],
				'fractions' => isset( $grids_in[ $slug ]['fractions'] ) && is_array( $grids_in[ $slug ]['fractions'] ) ? $grids_in[ $slug ]['fractions'] : [],
				'cells'     => isset( $grids_in[ $slug ]['cells'] )     && is_array( $grids_in[ $slug ]['cells'] )     ? $grids_in[ $slug ]['cells']     : [],
			];

			// Skip rows that came in totally empty (e.g. the layer type pill
			// was never visited and no fields were rendered). The hidden
			// size/fraction inputs ensure visited tabs always have at least
			// the labels submitted.
			if ( empty( $data['sizes'] ) && empty( $data['fractions'] ) && empty( $data['cells'] ) ) {
				continue;
			}

			// Optimisation: when every cell is empty/0 and there was no
			// previously-saved grid, do nothing — the admin clearly didn't
			// intend to create a grid for this type.
			$has_any_cell_value = false;
			foreach ( $data['cells'] as $val ) {
				if ( '' !== $val && null !== $val && (float) $val > 0 ) {
					$has_any_cell_value = true;
					break;
				}
			}
			$already_saved = $this->grid->has_global_layer_grid( $slug );
			if ( ! $has_any_cell_value && ! $already_saved ) {
				continue;
			}

			$result = $this->grid->save_global_layer_grid( $slug, $data );
			if ( is_wp_error( $result ) ) {
				$meta    = $this->layer_type_meta();
				$label   = $meta[ $slug ]['label'] ?? $slug;
				$errors[] = sprintf(
					/* translators: 1: layer type label, 2: error message */
					__( '%1$s: %2$s', 'pizzatier' ),
					$label,
					$result->get_error_message()
				);
			} else {
				$saved[] = $slug;
			}
		}

		// Surface result as transients so notices render on the redirect target.
		$uid = get_current_user_id();
		if ( ! empty( $errors ) ) {
			set_transient(
				'pizzatier_commerce_pricing_grids_error_' . $uid,
				implode( ' ', $errors ),
				60
			);
		} elseif ( ! empty( $saved ) || ! empty( $cleared ) ) {
			$parts = [];
			if ( ! empty( $saved ) ) {
				$parts[] = sprintf(
					/* translators: %d: number of grids saved */
					_n( '%d global grid saved.', '%d global grids saved.', count( $saved ), 'pizzatier' ),
					count( $saved )
				);
			}
			if ( ! empty( $cleared ) ) {
				$parts[] = sprintf(
					/* translators: %d: number of grids cleared */
					_n( '%d global grid cleared.', '%d global grids cleared.', count( $cleared ), 'pizzatier' ),
					count( $cleared )
				);
			}
			set_transient(
				'pizzatier_commerce_pricing_grids_success_' . $uid,
				implode( ' ', $parts ),
				60
			);
		} else {
			set_transient(
				'pizzatier_commerce_pricing_grids_success_' . $uid,
				__( 'No global grid changes to save.', 'pizzatier' ),
				30
			);
		}

		wp_safe_redirect( add_query_arg(
			[ 'page' => self::PAGE_SLUG ],
			admin_url( 'admin.php' )
		) . '#grids' );
		exit;
	}

	// -------------------------------------------------------------------------
	// Tab switcher + grids JS (interactivity)
	// -------------------------------------------------------------------------

	private function render_tab_script(): void {
		?>
		<script>
		( function () {
			'use strict';

			// ── Outer tab switcher (Engine / Defaults / Grids) ────────────
			var tabs   = document.querySelectorAll( '#pztc-pricing-tabs .pztc-tab' );
			var panels = document.querySelectorAll( '#pztc-pricing-tabs ~ .pztc-panels .pztc-panel' );
			var saveRows = document.querySelectorAll( '.pztc-pricing-save-row' );

			function setSaveRowVisibility( tabId ) {
				saveRows.forEach( function ( row ) {
					var formType = row.getAttribute( 'data-form' );
					var visible = ( tabId === 'engine' || tabId === 'defaults' )
						? ( formType === 'engine' )
						: ( formType === 'grids' );
					row.style.display = visible ? '' : 'none';
				} );
			}

			function activate( targetId ) {
				tabs.forEach( function ( t ) {
					var active = t.dataset.tab === targetId;
					t.classList.toggle( 'pztc-tab--active', active );
					t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( p ) {
					p.classList.toggle( 'pztc-panel--active', p.id === 'pztc-pricing-panel-' + targetId );
				} );
				setSaveRowVisibility( targetId );
			}

			if ( tabs.length ) {
				var initial = window.location.hash
					? window.location.hash.replace( '#', '' )
					: tabs[ 0 ].dataset.tab;
				if ( ! document.getElementById( 'pztc-pricing-panel-' + initial ) ) {
					initial = tabs[ 0 ].dataset.tab;
				}
				activate( initial );

				tabs.forEach( function ( tab ) {
					tab.addEventListener( 'click', function () {
						history.replaceState( null, '', '#' + tab.dataset.tab );
						activate( tab.dataset.tab );
					} );
				} );
			}

			// ── Inner pill nav (per-layer-type grid switcher) ─────────────
			var pills = document.querySelectorAll( '.pztc-grid-pill' );
			var bodies = document.querySelectorAll( '.pztc-grid-body' );

			pills.forEach( function ( pill ) {
				pill.addEventListener( 'click', function () {
					var type = pill.getAttribute( 'data-type' );
					pills.forEach( function ( p ) {
						p.classList.toggle( 'pztc-grid-pill--active', p === pill );
					} );
					bodies.forEach( function ( b ) {
						b.classList.toggle( 'pztc-grid-body--active', b.getAttribute( 'data-type' ) === type );
					} );
				} );
			} );

			// ── Set All buttons ────────────────────────────────────────────
			document.querySelectorAll( '.pztc-grid-set-all-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var type = btn.getAttribute( 'data-type' );
					var val = window.prompt( '<?php echo esc_js( __( 'Set every cell in this grid to:', 'pizzatier' ) ); ?>', '' );
					if ( null === val ) return;
					var num = parseFloat( val );
					if ( isNaN( num ) || num < 0 ) {
						window.alert( '<?php echo esc_js( __( 'Please enter a number ≥ 0.', 'pizzatier' ) ); ?>' );
						return;
					}
					var formatted = num.toFixed( 2 );
					var body = document.getElementById( 'pztc-grid-body-' + type );
					if ( ! body ) return;
					body.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
						inp.value = formatted;
					} );
				} );
			} );

			// ── Clear-grid buttons ────────────────────────────────────────
			document.querySelectorAll( '.pztc-grid-clear-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var type = btn.getAttribute( 'data-type' );
					if ( ! window.confirm( '<?php echo esc_js( __( 'Clear this global grid? Pricing for this layer type will fall through to per-product grids or no charge.', 'pizzatier' ) ); ?>' ) ) {
						return;
					}
					document.getElementById( 'pztc-global-grids-clear-' + type ).value = '1';
					// Visual feedback — fade the grid body so it's clear something changed.
					var body = document.getElementById( 'pztc-grid-body-' + type );
					if ( body ) {
						body.style.opacity = '0.5';
						body.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
							inp.disabled = true;
						} );
					}
				} );
			} );

			// ── Global grids confirmation modal ─────────────────────────────
			var gridsForm = document.getElementById( 'pztc-global-grids-form' );
			var modal     = document.getElementById( 'pztc-confirm-modal' );
			var modalList = document.getElementById( 'pztc-confirm-modal-list' );

			function findDirtyTypes() {
				var dirty = {};
				document.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
					var current  = ( inp.value || '' ).trim();
					var original = ( inp.getAttribute( 'data-original' ) || '' ).trim();
					if ( current !== original ) {
						dirty[ inp.getAttribute( 'data-type' ) ] = true;
					}
				} );
				// Also count any pending clears.
				document.querySelectorAll( '[id^="pztc-global-grids-clear-"]' ).forEach( function ( hid ) {
					if ( '1' === hid.value ) {
						var t = hid.id.replace( 'pztc-global-grids-clear-', '' );
						dirty[ t ] = true;
					}
				} );
				return Object.keys( dirty );
			}

			function typeLabel( slug ) {
				var pill = document.querySelector( '.pztc-grid-pill[data-type="' + slug + '"]' );
				return pill ? pill.textContent.trim() : slug;
			}

			function openModal( dirtyTypes ) {
				if ( ! modal ) return;
				modalList.innerHTML = '';
				dirtyTypes.forEach( function ( t ) {
					var li = document.createElement( 'li' );
					li.textContent = typeLabel( t );
					modalList.appendChild( li );
				} );
				modal.hidden = false;
				// Focus the confirm button for keyboard users.
				var confirmBtn = modal.querySelector( '[data-action="confirm"]' );
				if ( confirmBtn ) confirmBtn.focus();
			}

			function closeModal() {
				if ( modal ) modal.hidden = true;
			}

			if ( gridsForm ) {
				var bypassConfirm = false;
				gridsForm.addEventListener( 'submit', function ( e ) {
					if ( bypassConfirm ) return; // second pass after user confirmed
					var dirty = findDirtyTypes();
					if ( 0 === dirty.length ) {
						// Nothing changed — let the form submit (will show "nothing to save" notice).
						return;
					}
					e.preventDefault();
					openModal( dirty );
				} );

				modal.addEventListener( 'click', function ( e ) {
					var action = e.target.getAttribute( 'data-action' );
					if ( 'cancel' === action ) {
						closeModal();
					} else if ( 'confirm' === action ) {
						bypassConfirm = true;
						closeModal();
						gridsForm.submit();
					}
				} );

				document.addEventListener( 'keydown', function ( e ) {
					if ( ! modal.hidden && 'Escape' === e.key ) {
						closeModal();
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

	public function enqueue_assets( string $hook ): void {
		// Only run on this page. The hook for a submenu page registered under
		// the PizzaTier top-level menu is 'pizzatier_page_<slug>'.
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
		// Reuse the existing pricing-engine JS (calculator + mode-card UI +
		// conditional field visibility). They locate elements by ID, so they
		// work transparently on this page once the same fields are rendered.
		wp_enqueue_script(
			'pizzatier-commerce-admin-settings',
			PIZZATIER_PLUGIN_URL . 'assets/js/admin-settings.js',
			[],
			$v,
			true
		);
		wp_enqueue_script(
			'pizzatier-commerce-admin-settings-page',
			PIZZATIER_PLUGIN_URL . 'assets/js/admin-settings-page.js',
			[],
			$v,
			true
		);
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		wp_localize_script( 'pizzatier-commerce-admin-settings', 'pizzatier_commerceSettings', [
			'config' => [
				'mode'            => (string) pizzatier_get_option( 'pricing_mode', 'addon_per_layer' ),
				'freeCount'       => (int)    pizzatier_get_option( 'free_toppings_count', 0 ),
				'discThreshold'   => (int)    pizzatier_get_option( 'discount_threshold', 0 ),
				'discPercent'     => (float)  pizzatier_get_option( 'discount_percent', 0 ),
				'discMax'         => pizzatier_get_option( 'discount_max_amount', '' ) !== '' ? (float) pizzatier_get_option( 'discount_max_amount', 0 ) : null,
				'minToppingPrice' => pizzatier_get_option( 'min_topping_price', '' ) !== '' ? (float) pizzatier_get_option( 'min_topping_price', 0 ) : null,
				'maxToppingPrice' => pizzatier_get_option( 'max_topping_price', '' ) !== '' ? (float) pizzatier_get_option( 'max_topping_price', 0 ) : null,
				'rounding'        => (string) pizzatier_get_option( 'price_rounding', '' ),
				'currency'        => $currency,
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Inline styles — mirrors SettingsPage tokens, adds grids-specific CSS
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		/* ── Reuse most of the SettingsPage tokens by referencing the same
		 *    BEM classnames. Only the bits unique to the Pricing page (grids
		 *    nav, modal, callouts) need declaring here. The shared design
		 *    is duplicated below so the page is self-contained in case
		 *    SettingsPage.render_styles never runs on this page load. */

		.pztc-page-wrap { max-width: 1100px; }

		.pztc-header {
			display: flex; align-items: center; justify-content: space-between;
			flex-wrap: wrap; gap: 16px;
			background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%);
			color: #fff; border-radius: 10px;
			padding: 22px 28px; margin-bottom: 20px;
		}
		.pztc-header__brand { display: flex; align-items: center; gap: 16px; }
		.pztc-header__icon { font-size: 38px !important; width: 38px !important; height: 38px !important; color: #ff6b35; }
		.pztc-header__title  { margin: 0; font-size: 24px; font-weight: 700; color: #fff; }
		.pztc-header__tagline { margin: 3px 0 0; color: #8d97a5; font-size: 13px; }
		.pztc-header__actions { display: flex; gap: 8px; flex-wrap: wrap; }
		.pztc-header__actions .button {
			display: inline-flex; align-items: center; gap: 5px;
			padding: 7px 16px; border-radius: 50px; font-size: 13px; font-weight: 600;
			border: 2px solid rgba(255,255,255,.3); color: rgba(255,255,255,.8) !important;
			background: transparent; text-decoration: none; transition: all .2s;
		}
		.pztc-header__actions .button:hover { border-color: #ff6b35; color: #fff !important; background: transparent; box-shadow: none; }
		.pztc-header__actions .dashicons { font-size: 15px !important; width: 15px !important; height: 15px !important; margin: 0; }

		.pztc-card { background: #fff; border: 1px solid #e0e3e7; border-radius: 10px; margin-bottom: 20px; overflow: hidden; }
		.pztc-card__head { padding: 20px 24px 0; }
		.pztc-card__title { margin: 0 0 4px; font-size: 16px; display: flex; align-items: center; gap: 8px; }
		.pztc-card__title .dashicons { color: #646970; font-size: 18px !important; width: 18px !important; height: 18px !important; }
		.pztc-card__subtitle { margin: 0; color: #646970; font-size: 13px; padding-bottom: 4px; }

		.pztc-tabnav { display: flex; flex-wrap: wrap; border-bottom: 2px solid #e0e3e7; padding: 8px 12px 0; background: #f8f9fa; gap: 2px; }
		.pztc-tab { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; padding: 7px 14px 5px; border: none; border-bottom: 2px solid transparent; background: transparent; cursor: pointer; font-size: 12px; font-weight: 500; color: #646970; white-space: nowrap; margin-bottom: -2px; line-height: 1.2; transition: color .15s, border-color .15s; min-width: 84px; border-radius: 4px 4px 0 0; }
		.pztc-tab:hover { color: #1d2023; background: #eef0f2; }
		.pztc-tab--active { color: #ff6b35; border-bottom-color: #ff6b35; font-weight: 600; background: #fff; }
		.pztc-tab .dashicons { font-size: 18px !important; width: 18px !important; height: 18px !important; line-height: 1 !important; flex-shrink: 0; }

		.pztc-panels { padding: 0; }
		.pztc-panel { display: none; }
		.pztc-panel--active { display: block; }
		.pztc-panel__body { padding: 20px 24px 24px; }
		.pztc-section-desc { margin: 0 0 16px; color: #646970; font-size: 13px; }

		.pztc-form-table th { width: 240px; padding: 14px 20px 14px 0; font-size: 13px; font-weight: 600; color: #1d2023; vertical-align: top; }
		.pztc-form-table td { padding: 10px 0 14px; vertical-align: top; }
		.pztc-form-table .description { margin-top: 6px !important; font-size: 12px !important; }
		.pztc-text-input, .pztc-select-input { border: 1px solid #8c8f94 !important; border-radius: 4px !important; font-size: 13px !important; padding: 7px 10px !important; transition: border-color .15s !important; }
		.pztc-text-input { width: 320px !important; }
		.pztc-text-input:focus, .pztc-select-input:focus { border-color: #ff6b35 !important; outline: none !important; box-shadow: 0 0 0 2px rgba(255,107,53,.15) !important; }
		.pztc-textarea-input { border: 1px solid #8c8f94 !important; border-radius: 4px !important; font-size: 13px !important; padding: 8px 10px !important; width: 280px !important; resize: vertical; line-height: 1.6; }
		.pztc-toggle-label { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
		.pztc-toggle-input { position: absolute; opacity: 0; width: 0; height: 0; }
		.pztc-toggle-track { position: relative; display: inline-block; width: 40px; height: 22px; background: #c3c4c7; border-radius: 11px; transition: background .2s; flex-shrink: 0; }
		.pztc-toggle-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.25); transition: transform .2s; }
		.pztc-toggle-input:checked + .pztc-toggle-track { background: #ff6b35; }
		.pztc-toggle-input:checked + .pztc-toggle-track::after { transform: translateX(18px); }
		.pztc-field-desc { margin-top: 6px !important; color: #646970 !important; font-size: 12px !important; max-width: 500px; }

		.pztc-save-row { display: flex; align-items: center; gap: 20px; margin: 0 0 20px; padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #e0e3e7; }
		.pztc-save-btn.button-primary { background: #ff6b35 !important; border-color: #cf5519 !important; color: #fff !important; padding: 0 22px !important; height: 36px !important; font-size: 14px !important; font-weight: 600 !important; border-radius: 4px !important; }
		.pztc-save-btn.button-primary:hover, .pztc-save-btn.button-primary:focus { background: #cf5519 !important; }
		.pztc-save-row__version { font-size: 12px; color: #9ca3af; }

		.pztc-credits { padding: 4px 0 24px; font-size: 12px; color: #aaa; }
		.pztc-credits a { color: #aaa; text-decoration: none; }
		.pztc-credits a:hover { color: #ff6b35; }

		/* Grouped table rows — subtle left border accent (mirrors SettingsPage) */
		tr.pztc-row-grouped th { border-left: 3px solid #ff6b35; padding-left: 12px; }

		/* ── Global Grids tab — intro + callout ─────────────────────── */
		.pztc-grids-intro { margin-bottom: 18px; }
		.pztc-grids-callout {
			display: flex; align-items: flex-start; gap: 10px;
			background: #fff7ed; border: 1px solid #fed7aa;
			border-left: 4px solid #ff6b35;
			padding: 12px 14px; border-radius: 6px;
			margin-top: 12px;
		}
		.pztc-grids-callout .dashicons { color: #ff6b35; font-size: 20px !important; width: 20px !important; height: 20px !important; flex-shrink: 0; margin-top: 1px; }
		.pztc-grids-callout strong { display: block; font-size: 13px; color: #1d2023; margin-bottom: 2px; }
		.pztc-grids-callout span { font-size: 12px; color: #4b5563; line-height: 1.5; }

		/* Pills nav (per-layer-type) */
		.pztc-grids-nav { display: flex; flex-wrap: wrap; gap: 6px; margin: 14px 0 18px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
		.pztc-grid-pill {
			position: relative;
			display: inline-flex; align-items: center; gap: 6px;
			padding: 7px 14px; background: #f3f4f6;
			border: 1px solid #e5e7eb; border-radius: 50px;
			font-size: 12px; font-weight: 600; color: #4b5563;
			cursor: pointer; transition: all .15s;
		}
		.pztc-grid-pill:hover { background: #e5e7eb; border-color: #d1d5db; }
		.pztc-grid-pill--active { background: #ff6b35; border-color: #ff6b35; color: #fff; }
		.pztc-grid-pill__dot {
			display: inline-block; width: 6px; height: 6px; border-radius: 50%;
			background: #16a34a; box-shadow: 0 0 0 2px #fff;
		}
		.pztc-grid-pill--active .pztc-grid-pill__dot { background: #fff; box-shadow: 0 0 0 2px #ff6b35; }

		/* Per-type grid body */
		.pztc-grid-body { display: none; }
		.pztc-grid-body--active { display: block; }
		.pztc-grid-body__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; flex-wrap: wrap; }
		.pztc-grid-body__title { margin: 0; font-size: 16px; font-weight: 700; color: #1d2023; }
		.pztc-grid-body__desc  { margin: 4px 0 0; font-size: 13px; color: #646970; max-width: 640px; }
		.pztc-grid-body__actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
		.pztc-grid-body__actions .button { display: inline-flex; align-items: center; gap: 4px; }
		.pztc-grid-body__actions .button .dashicons { font-size: 16px !important; width: 16px !important; height: 16px !important; }
		.pztc-btn--danger { color: #b91c1c !important; border-color: #fecaca !important; background: #fff1f2 !important; }
		.pztc-btn--danger:hover { background: #fee2e2 !important; }

		/* Grid table — light-touch styles (admin.css carries the heavy lifting) */
		.pztc-grid-table-container { overflow-x: auto; }
		.pztc-global-grid-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; }
		.pztc-global-grid-table th, .pztc-global-grid-table td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: middle; text-align: left; }
		.pztc-global-grid-table thead th { background: #f9fafb; font-size: 12px; font-weight: 700; color: #374151; }
		.pztc-global-grid-table tbody th { background: #f9fafb; }
		.pztc-global-grid-table .pztc-cell-wrap { display: flex; align-items: center; gap: 4px; }
		.pztc-global-grid-table .pztc-cell-currency { font-size: 13px; color: #6b7280; font-weight: 600; }
		.pztc-global-grid-table .pztc-grid-input { width: 80px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; text-align: right; }
		.pztc-global-grid-table .pztc-grid-input:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.15); }
		.pztc-global-grid-table .pztc-header-label { font-size: 12px; font-weight: 700; color: #1d2023; }
		.pztc-global-grid-table .pztc-grid-corner { background: #f3f4f6; font-size: 11px; color: #6b7280; }
		.pztc-global-grid-table .pztc-grid-corner-row, .pztc-global-grid-table .pztc-grid-corner-col { display: block; line-height: 1.3; }
		.pztc-global-grid-table .pztc-grid-corner-sep { display: block; font-size: 10px; color: #9ca3af; margin: 2px 0; }

		/* ── Confirmation modal ─────────────────────────────────────── */
		.pztc-modal { position: fixed; inset: 0; z-index: 999999; display: flex; align-items: center; justify-content: center; }
		.pztc-modal[hidden] { display: none; }
		.pztc-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, .55); }
		.pztc-modal__dialog {
			position: relative; max-width: 460px; width: 92vw;
			background: #fff; border-radius: 12px;
			box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
			overflow: hidden; animation: pztc-modal-in .15s ease-out;
		}
		@keyframes pztc-modal-in { from { transform: translateY(8px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
		.pztc-modal__head {
			display: flex; align-items: center; gap: 10px;
			padding: 18px 22px 12px; border-bottom: 1px solid #f3f4f6;
		}
		.pztc-modal__head .dashicons { color: #ff6b35; font-size: 22px !important; width: 22px !important; height: 22px !important; }
		.pztc-modal__title { margin: 0; font-size: 16px; font-weight: 700; color: #1d2023; }
		.pztc-modal__body { padding: 14px 22px 6px; font-size: 13px; color: #374151; line-height: 1.6; }
		.pztc-modal__list { margin: 6px 0 12px 18px; padding: 0; }
		.pztc-modal__list li { font-size: 13px; color: #1d2023; font-weight: 600; padding: 2px 0; }
		.pztc-modal__warn { font-size: 12px; color: #6b7280; margin: 6px 0 0; }
		.pztc-modal__foot {
			display: flex; justify-content: flex-end; gap: 8px;
			padding: 12px 22px 18px; background: #fafbfc; border-top: 1px solid #f3f4f6;
		}
		.pztc-modal__btn { min-width: 92px; }
		.pztc-modal__btn.button-primary {
			background: #ff6b35 !important; border-color: #cf5519 !important;
			color: #fff !important;
		}
		.pztc-modal__btn.button-primary:hover { background: #cf5519 !important; }

		/* ── Responsive ─────────────────────────────────────────────── */
		@media ( max-width: 782px ) {
			.pztc-header { flex-direction: column; align-items: flex-start; }
			.pztc-text-input { width: 100% !important; max-width: 320px !important; }
			.pztc-form-table th { width: auto; }
			.pztc-grid-body__head { flex-direction: column; }
		}
		</style>
		<?php
	}
}
