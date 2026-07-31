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

		// ── Orders ────────────────────────────────────────────────────────
		// Seed the order-number sequence so numbering starts at 1 and survives
		// deactivate/reactivate cycles without restarting.
		if ( false === get_option( \PizzaTier\Orders\Order::OPTION_SEQUENCE ) ) {
			add_option( \PizzaTier\Orders\Order::OPTION_SEQUENCE, 0, '', false );
		}

		// Register statuses and the order CPT during activation too — the
		// `init` hooks have already fired by the time activation runs, and
		// flush_rewrite_rules() needs the post type present.
		( new \PizzaTier\Orders\OrderStatuses() )->register();
		( new \PizzaTier\Orders\OrderPostType() )->register();

		// Record the version so the upgrade routine can tell a fresh install
		// from a site coming up from an older release.
		if ( false === get_option( Upgrade::VERSION_OPTION, false ) ) {
			add_option( Upgrade::VERSION_OPTION, PIZZATIER_VERSION, '', false );
		}

		flush_rewrite_rules();
	}
}
