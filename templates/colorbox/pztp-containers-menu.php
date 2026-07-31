<?php
/**
 * Colorbox Template — [pizza_builder] output.
 *
 * This file is included (not called as a function) by BuilderShortcode.
 * Variables available from the shortcode:
 *   $instance_id  — unique ID string (e.g. "pizza-1", "pizzabuilder-1")
 *   $atts         — shortcode attribute array
 *
 * Multi-instance support: every JS reference uses $cb_var (the per-instance
 * namespace) instead of the global "CB", so multiple builders on one page
 * each maintain independent state.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

// Ensure we have all expected variables (guard for direct include)
if ( ! isset( $instance_id ) )    { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )           { $atts           = []; }
if ( ! isset( $template_slug ) )  { $template_slug  = 'colorbox'; }
if ( ! isset( $function_prefix ) ) { $function_prefix = 'pzt_colorbox'; }

// Per-instance JS namespace: CB_pizza1, CB_pizzabuilder2, etc.
$cb_var = 'CB_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

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
$layer_anim_speed = isset( $atts['layer_anim_speed'] ) && (int) $atts['layer_anim_speed'] > 0
	? max( 80, min( 800, (int) $atts['layer_anim_speed'] ) )
	: max( 80, min( 800, (int) get_option( 'pizzatier_setting_layer_anim_speed', 320 ) ) );

// Resolve hidden tabs
$hide_tabs_raw = $atts['hide_tabs'] ?? '';
$show_tabs_raw = $atts['show_tabs'] ?? '';
$all_tabs      = [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ];
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
 * Uses $cb_var for JS calls instead of global CB.
 */
if ( ! function_exists( 'pzt_colorbox_exclusive_card' ) ) :
function pzt_colorbox_exclusive_card( $post, string $layer_type, string $cb_var, int $zindex = 200 ): string {
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
	$js_add    = "window['{$cb_var}']&&window['{$cb_var}'].swapBase('{$layer_type}','{$slug}','{$js_title}','{$js_layer}',this)";
	$js_remove = "window['{$cb_var}']&&window['{$cb_var}'].removeBase('{$layer_type}','{$slug}',this)";

	$card_html = '';
	ob_start();
	try {
	do_action( 'pizzatier_before_layer_card', $post, $layer_type );
	?>
	<div class="cb-card cb-card--exclusive"
	     data-layer="<?php echo esc_attr( $layer_type ); ?>"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>">
		<div class="cb-card__thumb-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="cb-card__thumb" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="cb-card__thumb cb-card__thumb--placeholder"></div>
			<?php endif; ?>
			<div class="cb-card__check"><i class="fa fa-check"></i></div>
		</div>
		<div class="cb-card__body">
			<span class="cb-card__name"><?php echo esc_html( $title ); ?></span>
		</div>
		<div class="cb-card__actions">
			<button type="button" class="cb-btn cb-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
				<i class="fa fa-plus"></i> <?php esc_html_e( 'Add', 'pizzatier' ); ?>
			</button>
			<button type="button" class="cb-btn cb-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
				<i class="fa fa-times"></i> <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
			</button>
		</div>
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
 * Coverage helpers — shared by the topping card chip and the coverage modal.
 * The CSS swatch classes are: --whole, --left, --right, --q1..--q4.
 */
if ( ! function_exists( 'pzt_colorbox_coverage_icon' ) ) :
function pzt_colorbox_coverage_icon( string $fraction ): string {
	$map = [
		'whole'                => 'whole',
		'half-left'            => 'left',
		'half-right'           => 'right',
		'quarter-top-left'     => 'q1',
		'quarter-top-right'    => 'q2',
		'quarter-bottom-left'  => 'q3',
		'quarter-bottom-right' => 'q4',
	];
	return $map[ $fraction ] ?? 'whole';
}
endif;

if ( ! function_exists( 'pzt_colorbox_coverage_label' ) ) :
function pzt_colorbox_coverage_label( string $fraction ): string {
	$map = [
		'whole'                => __( 'Whole',        'pizzatier' ),
		'half-left'            => __( 'Left Half',    'pizzatier' ),
		'half-right'           => __( 'Right Half',   'pizzatier' ),
		'quarter-top-left'     => __( 'Top Left',     'pizzatier' ),
		'quarter-top-right'    => __( 'Top Right',    'pizzatier' ),
		'quarter-bottom-left'  => __( 'Bottom Left',  'pizzatier' ),
		'quarter-bottom-right' => __( 'Bottom Right', 'pizzatier' ),
	];
	return $map[ $fraction ] ?? ucfirst( str_replace( '-', ' ', $fraction ) );
}
endif;

if ( ! function_exists( 'pzt_colorbox_enabled_coverages' ) ) :
function pzt_colorbox_enabled_coverages(): array {
	$all = [ 'whole', 'half-left', 'half-right',
	         'quarter-top-left', 'quarter-top-right',
	         'quarter-bottom-left', 'quarter-bottom-right' ];
	$enabled = function_exists( 'pz_get_enabled_fractions' ) ? pz_get_enabled_fractions() : $all;
	$out = array_values( array_intersect( $all, (array) $enabled ) );
	// Guarantee Whole is always present and first (it is the default).
	$out = array_values( array_unique( array_merge( [ 'whole' ], $out ) ) );
	return $out;
}
endif;

/**
 * Build a topping card (multi-select with coverage picker).
 */
if ( ! function_exists( 'pzt_colorbox_topping_card' ) ) :
function pzt_colorbox_topping_card( $post, string $cb_var, int $zindex ): string {
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

	$js_add    = "window['{$cb_var}']&&window['{$cb_var}'].addTopping({$zindex},'{$js_slug}','{$js_layer}','{$js_title}','{$layer_id}','{$layer_id}',this)";
	$js_remove = "window['{$cb_var}']&&window['{$cb_var}'].removeTopping('pizzatier-topping-{$js_slug}','{$js_slug}',this)";

	$card_html = '';
	ob_start();
	try {
	do_action( 'pizzatier_before_layer_card', $post, 'toppings' );
	?>
	<div class="cb-card cb-card--topping"
	     data-layer="toppings"
	     data-slug="<?php echo esc_attr( $slug ); ?>"
	     data-title="<?php echo esc_attr( $title ); ?>"
	     data-thumb="<?php echo esc_attr( (string) $thumb_url ); ?>"
	     data-layer-img="<?php echo esc_attr( (string) $layer_url ); ?>"
	     data-zindex="<?php echo esc_attr( (string) $zindex ); ?>">
		<div class="cb-card__thumb-wrap">
			<?php if ( $thumb_url ) : ?>
				<img class="cb-card__thumb" src="<?php echo esc_url( (string) $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			<?php else : ?>
				<div class="cb-card__thumb cb-card__thumb--placeholder"></div>
			<?php endif; ?>
			<div class="cb-card__check"><i class="fa fa-check"></i></div>
		</div>
		<div class="cb-card__body">
			<span class="cb-card__name"><?php echo esc_html( $title ); ?></span>
			<?php
			// Coverage selection is now modal: the card shows only the chosen
			// coverage as a chip; tapping it opens the per-instance picker.
			// Default = Whole.
			$_icon = pzt_colorbox_coverage_icon( 'whole' );
			$_lbl  = pzt_colorbox_coverage_label( 'whole' );
			$js_open = "window['{$cb_var}']&&window['{$cb_var}'].openCoverage('" . esc_js( $slug ) . "')";
			?>
			<div class="cb-coverage" style="display:none;">
				<span class="cb-coverage__label"><?php esc_html_e( 'Coverage:', 'pizzatier' ); ?></span>
				<button type="button" class="cb-coverage__current" data-slug="<?php echo esc_attr( $slug ); ?>"
				        data-fraction="whole" onclick="<?php echo esc_attr( $js_open ); ?>"
				        aria-haspopup="dialog">
					<span class="cb-cov-ico cb-cov-ico--<?php echo esc_attr( $_icon ); ?>"></span>
					<span class="cb-coverage__current-label"><?php echo esc_html( $_lbl ); ?></span>
					<span class="cb-coverage__caret" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<div class="cb-card__actions">
			<button type="button" class="cb-btn cb-btn--add" onclick="<?php echo esc_attr( $js_add ); ?>">
				<i class="fa fa-plus"></i> <?php esc_html_e( 'Add', 'pizzatier' ); ?>
			</button>
			<button type="button" class="cb-btn cb-btn--remove" style="display:none;" onclick="<?php echo esc_attr( $js_remove ); ?>">
				<i class="fa fa-times"></i> <?php esc_html_e( 'Remove', 'pizzatier' ); ?>
			</button>
		</div>
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

// Render card HTML for each tab
$crusts_html = '';
foreach ( $crusts as $pzt_layer ) { $crusts_html .= pzt_colorbox_exclusive_card( $pzt_layer, 'crust', $cb_var, 100 ); }
if ( ! $crusts_html ) { $crusts_html = '<p class="cb-empty"><i class="fa fa-circle-exclamation"></i> ' . esc_html__( 'No crusts found.', 'pizzatier' ) . '</p>'; }

$sauces_html = '';
foreach ( $sauces as $pzt_layer ) { $sauces_html .= pzt_colorbox_exclusive_card( $pzt_layer, 'sauce', $cb_var, 150 ); }
if ( ! $sauces_html ) { $sauces_html = '<p class="cb-empty">' . esc_html__( 'No sauces found.', 'pizzatier' ) . '</p>'; }

$cheeses_html = '';
foreach ( $cheeses as $pzt_layer ) { $cheeses_html .= pzt_colorbox_exclusive_card( $pzt_layer, 'cheese', $cb_var, 200 ); }
if ( ! $cheeses_html ) { $cheeses_html = '<p class="cb-empty">' . esc_html__( 'No cheeses found.', 'pizzatier' ) . '</p>'; }

$drizzles_html = '';
foreach ( $drizzles as $pzt_layer ) { $drizzles_html .= pzt_colorbox_exclusive_card( $pzt_layer, 'drizzle', $cb_var, 900 ); }
if ( ! $drizzles_html ) { $drizzles_html = '<p class="cb-empty">' . esc_html__( 'No drizzles found.', 'pizzatier' ) . '</p>'; }

$toppings_html = '';
$t_z = 400;
foreach ( $toppings as $pzt_layer ) { $toppings_html .= pzt_colorbox_topping_card( $pzt_layer, $cb_var, $t_z ); $t_z += 10; }
if ( ! $toppings_html ) { $toppings_html = '<p class="cb-empty">' . esc_html__( 'No toppings found.', 'pizzatier' ) . '</p>'; }

$cuts_html = '';
foreach ( $cuts as $pzt_layer ) { $cuts_html .= pzt_colorbox_exclusive_card( $pzt_layer, 'cut', $cb_var, 950 ); }
if ( ! $cuts_html ) { $cuts_html = '<p class="cb-empty">' . esc_html__( 'No cut styles found.', 'pizzatier' ) . '</p>'; }

// Use PizzaBuilder for the initial pizza display
$builder = new \PizzaTier\Builder\PizzaBuilder();
$initial_pizza = $builder->build_dynamic(
	$atts['default_crust']  ?? '',
	$atts['default_sauce']  ?? '',
	$atts['default_cheese'] ?? ''
);

// Resolve additional layout settings
$layout_mode    = sanitize_key( (string) get_option( 'pizzatier_setting_layout_mode', 'stacked' ) );
$sticky_header  = ( get_option( 'pizzatier_setting_layout_sticky_header', 'no' ) === 'yes' ) ? 'yes' : 'no';
$show_spec_instr = ( get_option( 'pizzatier_setting_cx_special_instructions', 'no' ) === 'yes' );
$spec_placeholder = sanitize_text_field( (string) get_option( 'pizzatier_setting_cx_special_instr_placeholder', 'Any special requests? (optional)' ) );
$spec_max        = max( 1, (int) get_option( 'pizzatier_setting_cx_special_instr_max', 300 ) );

// Pass $cb_var, $instance_id to custom.js via data attribute on root
?>
<!-- ═══════════════════════════════════════════════════
     COLORBOX TEMPLATE — PizzaTier
     Instance: <?php echo esc_html( $instance_id ); ?>
═══════════════════════════════════════════════════ -->
<div id="<?php echo esc_attr( $instance_id ); ?>"
     class="cb-root"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-cb-var="<?php echo esc_attr( $cb_var ); ?>"
     data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
     data-pizza-shape="<?php echo esc_attr( $pizza_shape ); ?>"
     data-pizza-aspect="<?php echo esc_attr( $pizza_aspect ); ?>"
     data-pizza-radius="<?php echo esc_attr( $pizza_radius ); ?>"
     data-layer-anim="<?php echo esc_attr( $layer_anim ); ?>"
     data-layer-anim-speed="<?php echo esc_attr( (string) $layer_anim_speed ); ?>"
     data-layout-mode="<?php echo esc_attr( $layout_mode ); ?>"
     data-sticky-header="<?php echo esc_attr( $sticky_header ); ?>">

	<!-- Mobile mini-bar -->
	<div class="cb-mobile-preview-bar">
		<div class="cb-mobile-preview-bar__inner">
			<span class="cb-mobile-preview-bar__label"><i class="fa fa-pizza-slice"></i> <?php esc_html_e( 'Live Preview', 'pizzatier' ); ?></span>
			<div class="cb-mobile-preview-bar__pizza" id="<?php echo esc_attr( $instance_id ); ?>-pizza-mobile-slot"></div>
			<button class="cb-mobile-preview-bar__toggle" id="<?php echo esc_attr( $instance_id ); ?>-mobile-toggle" aria-label="<?php esc_attr_e( 'Toggle pizza preview', 'pizzatier' ); ?>">
				<i class="fa fa-chevron-down"></i>
			</button>
		</div>
		<div class="cb-mobile-preview-bar__expanded" id="<?php echo esc_attr( $instance_id ); ?>-mobile-expanded" aria-hidden="true"></div>
	</div>

	<!-- Main layout -->
	<div class="cb-layout">
		<div class="cb-layout__row">

			<!-- LEFT: sticky pizza visualizer -->
			<div class="cb-pizza-col" id="<?php echo esc_attr( $instance_id ); ?>-pizza-col">
				<div class="cb-pizza-sticky">
					<div class="cb-pizza-sticky__header">
						<i class="fa fa-pizza-slice"></i>
						<span><?php esc_html_e( 'Your Pizza', 'pizzatier' ); ?></span>
					</div>
					<div class="cb-pizza-sticky__canvas" id="<?php echo esc_attr( $instance_id ); ?>-canvas">
						<?php echo wp_kses( $initial_pizza, pzt_card_allowed_html() );?>
					</div>
					<div class="cb-pizza-sticky__footer">
						<button type="button" class="cb-btn cb-btn--ghost cb-btn--sm"
						        onclick="ClearPizza(); window['<?php echo esc_js( $cb_var ); ?>']&&window['<?php echo esc_js( $cb_var ); ?>'].resetAll();">
							<i class="fa fa-rotate-left"></i> <?php esc_html_e( 'Reset', 'pizzatier' ); ?>
						</button>
						<span class="cb-topping-counter">
							<i class="fa fa-layer-group"></i>
							<span id="<?php echo esc_attr( $instance_id ); ?>-count">0</span> / <?php echo esc_html( (string) $max_toppings ); ?> <?php esc_html_e( 'toppings', 'pizzatier' ); ?>
						</span>
					</div>
				</div>
			</div>

			<!-- RIGHT: tabbed builder -->
			<div class="cb-tabs-col">
				<div class="cb-builder">

					
                    <div class="cb-builder__header">
                        <div class="cb-builder__title"><?php esc_html_e( 'Build Your Pizza', 'pizzatier' ); ?></div>
                        <div class="cb-builder__subtitle"><?php esc_html_e( 'Tap a category, then pick your favorites.', 'pizzatier' ); ?></div>
                    </div>

                    <div class="cb-builder__body">
                        <aside class="cb-side">
<nav class="cb-tabnav" id="<?php echo esc_attr( $instance_id ); ?>-tabnav" role="tablist">
						<?php
						$tab_meta = [
							'crust'     => [ 'fa-circle',      __( 'Crust',     'pizzatier' ) ],
							'sauce'     => [ 'fa-droplet',     __( 'Sauce',     'pizzatier' ) ],
							'cheese'    => [ 'fa-cheese',      __( 'Cheese',    'pizzatier' ) ],
							'toppings'  => [ 'fa-seedling',    __( 'Toppings',  'pizzatier' ) ],
							'drizzle'   => [ 'fa-wine-glass',  __( 'Drizzle',   'pizzatier' ) ],
							'slicing'   => [ 'fa-pizza-slice', __( 'Slicing',   'pizzatier' ) ],
							'yourpizza' => [ 'fa-receipt',     __( 'Your Pizza','pizzatier' ) ],
						];
						$first_tab = true;
						foreach ( $visible_tabs as $pzt_tab ) :
							if ( ! isset( $tab_meta[ $pzt_tab ] ) ) { continue; }
							[ $icon, $label ] = $tab_meta[ $pzt_tab ];
							$active = $first_tab ? 'active' : '';
							$selected = $first_tab ? 'true' : 'false';
							$first_tab = false;
						?>
						<button class="cb-tab <?php echo esc_attr( $active ); ?>"
						        data-tab="<?php echo esc_attr( $pzt_tab ); ?>"
						        data-instance="<?php echo esc_attr( $instance_id ); ?>"
						        role="tab" aria-selected="<?php echo esc_attr( $selected ); ?>"
						        aria-controls="<?php echo esc_attr( $instance_id . '-panel-' . $pzt_tab ); ?>">
							<span class="cb-tab__icon"><i class="fa <?php echo esc_attr( $icon ); ?>"></i></span>
							<span class="cb-tab__label"><?php echo esc_html( $label ); ?></span>
						</button>
						<?php endforeach; ?>
					</nav>

					
<!-- Progress dots -->
					<div class="cb-progress" aria-hidden="true">
						<?php foreach ( $visible_tabs as $pzt_s ) : ?>
						<span class="cb-progress__dot" data-step="<?php echo esc_attr( $pzt_s ); ?>"></span>
						<?php endforeach; ?>
					</div>

					
                        </aside>

                        <section class="cb-main">
<!-- Tab panels -->
					<div class="cb-panels">

						<?php if ( in_array( 'crust', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_crust', $instance_id ); ?>
						<section class="cb-panel active" id="<?php echo esc_attr( $instance_id ); ?>-panel-crust" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-circle"></i> <?php esc_html_e( 'Choose Your Crust', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( 'Select one crust — it forms the base of your pizza.', 'pizzatier' ); ?></p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--exclusive"><?php echo wp_kses( $crusts_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<span></span>
								<button class="cb-btn cb-btn--next" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('sauce')"><?php esc_html_e( 'Sauce', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_crust', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'sauce', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_sauce', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-sauce" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-droplet"></i> <?php esc_html_e( 'Choose Your Sauce', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( 'Select one sauce.', 'pizzatier' ); ?></p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--exclusive"><?php echo wp_kses( $sauces_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('crust')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Crust', 'pizzatier' ); ?></button>
								<button class="cb-btn cb-btn--next" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('cheese')"><?php esc_html_e( 'Cheese', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_sauce', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'cheese', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_cheese', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-cheese" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-cheese"></i> <?php esc_html_e( 'Choose Your Cheese', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( 'Select one cheese.', 'pizzatier' ); ?></p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--exclusive"><?php echo wp_kses( $cheeses_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('sauce')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Sauce', 'pizzatier' ); ?></button>
								<button class="cb-btn cb-btn--next" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('toppings')"><?php esc_html_e( 'Toppings', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_cheese', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'toppings', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_toppings', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-toppings" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-seedling"></i> <?php esc_html_e( 'Choose Your Toppings', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint">
									<?php printf( /* translators: %s = maximum number of toppings. */ esc_html__( 'Add up to %s toppings.', 'pizzatier' ), '<strong>' . esc_html( (string) $max_toppings ) . '</strong>' ); ?>
								</p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--toppings"><?php echo wp_kses( $toppings_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('cheese')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Cheese', 'pizzatier' ); ?></button>
								<button class="cb-btn cb-btn--next" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('drizzle')"><?php esc_html_e( 'Drizzle', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_toppings', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'drizzle', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_drizzle', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-drizzle" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-wine-glass"></i> <?php esc_html_e( 'Choose a Drizzle', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( 'Optional finishing drizzle.', 'pizzatier' ); ?></p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--exclusive"><?php echo wp_kses( $drizzles_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('toppings')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Toppings', 'pizzatier' ); ?></button>
								<button class="cb-btn cb-btn--next" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('slicing')"><?php esc_html_e( 'Slicing', 'pizzatier' ); ?> <i class="fa fa-arrow-right"></i></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_drizzle', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'slicing', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_slicing', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-slicing" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-pizza-slice"></i> <?php esc_html_e( 'How Should We Slice It?', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( 'Choose a cut style.', 'pizzatier' ); ?></p>
							</div>
							<div class="cb-cards-grid cb-cards-grid--exclusive"><?php echo wp_kses( $cuts_html, pzt_card_allowed_html() );?></div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('drizzle')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Drizzle', 'pizzatier' ); ?></button>
								<button class="cb-btn cb-btn--next cb-btn--cta" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('yourpizza')"><i class="fa fa-receipt"></i> <?php esc_html_e( 'See Your Pizza', 'pizzatier' ); ?></button>
							</div>
						</section>
						<?php do_action( 'pizzatier_after_tab_slicing', $instance_id ); ?>
						<?php endif; ?>

						<?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) : ?>
						<?php do_action( 'pizzatier_before_tab_yourpizza', $instance_id ); ?>
						<section class="cb-panel" id="<?php echo esc_attr( $instance_id ); ?>-panel-yourpizza" role="tabpanel">
							<div class="cb-panel__header">
								<h2 class="cb-panel__title"><i class="fa fa-receipt"></i> <?php esc_html_e( 'Your Pizza', 'pizzatier' ); ?></h2>
								<p class="cb-panel__hint"><?php esc_html_e( "Here's everything you've built!", 'pizzatier' ); ?></p>
							</div>
							<div class="cb-yourpizza" id="<?php echo esc_attr( $instance_id ); ?>-summary">
								<?php
								$summary_rows = [
									'crust'    => [ 'fa-circle',      __( 'Crust',    'pizzatier' ) ],
									'sauce'    => [ 'fa-droplet',     __( 'Sauce',    'pizzatier' ) ],
									'cheese'   => [ 'fa-cheese',      __( 'Cheese',   'pizzatier' ) ],
									'toppings' => [ 'fa-seedling',    __( 'Toppings', 'pizzatier' ) ],
									'drizzle'  => [ 'fa-wine-glass',  __( 'Drizzle',  'pizzatier' ) ],
									'slicing'  => [ 'fa-pizza-slice', __( 'Slicing',  'pizzatier' ) ],
								];
								foreach ( $summary_rows as $key => [ $ico, $label ] ) :
								?>
								<div class="cb-yourpizza__row" id="<?php echo esc_attr( $instance_id ); ?>-yp-<?php echo esc_attr( $key ); ?>">
									<div class="cb-yourpizza__icon"><i class="fa <?php echo esc_attr( $ico ); ?>"></i></div>
									<div class="cb-yourpizza__layer-name"><?php echo esc_html( $label ); ?></div>
									<div class="cb-yourpizza__selection cb-yourpizza__selection--empty" id="<?php echo esc_attr( $instance_id . '-yp-' . $key . '-val' ); ?>">
										<span class="cb-yp-none">— <?php esc_html_e( 'none selected', 'pizzatier' ); ?> —</span>
									</div>
									<button class="cb-yourpizza__edit" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('<?php echo esc_js( $key ); ?>')"><i class="fa fa-pen"></i></button>
								</div>
								<?php endforeach; ?>
							</div>
							<div class="cb-panel__nav">
								<button class="cb-btn cb-btn--prev" onclick="<?php echo esc_js( $cb_var ); ?>.goTab('slicing')"><i class="fa fa-arrow-left"></i> <?php esc_html_e( 'Back', 'pizzatier' ); ?></button>
								<?php
								$start_over_label = sanitize_text_field( (string) get_option( 'pizzatier_setting_cx_start_over_label', 'Start Over' ) );
								$show_start_over  = get_option( 'pizzatier_setting_cx_show_start_over', 'yes' ) !== 'no';
								if ( $show_start_over ) :
								?>
								<button class="cb-btn cb-btn--ghost" onclick="ClearPizza(); window['<?php echo esc_js( $cb_var ); ?>']&&window['<?php echo esc_js( $cb_var ); ?>'].resetAll();"><i class="fa fa-rotate-left"></i> <?php echo esc_html( $start_over_label ); ?></button>
								<?php endif; ?>
							</div>
							<?php if ( $show_spec_instr ) : ?>
							<div class="cb-special-instructions-wrap">
								<label class="cb-special-instructions-label" for="<?php echo esc_attr( $instance_id ); ?>-special-instr">
									<?php esc_html_e( 'Special Instructions', 'pizzatier' ); ?>
								</label>
								<textarea
									class="cb-special-instructions"
									id="<?php echo esc_attr( $instance_id ); ?>-special-instr"
									name="pizzatier_special_instructions_<?php echo esc_attr( $instance_id ); ?>"
									placeholder="<?php echo esc_attr( $spec_placeholder ); ?>"
									maxlength="<?php echo esc_attr( (string) $spec_max ); ?>"
									rows="3"
								></textarea>
							</div>
							<?php endif; ?>
						</section>
						<?php do_action( 'pizzatier_after_tab_yourpizza', $instance_id ); ?>
						<?php endif; ?>

					</div><!-- /.cb-panels -->

						</section>
					</div><!-- /.cb-builder__body -->
				</div><!-- /.cb-builder -->
			</div><!-- /.cb-tabs-col -->

		</div><!-- /.cb-layout__row -->
	</div><!-- /.cb-layout -->

	<!-- Action bar: PizzaTier renders its checkout / Add to Cart bar here when
	     active. Placed full-width below the builder (rather than inside the
	     overflow-constrained sticky pizza column, where it could be clipped off
	     screen) so the Add to Cart CTA is always visible. -->
	<div class="cb-action-bar">
		<?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>
	</div>

	<div id="<?php echo esc_attr( $instance_id ); ?>-fly-container" aria-hidden="true"></div>

	<!-- Coverage picker modal (shared by all topping cards in this instance) -->
	<div class="cb-cov-modal" id="<?php echo esc_attr( $instance_id ); ?>-cov-modal" aria-hidden="true">
		<div class="cb-cov-modal__backdrop" onclick="window['<?php echo esc_js( $cb_var ); ?>']&&window['<?php echo esc_js( $cb_var ); ?>'].closeCoverage()"></div>
		<div class="cb-cov-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Choose topping coverage', 'pizzatier' ); ?>">
			<div class="cb-cov-modal__header">
				<span class="cb-cov-modal__title"><i class="fa fa-pizza-slice"></i> <?php esc_html_e( 'Choose Coverage', 'pizzatier' ); ?></span>
				<button type="button" class="cb-cov-modal__close" aria-label="<?php esc_attr_e( 'Close', 'pizzatier' ); ?>"
				        onclick="window['<?php echo esc_js( $cb_var ); ?>']&&window['<?php echo esc_js( $cb_var ); ?>'].closeCoverage()">&times;</button>
			</div>
			<div class="cb-cov-modal__grid">
				<?php foreach ( pzt_colorbox_enabled_coverages() as $fraction ) :
					$m_ico   = pzt_colorbox_coverage_icon( $fraction );
					$m_lbl   = pzt_colorbox_coverage_label( $fraction );
					$js_pick = "window['{$cb_var}']&&window['{$cb_var}'].chooseCoverage('" . esc_js( $fraction ) . "')";
				?>
				<button type="button" class="cb-cov-opt" data-fraction="<?php echo esc_attr( $fraction ); ?>"
				        onclick="<?php echo esc_attr( $js_pick ); ?>">
					<span class="cb-cov-ico cb-cov-ico--<?php echo esc_attr( $m_ico ); ?>"></span>
					<span class="cb-cov-opt__label"><?php echo esc_html( $m_lbl ); ?></span>
				</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

</div><!-- /#<?php echo esc_html( $instance_id ); ?> .cb-root -->

<?php
// Initialize this instance via wp_add_inline_script (WP.org compliant — no inline <script>).
$cb_init_js = "if(typeof CB!=='undefined'&&typeof CB.createInstance==='function'){"
	. "var " . esc_js( $cb_var ) . "=CB.createInstance(" . wp_json_encode( $instance_id ) . ");"
	. "}";
wp_add_inline_script( 'pizzatier-template-colorbox', $cb_init_js );
