<?php
namespace PizzaTier\Template;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Helper functions available to template files.
 * Templates can call PizzaTier\Template\TemplateAPI::method() or use the
 * procedural helpers registered below.
 */
class TemplateAPI {

	/**
	 * Get all posts for a CPT type, ordered by menu_order then title.
	 *
	 * @param string $type CPT suffix (e.g. 'toppings', 'crusts').
	 * @param array  $extra_args Additional WP_Query args.
	 * @return \WP_Post[]
	 */
	public static function get_layer_posts( string $type, array $extra_args = [] ): array {
		$defaults = [
			'post_type'      => 'pizzatier_' . $type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		];
		$args  = apply_filters( "pizzatier_query_args_{$type}", array_merge( $defaults, $extra_args ), $type );
		$posts = get_posts( $args );

		// Optionally drop layers whose custom data is too incomplete to render or
		// price safely, so a half-configured item can't break the builder. The
		// same filtered list feeds calculations (PizzaTierPro reads it too).
		if ( get_option( 'pizzatier_setting_require_complete_data', 'no' ) === 'yes' ) {
			$posts = array_values( array_filter( $posts, function ( $post ) use ( $type ) {
				return self::layer_has_sufficient_data( $post, $type );
			} ) );
		}

		return $posts;
	}

	/**
	 * Whether a layer post carries enough custom data to be safely usable in the
	 * builder and in price calculations.
	 *
	 * Rules (overridable via the `pizzatier_layer_is_usable` filter):
	 *  - Image-bearing types (toppings, crusts, sauces, cheeses, drizzles, cuts)
	 *    must resolve to a non-empty layer image — without it the stack can't render.
	 *  - Sizes must have a positive `diameter_inches` (needed for area / pricing).
	 *  - Everything else passes.
	 */
	public static function layer_has_sufficient_data( \WP_Post $post, string $type ): bool {
		$post_id = (int) $post->ID;
		$ok      = true;

		$image_types = [ 'toppings', 'crusts', 'sauces', 'cheeses', 'drizzles', 'cuts' ];
		if ( in_array( $type, $image_types, true ) ) {
			$ok = ( self::get_layer_image( $post_id, rtrim( $type, 's' ) ) !== '' );
		} elseif ( $type === 'sizes' ) {
			$diameter = get_post_meta( $post_id, '_pizzatier_diameter_inches', true );
			if ( $diameter === '' || $diameter === false ) {
				$diameter = get_post_meta( $post_id, 'diameter_inches', true );
			}
			$ok = ( (float) $diameter > 0 );
		}

		/**
		 * Filter the usability verdict for a single layer.
		 *
		 * @param bool     $ok    Whether the layer is considered complete enough to use.
		 * @param \WP_Post $post  The layer post.
		 * @param string   $type  CPT suffix (e.g. 'toppings').
		 */
		return (bool) apply_filters( 'pizzatier_layer_is_usable', $ok, $post, $type );
	}

	/**
	 * Resolve the preferred "list / thumbnail" image URL for a layer post.
	 * Falls back gracefully: SCF list image → SCF layer image → featured image.
	 */
	public static function get_list_image( int $post_id, string $type ): string {
		$field  = $type . '_image'; // e.g. topping_image, sauce_image
		$lfield = $type . '_layer_image';
		$url    = function_exists( 'get_field' ) ? get_field( $field, $post_id ) : null;
		if ( ! $url ) { $url = function_exists( 'get_field' ) ? get_field( $lfield, $post_id ) : null; }
		if ( ! $url ) { $url = (string) get_the_post_thumbnail_url( $post_id, 'medium' ); }
		return (string) $url;
	}

	/**
	 * Resolve the layer-stack image URL (the transparent PNG used in the pizza visualization).
	 */
	public static function get_layer_image( int $post_id, string $type ): string {
		$url = function_exists( 'get_field' ) ? get_field( $type . '_layer_image', $post_id ) : null;
		if ( ! $url ) { $url = self::get_list_image( $post_id, $type ); }
		return (string) $url;
	}
}
