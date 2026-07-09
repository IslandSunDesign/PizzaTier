<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizza_layer_info] shortcode — outputs the value of any SCF field for a layer.
 *
 * Attributes:
 *   type   CPT type suffix (e.g. 'topping', 'cheese').
 *   slug   Post slug.
 *   field  SCF field name (e.g. 'topping_ingredients', 'cheese_melt_factor').
 */
class LayerInfoShortcode {

	public function render( $atts ): string {
		$atts = shortcode_atts( [
			'type'  => '',
			'slug'  => '',
			'field' => '',
		], $atts, 'pizza_layer_info' );

		$type  = sanitize_key( $atts['type'] );
		$slug  = sanitize_key( $atts['slug'] );
		$field = sanitize_key( $atts['field'] );

		if ( ! $type || ! $slug || ! $field ) {
			return '<!-- [pizza_layer_info] missing type, slug, or field -->';
		}

		// Try plural first (toppings, sauces, cheeses…) then singular
		$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type . 's' );
		if ( ! $post ) { $post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type ); }
		if ( ! $post ) { return '<!-- [pizza_layer_info] post not found -->'; }

		$value = pzl_get_field( $field, $post->ID );
		if ( is_array( $value ) ) { $value = implode( ', ', $value ); }

		return esc_html( (string) $value );
	}
}
