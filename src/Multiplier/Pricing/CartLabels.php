<?php
/**
 * WooCommerce cart / order presentation of added portions.
 *
 * PizzaTier merges display names into its price breakdown by layer ID, so
 * after the rewrite every portion of "pepperoni" would show the same name.
 * This class re-applies the per-portion names by occurrence order (every
 * PizzaTier pricing engine preserves the relative order of same-ID layers),
 * swaps synthetic pricing IDs back to the real topping ID, and records a
 * short "Extra portions" summary for the cart, order and kitchen.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Pricing;

use PizzaTier\Commerce\WooCommerce\OrderMeta;
use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CartLabels {

	/** Cart item key holding [ slug => level ]. */
	const CART_PORTIONS = 'pizzatier_portions';

	/** Order item meta key (hidden) holding the same map. */
	const ORDER_META = '_pizzatier_mult_portions';

	public function register(): void {
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'fix_cart_item' ], 20, 2 );
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_in_cart' ], 20, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_to_order_item' ], 20, 4 );
	}

	/**
	 * @param array $data       Cart item data.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public function fix_cart_item( $data, $product_id ) {
		if ( ! is_array( $data ) || ! class_exists( OrderMeta::class ) ) {
			return $data;
		}
		$input = $data[ OrderMeta::CART_INPUT_LAYERS ] ?? null;
		if ( ! is_array( $input ) || empty( $input ) ) {
			return $data;
		}

		$levels = self::levels_from_layers( $input );
		if ( empty( $levels ) ) {
			return $data;
		}

		// Restore real topping IDs on the stored input layers.
		foreach ( $input as $i => $layer ) {
			if ( isset( $layer['layerPostId'] ) ) {
				$input[ $i ]['layerPostId'] = SyntheticGrid::decode( (int) $layer['layerPostId'] );
			}
		}
		$data[ OrderMeta::CART_INPUT_LAYERS ] = $input;

		// Re-apply names to the priced breakdown by occurrence.
		if ( isset( $data[ OrderMeta::CART_LAYERS ] ) && is_array( $data[ OrderMeta::CART_LAYERS ] ) ) {
			$names = [];
			foreach ( $input as $layer ) {
				$names[ (string) ( $layer['layerId'] ?? '' ) ][] = (string) ( $layer['layerName'] ?? '' );
			}
			$seen = [];
			foreach ( $data[ OrderMeta::CART_LAYERS ] as $i => $entry ) {
				$lid = (string) ( $entry['layerId'] ?? '' );
				if ( ! isset( $levels[ $lid ] ) ) {
					continue;
				}
				$k            = $seen[ $lid ] ?? 0;
				$seen[ $lid ] = $k + 1;
				if ( isset( $names[ $lid ][ $k ] ) && '' !== $names[ $lid ][ $k ] ) {
					$data[ OrderMeta::CART_LAYERS ][ $i ]['layerName'] = $names[ $lid ][ $k ];
				}
				if ( isset( $entry['layerPostId'] ) ) {
					$data[ OrderMeta::CART_LAYERS ][ $i ]['layerPostId'] = SyntheticGrid::decode( (int) $entry['layerPostId'] );
				}
			}
		}

		$data[ self::CART_PORTIONS ] = $levels;
		return $data;
	}

	/**
	 * Count portions per topping slug in a stored layer list.
	 *
	 * @return array<string,int> Only toppings with 2+ portions.
	 */
	public static function levels_from_layers( array $layers ): array {
		$count = [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$type = strtolower( (string) ( $layer['layerType'] ?? $layer['type'] ?? '' ) );
			if ( '' !== $type && 'topping' !== $type && 'toppings' !== $type ) {
				continue;
			}
			$lid = (string) ( $layer['layerId'] ?? $layer['slug'] ?? '' );
			if ( '' === $lid ) {
				continue;
			}
			$count[ $lid ] = ( $count[ $lid ] ?? 0 ) + 1;
		}
		return array_filter(
			$count,
			function ( $c ) {
				return $c > 1;
			}
		);
	}

	/**
	 * Human summary, e.g. "Pepperoni (Double), Mushrooms (Triple)".
	 *
	 * @param array<string,int> $levels Slug → portion count.
	 */
	public static function summary( array $levels ): string {
		$parts = [];
		foreach ( $levels as $slug => $level ) {
			$conf    = Config::topping( (string) $slug );
			$title   = $conf ? (string) $conf['title'] : ucwords( str_replace( [ '-', '_' ], ' ', (string) $slug ) );
			$what    = ( $conf && 'layers' === $conf['method'] )
				/* translators: %d: number of layers. */
				? sprintf( _n( '%d layer', '%d layers', (int) $level, 'pizzatier' ), (int) $level )
				: Config::level_label( (int) $level );
			$parts[] = sprintf( '%1$s (%2$s)', $title, $what );
		}
		return implode( ', ', $parts );
	}

	/**
	 * @param array $item_data Displayed rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_in_cart( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_PORTIONS ] ) || ! is_array( $cart_item[ self::CART_PORTIONS ] ) ) {
			return $item_data;
		}
		$item_data[] = [
			'key'   => __( 'Extra portions', 'pizzatier' ),
			'value' => esc_html( self::summary( $cart_item[ self::CART_PORTIONS ] ) ),
		];
		return $item_data;
	}

	/**
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart key.
	 * @param array                  $values        Cart item.
	 * @param \WC_Order              $order         Order.
	 */
	public function save_to_order_item( $item, $cart_item_key, $values, $order ): void {
		if ( empty( $values[ self::CART_PORTIONS ] ) || ! is_array( $values[ self::CART_PORTIONS ] ) ) {
			return;
		}
		$item->add_meta_data( self::ORDER_META, $values[ self::CART_PORTIONS ], true );
		$item->add_meta_data( __( 'Extra portions', 'pizzatier' ), self::summary( $values[ self::CART_PORTIONS ] ), true );
	}
}
