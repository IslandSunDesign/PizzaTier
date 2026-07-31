<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Decides what appears in the builder's action-bar area.
 *
 * History
 * -------
 * Until 2.0.0 this decision was split across two settings that could disagree:
 *
 *   • `pizzatier_setting_orders_bar_mode` — two values, no UI, defaulting to
 *     "show my bar".
 *   • `pizzatier_options['action_bar_mode']` — three values, with the only
 *     user-facing control, defaulting to "WooCommerce only".
 *
 * 2.0.0 made the second one the source of truth and kept the first as a legacy
 * input.
 *
 * From 2.1.0 the source of truth moved again, to PizzaTier\Orders\OrderRoute.
 * Which bar draws itself is a consequence of where the order is going, not an
 * independent choice, and treating it as independent is how the two settings
 * drifted apart in the first place. This class survives as the derived view:
 * it answers "which bar renders", from the route.
 *
 * The constants, the option key and the `pizzatier_action_bar_mode` filter are
 * all unchanged, so third-party code written against this class keeps working.
 *
 * @since 2.0.0
 */
final class ActionBarMode {

	/** Settings key inside the `pizzatier_options` array. */
	const KEY = 'action_bar_mode';

	/** Legacy discrete option, honoured when no explicit choice is stored. */
	const LEGACY_KEY = 'bar_mode';

	/** Show only the WooCommerce Add to Cart bar. */
	const WOOCOMMERCE = 'woocommerce';

	/** Show only PizzaTier's native order bar. */
	const ORDERS = 'orders';

	/** Show both, WooCommerce first. */
	const BOTH = 'both';

	/** @return string[] */
	public static function choices(): array {
		return [ self::WOOCOMMERCE, self::ORDERS, self::BOTH ];
	}

	/**
	 * The resolved bar mode for this request, derived from the order route.
	 *
	 * OrderRoute::BOTH maps to ORDERS here rather than BOTH. Under the routing
	 * model a single button records the order and adds it to the cart, so only
	 * one bar is drawn — PizzaTier's, because it owns the panel that collects
	 * the customer's details. BOTH is still returned faithfully if a filter asks
	 * for it, which is what keeps pre-2.1.0 integrations working.
	 */
	public static function get(): string {
		$route = OrderRoute::get();

		$map = [
			OrderRoute::WOOCOMMERCE  => self::WOOCOMMERCE,
			OrderRoute::WOO_CHECKOUT => self::WOOCOMMERCE,
			OrderRoute::ORDERS       => self::ORDERS,
			OrderRoute::BOTH         => self::ORDERS,
			OrderRoute::NOTIFY       => self::ORDERS,
		];

		$mode = isset( $map[ $route ] ) ? $map[ $route ] : self::ORDERS;

		// A WooCommerce-only store that has since deactivated WooCommerce would
		// otherwise show nothing at all. OrderRoute already degrades for this,
		// but a filter on the route could reintroduce it.
		if ( self::WOOCOMMERCE === $mode && ! class_exists( 'WooCommerce' ) ) {
			$mode = self::ORDERS;
		}

		/**
		 * Filter the resolved builder action-bar mode.
		 *
		 * @since 2.0.0
		 *
		 * @param string $mode  One of woocommerce | orders | both.
		 * @param string $route The order route this was derived from.
		 */
		$mode = (string) apply_filters( 'pizzatier_action_bar_mode', $mode, $route );

		return in_array( $mode, self::choices(), true ) ? $mode : self::ORDERS;
	}

	/** Whether the WooCommerce Add to Cart bar should render. */
	public static function shows_woocommerce_bar(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		return in_array( self::get(), [ self::WOOCOMMERCE, self::BOTH ], true );
	}

	/** Whether PizzaTier's native order bar should render. */
	public static function shows_orders_bar(): bool {
		return in_array( self::get(), [ self::ORDERS, self::BOTH ], true );
	}
}
