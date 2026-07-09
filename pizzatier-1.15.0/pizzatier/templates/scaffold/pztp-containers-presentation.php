<?php
/**
 * Scaffold Template — registers [pizzatier-visualizer] shortcode.
 * Delegates to pzt_scaffold_menu_func() defined in pztp-template-custom.php.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-containers-presentation_start' );

function pzt_scaffold_visualizer_func( $atts = [] ) {
    $atts = shortcode_atts( [
        'id'    => 'pizzatier-pizza',
        'crust' => '',
        'sauce' => '',
        'cheese'=> '',
    ], $atts, 'pizzatier-visualizer' );

    if ( function_exists( 'pzt_scaffold_menu_func' ) ) {
        return pzt_scaffold_menu_func( $atts );
    }
    return '<!-- Scaffold: pzt_scaffold_menu_func not found -->';
}

add_shortcode( 'pizzatier-visualizer', 'pzt_scaffold_visualizer_func' );

do_action( 'pizzatier_file_pztp-containers-presentation_end' );
