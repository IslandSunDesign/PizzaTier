<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-template-custom_start' );

/**
 * Colorbox template — shared PHP helpers + settings-driven CSS injection.
 *
 * Runs once per page load via TemplateLoader::load_template_custom().
 * Reads all colorbox_setting_* options and emits an inline <style> block
 * that overrides CSS custom properties on .cb-root.
 */

/* ── Helpers ─────────────────────────────────────────────────────── */

if ( ! function_exists( 'cb_hex2rgba' ) ) {
	function cb_hex2rgba( string $color, float $alpha ): string {
		$color = ltrim( $color, '#' );
		if ( strlen( $color ) === 3 ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}
		if ( strlen( $color ) !== 6 ) { return 'rgba(0,0,0,' . $alpha . ')'; }
		$r = hexdec( substr( $color, 0, 2 ) );
		$g = hexdec( substr( $color, 2, 2 ) );
		$b = hexdec( substr( $color, 4, 2 ) );
		return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
	}
}

// Back-compat alias used by older code in this file
if ( ! function_exists( 'hex2rgba' ) ) {
	function hex2rgba( $color, $alpha ) { return cb_hex2rgba( (string) $color, (float) $alpha ); }
}

/**
 * Blend two hex colours together. $weight is how much of $mix to apply (0..1).
 * Used to derive surface tints / borders from the configurable surface + text colours
 * so those settings propagate to hover states instead of staying on static defaults.
 */
if ( ! function_exists( 'cb_mix' ) ) {
	function cb_mix( string $base, string $mix, float $weight ): string {
		$base = ltrim( $base, '#' );
		$mix  = ltrim( $mix,  '#' );
		if ( strlen( $base ) === 3 ) { $base = $base[0].$base[0].$base[1].$base[1].$base[2].$base[2]; }
		if ( strlen( $mix )  === 3 ) { $mix  = $mix[0].$mix[0].$mix[1].$mix[1].$mix[2].$mix[2]; }
		if ( strlen( $base ) !== 6 || strlen( $mix ) !== 6 ) { return '#' . ( strlen( $base ) === 6 ? $base : '000000' ); }
		$weight = max( 0, min( 1, $weight ) );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$b = hexdec( substr( $base, $i * 2, 2 ) );
			$m = hexdec( substr( $mix,  $i * 2, 2 ) );
			$out .= str_pad( dechex( (int) round( $b + ( $m - $b ) * $weight ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}
}

if ( ! function_exists( 'pzt_colorbox_get_font_stack' ) ) :
function pzt_colorbox_get_font_stack( string $key ): string {
	$map = [
		'system'     => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
		'inter'      => "'Inter', system-ui, sans-serif",
		'poppins'    => "'Poppins', system-ui, sans-serif",
		'montserrat' => "'Montserrat', system-ui, sans-serif",
		'roboto'     => "'Roboto', system-ui, sans-serif",
	];
	return $map[ $key ] ?? $map['system'];
}
endif;

if ( ! function_exists( 'pzt_colorbox_inject_css' ) ) :
function pzt_colorbox_inject_css(): void {
	$g = function( string $k, string $d = '' ) { return (string) get_option( $k, $d ); };

	// ── Base ────────────────────────────────────────────────────────
	$accent      = sanitize_hex_color( $g( 'colorbox_setting_accent_color',      '#ff4d4d' ) ) ?: '#ff4d4d';
	$bg          = sanitize_hex_color( $g( 'colorbox_setting_bg_color',          '#f6f7fb' ) ) ?: '#f6f7fb';
	$container   = sanitize_hex_color( $g( 'colorbox_setting_container_bg',      '#f3e2c7' ) ) ?: '#f3e2c7';
	$surface     = sanitize_hex_color( $g( 'colorbox_setting_surface_color',     '#ffffff' ) ) ?: '#ffffff';
	$text        = sanitize_hex_color( $g( 'colorbox_setting_text_color',        '#161822' ) ) ?: '#161822';
	$text_muted  = sanitize_hex_color( $g( 'colorbox_setting_text_muted_color',  '#5b5f73' ) ) ?: '#5b5f73';

	// ── Category tile colours ───────────────────────────────────────
	$cat_size     = sanitize_hex_color( $g( 'colorbox_setting_cat_size_color',     '#4a90e2' ) ) ?: '#4a90e2';
	$cat_crust    = sanitize_hex_color( $g( 'colorbox_setting_cat_crust_color',    '#f5a623' ) ) ?: '#f5a623';
	$cat_sauce    = sanitize_hex_color( $g( 'colorbox_setting_cat_sauce_color',    '#d0021b' ) ) ?: '#d0021b';
	$cat_cheese   = sanitize_hex_color( $g( 'colorbox_setting_cat_cheese_color',   '#f8e71c' ) ) ?: '#f8e71c';
	$cat_toppings = sanitize_hex_color( $g( 'colorbox_setting_cat_toppings_color', '#7ed321' ) ) ?: '#7ed321';
	$cat_drizzle  = sanitize_hex_color( $g( 'colorbox_setting_cat_drizzle_color',  '#bd10e0' ) ) ?: '#bd10e0';
	$cat_cuts     = sanitize_hex_color( $g( 'colorbox_setting_cat_cuts_color',     '#50e3c2' ) ) ?: '#50e3c2';

	// ── Type / geometry / behaviour ─────────────────────────────────
	$font_key    = sanitize_key( $g( 'colorbox_setting_font_family', 'system' ) );
	$font_stack  = pzt_colorbox_get_font_stack( $font_key );
	$base_size   = max( 12, min( 20, (int) $g( 'colorbox_setting_base_font_size', '15' ) ) );
	$radius      = max(  0, min( 28, (int) $g( 'colorbox_setting_corner_radius',  '18' ) ) );

	$colorful    = $g( 'colorbox_setting_colorful_tiles', 'yes' ) === 'yes';

	// ── Derive ──────────────────────────────────────────────────────
	$accent_dim  = cb_hex2rgba( $accent, 0.14 );
	$accent_glow = cb_hex2rgba( $accent, 0.30 );
	// Smaller radius proportional to base (template defaults: sm=12, base=18).
	$radius_sm   = max( 0, (int) round( $radius * 0.66 ) );

	// Derive surface tints / borders from the configurable surface + text colours so
	// the "Card Surface Color" setting cascades to hover states and thumbs.
	$surface_2   = cb_mix( $surface, $text, 0.045 );
	$surface_3   = cb_mix( $surface, $text, 0.090 );
	$border      = cb_hex2rgba( $text, 0.10 );
	$border_hov  = cb_hex2rgba( $text, 0.22 );

	// When colorful tiles are off, all category vars collapse to the surface color.
	$tile_size     = $colorful ? $cat_size     : $surface;
	$tile_crust    = $colorful ? $cat_crust    : $surface;
	$tile_sauce    = $colorful ? $cat_sauce    : $surface;
	$tile_cheese   = $colorful ? $cat_cheese   : $surface;
	$tile_toppings = $colorful ? $cat_toppings : $surface;
	$tile_drizzle  = $colorful ? $cat_drizzle  : $surface;
	$tile_cuts     = $colorful ? $cat_cuts     : $surface;

	// ── Build CSS ───────────────────────────────────────────────────
	$css  = ".cb-root {";
	$css .= "--cb-accent:" .       esc_attr( $accent )      . ";";
	$css .= "--cb-accent-dim:" .   esc_attr( $accent_dim )  . ";";
	$css .= "--cb-accent-glow:" .  esc_attr( $accent_glow ) . ";";
	$css .= "--cb-bg:" .           esc_attr( $bg )          . ";";
	$css .= "--cb-container-bg:" .  esc_attr( $container )   . ";";
	$css .= "--cb-surface:" .      esc_attr( $surface )     . ";";
	$css .= "--cb-surface-2:" .    esc_attr( $surface_2 )   . ";";
	$css .= "--cb-surface-3:" .    esc_attr( $surface_3 )   . ";";
	$css .= "--cb-border:" .       esc_attr( $border )      . ";";
	$css .= "--cb-border-hover:" . esc_attr( $border_hov )  . ";";
	$css .= "--cb-text:" .         esc_attr( $text )        . ";";
	$css .= "--cb-text-muted:" .   esc_attr( $text_muted )  . ";";
	$css .= "--cb-size:" .         esc_attr( $tile_size )     . ";";
	$css .= "--cb-crust:" .        esc_attr( $tile_crust )    . ";";
	$css .= "--cb-sauce:" .        esc_attr( $tile_sauce )    . ";";
	$css .= "--cb-cheese:" .       esc_attr( $tile_cheese )   . ";";
	$css .= "--cb-toppings:" .     esc_attr( $tile_toppings ) . ";";
	$css .= "--cb-drizzle:" .      esc_attr( $tile_drizzle )  . ";";
	$css .= "--cb-cuts:" .         esc_attr( $tile_cuts )     . ";";
	// Mirror category colors to the alternate -c-* variable names also used in template.css
	$css .= "--cb-c-crust:" .      esc_attr( $tile_crust )    . ";";
	$css .= "--cb-c-sauce:" .      esc_attr( $tile_sauce )    . ";";
	$css .= "--cb-c-cheese:" .     esc_attr( $tile_cheese )   . ";";
	$css .= "--cb-c-top:" .        esc_attr( $tile_toppings ) . ";";
	$css .= "--cb-c-driz:" .       esc_attr( $tile_drizzle )  . ";";
	$css .= "--cb-c-slice:" .      esc_attr( $tile_cuts )     . ";";
	$css .= "--cb-radius:" .       $radius    . "px;";
	$css .= "--cb-radius-sm:" .    $radius_sm . "px;";
	$css .= "--cb-radius-lg:" .    $radius    . "px;";
	$css .= "--cb-radius-pill:999px;";
	$css .= "--cb-transition:0.18s ease;";
	$css .= "--cb-shadow-sm:0 4px 12px " . cb_hex2rgba( $text, 0.10 ) . ";";
	$css .= "--cb-font:" .         esc_attr( $font_stack ) . ";";
	$css .= "font-family:" .       esc_attr( $font_stack ) . ";";
	$css .= "font-size:" .         $base_size . "px;";
	$css .= "}";

	wp_add_inline_style( 'pizzatier-template-colorbox', $css ); // phpcs:ignore -- dynamic CSS vars
}
endif;

add_action( 'wp_enqueue_scripts', 'pzt_colorbox_inject_css', 99 );

do_action( 'pizzatier_file_pztp-template-custom_end' );
