<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-containers-presentation_start' );

$pizzatier_template_images_directory = plugin_dir_url( __FILE__ ) . 'images/';

/*
 * NightPie registers the [pizzatier-visualizer] shortcode.
 * This is the shortcode that pages actually use (e.g. [pizzatier-visualizer id="glass-demo-ui"]).
 * It outputs the full NightPie UI: sticky split-screen pizza + tabbed builder.
 * The heavy lifting is in pztp-containers-menu.php (pizzatier_toppings_menu_func).
 */

function pizzatier_toppings_visualizer_func( $atts = array() ) {
    // Merge shortcode attributes with defaults
    $atts = shortcode_atts( array(
        'id'       => 'pizzatier-pizza',
        'crust'    => '',
        'sauce'    => '',
        'cheese'   => '',
        'toppings' => '',
        'drizzle'  => '',
        'cut'      => '',
    ), $atts, 'pizzatier-visualizer' );

    // pizzatier_toppings_menu_func is defined in pztp-containers-menu.php
    // and already registered as the [pizzatier-menu] shortcode handler.
    // We call it directly here so [pizzatier-visualizer] also works.
    if ( function_exists( 'pizzatier_toppings_menu_func' ) ) {
        return pizzatier_toppings_menu_func();
    }

    return '<!-- NightPie: pizzatier_toppings_menu_func not found -->';
}

add_shortcode( 'pizzatier-visualizer', 'pizzatier_toppings_visualizer_func' );

do_action( 'pizzatier_file_pztp-containers-presentation_end' );
