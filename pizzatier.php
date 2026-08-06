<?php
/**
 * Plugin Name: PizzaTier
 * Plugin URI:  https://pizzatier.com
 * Description: Pizza toppings customizer and visualizer.
 * Version:     2.2.1
 * Author:      Island Sun Design
 * Author URI:  https://islandsundesign.com
 * Requires at least: 6.2
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * License:     GPLv2 or later
 * Text Domain: pizzatier
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Autoloader (PSR-4: PizzaTier\ → src/). The feature set formerly shipped as
// the separate PizzaTier plugin lives under PizzaTier\Commerce\ → src/Commerce/.
spl_autoload_register( function ( $class ) {
	$prefix   = 'PizzaTier\\';
	$base_dir = __DIR__ . '/src/';
	$len      = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) { return; }
	$relative = substr( $class, $len );
	$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $file ) ) {
		require $file;
		return;
	}

	// A PizzaTier class was requested but its file is absent or unreadable --
	// almost always a partial upload, or a stale realpath/opcache entry after
	// an in-place update. Returning silently here turns that into a bare
	// "Class not found" fatal with no clue as to the cause, so record it.
	if ( ! isset( $GLOBALS['pizzatier_autoload_misses'] ) ) {
		$GLOBALS['pizzatier_autoload_misses'] = array();
	}
	$GLOBALS['pizzatier_autoload_misses'][ $class ] = $file;

	error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Install-integrity failure; must be recorded even with WP_DEBUG off.
		sprintf(
			'[PizzaTier] Autoload failed for %1$s. Expected %2$s (%3$s). Re-extract the plugin zip server-side.',
			$class,
			$file,
			file_exists( $file ) ? 'exists but is not readable by PHP' : 'does not exist'
		)
	);
} );

// Constants
define( 'PIZZATIER_VERSION',       '2.2.1' );
define( 'PIZZATIER_PLUGIN_FILE',   __FILE__ );
define( 'PIZZATIER_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'PIZZATIER_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'PIZZATIER_TEMPLATES_DIR', PIZZATIER_PLUGIN_DIR . 'templates/' );
define( 'PIZZATIER_TEMPLATES_URL', PIZZATIER_PLUGIN_URL . 'templates/' );
define( 'PIZZATIER_ASSETS_URL',    PIZZATIER_PLUGIN_URL . 'assets/' );
define( 'PIZZATIER_IMAGES_URL',    PIZZATIER_PLUGIN_URL . 'assets/images/' );
define( 'PIZZATIER_BLOCKS_DIR',    PIZZATIER_PLUGIN_DIR . 'blocks/' );


/**
 * Returns the array of enabled topping coverage fractions from settings.
 * Always includes 'whole'. Handles legacy single-value migration.
 *
 * @return string[]
 */
if ( ! function_exists( 'pz_get_enabled_fractions' ) ) {
	function pz_get_enabled_fractions(): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Uses the plugin-owned pz_ prefix; function_exists()-guarded above.
		$saved = get_option( 'pizzatier_setting_topping_fractions', [] );
		if ( ! is_array( $saved ) ) {
			// Migrate legacy string values
			$lv    = (string) $saved;
			$saved = [ 'whole' ];
			if ( $lv === 'halves' || $lv === 'quarters' ) {
				$saved[] = 'half-left';
				$saved[] = 'half-right';
			}
			if ( $lv === 'quarters' ) {
				$saved[] = 'quarter-top-left';
				$saved[] = 'quarter-top-right';
				$saved[] = 'quarter-bottom-left';
				$saved[] = 'quarter-bottom-right';
			}
		}
		if ( empty( $saved ) ) {
			return [ 'whole', 'half-left', 'half-right', 'quarter-top-left', 'quarter-top-right', 'quarter-bottom-left', 'quarter-bottom-right' ];
		}
		if ( ! in_array( 'whole', $saved, true ) ) {
			array_unshift( $saved, 'whole' );
		}
		return $saved;
	}
}

/**
 * Safe SCF/ACF field accessor with a raw post-meta fallback.
 *
 * When SCF/ACF is active, delegates to get_field() so the configured return
 * format is preserved. When neither is active, falls back to post meta so the
 * front-end templates and the [pizza_layer_info] shortcode degrade gracefully
 * instead of fataling on an undefined get_field(). For image field keys
 * (those ending in "_image") a stored attachment ID is resolved to its URL,
 * matching what the layer-image meta box writes.
 *
 * @param string $field   Field / meta key.
 * @param int    $post_id Post ID.
 * @return mixed          Field value (string|array|int) or '' when unset.
 */
if ( ! function_exists( 'pzl_get_field' ) ) {
	function pzl_get_field( $field, $post_id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Uses the plugin-owned pzl_ prefix; this is the shared ACF/SCF safe accessor, function_exists()-guarded above.
		if ( function_exists( 'get_field' ) ) {
			return get_field( $field, $post_id );
		}

		$field = (string) $field;
		$value = get_post_meta( (int) $post_id, $field, true );
		if ( '' === $value || null === $value ) {
			return '';
		}

		// Image fields store an attachment ID (or an array) — resolve to a URL
		// so templates that expect a URL string keep working without SCF/ACF.
		if ( '_image' === substr( $field, -6 ) ) {
			if ( is_array( $value ) ) {
				return isset( $value['url'] ) ? (string) $value['url'] : '';
			}
			if ( is_numeric( $value ) ) {
				$url = wp_get_attachment_url( (int) $value );
				return $url ? $url : '';
			}
		}

		return $value;
	}
}

/**
 * Escape PizzaTier builder HTML at the shortcode/block output boundary.
 *
 * The static builder emits a fixed set of <div>/<img> layer markup whose
 * attributes are already escaped at construction. Wrapping the returned string
 * in wp_kses() guarantees the output boundary is escaped even when third parties
 * (e.g. PizzaTier) inject markup through the pizzatier_layer_html /
 * pizzatier_static_layers filters. The allowlist is filterable via
 * 'pizzatier_builder_kses' so add-ons can permit any extra tags/attributes
 * they legitimately render.
 *
 * @param string $html Raw builder HTML.
 * @return string      Escaped HTML safe to return/echo.
 */
if ( ! function_exists( 'pzl_kses_builder_html' ) ) {
	function pzl_kses_builder_html( $html ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pzl_ prefix; function_exists()-guarded above.
		$common = [
			'class'           => true,
			'style'           => true,
			'id'              => true,
			'title'           => true,
			'data-layer-id'   => true,
			'data-layer-type' => true,
			'data-layer-slug' => true,
		];

		$allowed = [
			'div'  => $common,
			'span' => $common,
			'p'    => $common,
			'img'  => array_merge(
				$common,
				[
					'src'      => true,
					'alt'      => true,
					'srcset'   => true,
					'sizes'    => true,
					'width'    => true,
					'height'   => true,
					'loading'  => true,
					'decoding' => true,
				]
			),
		];

		/**
		 * Filter the allowed HTML used to escape static builder output.
		 *
		 * @param array $allowed Allowed HTML map passed to wp_kses().
		 */
		$allowed = apply_filters( 'pizzatier_builder_kses', $allowed );

		return wp_kses( (string) $html, $allowed );
	}
}

/**
 * Allowlist of HTML permitted in INTERACTIVE builder markup, for use with
 * wp_kses() at the output boundary.
 *
 * The template card grids (pztp-containers-menu.php) assemble their HTML from
 * per-field esc_attr()/esc_html()/esc_url() calls, so the strings are already
 * safe by construction. Call sites pass them through wp_kses() with this
 * allowlist to add a single, verifiable WordPress-core escaping call at the
 * point of output ("escape late"). The allowlist covers exactly the
 * tags/attributes the interactive builder emits — buttons, form controls, inline
 * SVG icons, ARIA state, data-* hooks, and the onclick/onkeydown handlers the
 * cards use to talk to the front-end controller.
 *
 * Unlike pzl_kses_builder_html() (used for the non-interactive [pizza_static]
 * output), this allowlist intentionally permits interactive elements. It never
 * permits <script>, <style>, <iframe>, <object> or <form>, so those are stripped
 * even if they somehow reach the output.
 *
 * Note on data-* attributes: WordPress KSES only passes arbitrary data-*
 * attributes when the element's attribute map contains the literal 'data-*'
 * key, which $common provides; individual data-* names never need enumerating.
 *
 * The allowlist is filterable via 'pizzatier_card_kses' so
 * third-party templates can permit any extra markup they legitimately render.
 *
 * The result is cached per request.
 *
 * @return array Allowed HTML map for wp_kses().
 */
if ( ! function_exists( 'pzt_card_allowed_html' ) ) {
	function pzt_card_allowed_html() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pzt_ prefix; function_exists()-guarded above.
		static $allowed_cache = null;
		if ( null !== $allowed_cache ) {
			return $allowed_cache;
		}
		$common = [
			'class'            => true,
			'id'               => true,
			'style'            => true,
			'title'            => true,
			'hidden'           => true,
			'role'             => true,
			'tabindex'         => true,
			'data-*'           => true,
			'aria-checked'     => true,
			'aria-controls'    => true,
			'aria-current'     => true,
			'aria-describedby' => true,
			'aria-disabled'    => true,
			'aria-expanded'    => true,
			'aria-haspopup'    => true,
			'aria-hidden'      => true,
			'aria-label'       => true,
			'aria-labelledby'  => true,
			'aria-live'        => true,
			'aria-modal'       => true,
			'aria-pressed'     => true,
			'aria-selected'    => true,
			'onclick'          => true,
			'onkeydown'        => true,
		];

		$img = array_merge( $common, [
			'src'      => true,
			'alt'      => true,
			'srcset'   => true,
			'sizes'    => true,
			'width'    => true,
			'height'   => true,
			'loading'  => true,
			'decoding' => true,
		] );

		// Attributes shared by inline-SVG icon elements.
		$svg_common = [
			'class'             => true,
			'style'             => true,
			'fill'              => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-dasharray'  => true,
			'aria-hidden'       => true,
			'focusable'         => true,
		];

		$allowed = [
			'div'      => $common,
			'span'     => $common,
			'p'        => $common,
			'a'        => array_merge( $common, [ 'href' => true, 'target' => true, 'rel' => true ] ),
			'em'       => $common,
			'strong'   => $common,
			'small'    => $common,
			'h2'       => $common,
			'h3'       => $common,
			'h4'       => $common,
			'i'        => $common,
			'b'        => $common,
			'br'       => [],
			'hr'       => $common,
			'ul'       => $common,
			'ol'       => $common,
			'li'       => $common,
			'nav'      => $common,
			'aside'    => $common,
			'section'  => $common,
			'header'   => $common,
			'footer'   => $common,
			'figure'   => $common,
			'figcaption' => $common,
			'img'      => $img,
			'label'    => array_merge( $common, [ 'for' => true ] ),
			'button'   => array_merge( $common, [ 'type' => true, 'value' => true, 'name' => true, 'disabled' => true ] ),
			'input'    => array_merge( $common, [
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'placeholder' => true,
				'maxlength'   => true,
				'min'         => true,
				'max'         => true,
				'step'        => true,
				'checked'     => true,
				'disabled'    => true,
				'readonly'    => true,
			] ),
			'textarea' => array_merge( $common, [ 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'maxlength' => true ] ),
			'select'   => array_merge( $common, [ 'name' => true ] ),
			'option'   => array_merge( $common, [ 'value' => true, 'selected' => true ] ),
			'svg'      => array_merge( $svg_common, [ 'viewbox' => true, 'width' => true, 'height' => true, 'xmlns' => true, 'role' => true ] ),
			'g'        => $svg_common,
			'path'     => array_merge( $svg_common, [ 'd' => true ] ),
			'polyline' => array_merge( $svg_common, [ 'points' => true ] ),
			'polygon'  => array_merge( $svg_common, [ 'points' => true ] ),
			'line'     => array_merge( $svg_common, [ 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ] ),
			'rect'     => array_merge( $svg_common, [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ] ),
			'circle'   => array_merge( $svg_common, [ 'cx' => true, 'cy' => true, 'r' => true ] ),
		];

		/**
		 * Filter the allowed HTML used to escape interactive builder card output.
		 *
		 * @param array $allowed Allowed HTML map passed to wp_kses().
		 */
		$allowed_cache = apply_filters( 'pizzatier_card_kses', $allowed );

		return $allowed_cache;
	}
}

/**
 * Read a setting owned by a PizzaTier add-on (pricing, cart, size grids).
 *
 * Templates call this instead of naming any premium function directly, so a
 * template file can be included on a site with no extension installed and will
 * simply fall back to the supplied default. See PizzaTier\Compat\AddonBridge.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value to use when no add-on answers.
 * @return mixed
 */
if ( ! function_exists( 'pzt_addon_setting' ) ) {
	function pzt_addon_setting( $key, $default = null ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pzt_ prefix; function_exists()-guarded above.
		return \PizzaTier\Compat\AddonBridge::get_setting( (string) $key, $default );
	}
}

/**
 * Priced size options supplied by an add-on. Returns [] when none is active.
 *
 * @param int $product_id Optional product context.
 * @return array
 */
if ( ! function_exists( 'pzt_addon_sizes' ) ) {
	function pzt_addon_sizes( $product_id = 0 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pzt_ prefix; function_exists()-guarded above.
		return \PizzaTier\Compat\AddonBridge::get_sizes( (int) $product_id );
	}
}

/**
 * Whether a pricing add-on is active. Used for upgrade prompts and optional
 * pricing UI only — no PizzaTier behaviour depends on this being true.
 *
 * @return bool
 */
if ( ! function_exists( 'pzt_has_pricing_addon' ) ) {
	function pzt_has_pricing_addon() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pzt_ prefix; function_exists()-guarded above.
		return \PizzaTier\Compat\AddonBridge::has_pricing_addon();
	}
}

/**
 * Read one of PizzaTier's array-stored options.
 *
 * PizzaTier keeps two settings stores, for historical reasons:
 *
 *   • ~200 discrete `pizzatier_setting_*` options — the builder, templates,
 *     layers, typography and so on.
 *   • One array option, `pizzatier_options`, holding pricing, cart, checkout,
 *     nutrition and order-email settings. This is the store that arrived with
 *     the PizzaTier merge, where it was called `pizzatier_settings`.
 *
 * This accessor reads the second. The name is deliberately distinct from the
 * `pizzatier_setting_*` prefix so the two stores cannot be confused at a
 * glance.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value when the key is unset.
 * @return mixed
 */
if ( ! function_exists( 'pizzatier_get_option' ) ) {
	function pizzatier_get_option( string $key, $default = null ) {
		$settings = get_option( 'pizzatier_options', [] );
		return is_array( $settings ) && isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}

/**
 * Deprecated. Use pizzatier_get_option().
 *
 * PizzaTier exposed this as a public helper, so third-party snippets and
 * child-theme code may call it. It forwards to the new accessor and will be
 * removed in a future release. Nothing inside PizzaTier calls it.
 *
 * @deprecated 2.0.0 Use pizzatier_get_option().
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value when the key is unset.
 * @return mixed
 */
if ( ! function_exists( 'pizzatier_commerce_get_setting' ) ) {
	function pizzatier_commerce_get_setting( string $key, $default = null ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Plugin-owned pizzatier_commerce_ prefix, retained only as a deprecated shim for the former PizzaTier public helper; function_exists()-guarded above.
		if ( function_exists( '_deprecated_function' ) ) {
			_deprecated_function( __FUNCTION__, '2.0.0', 'pizzatier_get_option()' );
		}
		return pizzatier_get_option( $key, $default );
	}
}


// Boot
add_action( 'plugins_loaded', [ 'PizzaTier\\Plugin', 'init' ] );

/*
 * Translations: no load_plugin_textdomain() call here on purpose.
 * WordPress.org-hosted plugins get language packs built by
 * translate.wordpress.org and loaded automatically since WP 4.6, and
 * Plugin Check flags a manual call as discouraged. The /languages
 * catalogues remain in the repo as the translation source (.pot) and
 * for import into translate.wordpress.org.
 */

register_activation_hook(   __FILE__, [ 'PizzaTier\\Core\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'PizzaTier\\Core\\Deactivator', 'deactivate' ] );
