<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.
do_action( 'pizzatier_file_pztp-template-custom_start' );

/**
 * NightPie template — shared PHP helpers + settings-driven CSS injection.
 *
 * Runs once per page load via TemplateLoader::load_template_custom().
 * Reads all nightpie_setting_* options and emits an inline <style> block
 * that overrides CSS custom properties on .np-root.
 */

/* ── Helpers ─────────────────────────────────────────────────────── */

if ( ! function_exists( 'np_hex2rgba' ) ) {
	function np_hex2rgba( string $color, float $alpha ): string {
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
	function hex2rgba( $color, $alpha ) { return np_hex2rgba( (string) $color, (float) $alpha ); }
}

if ( ! function_exists( 'pzt_nightpie_get_font_stack' ) ) :
function pzt_nightpie_get_font_stack( string $key ): string {
	$map = [
		'system'     => "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
		'inter'      => "'Inter', system-ui, sans-serif",
		'poppins'    => "'Poppins', system-ui, sans-serif",
		'montserrat' => "'Montserrat', system-ui, sans-serif",
		'roboto'     => "'Roboto', system-ui, sans-serif",
	];
	return $map[ $key ] ?? $map['system'];
}
endif;

if ( ! function_exists( 'pzt_nightpie_inject_css' ) ) :
function pzt_nightpie_inject_css(): void {
	$g = function( string $k, string $d = '' ) { return (string) get_option( $k, $d ); };

	// ── Read all settings with safe defaults matching template.css ──
	$accent       = sanitize_hex_color( $g( 'nightpie_setting_accent_color',         '#ff5722' ) ) ?: '#ff5722';
	$bg           = sanitize_hex_color( $g( 'nightpie_setting_bg_color',             '#0e0e12' ) ) ?: '#0e0e12';
	$surface      = sanitize_hex_color( $g( 'nightpie_setting_surface_color',        '#18181f' ) ) ?: '#18181f';
	$surface_2    = sanitize_hex_color( $g( 'nightpie_setting_surface_2_color',      '#22222c' ) ) ?: '#22222c';
	$text         = sanitize_hex_color( $g( 'nightpie_setting_text_color',           '#f0f0f4' ) ) ?: '#f0f0f4';
	$text_muted   = sanitize_hex_color( $g( 'nightpie_setting_text_muted_color',     '#888898' ) ) ?: '#888898';

	$font_key     = sanitize_key( $g( 'nightpie_setting_font_family', 'system' ) );
	$font_stack   = pzt_nightpie_get_font_stack( $font_key );

	$base_size    = max( 12, min( 20, (int) $g( 'nightpie_setting_base_font_size', '15' ) ) );
	$radius       = max(  0, min( 28, (int) $g( 'nightpie_setting_corner_radius',  '16' ) ) );

	$sticky       = $g( 'nightpie_setting_sticky_preview', 'yes' ) === 'yes';
	$accent_glow  = $g( 'nightpie_setting_accent_glow',    'yes' ) === 'yes';

	// Item card border: off by default (transparent). When enabled, use the
	// chosen colour; selected/hover states keep their own accent borders.
	$card_border_on = $g( 'nightpie_setting_card_border', 'no' ) === 'yes';
	$card_border_c  = sanitize_hex_color( $g( 'nightpie_setting_card_border_color', '#2e2e3a' ) ) ?: '#2e2e3a';
	$card_border    = $card_border_on ? $card_border_c : 'transparent';

	// ── Derive dependent values ─────────────────────────────────────
	$accent_dim         = np_hex2rgba( $accent, 0.15 );
	$accent_glow_color  = np_hex2rgba( $accent, 0.35 );

	// Proportional small/large radius (template defaults: sm=10, base=16, lg=24).
	$radius_sm = max( 0, (int) round( $radius * 0.625 ) );
	$radius_lg = (int) round( $radius * 1.5 );

	// ── Build CSS ───────────────────────────────────────────────────
	// CSS custom properties redefined on .np-root override the :root tokens
	// for everything inside the builder (proximity wins, regardless of source
	// order). font-family/font-size are also set directly because template.css
	// later remaps .np-root font-family/size to --pzl-* tokens; this inline
	// block is appended AFTER template.css (same handle) so it wins.
	$css  = ".np-root {";
	$css .= "--np-accent:" .         esc_attr( $accent )            . ";";
	$css .= "--np-accent-dim:" .     esc_attr( $accent_dim )        . ";";
	$css .= "--np-accent-glow:" .    esc_attr( $accent_glow_color ) . ";";
	$css .= "--np-bg:" .             esc_attr( $bg )                . ";";
	$css .= "--np-surface:" .        esc_attr( $surface )           . ";";
	$css .= "--np-surface-2:" .      esc_attr( $surface_2 )         . ";";
	$css .= "--np-text:" .           esc_attr( $text )              . ";";
	$css .= "--np-text-muted:" .     esc_attr( $text_muted )        . ";";
	$css .= "--np-radius-sm:" .      $radius_sm . "px;";
	$css .= "--np-radius:" .         $radius    . "px;";
	$css .= "--np-radius-lg:" .      $radius_lg . "px;";
	$css .= "--np-card-border:" .    esc_attr( $card_border )       . ";";
	$css .= "--np-font:" .           esc_attr( $font_stack )        . ";";
	// --pzl-font feeds template.css's `.np-root{font-family:var(--pzl-font,...)}`
	// remap; setting font-family directly as well guarantees the chosen stack
	// applies regardless of which rule wins the cascade.
	$css .= "--pzl-font:" .          esc_attr( $font_stack )        . ";";
	$css .= "font-family:" .         esc_attr( $font_stack )        . ";";
	$css .= "font-size:" .           $base_size . "px;";
	$css .= "}";

	// Background Color — template.css applies a hard !important gradient to
	// .np-root, so a flat-colour override only fires when the user actually
	// changes this away from the default (keeping the designed gradient by
	// default). Emitted with !important to beat that gradient.
	if ( $bg !== '#0e0e12' ) {
		$css .= ".np-root{background:" . esc_attr( $bg ) . " !important;}";
	}

	// Sticky preview toggle. The static override beats the desktop sticky rule
	// (which lives inside a min-width media query) via .np-root specificity,
	// so it is breakpoint-independent.
	if ( ! $sticky ) {
		$css .= ".np-root .np-pizza-col{position:static !important;max-height:none !important;}";
	}

	// Accent Glow toggle (off = flatter look). Zero the glow token AND clear the
	// few box-shadows in template.css that use hard-coded rgba accent glows.
	if ( ! $accent_glow ) {
		$css .= ".np-root{--np-accent-glow:rgba(0,0,0,0);}";
		$css .= ".np-root .np-pizza-sticky{box-shadow:0 8px 32px rgba(0,0,0,0.50),inset 0 1px 0 rgba(255,255,255,0.04);}";
		$css .= ".np-root .np-section-nav__btn--next{box-shadow:none;}";
		$css .= ".np-root .np-size-option:hover,.np-root .np-size-option.pztpro-size-option--active,.np-root .pztpro-size-option--active{box-shadow:0 0 0 1px var(--np-accent,#ff5722);}";
		$css .= ".pztpro-checkout-bar--nightpie .pztpro-add-to-cart-btn,.pztpro-checkout-bar--nightpie .pztpro-bar-row__btn{box-shadow:none;}";
		$css .= ".pztpro-checkout-bar--nightpie .pztpro-bar-row__price{text-shadow:none;}";
	}

	wp_add_inline_style( 'pizzatier-template-nightpie', $css ); // phpcs:ignore -- dynamic CSS vars
}
endif;

add_action( 'wp_enqueue_scripts', 'pzt_nightpie_inject_css', 99 );

do_action( 'pizzatier_file_pztp-template-custom_end' );
