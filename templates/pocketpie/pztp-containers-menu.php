<?php
/**
 * PocketPie Template — [pizza_builder] / [pizzatier-menu] output.
 *
 * Layouts:
 *   corner-quad   — pizza centred, four corner menus expand inward
 *   layer-deck    — pizza dominant, thumbnail strip below; click opens modal
 *   slide-drawer  — pizza on top half, bottom drawer slides up per category
 *   stack-panel   — pizza inline, compact bottom-sheet overlay for choices
 *
 * Variables from BuilderShortcode:
 *   $instance_id, $atts, $template_slug, $function_prefix
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

if ( ! isset( $instance_id ) )     { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )            { $atts           = []; }
if ( ! isset( $template_slug ) )   { $template_slug  = 'pocketpie'; }
if ( ! isset( $function_prefix ) ) { $function_prefix = 'pzt_pocketpie'; }

// JS namespace
$pp_var = 'PP_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

// Resolve max toppings
$max_toppings = isset( $atts['max_toppings'] ) && (int) $atts['max_toppings'] > 0
    ? (int) $atts['max_toppings']
    : intval( get_option( 'pizzatier_setting_topping_maxtoppings', 0 ) );
if ( $max_toppings < 1 ) { $max_toppings = 99; }
$max_toppings = (int) apply_filters( 'pizzatier_max_toppings', $max_toppings, $instance_id );

// Resolve layout mode — shortcode attr wins, then the Default Layout Mode
// template setting, then the built-in corner-quad fallback.
$valid_layouts = [ 'corner-quad', 'layer-deck', 'slide-drawer', 'stack-panel' ];
$layout = sanitize_key( $atts['layout'] ?? '' );
if ( $layout === '' ) {
    $layout = sanitize_key( (string) get_option( 'pocketpie_setting_default_layout', 'corner-quad' ) );
}
if ( ! in_array( $layout, $valid_layouts, true ) ) { $layout = 'corner-quad'; }

// ── Template settings consumed by this markup ──────────────────────
$pp_show_reset   = get_option( 'pocketpie_setting_show_reset',      'yes' ) !== 'no';
$pp_show_review  = get_option( 'pocketpie_setting_show_review_btn', 'yes' ) !== 'no';
$pp_review_label = sanitize_text_field( (string) get_option( 'pocketpie_setting_review_btn_label', '' ) );
if ( $pp_review_label === '' ) { $pp_review_label = __( 'Review', 'pizzatier' ); }
$pp_summary_title = sanitize_text_field( (string) get_option( 'pocketpie_setting_summary_title', '' ) );
if ( $pp_summary_title === '' ) { $pp_summary_title = __( 'Your Pizza', 'pizzatier' ); }
$pp_close_on_backdrop = get_option( 'pocketpie_setting_close_on_backdrop', 'yes' ) !== 'no';
$pp_swipe_close_sd    = get_option( 'pocketpie_setting_sd_swipe_close', 'yes' ) !== 'no';
$pp_swipe_close_sp    = get_option( 'pocketpie_setting_sp_swipe_close', 'yes' ) !== 'no';
$pp_modal_anim        = sanitize_key( (string) get_option( 'pocketpie_setting_modal_anim', 'scale-fade' ) );
if ( ! in_array( $pp_modal_anim, [ 'scale-fade', 'slide-up', 'fade', 'instant' ], true ) ) { $pp_modal_anim = 'scale-fade'; }
$pp_sd_pill_pos = sanitize_key( (string) get_option( 'pocketpie_setting_sd_pill_position', 'bottom-overlay' ) );
if ( ! in_array( $pp_sd_pill_pos, [ 'bottom-overlay', 'below-pizza', 'top-overlay' ], true ) ) { $pp_sd_pill_pos = 'bottom-overlay'; }
$pp_sd_pill_style = sanitize_key( (string) get_option( 'pocketpie_setting_sd_pill_style', 'pill' ) );
if ( ! in_array( $pp_sd_pill_style, [ 'pill', 'square', 'icon', 'text' ], true ) ) { $pp_sd_pill_style = 'pill'; }

// Pizza shape
$valid_shapes  = [ 'round', 'square', 'rectangle', 'custom' ];
$pizza_shape   = sanitize_key( $atts['pizza_shape'] ?? get_option( 'pizzatier_setting_pizza_shape', 'round' ) );
if ( ! in_array( $pizza_shape, $valid_shapes, true ) ) { $pizza_shape = 'round'; }
$pizza_aspect  = sanitize_text_field( $atts['pizza_aspect'] ?? get_option( 'pizzatier_setting_pizza_aspect', '1 / 1' ) );
$pizza_radius  = sanitize_text_field( $atts['pizza_radius'] ?? get_option( 'pizzatier_setting_pizza_radius', '8px' ) );


// ── Add-on: inline size selector ──────────────────────────────────────
if ( ! function_exists( 'pzt_get_pro_sizes' ) ) :
function pzt_get_pro_sizes(): array {
	// Sizes come from a pricing add-on when one is installed; pzt_addon_sizes()
	// returns an empty array otherwise and the selector simply does not render.
	$product_id = ( function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0 );
	if ( ! $product_id ) {
		global $post;
		if ( $post instanceof \WP_Post ) { $product_id = $post->ID; }
	}
	return pzt_addon_sizes( $product_id );
}
endif;
if ( ! function_exists( 'pzt_render_inline_size_selector' ) ) :
function pzt_render_inline_size_selector( array $sizes, string $instance_id, string $css_prefix = 'cb' ): void {
	if ( empty( $sizes ) ) { return; }
	// Extract numeric suffix from instance_id (handles pztc-1, pizzabuilder-1, pztc-1-2, etc)
	preg_match( '/-(\d+)$/', $instance_id, $_m_suf );
	$radio_name_raw = ! empty( $_m_suf[1] ) ? $_m_suf[1] : preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );
	$radio_name = 'pizzatier_commerce_size_' . $radio_name_raw;
	$heading = (string) pzt_addon_setting( 'size_selector_label', '' );
	if ( '' === $heading ) { $heading = __( 'Choose a Size', 'pizzatier' ); }
	?>
	<div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector pztc-inline-size-selector" id="<?php echo esc_attr( $instance_id ); ?>-size-selector" role="group" aria-label="<?php echo esc_attr( $heading ); ?>">
		<p class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__heading"><?php echo esc_html( $heading ); ?></p>
		<div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__options">
			<?php foreach ( $sizes as $i => $size ) :
				$inp_id = esc_attr( $instance_id ) . '-sz-' . sanitize_html_class( strtolower( $size ) ); ?>
			<label class="<?php echo esc_attr( $css_prefix ); ?>-size-option pztc-size-option<?php echo 0 === $i ? ' pztc-size-option--active' : ''; ?>" for="<?php echo esc_attr( $inp_id ); ?>">
				<input type="radio" id="<?php echo esc_attr( $inp_id ); ?>" name="<?php echo esc_attr( $radio_name ); ?>" value="<?php echo esc_attr( $size ); ?>" class="pztc-size-radio" <?php checked( 0, $i ); ?> />
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

// Hidden tabs
$hide_tabs_raw = $atts['hide_tabs'] ?? '';
$show_tabs_raw = $atts['show_tabs'] ?? '';
$all_tabs      = array_merge( $_has_pro ? [ 'size' ] : [], [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ] );
$all_tabs      = apply_filters( 'pizzatier_tab_order', $all_tabs, $instance_id );

if ( $show_tabs_raw ) {
    $visible_tabs = array_intersect( $all_tabs, array_map( 'trim', explode( ',', $show_tabs_raw ) ) );
} elseif ( $hide_tabs_raw ) {
    $hide_set     = array_map( 'trim', explode( ',', $hide_tabs_raw ) );
    $visible_tabs = array_diff( $all_tabs, $hide_set );
} else {
    $visible_tabs = $all_tabs;
}

// Query all CPTs
$query_base = [
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
];
$crusts   = apply_filters( 'pizzatier_query_args_crusts',   get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_crusts'   ] ) ), 'crusts'   );
$sauces   = apply_filters( 'pizzatier_query_args_sauces',   get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_sauces'   ] ) ), 'sauces'   );
$cheeses  = apply_filters( 'pizzatier_query_args_cheeses',  get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_cheeses'  ] ) ), 'cheeses'  );
$drizzles = apply_filters( 'pizzatier_query_args_drizzles', get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_drizzles' ] ) ), 'drizzles' );
$toppings = apply_filters( 'pizzatier_query_args_toppings', get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_toppings' ] ) ), 'toppings' );
$cuts     = apply_filters( 'pizzatier_query_args_cuts',     get_posts( array_merge( $query_base, [ 'post_type' => 'pizzatier_cuts'     ] ) ), 'cuts'     );

/**
 * Helper: render a compact item chip for any layer type.
 * Used inside overlay modals and drawer panels.
 */
if ( ! function_exists( 'pzt_pocketpie_chip' ) ) :
function pzt_pocketpie_chip( $post, string $layer_type, string $pp_var, int $zindex = 200 ): string {
    if ( ! ( $post instanceof \WP_Post ) ) { return ''; }
    $id        = $post->ID;
    $title     = get_the_title( $post );
    $slug      = sanitize_title( $title );
    $img_field = $layer_type . '_image';
    $lyr_field = $layer_type . '_layer_image';

    $thumb_url = pzl_get_field( $img_field, $id ) ?: pzl_get_field( $lyr_field, $id ) ?: (string) get_the_post_thumbnail_url( $id, 'thumbnail' );
    $layer_url = pzl_get_field( $lyr_field, $id ) ?: $thumb_url;

    $js_add    = "window['{$pp_var}']&&window['{$pp_var}'].swapBase('{$layer_type}','".esc_js($slug)."','".esc_js($title)."','".esc_js((string)$layer_url)."',this)";
    $js_remove = "window['{$pp_var}']&&window['{$pp_var}'].removeBase('{$layer_type}','".esc_js($slug)."',this)";
    $card_html = '';
	ob_start();
	try {
    do_action( 'pizzatier_before_layer_card', $post, $layer_type );
    ?>
    <div class="pp-chip pp-chip--exclusive"
         data-layer="<?php echo esc_attr( $layer_type ); ?>"
         data-slug="<?php echo esc_attr( $slug ); ?>"
         data-title="<?php echo esc_attr( $title ); ?>"
         data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
         data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>">
        <?php if ( $thumb_url ) : ?>
            <img class="pp-chip__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
        <?php else : ?>
            <div class="pp-chip__img pp-chip__img--placeholder"></div>
        <?php endif; ?>
        <span class="pp-chip__name"><?php echo esc_html( $title ); ?></span>
        <span class="pp-chip__check">&#10003;</span>
        <button type="button" class="pp-chip__add-btn" onclick="<?php echo esc_attr( $js_add ); ?>">
            <span class="pp-chip__add-label"><?php esc_html_e( 'Select', 'pizzatier' ); ?></span>
        </button>
        <button type="button" class="pp-chip__remove-btn" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
            <span class="pp-chip__remove-label">&#x2715;</span>
        </button>
    </div>
    <?php
    do_action( 'pizzatier_after_layer_card', $post, $layer_type );
    $card_html = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	return apply_filters( 'pizzatier_card_html', $card_html, $post, $layer_type  );
}
endif;

/**
 * Helper: render a topping chip (multi-select, coverage buttons).
 */
if ( ! function_exists( 'pzt_pocketpie_topping_chip' ) ) :
function pzt_pocketpie_topping_chip( $post, string $pp_var, int $zindex ): string {
    if ( ! ( $post instanceof \WP_Post ) ) { return ''; }
    $id        = $post->ID;
    $title     = get_the_title( $post );
    $slug      = sanitize_title( $title );

    $thumb_url = pzl_get_field( 'topping_image', $id ) ?: pzl_get_field( 'topping_layer_image', $id ) ?: (string) get_the_post_thumbnail_url( $id, 'thumbnail' );
    $layer_url = pzl_get_field( 'topping_layer_image', $id ) ?: $thumb_url;
    $layer_id  = 'pizzatier-topping-' . $slug;

    $js_add    = "window['{$pp_var}']&&window['{$pp_var}'].addTopping({$zindex},'".esc_js($slug)."','".esc_js((string)$layer_url)."','".esc_js($title)."','{$layer_id}','{$layer_id}',this)";
    $js_remove = "window['{$pp_var}']&&window['{$pp_var}'].removeTopping('pizzatier-topping-".esc_js($slug)."','".esc_js($slug)."',this)";
    $card_html = '';
	ob_start();
	try {
    do_action( 'pizzatier_before_layer_card', $post, 'toppings' );
    ?>
    <div class="pp-chip pp-chip--topping"
         data-layer="toppings"
         data-slug="<?php echo esc_attr( $slug ); ?>"
         data-title="<?php echo esc_attr( $title ); ?>"
         data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
         data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>"
         data-zindex="<?php echo esc_attr( (string) $zindex ); ?>">
        <?php if ( $thumb_url ) : ?>
            <img class="pp-chip__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
        <?php else : ?>
            <div class="pp-chip__img pp-chip__img--placeholder"></div>
        <?php endif; ?>
        <span class="pp-chip__name"><?php echo esc_html( $title ); ?></span>
        <span class="pp-chip__check">&#10003;</span>
        <div class="pp-coverage" style="display:none;">
            <?php
            $coverages = [
                'whole'               => '&#9679;',
                'half-left'           => '&#9680;',
                'half-right'          => '&#9681;',
                'quarter-top-left'    => 'Q1',
                'quarter-top-right'   => 'Q2',
                'quarter-bottom-left' => 'Q3',
                'quarter-bottom-right'=> 'Q4',
            ];
            foreach ( $coverages as $fraction => $label ) :
                $js_cov = "window['{$pp_var}']&&window['{$pp_var}'].setCoverage('".esc_js($slug)."','".esc_js($fraction)."',this)";
            ?>
            <button type="button" class="pp-cov-btn" data-fraction="<?php echo esc_attr( $fraction ); ?>" onclick="<?php echo esc_attr( $js_cov ); ?>">
                <?php echo esc_html( $label );?>
            </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="pp-chip__add-btn" onclick="<?php echo esc_attr( $js_add ); ?>">
            <span class="pp-chip__add-label">+</span>
        </button>
        <button type="button" class="pp-chip__remove-btn" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
            <span>&#x2715;</span>
        </button>
    </div>
    <?php
    do_action( 'pizzatier_after_layer_card', $post, 'toppings' );
    $card_html = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	return apply_filters( 'pizzatier_card_html', $card_html, $post, 'toppings'  );
}
endif;

// Build all HTML pools
$crusts_html = '';
foreach ( $crusts as $pzt_layer )  { $crusts_html  .= pzt_pocketpie_chip( $pzt_layer, 'crust',  $pp_var, 100 ); }
if ( ! $crusts_html )  { $crusts_html  = '<p class="pp-empty">' . esc_html__( 'No crusts found.', 'pizzatier' ) . '</p>'; }

$sauces_html = '';
foreach ( $sauces as $pzt_layer )  { $sauces_html  .= pzt_pocketpie_chip( $pzt_layer, 'sauce',  $pp_var, 150 ); }
if ( ! $sauces_html )  { $sauces_html  = '<p class="pp-empty">' . esc_html__( 'No sauces found.', 'pizzatier' ) . '</p>'; }

$cheeses_html = '';
foreach ( $cheeses as $pzt_layer ) { $cheeses_html .= pzt_pocketpie_chip( $pzt_layer, 'cheese', $pp_var, 200 ); }
if ( ! $cheeses_html ) { $cheeses_html = '<p class="pp-empty">' . esc_html__( 'No cheeses found.', 'pizzatier' ) . '</p>'; }

$drizzles_html = '';
foreach ( $drizzles as $pzt_layer ){ $drizzles_html.= pzt_pocketpie_chip( $pzt_layer, 'drizzle',$pp_var, 900 ); }
if ( ! $drizzles_html ){ $drizzles_html = '<p class="pp-empty">' . esc_html__( 'No drizzles found.', 'pizzatier' ) . '</p>'; }

$toppings_html = '';
$t_z = 400;
foreach ( $toppings as $pzt_layer ){ $toppings_html .= pzt_pocketpie_topping_chip( $pzt_layer, $pp_var, $t_z ); $t_z += 10; }
if ( ! $toppings_html ){ $toppings_html = '<p class="pp-empty">' . esc_html__( 'No toppings found.', 'pizzatier' ) . '</p>'; }

$cuts_html = '';
foreach ( $cuts as $pzt_layer )    { $cuts_html    .= pzt_pocketpie_chip( $pzt_layer, 'cut',    $pp_var, 950 ); }
if ( ! $cuts_html )    { $cuts_html    = '<p class="pp-empty">' . esc_html__( 'No cut styles found.', 'pizzatier' ) . '</p>'; }

// Initial pizza render
$builder       = new \PizzaTier\Builder\PizzaBuilder();
$initial_pizza = $builder->build_dynamic(
    $atts['default_crust']    ?? '',
    $atts['default_sauce']    ?? '',
    $atts['default_cheese']   ?? '',
    $atts['default_toppings'] ?? '',
    $atts['default_drizzle']  ?? '',
    $atts['default_cut']      ?? ''
);

// ── PizzaTier size chips — rendered inside the shared "Size" modal ──
// (Previously these lived in a standalone "Choose Pizza Size" row above the
//  builder; they now open from the Size button in the actions row.)
$size_html  = '';
$pp_size_label = __( 'Size', 'pizzatier' );
if ( $_has_pro ) {
    $_pp_size_setting = (string) pzt_addon_setting( 'size_selector_label', '' );
    if ( '' !== $_pp_size_setting ) { $pp_size_label = sanitize_text_field( $_pp_size_setting ); }

    preg_match( '/-(\d+)$/', $instance_id, $_pp_m );
    $_pp_radio_sfx  = ! empty( $_pp_m[1] ) ? $_pp_m[1] : preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );
    $_pp_radio_name = 'pizzatier_commerce_size_' . $_pp_radio_sfx;

    ob_start();
    foreach ( $_pro_sizes as $i => $size ) :
        $_pp_sz_id = esc_attr( $instance_id ) . '-sz-' . sanitize_html_class( strtolower( $size ) );
        ?>
        <label class="pp-size-chip pztc-size-option<?php echo 0 === $i ? ' pp-size-chip--active pztc-size-option--active' : ''; ?>"
               for="<?php echo esc_attr( $_pp_sz_id ); ?>">
            <input type="radio"
                   id="<?php echo esc_attr( $_pp_sz_id ); ?>"
                   name="<?php echo esc_attr( $_pp_radio_name ); ?>"
                   value="<?php echo esc_attr( $size ); ?>"
                   class="pztc-size-radio"
                   <?php checked( 0, $i ); ?> />
            <span class="pp-size-chip__name"><?php echo esc_html( $size ); ?></span>
        </label>
        <?php
    endforeach;
    $size_html = ob_get_clean();
    unset( $_pp_m, $_pp_radio_sfx, $_pp_radio_name, $_pp_sz_id, $_pp_size_setting );
}

// Tab meta (icons + labels)
$tab_meta = [
    'size'      => [ '&#9654;',  $pp_size_label, $size_html ],
    'crust'     => [ '&#9711;',  __( 'Crust',      'pizzatier' ), $crusts_html   ],
    'sauce'     => [ '&#128138;',__( 'Sauce',      'pizzatier' ), $sauces_html   ],
    'cheese'    => [ '&#129472;',__( 'Cheese',     'pizzatier' ), $cheeses_html  ],
    'toppings'  => [ '&#127807;',__( 'Toppings',   'pizzatier' ), $toppings_html ],
    'drizzle'   => [ '&#127863;',__( 'Drizzle',    'pizzatier' ), $drizzles_html ],
    'slicing'   => [ '&#127829;',__( 'Slicing',    'pizzatier' ), $cuts_html     ],
    'yourpizza' => [ '&#128203;',__( 'Your Pizza', 'pizzatier' ), ''             ],
];

// Corner assignments for corner-quad layout (skip yourpizza)
$corner_tabs   = array_filter( $visible_tabs, fn($t) => $t !== 'yourpizza' );
$corner_tabs   = array_values( $corner_tabs );

// Honour the Corner Quad corner-category settings: order the first four
// corners as TL → TR → BL → BR per the saved options (only categories that
// are actually visible count), then append any remaining visible tabs.
$pp_corner_prefs = [
    sanitize_key( (string) get_option( 'pocketpie_setting_cq_corner_tl', 'crust' ) ),
    sanitize_key( (string) get_option( 'pocketpie_setting_cq_corner_tr', 'sauce' ) ),
    sanitize_key( (string) get_option( 'pocketpie_setting_cq_corner_bl', 'cheese' ) ),
    sanitize_key( (string) get_option( 'pocketpie_setting_cq_corner_br', 'toppings' ) ),
];
$pp_ordered = [];
foreach ( $pp_corner_prefs as $pp_pref ) {
    if ( in_array( $pp_pref, $corner_tabs, true ) && ! in_array( $pp_pref, $pp_ordered, true ) ) {
        $pp_ordered[] = $pp_pref;
    }
}
foreach ( $corner_tabs as $pp_t ) {
    if ( ! in_array( $pp_t, $pp_ordered, true ) ) { $pp_ordered[] = $pp_t; }
}
$corner_tabs = $pp_ordered;
unset( $pp_corner_prefs, $pp_ordered, $pp_pref, $pp_t );

$corners       = [ 'tl', 'tr', 'bl', 'br' ];

$ii = $instance_id;
$pv = $pp_var;

// Shorthand for summary rows
$summary_rows = [
    'size'     => [ '&#9654;', __( 'Size',    'pizzatier' ) ],
    'crust'    => [ '&#9711;',   __( 'Crust',    'pizzatier' ) ],
    'sauce'    => [ '&#128138;', __( 'Sauce',    'pizzatier' ) ],
    'cheese'   => [ '&#129472;', __( 'Cheese',   'pizzatier' ) ],
    'toppings' => [ '&#127807;', __( 'Toppings', 'pizzatier' ) ],
    'drizzle'  => [ '&#127863;', __( 'Drizzle',  'pizzatier' ) ],
    'slicing'  => [ '&#127829;', __( 'Slicing',  'pizzatier' ) ],
];
?>

<!-- ═══════════════════════════════════════════════════
     POCKETPIE TEMPLATE — PizzaTier
     Instance: <?php echo esc_html( $instance_id ); ?>
     Layout: <?php echo esc_html( $layout ); ?>
═══════════════════════════════════════════════════ -->
<div id="<?php echo esc_attr( $ii ); ?>"
     class="pp-root pp-layout--<?php echo esc_attr( $layout ); ?> pp-modal-anim--<?php echo esc_attr( $pp_modal_anim ); ?> pp-sd-pills-pos--<?php echo esc_attr( $pp_sd_pill_pos ); ?> pp-sd-pill-style--<?php echo esc_attr( $pp_sd_pill_style ); ?>"
     data-instance="<?php echo esc_attr( $ii ); ?>"
     data-pp-var="<?php echo esc_attr( $pp_var ); ?>"
     data-layout="<?php echo esc_attr( $layout ); ?>"
     data-swipe-close-sd="<?php echo $pp_swipe_close_sd ? 'yes' : 'no'; ?>"
     data-swipe-close-sp="<?php echo $pp_swipe_close_sp ? 'yes' : 'no'; ?>"
     data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
     data-pizza-shape="<?php echo esc_attr( $pizza_shape ); ?>"
     data-pizza-aspect="<?php echo esc_attr( $pizza_aspect ); ?>"
     data-pizza-radius="<?php echo esc_attr( $pizza_radius ); ?>">

    <?php /* The PizzaTier size selector is now presented inside the shared
             "Size" modal, opened from the Size button in the actions row.
             See $size_html / $tab_meta['size'] above. */ ?>

    <?php /* ─────────────────────────────────────────────────────────────────
           LAYOUT 1 — CORNER QUAD
           Pizza centred and large; four small corner triggers + an actions row
           open the shared full-screen modal. Corner categories default to
           TL=crust, TR=sauce, BL=cheese, BR=toppings (configurable).
           ───────────────────────────────────────────────────────────────── */ ?>
    <?php if ( $layout === 'corner-quad' ) : ?>
    <div class="pp-cq-wrap">

        <?php
        // Assign tabs to corners (up to 4; extras go to overflow)
        $corner_assignments = [];
        foreach ( $corners as $ci => $corner ) {
            if ( isset( $corner_tabs[ $ci ] ) ) {
                $corner_assignments[ $corner ] = $corner_tabs[ $ci ];
            }
        }
        $overflow_tabs = array_slice( $corner_tabs, 4 );
        ?>

        <?php foreach ( $corners as $ci => $corner ) :
            $ctab = $corner_assignments[ $corner ] ?? null;
            if ( ! $ctab || ! isset( $tab_meta[ $ctab ] ) ) { continue; }
            [ $icon, $label, $html ] = $tab_meta[ $ctab ];
        ?>
        <div class="pp-cq-corner pp-cq-corner--<?php echo esc_attr( $corner ); ?>"
             data-tab="<?php echo esc_attr( $ctab ); ?>">
            <button type="button" class="pp-cq-trigger"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_js( $ii ); ?>','<?php echo esc_js( $ctab ); ?>')">
                <span class="pp-cq-trigger__icon"><?php echo wp_kses( $icon, pzt_card_allowed_html() );?></span>
                <span class="pp-cq-trigger__label"><?php echo esc_html( $label ); ?></span>
                <span class="pp-cq-trigger__badge" id="<?php echo esc_attr( $ii ); ?>-cq-badge-<?php echo esc_attr( $corner ); ?>"></span>
            </button>
        </div>
        <?php endforeach; ?>

        <?php if ( ! empty( $overflow_tabs ) || $pp_show_review ) : ?>
        <!-- Actions row: overflow categories (Size / Drizzle / Slicing) + Review -->
        <div class="pp-cq-overflow-bar">
            <div class="pp-cq-overflow-bar__cats">
                <?php foreach ( $overflow_tabs as $otab ) :
                    if ( ! isset( $tab_meta[ $otab ] ) ) { continue; }
                    [ $oicon, $olabel, $ohtml ] = $tab_meta[ $otab ];
                ?>
                <button type="button" class="pp-cq-overflow-btn"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_attr( $ii ); ?>','<?php echo esc_js( $otab ); ?>')">
                    <span><?php echo wp_kses( $oicon, pzt_card_allowed_html() );?></span>
                    <span><?php echo esc_html( $olabel ); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <?php if ( $pp_show_review ) : ?>
            <button type="button" class="pp-cq-review-btn"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_attr( $ii ); ?>','yourpizza')">
                <span class="pp-cq-review-btn__icon">&#128203;</span>
                <span class="pp-cq-review-btn__label"><?php echo esc_html( $pp_review_label ); ?></span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Centre pizza -->
        <div class="pp-cq-pizza" id="<?php echo esc_attr( $ii ); ?>-cq-pizza">
            <div class="pp-pizza-stage-wrap" id="<?php echo esc_attr( $ii ); ?>-canvas">
                <?php echo wp_kses( $initial_pizza, pzt_card_allowed_html() );?>
            </div>
            <div class="pp-cq-pizza__controls">
                <?php if ( $pp_show_reset ) : ?>
                <button type="button" class="pp-cq-reset"
                        onclick="ClearPizza();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].resetAll();"
                        title="<?php esc_attr_e( 'Reset', 'pizzatier' ); ?>">&#8635;</button>
                <?php endif; ?>
            </div>
                <!-- Action bar moved to root level below -->
        </div>

    </div><!-- /.pp-cq-wrap -->

    <?php /* ─────────────────────────────────────────────────────────────────
           LAYOUT 2 — LAYER DECK
           Pizza dominates the top. Thumbnail strip below shows all layers.
           Click a thumb to reveal that layer's selection in an expanded card.
           ───────────────────────────────────────────────────────────────── */ ?>
    <?php elseif ( $layout === 'layer-deck' ) : ?>
    <div class="pp-ld-wrap">

        <!-- Pizza stage -->
        <div class="pp-ld-pizza-zone">
            <div class="pp-pizza-stage-wrap" id="<?php echo esc_attr( $ii ); ?>-canvas">
                <?php echo wp_kses( $initial_pizza, pzt_card_allowed_html() );?>
            </div>
            <div class="pp-ld-topping-badge" id="<?php echo esc_attr( $ii ); ?>-ld-count-wrap">
                <span id="<?php echo esc_attr( $ii ); ?>-ld-count">0</span> / <?php echo esc_html( (string) $max_toppings ); ?>
            </div>
        </div>

        <!-- Deck strip -->
        <div class="pp-ld-deck" id="<?php echo esc_attr( $ii ); ?>-ld-deck">
            <?php foreach ( $visible_tabs as $pzt_tab ) :
                if ( $pzt_tab === 'yourpizza' ) { continue; }
                if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                [ $icon, $label, $html ] = $tab_meta[ $pzt_tab ];
            ?>
            <button type="button"
                    class="pp-ld-deck-thumb"
                    data-tab="<?php echo esc_attr( $pzt_tab ); ?>"
                    id="<?php echo esc_attr( $ii ); ?>-ld-thumb-<?php echo esc_attr( $pzt_tab ); ?>"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].ldSelect('<?php echo esc_attr( $ii ); ?>','<?php echo esc_js( $pzt_tab ); ?>')">
                <span class="pp-ld-deck-thumb__icon"><?php echo wp_kses( $icon, pzt_card_allowed_html() );?></span>
                <span class="pp-ld-deck-thumb__label"><?php echo esc_html( $label ); ?></span>
                <span class="pp-ld-deck-thumb__sel" id="<?php echo esc_attr( $ii ); ?>-ld-sel-<?php echo esc_attr( $pzt_tab ); ?>"></span>
            </button>
            <?php endforeach; ?>
            <?php if ( $pp_show_review ) : ?>
            <button type="button" class="pp-ld-deck-thumb pp-ld-deck-thumb--summary"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_attr( $ii ); ?>','yourpizza')">
                <span class="pp-ld-deck-thumb__icon">&#128203;</span>
                <span class="pp-ld-deck-thumb__label"><?php echo esc_html( $pp_review_label ); ?></span>
            </button>
            <?php endif; ?>
        </div>

        <!-- Expanded selection card (fills box, shows selected layer image big) -->
        <div class="pp-ld-expand" id="<?php echo esc_attr( $ii ); ?>-ld-expand" aria-hidden="true">
            <div class="pp-ld-expand__header">
                <span class="pp-ld-expand__title" id="<?php echo esc_attr( $ii ); ?>-ld-expand-title"></span>
                <button type="button" class="pp-ld-expand__close"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].ldClose('<?php echo esc_attr( $ii ); ?>')">&#10005;</button>
            </div>
            <!-- Selected layer preview image -->
            <div class="pp-ld-expand__preview-img" id="<?php echo esc_attr( $ii ); ?>-ld-preview-img">
                <img src="" alt="" id="<?php echo esc_attr( $ii ); ?>-ld-preview-img-tag" />
                <div class="pp-ld-expand__preview-img-empty" id="<?php echo esc_attr( $ii ); ?>-ld-preview-img-empty"><?php esc_html_e( 'Tap a choice below', 'pizzatier' ); ?></div>
            </div>
            <!-- Chips for active tab -->
            <?php foreach ( $visible_tabs as $pzt_tab ) :
                if ( $pzt_tab === 'yourpizza' ) { continue; }
                if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                [ , , $html ] = $tab_meta[ $pzt_tab ];
                $is_top = ( $pzt_tab === 'toppings' );
            ?>
            <div class="pp-ld-expand__chips <?php echo $is_top ? 'pp-chips-grid--toppings' : ''; ?>"
                 id="<?php echo esc_attr( $ii ); ?>-ld-chips-<?php echo esc_attr( $pzt_tab ); ?>"
                 style="display:none;">
                <div class="pp-chips-grid">
                    <?php echo wp_kses( $html, pzt_card_allowed_html() );?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Reset bar -->
        <div class="pp-ld-controls">
            <?php if ( $pp_show_reset ) : ?>
            <button type="button" class="pp-btn pp-btn--ghost pp-btn--sm"
                    onclick="ClearPizza();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].resetAll();">
                &#8635; <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
            </button>
            <?php endif; ?>
                <!-- Action bar moved to root level below -->
        </div>

    </div><!-- /.pp-ld-wrap -->

    <?php /* ─────────────────────────────────────────────────────────────────
           LAYOUT 3 — SLIDE DRAWER
           Pizza occupies the top half. A category pill-bar sits at the bottom
           of the pizza zone. Tapping a pill slides a drawer up from below
           with that category's options.
           ───────────────────────────────────────────────────────────────── */ ?>
    <?php elseif ( $layout === 'slide-drawer' ) : ?>
    <div class="pp-sd-wrap">

        <!-- Pizza zone with category pills overlaid at bottom -->
        <div class="pp-sd-pizza-zone">
            <div class="pp-pizza-stage-wrap" id="<?php echo esc_attr( $ii ); ?>-canvas">
                <?php echo wp_kses( $initial_pizza, pzt_card_allowed_html() );?>
            </div>
            <!-- Category pills -->
            <div class="pp-sd-pills">
                <?php foreach ( $visible_tabs as $pzt_tab ) :
                    if ( $pzt_tab === 'yourpizza' ) { continue; }
                    if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                    [ $icon, $label, ] = $tab_meta[ $pzt_tab ];
                ?>
                <button type="button"
                        class="pp-sd-pill"
                        data-tab="<?php echo esc_attr( $pzt_tab ); ?>"
                        id="<?php echo esc_attr( $ii ); ?>-sd-pill-<?php echo esc_attr( $pzt_tab ); ?>"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].sdOpen('<?php echo esc_attr( $ii ); ?>','<?php echo esc_js( $pzt_tab ); ?>')">
                    <span class="pp-sd-pill__icon"><?php echo wp_kses( $icon, pzt_card_allowed_html() );?></span>
                    <span class="pp-sd-pill__text"><?php echo esc_html( $label ); ?></span>
                    <span class="pp-sd-pill__dot" id="<?php echo esc_attr( $ii ); ?>-sd-dot-<?php echo esc_attr( $pzt_tab ); ?>"></span>
                </button>
                <?php endforeach; ?>
                <?php if ( $pp_show_review ) : ?>
                <button type="button" class="pp-sd-pill pp-sd-pill--summary"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_attr( $ii ); ?>','yourpizza')">
                    &#128203; <?php echo esc_html( $pp_review_label ); ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="pp-sd-pizza-controls">
                <span class="pp-sd-count" id="<?php echo esc_attr( $ii ); ?>-sd-count-wrap">
                    &#127807; <span id="<?php echo esc_attr( $ii ); ?>-sd-count">0</span>/<?php echo esc_html( (string) $max_toppings ); ?>
                </span>
                <?php if ( $pp_show_reset ) : ?>
                <button type="button" class="pp-sd-reset"
                        onclick="ClearPizza();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].resetAll();">&#8635;</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Slide-up drawer -->
        <div class="pp-sd-drawer" id="<?php echo esc_attr( $ii ); ?>-sd-drawer" aria-hidden="true">
            <div class="pp-sd-drawer__handle"></div>
            <div class="pp-sd-drawer__header">
                <span class="pp-sd-drawer__title" id="<?php echo esc_attr( $ii ); ?>-sd-drawer-title"></span>
                <button type="button" class="pp-sd-drawer__close"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].sdClose('<?php echo esc_attr( $ii ); ?>')">&#10005;</button>
            </div>
            <?php foreach ( $visible_tabs as $pzt_tab ) :
                if ( $pzt_tab === 'yourpizza' ) { continue; }
                if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                [ , , $html ] = $tab_meta[ $pzt_tab ];
                $is_top = ( $pzt_tab === 'toppings' );
            ?>
            <div class="pp-sd-drawer__panel <?php echo $is_top ? 'pp-chips-grid--toppings' : ''; ?>"
                 id="<?php echo esc_attr( $ii ); ?>-sd-panel-<?php echo esc_attr( $pzt_tab ); ?>"
                 style="display:none;">
                <div class="pp-chips-grid"><?php echo wp_kses( $html, pzt_card_allowed_html() );?></div>
            </div>
            <?php endforeach; ?>
                <!-- Action bar moved to root level below -->
        </div>

    </div><!-- /.pp-sd-wrap -->

    <?php /* ─────────────────────────────────────────────────────────────────
           LAYOUT 4 — STACK PANEL
           Compact vertical layout: small pizza preview, progress dots, and
           a step-based bottom-sheet that slides in from the bottom.
           ───────────────────────────────────────────────────────────────── */ ?>
    <?php else : /* stack-panel */ ?>
    <div class="pp-sp-wrap">

        <!-- Compact pizza + step indicator -->
        <div class="pp-sp-top">
            <div class="pp-sp-pizza-mini" id="<?php echo esc_attr( $ii ); ?>-canvas">
                <?php echo wp_kses( $initial_pizza, pzt_card_allowed_html() );?>
            </div>
            <div class="pp-sp-step-info">
                <div class="pp-sp-step-dots" id="<?php echo esc_attr( $ii ); ?>-sp-dots">
                    <?php foreach ( $visible_tabs as $dot ) : ?>
                    <span class="pp-sp-dot" data-step="<?php echo esc_attr( $dot ); ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="pp-sp-step-label" id="<?php echo esc_attr( $ii ); ?>-sp-label">
                    <?php esc_html_e( 'Start building your pizza', 'pizzatier' ); ?>
                </div>
            </div>
        </div>

        <!-- Step nav bar -->
        <div class="pp-sp-stepbar" id="<?php echo esc_attr( $ii ); ?>-sp-stepbar">
            <?php $first_sp = true; foreach ( $visible_tabs as $pzt_tab ) :
                if ( $pzt_tab === 'yourpizza' ) { continue; }
                if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                [ $icon, $label, ] = $tab_meta[ $pzt_tab ];
                $active_class = $first_sp ? 'active' : '';
                $first_sp = false;
            ?>
            <button type="button"
                    class="pp-sp-step <?php echo esc_attr( $active_class ); ?>"
                    data-tab="<?php echo esc_attr( $pzt_tab ); ?>"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].spOpen('<?php echo esc_attr( $ii ); ?>','<?php echo esc_js( $pzt_tab ); ?>')">
                <span class="pp-sp-step__icon"><?php echo wp_kses( $icon, pzt_card_allowed_html() );?></span>
                <span class="pp-sp-step__label"><?php echo esc_html( $label ); ?></span>
                <span class="pp-sp-step__dot" id="<?php echo esc_attr( $ii ); ?>-sp-step-dot-<?php echo esc_attr( $pzt_tab ); ?>"></span>
            </button>
            <?php endforeach; ?>
            <?php if ( $pp_show_review ) : ?>
            <button type="button" class="pp-sp-step pp-sp-step--summary"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].openModal('<?php echo esc_attr( $ii ); ?>','yourpizza')">
                <span class="pp-sp-step__icon">&#128203;</span>
                <span class="pp-sp-step__label"><?php echo esc_html( $pp_review_label ); ?></span>
            </button>
            <?php endif; ?>
        </div>

        <!-- Bottom sheet panel -->
        <div class="pp-sp-sheet" id="<?php echo esc_attr( $ii ); ?>-sp-sheet" aria-hidden="true">
            <div class="pp-sp-sheet__grip"></div>
            <div class="pp-sp-sheet__header">
                <span class="pp-sp-sheet__title" id="<?php echo esc_attr( $ii ); ?>-sp-sheet-title"></span>
                <button type="button" class="pp-sp-sheet__close"
                        onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].spClose('<?php echo esc_attr( $ii ); ?>')">&#10005;</button>
            </div>
            <?php foreach ( $visible_tabs as $pzt_tab ) :
                if ( $pzt_tab === 'yourpizza' ) { continue; }
                if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
                [ , , $html ] = $tab_meta[ $pzt_tab ];
                $is_top = ( $pzt_tab === 'toppings' );
            ?>
            <div class="pp-sp-sheet__panel <?php echo $is_top ? 'pp-chips-grid--toppings' : ''; ?>"
                 id="<?php echo esc_attr( $ii ); ?>-sp-panel-<?php echo esc_attr( $pzt_tab ); ?>"
                 style="display:none;">
                <div class="pp-chips-grid"><?php echo wp_kses( $html, pzt_card_allowed_html() );?></div>
                <?php if ( $is_top ) : ?>
                <div class="pp-sp-topping-count">
                    <span id="<?php echo esc_attr( $ii ); ?>-sp-count">0</span>/<?php echo esc_html( (string) $max_toppings ); ?> <?php esc_html_e( 'toppings', 'pizzatier' ); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="pp-sp-sheet__actions">
                <?php if ( $pp_show_reset ) : ?>
                <button type="button" class="pp-btn pp-btn--ghost pp-btn--sm"
                        onclick="ClearPizza();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].resetAll();">
                    &#8635; <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
                </button>
                <?php endif; ?>
                <!-- Action bar moved to root level below -->
            </div>
        </div>

    </div><!-- /.pp-sp-wrap -->
    <?php endif; // end layout switch ?>

    <!-- ═══════════════════════════════════════
         SHARED: Summary Modal (all layouts)
         ═══════════════════════════════════════ -->
    <div class="pp-modal-overlay" id="<?php echo esc_attr( $ii ); ?>-modal-overlay" aria-hidden="true"
         <?php if ( $pp_close_on_backdrop ) : ?>onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].closeModal('<?php echo esc_attr( $ii ); ?>')"<?php endif; ?>>
    </div>
    <div class="pp-modal" id="<?php echo esc_attr( $ii ); ?>-modal" role="dialog" aria-hidden="true">
        <div class="pp-modal__header">
            <span class="pp-modal__title" id="<?php echo esc_attr( $ii ); ?>-modal-title"
                  data-default="<?php echo esc_attr( $pp_summary_title ); ?>"><?php echo esc_html( $pp_summary_title ); ?></span>
            <button type="button" class="pp-modal__close"
                    onclick="window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].closeModal('<?php echo esc_attr( $ii ); ?>')">&#10005;</button>
        </div>
        <div class="pp-modal__body" id="<?php echo esc_attr( $ii ); ?>-modal-body">
            <!-- Dynamic content depending on which tab triggered this modal -->
        </div>
        <!-- Category panels live in this modal ONLY for corner-quad, where the
             corner triggers and the actions-row buttons open them here. Other
             layouts render their categories in their own drawers/sheets/expands,
             so we skip them to avoid duplicate IDs (e.g. size radios). -->
        <?php if ( $layout === 'corner-quad' ) : ?>
        <?php foreach ( $visible_tabs as $pzt_tab ) :
            if ( $pzt_tab === 'yourpizza' ) { continue; }
            if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
            [ , , $html ] = $tab_meta[ $pzt_tab ];
            $is_top  = ( $pzt_tab === 'toppings' );
            $is_size = ( $pzt_tab === 'size' );
        ?>
        <div class="pp-modal__tab-panel <?php echo $is_top ? 'pp-chips-grid--toppings' : ''; ?> <?php echo $is_size ? 'pp-modal__tab-panel--size' : ''; ?>"
             id="<?php echo esc_attr( $ii ); ?>-modal-panel-<?php echo esc_attr( $pzt_tab ); ?>"
             style="display:none;">
            <div class="pp-chips-grid"><?php echo wp_kses( $html, pzt_card_allowed_html() );?></div>
            <?php if ( $is_top ) : ?>
            <div class="pp-modal__topping-count">
                <span id="<?php echo esc_attr( $ii ); ?>-modal-count">0</span>/<?php echo esc_html( (string) $max_toppings ); ?> <?php esc_html_e( 'toppings', 'pizzatier' ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <!-- Summary panel -->
        <div class="pp-modal__summary" id="<?php echo esc_attr( $ii ); ?>-modal-summary" style="display:none;">
            <?php foreach ( $summary_rows as $key => [ $ico, $slabel ] ) : ?>
            <div class="pp-summary-row" id="<?php echo esc_attr( $ii ); ?>-modal-yp-<?php echo esc_attr( $key ); ?>">
                <span class="pp-summary-row__icon"><?php echo wp_kses( $ico, pzt_card_allowed_html() );?></span>
                <span class="pp-summary-row__label"><?php echo esc_html( $slabel ); ?></span>
                <span class="pp-summary-row__val pp-summary-row__val--empty"
                      id="<?php echo esc_attr( $ii ); ?>-modal-yp-<?php echo esc_attr( $key ); ?>-val">
                    — <?php esc_html_e( 'none', 'pizzatier' ); ?> —
                </span>
            </div>
            <?php endforeach; ?>
            <div class="pp-modal__summary-actions">
                <!-- Action bar moved to root level below -->
                <button type="button" class="pp-btn pp-btn--ghost pp-btn--sm"
                        onclick="ClearPizza();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].resetAll();window['<?php echo esc_js( $pv ); ?>']&&window['<?php echo esc_js( $pv ); ?>'].closeModal('<?php echo esc_attr( $ii ); ?>')">
                    &#8635; <?php esc_html_e( 'Start Over', 'pizzatier' ); ?>
                </button>
            </div>
        </div>
    </div><!-- /.pp-modal -->

	<?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>

</div><!-- /.pp-root -->

<?php
// Initialize this instance via wp_add_inline_script (WP.org compliant — no inline <script>).
$pp_init_js = "if(typeof PP!=='undefined'&&typeof PP.createInstance==='function'){"
	. "var " . esc_js( $pp_var ) . "=PP.createInstance(" . wp_json_encode( $instance_id ) . ");"
	. "}";
wp_add_inline_script( 'pizzatier-template-pocketpie', $pp_init_js );
