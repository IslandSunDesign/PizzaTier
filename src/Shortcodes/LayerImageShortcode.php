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

		$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type . 's' );
		if ( ! $post ) {
			// Try without the plural 's' (e.g. 'cut' → 'pizzatier_cuts' above, singular fallback)
			$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type );
		}
		if ( ! $post ) { return '<!-- [pizzatier_layer] not found -->' ; }

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
