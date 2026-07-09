<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-containers-presentation_start' );

$pizzatier_template_images_directory = plugin_dir_url( __FILE__ ) . 'images/';

/*
 * Fornaia registers the [pizzatier-visualizer] shortcode.
 * The builder UI is rendered by pztp-containers-menu.php.
 */

function pizzatier_toppings_visualizer_func_rustic( $atts = array() ) {
    $atts = shortcode_atts( array(
        'id'       => 'pizzatier-pizza',
        'crust'    => '',
        'sauce'    => '',
        'cheese'   => '',
        'toppings' => '',
        'drizzle'  => '',
        'cut'      => '',
    ), $atts, 'pizzatier-visualizer' );

    if ( function_exists( 'pizzatier_toppings_menu_func' ) ) {
        return pizzatier_toppings_menu_func();
    }

    return '<!-- Fornaia: pizzatier_toppings_menu_func not found -->';
}

add_shortcode( 'pizzatier-visualizer', 'pizzatier_toppings_visualizer_func_rustic' );

do_action( 'pizzatier_file_pztp-containers-presentation_end' );
