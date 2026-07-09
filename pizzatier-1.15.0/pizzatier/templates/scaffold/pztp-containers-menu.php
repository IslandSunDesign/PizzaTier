<?php
/**
 * Scaffold Template — main builder render file.
 *
 * This file queries all CPTs, builds card HTML via helpers, then assembles
 * everything by including the HTML partials from ./partials/.
 *
 * ─── PARTIAL FILE SYSTEM ─────────────────────────────────────────────────────
 * Each visual element lives in its own file under ./partials/. To customise
 * a partial without touching this file:
 *   1. Duplicate the partial file and rename it (e.g. pizza-stage-custom.html).
 *   2. Override the path via the shortcode attribute partial_<name>=, e.g.:
 *        [pizzatier-menu partial_pizza_stage="pizza-stage-custom.html"]
 *   3. Or filter: add_filter('pizzatier_scaffold_partial', function($file, $name){
 *          if ($name === 'pizza-stage') return 'my-stage.html';
 *          return $file;
 *      }, 10, 2);
 *
 * ─── VARIABLES AVAILABLE IN ALL PARTIALS ─────────────────────────────────────
 *   $instance_id   string  unique builder instance ID
 *   $sc_var        string  JS window-object name, e.g. SC_pizzabuilder_1
 *   $atts          array   shortcode attributes
 *   $template_slug string  'scaffold'
 *   $pizza_shape   string  round | square | rectangle | custom
 *   $pizza_aspect  string  CSS aspect-ratio, e.g. '1 / 1'
 *   $pizza_radius  string  CSS border-radius, e.g. '8px'
 *   $visible_tabs  array   tab slugs to render
 *   $summary_title string  localised heading for summary panel
 *   $max_toppings  int     maximum allowed toppings
 * ─────────────────────────────────────────────────────────────────────────────
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Template helper functions use the plugin's pzt_ (PizzaTier Template) prefix; shared/back-compat helpers are function_exists()-guarded against redeclaration.

// ── Guard against direct inclusion without context ───────────────────────────
if ( ! isset( $instance_id ) )      { $instance_id    = 'pizzabuilder-1'; }
if ( ! isset( $atts ) )             { $atts           = []; }
if ( ! isset( $template_slug ) )    { $template_slug  = 'scaffold'; }
if ( ! isset( $function_prefix ) )  { $function_prefix = 'pzt_scaffold'; }

// ── JS namespace ──────────────────────────────────────────────────────────────
$sc_var = 'SC_' . preg_replace( '/[^a-zA-Z0-9_]/', '_', $instance_id );

// ── Max toppings ──────────────────────────────────────────────────────────────
$max_toppings = isset( $atts['max_toppings'] ) && (int) $atts['max_toppings'] > 0
    ? (int) $atts['max_toppings']
    : (int) get_option( 'pizzatier_setting_topping_maxtoppings', 0 );
if ( $max_toppings < 1 ) { $max_toppings = 99; }
$max_toppings = (int) apply_filters( 'pizzatier_max_toppings', $max_toppings, $instance_id );

// ── Pizza shape ───────────────────────────────────────────────────────────────
$valid_shapes  = [ 'round', 'square', 'rectangle', 'custom' ];
$pizza_shape   = sanitize_key( $atts['pizza_shape']  ?? get_option( 'pizzatier_setting_pizza_shape',  'round'  ) );
if ( ! in_array( $pizza_shape, $valid_shapes, true ) ) { $pizza_shape = 'round'; }
$pizza_aspect  = sanitize_text_field( $atts['pizza_aspect']  ?? get_option( 'pizzatier_setting_pizza_aspect',  '1 / 1' ) );
$pizza_radius  = sanitize_text_field( $atts['pizza_radius']  ?? get_option( 'pizzatier_setting_pizza_radius',  '8px'   ) );


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

// ── Visible tabs ──────────────────────────────────────────────────────────────
$hide_tabs_raw = $atts['hide_tabs'] ?? '';
$show_tabs_raw = $atts['show_tabs'] ?? '';
$all_tabs      = array_merge( $_has_pro ? [ 'size' ] : [], [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ] );
$all_tabs      = (array) apply_filters( 'pizzatier_tab_order', $all_tabs, $instance_id );

if ( $show_tabs_raw ) {
    $visible_tabs = array_values( array_intersect( $all_tabs, array_map( 'trim', explode( ',', $show_tabs_raw ) ) ) );
} elseif ( $hide_tabs_raw ) {
    $visible_tabs = array_values( array_diff( $all_tabs, array_map( 'trim', explode( ',', $hide_tabs_raw ) ) ) );
} else {
    $visible_tabs = $all_tabs;
}

// ── Summary heading ───────────────────────────────────────────────────────────
$summary_title = sanitize_text_field(
    (string) get_option( 'scaffold_setting_summary_title', __( 'Your Pizza', 'pizzatier' ) )
);

// ── CPT queries ───────────────────────────────────────────────────────────────
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

// ── Partial loader ─────────────────────────────────────────────────────────────
/**
 * pzt_scaffold_partial()
 *
 * Resolves and includes a partial file, passing the current scope.
 * Partial name is the filename without the .html extension.
 *
 * Resolution order:
 *  1. Shortcode attribute partial_<snake_name>= (e.g. partial_pizza_stage=)
 *  2. 'pizzatier_scaffold_partial' filter
 *  3. Default ./partials/<name>.html
 *
 * @param string $name          Partial name, e.g. 'pizza-stage'
 * @param array  $extra_vars    Additional variables to extract into partial scope
 * @param array  $atts_ref      Reference to $atts for shortcode overrides
 */
if ( ! function_exists( 'pzt_scaffold_partial' ) ) :
function pzt_scaffold_partial( string $name, array $extra_vars = [], array $atts_ref = [] ): void {
    // Check shortcode attr override (dashes → underscores for attr name)
    $attr_key = 'partial_' . str_replace( '-', '_', $name );
    $filename  = ! empty( $atts_ref[ $attr_key ] ) ? sanitize_file_name( $atts_ref[ $attr_key ] ) : ( $name . '.html' );

    // Allow filter override
    $filename = (string) apply_filters( 'pizzatier_scaffold_partial', $filename, $name );

    // Guard against empty filename (e.g. filter returned '' or sanitize_file_name() stripped everything)
    if ( '' === trim( $filename ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Developer warning to error log; interpolated values are internal template identifiers, not user input.
        trigger_error( "PizzaTier Scaffold: empty partial filename for '{$name}'", E_USER_WARNING );
        return;
    }

    $path = __DIR__ . '/partials/' . $filename;
    // Use is_file() — file_exists() returns true for directories, which causes include() to fail
    if ( ! is_file( $path ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Developer warning to error log; interpolated values are internal template identifiers, not user input.
        trigger_error( "PizzaTier Scaffold: partial not found: {$path}", E_USER_WARNING );
        return;
    }

    // Extract extra vars into this scope
    if ( $extra_vars ) { extract( $extra_vars, EXTR_SKIP ); } // phpcs:ignore WordPress.PHP.DontExtract
    include $path;
}
endif;

// ── Card render helpers ────────────────────────────────────────────────────────
/**
 * Render a single base-layer item card.
 * Returns HTML string — partial vars are resolved here before include.
 */
if ( ! function_exists( 'pzt_scaffold_render_item_card' ) ) :
function pzt_scaffold_render_item_card( \WP_Post $post, string $layer_type, string $sc_var ): string {
    $id         = $post->ID;
    $title      = get_the_title( $post );
    $slug       = sanitize_title( $title );
    $img_field  = $layer_type . '_image';
    $lyr_field  = $layer_type . '_layer_image';
    $thumb_url  = (string) ( pzl_get_field( $img_field, $id ) ?: pzl_get_field( $lyr_field, $id ) ?: get_the_post_thumbnail_url( $id, 'thumbnail' ) );
    $layer_url  = (string) ( pzl_get_field( $lyr_field, $id ) ?: $thumb_url );

    do_action( 'pizzatier_before_layer_card', $post, $layer_type );

    ob_start();
    include __DIR__ . '/partials/item-card.html';
    $html = ob_get_clean();

    return (string) apply_filters( 'pizzatier_card_html', $html, $post, $layer_type );
}
endif;

/**
 * Render a single topping card (multi-select + coverage).
 * Returns HTML string.
 */
if ( ! function_exists( 'pzt_scaffold_render_topping_card' ) ) :
function pzt_scaffold_render_topping_card( \WP_Post $post, string $sc_var, int $zindex ): string {
    $id        = $post->ID;
    $title     = get_the_title( $post );
    $slug      = sanitize_title( $title );
    $thumb_url = (string) ( pzl_get_field( 'topping_image', $id ) ?: pzl_get_field( 'topping_layer_image', $id ) ?: get_the_post_thumbnail_url( $id, 'thumbnail' ) );
    $layer_url = (string) ( pzl_get_field( 'topping_layer_image', $id ) ?: $thumb_url );
    $layer_id  = 'pizzatier-topping-' . $slug;

    do_action( 'pizzatier_before_layer_card', $post, 'toppings' );

    ob_start();
    include __DIR__ . '/partials/item-card-topping.html';
    $html = ob_get_clean();

    return (string) apply_filters( 'pizzatier_card_html', $html, $post, 'toppings' );
}
endif;

// ── Pre-render all card HTML pools ─────────────────────────────────────────────
$crusts_html   = '';
foreach ( $crusts   as $post ) { if ( $post instanceof \WP_Post ) { $crusts_html   .= pzt_scaffold_render_item_card( $post, 'crust',   $sc_var ); } }
$sauces_html   = '';
foreach ( $sauces   as $post ) { if ( $post instanceof \WP_Post ) { $sauces_html   .= pzt_scaffold_render_item_card( $post, 'sauce',   $sc_var ); } }
$cheeses_html  = '';
foreach ( $cheeses  as $post ) { if ( $post instanceof \WP_Post ) { $cheeses_html  .= pzt_scaffold_render_item_card( $post, 'cheese',  $sc_var ); } }
$drizzles_html = '';
foreach ( $drizzles as $post ) { if ( $post instanceof \WP_Post ) { $drizzles_html .= pzt_scaffold_render_item_card( $post, 'drizzle', $sc_var ); } }
$cuts_html     = '';
foreach ( $cuts     as $post ) { if ( $post instanceof \WP_Post ) { $cuts_html     .= pzt_scaffold_render_item_card( $post, 'slicing', $sc_var ); } }
$toppings_html = '';
$_tz = 500;
foreach ( $toppings as $post ) {
    if ( ! ( $post instanceof \WP_Post ) ) { continue; }
    $toppings_html .= pzt_scaffold_render_topping_card( $post, $sc_var, $_tz );
    $_tz += 10;
}

// Map panel slug → [html pool, empty message]
$_panel_map = [
    'crust'    => [ $crusts_html,   __( 'No crusts found.',   'pizzatier' ) ],
    'sauce'    => [ $sauces_html,   __( 'No sauces found.',   'pizzatier' ) ],
    'cheese'   => [ $cheeses_html,  __( 'No cheeses found.',  'pizzatier' ) ],
    'toppings' => [ $toppings_html, __( 'No toppings found.', 'pizzatier' ) ],
    'drizzle'  => [ $drizzles_html, __( 'No drizzles found.', 'pizzatier' ) ],
    'slicing'  => [ $cuts_html,     __( 'No cuts found.',     'pizzatier' ) ],
];

// ── Template options ─────────────────────────────────────────────────────────
$_opt = fn( string $key, string $default = '' ) => (string) get_option( $key, $default );

$sc_accent_color   = $_opt( 'scaffold_setting_accent_color',   '#2271b1' );
$sc_bg_color       = $_opt( 'scaffold_setting_bg_color',       '#ffffff' );
$sc_text_color     = $_opt( 'scaffold_setting_text_color',     '#1e1e1e' );
$sc_border_color   = $_opt( 'scaffold_setting_border_color',   '#dcdcde' );
$sc_font_family    = $_opt( 'scaffold_setting_font_family',    'inherit' );
$sc_font_custom    = $_opt( 'scaffold_setting_font_custom',    '' );
$sc_base_font_size = $_opt( 'scaffold_setting_base_font_size', '14px'    );
$sc_card_radius    = $_opt( 'scaffold_setting_card_radius',    '6px'     );
$sc_card_cols      = $_opt( 'scaffold_setting_card_cols',      '3'       );
$sc_thumb_size     = $_opt( 'scaffold_setting_thumb_size',     '56px'    );
$sc_tab_style      = $_opt( 'scaffold_setting_tab_style',      'underline' );
$sc_builder_width  = $_opt( 'scaffold_setting_builder_width',  '' );
$sc_show_labels    = $_opt( 'scaffold_setting_show_labels',    'yes'     );
$sc_anim_speed     = $_opt( 'scaffold_setting_anim_speed',     '200ms'   );

// Resolve font stack
$_fonts = [
    'inherit'    => 'inherit',
    'system'     => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    'serif'      => 'Georgia, "Times New Roman", serif',
    'mono'       => '"Courier New", Courier, monospace',
    'custom'     => $sc_font_custom ? $sc_font_custom : 'inherit',
];
$sc_font_stack = $_fonts[ $sc_font_family ] ?? 'inherit';

$_cols_map = [ '2' => 'repeat(2,1fr)', '3' => 'repeat(3,1fr)', '4' => 'repeat(4,1fr)', 'auto' => 'repeat(auto-fill,minmax(80px,1fr))' ];
$sc_grid_cols_css = $_cols_map[ $sc_card_cols ] ?? 'repeat(3,1fr)';

$sc_width_css = $sc_builder_width ? 'max-width:' . esc_attr( $sc_builder_width ) . ';' : '';

// ── Topping JS data (needed by base JS engine) ────────────────────────────────
$_topping_data = [];
foreach ( $toppings as $_tp ) {
    if ( ! ( $_tp instanceof \WP_Post ) ) { continue; }
    $_ts    = sanitize_title( get_the_title( $_tp ) );
    $_tu    = (string) ( pzl_get_field( 'topping_layer_image', $_tp->ID ) ?: pzl_get_field( 'topping_image', $_tp->ID ) ?: get_the_post_thumbnail_url( $_tp->ID, 'full' ) );
    $_topping_data[] = [ 'slug' => $_ts, 'url' => $_tu, 'title' => get_the_title( $_tp ) ];
}
// ── Default selections from options ──────────────────────────────────────────
$_defaults = [
    'crust'   => sanitize_text_field( $atts['default_crust']  ?? (string) get_option( 'pizzatier_setting_crust_defaultcrust',    '' ) ),
    'sauce'   => sanitize_text_field( $atts['default_sauce']  ?? (string) get_option( 'pizzatier_setting_sauce_defaultsauce',    '' ) ),
    'cheese'  => sanitize_text_field( $atts['default_cheese'] ?? (string) get_option( 'pizzatier_setting_cheese_defaultcheese',  '' ) ),
    'drizzle' => sanitize_text_field( $atts['default_drizzle'] ?? (string) get_option( 'pizzatier_setting_drizzle_defaultdrizzle', '' ) ),
    'slicing' => sanitize_text_field( $atts['default_slicing'] ?? (string) get_option( 'pizzatier_setting_cut_defaultcut',        '' ) ),
];
// ─────────────────────────────────────────────────────────────────────────────
// BEGIN HTML OUTPUT
// ─────────────────────────────────────────────────────────────────────────────
do_action( 'pizzatier_before_builder', $instance_id, $template_slug );
?>
<div
  id="<?php echo esc_attr( $instance_id ); ?>"
  class="sc-root sc-tab-style--<?php echo esc_attr( $sc_tab_style ); ?>"
  data-instance="<?php echo esc_attr( $instance_id ); ?>"
  data-template="scaffold"
  data-max-toppings="<?php echo esc_attr( (string) $max_toppings ); ?>"
  data-sc-cfg="<?php echo esc_attr( wp_json_encode( [
    'instanceId'  => $instance_id,
    'varName'     => $sc_var,
    'defaults'    => $_defaults,
    'maxToppings' => $max_toppings,
    'toppings'    => $_topping_data,
  ] ) ); ?>"
  style="<?php echo esc_attr( $sc_width_css ); ?>"
>

<?php
// ── Instance CSS custom properties via wp_add_inline_style ─────────────────
// NOTE: esc_attr() is for HTML attributes, NOT CSS. Running it on values that
// contain quotes/commas (e.g. the system/serif/mono font stacks) corrupts them
// into &quot; / &#039; entities, which are invalid inside a <style> block and
// silently break the font. Use a CSS-context sanitizer that strips only the
// characters able to terminate a declaration or inject a rule, while keeping
// quotes, commas and parens that are legal in property values.
$_sc_css_val = static function ( $v ) {
	return trim( str_replace( [ '<', '>', '{', '}', ';', '\\' ], '', (string) $v ) );
};
$_sc_sel = sanitize_html_class( $instance_id );

$_sc_instance_css  = '#' . $_sc_sel . ' {' . "\n";
$_sc_instance_css .= '  --sc-accent:      ' . $_sc_css_val( $sc_accent_color )   . ';' . "\n";
$_sc_instance_css .= '  --sc-bg:          ' . $_sc_css_val( $sc_bg_color )        . ';' . "\n";
$_sc_instance_css .= '  --sc-text:        ' . $_sc_css_val( $sc_text_color )      . ';' . "\n";
$_sc_instance_css .= '  --sc-border:      ' . $_sc_css_val( $sc_border_color )    . ';' . "\n";
$_sc_instance_css .= '  --sc-font:        ' . $_sc_css_val( $sc_font_stack )      . ';' . "\n";
$_sc_instance_css .= '  --sc-font-size:   ' . $_sc_css_val( $sc_base_font_size )  . ';' . "\n";
$_sc_instance_css .= '  --sc-card-radius: ' . $_sc_css_val( $sc_card_radius )     . ';' . "\n";
$_sc_instance_css .= '  --sc-thumb-size:  ' . $_sc_css_val( $sc_thumb_size )      . ';' . "\n";
$_sc_instance_css .= '  --sc-grid-cols:   ' . $_sc_css_val( $sc_grid_cols_css )   . ';' . "\n";
$_sc_instance_css .= '  --sc-anim-speed:  ' . $_sc_css_val( $sc_anim_speed )      . ';' . "\n";
$_sc_instance_css .= '}' . "\n";
if ( 'yes' !== $sc_show_labels ) {
	$_sc_instance_css .= '#' . $_sc_sel . ' .sc-card__label { display:none; }' . "\n";
}
// Attach the scoped instance variables to the template stylesheet handle
// (enqueued by AssetManager as 'pizzatier-template-scaffold'). In contexts
// without that handle (REST / block preview), route them through a dedicated
// inline-only handle so they still go out via the stylesheet pipeline rather
// than a raw <style> tag echoed into the markup.
if ( wp_style_is( 'pizzatier-template-scaffold', 'enqueued' ) ) {
	wp_add_inline_style( 'pizzatier-template-scaffold', $_sc_instance_css );
} else {
	if ( ! wp_style_is( 'pizzatier-scaffold-inline', 'registered' ) ) {
		wp_register_style( 'pizzatier-scaffold-inline', false, [], PIZZATIER_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
	wp_enqueue_style( 'pizzatier-scaffold-inline' );
	wp_add_inline_style( 'pizzatier-scaffold-inline', $_sc_instance_css );
}
unset( $_sc_instance_css );
?>

<?php do_action( 'pizzatier_scaffold_before_stage', $instance_id ); ?>

<!-- ── Pizza stage ───────────────────────────────────────────────────────── -->
<?php pzt_scaffold_partial( 'pizza-stage', compact( 'instance_id', 'sc_var', 'pizza_shape', 'pizza_aspect', 'pizza_radius', 'atts' ), $atts ); ?>

<?php do_action( 'pizzatier_scaffold_before_tabs', $instance_id ); ?>

<!-- ── Tab bar ───────────────────────────────────────────────────────────── -->
<?php pzt_scaffold_partial( 'tab-bar', compact( 'instance_id', 'sc_var', 'visible_tabs', 'atts' ), $atts ); ?>

<?php do_action( 'pizzatier_scaffold_before_panels', $instance_id ); ?>

<!-- ── Category panels ──────────────────────────────────────────────────── -->
<div class="sc-panels" data-instance="<?php echo esc_attr( $instance_id ); ?>">
<?php if ( $_has_pro ) : ?>
<?php do_action( 'pizzatier_before_tab_size', $instance_id ); ?>
<div class="sc-panel sc-panel--size sc-panel--active" data-tab="size" id="<?php echo esc_attr( $instance_id ); ?>-panel-size" role="tabpanel">
	<div class="sc-panel__header">
		<h2 class="sc-panel__title"><?php esc_html_e( 'Choose Your Size', 'pizzatier' ); ?></h2>
		<p class="sc-panel__hint"><?php esc_html_e( 'Select the size of your pizza.', 'pizzatier' ); ?></p>
	</div>
	<?php pzt_render_inline_size_selector( $_pro_sizes, $instance_id, 'sc' ); ?>
</div>
<?php do_action( 'pizzatier_after_tab_size', $instance_id ); ?>
<?php endif; ?>

<?php
$_sc_first_panel = true;
foreach ( $visible_tabs as $tab_slug ) :
    if ( 'yourpizza' === $tab_slug ) { continue; } // rendered separately below
    if ( 'size' === $tab_slug ) { continue; } // rendered separately above as sc-panel--size
    $panel_html = '';
    $empty_msg  = __( 'No items found.', 'pizzatier' );
    if ( isset( $_panel_map[ $tab_slug ] ) ) {
        [ $panel_html, $empty_msg ] = $_panel_map[ $tab_slug ];
    }
    $sc_is_first = $_sc_first_panel;
    $_sc_first_panel = false;
    pzt_scaffold_partial( 'category-panel', compact( 'instance_id', 'sc_var', 'tab_slug', 'panel_html', 'empty_msg', 'atts', 'sc_is_first' ), $atts );
endforeach; ?>

<!-- ── Summary panel ────────────────────────────────────────────────────── -->
<?php if ( in_array( 'yourpizza', $visible_tabs, true ) ) :
    pzt_scaffold_partial( 'summary-panel', compact( 'instance_id', 'sc_var', 'summary_title', 'atts' ), $atts );
endif; ?>

</div><!-- /.sc-panels -->

<?php do_action( 'pizzatier_scaffold_after_panels', $instance_id ); ?>

<?php
// Pass per-instance config to the enqueued custom.js via data-sc-cfg attribute.
// wp_add_inline_script can't reference DOM nodes, so we embed JSON on the root el.
// custom.js reads rootEl.getAttribute('data-sc-cfg') at runtime.
// (data attributes containing JSON are WP.org compliant — no executable script tag.)
?>

	<?php do_action( 'pizzatier_builder_action_bar', $instance_id ); ?>

</div><!-- /.sc-root -->

<?php do_action( 'pizzatier_after_builder', $instance_id, $template_slug ); ?>
