<?php
/**
 * Embeds the PizzaTier builder on WooCommerce pizza product pages.
 *
 * Responsibilities:
 *   - Inject the PizzaTier builder shortcode at the position configured in the
 *     product's Pizza Configurator tab (before_cart / after_title / after_summary).
 *   - Render a size selector (radio button group) populated from the product's
 *     price grid sizes, or global defaults if no grid is saved yet.
 *   - Render a live price display element that JavaScript updates in real time.
 *   - Localise the price grid data and i18n strings for frontend-builder.js.
 *   - Enqueue frontend CSS and JS only on single pizza product pages.
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceGrid\Grid;

class FrontendEmbed {

	/** @var Grid */
	private Grid $grid_model;

	/**
	 * Per-page instance counter — incremented each time render_builder_section()
	 * is called so multiple builders on the same page get unique DOM IDs.
	 *
	 * @var int
	 */
	private static int $instance_count = 0;

	/**
	 * Whether the builder has already been injected on this page load.
	 * Prevents double-render when both the_content filter and WC hooks fire.
	 *
	 * @var bool
	 */
	private static bool $injected = false;

	/**
	 * Filter: pzdemo_already_injected — returns true when this class has
	 * already injected the builder, so the demo theme fallback can skip.
	 */
	private function register_injected_filter(): void {
		add_filter( 'pzdemo_already_injected', function() {
			return self::$injected;
		} );
	}

	public function __construct( Grid $grid_model ) {
		$this->grid_model = $grid_model;
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// woocommerce_loaded fires from WooCommerce's own plugins_loaded
		// callback at priority -1, so it has already fired by the time this
		// runs — previously at plugins_loaded priority 20 (the PizzaTier
		// bootstrap), and now at priority 10 (PizzaTier's own boot, after the
		// merge). Register frontend hooks directly; guard with is_admin().
		$this->register_frontend_hooks();
	}

	/**
	 * Register all frontend hooks.
	 * Only fires on the frontend — never in admin context.
	 */
	public function register_frontend_hooks(): void {
		if ( is_admin() ) {
			return;
		}

		// Register our WooCommerce template override directory so WC can find
		// single-product/add-to-cart/pizza.php for pizza product pages.
		add_filter( 'woocommerce_locate_template',      [ $this, 'locate_wc_template' ], 10, 3 );
		add_filter( 'woocommerce_locate_template_path', [ $this, 'locate_wc_template_path' ], 10, 3 );

		// Enqueue assets — only on single product pages, checked inside the method.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// ── Universal injection via the_content filter ────────────────────
		// Works with any theme regardless of whether it fires WC product hooks.
		// Priority 20 — after standard content filters, before most plugins.
		// A static flag prevents double-render if WC hooks also fire.
		add_filter( 'the_content', [ $this, 'inject_via_content' ], 20 );

		// ── WC hook injection (secondary — themes that call WC actions) ───
		// inject_via_content() skips if these already ran (tracked via $injected).
		add_action( 'woocommerce_before_add_to_cart_form',   [ $this, 'maybe_inject_before_cart' ] );
		add_action( 'woocommerce_single_product_summary',    [ $this, 'maybe_inject_after_title' ],   15 );
		add_action( 'woocommerce_single_product_summary',    [ $this, 'maybe_inject_after_summary' ], 35 );

		// Suppress the default WC quantity field for pizza products —
		// quantity is handled by the builder configuration.
		add_filter( 'woocommerce_quantity_input_args', [ $this, 'maybe_hide_quantity_field' ], 10, 2 );
		add_filter( 'woocommerce_is_sold_individually', [ $this, 'mark_pizza_sold_individually' ], 10, 2 );

		// Replace the WC product price display with a live-updating element.
		// Only registered when the setting is enabled.
		if ( (bool) pizzatier_get_option( 'hide_wc_price', false ) ) {
			add_filter( 'woocommerce_get_price_html', [ $this, 'filter_pizza_price_html' ], 10, 2 );
		}

		// JSON-LD schema markup for pizza product pages.
		if ( (bool) pizzatier_get_option( 'schema_markup', false ) ) {
			add_action( 'wp_head', [ $this, 'output_pizza_schema_markup' ] );
		}
	}

	// -------------------------------------------------------------------------
	// Position-based injection
	// -------------------------------------------------------------------------

	/**
	 * Universal content injection via the_content filter.
	 *
	 * Appends the builder section to the product description. Runs for any
	 * theme — no WC hook support required. Guards against double-render:
	 * if the WC action hooks already fired (themes that do call them), the
	 * static $injected flag is set and this method returns the content unchanged.
	 *
	 * @param string $content  Post content passed through the_content filter.
	 * @return string
	 */
	public function inject_via_content( string $content ): string {
		// Only on the main query's single product page, not in loops/widgets.
		if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// Don't run twice if WC hooks already injected the builder.
		if ( self::$injected ) {
			return $content;
		}

		// Guard: WooCommerce fires the_content() inside product description tabs
		// (woocommerce_after_single_product_summary). If WC summary hooks have
		// already run, we are inside a tab — skip to avoid dumping builder HTML
		// into the description. Only inject here if the WC summary hook hasn't
		// fired yet (theme doesn't call WC hooks) or fired but didn't inject us.
		if ( did_action( 'woocommerce_after_single_product_summary' ) > 0 ) {
			return $content;
		}

		$product_id = (int) get_queried_object_id();
		if ( ! $product_id || ! $this->post_has_pizza_type( $product_id ) ) {
			return $content;
		}

		$template_slug = sanitize_key( (string) get_post_meta( $product_id, '_pizzatier_builder_template', true ) );

		ob_start();
		$this->render_builder_section( $product_id, $template_slug );
		$builder_html = ob_get_clean();

		self::$injected = true;

		// Append after content — builder always appears below the description.
		return $content . $builder_html;
	}

	/**
	 * Fires on woocommerce_before_add_to_cart_form.
	 * Injects builder if position is 'before_cart' (the default).
	 */
	public function maybe_inject_before_cart(): void {
		$this->maybe_inject( 'before_cart' );
	}

	/**
	 * Fires on woocommerce_single_product_summary (priority 15 = after title).
	 * Injects builder if position is 'after_title'.
	 */
	public function maybe_inject_after_title(): void {
		$this->maybe_inject( 'after_title' );
	}

	/**
	 * Fires on woocommerce_single_product_summary (priority 35 = after short desc).
	 * Injects builder if position is 'after_summary'.
	 */
	public function maybe_inject_after_summary(): void {
		$this->maybe_inject( 'after_summary' );
	}

	/**
	 * Core injection logic — only outputs if the current product is a pizza
	 * and the configured position matches $current_position.
	 *
	 * @param string $current_position  One of: before_cart, after_title, after_summary.
	 */
	private function maybe_inject( string $current_position ): void {
		// Already rendered via the_content fallback — skip.
		if ( self::$injected ) {
			return;
		}

		global $product;

		// Inside WC template loop $product is set; but verify it's a pizza.
		// Fall back to taxonomy check if the global isn't a WC_Product yet.
		if ( $product instanceof \WC_Product ) {
			$product_id = $product->get_id();
			// Accept 'pizza' type OR any product with a PizzaTier template configured.
			if ( 'pizza' !== $product->get_type() && ! $this->post_has_pizza_type( $product_id ) ) {
				return;
			}
		} else {
			$product_id = (int) get_queried_object_id();
			if ( ! $product_id || ! $this->post_has_pizza_type( $product_id ) ) {
				return;
			}
		}
		$saved_position   = (string) get_post_meta( $product_id, '_pizzatier_builder_position', true );
		$global_default   = (string) pizzatier_get_option( 'builder_position_default', 'before_cart' );
		$active_position  = $saved_position ?: $global_default;

		if ( $active_position !== $current_position ) {
			return;
		}

		$template_slug = sanitize_key( (string) get_post_meta( $product_id, '_pizzatier_builder_template', true ) );

		self::$injected = true;
		$this->render_builder_section( $product_id, $template_slug );
	}

	// -------------------------------------------------------------------------
	// Builder section HTML
	// -------------------------------------------------------------------------

	/**
	 * Render the full builder section: size selector + PizzaTier embed + price bar.
	 * Each call increments a static counter so multiple instances on one page
	 * receive unique DOM IDs, preventing JS conflicts.
	 *
	 * @param int    $product_id
	 * @param string $template_slug  PizzaTier template slug (e.g. 'colorbox'). Empty string = not configured.
	 */
	private function render_builder_section( int $product_id, string $template_slug ): void {
		self::$instance_count++;
		$idx = self::$instance_count;   // 1-based instance index for this page load

		$sizes           = $this->grid_model->get_sizes( $product_id );
		// WooCommerce returns currency symbols as HTML entities (USD = "&#36;").
		// Decode to a real UTF-8 character so it is safe for both esc_html()
		// output and JS .textContent writes (which would otherwise render the
		// raw entity literally in the Add to Cart bar).
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		$show_size_selector = (bool) pizzatier_get_option( 'show_size_selector', true );
		$show_price_bar     = (bool) pizzatier_get_option( 'show_price_bar', true );
		$show_order_note    = (bool) pizzatier_get_option( 'enable_order_notes', false );

		// ── Resolve per-product default layers → shortcode attributes ─────
		// _pizzatier_commerce_default_layers stores { type_singular => post_id }.
		// The shortcode expects slug strings (e.g. default_crust="thin-crispy").
		$shortcode_atts = $this->build_default_layer_atts( $product_id );

		// ── Resolve per-product enabled layers → restrict="" attribute ────
		// _pizzatier_commerce_enabled_layers stores an array of post IDs.
		// The shortcode accepts restrict="slug1,slug2,..." to limit visible items.
		$restrict_atts = $this->build_restrict_atts( $product_id );
		?>
		<div
			class="pztc-builder-section"
			id="pztc-builder-section-<?php echo esc_attr( $idx ); ?>"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-pztc-instance="<?php echo esc_attr( $idx ); ?>"
		>

			<?php $this->render_size_selector( $sizes, $currency_symbol, $idx ); ?>

			<?php if ( $template_slug ) : ?>
				<div class="pztc-builder-embed" id="pztc-builder-embed-<?php echo esc_attr( $idx ); ?>">
					<?php
					$sc = '[pizza_builder'
						. ' template="' . esc_attr( $template_slug ) . '"'
						. ' id="pztc-' . esc_attr( $idx ) . '"'
						. $shortcode_atts
						. $restrict_atts
						. ']';
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo do_shortcode( $sc );
					?>
				</div>
			<?php else : ?>
				<div class="pztc-builder-embed pztc-builder-embed--no-template">
					<p class="pztc-notice">
						<?php esc_html_e( 'Pizza builder not configured for this product.', 'pizzatier' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php $show_order_note && $this->render_order_note_field( $idx ); ?>

			<?php
			// The checkout bar (Add to Cart + live price) is rendered INSIDE the template
			// via the pizzatier_builder_action_bar hook in CartIntegration::render_cart_button().
			// The legacy pztc-price-bar is suppressed here to avoid a duplicate bar
			// appearing outside the template container on every page.
			?>

		</div><!-- .pztc-builder-section -->
		<?php
	}

	/**
	 * Build shortcode attribute string for per-product default layers.
	 *
	 * Reads _pizzatier_commerce_default_layers (map of type→post_id), resolves each to a
	 * post slug, and returns a string like:
	 *   ' default_crust="thin" default_sauce="marinara" default_cheese="mozzarella"'
	 *
	 * These map directly to the [pizza_builder] shortcode attributes that
	 * PizzaBuilder::build_dynamic() reads to set the initial canvas state.
	 *
	 * @param int $product_id
	 * @return string  Space-prefixed attribute string, or '' if nothing saved.
	 */
	private function build_default_layer_atts( int $product_id ): string {
		$raw = get_post_meta( $product_id, '_pizzatier_default_layers', true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return '';
		}

		// Supported shortcode attribute names keyed by type singular.
		$attr_map = [
			'crust'   => 'default_crust',
			'sauce'   => 'default_sauce',
			'cheese'  => 'default_cheese',
			'drizzle' => 'default_drizzle',
			'cut'     => 'default_cut',
		];

		$out = '';
		foreach ( $attr_map as $type => $attr_name ) {
			if ( empty( $raw[ $type ] ) ) {
				continue;
			}
			$slug = get_post_field( 'post_name', (int) $raw[ $type ] );
			if ( $slug ) {
				$out .= ' ' . $attr_name . '="' . esc_attr( $slug ) . '"';
			}
		}

		// Toppings: stored as an array under 'toppings' key (wizard/preset path),
		// or as a single post ID under 'topping' key (ProductTab radio path).
		// Normalise both into a flat array of IDs.
		$topping_ids = [];
		if ( ! empty( $raw['toppings'] ) && is_array( $raw['toppings'] ) ) {
			$topping_ids = array_map( 'absint', $raw['toppings'] );
		} elseif ( ! empty( $raw['topping'] ) ) {
			$topping_ids = [ absint( $raw['topping'] ) ];
		}
		$topping_ids = array_filter( $topping_ids );

		if ( $topping_ids ) {
			$topping_slugs = [];
			foreach ( $topping_ids as $tid ) {
				$slug = get_post_field( 'post_name', $tid );
				if ( $slug ) {
					$topping_slugs[] = $slug;
				}
			}
			if ( $topping_slugs ) {
				$out .= ' default_toppings="' . esc_attr( implode( ',', $topping_slugs ) ) . '"';
			}
		}

		return $out;
	}

	/**
	 * Build a restrict="" shortcode attribute string from enabled layers.
	 *
	 * _pizzatier_commerce_enabled_layers is an array of post IDs allowed for this product.
	 * An empty array means "all layers allowed" (no restriction).
	 * Returns ' restrict="slug1,slug2,..."' or '' if unrestricted.
	 *
	 * @param int $product_id
	 * @return string
	 */
	private function build_restrict_atts( int $product_id ): string {
		$raw = get_post_meta( $product_id, '_pizzatier_enabled_layers', true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return '';
		}

		$slugs = [];
		foreach ( $raw as $pid ) {
			$slug = get_post_field( 'post_name', (int) $pid );
			if ( $slug ) {
				$slugs[] = $slug;
			}
		}

		if ( empty( $slugs ) ) {
			return '';
		}

		return ' restrict="' . esc_attr( implode( ',', $slugs ) ) . '"';
	}

	// -------------------------------------------------------------------------
	// Size selector
	// -------------------------------------------------------------------------

	/**
	 * Render the pizza size selector above the builder.
	 *
	 * Always renders as a full-width "Step 1" step header so that size is
	 * never ambiguous and pricing always has a valid size to work with.
	 * The first size is pre-selected both in HTML (checked attribute) and
	 * via the --active CSS class so the JS pricing engine starts immediately.
	 *
	 * When show_size_selector is disabled in settings the wrapper is visually
	 * hidden but the hidden radio group still keeps the first size "checked"
	 * so JS can read it — pricing remains functional.
	 *
	 * @param string[] $sizes
	 * @param string   $currency_symbol
	 * @param int      $idx  Builder instance index for unique IDs.
	 */
	private function render_size_selector( array $sizes, string $currency_symbol, int $idx = 1 ): void {
		if ( empty( $sizes ) ) {
			return;
		}
		$heading      = pizzatier_get_option( 'size_selector_label', '' );
		if ( '' === $heading ) {
			$heading = __( 'Choose your size', 'pizzatier' );
		}
		$selector_style  = (string) pizzatier_get_option( 'size_selector_style', 'pills' );
		$show_selector   = (bool) pizzatier_get_option( 'show_size_selector', true );
		?>
		<div
			class="pztc-size-selector<?php echo $show_selector ? ' pztc-size-selector--visible' : ' pztc-size-selector--hidden'; ?>"
			id="pztc-size-selector-<?php echo esc_attr( $idx ); ?>"
			role="group"
			aria-label="<?php echo esc_attr( $heading ); ?>"
		>
			<?php if ( $show_selector ) : ?>
			<div class="pztc-size-selector__step-label">
				<span class="pztc-size-selector__step-num" aria-hidden="true">1</span>
				<span class="pztc-size-selector__step-text"><?php echo esc_html( $heading ); ?></span>
			</div>
			<?php endif; ?>

			<div class="pztc-size-selector__options<?php echo 'dropdown' === $selector_style ? ' pztc-size-selector__options--dropdown' : ''; ?>">
				<?php if ( 'dropdown' === $selector_style ) : ?>
					<select
						class="pztc-size-select-native"
						name="pizzatier_commerce_size_<?php echo esc_attr( $idx ); ?>"
						id="pztc-size-native-<?php echo esc_attr( $idx ); ?>"
					>
						<?php foreach ( $sizes as $i => $size ) : ?>
							<option value="<?php echo esc_attr( $size ); ?>" <?php selected( 0, $i ); ?>>
								<?php echo esc_html( $size ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php /* Hidden radios keep the JS event model consistent */ ?>
					<?php foreach ( $sizes as $i => $size ) :
						$input_id = 'pztc-size-' . $idx . '-' . sanitize_html_class( strtolower( $size ) );
						?>
						<input
							type="radio"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="pizzatier_commerce_size_<?php echo esc_attr( $idx ); ?>"
							value="<?php echo esc_attr( $size ); ?>"
							class="pztc-size-radio pztc-size-radio--hidden"
							<?php checked( 0, $i ); ?>
						/>
					<?php endforeach; ?>
				<?php else : ?>
					<?php foreach ( $sizes as $i => $size ) :
						$input_id = 'pztc-size-' . $idx . '-' . sanitize_html_class( strtolower( $size ) );
						$is_cards = ( 'cards' === $selector_style );
						?>
						<label
							class="pztc-size-option pztc-size-option--<?php echo esc_attr( $selector_style ); ?><?php echo 0 === $i ? ' pztc-size-option--active' : ''; ?>"
							for="<?php echo esc_attr( $input_id ); ?>"
						>
							<input
								type="radio"
								id="<?php echo esc_attr( $input_id ); ?>"
								name="pizzatier_commerce_size_<?php echo esc_attr( $idx ); ?>"
								value="<?php echo esc_attr( $size ); ?>"
								class="pztc-size-radio"
								<?php checked( 0, $i ); ?>
							/>
							<span class="pztc-size-option__name"><?php echo esc_html( $size ); ?></span>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Order note field
	// -------------------------------------------------------------------------

	/**
	 * Render the optional per-pizza customer note textarea.
	 *
	 * @param int $idx  Builder instance index for unique IDs.
	 */
	private function render_order_note_field( int $idx = 1 ): void {
		$label       = pizzatier_get_option( 'order_note_label', '' );
		$placeholder = pizzatier_get_option( 'order_note_placeholder', '' );

		if ( '' === $label ) {
			$label = __( 'Special instructions for this pizza', 'pizzatier' );
		}
		if ( '' === $placeholder ) {
			$placeholder = __( 'e.g. extra crispy, no garlic…', 'pizzatier' );
		}
		?>
		<div class="pztc-order-note" id="pztc-order-note-<?php echo esc_attr( $idx ); ?>">
			<label
				class="pztc-order-note__label"
				for="pztc-order-note-input-<?php echo esc_attr( $idx ); ?>"
			>
				<?php echo esc_html( $label ); ?>
			</label>
			<textarea
				id="pztc-order-note-input-<?php echo esc_attr( $idx ); ?>"
				class="pztc-order-note__input"
				name="pizzatier_commerce_order_note"
				rows="2"
				maxlength="500"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
			></textarea>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Price bar
	// -------------------------------------------------------------------------

	/**
	 * Render the live price display bar shown below the builder.
	 *
	 * JavaScript targets the instance-scoped IDs to update the total in real time.
	 *
	 * @param string $currency_symbol
	 * @param int    $idx  Builder instance index for unique IDs.
	 */
	private function render_price_bar( string $currency_symbol, int $idx = 1, array $sizes = [] ): void {
		$fallback       = pizzatier_get_option( 'fallback_price_label', __( 'Price calculated on selection', 'pizzatier' ) );
		$bar_label      = pizzatier_get_option( 'price_bar_label', '' );
		$show_selector  = (bool) pizzatier_get_option( 'show_size_selector', true );
		if ( '' === $bar_label ) {
			$bar_label = __( 'Your pizza total:', 'pizzatier' );
		}
		// Show inline size chips in the bar when: selector is enabled AND multiple sizes exist.
		$bar_has_sizes = $show_selector && count( $sizes ) > 0;
		?>
		<div class="pztc-price-bar<?php echo $bar_has_sizes ? ' pztc-price-bar--has-sizes' : ''; ?>" id="pztc-price-bar-<?php echo esc_attr( $idx ); ?>">

			<?php if ( $bar_has_sizes ) : ?>
			<?php // ── Inline size row inside price bar ── ?>
			<div class="pztc-price-bar__sizes" role="group" aria-label="<?php esc_attr_e( 'Choose size', 'pizzatier' ); ?>">
				<span class="pztc-price-bar__sizes-label"><?php esc_html_e( 'Size', 'pizzatier' ); ?></span>
				<div class="pztc-price-bar__sizes-chips">
					<?php foreach ( $sizes as $i => $size ) :
						$chip_id = 'pztc-bar-size-chip-' . $idx . '-' . sanitize_html_class( strtolower( $size ) );
					?>
					<label class="pztc-bar-chip<?php echo 0 === $i ? ' pztc-bar-chip--active' : ''; ?>" for="<?php echo esc_attr( $chip_id ); ?>">
						<input
							type="radio"
							id="<?php echo esc_attr( $chip_id ); ?>"
							name="pizzatier_commerce_size_<?php echo esc_attr( $idx ); ?>"
							value="<?php echo esc_attr( $size ); ?>"
							class="pztc-size-radio"
							<?php checked( 0, $i ); ?>
						/>
						<?php echo esc_html( $size ); ?>
					</label>
				<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php // ── Per-layer breakdown list (populated by frontend-builder.js) ── ?>
			<ul class="pztc-layer-breakdown" id="pztc-layer-breakdown-<?php echo esc_attr( $idx ); ?>" style="display:none;" aria-label="<?php esc_attr_e( 'Selected toppings and prices', 'pizzatier' ); ?>">
				<?php // Items injected by JS ?>
			</ul>

			<?php // ── Total row ── ?>
			<div class="pztc-price-bar__total">
				<div class="pztc-price-bar__label">
					<?php echo esc_html( $bar_label ); ?>
				</div>
				<div class="pztc-price-bar__amount" id="pztc-price-bar-amount-<?php echo esc_attr( $idx ); ?>">
					<span class="pztc-live-price" id="pztc-live-price-<?php echo esc_attr( $idx ); ?>" data-currency="<?php echo esc_attr( $currency_symbol ); ?>">
						<span class="pztc-live-price__currency"><?php echo esc_html( $currency_symbol ); ?></span>
						<span class="pztc-live-price__value">0.00</span>
					</span>
					<span class="pztc-price-bar__fallback" id="pztc-price-fallback-<?php echo esc_attr( $idx ); ?>" style="display:none;">
						<?php echo esc_html( $fallback ); ?>
					</span>
				</div>
			</div>

		</div><!-- .pztc-price-bar -->
		<?php
	}

	// -------------------------------------------------------------------------
	// WooCommerce filters
	// -------------------------------------------------------------------------

	/**
	 * Mark pizza products as sold individually so WooCommerce renders a
	 * simpler add-to-cart button without the quantity input box.
	 *
	 * @param bool        $sold_individually
	 * @param \WC_Product $product
	 * @return bool
	 */
	public function mark_pizza_sold_individually( bool $sold_individually, \WC_Product $product ): bool {
		if ( 'pizza' === $product->get_type() ) {
			return true;
		}
		return $sold_individually;
	}

	/**
	 * Hide the quantity input args for pizza products as a belt-and-braces
	 * measure on top of sold_individually.
	 *
	 * @param array       $args
	 * @param \WC_Product $product
	 * @return array
	 */
	public function maybe_hide_quantity_field( array $args, \WC_Product $product ): array {
		if ( 'pizza' === $product->get_type() ) {
			$args['min_value'] = 1;
			$args['max_value'] = 1;
		}
		return $args;
	}

	/**
	 * Replace the static WC price HTML for pizza products with a wrapper
	 * that our JS can update, seeded with the base product price.
	 *
	 * Only registered when the "hide_wc_price" setting is enabled.
	 * Only fires on single product pages (not loop/archive views).
	 *
	 * @param string      $price_html
	 * @param \WC_Product $product
	 * @return string
	 */
	public function filter_pizza_price_html( string $price_html, \WC_Product $product ): string {
		if ( 'pizza' !== $product->get_type() ) {
			return $price_html;
		}

		// Only swap on single product pages to avoid affecting archive loops.
		if ( ! is_product() ) {
			return $price_html;
		}

		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		$base_price = number_format( (float) $product->get_price(), (int) get_option( 'woocommerce_price_num_decimals', 2 ), get_option( 'woocommerce_price_decimal_sep', '.' ), get_option( 'woocommerce_price_thousand_sep', ',' ) );

		return '<span class="pztc-price-html-wrapper woocommerce-Price-amount amount">' .
			'<span class="woocommerce-Price-currencySymbol">' . esc_html( $currency ) . '</span>' .
			'<span id="pztc-wc-price-value">' . esc_html( $base_price ) . '</span>' .
			'</span>';
	}

	/**
	 * Output JSON-LD schema markup for pizza product pages.
	 *
	 * Emits an @type: Product schema with FoodEstablishmentReservation context
	 * extensions for the pizza configurator. Only fires on single pizza product
	 * pages when the schema_markup setting is enabled.
	 *
	 * WooCommerce already outputs a basic Product schema via its own structured
	 * data output (woocommerce_structured_data_product). This method adds
	 * pizza-specific properties (menu item, offer with size variants) that WC
	 * cannot infer on its own.
	 */
	public function output_pizza_schema_markup(): void {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof \WC_Product ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is WooCommerce's own global, populated here only when the loop has not already set it. It cannot be prefixed.
			$product = wc_get_product( get_the_ID() );
		}

		if ( ! $product || 'pizza' !== $product->get_type() ) {
			return;
		}

		$product_id      = $product->get_id();
		$currency        = get_option( 'woocommerce_currency', 'USD' );
		$base_price      = (float) $product->get_price();
		$product_url     = get_permalink( $product_id );
		$product_name    = $product->get_name();
		$product_desc    = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
		$thumbnail_url   = get_the_post_thumbnail_url( $product_id, 'woocommerce_single' ) ?: '';

		// Build size-based offers from the price grid.
		$grid_model = new \PizzaTier\Commerce\PriceGrid\Grid();
		$sizes      = $grid_model->get_sizes( $product_id );

		$offers = [];
		foreach ( $sizes as $size ) {
			$offers[] = [
				'@type'         => 'Offer',
				'name'          => $size,
				'price'         => $base_price > 0 ? number_format( $base_price, 2, '.', '' ) : '0.00',
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => esc_url( $product_url ),
			];
		}

		// If no size grid, fall back to a single offer.
		if ( empty( $offers ) ) {
			$offers[] = [
				'@type'         => 'Offer',
				'price'         => $base_price > 0 ? number_format( $base_price, 2, '.', '' ) : '0.00',
				'priceCurrency' => $currency,
				'availability'  => 'https://schema.org/InStock',
				'url'           => esc_url( $product_url ),
			];
		}

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => [ 'Product', 'MenuItem' ],
			'name'        => $product_name,
			'url'         => esc_url( $product_url ),
			'offers'      => count( $offers ) === 1 ? $offers[0] : $offers,
		];

		if ( $product_desc ) {
			$schema['description'] = $product_desc;
		}

		if ( $thumbnail_url ) {
			$schema['image'] = esc_url( $thumbnail_url );
		}

		// Allow themes/plugins to modify the schema before output.
		$schema = apply_filters( 'pizzatier_commerce_product_schema', $schema, $product );

		if ( empty( $schema ) ) {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -------------------------------------------------------------------------
	// WooCommerce template override
	// -------------------------------------------------------------------------

	/**
	 * Point WooCommerce to our plugin's woocommerce/ directory for pizza templates.
	 *
	 * @param string $template      Full path to the located template.
	 * @param string $template_name Relative template name (e.g. single-product/add-to-cart/pizza.php).
	 * @param string $template_path WC template path.
	 * @return string
	 */
	public function locate_wc_template( string $template, string $template_name, string $template_path ): string {
		$plugin_template = PIZZATIER_PLUGIN_DIR . 'woocommerce/' . $template_name;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
		return $template;
	}

	/**
	 * Register our woocommerce/ folder as a template search path.
	 *
	 * @param string $template_path
	 * @param string $template_name
	 * @param string $theme_template_path
	 * @return string
	 */
	public function locate_wc_template_path( string $template_path, string $template_name, string $theme_template_path ): string {
		if ( file_exists( PIZZATIER_PLUGIN_DIR . 'woocommerce/' . $template_name ) ) {
			return PIZZATIER_PLUGIN_DIR . 'woocommerce/';
		}
		return $template_path;
	}

	// -------------------------------------------------------------------------
	// Pizza type helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether a post has the 'pizza' product_type taxonomy term assigned.
	 *
	 * This is reliable at wp_enqueue_scripts time (before the WP loop / before
	 * WC resolves the product class), unlike $product->get_type() which requires
	 * the WC product object to already be built with the correct class.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	private function post_has_pizza_type( int $post_id ): bool {
		if ( ! $post_id ) {
			return false;
		}
		// Primary check: product_type taxonomy term 'pizza'.
		if ( has_term( 'pizza', 'product_type', $post_id ) ) {
			return true;
		}
		// Fallback: any product that has a PizzaTier builder template
		// configured should show the builder regardless of formal product type.
		// This handles products that were configured but whose type term wasn't
		// saved correctly (e.g. saved before PizzaTier was active).
		$template = get_post_meta( $post_id, '_pizzatier_builder_template', true );
		return '' !== (string) $template;
	}

	/**
	 * Proactively enqueue PizzaTier base plugin CSS/JS for a pizza product page.
	 *
	 * Normally AssetManager::enqueue_frontend() queues template assets only after
	 * BuilderShortcode::render() calls AssetManager::require_template(). When
	 * we inject via the_content filter the shortcode runs after wp_enqueue_scripts,
	 * so template assets would be silently missing. This method reads the saved
	 * template slug and enqueues everything at wp_enqueue_scripts time.
	 *
	 * @param int $product_id
	 */
	private function enqueue_pizzatier_template_assets( int $product_id ): void {
		if ( ! class_exists( 'PizzaTier\\Assets\\AssetManager' ) ) {
			return;
		}

		// Determine which template slug this product uses.
		$template_slug = sanitize_key( (string) get_post_meta( $product_id, '_pizzatier_builder_template', true ) );

		// Do not attempt to enqueue template assets when no template is selected.
		// Calling require_template('') would load whatever the global default is,
		// causing that template's CSS/JS to be output on the page even though the
		// builder will not render — this produces visible CSS text in the page.
		if ( '' === $template_slug ) {
			return;
		}

		// Register it so AssetManager knows to load it.
		\PizzaTier\Assets\AssetManager::require_template( $template_slug );

		// Trigger the base plugin's full asset enqueue (idempotent — WP
		// wp_enqueue_style/script deduplicate by handle).
		( new \PizzaTier\Assets\AssetManager() )->enqueue_frontend();
	}

	// -------------------------------------------------------------------------
	// Asset enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue frontend CSS and JS, and localise the price grid data.
	 * Only runs on single pizza product pages.
	 *
	 * For multi-instance support, each builder rendered on the page gets its
	 * own JS config object (pizzatier_commerceFrontend_1, pizzatier_commerceFrontend_2, …) injected
	 * via wp_add_inline_script. The base pizzatier_commerceFrontend object is still set
	 * for backward compatibility (points to the last registered instance).
	 */
	public function enqueue_assets(): void {
		// is_singular('product') is reliable at wp_enqueue_scripts time;
		// is_product() requires the main query loop to be running.
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		// Use get_queried_object_id() — reliable before the loop starts.
		$product_id = (int) get_queried_object_id();
		if ( ! $product_id ) {
			return;
		}

		// Check the product_type taxonomy term directly — faster and reliable
		// before WC has resolved the $product global with the correct class.
		if ( ! $this->post_has_pizza_type( $product_id ) ) {
			return;
		}

		// Resolve the WC product object for price/grid data below.
		global $product;
		if ( ! $product instanceof \WC_Product || $product->get_id() !== $product_id ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is WooCommerce's own global; it cannot be prefixed.
			$product = wc_get_product( $product_id );
		}
		if ( ! $product ) {
			return;
		}
		$product_mode = (string) get_post_meta( $product_id, '_pizzatier_pricing_mode', true );
		$url          = PIZZATIER_PLUGIN_URL;
		$ver          = PIZZATIER_VERSION;

		// ── CSS ───────────────────────────────────────────────────────────
		wp_enqueue_style(
			'pizzatier-commerce-frontend',
			$url . 'assets/css/frontend.css',
			[],
			$ver
		);

		// ── JS ────────────────────────────────────────────────────────────
		wp_enqueue_script(
			'pizzatier-commerce-frontend-builder',
			$url . 'assets/js/frontend-builder.js',
			[ 'jquery' ],
			$ver,
			true
		);

		wp_enqueue_script(
			'pizzatier-commerce-cart',
			$url . 'assets/js/cart.js',
			[ 'jquery', 'pizzatier-commerce-frontend-builder', 'pizzatier-commerce-checkout-bar' ],
			$ver,
			true
		);

		// ── PizzaTier base plugin assets (template CSS + JS) ─────────────
		// The base plugin's AssetManager::enqueue_frontend() normally enqueues
		// template assets only after the shortcode runs (which calls
		// AssetManager::require_template()). But when we inject via the_content
		// filter the shortcode executes AFTER wp_enqueue_scripts, so template
		// assets would be missed. We proactively enqueue them here using the
		// saved template slug so they're available when the builder renders.
		$this->enqueue_pizzatier_template_assets( $product_id );

		// ── Price grid data ───────────────────────────────────────────────
		$grid      = $this->grid_model->get( $product_id );
		$sizes     = $grid ? $grid['sizes']     : $this->grid_model->default_sizes();
		$fractions = $grid ? $grid['fractions'] : $this->grid_model->default_fractions();
		$cells     = $grid ? $grid['cells']     : [];

		$cells_clean = [];
		foreach ( $cells as $key => $price ) {
			$cells_clean[ $key ] = (float) $price;
		}

		// ── Flat grid data (non-fraction layer types) ─────────────────────
		$flat_grid        = $this->grid_model->get_flat( $product_id );
		$flat_cells_clean = [];
		$flat_layer_types = [];
		if ( $flat_grid ) {
			$flat_layer_types = $flat_grid['layer_types'];
			foreach ( $flat_grid['cells'] as $key => $price ) {
				$flat_cells_clean[ $key ] = (float) $price;
			}
		}

		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		$fallback_label = pizzatier_get_option(
			'fallback_price_label',
			__( 'Price calculated on selection', 'pizzatier' )
		);

		// ── Nutrition settings ────────────────────────────────────────────
		$nutrition_enabled        = (bool) pizzatier_get_option( 'nutrition_enabled', false );
		$nutrition_display        = (string) pizzatier_get_option( 'nutrition_display', 'tooltip' );  // tooltip|inline|panel
		$nutrition_show_calories  = (bool) pizzatier_get_option( 'nutrition_show_calories', true );
		$nutrition_show_fat       = (bool) pizzatier_get_option( 'nutrition_show_fat', false );
		$nutrition_show_carbs     = (bool) pizzatier_get_option( 'nutrition_show_carbs', false );
		$nutrition_show_protein   = (bool) pizzatier_get_option( 'nutrition_show_protein', false );
		$nutrition_show_sodium    = (bool) pizzatier_get_option( 'nutrition_show_sodium', false );
		$nutrition_show_allergens = (bool) pizzatier_get_option( 'nutrition_show_allergens', false );
		$nutrition_show_summary   = (bool) pizzatier_get_option( 'nutrition_show_summary', false );

		// ── Nutrition data per CPT layer ──────────────────────────────────
		// Build a map of { postSlug => { calories, fat, … } } for all ingredient CPTs.
		// Only populated when nutrition is enabled, to avoid unnecessary queries.
		$nutrition_data = (object) [];
		if ( $nutrition_enabled && class_exists( 'PizzaTier\\Admin\\NutritionMetaBox' ) ) {
			$cpt_map = [
				'pizzatier_toppings' => true,
				'pizzatier_crusts'   => true,
				'pizzatier_sauces'   => true,
				'pizzatier_cheeses'  => true,
				'pizzatier_drizzles' => true,
			];
			foreach ( array_keys( $cpt_map ) as $cpt ) {
				$posts = get_posts( [
					'post_type'      => $cpt,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
				] );
				foreach ( $posts as $pid ) {
					$slug = get_post_field( 'post_name', $pid );
					if ( ! $slug ) continue;
					$nutr = \PizzaTier\Admin\NutritionMetaBox::get( $pid );
					if ( ! empty( $nutr ) ) {
						$nutrition_data->$slug = $nutr;
					}
				}
			}
		}

		// ── Layer groups (hierarchical taxonomy) ──────────────────────────
		// Enabled via Pro's own setting. (The base plugin provides the
		// pizzatier_ingredient_group taxonomy but no enable-flag option, so
		// the toggle lives with the cart & pricing settings.)
		$groups_enabled = (bool) pizzatier_get_option( 'layer_groups_enabled', false );
		$layer_groups   = (object) [];
		if ( $groups_enabled && taxonomy_exists( 'pizzatier_ingredient_group' ) ) {
			$terms = get_terms( [
				'taxonomy'   => 'pizzatier_ingredient_group',
				'hide_empty' => false,
			] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$posts = get_posts( [
						'post_type'      => [
							'pizzatier_toppings', 'pizzatier_crusts', 'pizzatier_sauces',
							'pizzatier_cheeses', 'pizzatier_drizzles',
						],
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'fields'         => 'ids',
						'tax_query'      => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Ingredient group membership is only available as a taxonomy; there is no alternative lookup.
							'taxonomy' => 'pizzatier_ingredient_group',
							'terms'    => $term->term_id,
						] ],
					] );
					$slugs = array_filter( array_map( function( $pid ) {
						return get_post_field( 'post_name', $pid );
					}, $posts ) );
					if ( ! empty( $slugs ) ) {
						$layer_groups->{ $term->slug } = [
							'label'    => $term->name,
							'parent'   => $term->parent ? get_term( $term->parent )->slug ?? '' : '',
							'layers'   => array_values( $slugs ),
						];
					}
				}
			}
		}

		// ── Layer type map: slug → type string ───────────────────────────
		// Queried once here so JS can resolve layerType for any layer slug
		// even when the base plugin's events don't include a type field.
		// Shape: { 'thin-crispy': 'crust', 'marinara': 'sauce', … }
		$layer_type_map = [];
		$cpt_type_map   = [
			'pizzatier_crusts'   => 'crust',
			'pizzatier_sauces'   => 'sauce',
			'pizzatier_cheeses'  => 'cheese',
			'pizzatier_toppings' => 'topping',
			'pizzatier_drizzles' => 'drizzle',
			'pizzatier_cuts'     => 'cut',
		];
		foreach ( $cpt_type_map as $cpt => $type_str ) {
			if ( ! post_type_exists( $cpt ) ) {
				continue;
			}
			$posts = get_posts( [
				'post_type'      => $cpt,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );
			foreach ( $posts as $pid ) {
				$slug = get_post_field( 'post_name', $pid );
				if ( $slug ) {
					$layer_type_map[ $slug ] = $type_str;
				}
				// Also index by sanitize_title(title) so colorbox-computed slugs resolve.
				$title_slug = sanitize_title( get_the_title( $pid ) );
				if ( $title_slug && ! isset( $layer_type_map[ $title_slug ] ) ) {
					$layer_type_map[ $title_slug ] = $type_str;
				}
			}
		}

		// ── Per-layer price grids (Phase 5) ───────────────────────────────
		// Build a map of { postId: { sizes, fractions, cells } } for every
		// layer post ID enabled on this product that has its own pricing grid.
		// Layers without a custom grid are omitted — JS falls back to priceGrid.
		// Shape: { 123: { sizes: [...], fractions: [...], cells: {...} }, … }
		$layer_grids = [];
		$enabled_raw = get_post_meta( $product_id, '_pizzatier_enabled_layers', true );
		if ( is_array( $enabled_raw ) && ! empty( $enabled_raw ) ) {
			foreach ( $enabled_raw as $layer_pid ) {
				$layer_pid = absint( $layer_pid );
				if ( $layer_pid <= 0 ) {
					continue;
				}
				$lg = $this->grid_model->get_layer_grid( $layer_pid );
				if ( null === $lg ) {
					continue; // No custom grid — JS uses product fallback.
				}
				$layer_cells_clean = [];
				foreach ( $lg['cells'] as $ck => $cv ) {
					$layer_cells_clean[ $ck ] = (float) $cv;
				}
				$layer_grids[ $layer_pid ] = [
					'sizes'     => $lg['sizes'],
					'fractions' => $lg['fractions'],
					'cells'     => $layer_cells_clean,
				];
			}
		}

		// ── Build localized config ─────────────────────────────────────────
		$config = [
			// Product context.
			'productId'      => $product_id,
			'basePrice'      => (float) $product->get_price(),

			// Pricing engine configuration.
			// Per-product mode override takes precedence over the global setting.
			'pricingMode'       => $product_mode !== '' ? $product_mode : (string) pizzatier_get_option( 'pricing_mode', 'addon_per_layer' ),
			'freeToppingsCount' => (int)    pizzatier_get_option( 'free_toppings_count', 0 ),
			'discountThreshold' => (int)    pizzatier_get_option( 'discount_threshold', 0 ),
			'discountPercent'   => (float)  pizzatier_get_option( 'discount_percent', 0 ),
			'tieredThresholds'  => array_map( 'intval', explode( ',', (string) pizzatier_get_option( 'tiered_topping_thresholds', '3,6' ) ) ),
			'nonToppingPricing' => (string) pizzatier_get_option( 'non_topping_pricing', 'grid' ),
			'fixedPrices'       => [
				'crust'   => (float) pizzatier_get_option( 'crust_fixed_price',   0 ),
				'sauce'   => (float) pizzatier_get_option( 'sauce_fixed_price',   0 ),
				'cheese'  => (float) pizzatier_get_option( 'cheese_fixed_price',  0 ),
				'drizzle' => (float) pizzatier_get_option( 'drizzle_fixed_price', 0 ),
			],
			'priceRounding'     => (string) pizzatier_get_option( 'price_rounding', '' ),
			'maxToppings'       => (int)    pizzatier_get_option( 'max_toppings', 0 ),
			'minToppings'       => (int)    pizzatier_get_option( 'min_toppings', 0 ),
			'showBreakdown'      => (bool)   pizzatier_get_option( 'price_bar_show_breakdown', true ),
			'showBaseInBreakdown'=> (bool)   pizzatier_get_option( 'price_bar_show_base', false ),
			'showSavings'        => (bool)   pizzatier_get_option( 'price_bar_show_savings', true ),
			'debugMode'          => (bool)   pizzatier_get_option( 'debug_mode', false ),
			'minToppingPrice'    => pizzatier_get_option( 'min_topping_price', '' ) !== '' ? (float) pizzatier_get_option( 'min_topping_price', 0 ) : null,
			'maxToppingPrice'    => pizzatier_get_option( 'max_topping_price', '' ) !== '' ? (float) pizzatier_get_option( 'max_topping_price', 0 ) : null,
			'discountMaxAmount'  => pizzatier_get_option( 'discount_max_amount', '' ) !== '' ? (float) pizzatier_get_option( 'discount_max_amount', 0 ) : null,
			'showPerToppingPrice'=> (bool) pizzatier_get_option( 'show_per_topping_price', false ),
			'sizeMultipliers'    => ( function() {
				$raw = pizzatier_get_option( 'size_price_multipliers', [] );
				$result = [];
				foreach ( (array) $raw as $line ) {
					$parts = explode( '=', (string) $line, 2 );
					if ( count( $parts ) === 2 ) {
						$label = trim( $parts[0] );
						$mult  = (float) trim( $parts[1] );
						if ( $label !== '' && $mult > 0 ) {
							$result[ $label ] = $mult;
						}
					}
				}
				return $result ?: (object) [];
			} )(),
			'currencySymbol' => $currency_symbol,
			'decimals'       => (int) get_option( 'woocommerce_price_num_decimals', 2 ),
			'decimalSep'     => get_option( 'woocommerce_price_decimal_sep', '.' ),
			'thousandSep'    => get_option( 'woocommerce_price_thousand_sep', ',' ),

			// Price grid data.
			'priceGrid'      => [
				'sizes'     => $sizes,
				'fractions' => $fractions,
				'cells'     => $cells_clean,
			],

			// Flat grid data (crust, cut, topping, etc — one price per size).
			'flatGrid'       => [
				'layer_types' => $flat_layer_types,
				'sizes'       => $sizes,
				'cells'       => $flat_cells_clean,
			],

			'defaultSize'    => ! empty( $sizes ) ? $sizes[0] : '',

			'preselectedLayers' => array_values( array_filter(
				array_map( 'strval', (array) get_post_meta( $product_id, '_pizzatier_preselected_layers', true ) )
			) ),

			'defaultLayers'  => ( function() use ( $product_id ) {
				$raw = get_post_meta( $product_id, '_pizzatier_default_layers', true );
				if ( ! is_array( $raw ) ) return (object) [];
				$slugs = [];
				foreach ( $raw as $type => $pid ) {
					$slug = get_post_field( 'post_name', (int) $pid );
					if ( $slug ) $slugs[ $type ] = $slug;
				}
				return $slugs ?: (object) [];
			} )(),

			'fallbackLabel'  => $fallback_label,

			// AJAX / REST.
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'cartNonce'      => wp_create_nonce( \PizzaTier\Commerce\WooCommerce\CartIntegration::AJAX_ACTION ),
			'redirectAfterAdd' => (bool) pizzatier_get_option( 'redirect_after_add_to_cart', false ),

			// Topping constraints.
			'freeToppings'     => (int)  pizzatier_get_option( 'free_toppings_count', 0 ),
			'minOrder'         => pizzatier_get_option( 'min_order', '' ) !== '' ? (float) pizzatier_get_option( 'min_order', 0 ) : null,
			'maxOrder'         => pizzatier_get_option( 'max_order', '' ) !== '' ? (float) pizzatier_get_option( 'max_order', 0 ) : null,

			// Display flags.
			'showBasePrice'    => (bool) pizzatier_get_option( 'price_bar_show_base', false ),
			'priceBarLabel'    => (string) pizzatier_get_option( 'price_bar_label', '' ),
			'priceUpdateDelay' => (int)  pizzatier_get_option( 'price_update_delay_ms', 0 ),

			// Order notes.
			'orderNotesEnabled' => (bool) pizzatier_get_option( 'enable_order_notes', false ),

			// Quantity selector (checkout bar stepper).
			'showQuantitySelector' => (bool) pizzatier_get_option( 'show_quantity_selector', true ),
			'maxQuantity'          => max( 1, (int) pizzatier_get_option( 'max_quantity', 99 ) ),

			// Nutrition.
			'nutritionEnabled'       => $nutrition_enabled,
			'nutritionDisplay'       => $nutrition_display,
			'nutritionShowCalories'  => $nutrition_show_calories,
			'nutritionShowFat'       => $nutrition_show_fat,
			'nutritionShowCarbs'     => $nutrition_show_carbs,
			'nutritionShowProtein'   => $nutrition_show_protein,
			'nutritionShowSodium'    => $nutrition_show_sodium,
			'nutritionShowAllergens' => $nutrition_show_allergens,
			'nutritionShowSummary'   => $nutrition_show_summary,
			'nutritionData'          => $nutrition_data,

			// Layer groups.
			'layerGroupsEnabled' => $groups_enabled,
			'layerGroups'        => $layer_groups,

			// Layer type map: slug → type string.
			// Lets JS resolve layerData.type for any slug without relying on
			// the base plugin's events to carry a type field.
			'layerTypeMap'       => $layer_type_map ?: (object) [],

			// Per-layer price grids (Phase 5).
			// Map of { postId: { sizes, fractions, cells } } for layers that
			// have custom pricing. JS reads this in getLayerPrice() before
			// falling back to the product-level priceGrid.
			// Keys are integers encoded as strings by json_encode —
			// JS accesses as: layerGrids[String(postId)]
			'layerGrids'         => $layer_grids ?: (object) [],

			// Slug → postId map for all CPT layers so JS can resolve the
			// postId needed for layerGrids lookup from a layer slug string.
			// Shape: { 'thin-crispy': 123, 'marinara': 456, … }
			'layerPostIdMap'     => ( function() {
				$map      = [];
				$all_cpts = [
					'pizzatier_crusts', 'pizzatier_sauces', 'pizzatier_cheeses',
					'pizzatier_toppings', 'pizzatier_drizzles', 'pizzatier_cuts',
					'pizzatier_sizes',
				];
				foreach ( $all_cpts as $cpt ) {
					if ( ! post_type_exists( $cpt ) ) {
						continue;
					}
					$cpt_posts = get_posts( [
						'post_type'      => $cpt,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					] );
					foreach ( $cpt_posts as $cpid ) {
						$slug = get_post_field( 'post_name', $cpid );
						if ( $slug ) {
							$map[ $slug ] = (int) $cpid;
						}
						// Colorbox templates compute slugs via sanitize_title(get_the_title()),
						// not from post_name. Add that as a second key so both resolve to the
						// same post ID. If they happen to be identical, the assignment is a no-op.
						$title_slug = sanitize_title( get_the_title( $cpid ) );
						if ( $title_slug && $title_slug !== $slug ) {
							$map[ $title_slug ] = (int) $cpid;
						}
					}
				}
				return $map ?: (object) [];
			} )(),

			// i18n strings.
			'i18n'           => [
				'addToCart'        => __( 'Add to Cart', 'pizzatier' ),
				'selectSizePrompt' => __( 'Select a size', 'pizzatier' ),
				'addingToCart'     => __( 'Adding…', 'pizzatier' ),
				'addedToCart'      => __( 'Added to cart!', 'pizzatier' ),
				'addToCartError'   => __( 'Something went wrong. Please try again.', 'pizzatier' ),
				'configureFirst'   => __( 'Configure your pizza above, then add to cart.', 'pizzatier' ),
				'calculating'      => __( 'Calculating…', 'pizzatier' ),
				'priceUnavailable' => __( 'Price on request', 'pizzatier' ),
				'selectSize'       => __( 'Please select a pizza size.', 'pizzatier' ),
				'selectLayers'     => __( 'Please add at least one topping or layer.', 'pizzatier' ),
				'builderNotReady'  => __( 'The pizza builder is not ready yet. Please wait a moment.', 'pizzatier' ),
				'calories'         => __( 'cal', 'pizzatier' ),
				'nutritionSummaryLabel' => __( 'Estimated nutrition', 'pizzatier' ),
			],

			// REST endpoint.
			'restUrl'        => rest_url( 'pizzatier/v1/' ),
			'restNonce'      => wp_create_nonce( 'wp_rest' ),
		];

		// ── Emit config as inline JS (supports multiple instances per page) ──
		// Each call to enqueue_assets() pushes one config object onto the
		// pizzatier_commerceFrontendInstances array. The last-pushed object is also assigned
		// to the pizzatier_commerceFrontend alias for backward-compat with any customisations
		// that reference it directly. JS reads from pizzatier_commerceFrontendInstances[i].
		$json = wp_json_encode( $config );

		wp_add_inline_script(
			'pizzatier-commerce-frontend-builder',
			'window.pizzatier_commerceFrontend = ' . $json . ';' .
			'window.pizzatier_commerceFrontendInstances = window.pizzatier_commerceFrontendInstances || [];' .
			'window.pizzatier_commerceFrontendInstances.push(' . $json . ');',
			'before'
		);
	}
}
