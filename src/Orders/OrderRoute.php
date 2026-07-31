<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Where an order goes when the customer places it.
 *
 * A store can hand the pizza to WooCommerce as a cart item, record it as a
 * PizzaTier order, do both from a single click, or send it out by email and
 * webhook without keeping a record at all.
 *
 * Relationship to ActionBarMode
 * -----------------------------
 * ActionBarMode answered a narrower question — which bar draws itself in the
 * builder's action-bar area. That is a consequence of the route rather than a
 * separate decision, so from 2.1.0 the route is the source of truth and
 * ActionBarMode derives from it. The old option is still read as an input for
 * sites that never chose a route, and the old filter still applies, so nothing
 * set programmatically stops working.
 *
 * One behaviour did change deliberately. Under ActionBarMode, "both" meant two
 * bars with two buttons and let the *customer* pick the destination. Under
 * OrderRoute, BOTH means one button that records the order and puts it in the
 * cart, and the *store* picks the destination. Sites upgrading from a stored
 * `action_bar_mode` of "both" get the new single-button behaviour.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.1.0
 */
final class OrderRoute {

	/** Settings key, under OrderSettings::PREFIX. */
	const KEY = 'route';

	/** Add to the WooCommerce cart. Customer keeps shopping. */
	const WOOCOMMERCE = 'woocommerce';

	/** Add to the WooCommerce cart, then go straight to checkout. */
	const WOO_CHECKOUT = 'woocommerce_checkout';

	/** Record a PizzaTier order. No cart, no payment step. */
	const ORDERS = 'orders';

	/** One click: record a PizzaTier order *and* add it to the cart. */
	const BOTH = 'both';

	/** Email and/or webhook only. Nothing is kept in the database. */
	const NOTIFY = 'notify';

	/**
	 * Every valid route.
	 *
	 * @return string[]
	 */
	public static function choices(): array {
		return [
			self::ORDERS,
			self::WOOCOMMERCE,
			self::WOO_CHECKOUT,
			self::BOTH,
			self::NOTIFY,
		];
	}

	/**
	 * Admin-facing labels.
	 *
	 * @return array<string,string> route => label
	 */
	public static function labels(): array {
		return [
			self::ORDERS       => __( 'Pizza order list — record the order in WordPress', 'pizzatier' ),
			self::WOOCOMMERCE  => __( 'WooCommerce cart — add the pizza and keep shopping', 'pizzatier' ),
			self::WOO_CHECKOUT => __( 'WooCommerce checkout — add the pizza and go straight to payment', 'pizzatier' ),
			self::BOTH         => __( 'Both — record the order and add it to the cart', 'pizzatier' ),
			self::NOTIFY       => __( 'Notify only — send the ticket, keep no record', 'pizzatier' ),
		];
	}

	/**
	 * Longer descriptions for the settings screen.
	 *
	 * @return array<string,string> route => description
	 */
	public static function descriptions(): array {
		return [
			self::ORDERS       => __( 'Suits pay-on-collection and phone-order stores. No cart and no payment step.', 'pizzatier' ),
			self::WOOCOMMERCE  => __( 'The standard WooCommerce flow. Good when customers order more than one pizza, or pizza alongside other products.', 'pizzatier' ),
			self::WOO_CHECKOUT => __( 'Skips the cart page. Fewer clicks for stores that mostly sell one pizza at a time.', 'pizzatier' ),
			self::BOTH         => __( 'The kitchen gets the ticket immediately and the customer still pays through WooCommerce.', 'pizzatier' ),
			self::NOTIFY       => __( 'The order is emailed and posted to your webhook, then discarded. Nothing personal is stored.', 'pizzatier' ),
		];
	}

	public static function is_valid( string $route ): bool {
		return in_array( $route, self::choices(), true );
	}

	// -------------------------------------------------------------------------
	// Resolution
	// -------------------------------------------------------------------------

	/**
	 * The route for this request.
	 *
	 * Falls back through: explicit choice → legacy `action_bar_mode` → the
	 * conditional default, then degrades WooCommerce routes when WooCommerce
	 * is not active so the button is never left with nowhere to go.
	 */
	public static function get(): string {
		$route = (string) OrderSettings::get( self::KEY );

		if ( '' === $route || ! self::is_valid( $route ) ) {
			$route = self::from_legacy_option();
		}

		if ( '' === $route ) {
			$route = self::default_route();
		}

		if ( self::requires_woocommerce( $route ) && ! self::woocommerce_active() ) {
			// A cart route with no cart to add to would fail on every click.
			// Recording the order at least keeps the store taking orders.
			$route = self::ORDERS;
		}

		/**
		 * Filter the resolved order route.
		 *
		 * @since 2.1.0
		 *
		 * @param string $route One of orders | woocommerce | woocommerce_checkout | both | notify.
		 */
		$route = (string) apply_filters( 'pizzatier_order_route', $route );

		return self::is_valid( $route ) ? $route : self::ORDERS;
	}

	/**
	 * Read the pre-2.1.0 action-bar setting.
	 *
	 * Deliberately reads the raw option rather than calling ActionBarMode,
	 * which now derives from this class and would recurse.
	 *
	 * @return string A route, or '' when the legacy option carries no choice.
	 */
	private static function from_legacy_option(): string {
		$stored = get_option( 'pizzatier_options', [] );
		$legacy = ( is_array( $stored ) && isset( $stored[ ActionBarMode::KEY ] ) )
			? (string) $stored[ ActionBarMode::KEY ]
			: '';

		if ( '' === $legacy ) {
			// The older discrete option. Only 'woocommerce' ever carried meaning.
			$discrete = (string) OrderSettings::get( ActionBarMode::LEGACY_KEY );
			$legacy   = ( ActionBarMode::WOOCOMMERCE === $discrete ) ? ActionBarMode::WOOCOMMERCE : '';
		}

		$map = [
			ActionBarMode::WOOCOMMERCE => self::WOOCOMMERCE,
			ActionBarMode::ORDERS      => self::ORDERS,
			ActionBarMode::BOTH        => self::BOTH,
		];

		return isset( $map[ $legacy ] ) ? $map[ $legacy ] : '';
	}

	/**
	 * The default for a site that has never chosen.
	 *
	 * Presence of the cart-and-pricing options row is the signal, exactly as it
	 * was for ActionBarMode: a site that configured pricing was seeing an Add to
	 * Cart button and must keep seeing one, while a site that only ever ran the
	 * builder was seeing the native order bar.
	 */
	private static function default_route(): string {
		$stored = get_option( 'pizzatier_options', false );
		return ( false !== $stored ) ? self::WOOCOMMERCE : self::ORDERS;
	}

	private static function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	// -------------------------------------------------------------------------
	// Capabilities
	//
	// Everything downstream asks these questions rather than comparing route
	// strings, so adding a route later means touching this class only.
	// -------------------------------------------------------------------------

	/** Whether this route needs WooCommerce to function. */
	public static function requires_woocommerce( string $route ): bool {
		return in_array( $route, [ self::WOOCOMMERCE, self::WOO_CHECKOUT, self::BOTH ], true );
	}

	/** Whether a pizzatier_order record is kept. */
	public static function stores_record(): bool {
		return in_array( self::get(), [ self::ORDERS, self::BOTH ], true );
	}

	/** Whether the pizza is pushed into the WooCommerce cart. */
	public static function adds_to_cart(): bool {
		return in_array( self::get(), [ self::WOOCOMMERCE, self::WOO_CHECKOUT, self::BOTH ], true );
	}

	/** Whether the customer is sent to checkout once the pizza is in the cart. */
	public static function redirects_to_checkout(): bool {
		return self::WOO_CHECKOUT === self::get();
	}

	/** Whether the order is sent out and then discarded. */
	public static function notifies_only(): bool {
		return self::NOTIFY === self::get();
	}

	/**
	 * Whether PizzaTier's own order bar and checkout panel own the action area.
	 *
	 * The panel collects the name, phone and fulfilment details that a route
	 * without a WooCommerce checkout has no other way to ask for. The cart-only
	 * routes leave all of that to WooCommerce.
	 */
	public static function uses_native_bar(): bool {
		return in_array( self::get(), [ self::ORDERS, self::BOTH, self::NOTIFY ], true );
	}

	/** Whether the WooCommerce Add to Cart bar owns the action area. */
	public static function uses_woocommerce_bar(): bool {
		return in_array( self::get(), [ self::WOOCOMMERCE, self::WOO_CHECKOUT ], true );
	}
}
