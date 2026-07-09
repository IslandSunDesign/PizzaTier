<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-template-custom_start' );

/**
 * Metro template — shared PHP helpers + settings-driven CSS injection.
 *
 * This file runs once on wp_enqueue_scripts (via TemplateLoader::load_template_custom).
 * It reads all metro_setting_* options and injects a <style> block that
 * overrides CSS custom properties on .mt-root, ensuring every setting
 * propagates to the front-end without touching template.css.
 */

/* ── Helpers ─────────────────────────────────────────────────────── */

if ( ! function_exists( 'mt_hex2rgba' ) ) {
	/**
	 * Convert a hex colour + alpha to an rgba() string.
	 */
	function mt_hex2rgba( string $color, float $alpha ): string {
		$color = ltrim( $color, '#' );
		if ( strlen( $color ) === 3 ) {
			$color = $color[0].$color[0].$color[1].$color[1].$color[2].$color[2];
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
	function hex2rgba( $color, $alpha ) { return mt_hex2rgba( (string) $color, (float) $alpha ); }
}

/* ── Read all metro settings ─────────────────────────────────────── */

$mt_accent         = sanitize_hex_color( get_option( 'metro_setting_accent_color',           '#e63946' ) ) ?: '#e63946';
$mt_bg             = sanitize_hex_color( get_option( 'metro_setting_background_color',        '#f7f7f5' ) ) ?: '#f7f7f5';
$mt_ui_bg          = sanitize_hex_color( get_option( 'metro_setting_ui_bg_color',            '#ffffff' ) ) ?: '#ffffff';
$mt_bg_image       = esc_url_raw( (string) get_option( 'metro_setting_container_bg_image',     '' ) );
$mt_card_bg        = sanitize_hex_color( get_option( 'metro_setting_card_bg_color',           '#ffffff' ) ) ?: '#ffffff';
$mt_card_text      = sanitize_hex_color( get_option( 'metro_setting_card_text_color',         '#1a1a1a' ) ) ?: '#1a1a1a';
$mt_title          = sanitize_hex_color( get_option( 'metro_setting_title_color',             '#1a1a1a' ) ) ?: '#1a1a1a';
$mt_border         = sanitize_hex_color( get_option( 'metro_setting_border_color',            '#e4e4e0' ) ) ?: '#e4e4e0';
$mt_show_borders   =                     get_option( 'metro_setting_show_borders',            'yes'     ) === 'yes';
$mt_heading_font   = sanitize_key(       get_option( 'metro_setting_heading_font',            'system'  ) );
$mt_font_size      = (int)               get_option( 'metro_setting_base_font_size',           14        );
$mt_layout         = sanitize_key(       get_option( 'metro_setting_layout_mode',             'centered') );
$mt_columns        = sanitize_key(       get_option( 'metro_setting_card_columns',            '3'       ) );
$mt_viz_size       = (int)               get_option( 'metro_setting_visualizer_size',          0         );
$mt_show_tray      =                     get_option( 'metro_setting_show_summary_bar',         'yes'     ) === 'yes';
$mt_sticky_viz     =                     get_option( 'metro_setting_sticky_visualizer',        'no'      ) === 'yes';
$mt_show_count     =                     get_option( 'metro_setting_show_ingredient_count',    'yes'     ) === 'yes';
$mt_section_gap    = (int)               get_option( 'metro_setting_section_gap',              24        );
$mt_card_radius    = (int)               get_option( 'metro_setting_card_border_radius',       14        );
$mt_card_style     = sanitize_key(       get_option( 'metro_setting_card_style',               'standard') );
$mt_tab_style      = sanitize_key(       get_option( 'metro_setting_tab_style',                'scrollbar') );
$mt_font_size      = max( 12, min( 20, $mt_font_size ) );
$mt_section_gap    = max(  8, min( 60, $mt_section_gap ) );
$mt_card_radius    = max(  0, min( 24, $mt_card_radius ) );

/* ── Font map ─────────────────────────────────────────────────────── */

$mt_font_stacks = [
	'system'     => "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
	'inter'      => "'Inter', system-ui, sans-serif",
	'poppins'    => "'Poppins', system-ui, sans-serif",
	'montserrat' => "'Montserrat', system-ui, sans-serif",
	'playfair'   => "'Playfair Display', Georgia, serif",
];
$mt_google_fonts = [
	'inter'      => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
	'poppins'    => 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
	'montserrat' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
	'playfair'   => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap',
];

$mt_font_stack = $mt_font_stacks[ $mt_heading_font ] ?? $mt_font_stacks['system'];

/* ── Enqueue Google Font if needed ───────────────────────────────── */

if ( isset( $mt_google_fonts[ $mt_heading_font ] ) ) {
	// This file is included during wp_enqueue_scripts:10 (TemplateLoader).
	// A nested add_action('wp_enqueue_scripts', …, 10) registered while that
	// same priority is executing never fires (WP_Hook iterates a copy), so
	// the font silently failed to load — enqueue it directly instead.
	wp_enqueue_style(
		'mt-google-font-' . $mt_heading_font,
		$mt_google_fonts[ $mt_heading_font ],
		[],
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
	);
}

/* ── Derive dependent colour values ─────────────────────────────── */

// Slightly darken accent for hover: shift each channel -18
$darken = function( string $hex, int $amount = 18 ): string {
	$hex = ltrim( $hex, '#' );
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
	}
	$r = max( 0, hexdec( substr( $hex, 0, 2 ) ) - $amount );
	$g = max( 0, hexdec( substr( $hex, 2, 2 ) ) - $amount );
	$b = max( 0, hexdec( substr( $hex, 4, 2 ) ) - $amount );
	return sprintf( '#%02x%02x%02x', $r, $g, $b );
};
$mt_accent_hover = $darken( $mt_accent, 18 );
$mt_accent_dim   = mt_hex2rgba( $mt_accent, 0.10 );
$mt_border_hover = $darken( $mt_border, 28 );

/* ── Column map: setting value → CSS minmax width ────────────────── */

$mt_col_widths = [
	'2'    => '220px',
	'3'    => '160px',
	'4'    => '130px',
	'auto' => '140px',
];
$mt_card_min_w = $mt_col_widths[ $mt_columns ] ?? '160px';

/* ── Visualizer size ─────────────────────────────────────────────── */

$mt_hero_size = $mt_viz_size > 0
	? 'min(' . $mt_viz_size . 'px, 90vw)'
	: 'min(340px, 80vw)';

/* ── Output scoped CSS variable overrides ────────────────────────── */
/*
 * Previously this was hooked to wp_head, which had two fatal problems:
 *  1. Several rules were echo'd raw into <head> with no <style> wrapper,
 *     so they appeared as garbage text instead of applying.
 *  2. wp_add_inline_style() ran after styles were already printed
 *     (wp_print_styles fires at wp_head:8), so the CSS-vars block was
 *     silently discarded.
 * Everything is now built into one string and attached on
 * wp_enqueue_scripts:99 — this file loads during wp_enqueue_scripts:10,
 * and later priorities registered mid-hook still fire.
 */
add_action( 'wp_enqueue_scripts', function() use (
	$mt_accent, $mt_accent_hover, $mt_accent_dim,
	$mt_bg, $mt_ui_bg, $mt_card_bg, $mt_card_text, $mt_title,
	$mt_border, $mt_border_hover, $mt_show_borders,
	$mt_font_stack, $mt_font_size,
	$mt_hero_size, $mt_card_min_w, $mt_card_radius,
	$mt_section_gap,
	$mt_show_tray, $mt_sticky_viz, $mt_show_count,
	$mt_layout, $mt_card_style, $mt_tab_style, $mt_bg_image
) {
	// CSS variable overrides
	$accent_esc       = esc_attr( $mt_accent );
	$accent_hover_esc = esc_attr( $mt_accent_hover );
	$accent_dim_esc   = esc_attr( $mt_accent_dim );
	$bg_esc           = esc_attr( $mt_bg );
	$ui_bg_esc        = esc_attr( $mt_ui_bg );
	$card_bg_esc      = esc_attr( $mt_card_bg );
	$card_text_esc    = esc_attr( $mt_card_text );
	$title_esc        = esc_attr( $mt_title );
	$border_esc       = esc_attr( $mt_show_borders ? $mt_border       : 'transparent' );
	$border_hover_esc = esc_attr( $mt_show_borders ? $mt_border_hover : 'transparent' );
	$font_esc         = esc_attr( $mt_font_stack );
	$font_size_esc    = (int) $mt_font_size;
	$hero_esc         = esc_attr( $mt_hero_size );
	$card_min_w_esc   = esc_attr( $mt_card_min_w );
	$card_r_esc       = (int) $mt_card_radius;
	$gap_esc          = (int) $mt_section_gap;

	$_mt_css = "";
	$_mt_css .= ".mt-root {\n";
	$_mt_css .= "  --mt-accent:         {$accent_esc};\n";
	$_mt_css .= "  --mt-accent-hover:   {$accent_hover_esc};\n";
	$_mt_css .= "  --mt-accent-dim:     {$accent_dim_esc};\n";
	$_mt_css .= "  --mt-bg:             {$bg_esc};\n";
	$_mt_css .= "  --mt-ui-bg:          {$ui_bg_esc};\n";
	// Misc chrome (search field, summary tray, section navs, modals) follows the
	// UI container colour so it always sits on the same surface as the panel.
	$_mt_css .= "  --mt-surface:        {$ui_bg_esc};\n";
	$_mt_css .= "  --mt-card-bg:        {$card_bg_esc};\n";
	$_mt_css .= "  --mt-card-text:      {$card_text_esc};\n";
	$_mt_css .= "  --mt-title:          {$title_esc};\n";
	$_mt_css .= "  --mt-border:         {$border_esc};\n";
	$_mt_css .= "  --mt-border-hover:   {$border_hover_esc};\n";
	$_mt_css .= "  --mt-font:           {$font_esc};\n";
	$_mt_css .= "  --mt-hero-pizza-size:{$hero_esc};\n";
	$_mt_css .= "  --mt-card-w:         {$card_min_w_esc};\n";
	$_mt_css .= "  --mt-radius:         {$card_r_esc}px;\n";
	$_mt_css .= "  font-size:           {$font_size_esc}px;\n";
	$_mt_css .= "}\n";

	// Full-container background image (layered over --mt-bg color).
	if ( $mt_bg_image !== '' ) {
		$bg_img_esc = esc_url( $mt_bg_image );
		$_mt_css .= ".mt-root {\n";
		$_mt_css .= "  background-image:    url('{$bg_img_esc}');\n";
		$_mt_css .= "  background-size:     cover;\n";
		$_mt_css .= "  background-position: center center;\n";
		$_mt_css .= "  background-repeat:   no-repeat;\n";
		$_mt_css .= "  background-attachment: scroll;\n";
		$_mt_css .= "}\n";
	}

	// Section gap
	$_mt_css .= ".mt-root .mt-section { padding-top:{$gap_esc}px; padding-bottom:{$gap_esc}px; }\n";

	// Hide tray if disabled
	if ( ! $mt_show_tray ) {
		$_mt_css .= ".mt-root { padding-bottom: 0 !important; }\n";
		$_mt_css .= ".mt-root .mt-tray { display: none !important; }\n";
	}

	// Hide topping count badge if disabled
	if ( ! $mt_show_count ) {
		$_mt_css .= ".mt-root .mt-hero__meta { display: none !important; }\n";
		$_mt_css .= ".mt-root .mt-section__badge--toppings { display: none !important; }\n";
		$_mt_css .= ".mt-root .mt-orb__count { display: none !important; }\n";
	}

	// Sticky visualizer
	if ( $mt_sticky_viz ) {
		$_mt_css .= ".mt-root.mt-layout--centered .mt-hero__pizza-wrap,\n";
		$_mt_css .= ".mt-root.mt-layout--side-by-side .mt-sidebar__pizza-wrap {\n";
		$_mt_css .= "  position: sticky; top: 16px;\n";
		$_mt_css .= "}\n";
	}

	// Note: ingredient price display removed in PizzaTier 1.2.0 — pricing
	// is now provided by PizzaTierPro and rendered separately by Pro.

	// Layout mode classes
	if ( $mt_layout === 'side-by-side' ) {
		$_mt_css .= ".mt-root.mt-layout--side-by-side .mt-hero { display: none; }\n";
		$_mt_css .= ".mt-root.mt-layout--side-by-side .mt-builder-wrap {\n";
		$_mt_css .= "  display: flex; flex-direction: row; align-items: flex-start; gap: 0;\n";
		$_mt_css .= "}\n";
		$_mt_css .= ".mt-root.mt-layout--side-by-side .mt-sidebar {\n";
		$_mt_css .= "  display: flex; flex-direction: column; align-items: center;\n";
		$_mt_css .= "  width: 340px; flex-shrink: 0;\n";
		$_mt_css .= "  padding: 24px 20px;\n";
		$_mt_css .= "  background: var(--mt-surface);\n";
		$_mt_css .= "  border-right: 1px solid var(--mt-border);\n";
		$_mt_css .= "  position: sticky; top: 0; align-self: flex-start;\n";
		$_mt_css .= "  max-height: 100vh; overflow-y: auto;\n";
		$_mt_css .= "}\n";
		$_mt_css .= ".mt-root.mt-layout--side-by-side .mt-builder { flex: 1; min-width: 0; }\n";
	} elseif ( $mt_layout === 'fullwidth' ) {
		$_mt_css .= ".mt-root.mt-layout--fullwidth .mt-hero { position: sticky; top: 0; z-index: 100; }\n";
		$_mt_css .= ".mt-root.mt-layout--fullwidth .mt-hero__pizza-wrap { width: min(200px,40vw); height: min(200px,40vw); }\n";
		$_mt_css .= ".mt-root.mt-layout--fullwidth .mt-hero__inner { flex-direction: row; justify-content: center; max-width: 100%; }\n";
	}

	// Card style modifier class
	$card_style_esc = esc_attr( $mt_card_style );
	$tab_style_esc  = esc_attr( $mt_tab_style );
	$_mt_css .= ".mt-root { --mt-card-style: '{$card_style_esc}'; --mt-tab-style: '{$tab_style_esc}'; }\n";

	wp_add_inline_style( 'pizzatier-template-metro', $_mt_css );
}, 99 );

do_action( 'pizzatier_file_pztp-template-custom_end' );
