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

		// CPTs
		$cpt = new PostTypes\PostTypeRegistrar();
		$this->loader->add_action( 'init', $cpt, 'register', 0 );

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
			$admin_menu = new Admin\AdminMenu();
			$this->loader->add_action( 'admin_menu', $admin_menu, 'register' );
			// Redirect the Content Hub to WP lists when it's disabled in Settings.
			$this->loader->add_action( 'admin_init', $admin_menu, 'maybe_redirect_disabled_hub' );

			// Settings export — must run before any HTML output
			$settings = new Admin\Settings();
			$this->loader->add_action( 'admin_post_pizzatier_export_settings', $settings, 'handle_export' );

			// Site Migration — full export (settings + CPTs + meta + Pro hook).
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
	}

	/** Register all four shortcodes. */
	public function register_shortcodes(): void {
		add_shortcode( 'pizza_builder',    [ new Shortcodes\BuilderShortcode(),    'render' ] );
		add_shortcode( 'pizza_static',     [ new Shortcodes\StaticShortcode(),     'render' ] );
		add_shortcode( 'pizza_layer',      [ new Shortcodes\LayerImageShortcode(), 'render' ] );
		add_shortcode( 'pizza_layer_info', [ new Shortcodes\LayerInfoShortcode(),  'render' ] );

		// Deprecated aliases — keep for one major version
		add_shortcode( 'pizzatier-menu',   [ new Shortcodes\BuilderShortcode(), 'render' ] );
		add_shortcode( 'pizzatier-static', [ new Shortcodes\StaticShortcode(),  'render' ] );
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
