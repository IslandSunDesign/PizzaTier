<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-template-css_start' );

/**
 * Plainlist Template — CSS registration.
 * The dynamic CSS vars are injected by pztp-template-custom.php via wp_head.
 * This file registers the static stylesheet.
 */

function pizzatier_template_plainlist_generated_css() {
	// All dynamic overrides are handled via CSS custom properties in pztp-template-custom.php
	return '';
}

do_action( 'pizzatier_file_pztp-template-css_end' );
