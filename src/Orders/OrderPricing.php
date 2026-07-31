<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Commerce\PriceCalculator;
use PizzaTier\Commerce\PriceGrid\Grid;

/**
 * Prices native order records from the commerce price grid.
 *
 * OrderSubmission has always applied a `pizzatier_order_item_price` filter and
 * always documented it as the seam a premium extension would use. Nothing ever
 * hooked it: the calculator lived in PizzaTierPro, and when Pro merged into the
 * free plugin for 2.0 the seam was left unconnected. Every native order was
 * therefore written with a line total of zero, which is fine for a store that
 * quotes by phone and useless for one that does not.
 *
 * This class connects the two. It is registered only when WooCommerce and the
 * calculator are both available, and it never throws: a product without a grid,
 * a size that does not map, or any calculator error leaves the item unpriced
 * exactly as before, rather than failing the order.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.1.0
 */
final class OrderPricing {

	/**
	 * Product resolved for the submission currently being processed.
	 *
	 * The filter receives only the line item, so the request context is handed
	 * over here rather than re-read from $_POST inside the filter.
	 *
	 * @var int
	 */
	private static $context_product_id = 0;

	/** @var PriceCalculator|null */
	private $calculator = null;

	/** Whether the pricing bridge can run on this site at all. */
	public static function is_available(): bool {
		return class_exists( 'WooCommerce' )
			&& class_exists( PriceCalculator::class )
			&& class_exists( Grid::class );
	}

	public function register(): void {
		if ( ! self::is_available() ) {
			return;
		}
		add_filter( 'pizzatier_order_item_price', [ $this, 'price_item' ], 10, 2 );
	}

	/**
	 * Record which product the submission in progress belongs to.
	 */
	public static function set_context_product( int $product_id ): void {
		self::$context_product_id = max( 0, $product_id );
	}

	/**
	 * Price one resolved order line.
	 *
	 * @param float|null $price Existing price, null when unpriced.
	 * @param array      $item  The resolved item from OrderSubmission.
	 * @return float|null Unit price, or null to leave the item unpriced.
	 */
	public function price_item( $price, $item ) {
		// Never override a price another integration already set.
		if ( null !== $price ) {
			return $price;
		}

		if ( ! is_array( $item ) || empty( $item['layers'] ) || ! is_array( $item['layers'] ) ) {
			return null;
		}

		$product_id = OrderProduct::resolve( self::$context_product_id );
		if ( $product_id <= 0 ) {
			return null;
		}

		$grid   = new Grid();
		$sizes  = $grid->get_sizes( $product_id );
		$size   = self::resolve_size( $item, $sizes );
		$layers = self::map_layers( $item['layers'] );

		if ( '' === $size || empty( $layers ) ) {
			return null;
		}

		$result = $this->calculator()->calculate( $product_id, $size, $layers );

		if ( is_wp_error( $result ) || ! isset( $result['total'] ) ) {
			// A missing grid cell, a layer the product does not permit, a size
			// that fell through — all legitimate reasons to leave the order
			// unpriced for staff to quote by hand. Never fail the submission.
			return null;
		}

		return round( (float) $result['total'], 2 );
	}

	// -------------------------------------------------------------------------
	// Mapping
	// -------------------------------------------------------------------------

	/**
	 * Map a native order's size onto one of the product's grid sizes.
	 *
	 * The two vocabularies are independent: grid sizes are free-text labels
	 * saved against the product ("Small", "12 inch"), while the order carries a
	 * pizzatier_sizes post. The post title is what a store almost always types
	 * into both, so it is tried first, then the slug, then a loose match.
	 *
	 * When the order has no size at all — the size picker is switched off — the
	 * first grid size is used, since that is the column a store treats as its
	 * base price.
	 *
	 * @param array    $item  Resolved line item.
	 * @param string[] $sizes Grid sizes for the product.
	 */
	public static function resolve_size( array $item, array $sizes ): string {
		if ( empty( $sizes ) ) {
			return '';
		}

		$size_data = ( isset( $item['size'] ) && is_array( $item['size'] ) ) ? $item['size'] : [];
		$candidates = [
			isset( $size_data['label'] ) ? (string) $size_data['label'] : '',
			isset( $size_data['slug'] ) ? (string) $size_data['slug'] : '',
		];

		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}

			if ( in_array( $candidate, $sizes, true ) ) {
				return $candidate;
			}

			// Loose match: "large" against "Large", "12-inch" against "12 inch".
			$needle = self::flatten( $candidate );
			foreach ( $sizes as $size ) {
				if ( $needle === self::flatten( $size ) ) {
					return (string) $size;
				}
			}
		}

		$fallback = (string) $sizes[0];

		/**
		 * Filter the grid size a native order is priced at.
		 *
		 * @since 2.1.0
		 *
		 * @param string   $fallback The size chosen when the order's own size did not map.
		 * @param array    $item     The resolved line item.
		 * @param string[] $sizes    Grid sizes available on the product.
		 */
		return (string) apply_filters( 'pizzatier_order_price_size', $fallback, $item, $sizes );
	}

	/** Reduce a label to a comparable form. */
	private static function flatten( string $value ): string {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( $value ) );
	}

	/**
	 * Convert order layers into the shape PriceCalculator expects.
	 *
	 * The calculator matches layers by slug and normalises coverage strings
	 * internally ('half-left' → 'Half'), so the order's own values pass through
	 * unchanged. Only the key names differ.
	 *
	 * @param array $layers Layers from the resolved item.
	 * @return array<int,array<string,mixed>>
	 */
	public static function map_layers( array $layers ): array {
		$mapped = [];

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$slug = isset( $layer['slug'] ) ? (string) $layer['slug'] : '';
			if ( '' === $slug ) {
				continue;
			}

			$mapped[] = [
				'layerId'     => $slug,
				'fraction'    => isset( $layer['coverage'] ) ? (string) $layer['coverage'] : 'whole',
				'layerType'   => isset( $layer['type'] ) ? (string) $layer['type'] : '',
				'layerName'   => isset( $layer['name'] ) ? (string) $layer['name'] : '',
				'layerPostId' => isset( $layer['post_id'] ) ? (int) $layer['post_id'] : 0,
			];
		}

		return $mapped;
	}

	private function calculator(): PriceCalculator {
		if ( null === $this->calculator ) {
			$this->calculator = new PriceCalculator( new Grid() );
		}
		return $this->calculator;
	}
}
