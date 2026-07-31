<?php
namespace PizzaTier;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Main plugin singleton. Wires all subsystems via the Loader.
 */
final class Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @var Core\Loader */
	private $loader;

	private function __construct() {
		$this->loader = new Core\Loader();
		$this->register_services();
	}

	/**
	 * Boot the plugin (called on plugins_loaded).
	 */
	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->loader->run();
		}
	}

	/**
	 * Register all plugin services with the loader.
	 */
	private function register_services(): void {

		// Text domain is auto-loaded by WordPress (just-in-time, since 4.6;
		// plugin requires 6.2). Bundled /languages files load via Domain Path.

		// Upgrade steps for a version change. Registered before anything else
		// so a step runs ahead of the code that assumes it has.
		( new Core\Upgrade() )->register();

		// CPTs
		$cpt = new PostTypes\PostTypeRegistrar();
		$this->loader->add_action( 'init', $cpt, 'register', 0 );

		// Orders — native order capture, independent of PizzaTier.
		// Statuses must register before the CPT so wp_count_posts() and the
		// list-table status views resolve them on the same request.
		$order_statuses = new Orders\OrderStatuses();
		$this->loader->add_action( 'init', $order_statuses, 'register', 0 );

		$order_cpt = new Orders\OrderPostType();
		$this->loader->add_action( 'init', $order_cpt, 'register', 1 );

		// Personal-data export / erasure, suggested privacy-policy text and the
		// retention sweep. Registered unconditionally: a site that has switched
		// ordering off may still hold orders taken while it was on, and those
		// must remain reachable by a subject access request.
		( new Orders\Privacy() )->register();

		// Front-end order capture. The checkout renderer registers its own
		// hooks (action bar, assets, footer panel); the submission handler
		// registers the AJAX endpoints for both logged-in users and guests.
		if ( Orders\OrderCheckout::is_enabled() ) {
			( new Orders\OrderCheckout() )->register();
			( new Orders\OrderSubmission() )->register();

			// Prices native order records from the commerce grid. Registers
			// itself only when WooCommerce and the calculator are both present,
			// and leaves items unpriced rather than failing when they are not.
			( new Orders\OrderPricing() )->register();
		}

		// Assets
		$assets = new Assets\AssetManager();
		$this->loader->add_action( 'wp_enqueue_scripts',          $assets, 'enqueue_frontend' );
		$this->loader->add_action( 'enqueue_block_editor_assets', $assets, 'enqueue_block_editor' );
		$this->loader->add_action( 'admin_enqueue_scripts',       $assets, 'enqueue_admin' );

		// Frontend Settings — apply all Settings page options to the front end.
		// Priority 20 ensures handles registered by enqueue_frontend already exist.
		$frontend_settings = new Frontend\FrontendSettings();
		$this->loader->add_action( 'wp_enqueue_scripts', $frontend_settings, 'inject_inline_styles',    20 );
		$this->loader->add_action( 'wp_enqueue_scripts', $frontend_settings, 'apply_performance',       20 );
		$this->loader->add_action( 'wp_enqueue_scripts', $frontend_settings, 'localise_js_data',        25 );
		$this->loader->add_action( 'wp_enqueue_scripts', $frontend_settings, 'inject_a11y_css',         20 );
		$this->loader->add_filter( 'pizzatier_query_args_toppings', $frontend_settings, 'apply_sort_filter', 10, 2 );
		$this->loader->add_filter( 'pizzatier_tab_order',           $frontend_settings, 'apply_tab_order',   10, 2 );

		// Shortcodes (registered on init)
		$this->loader->add_action( 'init', $this, 'register_shortcodes' );

		// Gutenberg blocks (registered on init, requires WP 5.8+)
		$blocks = new Blocks\BlockRegistrar();
		$this->loader->add_action( 'init', $blocks, 'register' );

		// REST API — opt-in only (disabled by default; enabled via Settings → Advanced)
		if ( get_option( 'pizzatier_setting_adv_rest_api_enabled', 'no' ) === 'yes' ) {
			$rest_api = new Api\PizzaRestApi();
			$this->loader->add_action( 'rest_api_init', $rest_api, 'register_routes' );
		}

		// ── Live template preview override (front-end + admin) ───────────
		// Must run outside is_admin() — the iframe loads on the front-end.
		// A signed ?pzl_preview=slug&pzl_nonce=HASH request temporarily
		// swaps the active template for that page-load only (no DB write).
		$this->loader->add_action( 'init', $this, 'handle_preview_override', 1 );

		// Admin bar — registered outside is_admin() so it appears on the front end too.
		// WordPress only calls admin_bar_menu when the bar is showing, so this is safe.
		$admin_bar = new Admin\AdminBar();
		$this->loader->add_action( 'admin_bar_menu', $admin_bar, 'register', 100 );
		// Toolbar CSS goes out through the stylesheet pipeline on whichever context
		// the bar is showing in (front or admin).
		$this->loader->add_action( 'admin_enqueue_scripts', $admin_bar, 'enqueue_bar_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts',    $admin_bar, 'enqueue_bar_styles' );

		// Admin
		if ( is_admin() ) {
			// Install-integrity notice. Registered first so it still surfaces
			// if a later admin service is the thing that failed to load.
			$this->loader->add_action( 'admin_notices', $this, 'notice_autoload_misses' );

			// Orders admin screens. Registers its own admin_init handlers so
			// every state change can finish with a clean redirect.
			if ( Orders\OrderCheckout::is_enabled() ) {
				( new Orders\Admin\OrdersPage() )->register();
			}

			// Private, staff-only notes about customers, on the user profile
			// and the Users list. Registered independently of the ordering
			// switch so existing notes stay reachable if ordering is paused.
			( new Orders\CustomerNotes() )->register();

			$admin_menu = new Admin\AdminMenu();
			$this->loader->add_action( 'admin_menu', $admin_menu, 'register' );
			// Redirect the Content Hub to WP lists when it's disabled in Settings.
			$this->loader->add_action( 'admin_init', $admin_menu, 'maybe_redirect_disabled_hub' );

			// Settings export — must run before any HTML output
			$settings = new Admin\Settings();
			$this->loader->add_action( 'admin_post_pizzatier_export_settings', $settings, 'handle_export' );

			// Site Migration — full export (settings + CPTs + meta + extension hook).
			// Export handler must run before any HTML output, like Settings::handle_export.
			$site_migration = new Admin\SiteMigration();
			$this->loader->add_action( 'admin_post_pizzatier_site_export', $site_migration, 'handle_export' );

			// AJAX: template switcher
			$this->loader->add_action( 'wp_ajax_pizzatier_set_template', $this, 'ajax_set_template' );

			// Content Hub AJAX panel switcher
			$content_hub = new Admin\ContentHub();
			$this->loader->add_action( 'wp_ajax_pizzatier_content_panel', $content_hub, 'ajax_panel' );

			// Content Hub bulk actions (trash / restore / delete) — must run on
			// admin_init, before any output, so the post-action redirect fires
			// cleanly. Without this the bulk POST falls through to WordPress's
			// page-hook resolution and dies with "Cannot load pizzatier-content."
			$this->loader->add_action( 'admin_init', $content_hub, 'maybe_handle_bulk' );

			// Layer Image Maker — upload result to media library
			$layer_maker = new Admin\LayerImageMaker();
			$this->loader->add_action( 'wp_ajax_pizzatier_upload_layer_image', $layer_maker, 'ajax_upload_layer_image' );

			// Layer Image Maker meta box — on CPT edit/new screens
			$layer_meta_box = new Admin\LayerImageMetaBox();
			$layer_meta_box->register_hooks();

			// Nutrition & Ingredients meta box — on edible-layer CPT edit/new screens
			$nutrition_meta_box = new Admin\NutritionMetaBox();
			$nutrition_meta_box->register_hooks();

			// Layer Builder Wizard — save new layer post via AJAX
			$layer_wizard = new Admin\LayerBuilderWizard();
			$this->loader->add_action( 'wp_ajax_pizzatier_wizard_save_layer', $layer_wizard, 'ajax_save_layer' );
		}

		// Debug logging — only when WP_DEBUG is on
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->loader->add_action( 'init', $this, 'register_debug_helpers' );
		}

		// Merged PizzaTier feature set — pricing, WooCommerce, presets.
		$this->boot_pro_features();
	}

	/**
	 * Boot the feature set formerly shipped as the PizzaTier plugin.
	 *
	 * Ported from that plugin's `plugins_loaded` bootstrap. The base-plugin
	 * presence check, the minimum-base-version gate and the licence gate are
	 * all gone: this code now lives inside the base plugin, so there is nothing
	 * to check for and nothing to license.
	 *
	 * Timing note: the cart & pricing bootstrap used to run on `plugins_loaded` at
	 * priority 20, after PizzaTier booted at 10. It now runs inside PizzaTier's
	 * own boot at priority 10. Everything below is hook registration rather
	 * than immediate work, and `class_exists( 'WooCommerce' )` is already
	 * settled by the time any `plugins_loaded` callback runs, so the earlier
	 * timing is expected to be equivalent — but this is the one behavioural
	 * difference in the fold and is worth confirming on a live store.
	 *
	 * The pricing, presets and nutrition admin screens do not require
	 * WooCommerce and now load without it. Until 2.0.0 they did not: the
	 * PizzaTier bootstrap returned early when WooCommerce was absent, so the
	 * whole commerce admin disappeared — even though `Commerce\Plugin` re-checks for
	 * WooCommerce internally and is written to degrade rather than vanish.
	 * That was a bug; a store can legitimately configure per-layer prices and
	 * presets before installing a shop, or take orders through PizzaTier's own
	 * ordering system and never install one at all.
	 */
	private function boot_pro_features(): void {

		// Pizza Preset meta save. save_post fires on every post save; the
		// guard inside save_meta checks post type, nonce and capabilities, so
		// hooking unconditionally is safe.
		( new \PizzaTier\Commerce\Admin\Presets() )->register();

		// [pizza_preset] shortcode — depends only on PizzaTier. The
		// pizzatier_presets CPT is registered by PostTypeRegistrar.
		( new \PizzaTier\Commerce\Presets\PresetShortcode() )->register();

		// Cart & pricing card on the PizzaTier dashboard, replacing the
		// separate dashboard page that PizzaTier used to ship.
		( new \PizzaTier\Commerce\Admin\HomeCard() )->register();

		// Pricing, presets, nutrition and the WooCommerce integration.
		// Commerce\Plugin gates its own WooCommerce-dependent parts internally.
		\PizzaTier\Commerce\Plugin::init();
	}

	/**
	 * Register all four shortcodes.
	 *
	 * These are the only classes in the plugin instantiated lazily on `init`
	 * rather than eagerly in register_services(). That made them the first
	 * casualty of an incomplete upload: a missing src/Shortcodes/ file threw
	 * an uncaught Error out of a do_action() on `init`, which fatals every
	 * request including wp-admin, locking the site owner out entirely.
	 * Each shortcode is now registered independently and skipped if its class
	 * cannot be autoloaded, so a damaged install costs one shortcode instead
	 * of the whole site. The autoloader logs the miss, and
	 * notice_autoload_misses() surfaces it in the admin.
	 */
	public function register_shortcodes(): void {
		$shortcodes = [
			'pizza_builder'     => 'PizzaTier\\Shortcodes\\BuilderShortcode',
			'pizza_static'      => 'PizzaTier\\Shortcodes\\StaticShortcode',
			'pizza_layer'       => 'PizzaTier\\Shortcodes\\LayerImageShortcode',
			'pizza_layer_info'  => 'PizzaTier\\Shortcodes\\LayerInfoShortcode',

			// Deprecated aliases -- keep for one major version.
			'pizzatier-menu'    => 'PizzaTier\\Shortcodes\\BuilderShortcode',
			'pizzatier-static'  => 'PizzaTier\\Shortcodes\\StaticShortcode',
		];

		foreach ( $shortcodes as $tag => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			add_shortcode( $tag, [ new $class(), 'render' ] );
		}
	}

	/**
	 * Admin notice for any class the autoloader could not resolve this request.
	 *
	 * A silent partial install is worse than a loud one: features simply go
	 * missing with no explanation. This names the files so they can be
	 * restored.
	 */
	public function notice_autoload_misses(): void {
		if ( empty( $GLOBALS['pizzatier_autoload_misses'] ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$misses = $GLOBALS['pizzatier_autoload_misses'];

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'PizzaTier: incomplete installation detected.', 'pizzatier' );
		echo '</strong><br>';
		esc_html_e( 'The following files are missing or unreadable. Delete the plugin folder and re-extract the plugin zip on the server, then reload this page.', 'pizzatier' );
		echo '</p><ul style="list-style:disc;margin-left:2em">';
		foreach ( $misses as $class => $file ) {
			echo '<li><code>' . esc_html( $file ) . '</code> &mdash; ' . esc_html( $class ) . '</li>';
		}
		echo '</ul></div>';
	}

	/** Register write_log() helper when WP_DEBUG is active. */
	public function register_debug_helpers(): void {
		if ( ! function_exists( 'pizzatier_log' ) ) {
			function pizzatier_log( $data ): void { // phpcs:ignore
				$entry = is_array( $data ) || is_object( $data ) ? print_r( $data, true ) : (string) $data; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- WP_DEBUG-gated logging helper.
				error_log( '[PizzaTier] ' . $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
	}

	/** AJAX: switch active template. */
	public function ajax_set_template(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}
		check_ajax_referer( 'pizzatier_set_template', 'nonce' );

		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		if ( ! $slug ) {
			wp_send_json_error( [ 'message' => 'Missing slug' ], 400 );
		}

		$loader    = new Template\TemplateLoader();
		$templates = $loader->get_available_templates();
		if ( ! isset( $templates[ $slug ] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid template' ], 400 );
		}

		update_option( 'pizzatier_setting_global_template', $slug );
		wp_send_json_success( [ 'slug' => $slug ] );
	}

	/**
	 * If a valid ?pzl_preview=slug&pzl_nonce=hash is present, swap the active
	 * template option for this request only (no DB write).
	 * Only works for logged-in users with manage_options capability.
	 */
	public function handle_preview_override(): void {
		if ( empty( $_GET['pzl_preview'] ) || empty( $_GET['pzl_nonce'] ) ) {
			return;
		}
		$slug  = sanitize_key( wp_unslash( $_GET['pzl_preview'] ) );
		$nonce = sanitize_text_field( wp_unslash( $_GET['pzl_nonce'] ) );

		// Require a logged-in admin — nonce alone is not sufficient.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Nonce is action-specific. wp_verify_nonce returns 1 or 2 on success.
		if ( ! wp_verify_nonce( $nonce, 'pizzatier_preview_' . $slug ) ) {
			return;
		}

		// Validate slug exists
		$loader    = new Template\TemplateLoader();
		$templates = $loader->get_available_templates();
		if ( ! isset( $templates[ $slug ] ) ) {
			return;
		}

		// Override the option in-memory for this request only (no DB write)
		add_filter( 'option_pizzatier_setting_global_template', function() use ( $slug ) {
			return $slug;
		} );

		// Remove X-Frame-Options so the admin iframe can embed the page.
		// WordPress sets SAMEORIGIN by default; security plugins may set DENY.
		// For same-origin admin preview, we need to clear this header.
		add_filter( 'x_frame_options', '__return_false' );
		add_filter( 'wp_headers', function( $headers ) {
			unset( $headers['X-Frame-Options'] );
			if ( isset( $headers['Content-Security-Policy'] ) ) {
				$headers['Content-Security-Policy'] = preg_replace(
					'/frame-ancestors[^;]*;?/',
					'frame-ancestors \'self\';',
					$headers['Content-Security-Policy']
				);
			}
			return $headers;
		} );

		// Body class signals we are in preview mode
		add_filter( 'body_class', function( $classes ) use ( $slug ) {
			$classes[] = 'pizzatier-preview-mode';
			$classes[] = 'pizzatier-preview-' . $slug;
			return $classes;
		} );

		// Hide admin bar for a cleaner preview
		add_filter( 'show_admin_bar', '__return_false' );
	}
}
