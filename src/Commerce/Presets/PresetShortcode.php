<?php
/**
 * PizzaTier — Pizza Preset Shortcode
 *
 * [pizza_preset id="42"]
 *
 * Renders a static, non-interactive pizza display using the layers saved in a
 * preset CPT entry. Output is a pure server-rendered layer stack — no JS, no
 * builder UI.
 *
 * Attributes:
 *   id        (required) Post ID of the pizzatier_presets entry.
 *   template  (optional) Template slug whose template.css will be enqueued for
 *             visual theming of the stage (e.g. template="metro"). Defaults to
 *             the globally active template.
 *   title     (optional) "yes" to display the preset name above the pizza.
 *             Defaults to "no".
 *   size      (optional) Max-width of the display in any CSS unit
 *             (e.g. size="300px", size="50%"). Defaults to "400px".
 *   align     (optional) Horizontal alignment: "left" | "center" | "right".
 *             Defaults to "center".
 *
 * @package PizzaTier\Commerce\Presets
 */

namespace PizzaTier\Commerce\Presets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PresetShortcode {

	/**
	 * Single-select layer config: meta_key => [ type, ACF field name, z-index ]
	 * Field names mirror PizzaBuilder::get_img() exactly.
	 */
	const LAYER_CONFIG = [
		'crust_id'   => [ 'type' => 'crust',   'field' => 'crust_layer_image',   'z' => 100 ],
		'sauce_id'   => [ 'type' => 'sauce',   'field' => 'sauce_layer_image',   'z' => 200 ],
		'cheese_id'  => [ 'type' => 'cheese',  'field' => 'cheese_layer_image',  'z' => 300 ],
		'drizzle_id' => [ 'type' => 'drizzle', 'field' => 'drizzle_layer_image', 'z' => 900 ],
		'cut_id'     => [ 'type' => 'cut',     'field' => 'cut_layer_image',     'z' => 950 ],
	];

	const TOPPING_FIELD   = 'topping_layer_image';
	const TOPPING_Z_START = 410;
	const TOPPING_Z_STEP  = 10;

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_shortcode( 'pizza_preset', [ $this, 'render' ] );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * @param  array|string $atts
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'id'       => '',
				'template' => '',
				'title'    => 'no',
				'size'     => '400px',
				'align'    => 'center',
			],
			$atts,
			'pizza_preset'
		);

		// -- Validate preset --------------------------------------------------

		$preset_id = absint( $atts['id'] );
		if ( ! $preset_id ) {
			return $this->error( __( '[pizza_preset] requires an id attribute.', 'pizzatier' ) );
		}

		$post = get_post( $preset_id );
		if ( ! $post || 'pizzatier_presets' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $this->error(
				/* translators: %d: preset post ID */
				sprintf( __( '[pizza_preset] Preset #%d not found or not published.', 'pizzatier' ), $preset_id )
			);
		}

		// -- Load preset meta -------------------------------------------------

		$meta = get_post_meta( $preset_id, \PizzaTier\Commerce\Admin\Presets::META_KEY, true );
		if ( ! is_array( $meta ) || empty( $meta ) ) {
			return $this->error(
				/* translators: %d: preset post ID */
				sprintf( __( '[pizza_preset] Preset #%d has no layers configured.', 'pizzatier' ), $preset_id )
			);
		}

		// -- Resolve template CSS ---------------------------------------------

		$template_slug = $this->resolve_template( $atts['template'] );

		// -- Build layer HTML -------------------------------------------------

		if ( ! class_exists( 'PizzaTier\\Builder\\LayerRenderer' )
			|| ! class_exists( 'PizzaTier\\Builder\\LayerDTO' ) ) {
			return $this->error( __( '[pizza_preset] PizzaTier plugin is required.', 'pizzatier' ) );
		}

		$renderer    = new \PizzaTier\Builder\LayerRenderer();
		$layers_html = '';

		// Single-select layers
		foreach ( self::LAYER_CONFIG as $meta_key => $cfg ) {
			if ( empty( $meta[ $meta_key ] ) ) {
				continue;
			}
			$layer_post_id = absint( $meta[ $meta_key ] );
			$img_url       = $this->get_layer_image_url( $layer_post_id, $cfg['field'] );
			if ( ! $img_url ) {
				continue;
			}
			$layer_post = get_post( $layer_post_id );
			$slug       = $layer_post ? $layer_post->post_name : (string) $layer_post_id;

			$layers_html .= $renderer->render_closed(
				new \PizzaTier\Builder\LayerDTO( [
					'z_index'   => $cfg['z'],
					'type'      => $cfg['type'],
					'slug'      => $slug,
					'image_url' => $img_url,
				] )
			);
		}

		// Toppings
		if ( ! empty( $meta['topping_ids'] ) && is_array( $meta['topping_ids'] ) ) {
			$z = self::TOPPING_Z_START;
			foreach ( $meta['topping_ids'] as $tid ) {
				$tid = absint( $tid );
				if ( ! $tid ) {
					continue;
				}
				$img_url = $this->get_layer_image_url( $tid, self::TOPPING_FIELD );
				if ( ! $img_url ) {
					continue;
				}
				$layer_post = get_post( $tid );
				$slug       = $layer_post ? $layer_post->post_name : (string) $tid;

				$layers_html .= $renderer->render_closed(
					new \PizzaTier\Builder\LayerDTO( [
						'z_index'   => $z,
						'type'      => 'topping',
						'slug'      => $slug,
						'image_url' => $img_url,
					] )
				);
				$z += self::TOPPING_Z_STEP;
			}
		}

		// -- Assemble output --------------------------------------------------

		$stack_html = '<div class="np-pizza-stage-wrap">'
			. '<div class="np-pizza-stage np-pizza-stage--static">'
			. $layers_html
			. '</div>'
			. '</div>';

		$size       = $this->sanitize_css_size( $atts['size'], '400px' );
		$align      = $this->sanitize_align( $atts['align'] );
		$margin_css = ( 'right' === $align ) ? '0 0 0 auto' : ( ( 'left' === $align ) ? '0 auto 0 0' : '0 auto' );

		$wrapper_style  = 'max-width:' . $size . ';margin:' . $margin_css . ';display:block;';
		$template_class = $template_slug
			? ' pztc-preset--template-' . sanitize_html_class( $template_slug )
			: '';

		ob_start();

		echo '<div class="pztc-preset-display' . esc_attr( $template_class ) . '"'
			. ( $template_slug ? ' data-template="' . esc_attr( $template_slug ) . '"' : '' )
			. ' style="' . esc_attr( $wrapper_style ) . '">';

		if ( 'yes' === strtolower( $atts['title'] ) ) {
			echo '<p class="pztc-preset-display__title"'
				. ' style="text-align:' . esc_attr( $align ) . ';margin:0 0 .5em;font-weight:700">'
				. esc_html( $post->post_title )
				. '</p>';
		}

		// LayerRenderer escapes every attribute at construction; wp_kses() is
		// applied here as the single verifiable escaping call at the point of
		// output. Allowlist is shared with the builder cards and filterable
		// via 'pizzatier_card_kses'.
		echo wp_kses( $stack_html, pzt_card_allowed_html() );

		echo '</div>';

		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve the layer image URL for a given post ID and ACF field name.
	 *
	 * Resolution order (mirrors PizzaBuilder::get_img()):
	 *   1. get_field() — ACF/SCF, handles URL/ID/array return formats
	 *   2. get_post_meta() with the typed field name
	 *   3. get_post_meta() with underscore-prefixed key
	 *   4. Featured image as last resort
	 */
	private function get_layer_image_url( int $post_id, string $field_name ): string {
		if ( ! $post_id ) {
			return '';
		}

		$resolve = function ( $val ): string {
			if ( ! $val ) { return ''; }
			if ( is_array( $val ) ) { return (string) ( $val['url'] ?? '' ); }
			if ( is_numeric( $val ) && (int) $val > 0 ) {
				return (string) ( wp_get_attachment_url( (int) $val ) ?: '' );
			}
			return is_string( $val ) ? $val : '';
		};

		if ( function_exists( 'get_field' ) ) {
			$url = $resolve( get_field( $field_name, $post_id ) );
			if ( $url ) { return $url; }
		}

		$url = $resolve( get_post_meta( $post_id, $field_name, true ) );
		if ( $url ) { return $url; }

		$url = $resolve( get_post_meta( $post_id, '_' . $field_name, true ) );
		if ( $url ) { return $url; }

		$thumb = get_the_post_thumbnail_url( $post_id, 'full' );
		return $thumb ? (string) $thumb : '';
	}

	/**
	 * Resolve and enqueue the template CSS. Returns the slug used.
	 */
	private function resolve_template( string $requested ): string {
		if ( ! class_exists( 'PizzaTier\\Template\\TemplateLoader' ) ) {
			return '';
		}
		$loader = new \PizzaTier\Template\TemplateLoader();
		$slug   = $requested ? sanitize_key( $requested ) : $loader->get_active_slug();

		if ( class_exists( 'PizzaTier\\Assets\\AssetManager' ) ) {
			\PizzaTier\Assets\AssetManager::require_template( $slug );
		}

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			$handle   = 'pizzatier-template-' . $slug;
			$css_file = $loader->get_template_file( 'template.css', $slug );
			if ( ! wp_style_is( $handle, 'enqueued' ) && file_exists( $css_file ) ) {
				wp_enqueue_style(
					$handle,
					$loader->get_template_url( 'template.css', $slug ),
					[ 'pizzatier-css' ],
					defined( 'PIZZATIER_VERSION' ) ? PIZZATIER_VERSION : null
				);
			}
		}

		return $slug;
	}

	/** Sanitize a CSS size value (px, %, em, rem, vw, vh, ch only). */
	private function sanitize_css_size( string $val, string $default ): string {
		$val = trim( $val );
		return preg_match( '/^\d+(\.\d+)?(px|%|em|rem|vw|vh|ch)$/', $val ) ? $val : $default;
	}

	/** Sanitize alignment to left | center | right. */
	private function sanitize_align( string $val ): string {
		$val = strtolower( trim( $val ) );
		return in_array( $val, [ 'left', 'center', 'right' ], true ) ? $val : 'center';
	}

	/** Admin-only error notice for genuine misconfigurations; silent for visitors. */
	private function error( string $message ): string {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="color:#c00;border:1px solid #f5a5a5;padding:8px 12px;'
				. 'border-radius:4px;font-family:monospace;font-size:12px">'
				. esc_html( $message ) . '</p>';
		}
		return '';
	}
}
