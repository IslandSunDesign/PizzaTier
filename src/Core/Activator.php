<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Activator {
	public static function activate(): void {
		// Set defaults on first activation
		if ( false === get_option( 'pizzatier_setting_global_template' ) ) {
			update_option( 'pizzatier_setting_global_template', 'nightpie' );
		}
		if ( false === get_option( 'pizzatier_setting_topping_maxtoppings' ) ) {
			update_option( 'pizzatier_setting_topping_maxtoppings', 10 );
		}
		flush_rewrite_rules();
	}
}
