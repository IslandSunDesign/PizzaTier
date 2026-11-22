<?php
/**
 * Cart & checkout rules for pizza products.
 *
 *  - A pizza can only enter the cart through the builder (or a re-order that
 *    carries its stored configuration). Shop-page buttons, ?add-to-cart=ID
 *    links and the Store API used to add an empty pizza at base price.
 *  - "Allow guest pizza orders", "Cart item name template", "Order note
 *    template" and "Cart item thumbnail" were saved by the settings screen
 *    but never applied before 2.4.0.
 *
 * PHP 7.4 compatible.
 *
 * @package PizzaTier\Commerce\WooCommerce
 * @since   2.4.0
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartRules {

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 20, 6 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', [ $this, 'validate_store_api' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_name', [ $this, 'cart_item_name' ], 20, 3 );
		add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'cart_item_thumbnail' ], 20, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'order_item_name' ], 30, 4 );
		add_action( 'woocommerce_checkout_order_created', [ $this, 'order_notes' ] );

		// Block cart (Store API): the classic name/thumbnail filters and the
		// "Edit pizza" link never reach it, so expose the same data as cart
		// item extension data and apply it with a small script.
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api_data();
		} else {
			add_action( 'woocommerce_blocks_loaded', [ $this, 'register_store_api_data' ] );
		}
		add_filter( 'woocommerce_store_api_cart_item_images', [ $this, 'store_api_images' ], 20, 3 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_block_cart' ] );
	}

	public function register_store_api_data(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'pizzatier',
				'data_callback'   => [ $this, 'store_api_item_data' ],
				'schema_callback' => static function (): array {
					return [
						'name'       => [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
						'edit_url'   => [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
						'edit_label' => [ 'type' => 'string', 'context' => [ 'view', 'edit' ], 'readonly' => true ],
					];
				},
				'schema_type'     => ARRAY_A,
			]
		);
	}

	/**
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function store_api_item_data( $cart_item ): array {
		$out = [ 'name' => '', 'edit_url' => '', 'edit_label' => '' ];
		if ( ! is_array( $cart_item ) || ! isset( $cart_item[ OrderMeta::CART_TOTAL ] ) ) {
			return $out;
		}
		$tpl = trim( (string) pizzatier_get_option( 'cart_item_name_template', '' ) );
		if ( '' !== $tpl && ( $cart_item['data'] ?? null ) instanceof \WC_Product ) {
			$out['name'] = self::fill( $tpl, self::tokens( $cart_item['data']->get_name(), $cart_item ) );
		}
		if ( (bool) pizzatier_get_option( 'allow_cart_edit', false ) ) {
			$out['edit_url']   = OrderMeta::cart_edit_url( $cart_item, (string) ( $cart_item['key'] ?? '' ) );
			$out['edit_label'] = __( 'Edit pizza', 'pizzatier' );
		}
		return $out;
	}

	/**
	 * "Cart item thumbnail: none" for the block cart.
	 *
	 * @param array  $images        Images.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Key.
	 * @return array
	 */
	public function store_api_images( $images, $cart_item = [], $cart_item_key = '' ) {
		if ( 'none' === (string) pizzatier_get_option( 'cart_thumbnail_style', 'default' ) && is_array( $cart_item ) && isset( $cart_item[ OrderMeta::CART_TOTAL ] ) ) {
			return [];
		}
		return $images;
	}

	public function enqueue_block_cart(): void {
		$needed = (bool) pizzatier_get_option( 'allow_cart_edit', false ) || '' !== trim( (string) pizzatier_get_option( 'cart_item_name_template', '' ) );
		if ( ! $needed || ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) || ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			return;
		}
		$page = get_post( is_cart() ? wc_get_page_id( 'cart' ) : wc_get_page_id( 'checkout' ) );
		if ( ! $page || ! ( has_block( 'woocommerce/cart', $page ) || has_block( 'woocommerce/checkout', $page ) ) ) {
			return;
		}
		wp_enqueue_script( 'pizzatier-block-cart', PIZZATIER_PLUGIN_URL . 'assets/js/block-cart.js', [ 'wc-blocks-checkout', 'wp-data' ], PIZZATIER_VERSION, true );
	}

	private static function is_pizza( $product_id ): bool {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}
		$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
		$check  = $parent instanceof \WC_Product ? $parent : $product;
		return 'pizza' === $check->get_type() || '' !== (string) $check->get_meta( '_pizzatier_builder_template', true );
	}

	/** Whether guests may buy pizzas ("Allow guest pizza orders", default on). */
	public static function guests_allowed(): bool {
		return (bool) pizzatier_get_option( 'guest_checkout', true );
	}

	/**
	 * Classic add-to-cart (form, ?add-to-cart=, order again).
	 *
	 * @param bool  $passed       Validation so far.
	 * @param int   $product_id   Product.
	 * @param int   $quantity     Quantity.
	 * @param int   $variation_id Variation.
	 * @param array $variations   Attributes.
	 * @param array $cart_item_data Cart item data (order again passes it).
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0, $variations = [], $cart_item_data = [] ) {
		if ( ! $passed || ! self::is_pizza( $product_id ) ) {
			return $passed;
		}
		if ( ! self::guests_allowed() && ! is_user_logged_in() ) {
			wc_add_notice( __( 'Please log in to order pizza.', 'pizzatier' ), 'error' );
			return false;
		}
		// A configured pizza (re-order) carries its verified price.
		if ( is_array( $cart_item_data ) && isset( $cart_item_data[ OrderMeta::CART_TOTAL ] ) ) {
			return $passed;
		}
		wc_add_notice( __( 'Please build your pizza first — choose a size and toppings in the pizza builder.', 'pizzatier' ), 'error' );
		return false;
	}

	/**
	 * Block Store API (block cart / mini-cart) adds of unconfigured pizzas.
	 *
	 * @param \WC_Product $product Product.
	 * @param mixed       $request Request.
	 */
	public function validate_store_api( $product, $request = null ): void {
		if ( ! $product instanceof \WC_Product || ! self::is_pizza( $product->get_id() ) ) {
			return;
		}
		$class = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
		if ( class_exists( $class ) ) {
			throw new $class( 'pizzatier_configure_pizza', esc_html__( 'Please build your pizza in the pizza builder before adding it to the cart.', 'pizzatier' ), 400 );
		}
	}

	/** Token values for one cart / order item. */
	private static function tokens( string $product_name, array $data ): array {
		$layers = isset( $data[ OrderMeta::CART_LAYERS ] ) && is_array( $data[ OrderMeta::CART_LAYERS ] ) ? $data[ OrderMeta::CART_LAYERS ] : [];
		$by     = [ 'crust' => [], 'sauce' => [], 'cheese' => [], 'topping' => [] ];
		foreach ( $layers as $l ) {
			$t = strtolower( (string) ( $l['layerType'] ?? '' ) );
			$t = '' === $t ? 'topping' : rtrim( $t, 's' );
			if ( isset( $by[ $t ] ) && '' !== (string) ( $l['layerName'] ?? '' ) && 0 !== strpos( (string) ( $l['layerId'] ?? '' ), '_' ) ) {
				$by[ $t ][] = (string) $l['layerName'];
			}
		}
		return [
			'{product}'       => $product_name,
			'{size}'          => (string) ( $data[ OrderMeta::CART_SIZE ] ?? '' ),
			'{topping_count}' => (string) count( $by['topping'] ),
			'{toppings}'      => implode( ', ', $by['topping'] ),
			'{crust}'         => implode( ', ', $by['crust'] ),
			'{sauce}'         => implode( ', ', $by['sauce'] ),
			'{cheese}'        => implode( ', ', $by['cheese'] ),
			'{total}'         => isset( $data[ OrderMeta::CART_TOTAL ] ) ? wp_strip_all_tags( wc_price( (float) $data[ OrderMeta::CART_TOTAL ] ) ) : '',
		];
	}

	private static function fill( string $tpl, array $tokens ): string {
		$out = strtr( $tpl, $tokens );
		// Tidy separators left by empty tokens, e.g. "Pizza —  ()".
		$out = preg_replace( '/\(\s*\)|\s{2,}/u', ' ', $out );
		return trim( (string) $out, " \t\n\r\0\x0B—–-,:" );
	}

	/**
	 * @param string $name          Item name HTML.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Key.
	 * @return string
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key = '' ) {
		$tpl = trim( (string) pizzatier_get_option( 'cart_item_name_template', '' ) );
		if ( '' === $tpl || ! isset( $cart_item[ OrderMeta::CART_TOTAL ] ) || ! ( $cart_item['data'] ?? null ) instanceof \WC_Product ) {
			return $name;
		}
		$label = self::fill( $tpl, self::tokens( $cart_item['data']->get_name(), $cart_item ) );
		if ( '' === $label ) {
			return $name;
		}
		// Keep the product link the cart shows.
		if ( false !== strpos( (string) $name, '<a ' ) ) {
			return preg_replace( '/>[^<]*<\/a>/', '>' . esc_html( $label ) . '</a>', (string) $name, 1 );
		}
		return esc_html( $label );
	}

	/**
	 * @param string $thumbnail     Thumbnail HTML.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Key.
	 * @return string
	 */
	public function cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key = '' ) {
		if ( 'none' === (string) pizzatier_get_option( 'cart_thumbnail_style', 'default' ) && isset( $cart_item[ OrderMeta::CART_TOTAL ] ) ) {
			return '';
		}
		return $thumbnail;
	}

	/**
	 * Carry the cart item name template into the order.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Key.
	 * @param array                  $values        Cart item.
	 * @param \WC_Order              $order         Order.
	 */
	public function order_item_name( $item, $cart_item_key, $values, $order ): void {
		$tpl = trim( (string) pizzatier_get_option( 'cart_item_name_template', '' ) );
		if ( '' === $tpl || ! isset( $values[ OrderMeta::CART_TOTAL ] ) || ! ( $values['data'] ?? null ) instanceof \WC_Product ) {
			return;
		}
		$label = self::fill( $tpl, self::tokens( $values['data']->get_name(), $values ) );
		if ( '' !== $label ) {
			$item->set_name( $label );
		}
	}

	/**
	 * "Order note template": one private note per pizza.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function order_notes( $order ): void {
		$tpl = trim( (string) pizzatier_get_option( 'order_note_template', '' ) );
		if ( '' === $tpl || ! $order instanceof \WC_Order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$layers = $item->get_meta( OrderMeta::META_LAYERS, true );
			if ( ! is_array( $layers ) || empty( $layers ) ) {
				continue;
			}
			$data = [
				OrderMeta::CART_LAYERS => $layers,
				OrderMeta::CART_SIZE   => (string) $item->get_meta( OrderMeta::META_SIZE, true ),
				OrderMeta::CART_TOTAL  => (float) $item->get_meta( OrderMeta::META_TOTAL, true ),
			];
			$note = self::fill( $tpl, self::tokens( $item->get_name(), $data ) );
			if ( '' !== $note ) {
				$order->add_order_note( $note );
			}
		}
	}
}
