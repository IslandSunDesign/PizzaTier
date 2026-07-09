<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-template-css_start' );

/**
 * Fornaia template – dynamic CSS variable overrides from settings.
 * Called by AssetManager / TemplateLoader when this template is active.
 * Returns a CSS string that overrides :root tokens on .rp-root.
 */
function pizzatier_template_rustic_generated_css(): string {
    $g = fn( string $key, string $default = '' ): string =>
        (string) get_option( $key, $default );
    $gb = fn( string $key, string $default = 'yes' ): bool =>
        get_option( $key, $default ) === 'yes';

    $vars = [];

    // Colours
    $map = [
        'rustic_setting_bg_color'           => '--rp-bg',
        'rustic_setting_surface_color'       => '--rp-surface',
        'rustic_setting_card_bg_color'       => '--rp-surface-2',
        'rustic_setting_accent_color'        => '--rp-accent',
        'rustic_setting_gold_color'          => '--rp-gold',
        'rustic_setting_text_color'          => '--rp-text',
        'rustic_setting_muted_text_color'    => '--rp-text-muted',
        'rustic_setting_stepnav_bg'          => '--rp-bg-dark',
        'rustic_setting_pizza_canvas_bg'     => '--rp-canvas-center',
    ];
    foreach ( $map as $option => $cssvar ) {
        $val = sanitize_hex_color( $g( $option ) );
        if ( $val ) { $vars[ $cssvar ] = $val; }
    }

    // Step-nav active colour gets its OWN token so it no longer collides with
    // the global Accent setting (both previously wrote to --rp-accent, which
    // silently let the step-nav value override the Accent / Terracotta setting).
    $stepnav_active = sanitize_hex_color( $g( 'rustic_setting_stepnav_active_color' ) );
    if ( $stepnav_active ) { $vars['--rp-stepnav-active'] = $stepnav_active; }

    // Typography
    $serif = $g( 'rustic_setting_font_serif', 'Georgia' );
    if ( $serif ) { $vars['--rp-font-serif'] = '"' . addslashes( sanitize_text_field( $serif ) ) . '", Georgia, serif'; }

    $font_size = $g( 'rustic_setting_font_size', '15px' );
    if ( $font_size ) { $vars['--rp-font-size'] = sanitize_text_field( $font_size ); }

    // Layout dimensions
    $col_w = (int) $g( 'rustic_setting_preview_col_width', '300' );
    if ( $col_w >= 220 && $col_w <= 420 ) { $vars['--rp-preview-col-width'] = $col_w . 'px'; }

    $canvas_sz = (int) $g( 'rustic_setting_pizza_canvas_size', '250' );
    if ( $canvas_sz >= 160 && $canvas_sz <= 360 ) { $vars['--rp-canvas-size'] = $canvas_sz . 'px'; }

    $card_radius = sanitize_text_field( $g( 'rustic_setting_card_radius', '8px' ) );
    if ( $card_radius ) { $vars['--rp-radius'] = $card_radius; }

    $min_card = (int) $g( 'rustic_setting_cards_per_row', '150' );
    if ( $min_card >= 100 && $min_card <= 300 ) { $vars['--rp-card-min-width'] = $min_card . 'px'; }

    // Button radius from btn_style
    $btn_style = $g( 'rustic_setting_btn_style', 'square' );
    $btn_radius_map = [ 'square' => '4px', 'rounded' => '10px', 'pill' => '999px' ];
    $vars['--rp-btn-radius'] = $btn_radius_map[ $btn_style ] ?? '4px';

    // Build :root override block on .rp-root
    $css = '';
    if ( ! empty( $vars ) ) {
        $css .= '.rp-root{';
        foreach ( $vars as $prop => $val ) {
            $css .= esc_attr( $prop ) . ':' . esc_attr( $val ) . ';';
        }
        $css .= '}';
    }

    // Optional overrides via class toggles
    // Paper grain texture
    if ( ! $gb( 'rustic_setting_show_grain_texture' ) ) {
        $css .= '.rp-root::before{display:none;}';
    }

    // Wood grain stripes on preview column
    if ( ! $gb( 'rustic_setting_show_wood_grain' ) ) {
        $css .= '.rp-pizza-col{background-image:none!important;}';
    }

    // Corner fold — hide ::after on cards
    if ( ! $gb( 'rustic_setting_show_corner_fold', 'yes' ) ) {
        $css .= '.rp-card::after{display:none;}';
    }

    // Card hover lift
    if ( ! $gb( 'rustic_setting_card_hover_lift', 'yes' ) ) {
        $css .= '.rp-card:hover{transform:none!important;}';
    }

    // Lined paper in order summary
    if ( ! $gb( 'rustic_setting_show_lined_paper', 'yes' ) ) {
        $css .= '.rp-yourpizza{background:none!important;}';
    }

    // Step labels visibility
    if ( ! $gb( 'rustic_setting_show_step_labels', 'yes' ) ) {
        $css .= '.rp-step__label{display:none!important;}';
    }

    // Step icons visibility
    if ( ! $gb( 'rustic_setting_show_step_icons', 'yes' ) ) {
        $css .= '.rp-step__icon{display:none!important;}';
    }

    // Vintage badge visibility
    if ( ! $gb( 'rustic_setting_show_badge', 'yes' ) ) {
        $css .= '.rp-preview-badge{display:none!important;}';
    }

    // Preview column explicit bg override
    $preview_bg = sanitize_hex_color( $g( 'rustic_setting_preview_bg', '' ) );
    if ( $preview_bg ) {
        $css .= '.rp-pizza-col{background:' . esc_attr( $preview_bg ) . '!important;background-image:none!important;}';
    }

    // Uppercase buttons
    $uppercase = $g( 'rustic_setting_uppercase_btns', 'yes' );
    if ( $uppercase !== 'yes' ) {
        $css .= '.rp-btn,.pztpro-checkout-bar--rustic .pztpro-add-to-cart-btn{text-transform:none!important;letter-spacing:0.01em!important;}';
    }

    // Apply font-size to root element
    if ( $font_size ) {
        $css .= '.rp-root{font-size:' . esc_attr( $font_size ) . ';}';
    }

    return $css;
}

do_action( 'pizzatier_file_pztp-template-css_end' );
