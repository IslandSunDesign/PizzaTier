<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-template-css_start' );

/**
 * PocketPie — settings-driven generated CSS.
 *
 * Called by FrontendSettings::inject_inline_styles() when PocketPie is the
 * active template. Translates every pocketpie_setting_* option into scoped
 * CSS on .pp-root so the Template Settings page actually affects the
 * front-end (prior to 1.5.0 this returned an empty string and no PocketPie
 * setting did anything).
 */
function pizzatier_template_pocketpie_generated_css() {

    $g = static function ( string $k, string $d = '' ): string {
        return (string) get_option( $k, $d );
    };
    // Clamp a numeric option to a px value within range; '' when unset/zero.
    $px = static function ( string $k, int $min, int $max ) use ( $g ): string {
        $n = (int) preg_replace( '/[^0-9]/', '', $g( $k, '' ) );
        if ( $n <= 0 ) { return ''; }
        return max( $min, min( $max, $n ) ) . 'px';
    };
    // Sanitise a free-text CSS length (px/%/em/rem/vw/vh); '' when invalid.
    $len = static function ( string $k ) use ( $g ): string {
        $v = trim( $g( $k, '' ) );
        return preg_match( '/^\d+(\.\d+)?(px|%|em|rem|vw|vh)$/', $v ) ? $v : '';
    };

    $css = '';

    /* ── Colour theme ──────────────────────────────────────────────── */
    // 'dark-amber' is the template.css default — no override emitted.
    $palettes = [
        'light-slate' => [ '#f4f6f8', '#ffffff', '#eef1f4', 'rgba(71,85,105,0.18)',  '#1e293b', '#64748b', '#475569', '#334155', '#16a34a' ],
        'espresso'    => [ '#2b1d12', '#3a2818', '#4a3320', 'rgba(200,155,106,0.18)','#f1e3d0', '#b59b80', '#c89b6a', '#a0683a', '#7fbf8e' ],
        'forest'      => [ '#11201a', '#182c22', '#20382c', 'rgba(111,174,139,0.18)','#e2efe6', '#8aa896', '#6fae8b', '#4a8a68', '#5bcf80' ],
        'ocean'       => [ '#0c1a2b', '#12253c', '#18304c', 'rgba(56,178,200,0.18)', '#dcebf7', '#7e9ab3', '#38b2c8', '#2a7f9e', '#4ad0a0' ],
        'rose'        => [ '#fdf3f5', '#ffffff', '#fae6ec', 'rgba(212,83,122,0.22)', '#43202c', '#a06a7c', '#d4537a', '#b03a5e', '#2e9e5b' ],
        'mono-light'  => [ '#ffffff', '#f5f5f5', '#ebebeb', 'rgba(0,0,0,0.14)',      '#111111', '#666666', '#111111', '#333333', '#2e7d32' ],
        'mono-dark'   => [ '#0d0d0d', '#181818', '#232323', 'rgba(255,255,255,0.14)','#f2f2f2', '#999999', '#f2f2f2', '#cccccc', '#6fcf8e' ],
    ];
    $theme = sanitize_key( $g( 'pocketpie_setting_theme', 'dark-amber' ) );
    $pal   = null;
    if ( $theme === 'custom' ) {
        $hex = static function ( string $k, string $d ) use ( $g ): string {
            return sanitize_hex_color( $g( $k, $d ) ) ?: $d;
        };
        $pal = [
            $hex( 'pocketpie_setting_color_bg',      '#1a1008' ),
            $hex( 'pocketpie_setting_color_bg2',     '#251808' ),
            $hex( 'pocketpie_setting_color_bg3',     '#2e1e0a' ),
            $hex( 'pocketpie_setting_color_border',  '#2e1e0a' ),
            $hex( 'pocketpie_setting_color_text',    '#f5e8cc' ),
            $hex( 'pocketpie_setting_color_muted',   '#a08060' ),
            $hex( 'pocketpie_setting_color_accent',  '#ff9a1a' ),
            $hex( 'pocketpie_setting_color_accent2', '#e05c28' ),
            $hex( 'pocketpie_setting_color_success', '#5bcf80' ),
        ];
    } elseif ( isset( $palettes[ $theme ] ) ) {
        $pal = $palettes[ $theme ];
    }
    if ( $pal ) {
        [ $bg, $bg2, $bg3, $border, $text, $muted, $accent, $accent2, $success ] = array_map( 'esc_attr', $pal );
        $css .= ".pp-root{--pp-bg:{$bg};--pp-bg-2:{$bg2};--pp-bg-3:{$bg3};--pp-border:{$border};"
              . "--pp-text:{$text};--pp-muted:{$muted};--pp-accent:{$accent};--pp-accent2:{$accent2};--pp-success:{$success};}";
    }

    /* ── Typography ────────────────────────────────────────────────── */
    $font_key = sanitize_key( $g( 'pocketpie_setting_font_family', 'georgia' ) );
    $font     = '';
    switch ( $font_key ) {
        case 'system':  $font = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"; break;
        case 'mono':    $font = "'Courier New', Courier, monospace"; break;
        case 'inherit': $font = 'inherit'; break;
        case 'custom':
            $custom_font = sanitize_text_field( $g( 'pocketpie_setting_font_custom', '' ) );
            if ( $custom_font !== '' ) {
                $font = '"' . esc_attr( $custom_font ) . '", system-ui, sans-serif';
            }
            break;
        // 'georgia' = template.css default — no override.
    }
    if ( $font !== '' ) { $css .= '.pp-root{--pp-font:' . $font . ';}'; }

    $base_size = $px( 'pocketpie_setting_font_base_size', 10, 22 );
    if ( $base_size !== '' && $base_size !== '14px' ) { $css .= '.pp-root{font-size:' . $base_size . ';}'; }

    $transform = sanitize_key( $g( 'pocketpie_setting_label_transform', 'none' ) );
    if ( in_array( $transform, [ 'uppercase', 'capitalize', 'lowercase' ], true ) ) {
        $css .= '.pp-root .pp-cq-trigger__label,.pp-root .pp-sd-pill,.pp-root .pp-ld-deck-thumb__label,'
              . '.pp-root .pp-sp-step__label{text-transform:' . $transform . ';}';
    }

    /* ── Widget width ──────────────────────────────────────────────── */
    $max_w = $len( 'pocketpie_setting_widget_max_width' );
    if ( $max_w !== '' ) {
        $css .= '.pp-root{max-width:' . esc_attr( $max_w ) . ';margin-left:auto;margin-right:auto;}';
    }

    /* ── Per-layout pizza sizes ────────────────────────────────────── */
    $cq = $px( 'pocketpie_setting_pizza_size_cq', 120, 480 );
    if ( $cq !== '' ) {
        $css .= '.pp-root.pp-layout--corner-quad .pp-cq-pizza .pp-pizza-stage-wrap{width:' . $cq . ';height:' . $cq . ';}';
    }
    $ld = $px( 'pocketpie_setting_pizza_size_ld', 160, 420 );
    if ( $ld !== '' ) {
        $css .= '.pp-root .pp-ld-pizza-zone{height:' . $ld . ';max-height:' . $ld . ';aspect-ratio:auto;}';
    }
    $sd = $px( 'pocketpie_setting_pizza_size_sd', 140, 360 );
    if ( $sd !== '' ) {
        $css .= '.pp-root .pp-sd-pizza-zone{height:' . $sd . ';max-height:' . $sd . ';aspect-ratio:auto;}';
    }
    $sp = $px( 'pocketpie_setting_pizza_size_sp', 60, 160 );
    if ( $sp !== '' ) {
        $css .= '.pp-root .pp-sp-pizza-mini,.pp-root .pp-sp-pizza-mini .pp-pizza-stage-wrap{width:' . $sp . ';height:' . $sp . ';}';
    }

    /* ── Corner Quad geometry ──────────────────────────────────────── */
    $cq_trigger = $len( 'pocketpie_setting_cq_trigger_size' );
    if ( $cq_trigger !== '' ) { $css .= '.pp-root .pp-cq-trigger{width:' . esc_attr( $cq_trigger ) . ';}'; }

    $cq_aspect = trim( $g( 'pocketpie_setting_corner_quad_aspect', '' ) );
    if ( $cq_aspect !== '' && preg_match( '/^\d+(\.\d+)?\s*\/\s*\d+(\.\d+)?$/', $cq_aspect ) ) {
        $css .= '.pp-root.pp-layout--corner-quad .pp-cq-wrap{aspect-ratio:' . esc_attr( $cq_aspect ) . ';}';
    }

    /* ── Layer Deck geometry ───────────────────────────────────────── */
    $ld_prev = $px( 'pocketpie_setting_ld_preview_height', 60, 200 );
    if ( $ld_prev !== '' ) { $css .= '.pp-root .pp-ld-expand__preview-img{height:' . $ld_prev . ';}'; }

    $ld_thumb = $len( 'pocketpie_setting_ld_deck_thumb_width' );
    if ( $ld_thumb !== '' ) { $css .= '.pp-root .pp-ld-deck-thumb{min-width:' . esc_attr( $ld_thumb ) . ';}'; }

    if ( $g( 'pocketpie_setting_ld_show_sel_label', 'yes' ) === 'no' ) {
        $css .= '.pp-root .pp-ld-deck-thumb__sel{display:none;}';
    }

    /* ── Slide Drawer / Stack Panel geometry ───────────────────────── */
    $sd_h = $px( 'pocketpie_setting_sd_drawer_max_height', 160, 520 );
    if ( $sd_h !== '' ) { $css .= '.pp-root .pp-sd-drawer--open{max-height:' . $sd_h . ';}'; }

    $sp_h = $px( 'pocketpie_setting_sp_sheet_max_height', 200, 600 );
    if ( $sp_h !== '' ) { $css .= '.pp-root .pp-sp-sheet--open{max-height:' . $sp_h . ';}'; }

    if ( $g( 'pocketpie_setting_sp_show_step_dots', 'yes' ) === 'no' ) {
        $css .= '.pp-root .pp-sp-step-dots{display:none;}';
    }
    if ( $g( 'pocketpie_setting_sp_step_label', 'yes' ) === 'no' ) {
        $css .= '.pp-root .pp-sp-step-label{display:none;}';
    }

    /* ── Chips ─────────────────────────────────────────────────────── */
    $chip_thumb = $len( 'pocketpie_setting_chip_thumb_size' );
    if ( $chip_thumb !== '' ) {
        $css .= '.pp-root .pp-chip__img{width:' . esc_attr( $chip_thumb ) . ';height:' . esc_attr( $chip_thumb ) . ';}';
    }

    $chip_radius = trim( $g( 'pocketpie_setting_chip_radius', '' ) );
    if ( $chip_radius !== '' && preg_match( '/^\d+(px|%)$/', $chip_radius ) ) {
        $css .= '.pp-root .pp-chip{border-radius:' . esc_attr( $chip_radius ) . ';}';
    }

    // Chip grid uses flex + 8px gap: width = (100% − (n−1)·gap) / n
    $cols_rule = static function ( string $sel, int $n ): string {
        if ( $n === 1 ) { return $sel . '{width:100%;}'; }
        $gap = ( $n - 1 ) * 8;
        return $sel . '{width:calc((100% - ' . $gap . 'px)/' . $n . ');min-width:0;}';
    };
    $chip_cols = $g( 'pocketpie_setting_chip_cols', 'auto' );
    if ( in_array( $chip_cols, [ '2', '3', '4' ], true ) ) {
        $css .= $cols_rule( '.pp-root .pp-chips-grid .pp-chip', (int) $chip_cols );
    }
    $top_cols = $g( 'pocketpie_setting_toppings_cols', '2' );
    if ( in_array( $top_cols, [ '1', '2', '3' ], true ) && $top_cols !== '2' ) {
        // '2' is the template.css default for toppings — only override others.
        $css .= $cols_rule( '.pp-root .pp-chips-grid--toppings .pp-chip', (int) $top_cols );
    }
    if ( $g( 'pocketpie_setting_chip_show_name', 'yes' ) === 'no' ) {
        $css .= '.pp-root .pp-chip__name{display:none;}';
    }

    /* ── Slide Drawer pill bar position & style ────────────────────── */
    // Variants keyed off the pp-sd-pills-pos--* / pp-sd-pill-style--* root
    // classes rendered by pztp-containers-menu.php. Defaults
    // (bottom-overlay / pill) are the template.css baseline — no override.
    $css .= '.pp-root.pp-sd-pills-pos--top-overlay .pp-sd-pills{top:0;bottom:auto;'
          . 'background:linear-gradient(rgba(0,0,0,0.45) 40%,transparent);padding:6px 8px 20px;}'
          . '.pp-root.pp-sd-pills-pos--below-pizza .pp-sd-pizza-zone{padding-bottom:44px;}'
          . '.pp-root.pp-sd-pills-pos--below-pizza .pp-sd-pills{background:var(--pp-bg-2);'
          . 'border-top:1px solid var(--pp-border);padding:6px 8px;}';

    $css .= '.pp-root.pp-sd-pill-style--square .pp-sd-pill{border-radius:var(--pp-radius-sm);}'
          . '.pp-root.pp-sd-pill-style--icon .pp-sd-pill .pp-sd-pill__text{display:none;}'
          . '.pp-root.pp-sd-pill-style--icon .pp-sd-pill{padding:5px 8px;}'
          . '.pp-root.pp-sd-pill-style--text .pp-sd-pill .pp-sd-pill__icon{display:none;}';

    /* ── Modal backdrop / animation ────────────────────────────────── */
    $backdrop = sanitize_key( $g( 'pocketpie_setting_modal_backdrop', 'blur' ) );
    if ( $backdrop === 'dark' ) {
        $css .= '.pp-root .pp-modal-overlay{backdrop-filter:none;-webkit-backdrop-filter:none;}';
    } elseif ( $backdrop === 'none' ) {
        $css .= '.pp-root .pp-modal-overlay{backdrop-filter:none;-webkit-backdrop-filter:none;background:transparent;}';
    }

    // Modal animation variants — keyed off the pp-modal-anim--* root class
    // rendered by pztp-containers-menu.php. 'scale-fade' is the CSS default.
    $css .= '.pp-root.pp-modal-anim--slide-up .pp-modal{transform:translate(-50%,-38%);}'
          . '.pp-root.pp-modal-anim--slide-up .pp-modal--open{transform:translate(-50%,-50%);}'
          . '.pp-root.pp-modal-anim--fade .pp-modal,.pp-root.pp-modal-anim--fade .pp-modal--open{transform:translate(-50%,-50%);}'
          . '.pp-root.pp-modal-anim--instant .pp-modal,.pp-root.pp-modal-anim--instant .pp-modal-overlay{transition:none!important;}'
          . '.pp-root.pp-modal-anim--instant .pp-modal{transform:translate(-50%,-50%);}';

    /* ── Transition speed ──────────────────────────────────────────── */
    $speed = sanitize_key( $g( 'pocketpie_setting_transition_speed', 'normal' ) );
    $speed_map = [
        'fast'    => [ '0.18s cubic-bezier(0.4,0,0.2,1)', '0.2s cubic-bezier(0.34,1.56,0.64,1)' ],
        'slow'    => [ '0.5s cubic-bezier(0.4,0,0.2,1)',  '0.6s cubic-bezier(0.34,1.56,0.64,1)' ],
        'instant' => [ '0s', '0s' ],
    ];
    if ( isset( $speed_map[ $speed ] ) ) {
        $css .= '.pp-root{--pp-trans:' . $speed_map[ $speed ][0] . ';--pp-trans-spring:' . $speed_map[ $speed ][1] . ';}';
        if ( $speed === 'instant' ) {
            $css .= '.pp-root .pp-sd-drawer,.pp-root .pp-sp-sheet,.pp-root .pp-cq-panel,'
                  . '.pp-root .pp-modal,.pp-root .pp-modal-overlay{transition:none!important;}';
        }
    }

    /* ── Chip hover lift ───────────────────────────────────────────── */
    if ( $g( 'pocketpie_setting_chip_hover_anim', 'yes' ) === 'no' ) {
        $css .= '.pp-root .pp-chip:hover{transform:none;}';
    }

    return $css;
}

do_action( 'pizzatier_file_pztp-template-css_end' );
