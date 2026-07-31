<?php
/**
 * Cart integration for pizza products.
 *
 * Handles:
 *   1. render_cart_button() — called by the pizzatier_builder_action_bar hook
 *      (inside the PizzaTier builder canvas). Renders the Add to Cart button.
 *      This is the same hook that existed in Phase 1; we keep it for the
 *      in-builder context while the product page also gets an Add to Cart
 *      button through the standard WooCommerce add-to-cart form.
 *
 *   2. handle_add_to_cart() — wp_ajax / wp_ajax_nopriv AJAX handler.
 *      Called by cart.js with the pizza configuration. Steps:
 *        a. Verify nonce.
 *        b. Call PriceCalculator to get the authoritative price.
 *        c. Add the item to the WC cart with the verified price and config
 *           stored as custom cart item data.
 *        d. Return a JSON response for the JS to consume.
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceCalculator;

class CartIntegration {

	/** AJAX action name. */
	const AJAX_ACTION = 'pizzatier_commerce_add_to_cart';

	/** @var PriceCalculator */
	private PriceCalculator $calculator;

	public function __construct( PriceCalculator $calculator ) {
		$this->calculator = $calculator;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// AJAX handler — logged-in users.
		add_action( 'wp_ajax_'         . self::AJAX_ACTION, [ $this, 'handle_add_to_cart' ] );
		// AJAX handler — guests.
		add_action( 'wp_ajax_nopriv_'  . self::AJAX_ACTION, [ $this, 'handle_add_to_cart' ] );

		// Price override on every cart recalculation.
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_cart_prices' ], 20 );

		// Enqueue template-scoped checkout-bar styles and stepper JS.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_bar_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Checkout bar assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the per-template checkout bar CSS and quantity-stepper JS.
	 * Only fires on the frontend — assets are lightweight and only active when
	 * the pizzatier_builder_action_bar hook fires (i.e. a builder is on the page).
	 */
	public function enqueue_checkout_bar_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$url = PIZZATIER_PLUGIN_URL;
		$ver = PIZZATIER_VERSION;

		wp_enqueue_style(
			'pizzatier-commerce-checkout-bars',
			$url . 'assets/css/checkout-bars.css',
			[],
			$ver
		);

		wp_enqueue_script(
			'pizzatier-commerce-checkout-bar',
			$url . 'assets/js/checkout-bar.js',
			[],
			$ver,
			true
		);
	}

	// -------------------------------------------------------------------------
	// PizzaTier builder action bar button
	// -------------------------------------------------------------------------

	/**
	 * Render the checkout bar inside the PizzaTier builder canvas.
	 *
	 * Called via pizzatier_builder_action_bar action hook.
	 * Loads checkout-bar.php from the active PizzaTier template folder so
	 * each template can style it independently. The file receives $instance_id.
	 *
	 * @param string $instance_id  PizzaTier builder instance identifier.
	 */
	public function render_cart_button( string $instance_id ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// If on a WooCommerce single-product page, restrict to pizza products only.
		// On non-product pages (demo, shortcode embeds) we always render —
		// the checkout-bar.php file itself is guarded by class_exists(PizzaTier).
		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( ! $product instanceof \WC_Product ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product is WooCommerce's own global; it cannot be prefixed.
				$product = wc_get_product( get_the_ID() );
			}
			if ( $product instanceof \WC_Product ) {
				$is_pizza = ( 'pizza' === $product->get_type() )
					|| ( '' !== (string) get_post_meta( $product->get_id(), '_pizzatier_builder_template', true ) );
				if ( ! $is_pizza ) {
					return;
				}
			}
		}

		// Resolve which partial to render.
		//
		// Priority (LayoutRegistry handles 1–3 internally):
		//   1. Child-theme override at {stylesheet}/pizzatier/checkout-bar.php
		//   2. The selected global layout from Settings, if it isn't 'legacy'
		//   3. The built-in 'classic-horizontal' fallback
		//   4. (this code) The active PizzaTier template's own checkout-bar.php
		//      — used when LayoutRegistry signals legacy mode by returning ''.
		$active_slug = function_exists( 'pizzatier_get_active_template_slug' )
			? pizzatier_get_active_template_slug()
			: get_option( 'pizzatier_setting_global_template', 'colorbox' );

		$bar_file = '';
		if ( class_exists( \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::class ) ) {
			$bar_file = \PizzaTier\Commerce\CheckoutBar\LayoutRegistry::resolve_partial( $active_slug );
		}

		// Fallback chain when the registry returns '' (legacy mode or missing file):
		// first try the TemplateLoader, then hit the active template directory directly.
		if ( ! $bar_file || ! file_exists( $bar_file ) ) {
			if ( class_exists( 'PizzaTier\\Template\\TemplateLoader' ) ) {
				$loader   = new \PizzaTier\Template\TemplateLoader();
				$bar_file = $loader->get_template_file( 'checkout-bar.php' );
			}
		}

		if ( ! $bar_file || ! file_exists( $bar_file ) ) {
			$bar_file = defined( 'PIZZATIER_TEMPLATES_DIR' )
				? PIZZATIER_TEMPLATES_DIR . $active_slug . '/checkout-bar.php'
				: '';
		}

		if ( $bar_file && file_exists( $bar_file ) ) {
			// Expose the resolved template slug to the partial so layouts can
			// emit a matching .pztc-checkout-bar--{template} class for palette styling.
			$checkout_bar_template_slug = (string) $active_slug;
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			include $bar_file;
		}
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the add-to-cart AJAX request from cart.js.
	 *
	 * Expected POST params:
	 *   nonce      (string)  wp_ajax nonce for pizzatier_commerce_add_to_cart
	 *   product_id (int)
	 *   size       (string)
	 *   layers     (JSON string) array of { layerId, fraction }
	 */
	public function handle_add_to_cart(): void {
		// ── Nonce ─────────────────────────────────────────────────────────
		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, self::AJAX_ACTION ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'pizzatier' ),
				'code'    => 'invalid_nonce',
			] );
			return;
		}

		// ── Input ─────────────────────────────────────────────────────────
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$size       = sanitize_text_field( wp_unslash( $_POST['size'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
		$layers_raw = isset( $_POST['layers'] ) ? wp_unslash( $_POST['layers'] ) : '[]';
		$order_note = isset( $_POST['order_note'] )
			? sanitize_textarea_field( wp_unslash( $_POST['order_note'] ) )
			: '';
		// Quantity — sent by checkout-bar.js quantity stepper; default 1.
		$quantity   = max( 1, min( 99, absint( $_POST['quantity'] ?? 1 ) ) );

		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'pizzatier' ), 'code' => 'invalid_product' ] );
			return;
		}

		// Decode layers — arrives as JSON string from cart.js.
		$layers = is_string( $layers_raw ) ? json_decode( $layers_raw, true ) : (array) $layers_raw;

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $layers ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not read layer configuration.', 'pizzatier' ), 'code' => 'invalid_layers' ] );
			return;
		}

		// Sanitize each layer entry. layerPostId is the CPT post ID for per-layer
		// pricing; it flows through to PriceCalculator for grid lookup.
		$sanitized_layers = [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$sanitized_layers[] = [
				'layerId'     => sanitize_text_field( (string) ( $layer['layerId']     ?? '' ) ),
				'fraction'    => sanitize_text_field( (string) ( $layer['fraction']    ?? '' ) ),
				'portion'     => OrderMeta::canonical_portion( (string) ( $layer['portion'] ?? '' ) ),
				'portionLabel'=> sanitize_text_field( (string) ( $layer['portionLabel'] ?? '' ) ),
				'layerType'   => sanitize_text_field( (string) ( $layer['layerType']   ?? '' ) ),
				'layerName'   => sanitize_text_field( (string) ( $layer['layerName']   ?? '' ) ),
				'layerPostId' => absint( $layer['layerPostId'] ?? 0 ),
			];
		}

		// ── Server-side price verification ────────────────────────────────
		$result = $this->calculator->calculate( $product_id, $size, $sanitized_layers );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
			return;
		}

		$verified_price = (float) $result['total'];

		// Build a layerName lookup from the sanitized layer data.
		// layerName is display-only — the price always comes from the server grid.
		$client_names = [];
		foreach ( $sanitized_layers as $layer ) {
			$lid   = $layer['layerId'];
			$lname = $layer['layerName'];
			if ( $lid !== '' ) {
				$client_names[ $lid ] = $lname;
			}
		}

		// Merge layerName into the server-calculated breakdown.
		$breakdown_with_names = array_map( function( $entry ) use ( $client_names ) {
			$lid = $entry['layerId'] ?? '';
			$entry['layerName'] = isset( $client_names[ $lid ] ) && $client_names[ $lid ] !== ''
				? $client_names[ $lid ]
				: ucwords( str_replace( [ '-', '_' ], ' ', $lid ) );
			return $entry;
		}, $result['breakdown'] );

		// ── Add to WC cart ────────────────────────────────────────────────
		// We store TWO views of the layer data:
		//   CART_LAYERS       — server-priced breakdown (authoritative; carries prices/notes).
		//   CART_INPUT_LAYERS — raw, sanitized client input (always non-empty when the
		//                       customer selected anything). Used as a fallback so the
		//                       order/cart display can never end up with an empty layer
		//                       list when the customer actually chose ingredients
		//                       (e.g. zero-grid scenarios, or future code paths that
		//                       might short-circuit the breakdown array).
		$cart_item_data = [
			OrderMeta::CART_SIZE         => $size,
			OrderMeta::CART_LAYERS       => $breakdown_with_names,
			OrderMeta::CART_INPUT_LAYERS => $sanitized_layers,
			OrderMeta::CART_TOTAL        => $verified_price,
			OrderMeta::CART_BASE_PRICE   => $result['base_price'] ?? 0.0,
			// Unique key so different configurations of the same product are separate line items.
			OrderMeta::CART_KEY          => md5( $product_id . $size . wp_json_encode( $sanitized_layers ) ),
		];

		// Attach customer's per-pizza note if provided and the feature is enabled.
		if ( $order_note !== '' && (bool) pizzatier_get_option( 'enable_order_notes', false ) ) {
			$cart_item_data[ OrderMeta::CART_ORDER_NOTE ] = $order_note;
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );

		if ( false === $cart_item_key ) {
			$notices = wc_get_notices( 'error' );
			$message = ! empty( $notices )
				? wp_strip_all_tags( $notices[0]['notice'] )
				: __( 'Could not add the pizza to your cart. Please try again.', 'pizzatier' );
			wc_clear_notices();
			wp_send_json_error( [ 'message' => $message, 'code' => 'cart_add_failed' ] );
			return;
		}

		// ── Success response ──────────────────────────────────────────────
		// `redirect`, when set, overrides every client-side redirect rule. It
		// is how the straight-to-checkout route skips the cart page — the
		// decision belongs on the server because that is where the route lives.
		$redirect = \PizzaTier\Orders\OrderRoute::redirects_to_checkout()
			? (string) apply_filters( 'pizzatier_order_checkout_redirect', wc_get_checkout_url() )
			: '';

		wp_send_json_success( [
			'message'         => __( 'Pizza added to cart!', 'pizzatier' ),
			'cart_url'        => wc_get_cart_url(),
			'cart_count'      => WC()->cart->get_cart_contents_count(),
			'total_formatted' => $result['total_formatted'],
			'currency_symbol' => $result['currency_symbol'],
			'redirect'        => $redirect,
		] );
	}

	// -------------------------------------------------------------------------
	// Cart price override
	// -------------------------------------------------------------------------

	/**
	 * Apply the stored verified price to all pizza items before WC
	 * recalculates cart totals.
	 *
	 * Hooked to woocommerce_before_calculate_totals (priority 20).
	 *
	 * @param \WC_Cart $cart
	 */
	public function apply_cart_prices( \WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item[ OrderMeta::CART_TOTAL ] ) ) {
				continue;
			}

			$product = $cart_item['data'] ?? null;

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			// Apply to formal 'pizza' type products AND to any product that has a
			// PizzaTier builder template configured (standard WC product types
			// used with the builder but not registered as the custom pizza type).
			$is_pizza_type     = ( 'pizza' === $product->get_type() );
			$has_pizzatier_commerce_config = ( '' !== (string) $product->get_meta( '_pizzatier_builder_template', true ) );

			if ( ! $is_pizza_type && ! $has_pizzatier_commerce_config ) {
				continue;
			}

			$product->set_price( (float) $cart_item[ OrderMeta::CART_TOTAL ] );
		}
	}
}
