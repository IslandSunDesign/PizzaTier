<?php
/**
 * Command Center Template — [pizza_builder] output.
 *
 * Dark navy UI with numbered step wizard, persistent order summary sidebar,
 * and a bold red accent. Designed for large pizza chain ordering flows.
 *
 * Variables supplied by BuilderShortcode:
 *   $instance_id   string  unique builder ID, e.g. "pizzabuilder-1"
 *   $atts          array   shortcode attributes
 *   $template_slug string  'commandcenter'
 *   $function_prefix string 'pzt_commandcenter'
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

if ( ! isset( $instance_id ) )     { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )            { $atts           = []; }
if ( ! isset( $template_slug ) )   { $template_slug  = 'commandcenter'; }
if ( ! isset( $function_prefix ) ) { $function_prefix = 'pzt_commandcenter'; }

/* Per-instance JS namespace: CC_pizzabuilder1, CC_pztpro2, etc. */
$cc_var = 'CC_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

/* ── Max toppings ──────────────────────────────────────────────────────── */
$max_toppings = isset( $atts['max_toppings'] ) && (int) $atts['max_toppings'] > 0
    ? (int) $atts['max_toppings']
    : (int) get_option( 'pizzatier_setting_topping_maxtoppings', 0 );
if ( $max_toppings < 1 ) { $max_toppings = 99; }
$max_toppings = (int) apply_filters( 'pizzatier_max_toppings', $max_toppings, $instance_id );

/* ── Pizza shape ──────────────────────────────────────────────────────── */
$valid_shapes = [ 'round', 'square', 'rectangle', 'custom' ];
$pizza_shape  = sanitize_key( $atts['pizza_shape'] ?? get_option( 'pizzatier_setting_pizza_shape', 'round' ) );
if ( ! in_array( $pizza_shape, $valid_shapes, true ) ) { $pizza_shape = 'round'; }
$pizza_aspect = sanitize_text_field( $atts['pizza_aspect'] ?? get_option( 'pizzatier_setting_pizza_aspect', '1 / 1' ) );
$pizza_radius = sanitize_text_field( $atts['pizza_radius'] ?? get_option( 'pizzatier_setting_pizza_radius', '8px' ) );

/* ── Layer animation ──────────────────────────────────────────────────── */
$valid_anims      = [ 'fade', 'scale-in', 'slide-up', 'flip-in', 'drop-in', 'instant' ];
$layer_anim       = sanitize_key( $atts['layer_anim'] ?? get_option( 'pizzatier_setting_layer_anim', 'fade' ) );
if ( ! in_array( $layer_anim, $valid_anims, true ) ) { $layer_anim = 'fade'; }
$layer_anim_speed = isset( $atts['layer_anim_speed'] ) && (int) $atts['layer_anim_speed'] > 0
    ? max( 80, min( 800, (int) $atts['layer_anim_speed'] ) )
    : max( 80, min( 800, (int) get_option( 'pizzatier_setting_layer_anim_speed', 320 ) ) );

/* ── PizzaTierPro: inline size selector helpers ──────────────────────── */
if ( ! function_exists( 'pzt_get_pro_sizes' ) ) :
function pzt_get_pro_sizes(): array {
    if ( ! function_exists( 'pztpro_get_setting' ) || ! class_exists( 'PizzaTierPro\\Pro\\PriceGrid\\Grid' ) ) {
        return [];
    }
    $product_id = ( function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0 );
    if ( ! $product_id ) { global $post; if ( $post instanceof \WP_Post ) { $product_id = $post->ID; } }
    $grid = new \PizzaTierPro\Pro\PriceGrid\Grid();
    return $grid->get_sizes( $product_id );
}
endif;

if ( ! function_exists( 'pzt_render_inline_size_selector' ) ) :
function pzt_render_inline_size_selector( array $sizes, string $instance_id, string $css_prefix = 'cb' ): void {
    if ( empty( $sizes ) ) { return; }
    preg_match( '/-(\\d+)$/', $instance_id, $_m_suf );
    $radio_name_raw = ! empty( $_m_suf[1] ) ? $_m_suf[1] : preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );
    $radio_name = 'pztpro_size_' . $radio_name_raw;
    $heading = function_exists( 'pztpro_get_setting' ) ? (string) pztpro_get_setting( 'size_selector_label', '' ) : '';
    if ( '' === $heading ) { $heading = __( 'Choose a Size', 'pizzatier' ); }
    ?>
    <div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector pztpro-inline-size-selector"
         id="<?php echo esc_attr( $instance_id ); ?>-size-selector"
         role="group" aria-label="<?php echo esc_attr( $heading ); ?>">
        <p class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__heading"><?php echo esc_html( $heading ); ?></p>
        <div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__options">
            <?php foreach ( $sizes as $i => $size ) :
                $inp_id = esc_attr( $instance_id ) . '-sz-' . sanitize_html_class( strtolower( $size ) );
            ?>
            <label class="<?php echo esc_attr( $css_prefix ); ?>-size-option pztpro-size-option<?php echo 0 === $i ? ' pztpro-size-option--active' : ''; ?>"
                   for="<?php echo esc_attr( $inp_id ); ?>">
                <input type="radio"
                       id="<?php echo esc_attr( $inp_id ); ?>"
                       name="<?php echo esc_attr( $radio_name ); ?>"
                       value="<?php echo esc_attr( $size ); ?>"
                       class="pztpro-size-radio"
                       <?php checked( 0, $i ); ?> />
                <span class="<?php echo esc_attr( $css_prefix ); ?>-size-option__name"><?php echo esc_html( $size ); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
endif;

$_pro_sizes = pzt_get_pro_sizes();
$_has_pro   = ! empty( $_pro_sizes );

/* ── Visible tabs ─────────────────────────────────────────────────────── */
$hide_tabs_raw = $atts['hide_tabs'] ?? '';
$show_tabs_raw = $atts['show_tabs'] ?? '';
$all_tabs      = array_merge(
    $_has_pro ? [ 'size' ] : [],
    [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ]
);
$all_tabs = (array) apply_filters( 'pizzatier_tab_order', $all_tabs, $instance_id );

if ( $show_tabs_raw ) {
    $visible_tabs = array_values( array_intersect( $all_tabs, array_map( 'trim', explode( ',', $show_tabs_raw ) ) ) );
} elseif ( $hide_tabs_raw ) {
    $visible_tabs = array_values( array_diff( $all_tabs, array_map( 'trim', explode( ',', $hide_tabs_raw ) ) ) );
} else {
    $visible_tabs = $all_tabs;
}

/* ── CPT queries ──────────────────────────────────────────────────────── */
$_q_base  = [
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
];
$crusts   = (array) apply_filters( 'pizzatier_query_args_crusts',   get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_crusts'   ] ) ), 'crusts'   );
$sauces   = (array) apply_filters( 'pizzatier_query_args_sauces',   get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_sauces'   ] ) ), 'sauces'   );
$cheeses  = (array) apply_filters( 'pizzatier_query_args_cheeses',  get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_cheeses'  ] ) ), 'cheeses'  );
$drizzles = (array) apply_filters( 'pizzatier_query_args_drizzles', get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_drizzles' ] ) ), 'drizzles' );
$toppings = (array) apply_filters( 'pizzatier_query_args_toppings', get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_toppings' ] ) ), 'toppings' );
$cuts     = (array) apply_filters( 'pizzatier_query_args_cuts',     get_posts( array_merge( $_q_base, [ 'post_type' => 'pizzatier_cuts'     ] ) ), 'cuts'     );

/* ── Card render helpers ──────────────────────────────────────────────── */

/**
 * Render a single exclusive-select card (crust/sauce/cheese/drizzle/cut).
 */
if ( ! function_exists( 'pzt_commandcenter_exclusive_card' ) ) :
function pzt_commandcenter_exclusive_card( $post, string $layer_type, string $cc_var, int $zindex = 200 ): string {
    if ( ! ( $post instanceof \WP_Post ) ) { return ''; }
    $id        = $post->ID;
    $title     = get_the_title( $post );
    $slug      = sanitize_title( $title );
    $img_field = $layer_type . '_image';
    $lyr_field = $layer_type . '_layer_image';

    $thumb_url = pzl_get_field( $img_field, $id ) ?: pzl_get_field( $lyr_field, $id ) ?: (string) get_the_post_thumbnail_url( $id, 'medium' );
    $layer_url = pzl_get_field( $lyr_field, $id ) ?: $thumb_url;

    $js_title  = esc_js( $title );
    $js_layer  = esc_js( (string) $layer_url );
    $js_add    = "window['{$cc_var}']&&window['{$cc_var}'].swapBase('{$layer_type}','{$slug}','{$js_title}','{$js_layer}',this)";
    $js_remove = "window['{$cc_var}']&&window['{$cc_var}'].removeBase('{$layer_type}','{$slug}',this)";

    ob_start();
    do_action( 'pizzatier_before_layer_card', $post, $layer_type );
    ?>
    <div class="cc-card cc-card--exclusive"
         data-layer="<?php echo esc_attr( $layer_type ); ?>"
         data-slug="<?php echo esc_attr( $slug ); ?>"
         data-title="<?php echo esc_attr( $title ); ?>"
         data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
         data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>">
        <div class="cc-card__media">
            <?php if ( $thumb_url ) : ?>
                <img class="cc-card__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
            <?php else : ?>
                <div class="cc-card__img cc-card__img--placeholder"></div>
            <?php endif; ?>
            <div class="cc-card__check" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 8 6.5 11.5 13 5"/></svg>
            </div>
        </div>
        <div class="cc-card__body">
            <span class="cc-card__name"><?php echo esc_html( $title ); ?></span>
        </div>
        <div class="cc-card__actions">
            <button type="button" class="cc-btn cc-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="8" y1="3" x2="8" y2="13"/><line x1="3" y1="8" x2="13" y2="8"/></svg>
                <?php esc_html_e( 'Select', 'pizzatier' ); ?>
            </button>
            <button type="button" class="cc-btn cc-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>
                <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
            </button>
        </div>
    </div>
    <?php
    do_action( 'pizzatier_after_layer_card', $post, $layer_type );
    return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, $layer_type );
}
endif;

/**
 * Render a topping card (multi-select with coverage picker).
 */
if ( ! function_exists( 'pzt_commandcenter_topping_card' ) ) :
function pzt_commandcenter_topping_card( $post, string $cc_var, int $zindex ): string {
    if ( ! ( $post instanceof \WP_Post ) ) { return ''; }
    $id        = $post->ID;
    $title     = get_the_title( $post );
    $slug      = sanitize_title( $title );
    $layer_id  = 'pizzatier-topping-' . $slug;

    $thumb_url = pzl_get_field( 'topping_image', $id ) ?: pzl_get_field( 'topping_layer_image', $id ) ?: (string) get_the_post_thumbnail_url( $id, 'medium' );
    $layer_url = pzl_get_field( 'topping_layer_image', $id ) ?: $thumb_url;

    $js_title  = esc_js( $title );
    $js_slug   = esc_js( $slug );
    $js_layer  = esc_js( (string) $layer_url );

    $js_add    = "window['{$cc_var}']&&window['{$cc_var}'].addTopping({$zindex},'{$js_slug}','{$js_layer}','{$js_title}','{$layer_id}','{$layer_id}',this)";
    $js_remove = "window['{$cc_var}']&&window['{$cc_var}'].removeTopping('pizzatier-topping-{$js_slug}','{$js_slug}',this)";

    ob_start();
    do_action( 'pizzatier_before_layer_card', $post, 'toppings' );
    ?>
    <div class="cc-card cc-card--topping"
         data-layer="toppings"
         data-slug="<?php echo esc_attr( $slug ); ?>"
         data-title="<?php echo esc_attr( $title ); ?>"
         data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
         data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>"
         data-zindex="<?php echo esc_attr( (string) $zindex ); ?>">
        <div class="cc-card__media">
            <?php if ( $thumb_url ) : ?>
                <img class="cc-card__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
            <?php else : ?>
                <div class="cc-card__img cc-card__img--placeholder"></div>
            <?php endif; ?>
            <div class="cc-card__check" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 8 6.5 11.5 13 5"/></svg>
            </div>
        </div>
        <div class="cc-card__body">
            <span class="cc-card__name"><?php echo esc_html( $title ); ?></span>
            <div class="cc-coverage" style="display:none;">
                <span class="cc-coverage__label"><?php esc_html_e( 'Coverage:', 'pizzatier' ); ?></span>
                <div class="cc-coverage__btns">
                    <?php
                    $_all_coverages = [
                        'whole'               => __( 'Whole', 'pizzatier' ),
                        'half-left'           => __( 'Left ½', 'pizzatier' ),
                        'half-right'          => __( 'Right ½', 'pizzatier' ),
                        'quarter-top-left'    => __( 'Q1', 'pizzatier' ),
                        'quarter-top-right'   => __( 'Q2', 'pizzatier' ),
                        'quarter-bottom-left' => __( 'Q3', 'pizzatier' ),
                        'quarter-bottom-right'=> __( 'Q4', 'pizzatier' ),
                    ];
                    $_enabled_fracs = function_exists( 'pz_get_enabled_fractions' ) ? pz_get_enabled_fractions() : array_keys( $_all_coverages );
                    $coverages      = array_intersect_key( $_all_coverages, array_flip( $_enabled_fracs ) );
                    foreach ( $coverages as $fraction => $label ) :
                        $js_cov = "window['{$cc_var}']&&window['{$cc_var}'].setCoverage('" . esc_js( $slug ) . "','" . esc_js( $fraction ) . "',this)";
                    ?>
                    <button type="button" class="cc-cov-btn" data-fraction="<?php echo esc_attr( $fraction ); ?>"
                            onclick="<?php echo esc_attr( $js_cov ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="cc-card__actions">
            <button type="button" class="cc-btn cc-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="8" y1="3" x2="8" y2="13"/><line x1="3" y1="8" x2="13" y2="8"/></svg>
                <?php esc_html_e( 'Add', 'pizzatier' ); ?>
            </button>
            <button type="button" class="cc-btn cc-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>
                <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
            </button>
        </div>
    </div>
    <?php
    do_action( 'pizzatier_after_layer_card', $post, 'toppings' );
    return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, 'toppings' );
}
endif;

/* ── Pre-render card pools ────────────────────────────────────────────── */
$crusts_html = '';
foreach ( $crusts as $post ) { $crusts_html .= pzt_commandcenter_exclusive_card( $post, 'crust', $cc_var, 100 ); }
if ( ! $crusts_html ) { $crusts_html = '<p class="cc-empty">' . esc_html__( 'No crusts found.', 'pizzatier' ) . '</p>'; }

$sauces_html = '';
foreach ( $sauces as $post ) { $sauces_html .= pzt_commandcenter_exclusive_card( $post, 'sauce', $cc_var, 150 ); }
if ( ! $sauces_html ) { $sauces_html = '<p class="cc-empty">' . esc_html__( 'No sauces found.', 'pizzatier' ) . '</p>'; }

$cheeses_html = '';
foreach ( $cheeses as $post ) { $cheeses_html .= pzt_commandcenter_exclusive_card( $post, 'cheese', $cc_var, 200 ); }
if ( ! $cheeses_html ) { $cheeses_html = '<p class="cc-empty">' . esc_html__( 'No cheeses found.', 'pizzatier' ) . '</p>'; }

$drizzles_html = '';
foreach ( $drizzles as $post ) { $drizzles_html .= pzt_commandcenter_exclusive_card( $post, 'drizzle', $cc_var, 900 ); }
if ( ! $drizzles_html ) { $drizzles_html = '<p class="cc-empty">' . esc_html__( 'No drizzles found.', 'pizzatier' ) . '</p>'; }

$toppings_html = '';
$_t_z = 400;
foreach ( $toppings as $post ) {
    $toppings_html .= pzt_commandcenter_topping_card( $post, $cc_var, $_t_z );
    $_t_z += 10;
}
if ( ! $toppings_html ) { $toppings_html = '<p class="cc-empty">' . esc_html__( 'No toppings found.', 'pizzatier' ) . '</p>'; }

$cuts_html = '';
foreach ( $cuts as $post ) { $cuts_html .= pzt_commandcenter_exclusive_card( $post, 'cut', $cc_var, 950 ); }
if ( ! $cuts_html ) { $cuts_html = '<p class="cc-empty">' . esc_html__( 'No cut styles found.', 'pizzatier' ) . '</p>'; }

/* ── Initial pizza display ────────────────────────────────────────────── */
$builder       = new \PizzaTier\Builder\PizzaBuilder();
$initial_pizza = $builder->build_dynamic(
    $atts['default_crust']    ?? '',
    $atts['default_sauce']    ?? '',
    $atts['default_cheese']   ?? '',
    $atts['default_toppings'] ?? '',
    $atts['default_drizzle']  ?? '',
    $atts['default_cut']      ?? ''
);

/* ── Special instructions ─────────────────────────────────────────────── */
$show_spec_instr  = ( get_option( 'pizzatier_setting_cx_special_instructions', 'no' ) === 'yes' );
$spec_placeholder = sanitize_text_field( (string) get_option( 'pizzatier_setting_cx_special_instr_placeholder', 'Any special requests? (optional)' ) );
$spec_max         = max( 1, (int) get_option( 'pizzatier_setting_cx_special_instr_max', 300 ) );

/* ── Tab meta: icon (inline SVG path commands) + label ───────────────── */
$tab_meta = [
    'size'      => [ 'ruler',   __( 'Size',      'pizzatier' ) ],
    'crust'     => [ 'circle',  __( 'Crust',     'pizzatier' ) ],
    'sauce'     => [ 'drop',    __( 'Sauce',     'pizzatier' ) ],
    'cheese'    => [ 'cheese',  __( 'Cheese',    'pizzatier' ) ],
    'toppings'  => [ 'leaf',    __( 'Toppings',  'pizzatier' ) ],
    'drizzle'   => [ 'drizzle', __( 'Drizzle',   'pizzatier' ) ],
    'slicing'   => [ 'slice',   __( 'Slicing',   'pizzatier' ) ],
    'yourpizza' => [ 'receipt', __( 'Your Order','pizzatier' ) ],
];

/* ── Summary row labels (sidebar) ────────────────────────────────────── */
$summary_rows = [
    'crust'    => __( 'Crust',    'pizzatier' ),
    'sauce'    => __( 'Sauce',    'pizzatier' ),
    'cheese'   => __( 'Cheese',   'pizzatier' ),
    'toppings' => __( 'Toppings', 'pizzatier' ),
    'drizzle'  => __( 'Drizzle',  'pizzatier' ),
    'slicing'  => __( 'Slicing',  'pizzatier' ),
];

/* ── Build step number map (1-based, skipping yourpizza) ─────────────── */
$_step_num   = 1;
$step_numbers = [];
foreach ( $visible_tabs as $_st ) {
    if ( 'yourpizza' === $_st ) { continue; }
    $step_numbers[ $_st ] = $_step_num++;
}
$total_steps = count( $step_numbers );

do_action( 'pizzatier_before_builder', $instance_id, $template_slug );
?>
<!-- ═══════════════════════════════════════════════════════════
     COMMAND CENTER TEMPLATE — PizzaTier
     Instance: <?php echo esc_html( $instance_id ); ?>
═══════════════════════════════════════════════════════════ -->
<div id="<?php echo esc_attr( $instance_id ); ?>"
     class="cc-root"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-cc-var="<?php echo esc_attr( $cc_var ); ?>"
     data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
     data-pizza-shape="<?php echo esc_attr( $pizza_shape ); ?>"
     data-pizza-aspect="<?php echo esc_attr( $pizza_aspect ); ?>"
     data-pizza-radius="<?php echo esc_attr( $pizza_radius ); ?>"
     data-layer-anim="<?php echo esc_attr( $layer_anim ); ?>"
     data-layer-anim-speed="<?php echo esc_attr( (string) $layer_anim_speed ); ?>"
     data-total-steps="<?php echo esc_attr( (string) $total_steps ); ?>">

    <!-- ── Step progress bar (top, mobile + desktop) ──────────────────── -->
    <div class="cc-wizard-header" aria-label="<?php esc_attr_e( 'Order steps', 'pizzatier' ); ?>">
        <div class="cc-wizard-steps" id="<?php echo esc_attr( $instance_id ); ?>-steps">
            <?php
            foreach ( $visible_tabs as $tab ) :
                if ( 'yourpizza' === $tab || ! isset( $tab_meta[ $tab ] ) ) { continue; }
                $step_n = $step_numbers[ $tab ] ?? 0;
                [ $icon_key, $label ] = $tab_meta[ $tab ];
            ?>
            <div class="cc-step" data-tab="<?php echo esc_attr( $tab ); ?>" data-step="<?php echo esc_attr( (string) $step_n ); ?>"
                 role="button" tabindex="0"
                 onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('<?php echo esc_js( $tab ); ?>')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"
                 aria-label="<?php echo esc_attr( sprintf( /* translators: 1: step number, 2: step label. */ __( 'Step %1$s: %2$s', 'pizzatier' ), $step_n, $label ) ); ?>">
                <div class="cc-step__bubble">
                    <span class="cc-step__num"><?php echo esc_html( (string) $step_n ); ?></span>
                    <span class="cc-step__check" aria-hidden="true">
                        <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 7 5.5 10.5 12 4"/></svg>
                    </span>
                </div>
                <span class="cc-step__label"><?php echo esc_html( $label ); ?></span>
            </div>
            <?php endforeach; ?>
            <!-- "Your Order" review step (not counted) -->
            <?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) : ?>
            <div class="cc-step cc-step--review" data-tab="yourpizza"
                 role="button" tabindex="0"
                 onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('yourpizza')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"
                 aria-label="<?php esc_attr_e( 'Review order', 'pizzatier' ); ?>">
                <div class="cc-step__bubble cc-step__bubble--review">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="2" y="1" width="12" height="14" rx="1.5"/><line x1="5" y1="5" x2="11" y2="5"/><line x1="5" y1="8" x2="11" y2="8"/><line x1="5" y1="11" x2="8" y2="11"/></svg>
                </div>
                <span class="cc-step__label"><?php esc_html_e( 'Review', 'pizzatier' ); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <div class="cc-wizard-progress-track" aria-hidden="true">
            <div class="cc-wizard-progress-fill" id="<?php echo esc_attr( $instance_id ); ?>-progress-fill"></div>
        </div>
    </div>

    <!-- ── Main layout: panels + sidebar ─────────────────────────────── -->
    <div class="cc-layout">

        <!-- LEFT / MAIN: panels + pizza canvas -->
        <div class="cc-main-col">

            <!-- Pizza canvas (full-width on mobile, sits above panels) -->
            <div class="cc-canvas-wrap" id="<?php echo esc_attr( $instance_id ); ?>-canvas-wrap">
                <div class="cc-canvas" id="<?php echo esc_attr( $instance_id ); ?>-canvas">
                    <?php echo $initial_pizza; // phpcs:ignore WordPress.Security.EscapeOutput -- built by PizzaBuilder ?>
                </div>
                <div class="cc-canvas-reset">
                    <button type="button" class="cc-ghost-btn"
                            onclick="ClearPizza(); window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].resetAll();">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3.5 8A4.5 4.5 0 1 1 8 12.5H5"/><polyline points="5 10 5 12.5 2.5 12.5"/></svg>
                        <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
                    </button>
                    <span class="cc-topping-count">
                        <?php esc_html_e( 'Toppings:', 'pizzatier' ); ?>
                        <strong><span id="<?php echo esc_attr( $instance_id ); ?>-count">0</span> / <?php echo esc_html( (string) $max_toppings ); ?></strong>
                    </span>
                </div>
            </div>

            <!-- Panels -->
            <div class="cc-panels" id="<?php echo esc_attr( $instance_id ); ?>-panels">

                <?php if ( $_has_pro && in_array( 'size', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_size', $instance_id ); ?>
                <section class="cc-panel cc-panel--active" id="<?php echo esc_attr( $instance_id ); ?>-panel-size" role="tabpanel" aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-step-size">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['size'] ?? 1 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Choose Your Size', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'Select the size of your pizza.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <?php pzt_render_inline_size_selector( $_pro_sizes, $instance_id, 'cc' ); ?>
                    <div class="cc-panel__nav">
                        <span></span>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('crust')">
                            <?php esc_html_e( 'Crust', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_size', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'crust', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_crust', $instance_id ); ?>
                <section class="cc-panel<?php echo ( ! $_has_pro ) ? ' cc-panel--active' : ''; ?>"
                         id="<?php echo esc_attr( $instance_id ); ?>-panel-crust" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['crust'] ?? 1 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Choose Your Crust', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'The foundation of a great pizza.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--exclusive"><?php echo $crusts_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <?php if ( $_has_pro ) : ?>
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('size')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Size', 'pizzatier' ); ?>
                        </button>
                        <?php else : ?><span></span><?php endif; ?>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('sauce')">
                            <?php esc_html_e( 'Sauce', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_crust', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'sauce', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_sauce', $instance_id ); ?>
                <section class="cc-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-sauce" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['sauce'] ?? 2 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Choose Your Sauce', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'Pick the flavor base.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--exclusive"><?php echo $sauces_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('crust')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Crust', 'pizzatier' ); ?>
                        </button>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('cheese')">
                            <?php esc_html_e( 'Cheese', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_sauce', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'cheese', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_cheese', $instance_id ); ?>
                <section class="cc-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-cheese" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['cheese'] ?? 3 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Choose Your Cheese', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'Melt it your way.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--exclusive"><?php echo $cheeses_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('sauce')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Sauce', 'pizzatier' ); ?>
                        </button>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('toppings')">
                            <?php esc_html_e( 'Toppings', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_cheese', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'toppings', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_toppings', $instance_id ); ?>
                <section class="cc-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-toppings" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['toppings'] ?? 4 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Choose Toppings', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint">
                                <?php printf( /* translators: %s = maximum number of toppings. */ esc_html__( 'Up to %s toppings.', 'pizzatier' ), '<strong>' . esc_html( (string) $max_toppings ) . '</strong>' ); ?>
                            </p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--toppings"><?php echo $toppings_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('cheese')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Cheese', 'pizzatier' ); ?>
                        </button>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('drizzle')">
                            <?php esc_html_e( 'Drizzle', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_toppings', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'drizzle', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_drizzle', $instance_id ); ?>
                <section class="cc-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-drizzle" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['drizzle'] ?? 5 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Finishing Drizzle', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'Optional. Skip if you prefer.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--exclusive"><?php echo $drizzles_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('toppings')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Toppings', 'pizzatier' ); ?>
                        </button>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('slicing')">
                            <?php esc_html_e( 'Slicing', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_drizzle', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'slicing', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_slicing', $instance_id ); ?>
                <section class="cc-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-slicing" role="tabpanel">
                    <div class="cc-panel__header">
                        <div class="cc-panel__step-badge"><?php echo esc_html( (string) ( $step_numbers['slicing'] ?? 6 ) ); ?></div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'How Should We Slice It?', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( 'Choose a cut style.', 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-cards-grid cc-cards-grid--exclusive"><?php echo $cuts_html; // phpcs:ignore ?></div>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('drizzle')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Drizzle', 'pizzatier' ); ?>
                        </button>
                        <?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) : ?>
                        <button type="button" class="cc-nav-btn cc-nav-btn--next cc-nav-btn--cta" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('yourpizza')">
                            <?php esc_html_e( 'Review Order', 'pizzatier' ); ?>
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="6 3 11 8 6 13"/></svg>
                        </button>
                        <?php else : ?><span></span><?php endif; ?>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_slicing', $instance_id ); ?>
                <?php endif; ?>

                <?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) : ?>
                <?php do_action( 'pizzatier_before_tab_yourpizza', $instance_id ); ?>
                <section class="cc-panel cc-panel--review-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-yourpizza" role="tabpanel">
                    <div class="cc-panel__header cc-panel__header--review">
                        <div class="cc-panel__review-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><rect x="3" y="1" width="14" height="18" rx="2"/><line x1="6.5" y1="6" x2="13.5" y2="6"/><line x1="6.5" y1="10" x2="13.5" y2="10"/><line x1="6.5" y1="14" x2="10" y2="14"/></svg>
                        </div>
                        <div>
                            <h2 class="cc-panel__title"><?php esc_html_e( 'Your Order Summary', 'pizzatier' ); ?></h2>
                            <p class="cc-panel__hint"><?php esc_html_e( "Confirm everything looks right before adding to cart.", 'pizzatier' ); ?></p>
                        </div>
                    </div>
                    <div class="cc-review-grid" id="<?php echo esc_attr( $instance_id ); ?>-summary">
                        <?php foreach ( $summary_rows as $key => $row_label ) : ?>
                        <div class="cc-review-row" id="<?php echo esc_attr( $instance_id . '-yp-' . $key ); ?>">
                            <span class="cc-review-row__label"><?php echo esc_html( $row_label ); ?></span>
                            <span class="cc-review-row__value cc-review-row__value--empty"
                                  id="<?php echo esc_attr( $instance_id . '-yp-' . $key . '-val' ); ?>">
                                <em><?php esc_html_e( 'None selected', 'pizzatier' ); ?></em>
                            </span>
                            <button type="button" class="cc-review-edit"
                                    onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('<?php echo esc_js( $key ); ?>')"
                                    aria-label="<?php echo esc_attr( sprintf( /* translators: %s = item label. */ __( 'Edit %s', 'pizzatier' ), $row_label ) ); ?>">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M11.5 2.5a1.5 1.5 0 0 1 2 2L5 13l-3 1 1-3Z"/></svg>
                                <?php esc_html_e( 'Edit', 'pizzatier' ); ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( $show_spec_instr ) : ?>
                    <div class="cc-special-instructions">
                        <label class="cc-special-instructions__label" for="<?php echo esc_attr( $instance_id ); ?>-special-instr">
                            <?php esc_html_e( 'Special Instructions', 'pizzatier' ); ?>
                        </label>
                        <textarea class="cc-special-instructions__input"
                                  id="<?php echo esc_attr( $instance_id ); ?>-special-instr"
                                  name="pizzatier_special_instructions_<?php echo esc_attr( $instance_id ); ?>"
                                  placeholder="<?php echo esc_attr( $spec_placeholder ); ?>"
                                  maxlength="<?php echo esc_attr( (string) $spec_max ); ?>"
                                  rows="3"></textarea>
                    </div>
                    <?php endif; ?>
                    <div class="cc-panel__nav">
                        <button type="button" class="cc-nav-btn cc-nav-btn--prev" onclick="window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].goTab('slicing')">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><polyline points="10 3 5 8 10 13"/></svg>
                            <?php esc_html_e( 'Back', 'pizzatier' ); ?>
                        </button>
                        <?php
                        $start_over_label = sanitize_text_field( (string) get_option( 'pizzatier_setting_cx_start_over_label', 'Start Over' ) );
                        $show_start_over  = get_option( 'pizzatier_setting_cx_show_start_over', 'yes' ) !== 'no';
                        if ( $show_start_over ) :
                        ?>
                        <button type="button" class="cc-ghost-btn"
                                onclick="ClearPizza(); window['<?php echo esc_js( $cc_var ); ?>']&&window['<?php echo esc_js( $cc_var ); ?>'].resetAll();">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M3.5 8A4.5 4.5 0 1 1 8 12.5H5"/><polyline points="5 10 5 12.5 2.5 12.5"/></svg>
                            <?php echo esc_html( $start_over_label ); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </section>
                <?php do_action( 'pizzatier_after_tab_yourpizza', $instance_id ); ?>
                <?php endif; ?>

            </div><!-- /.cc-panels -->

        </div><!-- /.cc-main-col -->

        <!-- RIGHT: persistent order summary sidebar -->
        <aside class="cc-sidebar" id="<?php echo esc_attr( $instance_id ); ?>-sidebar" aria-label="<?php esc_attr_e( 'Order summary', 'pizzatier' ); ?>">
            <div class="cc-sidebar__inner">
                <div class="cc-sidebar__header">
                    <svg class="cc-sidebar__icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><rect x="3" y="1" width="14" height="18" rx="2"/><line x1="6.5" y1="6" x2="13.5" y2="6"/><line x1="6.5" y1="10" x2="13.5" y2="10"/><line x1="6.5" y1="14" x2="10" y2="14"/></svg>
                    <span><?php esc_html_e( 'Your Order', 'pizzatier' ); ?></span>
                </div>
                <div class="cc-sidebar__rows">
                    <?php foreach ( $summary_rows as $key => $row_label ) : ?>
                    <div class="cc-sidebar__row" id="<?php echo esc_attr( $instance_id . '-sb-' . $key ); ?>">
                        <span class="cc-sidebar__row-label"><?php echo esc_html( $row_label ); ?></span>
                        <span class="cc-sidebar__row-value cc-sidebar__row-value--empty"
                              id="<?php echo esc_attr( $instance_id . '-sb-' . $key . '-val' ); ?>">—</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

    </div><!-- /.cc-layout -->

    <!-- ── Checkout dock ───────────────────────────────────────────────
         PizzaTierPro renders its Add to Cart / checkout bar here when active.
         This dock is always present (it does NOT depend on the optional order
         summary sidebar), spans the full builder width, and sits at the end of
         the wizard so the final custom pizza can always be added to the cart. -->
    <div class="cc-checkout-dock" id="<?php echo esc_attr( $instance_id ); ?>-checkout-dock">
        <?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>
    </div>

    <div id="<?php echo esc_attr( $instance_id ); ?>-fly-container" aria-hidden="true"></div>

</div><!-- /#<?php echo esc_html( $instance_id ); ?> .cc-root -->

<?php
/* Initialize this instance via wp_add_inline_script — no inline <script> blocks. */
$cc_init_js = "if(typeof CC!=='undefined'&&typeof CC.createInstance==='function'){"
    . "var " . esc_js( $cc_var ) . "=CC.createInstance(" . wp_json_encode( $instance_id ) . ");"
    . "}";
wp_add_inline_script( 'pizzatier-template-commandcenter', $cc_init_js );
do_action( 'pizzatier_after_builder', $instance_id, $template_slug );
