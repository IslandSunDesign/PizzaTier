<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Activator {
	public static function activate(): void {
		// Set defaults on first activation. Whether the template option existed
		// beforehand is the signal for "fresh install" used further down.
		$is_fresh_install = ( false === get_option( 'pizzatier_setting_global_template' ) );
		if ( $is_fresh_install ) {
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
		// from a site coming up from an older release. Only on a genuinely
		// fresh install: a site from before version tracking has settings but
		// no version option, and recording the current version there would
		// skip every upgrade step it still needs.
		if ( $is_fresh_install && false === get_option( Upgrade::VERSION_OPTION, false ) ) {
			add_option( Upgrade::VERSION_OPTION, PIZZATIER_VERSION, '', false );
		}

		flush_rewrite_rules();
	}
}
