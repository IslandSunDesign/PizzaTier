<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
do_action( 'pizzatier_file_pztp-template-css_start' );

function pizzatier_template_metro_generated_css() {
	// No dynamic CSS needed — all styles are in template.css
	return '';
}

do_action( 'pizzatier_file_pztp-template-css_end' );
