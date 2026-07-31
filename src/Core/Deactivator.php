<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Deactivator {
	public static function deactivate(): void {
		flush_rewrite_rules();

		// Leave no orphaned cron entry behind. The retention sweep reschedules
		// itself on init when the plugin is reactivated and retention is on.
		$scheduled = wp_next_scheduled( \PizzaTier\Orders\Privacy::CRON_HOOK );
		while ( $scheduled ) {
			wp_unschedule_event( $scheduled, \PizzaTier\Orders\Privacy::CRON_HOOK );
			$scheduled = wp_next_scheduled( \PizzaTier\Orders\Privacy::CRON_HOOK );
		}
	}
}
