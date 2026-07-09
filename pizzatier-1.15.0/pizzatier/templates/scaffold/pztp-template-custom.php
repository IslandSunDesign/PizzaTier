<?php
/**
 * Scaffold Template — registers [pizzatier-menu] shortcode.
 * This is the primary entry point: instantiate, set context, include menu file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-template-custom_start' );

if ( ! function_exists( 'pzt_scaffold_menu_func' ) ) :
function pzt_scaffold_menu_func( $atts = [] ) {

    $atts = shortcode_atts( [
        'id'              => 'pizzabuilder-1',
        'max_toppings'    => '',
        'pizza_shape'     => '',
        'pizza_aspect'    => '',
        'pizza_radius'    => '',
        'hide_tabs'       => '',
        'show_tabs'       => '',
        'default_crust'   => '',
        'default_sauce'   => '',
        'default_cheese'  => '',
        'default_drizzle' => '',
        'default_slicing' => '',
        // Partial override attributes — any partial can be swapped at shortcode level.
        // e.g. partial_pizza_stage="my-stage.html" to use ./partials/my-stage.html
        'partial_pizza_stage'    => '',
        'partial_tab_bar'        => '',
        'partial_category_panel' => '',
        'partial_item_card'      => '',
        'partial_item_card_topping' => '',
        'partial_summary_panel'  => '',
    ], $atts, 'pizzatier-menu' );

    // Unique instance IDs for multiple shortcodes on one page.
    static $pzt_sc_counter = 0;
    $pzt_sc_counter++;

    $instance_id     = sanitize_html_class( $atts['id'] ?: ( 'pizzabuilder-' . $pzt_sc_counter ) );
    $template_slug   = 'scaffold';
    $function_prefix = 'pzt_scaffold';

    ob_start();
    include __DIR__ . '/pztp-containers-menu.php';
    return ob_get_clean();
}
endif;

add_shortcode( 'pizzatier-menu', 'pzt_scaffold_menu_func' );

do_action( 'pizzatier_file_pztp-template-custom_end' );
