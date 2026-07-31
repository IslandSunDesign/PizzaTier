<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Resolves the WooCommerce product a native order should be priced and carted
 * against.
 *
 * The builder does not need a product. It runs from a shortcode or a block on
 * any page, and PizzaTier's own order records are built entirely from the layer
 * CPTs. WooCommerce needs one for both pricing and the cart, so any route that
 * touches WooCommerce has to answer "which product?" before it can do anything.
 *
 * Three answers, in order of confidence:
 *
 *   1. The product the builder was embedded in. FrontendEmbed puts this on the
 *      wrapper as `data-product-id`, and the checkout panel posts it back.
 *   2. The queried object, when the builder is on a product page but the client
 *      did not send an ID.
 *   3. The store's designated pizza product, set on the ordering settings screen.
 *      This is what makes the cart routes work from a plain shortcode page.
 *
 * Every candidate is validated before use, so a tampered `product_id` can only
 * ever select another real pizza product.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.1.0
 */
final class OrderProduct {

	/** Settings key for the designated fallback product. */
	const SETTING_KEY = 'cart_product_id';

	/**
	 * Resolve the product ID to use.
	 *
	 * @param int $posted Product ID sent with the submission, if any.
	 * @return int Product ID, or 0 when the store has no usable pizza product.
	 */
	public static function resolve( int $posted = 0 ): int {
		$product_id = 0;

		if ( $posted > 0 && self::is_pizza_product( $posted ) ) {
			$product_id = $posted;
		}

		if ( 0 === $product_id && ! wp_doing_ajax() ) {
			$queried = (int) get_queried_object_id();
			if ( $queried > 0 && self::is_pizza_product( $queried ) ) {
				$product_id = $queried;
			}
		}

		if ( 0 === $product_id ) {
			$configured = OrderSettings::get_int( self::SETTING_KEY );
			if ( $configured > 0 && self::is_pizza_product( $configured ) ) {
				$product_id = $configured;
			}
		}

		/**
		 * Filter the product a native order is priced and carted against.
		 *
		 * @since 2.1.0
		 *
		 * @param int $product_id Resolved product ID, 0 when none was found.
		 * @param int $posted     The ID the client sent, before validation.
		 */
		return (int) apply_filters( 'pizzatier_order_product_id', $product_id, $posted );
	}

	/**
	 * Whether a post is a product the builder can price.
	 *
	 * Matches the test used throughout the commerce layer: either the formal
	 * `pizza` product type, or any standard product with a builder template
	 * configured on it.
	 */
	public static function is_pizza_product( int $product_id ): bool {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		if ( 'pizza' === $product->get_type() ) {
			return true;
		}

		return '' !== (string) get_post_meta( $product_id, '_pizzatier_builder_template', true );
	}

	/**
	 * Pizza products, for the settings screen dropdown.
	 *
	 * @return array<int,string> product ID => title
	 */
	public static function choices(): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [];
		}

		$ids = [];

		// Products carrying a builder template.
		$with_template = get_posts(
			[
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only, runs once per settings screen render.
				'meta_query'       => [
					[
						'key'     => '_pizzatier_builder_template',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		foreach ( (array) $with_template as $id ) {
			$ids[ (int) $id ] = true;
		}

		// Products registered as the formal pizza type.
		$pizza_type = get_posts(
			[
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin-only, runs once per settings screen render.
				'tax_query'        => [
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'pizza',
					],
				],
			]
		);

		foreach ( (array) $pizza_type as $id ) {
			$ids[ (int) $id ] = true;
		}

		$choices = [];
		foreach ( array_keys( $ids ) as $id ) {
			$title = get_the_title( $id );
			if ( '' === $title ) {
				/* translators: %d: product ID. */
				$title = sprintf( __( 'Product #%d', 'pizzatier' ), $id );
			}
			$choices[ $id ] = $title;
		}

		natcasesort( $choices );

		return $choices;
	}
}
