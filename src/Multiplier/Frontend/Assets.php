<?php
/**
 * Front-end script + configuration.
 *
 * Loaded wherever PizzaTier's builder scripts load: WooCommerce pizza product
 * pages (enqueued at wp_enqueue_scripts) and any page whose builder shortcode
 * or block enqueued the template late, during content rendering.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Frontend;

use PizzaTier\Multiplier\Config;
use PizzaTier\Multiplier\Pricing\SyntheticGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	const HANDLE = 'pizzatier-topping-multiplier';

	/** @var bool */
	private $done = false;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ], 99 );
		// Shortcodes/blocks enqueue PizzaTier during content rendering — catch
		// those before footer scripts print (wp_footer:20).
		add_action( 'wp_footer', [ $this, 'maybe_enqueue' ], 1 );
	}

	public function maybe_enqueue(): void {
		if ( $this->done || is_admin() ) {
			return;
		}
		$builder_present = wp_script_is( 'pizzatier-js', 'enqueued' )
			|| wp_script_is( 'pizzatier-commerce-frontend-builder', 'enqueued' );
		if ( ! $builder_present ) {
			return;
		}
		$this->done = true;

		$config = self::config();
		if ( empty( $config['toppings'] ) ) {
			return; // No topping allows multiples — nothing to do on the page.
		}

		wp_enqueue_style( self::HANDLE, PIZZATIER_PLUGIN_URL . 'assets/css/topping-multiplier.css', [], PIZZATIER_VERSION );
		wp_enqueue_script( self::HANDLE, PIZZATIER_PLUGIN_URL . 'assets/js/topping-multiplier.js', [], PIZZATIER_VERSION, true );
		wp_add_inline_script( self::HANDLE, 'window.pizzatierMultiplierConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Configuration handed to topping-multiplier.js.
	 */
	public static function config(): array {
		$toppings = [];
		foreach ( Config::toppings() as $slug => $t ) {
			$levels = [];
			if ( 'layers' === $t['method'] ) {
				// Only the levels the admin linked; the script repeats the
				// nearest lower one for every level in between and above, so
				// the config stays small however many portions are allowed.
				foreach ( (array) $t['levels'] as $n => $pid ) {
					if ( (int) $n > (int) $t['max'] ) {
						continue;
					}
					$levels[ (string) (int) $n ] = [
						'id'    => (int) $pid,
						'title' => (string) get_the_title( (int) $pid ),
						'img'   => esc_url_raw( Config::layer_image( (int) $pid ) ),
					];
				}
			}
			$toppings[ (string) $slug ] = [
				'id'      => (int) $t['id'],
				'title'   => (string) $t['title'],
				'method'  => (string) $t['method'],
				'max'     => (int) $t['max'],
				'pricing' => (string) $t['pricing'],
				'value'   => (float) $t['value'],
				'levels'  => (object) $levels,
			];
		}

		// Derived price grids for added portions charged at a percentage, a
		// fixed amount or nothing (synthetic pricing IDs). Everything else —
		// the topping's own grid, linked layers, global and product grids — is
		// already in PizzaTier's live-pricing config.
		$product_id = ( is_singular( 'product' ) ) ? (int) get_queried_object_id() : 0;
		$grids      = [];
		foreach ( Config::toppings() as $t ) {
			// Pricing IDs only change at linked levels (and below the first one),
			// so level 2 plus each linked level covers every portion.
			$check = array_unique( array_merge( [ 2 ], array_map( 'intval', array_keys( (array) $t['levels'] ) ) ) );
			foreach ( $check as $n ) {
				if ( $n > (int) $t['max'] ) {
					continue;
				}
				$pid = Config::price_post_id( $t, $n );
				if ( SyntheticGrid::is_synthetic( $pid ) && ! isset( $grids[ (string) $pid ] ) ) {
					$grids[ (string) $pid ] = SyntheticGrid::derived_grid( (int) $t['id'], $product_id );
				}
			}
		}

		$max = 0;
		if ( function_exists( 'pizzatier_get_option' ) ) {
			$max = (int) pizzatier_get_option( 'max_toppings', 0 );
		}
		if ( $max <= 0 ) {
			$max = (int) get_option( 'pizzatier_setting_topping_maxtoppings', 0 );
		}

		return [
			'sep'            => Config::SEP,
			'synBase'        => SyntheticGrid::BASE,
			'toppings'       => (object) $toppings,
			'grids'          => (object) $grids,
			'visual'         => (string) Config::get( 'visual' ),
			'countTowardMax' => (bool) Config::get( 'count_toward_max' ),
			'globalMax'      => $max,
			'labels'         => [
				'levels'      => Config::level_labels(),
				'control'     => (string) Config::get( 'control_label' ),
				'base'        => (string) Config::get( 'base_label' ),
				'extra'       => (string) Config::get( 'extra_label' ),
				'layersControl' => (string) Config::get( 'layers_label' ),
				'addLayer'    => (string) Config::get( 'add_layer_label' ),
				'baseLayers'  => (string) Config::get( 'base_label_layers' ),
				'layer'       => (string) Config::get( 'layer_label' ),
				/* translators: %d: number of layers, e.g. "3 layers". */
				'layerCount'  => __( '%d layers', 'pizzatier' ),
				/* translators: %s: layer name, e.g. "Pepperoni — layer 2". */
				'removeLayer' => __( 'Remove %s', 'pizzatier' ),
				/* translators: %s: topping name. */
				'layersGroup' => __( 'Layers of %s', 'pizzatier' ),
				'extraLayers' => (string) Config::get( 'extra_label_layers' ),
				'more'        => __( 'More', 'pizzatier' ),
				'less'        => __( 'Less', 'pizzatier' ),
				'limit'       => __( 'Topping limit reached', 'pizzatier' ),
				/* translators: %s: topping name. */
				'groupLabel'  => __( 'Amount of %s', 'pizzatier' ),
			],
		];
	}
}
