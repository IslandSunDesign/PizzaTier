<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Single source of truth for the native ordering feature's settings.
 *
 * Every option is read through get() so defaults live in exactly one place and
 * the Settings screen (Phase 3) and the front end can never drift apart.
 *
 * No dependency on PizzaTier or WooCommerce.
 */
class OrderSettings {

	/** Option name prefix. */
	const PREFIX = 'pizzatier_setting_orders_';

	/**
	 * key => default value.
	 */
	private static function defaults(): array {
		return [
			// Master switch for the whole ordering feature.
			'enabled'            => 'yes',

			// Legacy. Superseded in 2.0.0 by ActionBarMode, which resolves the
			// three-way choice between the WooCommerce Add to Cart bar, the
			// native order bar, and both. Still honoured as an input when no
			// explicit choice has been stored — see OrderRoute::get() — so
			// sites that set this programmatically keep working.
			'bar_mode'           => 'orders',

			// Where an order goes when the customer places it. Empty means the
			// site has never chosen, and OrderRoute derives a route from the
			// pre-2.1.0 settings instead. See PizzaTier\Orders\OrderRoute.
			'route'              => '',

			// WooCommerce product the cart routes add to when the builder is
			// not embedded in a product page. 0 means none configured.
			'cart_product_id'    => 0,

			// Endpoint every placed order is POSTed to. Empty disables it.
			'webhook_url'        => '',

			// Shared secret. When set, the webhook body is signed with
			// HMAC-SHA256 so the receiver can verify it.
			'webhook_secret'     => '',

			// Call-to-action label. Empty falls back to a translated default.
			'button_label'       => '',

			// Which contact fields the customer must supply.
			'require_name'       => 'yes',
			'require_phone'      => 'yes',
			'require_email'      => 'no',

			// Only accept orders from logged-in users.
			'login_required'     => 'no',

			// Enabled fulfilment methods, in display order.
			'fulfillment'        => [ 'pickup', 'delivery' ],

			// Customer-facing notes / special instructions.
			'notes_enabled'      => 'yes',
			'note_placeholder'   => '',
			'note_maxlength'     => 500,

			// Quantity stepper.
			'quantity_enabled'   => 'yes',
			'max_quantity'       => 20,

			// Let the customer pick a size when Size posts exist.
			'size_enabled'       => 'yes',

			// Ask for a requested pickup / delivery time.
			'request_time'       => 'yes',

			// Abuse control: max submissions per IP per hour. 0 disables.
			'rate_limit'         => 10,

			// Notification email to the store on each new order.
			'notify_admin'       => 'yes',
			'admin_email'        => '',

			// Shown after a successful submission. Empty uses a default.
			'confirm_message'    => '',

			// Status assigned to newly submitted orders.
			'initial_status'     => OrderStatuses::DEFAULT_STATUS,

			// Data retention. After this many months an order's personal
			// fields are cleared automatically, keeping the transaction record.
			// 0 disables the sweep.
			'retention_months'   => 0,
		];
	}

	/**
	 * Read one setting, falling back to its default.
	 *
	 * @param string $key     Setting key without the option prefix.
	 * @param mixed  $default Optional explicit default.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$defaults = self::defaults();
		if ( null === $default ) {
			$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		}

		$value = get_option( self::PREFIX . $key, $default );

		/**
		 * Filter one PizzaTier orders setting.
		 *
		 * @param mixed  $value Stored value.
		 * @param string $key   Setting key.
		 */
		return apply_filters( 'pizzatier_orders_setting', $value, $key );
	}

	/** Boolean convenience reader — treats 'yes' / '1' / true as true. */
	public static function is_on( string $key ): bool {
		$value = self::get( $key );
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( (string) $value, [ 'yes', '1', 'true', 'on' ], true );
	}

	/** Integer convenience reader. */
	public static function get_int( string $key ): int {
		return (int) self::get( $key );
	}

	/**
	 * The whole defaults map, for the Settings screen.
	 */
	public static function all_defaults(): array {
		return self::defaults();
	}

	/**
	 * Fulfilment methods the store actually offers, as key => label.
	 *
	 * Intersects the site's enabled list with the registered method map so a
	 * stale saved value can never produce an unselectable option.
	 *
	 * @return array<string,string>
	 */
	public static function enabled_fulfillment_methods(): array {
		$all     = Order::fulfillment_methods();
		$enabled = self::get( 'fulfillment' );
		if ( ! is_array( $enabled ) || empty( $enabled ) ) {
			$enabled = [ 'pickup' ];
		}

		$out = [];
		foreach ( $enabled as $key ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $all[ $key ] ) ) {
				$out[ $key ] = $all[ $key ];
			}
		}

		return empty( $out ) ? [ 'pickup' => $all['pickup'] ] : $out;
	}

	/** The call-to-action label for the order button. */
	public static function button_label(): string {
		$label = trim( (string) self::get( 'button_label' ) );
		return '' !== $label ? $label : __( 'Order Now', 'pizzatier' );
	}

	/** Placeholder text for the customer note field. */
	public static function note_placeholder(): string {
		$text = trim( (string) self::get( 'note_placeholder' ) );
		return '' !== $text ? $text : __( 'Any special requests?', 'pizzatier' );
	}

	/** Message shown after a successful submission. */
	public static function confirm_message(): string {
		$text = trim( (string) self::get( 'confirm_message' ) );
		return '' !== $text
			? $text
			: __( 'Thanks! Your order has been sent to the kitchen.', 'pizzatier' );
	}

	/** Address where new-order notifications are sent. */
	public static function admin_email(): string {
		$email = trim( (string) self::get( 'admin_email' ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$email = (string) get_option( 'admin_email' );
		}
		return $email;
	}

	/** Initial status for a newly submitted order. */
	public static function initial_status(): string {
		$status = (string) self::get( 'initial_status' );
		return OrderStatuses::is_valid( $status ) ? $status : OrderStatuses::DEFAULT_STATUS;
	}
}
