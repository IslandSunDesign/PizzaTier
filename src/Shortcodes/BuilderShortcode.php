<?php
namespace PizzaTier\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [pizza_builder] shortcode — renders the interactive pizza builder.
 *
 * The template file receives these variables:
 *   $instance_id      — unique string ID for this builder instance
 *   $atts             — sanitised shortcode attribute array
 *   $template_slug    — active template slug (e.g. 'colorbox')
 *   $function_prefix  — safe PHP function prefix for this template (e.g. 'pzt_colorbox')
 *
 * Attributes:
 *   id              Unique instance ID. Auto-generated if omitted.
 *   template        Template slug override.
 *   max_toppings    Override global max topping count.
 *   show_tabs       Comma-separated list of tabs to show (default: all).
 *   hide_tabs       Comma-separated list of tabs to hide.
 *   default_crust   Pre-select a crust slug on load.
 *   default_sauce   Pre-select a sauce slug on load.
 *   default_cheese  Pre-select a cheese slug on load.
 *   pizza_shape     Shape of the pizza: round | square | rectangle | custom.
 *   pizza_aspect    CSS aspect-ratio override (used with rectangle/custom shapes).
 *   pizza_radius    CSS border-radius override (used with custom shape).
 *   layer_anim      Layer add animation: fade | scale-in | slide-up | flip-in | drop-in | instant.
 *   layer_anim_speed  Animation duration in ms (80–800). Overrides global setting.
 */
class BuilderShortcode {

	/** Auto-increment for instances that don't provide an id. */
	private static int $counter = 0;

	public function render( $atts ): string {
		// Record that the builder has rendered live at least once (front-end only),
		// so the Setup Guide can auto-complete its "view your builder" step. Guarded
		// by a get_option check so it only ever writes once.
		if ( ! is_admin() && ! get_option( 'pizzatier_builder_viewed' ) ) {
			update_option( 'pizzatier_builder_viewed', '1' );
		}

		$atts = apply_filters( 'pizzatier_builder_atts', shortcode_atts( [
			'id'               => '',
			'template'         => '',
			'max_toppings'     => '',
			'show_tabs'        => '',
			'hide_tabs'        => '',
			'default_crust'    => '',
			'default_sauce'    => '',
			'default_cheese'   => '',
			'default_toppings' => '',
			'default_drizzle'  => '',
			'default_cut'      => '',
			'restrict'         => '',
			'pizza_shape'      => '',
			'pizza_aspect'     => '',
			'pizza_radius'     => '',
			'layer_anim'       => '',
			'layer_anim_speed' => '',
		], $atts, 'pizza_builder' ) );

		// Ensure a unique instance ID
		if ( $atts['id'] === '' ) {
			self::$counter++;
			$atts['id'] = 'pizzabuilder-' . self::$counter;
		}
		$instance_id = sanitize_html_class( $atts['id'] );

		// ── Resolve global-setting fallbacks ─────────────────────────────
		// shortcode_atts() defaults these attributes to '' (never null), so
		// the templates' `$atts['pizza_shape'] ?? get_option(...)` null
		// coalescing could never reach the global option — the saved Pizza
		// Shape settings silently never applied. Resolve them here instead,
		// in one place, so every template (and the Gutenberg block, which
		// routes through this class) receives the saved global values.
		if ( $atts['pizza_shape'] === '' ) {
			$atts['pizza_shape'] = sanitize_key( (string) get_option( 'pizzatier_setting_pizza_shape', 'round' ) );
		}
		if ( $atts['pizza_aspect'] === '' ) {
			$atts['pizza_aspect'] = sanitize_text_field( (string) get_option( 'pizzatier_setting_pizza_aspect', '' ) );
		}
		if ( $atts['pizza_radius'] === '' ) {
			$atts['pizza_radius'] = sanitize_text_field( (string) get_option( 'pizzatier_setting_pizza_radius', '' ) );
		}
		if ( $atts['layer_anim'] === '' ) {
			$atts['layer_anim'] = sanitize_key( (string) get_option( 'pizzatier_setting_layer_anim', 'fade' ) );
		}

		// Resolve template
		$loader = new \PizzaTier\Template\TemplateLoader();
		$template_slug = $atts['template'] ? sanitize_key( $atts['template'] ) : $loader->get_active_slug();

		// Register this template's assets so AssetManager enqueues them on this page.
		// This handles the case where template="" differs from the global active template.
		\PizzaTier\Assets\AssetManager::require_template( $template_slug );

		$menu_file = $loader->get_template_file( 'pztp-containers-menu.php', $template_slug );
		if ( ! file_exists( $menu_file ) ) {
			return '<p class="pizzatier-error">' . esc_html__( 'PizzaTier: template not found.', 'pizzatier' ) . '</p>';
		}

		// Provide the function prefix so templates can guard against re-declaration
		$function_prefix = $loader->get_function_prefix( $template_slug );

		do_action( 'pizzatier_before_builder', $instance_id, $atts );

		$html = $this->render_template( $menu_file, $instance_id, $atts, $template_slug, $function_prefix );

		do_action( 'pizzatier_after_builder', $instance_id, $atts );

		// Wrap with global chrome (announcement bar + help panel) — these are
		// driven by the "Plugin Settings" section and must work on every
		// template, so they're applied here rather than inside each template.
		return $this->wrap_global_chrome( $html );
	}

	/**
	 * Prepend the Demo/Announcement bar and append the Help panel around the
	 * rendered builder HTML. Both come from the Settings → Plugin Settings
	 * section and apply identically to all templates. The announcement bar is
	 * rendered once per page even when multiple builders are present.
	 *
	 * @param string $html Rendered builder markup.
	 * @return string
	 */
	private function wrap_global_chrome( string $html ): string {
		static $announce_rendered = false;

		$notice = trim( (string) get_option( 'pizzatier_setting_settings_demonotice', '' ) );
		$help   = trim( (string) get_option( 'pizzatier_setting_global_help_content', '' ) );

		$before = '';
		if ( $notice !== '' && ! $announce_rendered ) {
			$announce_rendered = true;
			$before = '<div class="pzl-announce" role="status">'
				. '<span class="pzl-announce__icon" aria-hidden="true">📣</span> '
				. esc_html( $notice )
				. '</div>';
		}

		$after = '';
		if ( $help !== '' ) {
			$after = '<details class="pzl-help">'
				. '<summary class="pzl-help__summary">'
				. '<span class="pzl-help__icon" aria-hidden="true">?</span> '
				. esc_html__( 'Need help?', 'pizzatier' )
				. '</summary>'
				. '<div class="pzl-help__body">' . wp_kses_post( wpautop( $help ) ) . '</div>'
				. '</details>';
		}

		if ( $before === '' && $after === '' ) {
			return $html;
		}
		return $before . $html . $after;
	}

	/**
	 * Isolate the template include in its own method scope.
	 * This prevents variables defined in render() from leaking into the template
	 * file's local scope beyond the four we intentionally expose.
	 */
	private function render_template(
		string $menu_file,
		string $instance_id,
		array  $atts,
		string $template_slug,
		string $function_prefix
	): string {
		ob_start();
		include $menu_file;
		return (string) ob_get_clean();
	}
}
