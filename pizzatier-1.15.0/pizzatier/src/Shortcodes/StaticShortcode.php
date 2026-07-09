<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizza_static] shortcode — renders a non-interactive static pizza.
 *
 * Attributes:
 *   crust    Layer slug.
 *   sauce    Layer slug.
 *   cheese   Layer slug.
 *   toppings Comma-separated topping slugs.
 *   drizzle  Layer slug.
 *   cut      Layer slug.
 */
class StaticShortcode {

	public function render( $atts ): string {
		$atts = apply_filters( 'pizzatier_static_atts', shortcode_atts( [
			'crust'    => '',
			'sauce'    => '',
			'cheese'   => '',
			'toppings' => '',
			'drizzle'  => '',
			'cut'      => '',
			// Legacy support for [pizzatier-static slices="..."]
			'slices'   => '',
		], $atts, 'pizza_static' ) );

		// Map legacy 'slices' → 'cut'
		if ( $atts['cut'] === '' && $atts['slices'] !== '' ) {
			$atts['cut'] = $atts['slices'];
		}

		$builder = new \PizzaTier\Builder\PizzaBuilder();
		$html    = '<div class="pizzatier-static-wrap">'
		         . $builder->build_static(
		               $atts['crust'],
		               $atts['sauce'],
		               $atts['cheese'],
		               $atts['toppings'],
		               $atts['drizzle'],
		               $atts['cut'],
		               ''
		           )
		         . '</div>';

		return pzl_kses_builder_html( $html );
	}
}
