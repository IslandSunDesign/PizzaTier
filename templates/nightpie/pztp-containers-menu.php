<?php
/**
 * NightPie Template — [pizza_builder] output.
 *
 * This file is included (not called as a function) by BuilderShortcode.
 * Variables available from the shortcode:
 *   $instance_id  — unique ID string (e.g. "pizza-1", "pizzabuilder-1")
 *   $atts         — shortcode attribute array
 *
 * Multi-instance support: every JS reference uses $np_var (the per-instance
 * namespace) instead of the global "NP", so multiple builders on one page
 * each maintain independent state.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

// Ensure we have all expected variables (guard for direct include)
if ( ! isset( $instance_id ) )    { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )           { $atts           = []; }
if ( ! isset( $template_slug ) )  { $template_slug  = 'nightpie'; }
if ( ! isset( $function_prefix ) ) { $function_prefix = 'pzt_nightpie'; }

// Per-instance JS namespace: NP_pizza1, NP_pizzabuilder2, etc.
$np_var = 'NP_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

// Resolve max toppings: shortcode attr → plugin option → default 99
$max_toppings = isset( $atts['max_toppings'] ) && (int) $atts['max_toppings'] > 0
	? (int) $atts['max_toppings']
	: intval( get_option( 'pizzatier_setting_topping_maxtoppings', 0 ) );
if ( $max_toppings < 1 ) { $max_toppings = 99; }

// Apply developer filter
$max_toppings = (int) apply_filters( 'pizzatier_max_toppings', $max_toppings, $instance_id );

// Resolve pizza shape: shortcode attr → plugin option → 'round'
$valid_shapes  = [ 'round', 'square', 'rectangle', 'custom' ];
$pizza_shape   = sanitize_key( $atts['pizza_shape'] ?? get_option( 'pizzatier_setting_pizza_shape', 'round' ) );
if ( ! in_array( $pizza_shape, $valid_shapes, true ) ) { $pizza_shape = 'round'; }
$pizza_aspect  = sanitize_text_field( $atts['pizza_aspect']  ?? get_option( 'pizzatier_setting_pizza_aspect',  '1 / 1' ) );
$pizza_radius  = sanitize_text_field( $atts['pizza_radius']  ?? get_option( 'pizzatier_setting_pizza_radius',  '8px'   ) );

// Resolve layer animation: shortcode attr → plugin option → 'fade'
$valid_anims   = [ 'fade', 'scale-in', 'slide-up', 'flip-in', 'drop-in', 'instant' ];
$layer_anim    = sanitize_key( $atts['layer_anim'] ?? get_option( 'pizzatier_setting_layer_anim', 'fade' ) );
if ( ! in_array( $layer_anim, $valid_anims, true ) ) { $layer_anim = 'fade'; }
$layer_anim_speed = max( 80, min( 800, (int) get_option( 'pizzatier_setting_layer_anim_speed', 320 ) ) );


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

// Resolve hidden tabs
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
 * Build an exclusive-select card (crust / sauce / cheese / drizzle / cut).
 * Uses $np_var for JS calls instead of global NP.
 */
if ( ! function_exists( 'pzt_nightpie_exclusive_card' ) ) :
function pzt_nightpie_exclusive_card( $post, string $layer_type, string $np_var, int $zindex = 200 ): string {
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
	$js_add    = "window['{$np_var}']&&window['{$np_var}'].swapBase('{$layer_type}','{$slug}','{$js_title}','{$js_layer}',this)";
	$js_remove = "window['{$np_var}']&&window['{$np_var}'].removeBase('{$layer_type}','{$slug}',this)";

	ob_start();
	do_action( 'pizzatier_before_layer_card', $post, $layer_type );
	?>
	<div class="np-card np-card--exclusive"
	     data-layer="<?php echo esc_attr( $layer_type ); ?>"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>">
		<div class="np-card__thumb-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="np-card__thumb" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="np-card__thumb np-card__thumb--placeholder"></div>
			<?php endif; ?>
			<div class="np-card__check"><i class="fa fa-check"></i></div>
		</div>
		<div class="np-card__body">
			<span class="np-card__name"><?php echo esc_html( $title ); ?></span>
		</div>
		<div class="np-card__actions">
			<button type="button" class="np-btn np-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
				<i class="fa fa-plus"></i> <?php esc_html_e( 'Add', 'pizzatier' ); ?>
			</button>
			<button type="button" class="np-btn np-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
				<i class="fa fa-times"></i> <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
			</button>
		</div>
	</div>
	<?php
	do_action( 'pizzatier_after_layer_card', $post, $layer_type );
	return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, $layer_type );
}
endif;

/**
 * Build a topping card (multi-select with coverage picker).
 */
if ( ! function_exists( 'pzt_nightpie_topping_card' ) ) :
function pzt_nightpie_topping_card( $post, string $np_var, int $zindex ): string {
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

	$js_add    = "window['{$np_var}']&&window['{$np_var}'].addTopping({$zindex},'{$js_slug}','{$js_layer}','{$js_title}','{$layer_id}','{$layer_id}',this)";
	$js_remove = "window['{$np_var}']&&window['{$np_var}'].removeTopping('pizzatier-topping-{$js_slug}','{$js_slug}',this)";

	ob_start();
	do_action( 'pizzatier_before_layer_card', $post, 'toppings' );
	?>
	<div class="np-card np-card--topping"
	     data-layer="toppings"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>"
	     data-zindex="<?php echo esc_attr( (string) $zindex ); ?>">
		<div class="np-card__thumb-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="np-card__thumb" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="np-card__thumb np-card__thumb--placeholder"></div>
			<?php endif; ?>
			<div class="np-card__check"><i class="fa fa-check"></i></div>
		</div>
		<div class="np-card__body">
			<span class="np-card__name"><?php echo esc_html( $title ); ?></span>
			<div class="np-coverage" style="display:none;">
				<span class="np-coverage__label"><?php esc_html_e( 'Coverage:', 'pizzatier' ); ?></span>
				<div class="np-coverage__btns">
					<?php
					$coverages = [ 'whole' => 'Whole', 'half-left' => 'Left', 'half-right' => 'Right',
					               'quarter-top-left' => 'Q1', 'quarter-top-right' => 'Q2',
					               'quarter-bottom-left' => 'Q3', 'quarter-bottom-right' => 'Q4' ];
					foreach ( $coverages as $fraction => $label ) :
						$js_cov = "window['{$np_var}']&&window['{$np_var}'].setCoverage('" . esc_js( $slug ) . "','" . esc_js( $fraction ) . "',this)";
						$ico    = 'np-cov-ico--' . str_replace( [ 'half-', 'quarter-' ], [ '', '' ], $fraction );
					?>
					<button type="button" class="np-cov-btn" data-fraction="<?php echo esc_attr( $fraction ); ?>"
					        onclick="<?php echo esc_attr( $js_cov ); ?>">
						<span class="np-cov-ico <?php echo esc_attr( $ico ); ?>"></span>
						<?php echo esc_html( $label ); ?>
					</button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="np-card__actions">
			<button type="button" class="np-btn np-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
				<i class="fa fa-plus"></i> <?php esc_html_e( 'Add', 'pizzatier' ); ?>
			</button>
			<button type="button" class="np-btn np-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
				<i class="fa fa-times"></i> <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
			</button>
		</div>
	</div>
	<?php
	do_action( 'pizzatier_after_layer_card', $post, 'toppings' );
	return apply_filters( 'pizzatier_card_html', ob_get_clean(), $post, 'toppings' );
}
endif;

// Render card HTML for each tab
$crusts_html = '';
foreach ( $crusts as $post ) { $crusts_html .= pzt_nightpie_exclusive_card( $post, 'crust', $np_var, 100 ); }
if ( ! $crusts_html ) { $crusts_html = '<p class="np-empty"><i class="fa fa-circle-exclamation"></i> ' . esc_html__( 'No crusts found.', 'pizzatier' ) . '</p>'; }

$sauces_html = '';
foreach ( $sauces as $post ) { $sauces_html .= pzt_nightpie_exclusive_card( $post, 'sauce', $np_var, 150 ); }
if ( ! $sauces_html ) { $sauces_html = '<p class="np-empty">' . esc_html__( 'No sauces found.', 'pizzatier' ) . '</p>'; }

$cheeses_html = '';
foreach ( $cheeses as $post ) { $cheeses_html .= pzt_nightpie_exclusive_card( $post, 'cheese', $np_var, 200 ); }
if ( ! $cheeses_html ) { $cheeses_html = '<p class="np-empty">' . esc_html__( 'No cheeses found.', 'pizzatier' ) . '</p>'; }

$drizzles_html = '';
foreach ( $drizzles as $post ) { $drizzles_html .= pzt_nightpie_exclusive_card( $post, 'drizzle', $np_var, 900 ); }
if ( ! $drizzles_html ) { $drizzles_html = '<p class="np-empty">' . esc_html__( 'No drizzles found.', 'pizzatier' ) . '</p>'; }

$toppings_html = '';
$t_z = 400;
foreach ( $toppings as $post ) { $toppings_html .= pzt_nightpie_topping_card( $post, $np_var, $t_z ); $t_z += 10; }
if ( ! $toppings_html ) { $toppings_html = '<p class="np-empty">' . esc_html__( 'No toppings found.', 'pizzatier' ) . '</p>'; }

$cuts_html = '';
foreach ( $cuts as $post ) { $cuts_html .= pzt_nightpie_exclusive_card( $post, 'cut', $np_var, 950 ); }
if ( ! $cuts_html ) { $cuts_html = '<p class="np-empty">' . esc_html__( 'No cut styles found.', 'pizzatier' ) . '</p>'; }

// Use PizzaBuilder for the initial pizza display
$builder = new \PizzaTier\Builder\PizzaBuilder();
$initial_pizza = $builder->build_dynamic(
	$atts['default_crust']    ?? '',
	$atts['default_sauce']    ?? '',
	$atts['default_cheese']   ?? '',
	$atts['default_toppings'] ?? '',
	$atts['default_drizzle']  ?? '',
	$atts['default_cut']      ?? ''
);

// Pass $np_var, $instance_id to custom.js via data attribute on root
?>
<!-- ═══════════════════════════════════════════════════
     NIGHTPIE TEMPLATE — PizzaTier
     Instance: <?php echo esc_html( $instance_id ); ?>
═══════════════════════════════════════════════════ -->
<div id="<?php echo esc_attr( $instance_id ); ?>"
     class="np-root"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-np-var="<?php echo esc_attr( $np_var ); ?>"
     data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
     data-pizza-shape="<?php echo esc_attr( $pizza_shape ); ?>"
     data-pizza-aspect="<?php echo esc_attr( $pizza_aspect ); ?>"
     data-pizza-radius="<?php echo esc_attr( $pizza_radius ); ?>"
     data-layer-anim="<?php echo esc_attr( $layer_anim ); ?>"
     data-layer-anim-speed="<?php echo esc_attr( (string) $layer_anim_speed ); ?>">

	<!-- Mobile mini-bar -->
	<div class="np-mobile-preview-bar">
		<div class="np-mobile-preview-bar__inner">
			<span class="np-mobile-preview-bar__label"><i class="fa fa-pizza-slice"></i> <?php esc_html_e( 'Live Preview', 'pizzatier' ); ?></span>
			<div class="np-mobile-preview-bar__pizza" id="<?php echo esc_attr( $instance_id ); ?>-pizza-mobile-slot"></div>
			<button class="np-mobile-preview-bar__toggle" id="<?php echo esc_attr( $instance_id ); ?>-mobile-toggle" aria-label="<?php esc_attr_e( 'Toggle pizza preview', 'pizzatier' ); ?>">
				<i class="fa fa-chevron-down"></i>
			</button>
		</div>
		<div class="np-mobile-preview-bar__expanded" id="<?php echo esc_attr( $instance_id ); ?>-mobile-expanded" aria-hidden="true"></div>
	</div>

	<!-- Main layout -->
	<div class="np-layout">
		<div class="np-layout__row">

			<!-- LEFT: sticky pizza visualizer -->
			<div class="np-pizza-col" id="<?php echo esc_attr( $instance_id ); ?>-pizza-col">
				<div class="np-pizza-sticky">
					<div class="np-pizza-sticky__header">
						<i class="fa fa-pizza-slice"></i>
						<span><?php esc_html_e( 'Your Pizza', 'pizzatier' ); ?></span>
					</div>
					<div class="np-pizza-sticky__canvas" id="<?php echo esc_attr( $instance_id ); ?>-canvas">
						<?php echo $initial_pizza; // phpcs:ignore WordPress.Security.EscapeOutput -- built by PizzaBuilder with proper escaping ?>
					</div>
					<div class="np-pizza-sticky__footer">
						<button type="button" class="np-btn np-btn--ghost np-btn--sm"
						        onclick="ClearPizza(); window['<?php echo esc_js( $np_var ); ?>']&&window['<?php echo esc_js( $np_var ); ?>'].resetAll();">
							<i class="fa fa-rotate-left"></i> <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
						</button>
						<span class="np-topping-counter">
							<i class="fa fa-layer-group"></i>
							<span id="<?php echo esc_attr( $instance_id ); ?>-count">0</span> / <?php echo esc_html( (string) $max_toppings ); ?> <?php esc_html_e( 'toppings', 'pizzatier' ); ?>
						</span>
					</div>

					<!-- Action bar: PizzaTierPro hooks here for WC cart button -->
					<!-- Action bar moved to root level below -->
				</div>
			</div>

			<!-- RIGHT: tabbed builder -->
			<div class="np-tabs-col">
				<div class="np-builder">

					<nav class="np-tabnav" id="<?php echo esc_attr( $instance_id ); ?>-tabnav" role="tablist">
						<?php
						$tab_meta = [
							'size'      => [ 'fa-ruler-combined', __( 'Size',     'pizzatier' ) ],
							'crust'     => [ 'fa-circle',      __( 'Crust',     'pizzatier' ) ],
							'sauce'     => [ 'fa-droplet',     __( 'Sauce',     'pizzatier' ) ],
							'cheese'    => [ 'fa-cheese',      __( 'Cheese',    'pizzatier' ) ],
							'toppings'  => [ 'fa-seedling',    __( 'Toppings',  'pizzatier' ) ],
							'drizzle'   => [ 'fa-wine-glass',  __( 'Drizzle',   'pizzatier' ) ],
							'slicing'   => [ 'fa-pizza-slice', __( 'Slicing',   'pizzatier' ) ],
							'yourpizza' => [ 'fa-receipt',     __( 'Your Pizza','pizzatier' ) ],
						];
						$first_tab = true;
						foreach ( $visible_tabs as $tab ) :
							if ( ! isset( $tab_meta[ $tab ] ) ) { continue; }
							[ $icon, $label ] = $tab_meta[ $tab ];
							$active = $first_tab ? 'active' : '';
							$selected = $first_tab ? 'true' : 'false';
							$first_tab = false;
						?>
						<button class="np-tab <?php echo esc_attr( $active ); ?>"
						        data-tab="<?php echo esc_attr( $tab ); ?>"
						        data-instance="<?php echo esc_attr( $instance_id ); ?>"
						        role="tab" aria-selected="<?php echo esc_attr( $selected ); ?>"
						        aria-controls="<?php echo esc_attr( $instance_id . '-panel-' . $tab ); ?>">
							<span class="np-tab__icon"><i class="fa <?php echo esc_attr( $icon ); ?>"></i></span>
							<span class="np-tab__label"><?php echo esc_html( $label ); ?></span>
						</button>
						<?php endforeach; ?>
					</nav>

					<!-- Tab panels -->
					<div class="np-panels">


						<?php if ( $_has_pro && in_array( 'size', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_size', $instance_id ); ?>
						<section class="np-panel active" id="<?php echo esc_attr( $instance_id ); ?>-panel-size" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-ruler-combined"></i> <?php esc_html_e( 'Choose Your Size', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Select the size of your pizza.', 'pizzatier' ); ?></p>
							</div>
							<?php pzt_render_inline_size_selector( $_pro_sizes, $instance_id, 'np' ); ?>
							<div class="np-panel__nav">
								<span></span>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('crust')"><?php esc_html_e( 'Crust', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_size', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'crust', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_crust', $instance_id ); ?>
						<section class="np-panel<?php echo $_has_pro ? '' : ' active'; ?>" id="<?php echo esc_attr( $instance_id ); ?>-panel-crust" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-circle"></i> <?php esc_html_e( 'Choose Your Crust', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Select one crust — it forms the base of your pizza.', 'pizzatier' ); ?></p>
							</div>
							<div class="np-cards-grid np-cards-grid--exclusive"><?php echo $crusts_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<span></span>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('sauce')"><?php esc_html_e( 'Sauce', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_crust', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'sauce', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_sauce', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-sauce" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-droplet"></i> <?php esc_html_e( 'Choose Your Sauce', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Select one sauce.', 'pizzatier' ); ?></p>
							</div>
							<div class="np-cards-grid np-cards-grid--exclusive"><?php echo $sauces_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('crust')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Crust', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('cheese')"><?php esc_html_e( 'Cheese', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_sauce', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'cheese', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_cheese', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-cheese" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-cheese"></i> <?php esc_html_e( 'Choose Your Cheese', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Select one cheese.', 'pizzatier' ); ?></p>
							</div>
							<div class="np-cards-grid np-cards-grid--exclusive"><?php echo $cheeses_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('sauce')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Sauce', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('toppings')"><?php esc_html_e( 'Toppings', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_cheese', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'toppings', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_toppings', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-toppings" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-seedling"></i> <?php esc_html_e( 'Choose Your Toppings', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint">
									<?php printf( /* translators: %s = maximum number of toppings. */ esc_html__( 'Add up to %s toppings.', 'pizzatier' ), '<strong>' . esc_html( (string) $max_toppings ) . '</strong>' ); ?>
								</p>
							</div>
							<div class="np-cards-grid np-cards-grid--toppings"><?php echo $toppings_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('cheese')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Cheese', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('drizzle')"><?php esc_html_e( 'Drizzle', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_toppings', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'drizzle', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_drizzle', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-drizzle" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-wine-glass"></i> <?php esc_html_e( 'Choose a Drizzle', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Optional finishing drizzle.', 'pizzatier' ); ?></p>
							</div>
							<div class="np-cards-grid np-cards-grid--exclusive"><?php echo $drizzles_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('toppings')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Toppings', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--next" onclick="<?php echo esc_js( $np_var ); ?>.goTab('slicing')"><?php esc_html_e( 'Slicing', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_drizzle', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'slicing', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_slicing', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-slicing" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-pizza-slice"></i> <?php esc_html_e( 'How Should We Slice It?', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( 'Choose a cut style.', 'pizzatier' ); ?></p>
							</div>
							<div class="np-cards-grid np-cards-grid--exclusive"><?php echo $cuts_html; // phpcs:ignore ?></div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('drizzle')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Drizzle', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--next np-btn--cta" onclick="<?php echo esc_js( $np_var ); ?>.goTab('yourpizza')"><i class="fa fa-receipt"></i> <?php esc_html_e( 'See Your Pizza', 'pizzatier' ); ?></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_slicing', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_yourpizza', $instance_id ); ?>
						<section class="np-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-yourpizza" role="tabpanel">
							<div class="np-panel__header">
								<h2 class="np-panel__title"><i class="fa fa-receipt"></i> <?php esc_html_e( 'Your Pizza', 'pizzatier' ); ?></h2>
								<p class="np-panel__hint"><?php esc_html_e( "Here's everything you've built!", 'pizzatier' ); ?></p>
							</div>
							<div class="np-yourpizza" id="<?php echo esc_attr( $instance_id ); ?>-summary">
								<?php
								$summary_rows = [
									'size'     => [ 'fa-ruler-combined', __( 'Size',    'pizzatier' ) ],
									'crust'    => [ 'fa-circle',      __( 'Crust',    'pizzatier' ) ],
									'sauce'    => [ 'fa-droplet',     __( 'Sauce',    'pizzatier' ) ],
									'cheese'   => [ 'fa-cheese',      __( 'Cheese',   'pizzatier' ) ],
									'toppings' => [ 'fa-seedling',    __( 'Toppings', 'pizzatier' ) ],
									'drizzle'  => [ 'fa-wine-glass',  __( 'Drizzle',  'pizzatier' ) ],
									'slicing'  => [ 'fa-pizza-slice', __( 'Slicing',  'pizzatier' ) ],
								];
								foreach ( $summary_rows as $key => [ $ico, $label ] ) :
								?>
								<div class="np-yourpizza__row" id="<?php echo esc_attr( $instance_id ); ?>-yp-<?php echo esc_attr( $key ); ?>">
									<div class="np-yourpizza__icon"><i class="fa <?php echo esc_attr( $ico ); ?>"></i></div>
									<div class="np-yourpizza__layer-name"><?php echo esc_html( $label ); ?></div>
									<div class="np-yourpizza__selection np-yourpizza__selection--empty" id="<?php echo esc_attr( $instance_id . '-yp-' . $key . '-val' ); ?>">
										<span class="np-yp-none">— <?php esc_html_e( 'none selected', 'pizzatier' ); ?> —</span>
									</div>
									<button class="np-yourpizza__edit" onclick="<?php echo esc_js( $np_var ); ?>.goTab('<?php echo esc_js( $key ); ?>')"><i class="fa fa-pen"></i></button>
								</div>
								<?php endforeach; ?>
							</div>
							<div class="np-panel__nav">
								<button class="np-btn np-btn--prev" onclick="<?php echo esc_js( $np_var ); ?>.goTab('slicing')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Back', 'pizzatier' ); ?></button>
								<button class="np-btn np-btn--ghost" onclick="ClearPizza(); window['<?php echo esc_js( $np_var ); ?>']&&window['<?php echo esc_js( $np_var ); ?>'].resetAll();"><i class="fa fa-rotate-left"></i> <?php esc_html_e( 'Start Over', 'pizzatier' ); ?></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_yourpizza', $instance_id ); ?>
						<?php endif; ?>

					</div><!-- /.np-panels -->

					<!-- Step controls — placed BELOW the options/choices -->
					<div class="np-builder-footer">
						<!-- Progress dots -->
						<div class="np-progress" aria-hidden="true">
							<?php foreach ( $visible_tabs as $s ) : ?>
							<span class="np-progress__dot" data-step="<?php echo esc_attr( $s ); ?>"></span>
							<?php endforeach; ?>
						</div>

						<!-- Section navigation (prev/next) -->
						<div class="np-section-nav" id="<?php echo esc_attr( $instance_id ); ?>-section-nav">
							<button type="button" class="np-section-nav__btn np-section-nav__btn--prev"
							        id="<?php echo esc_attr( $instance_id ); ?>-nav-prev"
							        onclick="<?php echo esc_js( $np_var ); ?>.navPrev()"
							        disabled aria-label="<?php esc_attr_e( 'Previous section', 'pizzatier' ); ?>">
								<i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Prev', 'pizzatier' ); ?>
							</button>
							<button type="button" class="np-section-nav__btn np-section-nav__btn--next"
							        id="<?php echo esc_attr( $instance_id ); ?>-nav-next"
							        onclick="<?php echo esc_js( $np_var ); ?>.navNext()"
							        aria-label="<?php esc_attr_e( 'Next section', 'pizzatier' ); ?>">
								<?php esc_html_e( 'Next', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i>
							</button>
						</div>
					</div><!-- /.np-builder-footer -->
				</div><!-- /.np-builder -->
			</div><!-- /.np-tabs-col -->

		</div><!-- /.np-layout__row -->
	</div><!-- /.np-layout -->

	<div id="<?php echo esc_attr( $instance_id ); ?>-fly-container" aria-hidden="true"></div>

	<?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>

</div><!-- /#<?php echo esc_html( $instance_id ); ?> .np-root -->

<?php
// Initialize this instance via wp_add_inline_script (WP.org compliant — no inline <script>).
$np_init_js = "if(typeof NP!=='undefined'&&typeof NP.createInstance==='function'){"
	. "var " . esc_js( $np_var ) . "=NP.createInstance(" . wp_json_encode( $instance_id ) . ");"
	. "}";
wp_add_inline_script( 'pizzatier-template-nightpie', $np_init_js );
