<?php
/**
 * Scaffold Template — template.css loader.
 *
 * Intentionally minimal: provides reset + layout skeleton only.
 * All visual design is your responsibility.
 *
 * NOTE: On the front end the plugin's AssetManager already enqueues this
 * template's template.css under the canonical handle
 * 'pizzatier-template-scaffold' (and the per-instance CSS custom properties
 * are attached to that handle). This file therefore only enqueues a fallback
 * copy when that canonical handle is NOT present, to avoid loading the same
 * stylesheet twice.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.

if ( ! wp_style_is( 'pizzatier-template-scaffold', 'enqueued' )
	&& ! wp_style_is( 'pizzatier-template-scaffold', 'registered' ) ) {
	$css_url = PIZZATIER_TEMPLATES_URL . 'scaffold/template.css';
	wp_enqueue_style( 'pztp-scaffold', $css_url, [], PIZZATIER_VERSION );
}
