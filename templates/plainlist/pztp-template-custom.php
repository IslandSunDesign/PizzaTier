<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-template-custom_start' );

/**
 * Plainlist template — settings-driven CSS injection.
 *
 * Reads all plainlist_setting_* options and emits a <style> block
 * that sets CSS custom properties on .pl-root.
 */

if ( ! function_exists( 'pzt_plainlist_get_font_stack' ) ) :
function pzt_plainlist_get_font_stack( string $key ): string {
	$map = [
		'system'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
		'georgia' => "Georgia, 'Times New Roman', serif",
		'inter'   => "'Inter', sans-serif",
		'roboto'  => "'Roboto', sans-serif",
		'mono'    => "'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace",
		'courier' => "'Courier New', Courier, monospace",
	];
	return $map[ $key ] ?? $map['system'];
}
endif;

if ( ! function_exists( 'pzt_plainlist_inject_css' ) ) :
function pzt_plainlist_inject_css(): void {
	$g = fn( string $k, string $d = '' ) => (string) get_option( $k, $d );

	$accent          = $g( 'plainlist_setting_accent_color',         '#1a1a1a' );
	$bg              = $g( 'plainlist_setting_bg_color',              '#ffffff' );
	$heading_color   = $g( 'plainlist_setting_section_header_color', '#111111' );
	$item_color      = $g( 'plainlist_setting_item_text_color',      '#333333' );
	$divider_color   = $g( 'plainlist_setting_divider_color',        '#e0e0e0' );
	$font_key        = $g( 'plainlist_setting_font_family',          'system' );
	$font_stack      = pzt_plainlist_get_font_stack( $font_key );
	$base_size       = max( 12, min( 22, (int) $g( 'plainlist_setting_base_font_size',  '15' ) ) );
	$heading_size    = max( 13, min( 32, (int) $g( 'plainlist_setting_heading_size',    '18' ) ) );
	$heading_weight  = $g( 'plainlist_setting_heading_weight',       '700' );
	$text_transform  = $g( 'plainlist_setting_text_transform',       'none' );
	$check_size      = max( 12, min( 28, (int) $g( 'plainlist_setting_check_size',      '18' ) ) );
	$max_width_raw   = (int) $g( 'plainlist_setting_max_width',      '680' );
	$max_width       = $max_width_raw > 0 ? $max_width_raw . 'px' : 'none';
	$section_gap     = max( 8, min( 80, (int) $g( 'plainlist_setting_section_gap',     '32' ) ) );
	$item_gap        = max( 2, min( 32, (int) $g( 'plainlist_setting_item_gap',        '10' ) ) );

	// List-style additions
	$row_padding     = max( 0, min( 20, (int) $g( 'plainlist_setting_row_padding',     '4' ) ) );
	$label_weight    = (int) $g( 'plainlist_setting_label_weight',   '400' );

	// Add-to-Cart button (PizzaTier checkout bar)
	$cart_bg         = $g( 'plainlist_setting_cart_btn_bg',          '#1a1a1a' );
	$cart_fg         = $g( 'plainlist_setting_cart_btn_text_color',  '#ffffff' );
	$cart_radius     = max( 0, min( 32, (int) $g( 'plainlist_setting_cart_btn_radius', '4' ) ) );

	$css = "
.pl-root {
	--pl-accent:        " . sanitize_hex_color( $accent )        . ";
	--pl-bg:            " . sanitize_hex_color( $bg )            . ";
	--pl-heading-color: " . sanitize_hex_color( $heading_color ) . ";
	--pl-item-color:    " . sanitize_hex_color( $item_color )    . ";
	--pl-divider:       " . sanitize_hex_color( $divider_color ) . ";
	--pl-font:          " . esc_attr( $font_stack )              . ";
	--pl-base-size:     " . $base_size                           . "px;
	--pl-heading-size:  " . $heading_size                        . "px;
	--pl-heading-weight:" . (int) $heading_weight                . ";
	--pl-text-transform:" . esc_attr( $text_transform )          . ";
	--pl-check-size:    " . $check_size                          . "px;
	--pl-max-width:     " . esc_attr( $max_width )               . ";
	--pl-section-gap:   " . $section_gap                         . "px;
	--pl-item-gap:      " . $item_gap                            . "px;
	--pl-row-pad:       " . $row_padding                         . "px;
	--pl-label-weight:  " . ( $label_weight ?: 400 )             . ";
	--pl-cart-bg:       " . sanitize_hex_color( $cart_bg )       . ";
	--pl-cart-fg:       " . sanitize_hex_color( $cart_fg )       . ";
	--pl-cart-radius:   " . $cart_radius                         . "px;
}
";
	wp_add_inline_style( 'pizzatier-template-plainlist', $css ); // phpcs:ignore -- dynamic CSS vars
}
endif;

// Must run on wp_enqueue_scripts (not wp_head): wp_add_inline_style() only
// works before styles are printed, and styles print at wp_head priority 8 —
// hooking later in wp_head silently discarded all of this CSS.
// This file is included during wp_enqueue_scripts:10 (TemplateLoader), so
// registering at priority 99 on the same hook still fires.
add_action( 'wp_enqueue_scripts', 'pzt_plainlist_inject_css', 99 );

do_action( 'pizzatier_file_pztp-template-custom_end' );
