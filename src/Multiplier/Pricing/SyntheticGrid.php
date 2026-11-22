<?php
/**
 * Synthetic per-layer price grids for added portions.
 *
 * PizzaTier's PriceCalculator prices every topping through
 * Grid::get_layer_price( $layer_post_id, … ), which reads the
 * `_pizzatier_layer_grid` post meta of that post ID first. When an added
 * portion should cost something other than a full topping (a percentage, a
 * fixed amount, or nothing), the portion is sent with a reserved synthetic
 * post ID instead of the topping's real ID, and this class answers the meta
 * lookup for that ID with a derived grid. Every pricing engine, size
 * multiplier, bulk discount and rounding rule in PizzaTier then applies to the
 * added portion exactly as it does to any other topping.
 *
 * Synthetic IDs are BASE + real topping ID. BASE (9×10^11) is far beyond any
 * real post ID and still exactly representable in JavaScript.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Pricing;

use PizzaTier\Commerce\PriceGrid\Grid;
use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SyntheticGrid {

	const BASE = 900000000000;

	/** Product whose grid the derived cells are computed against. */
	private static $product_id = 0;

	/** @var array<string,array> Request cache keyed "product:topping". */
	private static $cache = [];

	public function register(): void {
		add_filter( 'get_post_metadata', [ $this, 'filter_meta' ], 5, 4 );
	}

	public static function encode( int $topping_id ): int {
		return self::BASE + $topping_id;
	}

	public static function is_synthetic( int $post_id ): bool {
		return $post_id > self::BASE;
	}

	public static function decode( int $post_id ): int {
		return self::is_synthetic( $post_id ) ? $post_id - self::BASE : $post_id;
	}

	/** Called by the request rewriters before PizzaTier prices anything. */
	public static function set_product( int $product_id ): void {
		self::$product_id = max( 0, $product_id );
	}

	/**
	 * Short-circuit every meta read for a synthetic ID so no database query is
	 * made for a post that does not exist.
	 *
	 * @param mixed  $value     Null to continue normally.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Single value requested.
	 * @return mixed
	 */
	public function filter_meta( $value, $object_id, $meta_key, $single ) {
		$object_id = (int) $object_id;
		if ( ! self::is_synthetic( $object_id ) ) {
			return $value;
		}
		if ( '' === $meta_key ) {
			return [];
		}
		if ( Grid::LAYER_META_KEY === $meta_key ) {
			$grid = self::derived_grid( self::decode( $object_id ), self::$product_id );
			return $single ? [ $grid ] : [ $grid ];
		}
		return $single ? [ '' ] : [];
	}

	/**
	 * Build the price grid an added portion of $topping_id is charged from.
	 *
	 * Cells cover every size × coverage of the product grid (or the site
	 * defaults when the product has none), so the lookup never falls through
	 * to the full-price product grid.
	 *
	 * @return array{sizes:string[],fractions:string[],cells:array<string,float>}
	 */
	public static function derived_grid( int $topping_id, int $product_id ): array {
		$key = $product_id . ':' . $topping_id;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$grid  = new Grid();
		$post  = get_post( $topping_id );
		$conf  = $post instanceof \WP_Post ? Config::resolve_post( $post ) : null;
		$mode  = $conf ? $conf['pricing'] : 'full';
		$value = $conf ? (float) $conf['value'] : 0.0;

		$sizes     = $product_id > 0 ? $grid->get_sizes( $product_id ) : [];
		$fractions = $product_id > 0 ? $grid->get_fractions( $product_id ) : [];
		if ( empty( $sizes ) ) {
			$sizes = $grid->default_sizes();
		}
		if ( empty( $fractions ) ) {
			$fractions = $grid->default_fractions();
		}
		// Make sure the topping's own grid labels are covered too.
		$own = $grid->get_layer_grid( $topping_id );
		if ( is_array( $own ) ) {
			$sizes     = array_values( array_unique( array_merge( $sizes, (array) ( $own['sizes'] ?? [] ) ) ) );
			$fractions = array_values( array_unique( array_merge( $fractions, (array) ( $own['fractions'] ?? [] ) ) ) );
		}

		$cells = [];
		foreach ( $sizes as $size ) {
			foreach ( $fractions as $fraction ) {
				$size     = (string) $size;
				$fraction = (string) $fraction;
				switch ( $mode ) {
					case 'free':
						$price = 0.0;
						break;
					case 'fixed':
						$price = $value;
						break;
					case 'percent':
						$full  = self::full_price( $grid, $topping_id, $product_id, $size, $fraction );
						$price = $full * ( $value / 100 );
						break;
					default:
						$price = self::full_price( $grid, $topping_id, $product_id, $size, $fraction );
				}
				$cells[ $grid->cell_key( $size, $fraction ) ] = round( $price, 4 );
			}
		}

		self::$cache[ $key ] = [
			'sizes'     => array_values( array_map( 'strval', $sizes ) ),
			'fractions' => array_values( array_map( 'strval', $fractions ) ),
			'cells'     => $cells,
		];
		return self::$cache[ $key ];
	}

	/**
	 * The grid PizzaTier's server actually charges a layer from on a product —
	 * its own grid, else the global topping grid, else the product grid —
	 * flattened into one size × coverage table. Handed to the browser so the
	 * live price uses exactly the cells the cart will be charged.
	 *
	 * @return array{sizes:string[],fractions:string[],cells:array<string,float>}
	 */
	public static function effective_grid( int $post_id, int $product_id ): array {
		$grid      = new Grid();
		$sizes     = $product_id > 0 ? $grid->get_sizes( $product_id ) : $grid->default_sizes();
		$fractions = $product_id > 0 ? $grid->get_fractions( $product_id ) : $grid->default_fractions();
		$own       = $grid->get_layer_grid( $post_id );
		if ( is_array( $own ) ) {
			$sizes     = array_values( array_unique( array_merge( $sizes, (array) ( $own['sizes'] ?? [] ) ) ) );
			$fractions = array_values( array_unique( array_merge( $fractions, (array) ( $own['fractions'] ?? [] ) ) ) );
		}
		$cells = [];
		foreach ( $sizes as $size ) {
			foreach ( $fractions as $fraction ) {
				$cells[ $grid->cell_key( (string) $size, (string) $fraction ) ] = round( self::full_price( $grid, $post_id, $product_id, (string) $size, (string) $fraction ), 4 );
			}
		}
		return [
			'sizes'     => array_values( array_map( 'strval', $sizes ) ),
			'fractions' => array_values( array_map( 'strval', $fractions ) ),
			'cells'     => $cells,
		];
	}

	/**
	 * What PizzaTier would charge for one full portion of the topping.
	 */
	private static function full_price( Grid $grid, int $topping_id, int $product_id, string $size, string $fraction ): float {
		$price = $grid->get_layer_price( $topping_id, $product_id, $size, $fraction, 'topping' );
		if ( null === $price && $product_id > 0 ) {
			$price = $grid->get_flat_price( $product_id, 'topping', $size );
		}
		return null === $price ? 0.0 : (float) $price;
	}
}
