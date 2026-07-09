<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-containers-presentation_start' );

/**
 * PocketPie registers [pizzatier-visualizer] shortcode.
 * All rendering is handled in pztp-containers-menu.php.
 */
function pzt_pocketpie_visualizer_func( $atts = array() ) {
    $atts = shortcode_atts( array(
        'id'      => 'pizzatier-pizza',
        'crust'   => '',
        'sauce'   => '',
        'cheese'  => '',
        'layout'  => 'corner-quad', // corner-quad | layer-deck | slide-drawer | stack-panel
    ), $atts, 'pizzatier-visualizer' );

    if ( function_exists( 'pzt_pocketpie_menu_func' ) ) {
        return pzt_pocketpie_menu_func( $atts );
    }
    return '<!-- PocketPie: pzt_pocketpie_menu_func not found -->';
}

add_shortcode( 'pizzatier-visualizer', 'pzt_pocketpie_visualizer_func' );

do_action( 'pizzatier_file_pztp-containers-presentation_end' );
