<?php
/**
 * Added portions for PizzaTier's native (non-WooCommerce) order flow.
 *
 * pizzatier-orders.js submits each layer as { type, slug, coverage }, and the
 * server re-resolves every slug against the database — an unknown slug such
 * as "pepperoni~2" would simply be dropped. This class:
 *
 *  1. rewrites added portions to repeat their base topping (same coverage)
 *     before the order handler runs, and prefixes a short "Extra portions"
 *     line to the item note so the kitchen sees it on every ticket/email;
 *  2. pricing then happens in PriceCalculator (via Commerce\LayerResolver),
 *     which prices the repeats with the admin's per-portion rule.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Pricing;

use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NativeOrders {

	const ACTION = 'pizzatier_submit_order';

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'rewrite_items' ], 1 );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'rewrite_items' ], 1 );
		add_filter( 'pizzatier_order_max_layers', [ __CLASS__, 'layer_allowance' ], 5 );
	}

	/**
	 * Added portions arrive as repeated layers, so leave room for them in the
	 * per-pizza layer cap: three toppings at the portion ceiling (2.4.1).
	 *
	 * @param int $max Current cap.
	 * @return int
	 */
	public static function layer_allowance( $max ): int {
		return (int) $max + 3 * ( Config::ceiling() - 1 );
	}

	/**
	 * Data-only rewrite of $_POST['items']; PizzaTier's handler verifies the
	 * nonce right after.
	 */
	public function rewrite_items(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- PizzaTier's handler verifies the nonce immediately after this data-only rewrite.
		if ( ! isset( $_POST['items'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; PizzaTier sanitises every field it keeps.
		$raw = wp_unslash( $_POST['items'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Oversized payloads are left untouched for the order handler to reject.
		if ( is_string( $raw ) && strlen( $raw ) > 200000 ) {
			return;
		}
		$items = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $items ) || count( $items ) > 20 ) {
			return;
		}

		$changed = false;
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) || empty( $item['layers'] ) || ! is_array( $item['layers'] ) ) {
				continue;
			}
			$result = self::rewrite_layers( $item['layers'] );
			if ( ! $result['changed'] ) {
				continue;
			}
			$changed               = true;
			$items[ $i ]['layers'] = $result['layers'];
			// The "Extra portions" kitchen line is written by OrderSubmission
			// from the validated layers (2.4.3); it is no longer injected into
			// the customer's note text here, where it could be imitated.
		}

		if ( $changed ) {
			$_POST['items'] = wp_slash( (string) wp_json_encode( $items ) );
		}
	}

	/**
	 * @param array $layers [ { type, slug, coverage } ].
	 * @return array{layers:array,changed:bool,levels:array<string,int>}
	 */
	public static function rewrite_layers( array $layers ): array {
		$bases   = [];
		$extras  = [];
		$changed = false;

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$slug   = (string) ( $layer['slug'] ?? '' );
			$parsed = Config::parse_extra_id( $slug );
			if ( null !== $parsed ) {
				$changed                            = true;
				$extras[ $parsed[0] ][ $parsed[1] ] = true;
				continue;
			}
			$type = strtolower( (string) ( $layer['type'] ?? '' ) );
			if ( '' !== $slug && ( 'topping' === $type || 'toppings' === $type ) && ! isset( $bases[ $slug ] ) ) {
				$bases[ $slug ] = $layer;
			}
		}

		if ( ! $changed ) {
			return [
'layers' => $layers,
'changed' => false,
'levels' => []
];
		}

		$out    = [];
		$levels = [];
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$slug = (string) ( $layer['slug'] ?? '' );
			if ( null !== Config::parse_extra_id( $slug ) ) {
				continue;
			}
			$out[] = $layer;

			if ( ! isset( $bases[ $slug ], $extras[ $slug ] ) || isset( $levels[ $slug ] ) ) {
				continue;
			}
			$conf = Config::topping( $slug );
			if ( null === $conf ) {
				continue;
			}
			$ns    = array_keys( $extras[ $slug ] );
			$count = 0;
			foreach ( $ns as $n ) {
				if ( (int) $n >= 2 && (int) $n <= (int) $conf['max'] ) {
					$count++;
				}
			}
			for ( $k = 0; $k < $count; $k++ ) {
				$out[] = [
					'type'     => (string) ( $layer['type'] ?? 'topping' ),
					'slug'     => $slug,
					'coverage' => (string) ( $layer['coverage'] ?? 'whole' ),
				];
			}
			if ( $count > 0 ) {
				$levels[ $slug ] = 1 + $count;
			}
		}

		return [
'layers' => $out,
'changed' => true,
'levels' => $levels
];
	}

}
