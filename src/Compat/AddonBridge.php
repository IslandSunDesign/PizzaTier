<?php
namespace PizzaTier\Compat;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pricing / cart capability accessors.
 *
 * Origin
 * ------
 * This class was introduced when pricing, size grids and cart behaviour lived
 * in a separate premium plugin. PizzaTier asked for those capabilities through
 * filters and never named a premium class, so the dependency pointed one way
 * only. PizzaTier answered the filters.
 *
 * Status since 2.0.0
 * ------------------
 * PizzaTier merged into PizzaTier, so there is no longer anything external
 * to ask — the capabilities are built in. Each method now resolves PizzaTier's
 * own data directly and then passes the result through its filter, so the
 * filters survive as genuine extension points: a third party can still override
 * what PizzaTier resolved, rather than only filling a gap when nothing answered.
 *
 * Note the resulting change in filter semantics. Before 2.0.0 these filters
 * received null (or an empty array) and returning a value meant "I am supplying
 * this". They now receive PizzaTier's resolved value, so a callback that returns
 * early when the incoming value is non-null will never take effect. Callbacks
 * should inspect and override instead.
 *
 * The pzt_addon_setting() / pzt_addon_sizes() / pzt_has_pricing_addon() helpers
 * that wrap this class are retained and still route through here, so the shipped
 * checkout-bar templates — and any child-theme copy of one — keep working
 * unchanged and keep honouring the filters.
 */
class AddonBridge {

	/**
	 * Read a pricing / cart setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Value when the key is unset.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$value = pizzatier_get_option( $key, $default );

		/**
		 * Filter a pricing / cart setting.
		 *
		 * @since 1.17.0
		 * @since 2.0.0 Receives PizzaTier's resolved value rather than null.
		 *
		 * @param mixed  $value   The resolved value.
		 * @param string $key     Setting key.
		 * @param mixed  $default The default supplied by the caller.
		 */
		return apply_filters( 'pizzatier_addon_setting', $value, $key, $default );
	}

	/**
	 * Priced size options for the builder.
	 *
	 * PizzaTier's own Size content type is exposed separately through
	 * OrderCheckout::get_sizes(); this is the priced size grid attached to a
	 * product.
	 *
	 * @param int $product_id Optional product context.
	 * @return array
	 */
	public static function get_sizes( int $product_id = 0 ): array {
		$sizes = [];

		// Guarded so this still degrades gracefully if the pricing sources are
		// ever absent from a build.
		if ( class_exists( '\\PizzaTier\Commerce\PriceGrid\\Grid' ) ) {
			$grid   = new \PizzaTier\Commerce\PriceGrid\Grid();
			$result = $grid->get_sizes( $product_id );
			if ( is_array( $result ) ) {
				$sizes = $result;
			}
		}

		/**
		 * Filter the priced size options available in the builder.
		 *
		 * @since 1.17.0
		 * @since 2.0.0 Receives PizzaTier's resolved sizes rather than an empty array.
		 *
		 * @param array $sizes      Size rows.
		 * @param int   $product_id Product context, 0 when there is none.
		 */
		$sizes = apply_filters( 'pizzatier_addon_sizes', $sizes, $product_id );

		return is_array( $sizes ) ? $sizes : [];
	}

	/**
	 * Whether pricing features are available.
	 *
	 * Always true since 2.0.0, when pricing became part of PizzaTier. Retained
	 * because templates and third-party snippets branch on it; those branches
	 * can be simplified away at leisure.
	 *
	 * @return bool
	 */
	public static function has_pricing_addon(): bool {
		/**
		 * Filter whether pricing features are reported as available.
		 *
		 * @since 1.17.0
		 * @since 2.0.0 Receives true rather than false; pricing is built in.
		 *
		 * @param bool $active Whether pricing is available.
		 */
		return (bool) apply_filters( 'pizzatier_has_pricing_addon', true );
	}
}
