<?php
namespace PizzaTier\Blocks;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registers PizzaTier Gutenberg blocks.
 *
 * Each block is defined in blocks/{name}/block.json (no build step required).
 * The editor UI is plain vanilla JS (block.js).
 * Frontend rendering delegates directly to the existing shortcode classes
 * so there is zero duplication of logic.
 *
 * Requires WordPress 5.8+ (block.json registration API).
 */
class BlockRegistrar {

	/**
	 * Register all blocks via the init hook.
	 * Call: $this->loader->add_action( 'init', $blocks, 'register' );
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress < 5.0 guard
		}

		$blocks_dir = PIZZATIER_BLOCKS_DIR;

		// Pizza Builder — interactive builder
		register_block_type(
			$blocks_dir . 'pizza-builder',
			[ 'render_callback' => [ $this, 'render_builder' ] ]
		);

		// Pizza Static — non-interactive display pizza
		register_block_type(
			$blocks_dir . 'pizza-static',
			[ 'render_callback' => [ $this, 'render_static' ] ]
		);

		// PizzaTier Image — single layer <img>
		register_block_type(
			$blocks_dir . 'pizza-layer',
			[ 'render_callback' => [ $this, 'render_layer' ] ]
		);

		// Register Gutenberg block templates + editor notice on CPT screens.
		add_action( 'init',                          [ $this, 'register_cpt_block_templates' ], 20 );
		add_action( 'enqueue_block_editor_assets',   [ $this, 'enqueue_cpt_editor_assets' ] );
	}

	/**
	 * Set a Gutenberg block template on each PizzaTier CPT so the editor
	 * opens with a helpful, branded layout instead of a blank page.
	 * Priority 20 so it runs after PostTypeRegistrar (priority 0).
	 */
	public function register_cpt_block_templates(): void {
		$cpts = [
			'toppings' => [ 'label' => 'Topping',  'hint' => 'Add a name, optional description, and set a layer image using the sidebar meta box.' ],
			'crusts'   => [ 'label' => 'Crust',    'hint' => 'Add a name and short description. Upload a layer image via the sidebar.' ],
			'sauces'   => [ 'label' => 'Sauce',    'hint' => 'Add a name and short description. Upload a layer image via the sidebar.' ],
			'cheeses'  => [ 'label' => 'Cheese',   'hint' => 'Add a name and short description. Upload a layer image via the sidebar.' ],
			'drizzles' => [ 'label' => 'Drizzle',  'hint' => 'Add a name and short description. Upload a layer image via the sidebar.' ],
			'cuts'     => [ 'label' => 'Cut',       'hint' => 'Add a name and optional description for this slicing style.' ],
			'sizes'    => [ 'label' => 'Size',      'hint' => 'Add a size name (e.g. Large 16"). The slug is used in presets and shortcodes.' ],
			'presets'  => [ 'label' => 'Preset',    'hint' => 'Add a preset name and description. Configure layers in the sidebar meta boxes.' ],
		];

		foreach ( $cpts as $slug => $meta ) {
			$post_type_obj = get_post_type_object( 'pizzatier_' . $slug );
			if ( ! $post_type_obj ) { continue; }

			// Block template: just a paragraph block with instructional placeholder text.
			// Lock the template so the hint stays visible but the user can still type.
			$post_type_obj->template = [
				[
					'core/paragraph',
					[
						'placeholder' => $meta['hint'],
					],
				],
			];
			// Do NOT set template_lock — let the user add more blocks freely.
		}
	}

	/**
	 * Inject a PizzaTier-branded notice into the block editor header area
	 * when editing any PizzaTier CPT. Uses a small inline script that
	 * inserts a notice banner once the editor DOM is ready.
	 */
	public function enqueue_cpt_editor_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) { return; }

		$post_type = $screen->post_type ?? '';
		if ( strpos( $post_type, 'pizzatier_' ) !== 0 ) { return; }

		$slug = str_replace( 'pizzatier_', '', $post_type );

		$type_meta = [
			'toppings' => [ 'label' => 'Topping',  'emoji' => '🍕', 'color' => '#e74c3c', 'hint' => 'Set a name, description, and a layer image in the sidebar.' ],
			'crusts'   => [ 'label' => 'Crust',    'emoji' => '🫓', 'color' => '#e67e22', 'hint' => 'Set a name. Upload a transparent PNG layer image via the sidebar meta box.' ],
			'sauces'   => [ 'label' => 'Sauce',    'emoji' => '🥫', 'color' => '#c0392b', 'hint' => 'Set a name. Upload a transparent PNG layer image via the sidebar meta box.' ],
			'cheeses'  => [ 'label' => 'Cheese',   'emoji' => '🧀', 'color' => '#f39c12', 'hint' => 'Set a name. Upload a transparent PNG layer image via the sidebar meta box.' ],
			'drizzles' => [ 'label' => 'Drizzle',  'emoji' => '💧', 'color' => '#8e44ad', 'hint' => 'Set a name. Upload a transparent PNG layer image via the sidebar meta box.' ],
			'cuts'     => [ 'label' => 'Cut Style','emoji' => '✂️', 'color' => '#2980b9', 'hint' => 'Set a name for this slicing style. Add a layer image if needed.' ],
			'sizes'    => [ 'label' => 'Size',     'emoji' => '📏', 'color' => '#27ae60', 'hint' => 'Set a size name (e.g. Large 16"). The post slug is used in shortcodes.' ],
			'presets'  => [ 'label' => 'Preset',   'emoji' => '⭐', 'color' => '#ff6b35', 'hint' => 'Name this preset. Configure the layers in the sidebar meta boxes.' ],
		];

		if ( ! isset( $type_meta[ $slug ] ) ) { return; }

		$meta  = $type_meta[ $slug ];
		$label = $meta['label'];
		$emoji = $meta['emoji'];
		$color = $meta['color'];
		$hint  = $meta['hint'];

		// Build the pizza SVG as a data URI for the JS to use
		$svg_raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#ff6b35"><path d="M10 1C5.03 1 1 5.03 1 10s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zM10 2.6c3.37 0 6.27 2.08 7.52 5.06L10 10.1 2.48 7.66C3.73 4.68 6.63 2.6 10 2.6zM2.6 10c0-.38.03-.75.09-1.11L10 11.7l7.31-2.81c.06.36.09.73.09 1.11 0 4.08-3.32 7.4-7.4 7.4S2.6 14.08 2.6 10zM7.2 11.8a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2zM12.4 12.6a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2z"/></svg>';

		wp_add_inline_script(
			'wp-blocks',
			'(function(){
				var MAX_TRIES = 40, tries = 0;
				var SLUG  = ' . wp_json_encode( $slug )  . ';
				var LABEL = ' . wp_json_encode( $label ) . ';
				var EMOJI = ' . wp_json_encode( $emoji ) . ';
				var COLOR = ' . wp_json_encode( $color ) . ';
				var HINT  = ' . wp_json_encode( $hint )  . ';
				var SVG   = ' . wp_json_encode( $svg_raw ) . ';

				function injectBanner(){
					// Avoid double injection
					if(document.getElementById("pzl-cpt-editor-banner")) return;

					// Look for the editor canvas area
					var target = document.querySelector(".editor-post-title__block")
					           || document.querySelector(".wp-block-post-title")
					           || document.querySelector("[data-type=\'core/post-title\']")
					           || document.querySelector(".editor-visual-editor");
					if(!target){ tries++; if(tries<MAX_TRIES) setTimeout(injectBanner,250); return; }

					var banner = document.createElement("div");
					banner.id  = "pzl-cpt-editor-banner";
					banner.style.cssText = [
						"display:flex",
						"align-items:center",
						"gap:12px",
						"padding:10px 16px",
						"background:linear-gradient(135deg,#1a1a2e 0%,#2d1b0e 100%)",
						"border-left:4px solid " + COLOR,
						"border-radius:6px",
						"margin-bottom:16px",
						"font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif",
					].join(";");

					banner.innerHTML =
						"<span style=\"font-size:26px;flex-shrink:0;line-height:1\">" + EMOJI + "</span>"
						+ "<div style=\"flex:1\">"
						+   "<div style=\"color:#fff;font-weight:700;font-size:13px;\">"
						+     "<span style=\"color:#ff6b35;\">PizzaTier</span> &mdash; "
						+     LABEL
						+   "</div>"
						+   "<div style=\"color:#aaa;font-size:11px;margin-top:2px\">" + HINT + "</div>"
						+ "</div>"
						+ "<div style=\"flex-shrink:0;opacity:.6\">" + SVG + "</div>";

					// Insert before the title block
					if(target.parentNode){
						target.parentNode.insertBefore(banner, target);
					}
				}

				if(document.readyState === "loading"){
					document.addEventListener("DOMContentLoaded", function(){ setTimeout(injectBanner, 400); });
				} else {
					setTimeout(injectBanner, 400);
				}
			})();',
			'after'
		);
	}

	/* ──────────────────────────────────────────────────────────────
	   RENDER CALLBACKS
	   These map block attributes → shortcode attributes and delegate
	   to the existing shortcode render methods.
	   ────────────────────────────────────────────────────────────── */

	/**
	 * Render callback for pizzatier/pizza-builder.
	 *
	 * Maps block attributes to [pizza_builder] shortcode attributes.
	 * In the block editor REST preview context we return a styled static
	 * placeholder so the editor always shows something useful — the full
	 * template pipeline requires frontend globals that are not available
	 * during a REST render_callback request.
	 *
	 * @param array $atts Block attributes from the editor.
	 * @return string HTML output.
	 */
	public function render_builder( array $atts ): string {

		// Detect block editor / REST preview context. The block-renderer
		// endpoint runs as a REST request, which always defines REST_REQUEST.
		$is_editor_preview = $this->is_rest_request();

		if ( $is_editor_preview ) {
			return $this->editor_placeholder( $atts );
		}

		$shortcode_atts = $this->filter_atts( [
			'id'             => $atts['instanceId']   ?? '',
			'template'       => $atts['template']     ?? '',
			'max_toppings'   => $atts['maxToppings']  ?? '',
			'show_tabs'      => $atts['showTabs']     ?? '',
			'hide_tabs'      => $atts['hideTabs']     ?? '',
			'default_crust'  => $atts['defaultCrust'] ?? '',
			'default_sauce'  => $atts['defaultSauce'] ?? '',
			'default_cheese' => $atts['defaultCheese'] ?? '',
			'pizza_shape'    => $atts['pizzaShape']   ?? '',
			'pizza_aspect'   => $atts['pizzaAspect']  ?? '',
			'pizza_radius'   => $atts['pizzaRadius']  ?? '',
			'layer_anim'     => $atts['layerAnim']    ?? '',
		] );

		$shortcode = new \PizzaTier\Shortcodes\BuilderShortcode();
		return $shortcode->render( $shortcode_atts );
	}

	/**
	 * Returns a branded static HTML preview shown inside the block editor.
	 * This avoids running the full template pipeline (which requires frontend
	 * globals) during a REST render_callback request.
	 *
	 * @param array $atts Block attributes.
	 * @return string HTML.
	 */
	private function editor_placeholder( array $atts ): string {
		$template    = ! empty( $atts['template'] )    ? esc_html( $atts['template'] )    : esc_html__( 'Site default', 'pizzatier' );
		$max_top     = ! empty( $atts['maxToppings'] ) ? esc_html( $atts['maxToppings'] ) : esc_html__( 'Default', 'pizzatier' );
		$shape       = ! empty( $atts['pizzaShape'] )  ? esc_html( $atts['pizzaShape'] )  : esc_html__( 'Default', 'pizzatier' );

		// Visible tabs summary
		$all_tabs    = [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ];
		$hidden_set  = array_filter( array_map( 'trim', explode( ',', $atts['hideTabs'] ?? '' ) ) );
		$shown_tabs  = array_values( array_diff( $all_tabs, $hidden_set ) );
		$tabs_label  = empty( $hidden_set )
			? esc_html__( 'All tabs', 'pizzatier' )
			: implode( ', ', array_map( 'esc_html', $shown_tabs ) );

		// Tab pill HTML
		$tab_pills = '';
		foreach ( $shown_tabs as $tab ) {
			$tab_pills .= '<span style="display:inline-block;background:#2d1b0e;color:#ff8c42;border:1px solid #ff6b35;border-radius:3px;padding:2px 8px;font-size:11px;margin:2px;">' . esc_html( $tab ) . '</span>';
		}

		// Config rows
		$rows = '';
		$cfg = [
			__( 'Template', 'pizzatier' )     => $template,
			__( 'Max toppings', 'pizzatier' ) => $max_top,
			__( 'Shape', 'pizzatier' )        => $shape,
			__( 'Visible tabs', 'pizzatier' ) => $tab_pills ?: $tabs_label,
		];
		foreach ( $cfg as $label => $val ) {
			$rows .= '<tr>'
				. '<td style="padding:5px 10px;color:#aaa;font-size:12px;white-space:nowrap;border-bottom:1px solid #2a2a3e;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:5px 10px;color:#ddd;font-size:12px;border-bottom:1px solid #2a2a3e;">' . $val . '</td>'
				. '</tr>';
		}

		// Pizza SVG icon
		$pizza_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="22" height="22" fill="#ff6b35" style="flex-shrink:0">'
			. '<path d="M10 1C5.03 1 1 5.03 1 10s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z'
			. 'M10 2.6c3.37 0 6.27 2.08 7.52 5.06L10 10.1 2.48 7.66C3.73 4.68 6.63 2.6 10 2.6z'
			. 'M2.6 10c0-.38.03-.75.09-1.11L10 11.7l7.31-2.81c.06.36.09.73.09 1.11 0 4.08-3.32 7.4-7.4 7.4S2.6 14.08 2.6 10z'
			. 'M7.2 11.8a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2z'
			. 'M12.4 12.6a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2z"/>'
			. '</svg>';

		return '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;border-radius:6px;overflow:hidden;border:2px solid #ff6b35;">'

			// Header bar
			. '<div style="background:linear-gradient(135deg,#1a1a2e 0%,#2d1b0e 100%);padding:12px 16px;display:flex;align-items:center;gap:10px;">'
			.   $pizza_svg
			.   '<div>'
			.     '<div style="color:#fff;font-weight:700;font-size:14px;letter-spacing:.02em;">PizzaTier &mdash; ' . esc_html__( 'Pizza Builder', 'pizzatier' ) . '</div>'
			.     '<div style="color:#ff8c42;font-size:11px;">' . esc_html__( 'Interactive pizza builder · renders on the front end', 'pizzatier' ) . '</div>'
			.   '</div>'
			. '</div>'

			// Config table
			. '<div style="background:#12121f;">'
			.   '<table style="width:100%;border-collapse:collapse;">' . $rows . '</table>'
			. '</div>'

			// Tab pills row
			. '<div style="background:#0e0e1a;padding:10px 14px;border-top:1px solid #2a2a3e;">'
			.   '<span style="color:#888;font-size:11px;margin-right:6px;">' . esc_html__( 'Tabs:', 'pizzatier' ) . '</span>'
			.   $tab_pills
			. '</div>'

			// Hint footer
			. '<div style="background:#0a0a14;padding:8px 14px;text-align:center;">'
			.   '<span style="color:#555;font-size:11px;">⚙ ' . esc_html__( 'Configure this block in the sidebar →', 'pizzatier' ) . '</span>'
			. '</div>'

		. '</div>';
	}

	/**
	 * Render callback for pizzatier/pizza-static.
	 *
	 * @param array $atts Block attributes.
	 * @return string HTML output.
	 */
	public function render_static( array $atts ): string {
		$shortcode_atts = $this->filter_atts( [
			'preset'   => $atts['preset']   ?? '',
			'crust'    => $atts['crust']    ?? '',
			'sauce'    => $atts['sauce']    ?? '',
			'cheese'   => $atts['cheese']   ?? '',
			'toppings' => $atts['toppings'] ?? '',
			'drizzle'  => $atts['drizzle']  ?? '',
			'cut'      => $atts['cut']      ?? '',
		] );

		$shortcode = new \PizzaTier\Shortcodes\StaticShortcode();
		return $shortcode->render( $shortcode_atts );
	}

	/**
	 * Render callback for pizzatier/pizza-layer.
	 *
	 * @param array $atts Block attributes.
	 * @return string HTML output.
	 */
	public function render_layer( array $atts ): string {
		$shortcode_atts = $this->filter_atts( [
			'type'  => $atts['layerType']  ?? 'crust',
			'slug'  => $atts['slug']       ?? '',
			'image' => $atts['imageField'] ?? 'list',
			'class' => $atts['cssClass']   ?? '',
		] );

		if ( empty( $shortcode_atts['slug'] ) ) {
			if ( is_admin() || $this->is_rest_request() ) {
				return '<p style="color:#999;font-style:italic;font-size:13px;margin:0;">'
				     . esc_html__( 'Enter a layer slug in the block settings.', 'pizzatier' )
				     . '</p>';
			}
			return '';
		}

		$shortcode = new \PizzaTier\Shortcodes\LayerImageShortcode();
		return $shortcode->render( $shortcode_atts );
	}

	/* ──────────────────────────────────────────────────────────────
	   HELPERS
	   ────────────────────────────────────────────────────────────── */

	/**
	 * Whether the current request is being served as a REST API request.
	 *
	 * Server-side blocks are previewed in the editor through the REST
	 * block-renderer endpoint, which sets the REST_REQUEST constant for the
	 * duration of the request. Relying on the constant keeps the plugin
	 * compatible with its declared minimum WordPress version —
	 * wp_is_serving_rest_request() only exists since WP 6.5.
	 *
	 * @return bool True when handling a REST request.
	 */
	private function is_rest_request(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Strip empty-string values so shortcode_atts() defaults kick in.
	 *
	 * @param array $atts Raw attribute map.
	 * @return array Filtered map (empty strings preserved — shortcodes handle them).
	 */
	private function filter_atts( array $atts ): array {
		return array_map( 'strval', $atts );
	}
}
