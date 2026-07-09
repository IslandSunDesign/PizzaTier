<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Customizer.php — stub only.
 *
 * PizzaTier no longer registers a WordPress Customizer panel.
 * All settings have been moved to the native Settings page.
 * This class exists only to avoid autoloader errors from old references.
 */
class Customizer {
	public function render(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=pizzatier-settings' ) );
		exit;
	}
}
