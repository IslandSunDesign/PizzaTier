<?php
/**
 * Keeps "extra portion" layers out of the customer-facing topping menus.
 *
 * A layer the admin created only to be stacked on another topping (e.g.
 * "Pepperoni — Extra Layer") should not appear as a topping of its own. Every
 * bundled PizzaTier template passes its topping posts through
 * `pizzatier_query_args_toppings` and each card through `pizzatier_card_html`,
 * so both are filtered.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Frontend;

use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MenuFilter {

	public function register(): void {
		add_filter( 'pizzatier_query_args_toppings', [ $this, 'filter_toppings' ], 20, 2 );
		add_filter( 'pizzatier_card_html', [ $this, 'filter_card' ], 20, 3 );
	}

	/**
	 * Templates pass a list of WP_Post objects; TemplateAPI passes WP_Query
	 * args. Handle both.
	 *
	 * @param mixed  $value Posts or query args.
	 * @param string $type  Layer type.
	 * @return mixed
	 */
	public function filter_toppings( $value, $type = '' ) {
		if ( ! is_array( $value ) || is_admin() ) {
			return $value;
		}
		$hidden = Config::portion_only_ids();
		if ( empty( $hidden ) ) {
			return $value;
		}

		if ( isset( $value['post_type'] ) ) {
			$not_in                = isset( $value['post__not_in'] ) ? (array) $value['post__not_in'] : [];
			$value['post__not_in'] = array_values( array_unique( array_merge( $not_in, $hidden ) ) );
			return $value;
		}

		return array_values(
			array_filter(
				$value,
				function ( $post ) use ( $hidden ) {
					return ! ( $post instanceof \WP_Post ) || ! in_array( (int) $post->ID, $hidden, true );
				}
			)
		);
	}

	/**
	 * @param string   $html       Card markup.
	 * @param \WP_Post $post       Layer post.
	 * @param string   $layer_type Layer type.
	 * @return string
	 */
	public function filter_card( $html, $post = null, $layer_type = '' ) {
		if ( 'toppings' !== $layer_type || ! $post instanceof \WP_Post ) {
			return $html;
		}
		return in_array( (int) $post->ID, Config::portion_only_ids(), true ) ? '' : $html;
	}
}
