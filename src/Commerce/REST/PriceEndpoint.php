<?php
/**
 * REST API endpoint: calculate and verify pizza price.
 *
 * Route:  POST /wp-json/pizzatier/v1/calculate-price
 *
 * Request JSON body:
 * {
 *   "product_id": 123,
 *   "size":       "Large",
 *   "layers":     [
 *     { "layerId": "sauce-tomato", "fraction": "Whole" },
 *     { "layerId": "topping-pepperoni", "fraction": "Half" }
 *   ]
 * }
 *
 * Success response (200):
 * {
 *   "total":           14.50,
 *   "total_formatted": "14.50",
 *   "currency_symbol": "$",
 *   "size":            "Large",
 *   "breakdown":       [ { "layerId": "...", "fraction": "...", "price": 7.00, "price_formatted": "7.00" }, … ],
 *   "layer_count":     2
 * }
 *
 * Error response (4xx):
 * { "code": "pizzatier_commerce_*", "message": "…" }
 *
 * Security: The route is intentionally public — unauthenticated guests must be
 * able to price-check a pizza before adding it to the cart. A wp_rest nonce IS
 * generated in FrontendEmbed and sent as the X-WP-Nonce header by the JS, which
 * gives cookie-authenticated users an elevated context, but the nonce is NOT
 * enforced for unauthenticated callers (WP core behaviour). All pricing data is
 * read-only and server-authoritative; no price submitted by the client is trusted.
 *
 * @package PizzaTier\Commerce\REST
 */

namespace PizzaTier\Commerce\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceCalculator;
use PizzaTier\Commerce\PriceGrid\Grid;

class PriceEndpoint {

	const REST_NAMESPACE = 'pizzatier/v1';
	const ROUTE     = '/calculate-price';

	/** @var PriceCalculator */
	private PriceCalculator $calculator;

	public function __construct( PriceCalculator $calculator ) {
		$this->calculator = $calculator;
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::CREATABLE, // POST
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => $this->get_args(),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Permission
	// -------------------------------------------------------------------------

	/**
	 * Allow any visitor (logged-in or not) to call the endpoint.
	 *
	 * Pizza products are public and guests must be able to calculate a price
	 * before adding to cart. No write operation is performed — the endpoint
	 * only returns a computed price derived from admin-configured grid data.
	 *
	 * Note: when the JS sends X-WP-Nonce, WP core automatically verifies it
	 * and sets is_user_logged_in() for that request. The nonce is NOT required
	 * for unauthenticated callers — that is intentional and expected.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function check_permission( \WP_REST_Request $request ) {
		// "Enable REST price endpoint" (Cart & Pricing → Advanced). The cart
		// skips its pre-check when this is off; add-to-cart still verifies.
		if ( ! (bool) pizzatier_get_option( 'enable_rest_price', true ) ) {
			return false;
		}

		/**
		 * Filter the per-client limit on the public price endpoint (2.4.3).
		 *
		 * The builder re-quotes on each change, so the default is generous.
		 *
		 * @param int $max    Requests per window (default 240; 0 disables).
		 * @param int $window Window in seconds (default 60).
		 */
		$max    = (int) apply_filters( 'pizzatier_price_rate_limit', 240 );
		$window = (int) apply_filters( 'pizzatier_price_rate_window', 60 );
		if ( ! \PizzaTier\Core\RateLimiter::hit( 'price', $max, $window ) ) {
			return new \WP_Error( 'pizzatier_rate_limited', __( 'Too many requests. Please slow down and try again shortly.', 'pizzatier' ), [ 'status' => 429 ] );
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Handler
	// -------------------------------------------------------------------------

	/**
	 * Handle the calculate-price request.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'product_id' );
		$size       = (string) $request->get_param( 'size' );
		$layers     = (array) $request->get_param( 'layers' );

		$result = $this->calculator->calculate( $product_id, $size, $layers );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => $status ]
			);
		}

		return rest_ensure_response( $result );
	}

	// -------------------------------------------------------------------------
	// Argument schema
	// -------------------------------------------------------------------------

	/**
	 * REST argument definitions with sanitisation and validation callbacks.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_args(): array {
		return [
			'product_id' => [
				// Schema checks (types, lengths, maxItems) run only with an explicit
				// validator when a sanitize_callback is set (2.4.3).
				'validate_callback' => 'rest_validate_request_arg',
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'description'       => __( 'WooCommerce product ID.', 'pizzatier' ),
			],
			'size'       => [
				// Schema checks (types, lengths, maxItems) run only with an explicit
				// validator when a sanitize_callback is set (2.4.3).
				'validate_callback' => 'rest_validate_request_arg',
				'required'          => true,
				'type'              => 'string',
				'minLength'         => 1,
				'maxLength'         => 40,
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Selected pizza size label.', 'pizzatier' ),
			],
			'layers'     => [
				// Schema checks (types, lengths, maxItems) run only with an explicit
				// validator when a sanitize_callback is set (2.4.3).
				'validate_callback' => 'rest_validate_request_arg',
				'required'          => true,
				'type'              => 'array',
				// Bounded before any lookup is done (2.4.3).
				'maxItems'          => 100,
				'items'             => [
					'type'       => 'object',
					'properties' => [
						'layerId'      => [ 'type' => 'string', 'minLength' => 1, 'maxLength' => 200 ],
						'fraction'     => [ 'type' => 'string', 'minLength' => 1, 'maxLength' => 40 ],
						'layerType'    => [ 'type' => 'string', 'maxLength' => 40 ],
						'layerPostId'  => [ 'type' => 'integer', 'minimum' => 0 ],
						'portion'      => [ 'type' => 'string', 'maxLength' => 40 ],
					],
					'required'   => [ 'layerId', 'fraction' ],
				],
				'sanitize_callback' => [ $this, 'sanitize_layers' ],
				'description'       => __( 'Array of selected layers with their coverage fractions.', 'pizzatier' ),
			],
		];
	}

	/**
	 * Sanitise each layer entry in the layers array.
	 *
	 * @param array $layers
	 * @return array
	 */
	public function sanitize_layers( array $layers ): array {
		$clean = [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$clean[] = [
				'layerId'     => sanitize_text_field( (string) ( $layer['layerId']     ?? '' ) ),
				'fraction'    => sanitize_text_field( (string) ( $layer['fraction']    ?? '' ) ),
				'layerType'   => sanitize_text_field( (string) ( $layer['layerType']   ?? '' ) ),
				'layerPostId' => absint( $layer['layerPostId'] ?? 0 ),
				// The specific portion decides the price column (2.4.0: it was
				// dropped here, so this quote could differ from add-to-cart).
				'portion'     => sanitize_text_field( (string) ( $layer['portion'] ?? '' ) ),
			];
		}
		return $clean;
	}
}
