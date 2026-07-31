<?php
/**
 * Metro Template — [pizza_builder] output.
 *
 * Layout concept:
 *   - Full-width builder, single vertical scroll — no side-by-side panes.
 *   - Pizza hero: a centered, sticky-on-scroll pizza stage sits at the top,
 *     collapsing to a compact floating "orb" bar as the user scrolls down
 *     into the ingredient sections.
 *   - Ingredient sections are full-width rows: each one has a bold section
 *     header on the left and a responsive card grid on the right.
 *   - Toppings get a search/filter bar above the grid.
 *   - "Your Pizza" summary docks to the bottom as a persistent tray.
 *   - Step mode: toggle button switches to a stepped tab-by-tab flow.
 *
 * Shortcode attributes (layer offsets — 0–100, default 0):
 *   crust_offset, sauce_offset, cheese_offset, topping_offset, drizzle_offset, cut_offset
 *
 * Variables available (from BuilderShortcode):
 *   $instance_id   — unique ID string
 *   $atts          — shortcode attribute array
 *   $template_slug — 'metro'
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

if ( ! isset( $instance_id ) )    { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )           { $atts           = []; }
if ( ! isset( $template_slug ) )  { $template_slug  = 'metro'; }
if ( ! isset( $function_prefix ) ) { $function_prefix = 'pzt_metro'; }

// Per-instance JS namespace
$mt_var = 'MT_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

// ── Read Metro template settings ─────────────────────────────────────────────
$mt_layout       = sanitize_key( get_option( 'metro_setting_layout_mode',             'centered' ) );
$mt_show_tray    =               get_option( 'metro_setting_show_summary_bar',         'yes' ) === 'yes';
$mt_show_count   =               get_option( 'metro_setting_show_ingredient_count',    'yes' ) === 'yes';
// NOTE: pricing is provided by PizzaTierPro; the legacy
// 'metro_setting_show_ingredient_prices' option is no longer read here.
$mt_sticky_viz   =               get_option( 'metro_setting_sticky_visualizer',        'no'  ) === 'yes';
$mt_hero_tagline = sanitize_text_field( get_option( 'metro_setting_hero_tagline',     '' ) );
$mt_footer_note  = wp_kses_post( get_option( 'metro_setting_footer_note',             '' ) );
$mt_columns      = sanitize_key( get_option( 'metro_setting_card_columns',            '3'  ) );
$mt_card_style   = sanitize_key( get_option( 'metro_setting_card_style',              'standard' ) );
$mt_tab_style    = sanitize_key( get_option( 'metro_setting_tab_style',               'scrollbar' ) );

// Layout modifier class for CSS hooks
$mt_layout_class = 'mt-layout--' . $mt_layout;

// Column grid class
$mt_col_class_map = [
	'2'    => 'mt-cards-grid--cols-2',
	'3'    => 'mt-cards-grid--cols-3',
	'4'    => 'mt-cards-grid--cols-4',
	'auto' => 'mt-cards-grid--cols-auto',
];
$mt_col_class = $mt_col_class_map[ $mt_columns ] ?? '';

// Per-instance JS namespace
$mt_var = 'MT_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

// Max toppings
$max_toppings = isset( $atts['max_toppings'] ) && (int) $atts['max_toppings'] > 0
	? (int) $atts['max_toppings']
	: intval( get_option( 'pizzatier_setting_topping_maxtoppings', 0 ) );
if ( $max_toppings < 1 ) { $max_toppings = 99; }
$max_toppings = (int) apply_filters( 'pizzatier_max_toppings', $max_toppings, $instance_id );

// Pizza shape
$valid_shapes = [ 'round', 'square', 'rectangle', 'custom' ];
$pizza_shape  = sanitize_key( $atts['pizza_shape'] ?? get_option( 'pizzatier_setting_pizza_shape', 'round' ) );
if ( ! in_array( $pizza_shape, $valid_shapes, true ) ) { $pizza_shape = 'round'; }
$pizza_aspect = sanitize_text_field( $atts['pizza_aspect'] ?? get_option( 'pizzatier_setting_pizza_aspect', '1 / 1' ) );
$pizza_radius = sanitize_text_field( $atts['pizza_radius'] ?? get_option( 'pizzatier_setting_pizza_radius', '8px' ) );

// Layer animation
$valid_anims = [ 'fade', 'scale-in', 'slide-up', 'flip-in', 'drop-in', 'instant' ];
$layer_anim  = sanitize_key( $atts['layer_anim'] ?? get_option( 'pizzatier_setting_layer_anim', 'fade' ) );
if ( ! in_array( $layer_anim, $valid_anims, true ) ) { $layer_anim = 'fade'; }
$layer_anim_speed = isset( $atts['layer_anim_speed'] ) && (int) $atts['layer_anim_speed'] > 0
	? max( 80, min( 800, (int) $atts['layer_anim_speed'] ) )
	: max( 80, min( 800, (int) get_option( 'pizzatier_setting_layer_anim_speed', 320 ) ) );

// Layer offset values (0–100, controls how much each layer is inset from the pizza edge)
$clamp_offset = function( $val ) { $v = (int) $val; return max( 0, min( 100, $v ) ); };
$layer_offsets = [
	'crust'   => $clamp_offset( $atts['crust_offset']   ?? 0 ),
	'sauce'   => $clamp_offset( $atts['sauce_offset']   ?? 0 ),
	'cheese'  => $clamp_offset( $atts['cheese_offset']  ?? 0 ),
	'topping' => $clamp_offset( $atts['topping_offset'] ?? 0 ),
	'drizzle' => $clamp_offset( $atts['drizzle_offset'] ?? 0 ),
	'cut'     => $clamp_offset( $atts['cut_offset']     ?? 0 ),
];
$offsets_json = wp_json_encode( $layer_offsets );


// ── PizzaTierPro: inline size selector ──────────────────────────────────────
if ( ! function_exists( 'pzt_get_pro_sizes' ) ) :
function pzt_get_pro_sizes(): array {
	if ( ! function_exists( 'pztpro_get_setting' ) || ! class_exists( 'PizzaTierPro\\Pro\\PriceGrid\\Grid' ) ) { return []; }
	$product_id = ( function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0 );
	if ( ! $product_id ) { global $post; if ( $post instanceof \WP_Post ) { $product_id = $post->ID; } }
	$grid = new \PizzaTierPro\Pro\PriceGrid\Grid(); return $grid->get_sizes( $product_id );
}
endif;
if ( ! function_exists( 'pzt_render_inline_size_selector' ) ) :
function pzt_render_inline_size_selector( array $sizes, string $instance_id, string $css_prefix = 'cb' ): void {
	if ( empty( $sizes ) ) { return; }
	// Extract numeric suffix from instance_id (handles pztpro-1, pizzabuilder-1, pztpro-1-2, etc)
	preg_match( '/-(\d+)$/', $instance_id, $_m_suf );
	$radio_name_raw = ! empty( $_m_suf[1] ) ? $_m_suf[1] : preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );
	$radio_name = 'pztpro_size_' . $radio_name_raw;
	$heading = function_exists( 'pztpro_get_setting' ) ? (string) pztpro_get_setting( 'size_selector_label', '' ) : '';
	if ( '' === $heading ) { $heading = __( 'Choose a Size', 'pizzatier' ); }
	?>
	<div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector pztpro-inline-size-selector" id="<?php echo esc_attr( $instance_id ); ?>-size-selector" role="group" aria-label="<?php echo esc_attr( $heading ); ?>">
		<p class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__heading"><?php echo esc_html( $heading ); ?></p>
		<div class="<?php echo esc_attr( $css_prefix ); ?>-size-selector__options">
			<?php foreach ( $sizes as $i => $size ) :
				$inp_id = esc_attr( $instance_id ) . '-sz-' . sanitize_html_class( strtolower( $size ) ); ?>
			<label class="<?php echo esc_attr( $css_prefix ); ?>-size-option pztpro-size-option<?php echo 0 === $i ? ' pztpro-size-option--active' : ''; ?>" for="<?php echo esc_attr( $inp_id ); ?>">
				<input type="radio" id="<?php echo esc_attr( $inp_id ); ?>" name="<?php echo esc_attr( $radio_name ); ?>" value="<?php echo esc_attr( $size ); ?>" class="pztpro-size-radio" <?php checked( 0, $i ); ?> />
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

// Visible tabs
$hide_tabs_raw = $atts['hide_tabs'] ?? '';
$show_tabs_raw = $atts['show_tabs'] ?? '';
$all_tabs      = [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing' ]; // no 'yourpizza' — handled by tray
$all_tabs      = apply_filters( 'pizzatier_tab_order', $all_tabs, $instance_id );

if ( $show_tabs_raw ) {
	$visible_tabs = array_intersect( $all_tabs, array_map( 'trim', explode( ',', $show_tabs_raw ) ) );
} elseif ( $hide_tabs_raw ) {
	$hide_set     = array_map( 'trim', explode( ',', $hide_tabs_raw ) );
	$visible_tabs = array_diff( $all_tabs, $hide_set );
} else {
	$visible_tabs = $all_tabs;
}

// Query CPTs
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

// ── Card builders ────────────────────────────────────────────────────────────

/**
 * Exclusive-select card (crust / sauce / cheese / drizzle / cut).
 */
if ( ! function_exists( 'pzt_metro_exclusive_card' ) ) :
function pzt_metro_exclusive_card( $post, string $layer_type, string $mt_var, int $zindex = 200 ): string {
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
	$js_add    = "window['{$mt_var}']&&window['{$mt_var}'].swapBase('{$layer_type}','{$slug}','{$js_title}','{$js_layer}',this)";
	$js_remove = "window['{$mt_var}']&&window['{$mt_var}'].removeBase('{$layer_type}','{$slug}',this)";

	ob_start();
	do_action( 'pizzatier_before_layer_card', $post, $layer_type );
	?>
	<div class="mt-card mt-card--exclusive"
	     data-layer="<?php echo esc_attr( $layer_type ); ?>"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>">
		<div class="mt-card__img-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="mt-card__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="mt-card__img mt-card__img--placeholder"></div>
			<?php endif; ?>
		</div>
		<div class="mt-card__footer">
			<div class="mt-card__footer-row mt-card__footer-row--title">
				<span class="mt-card__name"><?php echo esc_html( $title ); ?></span>
				<?php /* Pricing is provided by PizzaTierPro; no inline price here. */ ?>
			</div>
			<div class="mt-card__footer-row mt-card__footer-row--actions">
				<button type="button" class="mt-card__btn mt-card__btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
					<i class="fa fa-plus"></i>
				</button>
				<button type="button" class="mt-card__btn mt-card__btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
					<i class="fa fa-plus mt-card__icon-selected"></i>
				</button>
			</div>
		</div>
	</div>
	<?php
	do_action( 'pizzatier_after_layer_card', $post, $layer_type );
	return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, $layer_type );
}
endif;

/**
 * Topping card (multi-select; coverage via modal).
 */
if ( ! function_exists( 'pzt_metro_topping_card' ) ) :
function pzt_metro_topping_card( $post, string $mt_var, int $zindex ): string {
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
	$js_thumb  = esc_js( (string) $thumb_url );

	$js_add    = "window['{$mt_var}']&&window['{$mt_var}'].addTopping({$zindex},'{$js_slug}','{$js_layer}','{$js_title}','{$layer_id}','{$layer_id}',this)";
	$js_remove = "window['{$mt_var}']&&window['{$mt_var}'].removeTopping('pizzatier-topping-{$js_slug}','{$js_slug}',this)";
	$js_cov    = "window['{$mt_var}']&&window['{$mt_var}'].openCoverageModal('{$js_slug}','{$js_title}','{$js_thumb}')";

	ob_start();
	do_action( 'pizzatier_before_layer_card', $post, 'toppings' );
	?>
	<div class="mt-card mt-card--topping"
	     data-layer="toppings"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>"
	     data-zindex="<?php echo esc_attr( (string) $zindex ); ?>">
		<div class="mt-card__img-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="mt-card__img" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="mt-card__img mt-card__img--placeholder"></div>
			<?php endif; ?>
		</div>
		<div class="mt-card__footer">
			<div class="mt-card__footer-row mt-card__footer-row--title">
				<span class="mt-card__name"><?php echo esc_html( $title ); ?></span>
				<?php /* Pricing is provided by PizzaTierPro; no inline price here. */ ?>
			</div>
			<div class="mt-card__footer-row mt-card__footer-row--actions">
				<button type="button" class="mt-card__btn mt-card__btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
					<i class="fa fa-plus"></i>
				</button>
				<button type="button" class="mt-card__btn mt-card__btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
					<i class="fa fa-plus mt-card__icon-selected"></i>
				</button>
				<button type="button" class="mt-card__btn mt-card__btn--coverage" style="display:none;"
				        title="<?php esc_attr_e( 'Change coverage', 'pizzatier' ); ?>"
				        onclick="<?php echo esc_attr( $js_cov ); ?>">
					<i class="fa fa-circle-half-stroke"></i>
				</button>
			</div>
		</div>
	</div>
	<?php
	do_action( 'pizzatier_after_layer_card', $post, 'toppings' );
	return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, 'toppings' );
}
endif;

// Build all card HTML
$crusts_html = '';
foreach ( $crusts as $post ) { $crusts_html .= pzt_metro_exclusive_card( $post, 'crust', $mt_var, 100 ); }
if ( ! $crusts_html ) { $crusts_html = '<p class="mt-empty">' . esc_html__( 'No crusts found.', 'pizzatier' ) . '</p>'; }

$sauces_html = '';
foreach ( $sauces as $post ) { $sauces_html .= pzt_metro_exclusive_card( $post, 'sauce', $mt_var, 150 ); }
if ( ! $sauces_html ) { $sauces_html = '<p class="mt-empty">' . esc_html__( 'No sauces found.', 'pizzatier' ) . '</p>'; }

$cheeses_html = '';
foreach ( $cheeses as $post ) { $cheeses_html .= pzt_metro_exclusive_card( $post, 'cheese', $mt_var, 200 ); }
if ( ! $cheeses_html ) { $cheeses_html = '<p class="mt-empty">' . esc_html__( 'No cheeses found.', 'pizzatier' ) . '</p>'; }

$drizzles_html = '';
foreach ( $drizzles as $post ) { $drizzles_html .= pzt_metro_exclusive_card( $post, 'drizzle', $mt_var, 900 ); }
if ( ! $drizzles_html ) { $drizzles_html = '<p class="mt-empty">' . esc_html__( 'No drizzles found.', 'pizzatier' ) . '</p>'; }

$toppings_html = '';
$t_z = 400;
foreach ( $toppings as $post ) { $toppings_html .= pzt_metro_topping_card( $post, $mt_var, $t_z ); $t_z += 10; }
if ( ! $toppings_html ) { $toppings_html = '<p class="mt-empty">' . esc_html__( 'No toppings found.', 'pizzatier' ) . '</p>'; }

$cuts_html = '';
foreach ( $cuts as $post ) { $cuts_html .= pzt_metro_exclusive_card( $post, 'cut', $mt_var, 950 ); }
if ( ! $cuts_html ) { $cuts_html = '<p class="mt-empty">' . esc_html__( 'No cut styles found.', 'pizzatier' ) . '</p>'; }

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

// Section meta: tab key → [ icon, label, content ]
// Icons chosen to reflect the ingredient type clearly.
$section_meta = [
	'size'     => [ 'fa-ruler-combined', __( 'Size',    'pizzatier' ), '' ],
	'crust'    => [ 'fa-layer-group',  __( 'Crust',   'pizzatier' ), $crusts_html   ],
	'sauce'    => [ 'fa-droplet',      __( 'Sauce',   'pizzatier' ), $sauces_html   ],
	'cheese'   => [ 'fa-cheese',       __( 'Cheese',  'pizzatier' ), $cheeses_html  ],
	'toppings' => [ 'fa-leaf',         __( 'Toppings','pizzatier' ), $toppings_html ],
	'drizzle'  => [ 'fa-bottle-droplet', __( 'Drizzle', 'pizzatier' ), $drizzles_html ],
	'slicing'  => [ 'fa-pizza-slice',  __( 'Slicing', 'pizzatier' ), $cuts_html     ],
];
?>
<!-- ════════════════════════════════════════════════════
     METRO TEMPLATE — PizzaTier
     Instance: <?php echo esc_html( $instance_id ); ?>
════════════════════════════════════════════════════ -->
<div id="<?php echo esc_attr( $instance_id ); ?>"
     class="mt-root <?php echo esc_attr( $mt_layout_class ); ?> mt-cards--<?php echo esc_attr( $mt_card_style ); ?> mt-tabs--<?php echo esc_attr( $mt_tab_style ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-mt-var="<?php echo esc_attr( $mt_var ); ?>"
     data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
     data-pizza-shape="<?php echo esc_attr( $pizza_shape ); ?>"
     data-pizza-aspect="<?php echo esc_attr( $pizza_aspect ); ?>"
     data-pizza-radius="<?php echo esc_attr( $pizza_radius ); ?>"
     data-layer-anim="<?php echo esc_attr( $layer_anim ); ?>"
     data-layer-anim-speed="<?php echo esc_attr( (string) $layer_anim_speed ); ?>"
     data-layer-offsets='<?php echo esc_attr( $offsets_json ); ?>'
     data-layout="<?php echo esc_attr( $mt_layout ); ?>"
     data-show-count="<?php echo $mt_show_count ? 'yes' : 'no'; ?>">

	<!-- ── Hero: centered pizza stage ───────────────────────── -->
	<div class="mt-hero" id="<?php echo esc_attr( $instance_id ); ?>-hero">
		<div class="mt-hero__inner">

			<?php if ( $mt_hero_tagline ) : ?>
			<p class="mt-hero__tagline"><?php echo esc_html( $mt_hero_tagline ); ?></p>
			<?php endif; ?>

			<div class="mt-hero__pizza-wrap">
				<div class="mt-hero__canvas" id="<?php echo esc_attr( $instance_id ); ?>-canvas">
					<?php echo $initial_pizza; // phpcs:ignore WordPress.Security.EscapeOutput -- built by PizzaBuilder ?>
				</div>
			</div>

			<div class="mt-hero__meta">
				<div class="mt-hero__count-wrap<?php echo ! $mt_show_count ? ' mt-hero__count-wrap--hidden' : ''; ?>">
					<span class="mt-hero__count-label"><i class="fa fa-layer-group"></i></span>
					<span class="mt-hero__count" id="<?php echo esc_attr( $instance_id ); ?>-count">0</span>
					<span class="mt-hero__count-max">/ <?php echo esc_html( (string) $max_toppings ); ?></span>
					<span class="mt-hero__count-word"><?php esc_html_e( 'toppings', 'pizzatier' ); ?></span>
				</div>
				<button type="button" class="mt-reset-btn"
				        onclick="ClearPizza(); window['<?php echo esc_js( $mt_var ); ?>']&&window['<?php echo esc_js( $mt_var ); ?>'].resetAll();">
					<i class="fa fa-rotate-left"></i> <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
				</button>
			</div>

		</div>

		<!-- Scroll indicator -->
		<div class="mt-hero__scroll-hint" aria-hidden="true">
			<span><?php esc_html_e( 'Scroll to build', 'pizzatier' ); ?></span>
			<i class="fa fa-chevron-down"></i>
		</div>
	</div>

	<!-- ── Sticky mini-orb (appears when hero scrolls out of view) ── -->
	<div class="mt-orb" id="<?php echo esc_attr( $instance_id ); ?>-orb" aria-hidden="true">
		<div class="mt-orb__pizza" id="<?php echo esc_attr( $instance_id ); ?>-orb-pizza"></div>
		<div class="mt-orb__info">
			<span class="mt-orb__label"><?php esc_html_e( 'Your pizza', 'pizzatier' ); ?></span>
			<span class="mt-orb__count"><span id="<?php echo esc_attr( $instance_id ); ?>-orb-count">0</span> <?php esc_html_e( 'toppings', 'pizzatier' ); ?></span>
		</div>
		<button class="mt-orb__back" onclick="document.getElementById('<?php echo esc_js( $instance_id ); ?>-hero').scrollIntoView({behavior:'smooth'})">
			<i class="fa fa-arrow-up"></i>
		</button>
	</div>

	<!-- ── Builder-wrap (flex row in side-by-side mode) ──────── -->
	<div class="mt-builder-wrap">

		<?php if ( $mt_layout === 'side-by-side' ) : ?>
		<!-- Sidebar: sticky pizza canvas for side-by-side mode -->
		<aside class="mt-sidebar" id="<?php echo esc_attr( $instance_id ); ?>-sidebar" aria-label="<?php esc_attr_e( 'Pizza preview', 'pizzatier' ); ?>">
			<?php if ( $mt_hero_tagline ) : ?>
			<p class="mt-hero__tagline mt-sidebar__tagline"><?php echo esc_html( $mt_hero_tagline ); ?></p>
			<?php endif; ?>
			<div class="mt-sidebar__pizza-wrap">
				<div class="mt-hero__canvas mt-sidebar__canvas" id="<?php echo esc_attr( $instance_id ); ?>-sidebar-canvas">
					<?php echo $initial_pizza; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
			<div class="mt-hero__meta">
				<div class="mt-hero__count-wrap<?php echo ! $mt_show_count ? ' mt-hero__count-wrap--hidden' : ''; ?>">
					<span class="mt-hero__count-label"><i class="fa fa-layer-group"></i></span>
					<span class="mt-hero__count mt-sidebar__count">0</span>
					<span class="mt-hero__count-max">/ <?php echo esc_html( (string) $max_toppings ); ?></span>
					<span class="mt-hero__count-word"><?php esc_html_e( 'toppings', 'pizzatier' ); ?></span>
				</div>
				<button type="button" class="mt-reset-btn"
				        onclick="ClearPizza(); window['<?php echo esc_js( $mt_var ); ?>']&&window['<?php echo esc_js( $mt_var ); ?>'].resetAll();">
					<i class="fa fa-rotate-left"></i> <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
				</button>
			</div>
		</aside>
		<?php endif; ?>
	<div class="mt-builder" id="<?php echo esc_attr( $instance_id ); ?>-builder">

		<!-- Section nav (anchors + step-mode toggle) -->
		<nav class="mt-section-nav" id="<?php echo esc_attr( $instance_id ); ?>-section-nav" aria-label="<?php esc_attr_e( 'Builder sections', 'pizzatier' ); ?>">
			<div class="mt-section-nav__links">
				<?php foreach ( $visible_tabs as $tab ) :
					if ( ! isset( $section_meta[ $tab ] ) ) { continue; }
					[ $icon, $label ] = $section_meta[ $tab ];
				?>
				<a class="mt-section-nav__item" data-section="<?php echo esc_attr( $tab ); ?>"
				   href="#<?php echo esc_attr( $instance_id . '-section-' . $tab ); ?>">
					<i class="fa <?php echo esc_attr( $icon ); ?>"></i>
					<span><?php echo esc_html( $label ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<button type="button"
			        class="mt-mode-toggle"
			        id="<?php echo esc_attr( $instance_id ); ?>-mode-toggle"
			        title="<?php esc_attr_e( 'Switch to step-by-step mode', 'pizzatier' ); ?>"
			        aria-pressed="false">
				<i class="fa fa-list-ol mt-mode-toggle__icon-step"></i>
				<i class="fa fa-grip mt-mode-toggle__icon-scroll" style="display:none;"></i>
				<span class="mt-mode-toggle__label"><?php esc_html_e( 'Step mode', 'pizzatier' ); ?></span>
			</button>
		</nav>

		<!-- Step-mode navigation bar (prev/next, hidden in scroll mode) -->
		<div class="mt-step-nav" id="<?php echo esc_attr( $instance_id ); ?>-step-nav" aria-hidden="true">
			<button type="button" class="mt-step-nav__prev" id="<?php echo esc_attr( $instance_id ); ?>-step-prev" disabled>
				<i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Previous', 'pizzatier' ); ?>
			</button>
			<span class="mt-step-nav__indicator" id="<?php echo esc_attr( $instance_id ); ?>-step-indicator">
				<span class="mt-step-nav__current">1</span> / <span class="mt-step-nav__total"><?php echo esc_html( (string) count( $visible_tabs ) ); ?></span>
			</span>
			<button type="button" class="mt-step-nav__next" id="<?php echo esc_attr( $instance_id ); ?>-step-next">
				<?php esc_html_e( 'Next', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i>
			</button>
		</div>

		<!-- Section rows -->

	<?php if ( $_has_pro && in_array( 'size', $visible_tabs, true ) ) : ?>
	<?php do_action( 'pizzatier_before_tab_size', $instance_id ); ?>
	<section class="mt-section" id="<?php echo esc_attr( $instance_id . '-section-size' ); ?>" data-section="size">
		<div class="mt-section__header">
			<div class="mt-section__header-inner">
				<span class="mt-section__icon"><i class="fa fa-ruler-combined"></i></span>
				<h2 class="mt-section__title"><?php esc_html_e( 'Size', 'pizzatier' ); ?></h2>
			</div>
		</div>
		<?php pzt_render_inline_size_selector( $_pro_sizes, $instance_id, 'mt' ); ?>
	</section>
	<?php do_action( 'pizzatier_after_tab_size', $instance_id ); ?>
	<?php endif; ?>

		<?php foreach ( $visible_tabs as $tab ) :
			if ( ! isset( $section_meta[ $tab ] ) ) { continue; }
			[ $icon, $label, $cards_html ] = $section_meta[ $tab ];
			$is_toppings = ( $tab === 'toppings' );
		?>
		<?php do_action( 'pizzatier_before_tab_' . $tab, $instance_id ); ?>
		<section class="mt-section" id="<?php echo esc_attr( $instance_id . '-section-' . $tab ); ?>" data-section="<?php echo esc_attr( $tab ); ?>">

			<div class="mt-section__header">
				<div class="mt-section__header-inner">
					<span class="mt-section__icon"><i class="fa <?php echo esc_attr( $icon ); ?>"></i></span>
					<h2 class="mt-section__title"><?php echo esc_html( $label ); ?></h2>
					<?php if ( $is_toppings ) : ?>
					<span class="mt-section__badge mt-section__badge--toppings" id="<?php echo esc_attr( $instance_id ); ?>-topping-badge">0</span>
					<?php endif; ?>
				</div>
				<?php if ( $is_toppings ) : ?>
				<div class="mt-section__search-wrap">
					<i class="fa fa-magnifying-glass mt-section__search-icon"></i>
					<input type="search" class="mt-section__search"
					       id="<?php echo esc_attr( $instance_id ); ?>-topping-search"
					       placeholder="<?php esc_attr_e( 'Filter toppings…', 'pizzatier' ); ?>"
					       aria-label="<?php esc_attr_e( 'Filter toppings', 'pizzatier' ); ?>">
				</div>
				<?php endif; ?>
			</div>

			<div class="mt-cards-grid<?php echo $is_toppings ? ' mt-cards-grid--toppings' : ' mt-cards-grid--exclusive'; ?> <?php echo esc_attr( $mt_col_class ); ?>">
				<?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>

			<?php if ( $tab === 'slicing' ) : ?>
			<!-- Action bar: PizzaTierPro hooks here -->
			<div class="mt-action-bar">
				<!-- Action bar moved to root level below -->
			</div>
			<?php endif; ?>

		</section>
		<?php do_action( 'pizzatier_after_tab_' . $tab, $instance_id ); ?>
		<?php endforeach; ?>

	</div><!-- /.mt-builder -->

	</div><!-- /.mt-builder-wrap -->

	<!-- ── Coverage modal (shared, one per instance) ────────── -->
	<div class="mt-cov-modal" id="<?php echo esc_attr( $instance_id ); ?>-cov-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Choose coverage', 'pizzatier' ); ?>">
		<div class="mt-cov-modal__backdrop"></div>
		<div class="mt-cov-modal__panel">
			<div class="mt-cov-modal__header">
				<span class="mt-cov-modal__thumb-wrap">
					<img class="mt-cov-modal__thumb" src="" alt="" />
				</span>
				<div>
					<p class="mt-cov-modal__label"><?php esc_html_e( 'Coverage for', 'pizzatier' ); ?></p>
					<strong class="mt-cov-modal__name"></strong>
				</div>
				<button type="button" class="mt-cov-modal__close" aria-label="<?php esc_attr_e( 'Close', 'pizzatier' ); ?>">
					<i class="fa fa-times"></i>
				</button>
			</div>
			<div class="mt-cov-modal__btns">
				<?php
				$_all_coverages_mt = [
					'whole'               => [ 'Whole',   'mt-cov--whole',  'fa-circle'                ],
					'half-left'           => [ 'Left ½',  'mt-cov--hl',     'fa-circle-half-stroke'    ],
					'half-right'          => [ 'Right ½', 'mt-cov--hr',     'fa-circle-half-stroke'    ],
					'quarter-top-left'    => [ 'Q1',      'mt-cov--q1',     'fa-circle-quarter-stroke' ],
					'quarter-top-right'   => [ 'Q2',      'mt-cov--q2',     'fa-circle-quarter-stroke' ],
					'quarter-bottom-left' => [ 'Q3',      'mt-cov--q3',     'fa-circle-quarter-stroke' ],
					'quarter-bottom-right'=> [ 'Q4',      'mt-cov--q4',     'fa-circle-quarter-stroke' ],
				];
				$_enabled_fracs_mt = function_exists( 'pz_get_enabled_fractions' ) ? pz_get_enabled_fractions() : array_keys( $_all_coverages_mt );
				$coverages         = array_intersect_key( $_all_coverages_mt, array_flip( $_enabled_fracs_mt ) );
				foreach ( $coverages as $fraction => [ $label, $cls, $ico ] ) :
				?>
				<button type="button" class="mt-cov-modal__btn <?php echo esc_attr( $cls ); ?>"
				        data-fraction="<?php echo esc_attr( $fraction ); ?>">
					<i class="fa <?php echo esc_attr( $ico ); ?>"></i>
					<span><?php echo esc_html( $label ); ?></span>
				</button>
				<?php endforeach; ?>
			</div>
			<div class="mt-cov-modal__footer">
				<button type="button" class="mt-cov-modal__done"><?php esc_html_e( 'Done', 'pizzatier' ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $mt_footer_note ) : ?>
	<!-- ── Builder footer note ───────────────────────────────── -->
	<div class="mt-footer-note">
		<?php echo $mt_footer_note; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized via wp_kses_post on read ?>
	</div>
	<?php endif; ?>

	<!-- ── Summary tray (bottom of page) ─────────────────────── -->
	<div class="mt-tray" id="<?php echo esc_attr( $instance_id ); ?>-tray">
		<div class="mt-tray__inner">
			<div class="mt-tray__pizza-thumb" id="<?php echo esc_attr( $instance_id ); ?>-tray-pizza"></div>
			<div class="mt-tray__chips" id="<?php echo esc_attr( $instance_id ); ?>-tray-chips">
				<span class="mt-tray__empty"><?php esc_html_e( 'Start selecting below ↓', 'pizzatier' ); ?></span>
			</div>
			<button type="button" class="mt-tray__reset"
			        onclick="ClearPizza(); window['<?php echo esc_js( $mt_var ); ?>']&&window['<?php echo esc_js( $mt_var ); ?>'].resetAll();"
			        title="<?php esc_attr_e( 'Reset pizza', 'pizzatier' ); ?>">
				<i class="fa fa-rotate-left"></i>
			</button>
		</div>
	</div>

	<?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>

</div><!-- /#<?php echo esc_html( $instance_id ); ?> .mt-root -->

<?php
// Initialize this instance via wp_add_inline_script (WP.org compliant — no inline <script>).
$mt_init_js = "if(typeof MT!=='undefined'&&typeof MT.createInstance==='function'){"
	. "var " . esc_js( $mt_var ) . "=MT.createInstance(" . wp_json_encode( $instance_id ) . ");"
	. "}";
wp_add_inline_script( 'pizzatier-template-metro', $mt_init_js );
