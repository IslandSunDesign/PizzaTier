<?php
namespace PizzaTier\Builder;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Assembles pizza layer HTML.
 *
 * build_dynamic() — returns an empty stage scaffold; the JS populates layers.
 *                   Data attributes carry default layer URLs for JS to read.
 * build_static()  — returns fully-rendered flat layer stack (no JS needed).
 *
 * PizzaTier Public PHP API (for other plugins / themes):
 *   PizzaBuilder::render_pizza_stack( $layers )  → HTML string (flat stack)
 *   PizzaBuilder::get_layer_url( $type, $slug )  → image URL string
 */
class PizzaBuilder {

	private LayerRenderer $renderer;

	public function __construct() {
		$this->renderer = new LayerRenderer();
	}

	// ──────────────────────────────────────────────────────────────────────
	// PUBLIC STATIC API (for other plugins / JS via REST)
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Pizza API: render a full flat layer stack from a layers array.
	 *
	 * Usage from another plugin:
	 *   $html = PizzaBuilder::render_pizza_stack([
	 *       'crust'   => 'thin-crust',
	 *       'sauce'   => 'classic-tomato',
	 *       'cheese'  => 'mozzarella',
	 *       'toppings'=> ['pepperoni', 'mushrooms'],
	 *       'drizzle' => 'hot-honey',
	 *       'cut'     => '8-slices',
	 *   ]);
	 *
	 * @param array $layers  Associative array. 'toppings' may be array or comma string.
	 * @return string        HTML ready to drop into any container.
	 */
	public static function render_pizza_stack( array $layers ): string {
		$builder = new self();
		$tops    = $layers['toppings'] ?? '';
		if ( is_array( $tops ) ) { $tops = implode( ',', $tops ); }

		return $builder->build_static(
			$layers['crust']   ?? '',
			$layers['sauce']   ?? '',
			$layers['cheese']  ?? '',
			$tops,
			$layers['drizzle'] ?? '',
			$layers['cut']     ?? '',
			$layers['preset']  ?? ''
		);
	}

	/**
	 * Pizza API: get the layer image URL for a given type + slug.
	 *
	 * @param string $type  'crust'|'sauce'|'cheese'|'topping'|'drizzle'|'cut'
	 * @param string $slug  Post slug
	 * @return string       Image URL or empty string
	 */
	public static function get_layer_url( string $type, string $slug ): string {
		$type = self::normalize_layer_type( $type );
		$slug = sanitize_title( $slug );
		if ( '' === $type || '' === $slug ) { return ''; }
		$builder = new self();
		$id      = $builder->get_id_by_slug( $slug, $type . 's' );
		if ( ! $id ) { return ''; }
		$val = pzl_get_field( $type . '_layer_image', $id );
		// ACF may return an array (when return format = 'array') — unwrap to URL
		if ( is_array( $val ) ) { $val = $val['url'] ?? ''; }
		return (string) ( $val ?? '' );
	}

	/**
	 * Allowlist + normalize a layer type to its canonical singular form.
	 * Accepts singular or plural input; returns '' for anything unrecognised
	 * so callers never build a post-type name from arbitrary input.
	 */
	private static function normalize_layer_type( string $type ): string {
		$type = sanitize_key( $type );
		$map  = [
			'crust'    => 'crust',   'crusts'   => 'crust',
			'sauce'    => 'sauce',   'sauces'   => 'sauce',
			'cheese'   => 'cheese',  'cheeses'  => 'cheese',
			'topping'  => 'topping', 'toppings' => 'topping',
			'drizzle'  => 'drizzle', 'drizzles' => 'drizzle',
			'cut'      => 'cut',     'cuts'     => 'cut',
			'size'     => 'size',    'sizes'    => 'size',
		];
		return $map[ $type ] ?? '';
	}

	// ──────────────────────────────────────────────────────────────────────
	// INSTANCE HELPERS
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Get a post ID by slug for a given PizzaTier CPT type.
	 */
	public function get_id_by_slug( string $slug, string $type ): int {
		if ( ! $slug ) { return 0; }
		$post = get_page_by_path( $slug, OBJECT, 'pizzatier_' . $type );
		return is_object( $post ) ? (int) $post->ID : 0;
	}

	/**
	 * Resolve a layer slug: use the passed value if non-empty, otherwise fall
	 * back to the plugin's global default option.
	 */
	private function resolve_slug( string $param, string $option_key ): string {
		if ( $param !== '' ) { return $param; }
		return (string) get_option( $option_key, '' );
	}

	/**
	 * Get image URL for a layer by field name + post ID.
	 */
	private function get_img( string $field, int $id ): string {
		if ( ! $id ) { return ''; }
		$val = pzl_get_field( $field, $id );
		// ACF may return an array (when return format = 'array') — unwrap to URL
		if ( is_array( $val ) ) { $val = $val['url'] ?? ''; }
		return (string) ( $val ?? '' );
	}

	// ──────────────────────────────────────────────────────────────────────
	// DYNAMIC BUILDER
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Build the scaffold for a dynamic (interactive) pizza.
	 *
	 * Returns a .np-pizza-stage-wrap with:
	 *   - .np-pizza-stage  (empty — JS populates layers via PizzaStack.setLayer)
	 * Plus data-* attributes on the wrapper carrying default layer URLs
	 * for JS to read during _initDefaultLayers().
	 *
	 * Why empty stage? The JS architecture uses PizzaStack.setLayer() for all
	 * visual updates. Pre-rendering PHP HTML inside the stage created a mismatch
	 * between the nested PHP divs and the flat layer model the JS expects.
	 */
	public function build_dynamic(
		string $crust    = '',
		string $sauce    = '',
		string $cheese   = '',
		string $toppings = '',
		string $drizzle  = '',
		string $cut      = ''
	): string {
		do_action( 'pizzatier_before_builder', 'dynamic', compact( 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'cut' ) );

		$crust_slug   = $this->resolve_slug( $crust,   'pizzatier_setting_crust_defaultcrust'     );
		$sauce_slug   = $this->resolve_slug( $sauce,   'pizzatier_setting_sauce_defaultsauce'     );
		$cheese_slug  = $this->resolve_slug( $cheese,  'pizzatier_setting_cheese_defaultcheese'   );
		$drizzle_slug = $this->resolve_slug( $drizzle, 'pizzatier_setting_drizzle_defaultdrizzle' );
		$cut_slug     = $this->resolve_slug( $cut,     'pizzatier_setting_cut_defaultcut'         );

		// Resolve image URLs for default layers — passed to JS via data attrs
		$crust_url   = $crust_slug  ? $this->get_img( 'crust_layer_image',   $this->get_id_by_slug( $crust_slug,  'crusts'  ) ) : '';
		$sauce_url   = $sauce_slug  ? $this->get_img( 'sauce_layer_image',   $this->get_id_by_slug( $sauce_slug,  'sauces'  ) ) : '';
		$cheese_url  = $cheese_slug ? $this->get_img( 'cheese_layer_image',  $this->get_id_by_slug( $cheese_slug, 'cheeses' ) ) : '';
		$drizzle_url = $drizzle_slug ? $this->get_img( 'drizzle_layer_image',$this->get_id_by_slug( $drizzle_slug,'drizzles') ) : '';
		$cut_url     = $cut_slug    ? $this->get_img( 'cut_layer_image',     $this->get_id_by_slug( $cut_slug,    'cuts'    ) ) : '';

		// Toppings: build JS-friendly JSON for default toppings (if shortcode specifies them)
		$toppings_data = '[]';
		$toppings_arr  = $toppings ? array_filter( array_map( 'trim', explode( ',', $toppings ) ) ) : [];
		if ( $toppings_arr ) {
			$tops_json = [];
			$z = 400;
			foreach ( $toppings_arr as $t_slug ) {
				$z += 5;
				$t_id  = $this->get_id_by_slug( $t_slug, 'toppings' );
				$t_url = $this->get_img( 'topping_layer_image', $t_id );
				if ( $t_url ) {
					$tops_json[] = [
						'slug'     => $t_slug,
						'layerImg' => $t_url,
						'zindex'   => $z,
						'coverage' => 'whole',
					];
				}
			}
			$toppings_data = wp_json_encode( $tops_json );
		}

		$html = '<div class="np-pizza-stage-wrap"'
			. ' data-default-crust="'   . esc_attr( $crust_url   ) . '"'
			. ' data-default-sauce="'   . esc_attr( $sauce_url   ) . '"'
			. ' data-default-cheese="'  . esc_attr( $cheese_url  ) . '"'
			. ' data-default-drizzle="' . esc_attr( $drizzle_url ) . '"'
			. ' data-default-cut="'     . esc_attr( $cut_url     ) . '"'
			. ' data-default-crust-slug="'  . esc_attr( $crust_slug  ) . '"'
			. ' data-default-sauce-slug="'  . esc_attr( $sauce_slug  ) . '"'
			. ' data-default-cheese-slug="' . esc_attr( $cheese_slug ) . '"'
			. ' data-default-toppings=\'' . esc_attr( $toppings_data ) . '\''
			. '>'
			. '<div class="np-pizza-stage"></div>'
			. '</div>';

		do_action( 'pizzatier_after_builder', 'dynamic', compact( 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'cut' ) );

		return $html;
	}

	// ──────────────────────────────────────────────────────────────────────
	// STATIC PIZZA
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Build fully-rendered flat layer stack for a static (non-interactive) pizza.
	 * Layers are <div><img></div> elements stacked by z-index inside a stage.
	 */
	public function build_static(
		string $crust    = '',
		string $sauce    = '',
		string $cheese   = '',
		string $toppings = '',
		string $drizzle  = '',
		string $cut      = '',
		string $preset   = '' // kept for backward compatibility — ignored
	): string {
		// Preset lookup removed (Pizza Presets CPT has been retired).
		// Pass layers directly via shortcode attributes instead.

		$layers = apply_filters( 'pizzatier_static_layers', compact( 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'cut' ), [] );
		extract( $layers ); // phpcs:ignore WordPress.PHP.DontExtract

		do_action( 'pizzatier_before_static_pizza', $layers );

		$crust_slug   = $this->resolve_slug( $crust,   'pizzatier_setting_crust_defaultcrust'     );
		$sauce_slug   = $this->resolve_slug( $sauce,   'pizzatier_setting_sauce_defaultsauce'     );
		$cheese_slug  = $this->resolve_slug( $cheese,  'pizzatier_setting_cheese_defaultcheese'   );
		$drizzle_slug = $this->resolve_slug( $drizzle, 'pizzatier_setting_drizzle_defaultdrizzle' );
		$cut_slug     = $this->resolve_slug( $cut,     'pizzatier_setting_cut_defaultcut'         );

		$layers_html = '';

		// Crust (z:100)
		$c_url = $crust_slug ? $this->get_img( 'crust_layer_image', $this->get_id_by_slug( $crust_slug, 'crusts' ) ) : '';
		$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => 100, 'type' => 'crust',  'slug' => $crust_slug,  'image_url' => $c_url ] ) );

		// Sauce (z:200)
		$s_url = $sauce_slug ? $this->get_img( 'sauce_layer_image', $this->get_id_by_slug( $sauce_slug, 'sauces' ) ) : '';
		$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => 200, 'type' => 'sauce',  'slug' => $sauce_slug,  'image_url' => $s_url ] ) );

		// Cheese (z:300)
		$ch_url = $cheese_slug ? $this->get_img( 'cheese_layer_image', $this->get_id_by_slug( $cheese_slug, 'cheeses' ) ) : '';
		$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => 300, 'type' => 'cheese', 'slug' => $cheese_slug, 'image_url' => $ch_url ] ) );

		// Toppings (z:400+)
		$toppings_arr = $toppings ? array_filter( array_map( 'trim', explode( ',', $toppings ) ) ) : [];
		$z = 400;
		foreach ( $toppings_arr as $t_slug ) {
			$z += 10;
			$t_url = $this->get_img( 'topping_layer_image', $this->get_id_by_slug( $t_slug, 'toppings' ) );
			$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => $z, 'type' => 'topping', 'slug' => $t_slug, 'image_url' => $t_url ] ) );
		}

		// Drizzle (z:900)
		$dr_url = $drizzle_slug ? $this->get_img( 'drizzle_layer_image', $this->get_id_by_slug( $drizzle_slug, 'drizzles' ) ) : '';
		$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => 900, 'type' => 'drizzle', 'slug' => $drizzle_slug, 'image_url' => $dr_url ] ) );

		// Cut (z:950)
		$cu_url = $cut_slug ? $this->get_img( 'cut_layer_image', $this->get_id_by_slug( $cut_slug, 'cuts' ) ) : '';
		$layers_html .= $this->renderer->render_closed( new LayerDTO( [ 'z_index' => 950, 'type' => 'cut',     'slug' => $cut_slug,     'image_url' => $cu_url ] ) );

		do_action( 'pizzatier_after_static_pizza', $layers );

		return '<div class="np-pizza-stage-wrap"><div class="np-pizza-stage np-pizza-stage--static">'
			. $layers_html
			. '</div></div>';
	}
}
