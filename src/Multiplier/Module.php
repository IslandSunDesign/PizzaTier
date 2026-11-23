<?php
/**
 * Topping Multiplier — lets customers add more than one portion of the same
 * topping (double, triple, …).
 *
 * Shipped as the separate "PizzaTier Topping Multiplier" add-on before 2.4.0;
 * merged into PizzaTier in 2.4.0. If the add-on is still installed, this
 * module stops it from booting (both copies rewriting the same requests would
 * double-count portions), deactivates it, and carries its settings over.
 *
 * PHP 7.4 compatible.
 *
 * @package PizzaTier\Multiplier
 * @since   2.4.0
 */

namespace PizzaTier\Multiplier;

use PizzaTier\Multiplier\Admin\SettingsPage;
use PizzaTier\Multiplier\Admin\ToppingMetaBox;
use PizzaTier\Multiplier\Frontend\Assets;
use PizzaTier\Multiplier\Frontend\MenuFilter;
use PizzaTier\Multiplier\Pricing\CartLabels;
use PizzaTier\Multiplier\Pricing\NativeOrders;
use PizzaTier\Multiplier\Pricing\SyntheticGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Module {

	/** Notice flag set after the standalone add-on was switched off. */
	const ADDON_NOTICE = 'pizzatier_multiplier_addon_notice';

	/** Settings option and meta keys used by the standalone add-on. */
	const LEGACY_OPTION    = 'pztm_settings';
	const LEGACY_META_KEYS = [ 'mode', 'max', 'pricing', 'price_value', 'level_layers', 'portion_only' ];

	/** @var bool */
	private static $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		self::neutralise_addon();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new ToppingMetaBox() )->register();
			add_action( 'admin_notices', [ __CLASS__, 'addon_notice' ] );
		}

		if ( ! Config::enabled() ) {
			return;
		}

		( new SyntheticGrid() )->register();
		( new CartLabels() )->register();
		( new NativeOrders() )->register();
		( new MenuFilter() )->register();
		( new Assets() )->register();
	}

	// -------------------------------------------------------------------------
	// Standalone add-on hand-over
	// -------------------------------------------------------------------------

	/**
	 * Keep the pre-2.4.0 add-on from booting alongside the merged module, and
	 * deactivate it on the next admin request.
	 */
	private static function neutralise_addon(): void {
		if ( ! defined( 'PZTM_FILE' ) ) {
			return;
		}

		// The add-on boots from an anonymous plugins_loaded:20 callback in its
		// main file. Remove it for this request.
		global $wp_filter;
		if ( isset( $wp_filter['plugins_loaded'] ) && $wp_filter['plugins_loaded'] instanceof \WP_Hook ) {
			$addon_file = wp_normalize_path( (string) PZTM_FILE );
			foreach ( (array) ( $wp_filter['plugins_loaded']->callbacks[20] ?? [] ) as $cb ) {
				if ( ! isset( $cb['function'] ) || ! $cb['function'] instanceof \Closure ) {
					continue;
				}
				try {
					$file = wp_normalize_path( (string) ( new \ReflectionFunction( $cb['function'] ) )->getFileName() );
				} catch ( \ReflectionException $e ) {
					continue;
				}
				if ( $file === $addon_file || realpath( $file ) === realpath( $addon_file ) ) {
					remove_action( 'plugins_loaded', $cb['function'], 20 );
				}
			}
		}

		add_action(
			'admin_init',
			function () {
				self::migrate_addon_data();
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				deactivate_plugins( plugin_basename( (string) PZTM_FILE ), true );
				update_option( self::ADDON_NOTICE, 1, false );
			}
		);
	}

	/**
	 * Copy the add-on's settings and rename its per-topping meta keys.
	 * Idempotent: once the legacy keys are gone there is nothing left to do.
	 * Renaming meta_key values never touches serialised meta_value data.
	 */
	public static function migrate_addon_data(): void {
		global $wpdb;

		$legacy = get_option( self::LEGACY_OPTION, null );
		if ( is_array( $legacy ) && false === get_option( Config::OPTION, false ) ) {
			add_option( Config::OPTION, $legacy );
		}

		foreach ( self::LEGACY_META_KEYS as $suffix ) {
			// One-off key rename during migration from the standalone add-on.
			$wpdb->update( $wpdb->postmeta, [ 'meta_key' => '_pizzatier_mult_' . $suffix ], [ 'meta_key' => '_pztm_' . $suffix ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration rename; there is no API for renaming meta keys in bulk.
		}

		$itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check for an optional WooCommerce table.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $itemmeta ) ) === $itemmeta ) {
			// One-off key rename during migration from the standalone add-on.
			$wpdb->update( $itemmeta, [ 'meta_key' => CartLabels::ORDER_META ], [ 'meta_key' => '_pztm_portions' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration rename; there is no API for renaming meta keys in bulk.
		}

		if ( false !== $legacy ) {
			delete_option( self::LEGACY_OPTION );
		}
		wp_cache_flush_group( 'post_meta' );
		Config::flush();
	}

	public static function addon_notice(): void {
		if ( ! get_option( self::ADDON_NOTICE ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		delete_option( self::ADDON_NOTICE );
		$url = admin_url( 'admin.php?page=' . SettingsPage::SLUG );
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Topping Multiplier is now built into PizzaTier, so the separate “PizzaTier Topping Multiplier” plugin has been deactivated. Your settings were kept — you can delete the old plugin.', 'pizzatier' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open Topping Multiplier settings', 'pizzatier' ); ?></a>
			</p>
		</div>
		<?php
	}
}
