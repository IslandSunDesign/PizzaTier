<?php
/**
 * Registers and sanitises all PizzaTier settings.
 *
 * Sections
 * ────────
 * [general]     — Cart, redirect, builder display behaviour
 * [defaults]    — Default size/fraction labels for new grids
 * [pricing]     — Pricing engine mode + engine-specific options
 * [toppings]    — Topping limits, free-topping rules
 * [display]     — Labels, price-bar appearance, size selector style
 * [checkout]    — Cart item names, order notes, min/max order, notifications
 * [advanced]    — Tax override, REST API, quantity, reorder, debug
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_NAME  = 'pizzatier_options';
	const PAGE_SLUG    = 'pizzatier-commerce';
	const OPTION_GROUP = 'pizzatier_options_group';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => $this->defaults(),
			]
		);

		$this->register_section_general();
		// Note: Grid Defaults (default sizes / fractions) and Pricing Engine
		// have moved to the dedicated Pricing page (PricingPage class).
		// They still write to the same pizzatier_options option so
		// pizzatier_get_option() lookups across the plugin are unchanged —
		// only their UI section registration moved off of this page.
		$this->register_section_toppings();
		$this->register_section_display();
		$this->register_section_checkout_bar_layout();
		$this->register_section_checkout();
		$this->register_section_nutrition();
		$this->register_section_email_summary();
		$this->register_section_layer_groups();
		$this->register_section_advanced();

		/**
		 * Fires after all built-in PizzaTier settings sections are registered.
		 *
		 * Third-party plugins can add their own sections and fields to the
		 * PizzaTier settings page by hooking here and calling
		 * add_settings_section() / add_settings_field() targeting Settings::PAGE_SLUG.
		 *
		 * Note: sanitisation for any extra fields must be handled separately via
		 * a filter on the 'sanitize_option_' . Settings::OPTION_NAME hook, or by
		 * storing in a separate option key entirely.
		 *
		 * @param Settings $settings  This instance — exposes PAGE_SLUG and OPTION_NAME as public constants.
		 */
		do_action( 'pizzatier_commerce_register_settings_sections', $this );
	}

	// ─── Section: General ────────────────────────────────────────────────────

	private function register_section_general(): void {
		add_settings_section( 'pizzatier_commerce_section_general', __( 'General', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'redirect_after_add_to_cart', __( 'Redirect to cart after adding', 'pizzatier' ),
			  __( 'Immediately redirect to the cart page after a pizza is added. Overrides WooCommerce\'s global redirect setting for pizza products only.', 'pizzatier' ), 'checkbox' ],
			[ 'show_size_selector', __( 'Show size selector', 'pizzatier' ),
			  __( 'Display the size selector above the builder on the product page.', 'pizzatier' ), 'checkbox' ],
			[ 'show_price_bar', __( 'Show live price bar', 'pizzatier' ),
			  __( 'Show the running total below the builder as the customer makes selections.', 'pizzatier' ), 'checkbox' ],
			[ 'show_cart_btn', __( 'Show Add to Cart inside builder', 'pizzatier' ),
			  __( 'Render an Add to Cart button inside the PizzaTier canvas.', 'pizzatier' ), 'checkbox' ],
			[ 'cart_btn_text', __( 'Add to Cart button text', 'pizzatier' ),
			  __( 'Custom label for the in-builder Add to Cart button. Leave blank for WooCommerce default.', 'pizzatier' ), 'text',
			  __( 'Add to Cart', 'pizzatier' ) ],
			[ 'require_crust', __( 'Require crust selection', 'pizzatier' ),
			  __( 'Block the cart button until the customer selects a crust.', 'pizzatier' ), 'checkbox' ],
			[ 'require_sauce', __( 'Require sauce selection', 'pizzatier' ),
			  __( 'Block the cart button until the customer selects a sauce.', 'pizzatier' ), 'checkbox' ],
			[ 'require_size', __( 'Require size selection', 'pizzatier' ),
			  __( 'Block the cart button until the customer explicitly picks a size.', 'pizzatier' ), 'checkbox' ],
			[ 'builder_position_default', __( 'Default builder position', 'pizzatier' ),
			  __( 'Where the builder is injected on pizza product pages. Each product can override this on its Pizza Configurator tab.', 'pizzatier' ), 'select', 'before_cart', [
				'before_cart'   => __( 'Above add-to-cart form', 'pizzatier' ),
				'after_title'   => __( 'After product title', 'pizzatier' ),
				'after_summary' => __( 'After product summary', 'pizzatier' ),
			] ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_general' );
		}
	}

	// ─── Section: Defaults ───────────────────────────────────────────────────

	private function register_section_defaults(): void {
		add_settings_section( 'pizzatier_commerce_section_defaults', __( 'Grid Defaults', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$this->add_field( [ 'default_sizes', __( 'Default sizes', 'pizzatier' ),
			__( 'One size label per line. Used as row headers in every new price grid. Example: Small, Medium, Large, XL.', 'pizzatier' ),
			'tag_list', "Small\nMedium\nLarge\nXL" ], 'pizzatier_commerce_section_defaults' );

		$this->add_field( [ 'default_fractions', __( 'Default coverage fractions', 'pizzatier' ),
			__( 'One fraction label per line. Used as column headers in every new price grid. Example: Whole, Half, Quarter.', 'pizzatier' ),
			'tag_list', "Whole\nHalf\nQuarter" ], 'pizzatier_commerce_section_defaults' );
	}

	// ─── Section: Pricing ────────────────────────────────────────────────────

	private function register_section_pricing(): void {
		add_settings_section( 'pizzatier_commerce_section_pricing', __( 'Pricing Engine', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		// Pricing mode — rendered as rich card selector
		add_settings_field(
			'pricing_mode',
			__( 'Global pricing mode', 'pizzatier' ),
			[ $this, 'field_pricing_mode' ],
			self::PAGE_SLUG,
			'pizzatier_commerce_section_pricing'
		);

		// Example calculator widget
		add_settings_field(
			'pricing_example_calculator',
			__( 'Example calculator', 'pizzatier' ),
			[ $this, 'field_pricing_calculator' ],
			self::PAGE_SLUG,
			'pizzatier_commerce_section_pricing'
		);

		$fields = [
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
				'grid'   => __( 'Same grid as toppings (Size x Coverage cell)', 'pizzatier' ),
				'fixed'  => __( 'Fixed add-on amount per layer type', 'pizzatier' ),
				'free'   => __( 'Always free (no charge)', 'pizzatier' ),
			] ],
			[ 'crust_fixed_price',   __( 'Crust fixed add-on',   'pizzatier' ), __( 'Flat price added when a crust is selected (fixed add-on mode only).', 'pizzatier' ),   'text', '0.00' ],
			[ 'sauce_fixed_price',   __( 'Sauce fixed add-on',   'pizzatier' ), __( 'Flat price for sauce (fixed add-on mode only).', 'pizzatier' ),   'text', '0.00' ],
			[ 'cheese_fixed_price',  __( 'Cheese fixed add-on',  'pizzatier' ), __( 'Flat price for cheese (fixed add-on mode only).', 'pizzatier' ),  'text', '0.00' ],
			[ 'drizzle_fixed_price', __( 'Drizzle fixed add-on', 'pizzatier' ), __( 'Flat price for drizzle (fixed add-on mode only).', 'pizzatier' ), 'text', '0.00' ],
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

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_pricing' );
		}
	}

	// ─── Section: Toppings ───────────────────────────────────────────────────

	private function register_section_toppings(): void {
		add_settings_section( 'pizzatier_commerce_section_toppings', __( 'Topping Rules', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'max_toppings', __( 'Maximum toppings per pizza', 'pizzatier' ),
			  __( 'Global cap on topping layers. Set 0 for unlimited.', 'pizzatier' ), 'text', '0' ],
			[ 'min_toppings', __( 'Minimum toppings required', 'pizzatier' ),
			  __( 'Require at least this many toppings before the cart button activates. Set 0 to disable.', 'pizzatier' ), 'text', '0' ],
			[ 'max_same_topping', __( 'Max of same topping', 'pizzatier' ),
			  __( 'Prevent selecting the same topping more than this many times. Set 0 for unlimited.', 'pizzatier' ), 'text', '0' ],
			[ 'allow_half_toppings', __( 'Allow half/quarter topping coverage', 'pizzatier' ),
			  __( 'Show coverage fraction options (Whole / Half / Quarter) for each topping on the frontend.', 'pizzatier' ), 'checkbox' ],
			[ 'default_topping_fraction', __( 'Default topping coverage', 'pizzatier' ),
			  __( 'Coverage fraction pre-selected when a topping is added. Must match a label in your price grid.', 'pizzatier' ), 'text', 'Whole' ],
			[ 'topping_count_label', __( 'Topping counter label', 'pizzatier' ),
			  __( 'Label shown next to the topping count in the price bar. Leave blank to hide.', 'pizzatier' ), 'text', __( 'toppings', 'pizzatier' ) ],
			[ 'topping_extra_charge_msg', __( 'Extra topping charge notice', 'pizzatier' ),
			  __( 'Message shown once the free topping count is exceeded. Use {n} for the number of paid toppings. E.g. "+{n} toppings charged". Leave blank to hide.', 'pizzatier' ), 'text', '' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_toppings' );
		}
	}

	// ─── Section: Display ────────────────────────────────────────────────────

	private function register_section_display(): void {
		add_settings_section( 'pizzatier_commerce_section_display', __( 'Display & Labels', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'size_selector_label', __( 'Size selector heading', 'pizzatier' ),
			  __( 'Heading shown above the size options on the product page.', 'pizzatier' ), 'text', __( 'Choose your size', 'pizzatier' ) ],
			[ 'size_selector_style', __( 'Size selector style', 'pizzatier' ),
			  __( 'Visual style of the size selector.', 'pizzatier' ), 'select', 'pills', [
				'pills'    => __( 'Pill buttons (default)', 'pizzatier' ),
				'cards'    => __( 'Cards with price preview', 'pizzatier' ),
				'dropdown' => __( 'Dropdown select', 'pizzatier' ),
			] ],
			[ 'price_bar_label', __( 'Price bar label', 'pizzatier' ),
			  __( 'Label to the left of the running total in the price bar.', 'pizzatier' ), 'text', __( 'Your pizza total:', 'pizzatier' ) ],
			[ 'price_bar_show_breakdown', __( 'Show layer breakdown in price bar', 'pizzatier' ),
			  __( 'Display a per-layer price breakdown list below the total.', 'pizzatier' ), 'checkbox' ],
			[ 'price_bar_show_base', __( 'Show base price row in breakdown', 'pizzatier' ),
			  __( 'Include a "Base pizza" line showing the WooCommerce product price.', 'pizzatier' ), 'checkbox' ],
			[ 'price_bar_show_savings', __( 'Show savings when discount applied', 'pizzatier' ),
			  __( 'Display the savings amount in the price bar when a bulk discount is active.', 'pizzatier' ), 'checkbox' ],
			[ 'price_bar_position', __( 'Price bar position', 'pizzatier' ),
			  __( 'Where the live price bar appears relative to the builder.', 'pizzatier' ), 'select', 'below_builder', [
				'below_builder' => __( 'Below builder (default)', 'pizzatier' ),
				'above_builder' => __( 'Above builder', 'pizzatier' ),
				'sticky_bottom' => __( 'Sticky bottom bar', 'pizzatier' ),
			] ],
			[ 'hide_wc_price', __( 'Replace WC product price with live price', 'pizzatier' ),
			  __( 'Replace the WooCommerce product price with the live PizzaTier element on single product pages.', 'pizzatier' ), 'checkbox' ],
			[ 'add_to_cart_success_msg', __( 'Add to cart success message', 'pizzatier' ),
			  __( 'Message shown after a pizza is added. Use {product} for product name, {size} for size. Leave blank for default.', 'pizzatier' ), 'text', '' ],
			[ 'out_of_stock_msg', __( 'Custom out-of-stock message', 'pizzatier' ),
			  __( 'Override the WooCommerce out-of-stock message for pizza products. Leave blank for WC default.', 'pizzatier' ), 'text', '' ],
			[ 'builder_loading_text', __( 'Builder loading text', 'pizzatier' ),
			  __( 'Text shown while the builder is loading. Leave blank to use default.', 'pizzatier' ), 'text', '' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_display' );
		}
	}

	// ─── Section: Cart & Checkout ────────────────────────────────────────────

	// ─── Section: Checkout Bar Layout ────────────────────────────────────────
	//
	// Global "Order Now" bar layout selection. The available options come
	// straight from LayoutRegistry so adding a layout only requires touching
	// one file. Defaults to LEGACY_SLUG so existing installs keep their
	// per-template bar on upgrade with no visible change.

	private function register_section_checkout_bar_layout(): void {
		add_settings_section(
			'pizzatier_commerce_section_checkout_bar_layout',
			__( '"Order Now" Bar Layout', 'pizzatier' ),
			function() {
				echo '<p class="description">'
					. esc_html__( 'Choose what appears in the builder\'s action-bar area, and the markup and structure of the Add-to-Cart bar. Colours are still controlled by the active PizzaTier template, so the same layout adapts to each template\'s palette.', 'pizzatier' )
					. '</p>';
			},
			self::PAGE_SLUG
		);

		// The action-bar choice moved to the Orders screen in 2.0.0. It decides
		// whether customers get a cart or a recorded order, which is an ordering
		// decision rather than a pricing one, and it belongs next to the rest of
		// the ordering settings. The value is still stored here — see
		// PizzaTier\Orders\ActionBarMode.

		if ( ! class_exists( \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::class ) ) {
			return; // Registry unavailable; nothing further to pick.
		}

		$this->add_field(
			[
				\PizzaTier\Commerce\CheckoutBar\LayoutRegistry::SETTING_KEY,
				__( 'Bar layout', 'pizzatier' ),
				__( 'Click a card to pick a layout. The sketch shows the general shape; colours come from whichever PizzaTier template is active on the front end.', 'pizzatier' ),
				'layout_picker',
				\PizzaTier\Commerce\CheckoutBar\LayoutRegistry::LEGACY_SLUG,
			],
			'pizzatier_commerce_section_checkout_bar_layout'
		);
	}

	// ─── Section: Cart & Checkout ────────────────────────────────────────────

	private function register_section_checkout(): void {
		add_settings_section( 'pizzatier_commerce_section_checkout', __( 'Cart & Checkout', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'cart_item_name_template', __( 'Cart item name template', 'pizzatier' ),
			  __( 'Customise the product name in cart/order. Tokens: {product}, {size}, {topping_count}, {crust}, {sauce}. E.g. "{product} — {size}". Leave blank for default product name.', 'pizzatier' ), 'text', '' ],
			[ 'order_note_template', __( 'Order note template', 'pizzatier' ),
			  __( 'Auto-add a note to each pizza order. Tokens: {size}, {toppings}, {crust}, {sauce}, {cheese}, {total}. Leave blank to disable.', 'pizzatier' ), 'text', '' ],
			[ 'min_order', __( 'Minimum order amount', 'pizzatier' ),
			  __( 'Minimum calculated pizza price before the cart button activates. Leave blank to disable.', 'pizzatier' ), 'text', '' ],
			[ 'max_order', __( 'Maximum order amount', 'pizzatier' ),
			  __( 'Maximum allowed pizza price. Customer sees an error if exceeded. Leave blank to disable.', 'pizzatier' ), 'text', '' ],
			[ 'min_order_msg', __( 'Below minimum message', 'pizzatier' ),
			  __( 'Message shown when the order falls below the minimum. Use {min} for the formatted minimum amount.', 'pizzatier' ), 'text', '' ],
			[ 'max_order_msg', __( 'Exceeds maximum message', 'pizzatier' ),
			  __( 'Message shown when the order exceeds the maximum. Use {max} for the formatted maximum amount.', 'pizzatier' ), 'text', '' ],
			[ 'show_price_in_cart', __( 'Show pizza configuration in cart', 'pizzatier' ),
			  __( 'Display the pizza\'s size and layer breakdown in the WooCommerce cart and checkout.', 'pizzatier' ), 'checkbox' ],
			[ 'show_price_in_order', __( 'Show configuration in order emails', 'pizzatier' ),
			  __( 'Include the pizza size and layer details in WooCommerce order confirmation emails.', 'pizzatier' ), 'checkbox' ],
			[ 'cart_thumbnail_style', __( 'Cart item thumbnail', 'pizzatier' ),
			  __( 'How the pizza product image appears in the cart.', 'pizzatier' ), 'select', 'default', [
				'default' => __( 'WooCommerce default product image', 'pizzatier' ),
				'none'    => __( 'No thumbnail', 'pizzatier' ),
			] ],
			[ 'allow_reorder', __( 'Enable "Order again" re-customisation', 'pizzatier' ),
			  __( 'When a customer clicks "Order Again", pre-fill the builder with their previous configuration.', 'pizzatier' ), 'checkbox' ],
			[ 'allow_cart_edit', __( 'Allow editing from cart', 'pizzatier' ),
			  __( 'Show an "Edit" link on cart items that returns the customer to the builder with their configuration loaded.', 'pizzatier' ), 'checkbox' ],
			[ 'guest_checkout', __( 'Allow guest pizza orders', 'pizzatier' ),
			  __( 'Permit non-logged-in users to add pizza products to cart and checkout. Disable if you want to require login for pizza purchases.', 'pizzatier' ), 'checkbox' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_checkout' );
		}
	}

	// ─── Section: Nutrition ──────────────────────────────────────────────────

	private function register_section_nutrition(): void {
		add_settings_section( 'pizzatier_commerce_section_nutrition', __( 'Nutrition & Calories', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'nutrition_enabled', __( 'Enable nutritional info display', 'pizzatier' ),
			  __( 'Show nutritional data (calories, macros, allergens) for individual ingredients and/or as a pizza total. Data is entered per-ingredient in each item\'s edit screen.', 'pizzatier' ), 'checkbox' ],
			[ 'nutrition_display', __( 'Display location', 'pizzatier' ),
			  __( 'Where to show nutrition info when an ingredient is selected.', 'pizzatier' ), 'select', 'tooltip', [
				'tooltip' => __( 'Tooltip on hover/tap over ingredient name in breakdown', 'pizzatier' ),
				'inline'  => __( 'Inline — shown directly in the price breakdown list', 'pizzatier' ),
				'panel'   => __( 'Panel — below the builder (requires theme integration)', 'pizzatier' ),
			] ],
			[ 'nutrition_show_summary', __( 'Show running nutrition totals', 'pizzatier' ),
			  __( 'Display a running nutritional total (e.g. total calories) below the builder, updating as the customer builds their pizza.', 'pizzatier' ), 'checkbox' ],
			[ 'nutrition_show_calories', __( 'Show calories', 'pizzatier' ),
			  __( 'Include calorie count in nutrition display. This is the most commonly shown field.', 'pizzatier' ), 'checkbox' ],
			[ 'nutrition_show_fat', __( 'Show fat (g)', 'pizzatier' ),
			  '', 'checkbox' ],
			[ 'nutrition_show_carbs', __( 'Show carbohydrates (g)', 'pizzatier' ),
			  '', 'checkbox' ],
			[ 'nutrition_show_protein', __( 'Show protein (g)', 'pizzatier' ),
			  '', 'checkbox' ],
			[ 'nutrition_show_sodium', __( 'Show sodium (mg)', 'pizzatier' ),
			  '', 'checkbox' ],
			[ 'nutrition_show_allergens', __( 'Show allergens', 'pizzatier' ),
			  __( 'Display allergen text entered on each ingredient.', 'pizzatier' ), 'checkbox' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_nutrition' );
		}
	}

	// ─── Section: Order Email Summary ────────────────────────────────────────

	private function register_section_email_summary(): void {
		add_settings_section( 'pizzatier_commerce_section_email_summary', __( 'Order Email Summary', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'enable_order_notes', __( 'Enable per-pizza order notes', 'pizzatier' ),
			  __( 'Show a free-text note field inside the builder so customers can add special instructions for each pizza (e.g. "extra crispy, no garlic"). Stored and displayed in cart, order admin, and emails.', 'pizzatier' ), 'checkbox' ],
			[ 'order_note_label', __( 'Note field label', 'pizzatier' ),
			  __( 'Label shown above the note textarea. Leave blank for default.', 'pizzatier' ), 'text', '' ],
			[ 'order_note_placeholder', __( 'Note field placeholder', 'pizzatier' ),
			  __( 'Placeholder text inside the textarea. Leave blank for default.', 'pizzatier' ), 'text', '' ],
			[ 'show_quantity_selector', __( 'Show quantity stepper in checkout bar', 'pizzatier' ),
			  __( 'Display +/− quantity buttons inside the template checkout bar so customers can order multiple of the same custom pizza.', 'pizzatier' ), 'checkbox', true ],
			[ 'max_quantity', __( 'Maximum quantity per pizza', 'pizzatier' ),
			  __( 'The highest quantity a customer can order for a single custom pizza. Default: 99.', 'pizzatier' ), 'number', 99 ],
			[ 'email_append_to_order', __( 'Append pizza summary to order confirmation', 'pizzatier' ),
			  __( 'Add a formatted pizza configuration table inside the standard WooCommerce order confirmation email sent to the customer.', 'pizzatier' ), 'checkbox' ],
			[ 'email_send_separate', __( 'Send a separate pizza summary email', 'pizzatier' ),
			  __( 'Send a dedicated pizza configuration summary email when the order status becomes "processing".', 'pizzatier' ), 'checkbox' ],
			[ 'email_separate_to_customer', __( 'Send separate email to customer', 'pizzatier' ),
			  __( 'Include the customer\'s billing email as a recipient of the separate pizza summary email.', 'pizzatier' ), 'checkbox' ],
			[ 'email_separate_to_admin', __( 'Send separate email to admin', 'pizzatier' ),
			  __( 'Also send the separate pizza summary email to the site admin email address.', 'pizzatier' ), 'checkbox' ],
			[ 'email_separate_subject', __( 'Separate email subject', 'pizzatier' ),
			  __( 'Subject line for the separate pizza summary email. Tokens: {order_number}, {site_name}. Leave blank for default.', 'pizzatier' ), 'text', '' ],
			[ 'email_summary_heading', __( 'Summary block heading', 'pizzatier' ),
			  __( 'Heading text shown above the pizza configuration table in emails. Leave blank for default.', 'pizzatier' ), 'text', '' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_email_summary' );
		}
	}

	// ─── Section: Layer Groups ────────────────────────────────────────────────

	private function register_section_layer_groups(): void {
		add_settings_section( 'pizzatier_commerce_section_layer_groups', __( 'Ingredient Groups', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'layer_groups_enabled', __( 'Enable ingredient grouping', 'pizzatier' ),
			  __( 'Assign ingredients (toppings, crusts, sauces, cheeses, drizzles) to hierarchical groups using the "Ingredient Group" taxonomy added to each item\'s edit screen. Groups are passed to the frontend builder so templates can render grouped menus. The base plugin\'s "Enable category grouping" setting is also respected.', 'pizzatier' ), 'checkbox' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_layer_groups' );
		}

		// Info note about how to assign groups.
		add_settings_field(
			'pizzatier_commerce_layer_groups_info',
			__( 'How to set up groups', 'pizzatier' ),
			function() {
				echo '<p class="description">'
					. esc_html__( 'Edit any topping, crust, sauce, cheese, or drizzle and use the "Ingredient Group" meta box to assign it to a group. Groups are hierarchical — create parent groups (e.g. "Meat", "Vegetable") and optionally sub-groups.', 'pizzatier' )
					. ' <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=pizzatier_ingredient_group&post_type=pizzatier_toppings' ) ) . '" target="_blank">'
					. esc_html__( 'Manage groups →', 'pizzatier' )
					. '</a></p>';
			},
			self::PAGE_SLUG,
			'pizzatier_commerce_section_layer_groups'
		);
	}

	// ─── Section: Advanced ───────────────────────────────────────────────────

	private function register_section_advanced(): void {
		add_settings_section( 'pizzatier_commerce_section_advanced', __( 'Advanced', 'pizzatier' ), '__return_false', self::PAGE_SLUG );

		$fields = [
			[ 'tax_display', __( 'Tax display on product page', 'pizzatier' ),
			  __( 'How taxes appear in the live price bar.', 'pizzatier' ), 'select', '', [
				''     => __( 'Inherit from WooCommerce', 'pizzatier' ),
				'incl' => __( 'Including tax', 'pizzatier' ),
				'excl' => __( 'Excluding tax', 'pizzatier' ),
			] ],
			[ 'tax_class_override', __( 'Tax class override', 'pizzatier' ),
			  __( 'Force a specific WooCommerce tax class on all pizza products. Leave blank to use each product\'s own class.', 'pizzatier' ), 'text', '' ],
			[ 'enable_rest_price', __( 'Enable REST price endpoint', 'pizzatier' ),
			  __( 'Expose /wp-json/pizzatier/v1/calculate-price for server-side price verification. Disable only for custom checkout flows.', 'pizzatier' ), 'checkbox' ],
			[ 'disable_quantity', __( 'Disable quantity selector for pizza products', 'pizzatier' ),
			  __( 'Force qty=1 — each customisation is its own cart line item.', 'pizzatier' ), 'checkbox' ],
			[ 'cache_price_grid', __( 'Cache price grid data', 'pizzatier' ),
			  __( 'Cache price grid lookups in a transient to reduce database queries on high-traffic stores. Cache is cleared when any grid is saved.', 'pizzatier' ), 'checkbox' ],
			[ 'schema_markup', __( 'Output product schema markup', 'pizzatier' ),
			  __( 'Add JSON-LD schema markup for pizza product pages to help with rich search results.', 'pizzatier' ), 'checkbox' ],
			[ 'price_update_delay_ms', __( 'Live price update delay (ms)', 'pizzatier' ),
			  __( 'Milliseconds to debounce live price recalculation after a layer change. Default 0 = instant. Increase on slow devices.', 'pizzatier' ), 'text', '0' ],
			[ 'debug_mode', __( 'Debug mode', 'pizzatier' ),
			  __( 'Log price calculation details to the browser console. Disable in production.', 'pizzatier' ), 'checkbox' ],
		];

		foreach ( $fields as $f ) {
			$this->add_field( $f, 'pizzatier_commerce_section_advanced' );
		}
	}

	// -------------------------------------------------------------------------
	// Field helper
	// -------------------------------------------------------------------------

	/** @param array $f  [key, label, desc, type, placeholder?, options?] */
	private function add_field( array $f, string $section, string $show_for = '', string $nt_show = '' ): void {
		[ $key, $label, $desc, $type ] = $f;
		$ph      = $f[4] ?? '';
		$options = $f[5] ?? [];

		$cb_map = [
			'tag_list'     => 'field_tag_list',
			'textarea_wide'=> 'field_textarea_wide',
		];
		$cb = isset( $cb_map[ $type ] ) ? $cb_map[ $type ] : 'field_' . $type;

		add_settings_field(
			$key, $label,
			[ $this, $cb ],
			self::PAGE_SLUG, $section,
			[
				'key'         => $key,
				'description' => $desc,
				'placeholder' => $ph,
				'options'     => $options,
				'show_for'    => $show_for,  // space-sep list of mode keys this field is relevant to
				'nt_show'     => $nt_show,   // show only when non_topping_pricing=fixed
			]
		);
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function field_checkbox( array $args ): void {
		$key   = $args['key'];
		$desc  = $args['description'] ?? '';
		$value = (bool) pizzatier_get_option( $key, false );
		$name  = self::OPTION_NAME . '[' . $key . ']';
		$id    = 'pizzatier_commerce_field_' . $key;
		?>
		<label for="<?php echo esc_attr( $id ); ?>" class="pztc-toggle-label">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				   value="1" <?php checked( $value, true ); ?> class="pztc-toggle-input">
			<span class="pztc-toggle-track" aria-hidden="true"></span>
		</label>
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	public function field_text( array $args ): void {
		$key   = $args['key'];
		$desc  = $args['description'] ?? '';
		$ph    = $args['placeholder']  ?? '';
		$value = (string) pizzatier_get_option( $key, '' );
		$name  = self::OPTION_NAME . '[' . $key . ']';
		$id    = 'pizzatier_commerce_field_' . $key;
		?>
		<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
			   value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>"
			   class="regular-text pztc-text-input">
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	public function field_number( array $args ): void {
		$key   = $args['key'];
		$desc  = $args['description'] ?? '';
		$ph    = $args['placeholder']  ?? '';
		$value = pizzatier_get_option( $key, $ph );
		$name  = self::OPTION_NAME . '[' . $key . ']';
		$id    = 'pizzatier_commerce_field_' . $key;
		?>
		<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
			   value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>"
			   min="1" step="1" class="small-text pztc-text-input" style="width:90px !important;">
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	public function field_tag_list( array $args ): void {
		$key   = $args['key'];
		$desc  = $args['description'] ?? '';
		$ph    = $args['placeholder']  ?? '';
		$value = pizzatier_get_option( $key, [] );
		$text  = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
		$name  = self::OPTION_NAME . '[' . $key . ']';
		$id    = 'pizzatier_commerce_field_' . $key;
		?>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				  rows="5" cols="30" placeholder="<?php echo esc_attr( $ph ); ?>"
				  class="pztc-textarea-input"><?php echo esc_textarea( $text ); ?></textarea>
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	public function field_select( array $args ): void {
		$key     = $args['key'];
		$desc    = $args['description'] ?? '';
		$options = $args['options']      ?? [];
		$value   = (string) pizzatier_get_option( $key, '' );
		$name    = self::OPTION_NAME . '[' . $key . ']';
		$id      = 'pizzatier_commerce_field_' . $key;
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="pztc-select-input">
			<?php foreach ( $options as $ov => $ol ) : ?>
				<option value="<?php echo esc_attr( $ov ); ?>" <?php selected( $value, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	/**
	 * Layout picker: a grid of cards, each with an SVG sketch + label + description.
	 *
	 * Backed by hidden radio inputs so it submits through the normal Settings
	 * API without any JS on save. A tiny bit of click-handler JS on the label
	 * element toggles the selected-visual state for nicer feedback.
	 */
	public function field_layout_picker( array $args ): void {
		$key     = $args['key'];
		$desc    = $args['description'] ?? '';
		$current = (string) pizzatier_get_option( $key, '' );
		$name    = self::OPTION_NAME . '[' . $key . ']';

		$layouts = class_exists( \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::class )
			? \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::get_layouts()
			: [];

		if ( empty( $layouts ) ) {
			esc_html_e( 'Layout registry unavailable.', 'pizzatier' );
			return;
		}

		// Fall back to the default slug when the stored value is blank/unknown.
		if ( '' === $current || ! isset( $layouts[ $current ] ) ) {
			$current = \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::LEGACY_SLUG;
		}
		?>
		<fieldset class="pztc-layout-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Checkout bar layout', 'pizzatier' ); ?>">
			<?php foreach ( $layouts as $slug => $def ) :
				$is_selected = ( $slug === $current );
				$input_id    = 'pizzatier_commerce_layout_' . sanitize_html_class( $slug );
				$label       = (string) ( $def['label']       ?? $slug );
				$description = (string) ( $def['description'] ?? '' );
				$preview     = (string) ( $def['preview']     ?? '' );
				?>
				<label class="pztc-layout-card<?php echo $is_selected ? ' pztc-layout-card--selected' : ''; ?>"
				       for="<?php echo esc_attr( $input_id ); ?>">
					<input type="radio"
					       id="<?php echo esc_attr( $input_id ); ?>"
					       name="<?php echo esc_attr( $name ); ?>"
					       value="<?php echo esc_attr( $slug ); ?>"
					       class="pztc-layout-card__input"
					       <?php checked( $is_selected ); ?> />

					<span class="pztc-layout-card__preview" aria-hidden="true">
						<?php
						// Preview SVGs come from the layout registry — curated
						// internal markup, not user input. Still passed through
						// wp_kses() so there is one escaping call at the output
						// boundary that a reviewer can verify.
						echo wp_kses( $preview, pzt_card_allowed_html() );
						?>
					</span>

					<span class="pztc-layout-card__body">
						<span class="pztc-layout-card__label">
							<?php echo esc_html( $label ); ?>
							<span class="pztc-layout-card__check dashicons dashicons-yes" aria-hidden="true"></span>
						</span>
						<?php if ( $description ) : ?>
							<span class="pztc-layout-card__desc"><?php echo esc_html( $description ); ?></span>
						<?php endif; ?>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php if ( $desc ) : ?>
			<p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<script>
		// Minimal click-state sync — keeps the selected card outlined as the
		// user clicks without requiring a full page reload. The radio itself
		// is what actually submits the value.
		( function () {
			var group = document.currentScript.previousElementSibling;
			while ( group && ! group.classList.contains( 'pztc-layout-picker' ) ) {
				group = group.previousElementSibling;
			}
			if ( ! group ) { return; }
			group.addEventListener( 'change', function ( e ) {
				if ( ! e.target || 'radio' !== e.target.type ) { return; }
				var cards = group.querySelectorAll( '.pztc-layout-card' );
				for ( var i = 0; i < cards.length; i++ ) {
					var chk = cards[ i ].querySelector( 'input[type="radio"]' );
					cards[ i ].classList.toggle( 'pztc-layout-card--selected', !! ( chk && chk.checked ) );
				}
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * Wide textarea (for multi-line settings like size_price_multipliers).
	 */
	public function field_textarea_wide( array $args ): void {
		$key   = $args['key'];
		$desc  = $args['description'] ?? '';
		$ph    = $args['placeholder']  ?? '';
		$value = pizzatier_get_option( $key, [] );
		// May be stored as array of lines
		if ( is_array( $value ) ) {
			$text = implode( "\n", $value );
		} else {
			$text = (string) $value;
		}
		$name = self::OPTION_NAME . '[' . $key . ']';
		$id   = 'pizzatier_commerce_field_' . $key;
		?>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"
				  rows="5" cols="40" placeholder="<?php echo esc_attr( $ph ); ?>"
				  class="pztc-textarea-input" style="width:320px;"><?php echo esc_textarea( $text ); ?></textarea>
		<?php if ( $desc ) : ?><p class="description pztc-field-desc"><?php echo esc_html( $desc ); ?></p><?php endif;
	}

	/**
	 * Pricing Engine — example calculator widget.
	 * Lets admins test the configured engine with hypothetical inputs, live.
	 */
	public function field_pricing_calculator(): void {
		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		?>
		<div class="pztc-calc" id="pztc-example-calc">
			<div class="pztc-calc__row">
				<label class="pztc-calc__label"><?php esc_html_e( 'Base price', 'pizzatier' ); ?></label>
				<div class="pztc-calc__input-wrap">
					<span class="pztc-calc__sym"><?php echo esc_html( $currency ); ?></span>
					<input type="number" id="pztc-calc-base" min="0" step="0.01" value="10.00" class="pztc-calc__num">
				</div>
			</div>
			<div class="pztc-calc__row">
				<label class="pztc-calc__label"><?php esc_html_e( 'Grid cell (per topping/layer)', 'pizzatier' ); ?></label>
				<div class="pztc-calc__input-wrap">
					<span class="pztc-calc__sym"><?php echo esc_html( $currency ); ?></span>
					<input type="number" id="pztc-calc-cell" min="0" step="0.01" value="1.50" class="pztc-calc__num">
				</div>
			</div>
			<div class="pztc-calc__row">
				<label class="pztc-calc__label"><?php esc_html_e( 'Number of paid toppings', 'pizzatier' ); ?></label>
				<input type="number" id="pztc-calc-count" min="0" step="1" value="3" class="pztc-calc__num" style="width:70px;">
			</div>
			<div class="pztc-calc__result" id="pztc-calc-result">
				<span class="pztc-calc__result-label"><?php esc_html_e( 'Estimated total', 'pizzatier' ); ?></span>
				<span class="pztc-calc__result-value" id="pztc-calc-total"><?php echo esc_html( $currency ); ?>—</span>
			</div>
			<p class="pztc-calc__note"><?php esc_html_e( 'Uses the currently saved settings. Save first, then this calculator reflects your actual engine configuration.', 'pizzatier' ); ?></p>
		</div>

		<style>
		.pztc-calc {
			background: #f8f9fa; border: 1px solid #e0e3e7;
			border-radius: 10px; padding: 16px 20px; max-width: 480px;
			display: flex; flex-direction: column; gap: 10px;
		}
		.pztc-calc__row { display: flex; align-items: center; gap: 12px; }
		.pztc-calc__label { font-size: 12px; color: #646970; width: 200px; flex-shrink: 0; }
		.pztc-calc__input-wrap { display: flex; align-items: center; gap: 4px; }
		.pztc-calc__sym { font-size: 13px; color: #1d2023; font-weight: 600; }
		.pztc-calc__num {
			width: 90px; padding: 5px 8px;
			border: 1px solid #c3c4c7; border-radius: 4px;
			font-size: 13px; text-align: right;
		}
		.pztc-calc__num:focus { border-color: #ff6b35; outline: none; box-shadow: 0 0 0 2px rgba(255,107,53,.15); }
		.pztc-calc__result {
			display: flex; align-items: center; justify-content: space-between;
			background: #1a1e23; border-radius: 8px; padding: 12px 16px;
			margin-top: 4px;
		}
		.pztc-calc__result-label { font-size: 12px; color: rgba(255,255,255,.65); }
		.pztc-calc__result-value { font-size: 22px; font-weight: 700; color: #ff6b35; font-variant-numeric: tabular-nums; }
		.pztc-calc__note { font-size: 11px; color: #9ca3af; margin: 0; }
		</style>

		<?php // JS enqueued via wp_enqueue_script — see enqueue_assets() ?>
		<?php
	}

	/**
	 * Pricing mode — rich card-grid selector.
	 */
	public function field_pricing_mode(): void {
		$current = (string) pizzatier_get_option( 'pricing_mode', 'addon_per_layer' );
		$name    = self::OPTION_NAME . '[pricing_mode]';

		$modes = [
			'addon_per_layer' => [
				'icon'  => '🧱',
				'title' => __( 'Add-on per layer', 'pizzatier' ),
				'desc'  => __( 'Each selected layer adds its grid price (Size × Coverage) to the base pizza price. The most flexible and common mode.', 'pizzatier' ),
				'tag'   => __( 'Default', 'pizzatier' ),
				'color' => '#ff6b35',
			],
			'flat_per_size' => [
				'icon'  => '📐',
				'title' => __( 'Flat price per size', 'pizzatier' ),
				'desc'  => __( 'The grid\'s "Whole" column for the chosen size is a single flat add-on, regardless of how many layers are selected.', 'pizzatier' ),
				'tag'   => '',
				'color' => '#3b82f6',
			],
			'highest_wins' => [
				'icon'  => '🏆',
				'title' => __( 'Highest layer wins', 'pizzatier' ),
				'desc'  => __( 'Only the most expensive layer\'s grid price is added. Good for "any topping, same price" setups.', 'pizzatier' ),
				'tag'   => '',
				'color' => '#8b5cf6',
			],
			'tiered_by_count' => [
				'icon'  => '🪜',
				'title' => __( 'Tiered by topping count', 'pizzatier' ),
				'desc'  => __( 'Price depends on the number of toppings selected. Define tier thresholds below; grid columns must be labeled Tier1, Tier2, Tier3…', 'pizzatier' ),
				'tag'   => '',
				'color' => '#0ea5e9',
			],
			'free_first_n' => [
				'icon'  => '🎁',
				'title' => __( 'Free first N toppings', 'pizzatier' ),
				'desc'  => __( 'A configured number of toppings are included free. Each topping beyond that count is charged at the grid rate.', 'pizzatier' ),
				'tag'   => '',
				'color' => '#10b981',
			],
			'bundle' => [
				'icon'  => '📦',
				'title' => __( 'Bundle (fixed total)', 'pizzatier' ),
				'desc'  => __( 'The WooCommerce product price IS the total — no grid add-ons applied. The grid is informational only.', 'pizzatier' ),
				'tag'   => __( 'Simple', 'pizzatier' ),
				'color' => '#6b7280',
			],
		];
		?>
		<input type="hidden" id="pizzatier_commerce_pricing_mode_input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $current ); ?>">

		<div class="pztc-mode-grid" id="pztc-pricing-mode-grid">
			<?php foreach ( $modes as $mode_key => $mode ) :
				$selected = ( $current === $mode_key );
				?>
				<div class="pztc-mode-card<?php echo $selected ? ' pztc-mode-card--selected' : ''; ?>"
					 data-mode="<?php echo esc_attr( $mode_key ); ?>"
					 data-color="<?php echo esc_attr( $mode['color'] ); ?>"
					 style="<?php echo $selected ? '--mc:' . esc_attr( $mode['color'] ) . ';' : ''; ?>"
					 role="button" tabindex="0"
					 aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>">
					<?php if ( $mode['tag'] ) : ?>
						<span class="pztc-mode-card__tag" style="background:<?php echo esc_attr( $mode['color'] ); ?>">
							<?php echo esc_html( $mode['tag'] ); ?>
						</span>
					<?php endif; ?>
					<span class="pztc-mode-card__icon"><?php echo wp_kses( $mode['icon'], pzt_card_allowed_html() ); ?></span>
					<span class="pztc-mode-card__title"><?php echo esc_html( $mode['title'] ); ?></span>
					<span class="pztc-mode-card__desc"><?php echo esc_html( $mode['desc'] ); ?></span>
					<span class="pztc-mode-card__check" aria-hidden="true">✓</span>
				</div>
			<?php endforeach; ?>
		</div>

		<style>
		.pztc-mode-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
			gap: 12px;
			max-width: 800px;
			margin-top: 6px;
		}
		.pztc-mode-card {
			--mc: #ff6b35;
			position: relative;
			display: flex; flex-direction: column; gap: 7px;
			padding: 16px 14px 14px;
			background: #fff;
			border: 2px solid #e0e3e7;
			border-radius: 12px;
			cursor: pointer;
			transition: border-color .15s, background .15s, box-shadow .15s, transform .1s;
			user-select: none;
		}
		.pztc-mode-card:hover {
			border-color: var(--mc);
			background: color-mix(in srgb, var(--mc) 5%, white);
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(0,0,0,.08);
		}
		.pztc-mode-card--selected {
			border-color: var(--mc);
			background: color-mix(in srgb, var(--mc) 8%, white);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--mc) 20%, transparent);
		}
		.pztc-mode-card__tag {
			position: absolute; top: -1px; right: 12px;
			color: #fff;
			font-size: 10px; font-weight: 700;
			padding: 2px 8px; border-radius: 0 0 6px 6px;
			text-transform: uppercase; letter-spacing: .04em;
		}
		.pztc-mode-card__icon { font-size: 26px; line-height: 1; }
		.pztc-mode-card__title { font-size: 13px; font-weight: 700; color: #1d2023; }
		.pztc-mode-card--selected .pztc-mode-card__title { color: var(--mc); }
		.pztc-mode-card__desc { font-size: 11px; color: #646970; line-height: 1.5; flex: 1; }
		.pztc-mode-card__check {
			display: none;
			position: absolute; top: 10px; right: 10px;
			width: 20px; height: 20px; border-radius: 50%;
			background: var(--mc); color: #fff;
			font-size: 11px; font-weight: 700;
			align-items: center; justify-content: center;
		}
		.pztc-mode-card--selected .pztc-mode-card__check { display: flex; }
		</style>

		<?php // JS enqueued via wp_enqueue_script — see enqueue_assets() ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Sanitisation
	// -------------------------------------------------------------------------

	public function sanitize( array $raw ): array {
		// ── Partial-save support ─────────────────────────────────────────
		// The pizzatier_options option is now written from TWO admin
		// pages: SettingsPage (general/toppings/display/etc.) and the new
		// PricingPage (pricing engine + grid defaults). Both submit via
		// the Settings API to the same option group so each save sees only
		// a subset of fields in $raw. To avoid clobbering keys owned by the
		// other page we seed $clean from the current saved option and only
		// overwrite a section when at least one of its sentinel keys is
		// present in $raw.
		$current = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}
		$clean = array_merge( $this->defaults(), $current );

		// Helper: is any of these keys present in the submitted form?
		$present = function ( array $keys ) use ( $raw ): bool {
			foreach ( $keys as $k ) {
				if ( array_key_exists( $k, $raw ) ) {
					return true;
				}
			}
			return false;
		};

		// ── General ──────────────────────────────────────────────────────
		$general_keys = [ 'cart_btn_text', 'builder_position_default', 'redirect_after_add_to_cart',
			'show_size_selector', 'show_price_bar', 'show_cart_btn',
			'require_crust', 'require_sauce', 'require_size' ];
		if ( $present( $general_keys ) ) {
			foreach ( ['redirect_after_add_to_cart','show_size_selector','show_price_bar',
					   'show_cart_btn','require_crust','require_sauce','require_size',
					   'show_quantity_selector'] as $k ) {
				$clean[ $k ] = ! empty( $raw[ $k ] );
			}
			$clean['cart_btn_text'] = sanitize_text_field( $raw['cart_btn_text'] ?? '' );
			$allowed_pos = ['before_cart','after_title','after_summary'];
			$pos = sanitize_key( $raw['builder_position_default'] ?? 'before_cart' );
			$clean['builder_position_default'] = in_array( $pos, $allowed_pos, true ) ? $pos : 'before_cart';
		}

		// ── Defaults ─────────────────────────────────────────────────────
		// Default sizes / fractions are now owned by the PricingPage's
		// "Default Values" tab. Either page may submit them; both go through
		// the same Settings API so behaviour is identical.
		if ( $present( [ 'default_sizes', 'default_fractions' ] ) ) {
			$clean['default_sizes']     = $this->sanitize_label_list( $raw['default_sizes']     ?? '', 'default_sizes',     ['Small','Medium','Large','XL'] );
			$clean['default_fractions'] = $this->sanitize_label_list( $raw['default_fractions'] ?? '', 'default_fractions', ['Whole','Half','Quarter'] );
		}

		// ── Pricing ───────────────────────────────────────────────────────
		// Owned by PricingPage. Treat as a self-contained block so a save
		// from any other page (which won't include these keys) leaves the
		// pricing config untouched.
		$pricing_sentinel = [ 'pricing_mode', 'free_toppings_count', 'tiered_topping_thresholds',
			'non_topping_pricing', 'price_rounding', 'fallback_price_label',
			'size_price_multipliers', 'min_topping_price', 'max_topping_price',
			'discount_threshold', 'discount_percent', 'discount_max_amount',
			'crust_fixed_price', 'sauce_fixed_price', 'cheese_fixed_price', 'drizzle_fixed_price',
			'price_includes_tax', 'show_per_topping_price' ];
		if ( $present( $pricing_sentinel ) ) {
			$allowed_modes = ['addon_per_layer','flat_per_size','highest_wins','tiered_by_count','free_first_n','bundle'];
			$mode = sanitize_key( $raw['pricing_mode'] ?? 'addon_per_layer' );
			$clean['pricing_mode'] = in_array( $mode, $allowed_modes, true ) ? $mode : 'addon_per_layer';

			$clean['free_toppings_count']       = absint( $raw['free_toppings_count'] ?? 0 );
			$clean['tiered_topping_thresholds'] = sanitize_text_field( $raw['tiered_topping_thresholds'] ?? '3,6' );
			$clean['fallback_price_label']      = sanitize_text_field( $raw['fallback_price_label'] ?? '' );

			$allowed_np = ['grid','fixed','free'];
			$np = sanitize_key( $raw['non_topping_pricing'] ?? 'grid' );
			$clean['non_topping_pricing'] = in_array( $np, $allowed_np, true ) ? $np : 'grid';

			foreach ( ['crust_fixed_price','sauce_fixed_price','cheese_fixed_price','drizzle_fixed_price'] as $k ) {
				$clean[ $k ] = $this->sanitize_price( $raw[ $k ] ?? '' );
			}

			$allowed_rounding = ['','up','nearest5','nearest25','nearest50','nearest1'];
			$r = sanitize_key( $raw['price_rounding'] ?? '' );
			$clean['price_rounding'] = in_array( $r, $allowed_rounding, true ) ? $r : '';

			$clean['discount_threshold']  = absint( $raw['discount_threshold'] ?? 0 );
			$clean['discount_percent']    = min( 100, max( 0, (float)( $raw['discount_percent'] ?? 0 ) ) );
			$clean['discount_max_amount'] = $this->sanitize_price( $raw['discount_max_amount'] ?? '' );
			$clean['min_topping_price']   = $this->sanitize_price( $raw['min_topping_price'] ?? '' );
			$clean['max_topping_price']   = $this->sanitize_price( $raw['max_topping_price'] ?? '' );
			// size_price_multipliers: stored as array of "Label=multiplier" strings
			$raw_mults = $raw['size_price_multipliers'] ?? [];
			if ( is_string( $raw_mults ) ) {
				$raw_mults = explode( "\n", $raw_mults );
			}
			$clean_mults = [];
			foreach ( (array) $raw_mults as $line ) {
				$line = sanitize_text_field( trim( (string) $line ) );
				if ( preg_match( '/^.+=\d+(\.\d+)?$/', $line ) ) {
					$clean_mults[] = $line;
				}
			}
			$clean['size_price_multipliers'] = $clean_mults;
			$clean['price_includes_tax']     = ! empty( $raw['price_includes_tax'] );
			$clean['show_per_topping_price'] = ! empty( $raw['show_per_topping_price'] );
		}

		// ── Toppings ──────────────────────────────────────────────────────
		$topping_sentinel = [ 'max_toppings', 'min_toppings', 'max_same_topping',
			'allow_half_toppings', 'default_topping_fraction', 'topping_count_label',
			'topping_extra_charge_msg' ];
		if ( $present( $topping_sentinel ) ) {
			$clean['max_toppings']             = absint( $raw['max_toppings']   ?? 0 );
			$clean['min_toppings']             = absint( $raw['min_toppings']   ?? 0 );
			$clean['max_same_topping']         = absint( $raw['max_same_topping'] ?? 0 );
			$clean['allow_half_toppings']      = ! empty( $raw['allow_half_toppings'] );
			$clean['default_topping_fraction'] = sanitize_text_field( $raw['default_topping_fraction'] ?? 'Whole' );
			$clean['topping_count_label']      = sanitize_text_field( $raw['topping_count_label'] ?? '' );
			$clean['topping_extra_charge_msg'] = sanitize_text_field( $raw['topping_extra_charge_msg'] ?? '' );
		}

		// ── Display ───────────────────────────────────────────────────────
		$display_sentinel = [ 'size_selector_label', 'size_selector_style', 'price_bar_label',
			'price_bar_show_breakdown', 'price_bar_show_base', 'price_bar_show_savings',
			'price_bar_position', 'hide_wc_price', 'add_to_cart_success_msg',
			'out_of_stock_msg', 'builder_loading_text' ];
		if ( $present( $display_sentinel ) ) {
			$clean['size_selector_label']      = sanitize_text_field( $raw['size_selector_label'] ?? '' );
			$allowed_ss = ['pills','cards','dropdown'];
			$ss = sanitize_key( $raw['size_selector_style'] ?? 'pills' );
			$clean['size_selector_style']      = in_array( $ss, $allowed_ss, true ) ? $ss : 'pills';
			$clean['price_bar_label']          = sanitize_text_field( $raw['price_bar_label'] ?? '' );
			$clean['price_bar_show_breakdown'] = ! empty( $raw['price_bar_show_breakdown'] );
			$clean['price_bar_show_base']      = ! empty( $raw['price_bar_show_base'] );
			$clean['price_bar_show_savings']   = ! empty( $raw['price_bar_show_savings'] );
			$allowed_pbp = ['below_builder','above_builder','sticky_bottom'];
			$pbp = sanitize_key( $raw['price_bar_position'] ?? 'below_builder' );
			$clean['price_bar_position']       = in_array( $pbp, $allowed_pbp, true ) ? $pbp : 'below_builder';
			$clean['hide_wc_price']            = ! empty( $raw['hide_wc_price'] );
			$clean['add_to_cart_success_msg']  = sanitize_text_field( $raw['add_to_cart_success_msg'] ?? '' );
			$clean['out_of_stock_msg']         = sanitize_text_field( $raw['out_of_stock_msg'] ?? '' );
			$clean['builder_loading_text']     = sanitize_text_field( $raw['builder_loading_text'] ?? '' );
		}

		// ── Checkout Bar Layout ───────────────────────────────────────────
		$bar_layout_key = \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::SETTING_KEY;
		if ( array_key_exists( $bar_layout_key, $raw ) ) {
			$allowed_bar    = class_exists( \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::class )
				? array_keys( \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::get_select_options() )
				: [ \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::LEGACY_SLUG ];
			$submitted      = sanitize_key( (string) ( $raw[ $bar_layout_key ] ?? '' ) );
			$clean[ $bar_layout_key ] = in_array( $submitted, $allowed_bar, true )
				? $submitted
				: \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::LEGACY_SLUG;
		}

		// ── Action bar mode ───────────────────────────────────────────────
		// Which bar owns the builder's action-bar area: the WooCommerce Add to
		// Cart bar, PizzaTier's native order bar, or both.
		$mode_key = \PizzaTier\Orders\ActionBarMode::KEY;
		if ( array_key_exists( $mode_key, $raw ) ) {
			$allowed_modes = [
				\PizzaTier\Orders\ActionBarMode::WOOCOMMERCE,
				\PizzaTier\Orders\ActionBarMode::ORDERS,
				\PizzaTier\Orders\ActionBarMode::BOTH,
			];
			$submitted_mode   = sanitize_key( (string) ( $raw[ $mode_key ] ?? '' ) );
			$clean[ $mode_key ] = in_array( $submitted_mode, $allowed_modes, true )
				? $submitted_mode
				: \PizzaTier\Orders\ActionBarMode::WOOCOMMERCE;
		}

		// ── Cart & Checkout / Order Notes / Quantity / Email summary / Nutrition / Layer groups ──
		$checkout_sentinel = [ 'cart_item_name_template', 'order_note_template', 'min_order',
			'max_order', 'min_order_msg', 'max_order_msg', 'show_price_in_cart',
			'show_price_in_order', 'enable_order_notes', 'order_note_label',
			'order_note_placeholder', 'show_quantity_selector', 'max_quantity',
			'email_append_to_order', 'email_send_separate', 'email_separate_to_customer',
			'email_separate_to_admin', 'email_separate_subject', 'email_summary_heading',
			'nutrition_enabled', 'nutrition_display', 'nutrition_show_summary',
			'nutrition_show_calories', 'nutrition_show_fat', 'nutrition_show_carbs',
			'nutrition_show_protein', 'nutrition_show_sodium', 'nutrition_show_allergens',
			'layer_groups_enabled', 'cart_thumbnail_style', 'allow_reorder',
			'allow_cart_edit', 'guest_checkout' ];
		if ( $present( $checkout_sentinel ) ) {
			$clean['cart_item_name_template']  = sanitize_text_field( $raw['cart_item_name_template'] ?? '' );
			$clean['order_note_template']      = sanitize_text_field( $raw['order_note_template'] ?? '' );
			$clean['min_order']                = $this->sanitize_price( $raw['min_order'] ?? '' );
			$clean['max_order']                = $this->sanitize_price( $raw['max_order'] ?? '' );
			$clean['min_order_msg']            = sanitize_text_field( $raw['min_order_msg'] ?? '' );
			$clean['max_order_msg']            = sanitize_text_field( $raw['max_order_msg'] ?? '' );
			$clean['show_price_in_cart']       = ! empty( $raw['show_price_in_cart'] );
			$clean['show_price_in_order']      = ! empty( $raw['show_price_in_order'] );
			// Order notes
			$clean['enable_order_notes']       = ! empty( $raw['enable_order_notes'] );
			$clean['order_note_label']         = sanitize_text_field( $raw['order_note_label']       ?? '' );
			$clean['order_note_placeholder']   = sanitize_text_field( $raw['order_note_placeholder'] ?? '' );
			// Quantity stepper
			$clean['show_quantity_selector']   = ! empty( $raw['show_quantity_selector'] );
			$clean['max_quantity']             = max( 1, absint( $raw['max_quantity'] ?? 99 ) );
			// Email summary
			$clean['email_append_to_order']       = ! empty( $raw['email_append_to_order'] );
			$clean['email_send_separate']         = ! empty( $raw['email_send_separate'] );
			$clean['email_separate_to_customer']  = ! empty( $raw['email_separate_to_customer'] );
			$clean['email_separate_to_admin']     = ! empty( $raw['email_separate_to_admin'] );
			$clean['email_separate_subject']      = sanitize_text_field( $raw['email_separate_subject']  ?? '' );
			$clean['email_summary_heading']       = sanitize_text_field( $raw['email_summary_heading']   ?? '' );
			// Nutrition
			$clean['nutrition_enabled']           = ! empty( $raw['nutrition_enabled'] );
			$allowed_nd = [ 'tooltip', 'inline', 'panel' ];
			$nd = sanitize_key( $raw['nutrition_display'] ?? 'tooltip' );
			$clean['nutrition_display']           = in_array( $nd, $allowed_nd, true ) ? $nd : 'tooltip';
			$clean['nutrition_show_summary']      = ! empty( $raw['nutrition_show_summary'] );
			$clean['nutrition_show_calories']     = ! empty( $raw['nutrition_show_calories'] );
			$clean['nutrition_show_fat']          = ! empty( $raw['nutrition_show_fat'] );
			$clean['nutrition_show_carbs']        = ! empty( $raw['nutrition_show_carbs'] );
			$clean['nutrition_show_protein']      = ! empty( $raw['nutrition_show_protein'] );
			$clean['nutrition_show_sodium']       = ! empty( $raw['nutrition_show_sodium'] );
			$clean['nutrition_show_allergens']    = ! empty( $raw['nutrition_show_allergens'] );
			$clean['layer_groups_enabled']        = ! empty( $raw['layer_groups_enabled'] );
			$allowed_ct = ['default','none'];
			$ct = sanitize_key( $raw['cart_thumbnail_style'] ?? 'default' );
			$clean['cart_thumbnail_style']     = in_array( $ct, $allowed_ct, true ) ? $ct : 'default';
			$clean['allow_reorder']            = ! empty( $raw['allow_reorder'] );
			$clean['allow_cart_edit']          = ! empty( $raw['allow_cart_edit'] );
			$clean['guest_checkout']           = ! empty( $raw['guest_checkout'] );
		}

		// ── Advanced ──────────────────────────────────────────────────────
		$advanced_sentinel = [ 'tax_display', 'tax_class_override', 'enable_rest_price',
			'disable_quantity', 'cache_price_grid', 'schema_markup',
			'price_update_delay_ms', 'debug_mode' ];
		if ( $present( $advanced_sentinel ) ) {
			$allowed_tax = ['','incl','excl'];
			$tax = sanitize_key( $raw['tax_display'] ?? '' );
			$clean['tax_display']              = in_array( $tax, $allowed_tax, true ) ? $tax : '';
			$clean['tax_class_override']       = sanitize_text_field( $raw['tax_class_override'] ?? '' );
			$clean['enable_rest_price']        = ! empty( $raw['enable_rest_price'] );
			$clean['disable_quantity']         = ! empty( $raw['disable_quantity'] );
			$clean['cache_price_grid']         = ! empty( $raw['cache_price_grid'] );
			$clean['schema_markup']            = ! empty( $raw['schema_markup'] );
			$clean['price_update_delay_ms']    = absint( $raw['price_update_delay_ms'] ?? 0 );
			$clean['debug_mode']               = ! empty( $raw['debug_mode'] );
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Defaults
	// -------------------------------------------------------------------------

	public function defaults(): array {
		return [
			// General
			'redirect_after_add_to_cart' => false,
			'show_size_selector'         => true,
			'show_price_bar'             => true,
			'show_cart_btn'              => false,
			'cart_btn_text'              => '',
			'require_crust'              => false,
			'require_sauce'              => false,
			'require_size'               => false,
			'builder_position_default'   => 'before_cart',
			// Defaults
			'default_sizes'              => ['Small','Medium','Large','XL'],
			'default_fractions'          => ['Whole','Half','Quarter'],
			// Pricing
			'pricing_mode'               => 'addon_per_layer',
			'free_toppings_count'        => 0,
			'tiered_topping_thresholds'  => '3,6',
			'non_topping_pricing'        => 'grid',
			'crust_fixed_price'          => '',
			'sauce_fixed_price'          => '',
			'cheese_fixed_price'         => '',
			'drizzle_fixed_price'        => '',
			'fallback_price_label'       => '',
			'price_rounding'             => '',
			'discount_threshold'         => 0,
			'discount_percent'           => 0,
			'discount_max_amount'        => '',
			'min_topping_price'          => '',
			'max_topping_price'          => '',
			'size_price_multipliers'     => [],
			'price_includes_tax'         => false,
			'show_per_topping_price'     => false,
			// Toppings
			'max_toppings'               => 0,
			'min_toppings'               => 0,
			'max_same_topping'           => 0,
			'allow_half_toppings'        => false,
			'default_topping_fraction'   => 'Whole',
			'topping_count_label'        => 'toppings',
			'topping_extra_charge_msg'   => '',
			// Display
			'size_selector_label'        => '',
			'size_selector_style'        => 'pills',
			'price_bar_label'            => '',
			'price_bar_show_breakdown'   => true,
			'price_bar_show_base'        => false,
			'price_bar_show_savings'     => true,
			'price_bar_position'         => 'below_builder',
			'hide_wc_price'              => false,
			'add_to_cart_success_msg'    => '',
			'out_of_stock_msg'           => '',
			'builder_loading_text'       => '',
			// Checkout Bar Layout
			'checkout_bar_layout'        => 'legacy',
			// Which bar owns the builder's action-bar area.
			// Defaults to WooCommerce so updating an existing store changes
			// nothing about what customers see until this is set deliberately.
			'action_bar_mode'            => 'woocommerce',
			// Cart & Checkout
			'cart_item_name_template'    => '',
			'order_note_template'        => '',
			'min_order'                  => '',
			'max_order'                  => '',
			'min_order_msg'              => '',
			'max_order_msg'              => '',
			'show_price_in_cart'         => true,
			'show_price_in_order'        => true,
			// Order notes
			'enable_order_notes'         => false,
			'order_note_label'           => '',
			'order_note_placeholder'     => '',
			// Quantity stepper
			'show_quantity_selector'     => true,
			'max_quantity'               => 99,
			// Email summary
			'email_append_to_order'      => false,
			'email_send_separate'        => false,
			'email_separate_to_customer' => true,
			'email_separate_to_admin'    => false,
			'email_separate_subject'     => '',
			'email_summary_heading'      => '',
			// Nutrition
			'nutrition_enabled'          => false,
			'nutrition_display'          => 'tooltip',
			'nutrition_show_summary'     => false,
			'nutrition_show_calories'    => true,
			'nutrition_show_fat'         => false,
			'nutrition_show_carbs'       => false,
			'nutrition_show_protein'     => false,
			'nutrition_show_sodium'      => false,
			'nutrition_show_allergens'   => false,
			// Layer groups
			'layer_groups_enabled'       => false,
			'cart_thumbnail_style'       => 'default',
			'allow_reorder'              => false,
			'allow_cart_edit'            => false,
			'guest_checkout'             => true,
			// Advanced
			'tax_display'                => '',
			'tax_class_override'         => '',
			'enable_rest_price'          => true,
			'disable_quantity'           => false,
			'cache_price_grid'           => false,
			'schema_markup'              => false,
			'price_update_delay_ms'      => 0,
			'debug_mode'                 => false,
		];
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( 'pizzatier_page_pizzatier-commerce' !== $hook ) {
			return;
		}
		$v = PIZZATIER_VERSION;
		wp_enqueue_style( 'pizzatier-commerce-admin', PIZZATIER_PLUGIN_URL . 'assets/css/admin.css', [], $v );
		wp_enqueue_script( 'pizzatier-commerce-admin-settings',      PIZZATIER_PLUGIN_URL . 'assets/js/admin-settings.js',      [], $v, true );
		wp_enqueue_script( 'pizzatier-commerce-admin-settings-page', PIZZATIER_PLUGIN_URL . 'assets/js/admin-settings-page.js', [], $v, true );
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
	// Private helpers
	// -------------------------------------------------------------------------

	private function sanitize_price( $raw ): string {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) return '';
		return number_format( (float) $raw, 2, '.', '' );
	}

	private function sanitize_label_list( $raw, string $error_key, array $fallback ): array {
		$lines = is_array( $raw ) ? $raw : explode( "\n", (string) $raw );
		$clean = [];
		foreach ( $lines as $line ) {
			$line = sanitize_text_field( trim( $line ) );
			if ( '' === $line ) continue;
			if ( strlen( $line ) > 40 ) { $line = substr( $line, 0, 40 ); }
			$clean[] = $line;
		}
		$clean = array_values( array_unique( $clean ) );
		if ( empty( $clean ) ) {
			add_settings_error( self::OPTION_NAME, 'pizzatier_commerce_' . $error_key . '_empty',
				__( 'That list cannot be empty. Reverted to defaults.', 'pizzatier' ), 'warning' );
			return $fallback;
		}
		return $clean;
	}
}
