<?php
namespace PizzaTier\Api;

use PizzaTier\Builder\PizzaBuilder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier REST API — Pizza Stack Endpoint
 *
 * POST /wp-json/pizzatier/v1/render
 * GET  /wp-json/pizzatier/v1/layer-url?type=crust&slug=thin-crust
 *
 * Both endpoints are intentionally unauthenticated — they render builder
 * markup / image URLs that are visible to all site visitors — but they are
 * disabled by default (Settings → Advanced → REST API) and protected by a
 * lightweight per-IP rate limit so they can't be used as a cheap
 * unauthenticated way to hammer the database. The /render response is also
 * optionally cached (Settings → Advanced → REST cache TTL).
 *
 * PHP usage (other plugins):
 *   $html = \PizzaTier\Builder\PizzaBuilder::render_pizza_stack([...]);
 *   $url  = \PizzaTier\Builder\PizzaBuilder::get_layer_url('crust','thin-crust');
 *
 * JS usage:
 *   window.PizzaTierAPI.renderPizza({crust:'thin',sauce:'tomato'})
 *     .then(html => el.innerHTML = html);
 */
class PizzaRestApi {

	public function register_routes(): void {
		register_rest_route( 'pizzatier/v1', '/render', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'render_pizza' ],
			// Public by design (see class docblock); gated by a per-IP rate limit.
			'permission_callback' => [ $this, 'check_public_access' ],
			'args'                => [
				'crust'    => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'sauce'    => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'cheese'   => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'drizzle'  => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'cut'      => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'preset'   => [ 'type' => 'string',              'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
				'toppings' => [ 'type' => [ 'array', 'string' ], 'default' => [], 'sanitize_callback' => static function( $val ) {
					if ( is_array( $val ) ) { return array_map( 'sanitize_text_field', $val ); }
					return sanitize_text_field( (string) $val );
				} ],
			],
		] );

		register_rest_route( 'pizzatier/v1', '/layer-url', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_layer_url' ],
			// Public by design (see class docblock); gated by a per-IP rate limit.
			'permission_callback' => [ $this, 'check_public_access' ],
			'args'                => [
				'type' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				'slug' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );
	}

	/**
	 * Permission gate for the public endpoints.
	 *
	 * The endpoints are unauthenticated on purpose, so this is not an
	 * identity check — it is a lightweight per-IP, fixed-window rate limit
	 * that protects against an unauthenticated request flood. Limits are
	 * filterable; set either to 0 to disable.
	 *
	 *   pizzatier_rest_rate_limit   — max requests per window (default 120)
	 *   pizzatier_rest_rate_window  — window length in seconds (default 60)
	 *
	 * Note: the counter uses a transient. On sites with a persistent object
	 * cache this never touches the database; without one it is a single
	 * indexed write per window — still far cheaper than the render path.
	 *
	 * @return true|\WP_Error
	 */
	public function check_public_access( \WP_REST_Request $request ) {
		$max    = (int) apply_filters( 'pizzatier_rest_rate_limit', 120 );
		$window = (int) apply_filters( 'pizzatier_rest_rate_window', 60 );

		// Limiter disabled by configuration.
		if ( $max <= 0 || $window <= 0 ) {
			return true;
		}

		$ip = $this->client_ip();
		// If we can't identify the caller, don't block legitimate traffic.
		if ( '' === $ip ) {
			return true;
		}

		$key   = 'pizzatier_rl_' . md5( $ip );
		$now   = time();
		$entry = get_transient( $key );

		// New (or expired) window — start a fresh count.
		if ( ! is_array( $entry ) || ! isset( $entry['start'], $entry['count'] ) || ( $now - (int) $entry['start'] ) >= $window ) {
			set_transient( $key, [ 'start' => $now, 'count' => 1 ], $window );
			return true;
		}

		if ( (int) $entry['count'] >= $max ) {
			return new \WP_Error(
				'pizzatier_rate_limited',
				__( 'Too many requests. Please slow down and try again shortly.', 'pizzatier' ),
				[ 'status' => 429 ]
			);
		}

		// Increment within the current window. We anchor the window with the
		// stored 'start' timestamp, so resetting the transient TTL here to the
		// remaining window time keeps the window boundary fixed.
		$entry['count'] = (int) $entry['count'] + 1;
		$remaining      = max( 1, $window - ( $now - (int) $entry['start'] ) );
		set_transient( $key, $entry, $remaining );

		return true;
	}

	/**
	 * Resolve the caller's IP for rate limiting.
	 *
	 * Uses REMOTE_ADDR only. X-Forwarded-For and friends are client-supplied
	 * and trivially spoofable, so they must not be trusted for a security
	 * control. Sites behind a trusted proxy can adjust REMOTE_ADDR upstream
	 * or short-circuit the limiter via the rate-limit filters.
	 */
	private function client_ip(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip  = filter_var( (string) $raw, FILTER_VALIDATE_IP );
		return $ip ? (string) $ip : '';
	}

	public function render_pizza( \WP_REST_Request $request ): \WP_REST_Response {
		$toppings = $request->get_param( 'toppings' );
		if ( is_array( $toppings ) ) {
			$toppings = implode( ',', array_map( 'sanitize_text_field', $toppings ) );
		}

		$layers = [
			'crust'    => (string) $request->get_param( 'crust' ),
			'sauce'    => (string) $request->get_param( 'sauce' ),
			'cheese'   => (string) $request->get_param( 'cheese' ),
			'toppings' => (string) $toppings,
			'drizzle'  => (string) $request->get_param( 'drizzle' ),
			'cut'      => (string) $request->get_param( 'cut' ),
			'preset'   => (string) $request->get_param( 'preset' ),
		];

		// Optional response cache. TTL of 0 (the default) disables it. Keyed
		// on the normalised layer set so identical requests skip the DB work
		// of resolving every slug to a post and reading its image field.
		$ttl = (int) get_option( 'pizzatier_setting_adv_rest_cache_ttl', 0 );
		if ( $ttl > 0 ) {
			$cache_key = 'pizzatier_render_' . md5( (string) wp_json_encode( $layers ) );
			$cached    = get_transient( $cache_key );
			if ( is_string( $cached ) ) {
				return new \WP_REST_Response( [ 'html' => $cached, 'cached' => true ], 200 );
			}
			$html = PizzaBuilder::render_pizza_stack( $layers );
			set_transient( $cache_key, $html, $ttl );
			return new \WP_REST_Response( [ 'html' => $html, 'cached' => false ], 200 );
		}

		$html = PizzaBuilder::render_pizza_stack( $layers );
		return new \WP_REST_Response( [ 'html' => $html ], 200 );
	}

	public function get_layer_url( \WP_REST_Request $request ): \WP_REST_Response {
		$url = PizzaBuilder::get_layer_url(
			(string) $request->get_param( 'type' ),
			(string) $request->get_param( 'slug' )
		);
		return new \WP_REST_Response( [ 'url' => $url ], 200 );
	}
}
