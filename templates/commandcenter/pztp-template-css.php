<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
$css_url = PIZZATIER_TEMPLATES_URL . 'commandcenter/template.css';
wp_enqueue_style( 'pizzatier-template-commandcenter', $css_url, [ 'pizzatier-css' ], '1.1.0' );
