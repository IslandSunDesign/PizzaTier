<?php
/**
 * Expands added-portion layer IDs.
 *
 * The browser sends each added portion as a layer whose ID carries the
 * portion number ("pepperoni~2", "pepperoni~3"). expand() turns those into
 * repeats of the base topping placed directly after it — the order the
 * browser prices them in. Commerce\LayerResolver then validates the repeats
 * against the topping's maximum and chooses, on the server, the post ID each
 * portion is priced from and its display name.
 *
 * Portions for a topping that is not on the pizza are dropped here.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Pricing;

use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestRewriter {

	/**
	 * @param array $layers Client layers.
	 * @return array Layers with "slug~n" replaced by repeats of "slug".
	 */
	public static function expand( array $layers ): array {
		$extras = []; // slug → count of requested added portions.
		$any    = false;
		foreach ( $layers as $layer ) {
			$parsed = is_array( $layer ) ? Config::parse_extra_id( (string) ( $layer['layerId'] ?? '' ) ) : null;
			if ( null !== $parsed ) {
				$any                   = true;
				$extras[ $parsed[0] ][ $parsed[1] ] = true;
			}
		}
		if ( ! $any ) {
			return $layers;
		}

		$out  = [];
		$done = [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$lid = (string) ( $layer['layerId'] ?? '' );
			if ( null !== Config::parse_extra_id( $lid ) ) {
				continue; // Re-emitted after the base topping.
			}
			$out[] = $layer;
			if ( isset( $extras[ $lid ] ) && ! isset( $done[ $lid ] ) ) {
				$done[ $lid ] = true;
				foreach ( array_keys( $extras[ $lid ] ) as $n ) {
					$copy            = $layer;
					$copy['layerId'] = $lid;
					$out[]           = $copy;
				}
			}
		}
		return $out;
	}
}
