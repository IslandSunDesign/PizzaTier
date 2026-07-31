<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Commerce\PriceCalculator;
use PizzaTier\Commerce\PriceGrid\Grid;
use PizzaTier\Commerce\WooCommerce\OrderMeta;

/**
 * Carries a submitted order to wherever the route says it should go.
 *
 * OrderSubmission builds and validates the order; this class delivers it. The
 * split matters because delivery is the part that can fail for reasons that
 * have nothing to do with the customer's input — WooCommerce declining the cart
 * add, a webhook endpoint timing out — and those failures need different
 * handling from a bad phone number.
 *
 * The safety rule this class enforces: an order is never discarded unless it was
 * demonstrably delivered somewhere. If the "notify only" route cannot reach the
 * store by email or webhook, the record is kept regardless of the setting, so a
 * network blip loses a customer's dinner rather than the store's only copy of it.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.1.0
 */
final class RouteDispatcher {

	/** Header carrying the HMAC signature of the webhook body. */
	const SIGNATURE_HEADER = 'X-PizzaTier-Signature';

	/**
	 * Deliver an order according to the active route.
	 *
	 * @param Order $order      The populated order.
	 * @param int   $product_id Product the submission came from, 0 when unknown.
	 * @param bool  $emailed    Whether the admin notification was sent.
	 *
	 * @return array{
	 *     cart_added:bool, cart_count:int, cart_url:string, redirect:string,
	 *     webhook_sent:bool, discard:bool, errors:string[]
	 * }
	 */
	public function dispatch( Order $order, int $product_id = 0, bool $emailed = false ): array {
		$result = [
			'cart_added'   => false,
			'cart_count'   => 0,
			'cart_url'     => '',
			'redirect'     => '',
			'webhook_sent' => false,
			'discard'      => false,
			'errors'       => [],
		];

		// ── WooCommerce cart ──────────────────────────────────────────────
		if ( OrderRoute::adds_to_cart() ) {
			$cart = $this->add_to_cart( $order, $product_id );

			$result['cart_added'] = $cart['added'];
			$result['cart_count'] = $cart['count'];
			$result['cart_url']   = $cart['cart_url'];

			if ( ! $cart['added'] && '' !== $cart['error'] ) {
				$result['errors'][] = $cart['error'];
			}

			if ( $cart['added'] ) {
				$result['redirect'] = OrderRoute::redirects_to_checkout()
					? $this->checkout_url()
					: '';
			}
		}

		// ── Webhook ───────────────────────────────────────────────────────
		// Sent for every route when a URL is configured, not just "notify
		// only" — a kitchen display is just as useful alongside the cart.
		if ( '' !== $this->webhook_url() ) {
			$result['webhook_sent'] = $this->send_webhook( $order );
			if ( ! $result['webhook_sent'] ) {
				$result['errors'][] = __( 'The order webhook could not be reached.', 'pizzatier' );
			}
		}

		// ── Discard decision ──────────────────────────────────────────────
		if ( ! OrderRoute::stores_record() ) {
			$delivered = $emailed || $result['webhook_sent'] || $result['cart_added'];

			// Keeping an undeliverable order is the lesser failure.
			$result['discard'] = $delivered;
		}

		/**
		 * Filter the outcome of routing an order.
		 *
		 * @since 2.1.0
		 *
		 * @param array $result The dispatch result.
		 * @param Order $order  The order that was routed.
		 * @param string $route The active route.
		 */
		return (array) apply_filters( 'pizzatier_order_dispatch_result', $result, $order, OrderRoute::get() );
	}

	// -------------------------------------------------------------------------
	// WooCommerce cart
	// -------------------------------------------------------------------------

	/**
	 * Push every line of the order into the WooCommerce cart.
	 *
	 * Prices are recalculated here rather than copied from the order record.
	 * The cart is a separate authority with its own grid lookup, and a line that
	 * the calculator declines to price must not silently enter the cart at the
	 * product's base price.
	 *
	 * @return array{added:bool,count:int,cart_url:string,error:string}
	 */
	private function add_to_cart( Order $order, int $product_id ): array {
		$out = [ 'added' => false, 'count' => 0, 'cart_url' => '', 'error' => '' ];

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			$out['error'] = __( 'WooCommerce is not available, so the pizza could not be added to the cart.', 'pizzatier' );
			return $out;
		}

		$product_id = OrderProduct::resolve( $product_id );
		if ( $product_id <= 0 ) {
			$out['error'] = __( 'This store has no pizza product set up, so the pizza could not be added to the cart.', 'pizzatier' );
			return $out;
		}

		if ( ! $this->ensure_cart() ) {
			$out['error'] = __( 'The cart could not be loaded. Please try again.', 'pizzatier' );
			return $out;
		}

		$grid       = new Grid();
		$calculator = new PriceCalculator( $grid );
		$sizes      = $grid->get_sizes( $product_id );
		$added_any  = false;

		foreach ( $order->get_items() as $item ) {
			if ( ! is_array( $item ) || empty( $item['layers'] ) ) {
				continue;
			}

			$size   = OrderPricing::resolve_size( $item, $sizes );
			$layers = OrderPricing::map_layers( $item['layers'] );

			if ( '' === $size || empty( $layers ) ) {
				continue;
			}

			$priced = $calculator->calculate( $product_id, $size, $layers );

			if ( is_wp_error( $priced ) ) {
				$out['error'] = $priced->get_error_message();
				continue;
			}

			$quantity = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );

			$cart_item_data = [
				OrderMeta::CART_SIZE         => $size,
				OrderMeta::CART_LAYERS       => $this->name_breakdown( $priced['breakdown'], $layers ),
				OrderMeta::CART_INPUT_LAYERS => $layers,
				OrderMeta::CART_TOTAL        => (float) $priced['total'],
				OrderMeta::CART_BASE_PRICE   => isset( $priced['base_price'] ) ? (float) $priced['base_price'] : 0.0,
				// Distinct configurations of the same product stay separate lines.
				OrderMeta::CART_KEY          => md5( $product_id . $size . wp_json_encode( $layers ) ),
			];

			$note = isset( $item['notes'] ) ? (string) $item['notes'] : '';
			if ( '' !== $note && (bool) pizzatier_get_option( 'enable_order_notes', false ) ) {
				$cart_item_data[ OrderMeta::CART_ORDER_NOTE ] = $note;
			}

			$key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );

			if ( false === $key ) {
				$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : [];
				if ( ! empty( $notices ) ) {
					$out['error'] = wp_strip_all_tags( $notices[0]['notice'] );
					wc_clear_notices();
				} elseif ( '' === $out['error'] ) {
					$out['error'] = __( 'Could not add the pizza to your cart.', 'pizzatier' );
				}
				continue;
			}

			$added_any = true;
		}

		$out['added']    = $added_any;
		$out['count']    = $added_any ? (int) WC()->cart->get_cart_contents_count() : 0;
		$out['cart_url'] = function_exists( 'wc_get_cart_url' ) ? (string) wc_get_cart_url() : '';

		if ( $added_any ) {
			$out['error'] = '';
		}

		return $out;
	}

	/**
	 * Make sure a cart object exists on this request.
	 *
	 * WooCommerce sets the cart up on `wp_loaded` for front-end requests, which
	 * includes admin-ajax, but a site that has disabled cart fragments or is
	 * mid-boot can still land here without one.
	 */
	private function ensure_cart(): bool {
		if ( isset( WC()->cart ) && WC()->cart instanceof \WC_Cart ) {
			return true;
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return isset( WC()->cart ) && WC()->cart instanceof \WC_Cart;
	}

	/**
	 * Merge display names into the calculator's priced breakdown, matching what
	 * CartIntegration stores so cart and order templates render identically.
	 *
	 * @param array $breakdown Priced rows from the calculator.
	 * @param array $layers    Mapped layers carrying the display names.
	 */
	private function name_breakdown( array $breakdown, array $layers ): array {
		$names = [];
		foreach ( $layers as $layer ) {
			$id = isset( $layer['layerId'] ) ? (string) $layer['layerId'] : '';
			if ( '' !== $id ) {
				$names[ $id ] = isset( $layer['layerName'] ) ? (string) $layer['layerName'] : '';
			}
		}

		$out = [];
		foreach ( $breakdown as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['layerId'] ) ? (string) $row['layerId'] : '';
			$row['layerName'] = ( isset( $names[ $id ] ) && '' !== $names[ $id ] )
				? $names[ $id ]
				: ucwords( str_replace( [ '-', '_' ], ' ', $id ) );
			$out[] = $row;
		}

		return $out;
	}

	private function checkout_url(): string {
		if ( ! function_exists( 'wc_get_checkout_url' ) ) {
			return '';
		}

		/**
		 * Filter where the customer lands after a straight-to-checkout order.
		 *
		 * @since 2.1.0
		 *
		 * @param string $url The WooCommerce checkout URL.
		 */
		return (string) apply_filters( 'pizzatier_order_checkout_redirect', wc_get_checkout_url() );
	}

	// -------------------------------------------------------------------------
	// Webhook
	// -------------------------------------------------------------------------

	private function webhook_url(): string {
		$url = trim( (string) OrderSettings::get( 'webhook_url' ) );
		return ( '' !== $url && wp_http_validate_url( $url ) ) ? $url : '';
	}

	/**
	 * POST the order to the configured endpoint.
	 *
	 * The body is signed with HMAC-SHA256 when a secret is set, so the receiver
	 * can prove the payload came from this site and was not altered on the way.
	 *
	 * @return bool Whether the endpoint accepted the delivery.
	 */
	private function send_webhook( Order $order ): bool {
		$url = $this->webhook_url();
		if ( '' === $url ) {
			return false;
		}

		$payload = $this->webhook_payload( $order );
		$body    = wp_json_encode( $payload );

		if ( false === $body ) {
			return false;
		}

		$headers = [
			'Content-Type' => 'application/json; charset=utf-8',
			'Accept'       => 'application/json',
		];

		$secret = (string) OrderSettings::get( 'webhook_secret' );
		if ( '' !== $secret ) {
			$headers[ self::SIGNATURE_HEADER ] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}

		$response = wp_remote_post(
			$url,
			[
				'timeout'     => 8,
				'redirection' => 2,
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $body,
				'user-agent'  => 'PizzaTier/' . PIZZATIER_VERSION . '; ' . home_url(),
			]
		);

		if ( is_wp_error( $response ) ) {
			/**
			 * Fires when an order webhook could not be delivered.
			 *
			 * @since 2.1.0
			 *
			 * @param \WP_Error $error The transport error.
			 * @param Order     $order The order that failed to send.
			 */
			do_action( 'pizzatier_order_webhook_failed', $response, $order );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return ( $code >= 200 && $code < 300 );
	}

	/**
	 * The JSON body sent to the webhook.
	 *
	 * Deliberately excludes internal staff notes, which are staff-screen only,
	 * and the raw IP address, which the store already holds and a third-party
	 * endpoint has no need for.
	 */
	private function webhook_payload( Order $order ): array {
		$source = $order->get_source();

		$payload = [
			'event'     => 'order.placed',
			'sent_at'   => gmdate( 'c' ),
			'site'      => [
				'name' => wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
				'url'  => home_url(),
			],
			'route'     => OrderRoute::get(),
			'order'     => [
				'id'            => $order->get_id(),
				'number'        => $order->get_number(),
				'status'        => $order->get_status(),
				'customer'      => $order->get_customer(),
				'fulfillment'   => $order->get_fulfillment(),
				'items'         => $order->get_items(),
				'totals'        => $order->get_totals(),
				'customer_note' => $order->get_customer_note(),
				'placed_at'     => gmdate( 'c' ),
			],
			'source'    => [
				'origin'   => isset( $source['origin'] ) ? $source['origin'] : '',
				'page_id'  => isset( $source['page_id'] ) ? (int) $source['page_id'] : 0,
				'url'      => isset( $source['url'] ) ? $source['url'] : '',
				'template' => isset( $source['template'] ) ? $source['template'] : '',
			],
		];

		/**
		 * Filter the order webhook payload.
		 *
		 * @since 2.1.0
		 *
		 * @param array $payload The body about to be sent.
		 * @param Order $order   The order being sent.
		 */
		return (array) apply_filters( 'pizzatier_order_webhook_payload', $payload, $order );
	}
}
