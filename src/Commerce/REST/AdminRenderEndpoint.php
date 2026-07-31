<?php
/**
 * REST API endpoint: render a pizza layer stack for the admin product-editor
 * live preview.
 *
 * Route:  POST /wp-json/pizzatier/v1/render
 *
 * Request JSON body (all optional):
 * {
 *   "crust":    "thin",
 *   "sauce":    "tomato",
 *   "cheese":   "mozzarella",
 *   "drizzle":  "garlic-oil",
 *   "cut":      "square",
 *   "preset":   "",
 *   "toppings": "pepperoni,mushroom"   // CSV or array
 * }
 *
 * Success response (200): { "html": "<…layer stack markup…>" }
 *
 * Why this exists
 * ---------------
 * The product-editor preview needs server-rendered layer HTML. The base
 * PizzaTier plugin exposes an equivalent public route (pizzatier/v1/render),
 * but that route is ONLY registered when the site owner opts into the base
 * REST API (Settings → Advanced), which ships disabled. Rather than force that
 * dependency on every merchant, this registers its own admin-only, capability-
 * gated route that calls the same underlying PizzaBuilder renderer directly.
 * This keeps the editor preview working out of the box.
 *
 * Security: unlike the public base route, this endpoint is restricted to users
 * who can edit products — it only ever runs inside the product editor.
 *
 * @package PizzaTier\Commerce\REST
 */

namespace PizzaTier\Commerce\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminRenderEndpoint {

	const REST_NAMESPACE = 'pizzatier/v1';
	const ROUTE          = '/render';

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

	/**
	 * Restrict to users who can edit products. This endpoint exists only to
	 * power the admin product-editor preview, so a public surface is neither
	 * needed nor desirable.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_products' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Render the pizza stack via the base PizzaBuilder renderer.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( \WP_REST_Request $request ) {
		if ( ! class_exists( '\\PizzaTier\\Builder\\PizzaBuilder' ) ) {
			return new \WP_Error(
				'pizzatier_commerce_base_missing',
				__( 'The PizzaTier base plugin is not available to render a preview.', 'pizzatier' ),
				[ 'status' => 500 ]
			);
		}

		$toppings = $request->get_param( 'toppings' );
		if ( is_array( $toppings ) ) {
			$toppings = implode( ',', array_map( 'sanitize_text_field', $toppings ) );
		}

		$html = \PizzaTier\Builder\PizzaBuilder::render_pizza_stack( [
			'crust'    => (string) $request->get_param( 'crust' ),
			'sauce'    => (string) $request->get_param( 'sauce' ),
			'cheese'   => (string) $request->get_param( 'cheese' ),
			'toppings' => (string) $toppings,
			'drizzle'  => (string) $request->get_param( 'drizzle' ),
			'cut'      => (string) $request->get_param( 'cut' ),
			'preset'   => (string) $request->get_param( 'preset' ),
		] );

		return new \WP_REST_Response( [ 'html' => (string) $html ], 200 );
	}

	/**
	 * REST argument schema — mirrors the base render route so the same JS
	 * payload works against either endpoint.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_args(): array {
		$str = [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ];

		return [
			'crust'    => $str,
			'sauce'    => $str,
			'cheese'   => $str,
			'drizzle'  => $str,
			'cut'      => $str,
			'preset'   => $str,
			'toppings' => [
				'type'              => [ 'array', 'string' ],
				'default'           => [],
				'sanitize_callback' => static function ( $val ) {
					if ( is_array( $val ) ) {
						return array_map( 'sanitize_text_field', $val );
					}
					return sanitize_text_field( (string) $val );
				},
			],
		];
	}
}
