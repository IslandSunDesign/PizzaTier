<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizzatier_layer_info] shortcode — outputs the value of any SCF field for a layer.
 *
 * Attributes:
 *   type   CPT type suffix (e.g. 'topping', 'cheese').
 *   slug   Post slug.
 *   field  SCF field name (e.g. 'topping_ingredients', 'cheese_melt_factor').
 */
class LayerInfoShortcode {

	/**
	 * Accepted `type` values (singular and plural) => layer post type.
	 *
	 * The type used to be concatenated straight into 'pizzatier_' . $type, so
	 * any plugin post type could be read — including pizzatier_order, whose
	 * meta holds customer names, emails and phone numbers.
	 */
	private const TYPE_MAP = [
		'crust'    => 'pizzatier_crusts',
		'crusts'   => 'pizzatier_crusts',
		'sauce'    => 'pizzatier_sauces',
		'sauces'   => 'pizzatier_sauces',
		'cheese'   => 'pizzatier_cheeses',
		'cheeses'  => 'pizzatier_cheeses',
		'topping'  => 'pizzatier_toppings',
		'toppings' => 'pizzatier_toppings',
		'drizzle'  => 'pizzatier_drizzles',
		'drizzles' => 'pizzatier_drizzles',
		'cut'      => 'pizzatier_cuts',
		'cuts'     => 'pizzatier_cuts',
		'size'     => 'pizzatier_sizes',
		'sizes'    => 'pizzatier_sizes',
		'preset'   => 'pizzatier_presets',
		'presets'  => 'pizzatier_presets',
	];

	/**
	 * Protected ('_'-prefixed) meta keys that are public layer data and may be
	 * shown. Every other protected key is refused.
	 */
	private const PUBLIC_PROTECTED_FIELDS = [
		'_pizzatier_ingredients',
		'_pizzatier_serving_size',
		'_pizzatier_calories',
		'_pizzatier_fat',
		'_pizzatier_carbs',
		'_pizzatier_protein',
		'_pizzatier_sodium',
		'_pizzatier_allergens',
		'_pizzatier_spice_level',
		'_pizzatier_thickness',
		'_pizzatier_diameter_inches',
		'_pizzatier_is_vegetarian',
		'_pizzatier_is_vegan',
		'_pizzatier_is_gluten_free',
		'_pizzatier_is_dairy_free',
	];

	/**
	 * Resolve a layer post type from a shortcode `type` value, or '' when the
	 * value is not a layer type.
	 */
	public static function post_type_for( string $type ): string {
		$type = sanitize_key( $type );
		return self::TYPE_MAP[ $type ] ?? '';
	}

	/**
	 * Singular layer type ('topping', 'cheese', ...) for a layer post type.
	 */
	public static function singular_for( string $post_type ): string {
		return (string) preg_replace( '/s$/', '', str_replace( 'pizzatier_', '', $post_type ) );
	}

	/**
	 * Find a published, non-password-protected layer post by type and slug.
	 */
	public static function find_public_post( string $type, string $slug ): ?\WP_Post {
		$post_type = self::post_type_for( $type );
		if ( '' === $post_type || '' === $slug ) {
			return null;
		}
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		// get_page_by_path() ignores post status, so drafts, private and
		// trashed layers would otherwise be readable.
		if ( 'publish' !== $post->post_status || post_password_required( $post ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Whether a field may be shown publicly.
	 */
	private static function field_allowed( string $field ): bool {
		if ( '' === $field ) {
			return false;
		}
		if ( 0 === strpos( $field, '_' ) ) {
			return in_array( $field, self::PUBLIC_PROTECTED_FIELDS, true );
		}
		return true;
	}

	public function render( $atts ): string {
		$atts = shortcode_atts( [
			'type'  => '',
			'slug'  => '',
			'field' => '',
		], $atts, 'pizzatier_layer_info' );

		$type  = sanitize_key( $atts['type'] );
		$slug  = sanitize_key( $atts['slug'] );
		$field = sanitize_key( $atts['field'] );

		if ( ! $type || ! $slug || ! $field ) {
			return '<!-- [pizzatier_layer_info] missing type, slug, or field -->';
		}

		if ( '' === self::post_type_for( $type ) ) {
			return '<!-- [pizzatier_layer_info] unknown type -->';
		}

		if ( ! self::field_allowed( $field ) ) {
			return '<!-- [pizzatier_layer_info] field not available -->';
		}

		$post = self::find_public_post( $type, $slug );
		if ( ! $post ) { return '<!-- [pizzatier_layer_info] post not found -->'; }

		$value = pzl_get_field( $field, $post->ID );
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'is_scalar' ) );
		}
		if ( ! is_scalar( $value ) ) {
			$value = '';
		}

		return esc_html( (string) $value );
	}
}
