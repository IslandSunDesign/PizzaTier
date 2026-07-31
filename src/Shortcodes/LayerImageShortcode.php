<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizza_layer] shortcode — outputs a single layer <img> anywhere on the page.
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
		], $atts, 'pizza_layer' );

		$type = sanitize_key( $atts['type'] );
		$slug = sanitize_key( $atts['slug'] );

		if ( ! $type || ! $slug ) {
			return '<!-- [pizza_layer] missing type or slug -->';
		}

		$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type . 's' );
		if ( ! $post ) {
			// Try without the plural 's' (e.g. 'cut' → 'pizzatier_cuts' above, singular fallback)
			$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type );
		}
		if ( ! $post ) { return '<!-- [pizza_layer] not found -->' ; }

		if ( $atts['image'] === 'layer' ) {
			$url = \PizzaTier\Template\TemplateAPI::get_layer_image( $post->ID, $type );
		} else {
			$url = \PizzaTier\Template\TemplateAPI::get_list_image( $post->ID, $type );
		}

		if ( ! $url ) { return '<!-- [pizza_layer] no image found -->'; }

		$css_class = trim( 'pizzatier-layer-img pizzatier-' . $type . '-img ' . sanitize_html_class( $atts['class'] ) );
		$alt       = esc_attr( get_the_title( $post ) );

		$html = apply_filters(
			'pizzatier_layer_html',
			'<img src="' . esc_url( $url ) . '" class="' . esc_attr( $css_class ) . '" alt="' . $alt . '" loading="lazy" />',
			$type,
			$slug
		);

		return $html;
	}
}
