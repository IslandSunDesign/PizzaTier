<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizzatier_layer] shortcode — outputs a single layer <img> anywhere on the page.
 *
 * Attributes:
 *   type   One of: topping | sauce | cheese | crust | drizzle | cut
 *   slug   The CPT post slug.
 *   image  'list'  → uses the product/menu image field (default)
 *           'layer' → uses the transparent stack image field
 *   class  Additional CSS classes on the <img>.
 */
class LayerImageShortcode {

	public function render( $atts ): string {
		$atts = shortcode_atts( [
			'type'  => '',
			'slug'  => '',
			'image' => 'list',  // 'list' | 'layer'
			'class' => '',
		], $atts, 'pizzatier_layer' );

		$type = sanitize_key( $atts['type'] );
		$slug = sanitize_key( $atts['slug'] );

		if ( ! $type || ! $slug ) {
			return '<!-- [pizzatier_layer] missing type or slug -->';
		}

		// Only the layer post types, and only published layers: the type used
		// to be concatenated into 'pizzatier_' . $type, so any plugin post type
		// (orders included) could be probed, and get_page_by_path() ignores
		// post status.
		$post_type = LayerInfoShortcode::post_type_for( $type );
		if ( '' === $post_type ) { return '<!-- [pizzatier_layer] unknown type -->'; }

		$post = LayerInfoShortcode::find_public_post( $type, $slug );
		if ( ! $post ) { return '<!-- [pizzatier_layer] not found -->' ; }

		// Field names use the singular form (topping_image, cheese_layer_image).
		$type = LayerInfoShortcode::singular_for( $post_type );

		if ( $atts['image'] === 'layer' ) {
			$url = \PizzaTier\Template\TemplateAPI::get_layer_image( $post->ID, $type );
		} else {
			$url = \PizzaTier\Template\TemplateAPI::get_list_image( $post->ID, $type );
		}

		if ( ! $url ) { return '<!-- [pizzatier_layer] no image found -->'; }

		$css_class = trim( 'pizzatier-layer-img pizzatier-' . $type . '-img ' . sanitize_html_class( $atts['class'] ) );
		$alt       = esc_attr( get_the_title( $post ) );

		$html = apply_filters(
			'pizzatier_layer_html',
			'<img src="' . esc_url( $url ) . '" class="' . esc_attr( $css_class ) . '" alt="' . $alt . '" loading="lazy" />',
			$type,
			$slug
		);

		// Escape late: the filter can hand back arbitrary markup, so the
		// returned HTML is passed through wp_kses() with an explicit
		// allowlist before WordPress renders it (shortcode and block
		// render_callback output is printed as-is).
		return wp_kses(
			$html,
			[
				'img' => [
					'src'      => true,
					'class'    => true,
					'alt'      => true,
					'loading'  => true,
					'decoding' => true,
					'width'    => true,
					'height'   => true,
					'srcset'   => true,
					'sizes'    => true,
					'style'    => true,
				],
				'span' => [ 'class' => true ],
			]
		);
	}
}
