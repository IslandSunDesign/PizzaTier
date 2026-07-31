<?php
/**
 * Main plugin bootstrap.
 *
 * @package PizzaTier\Commerce
 */

namespace PizzaTier\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?self $instance = null;

	private function __construct() {

		// ── Settings (must be first — provides pizzatier_get_option() data) ─
		// register() hooks into admin_menu / admin_init / admin_enqueue_scripts.
		// Called directly — NOT wrapped in add_action('init').
		$settings = new Admin\Settings();
		$settings->register();

		// ── Pricing page ──────────────────────────────────────────────────
		// Owns the new "Pricing" submenu — pricing engine, default grid
		// values, and the site-wide global per-layer-type grids. Hooks into
		// admin_init (settings field registration), admin_post_* (global
		// grids form save), and admin_enqueue_scripts.
		$pricing_page = new Admin\PricingPage( new PriceGrid\Grid() );
		$pricing_page->register();

		// ── Bulk Pricing page ─────────────────────────────────────────────
		// No-refresh per-ingredient price-grid editor. register() only hooks
		// the two AJAX endpoints (load items / save batch); the menu item and
		// render callback are wired in Dashboard::register_menus(). Registered
		// here so the AJAX actions exist on admin-ajax.php requests, which never
		// fire admin_menu.
		$bulk_pricing_page = new Admin\BulkPricingPage( new PriceGrid\Grid() );
		$bulk_pricing_page->register();

		// ── Admin menu ────────────────────────────────────────────────────
		// Since 2.0.0 the whole sidebar is registered by
		// PizzaTier\Admin\AdminMenu, including the screens that arrived with
		// this feature set. Nothing to register here.

		// ── Pizza Presets (meta box on pizzatier_presets CPT) ────────────
		// Registered in pizzatier.php before the WooCommerce gate so saves
		// work regardless of WooCommerce state. No re-registration needed here.

		// ── Nutrition meta box ────────────────────────────────────────────
		// Merged into PizzaTier\Admin\NutritionMetaBox in 2.0.0. Two boxes were
		// asking for overlapping nutrition data on the same five post types;
		// the surviving box carries the union of both field sets.

		// ── Layer pricing grid meta box (on all 7 PizzaTier CPTs) ───────
		// Registers a size × coverage price table on every ingredient CPT post
		// so individual layers can carry their own pricing (Phase 3).
		$layer_grid_mb = new Admin\LayerGridMetaBox( new PriceGrid\Grid() );
		$layer_grid_mb->register();

		// ── Order pizza build meta box (on WC order edit screen) ─────────
		// Dedicated meta box showing the full pizza configuration for every
		// pizza line item in an order. Works on both legacy and HPOS orders.
		$order_mb = new Admin\OrderPizzaMetaBox();
		$order_mb->register();

		// ── New Pizza Wizard ──────────────────────────────────────────────
		$wizard = new Admin\NewPizzaWizard();
		$wizard->register();

		// ── Site Migration integration ────────────────────────────────────
		// Hooks into the pizzatier_export_payload filter and
		// pizzatier_import_payload action to add the cart & pricing
		// settings, the setup-done flag, and any WooCommerce pizza products
		// to the export. If those hooks never fire,
		// this class is silently inert.
		$migration = new Admin\Migration();
		$migration->register();

		// ── WooCommerce ───────────────────────────────────────────────────
		if ( class_exists( 'WooCommerce' ) ) {

			// Product type — register() hooks its own add_action('init') calls
			// internally, so call directly here.
			$product_type = new WooCommerce\ProductType();
			$product_type->register();

			// Product tab (admin only).
			$product_tab = new WooCommerce\ProductTab();
			add_action( 'init', [ $product_tab, 'register' ] );

			// Price grid import/export AJAX handlers.
			$grid_io = new PriceGrid\GridImportExport( new PriceGrid\Grid() );
			add_action( 'init', [ $grid_io, 'register' ] );

			// Shared price calculator.
			$calculator = new PriceCalculator( new PriceGrid\Grid() );

			// Frontend embed (builder injection + size selector + price bar).
			$frontend = new WooCommerce\FrontendEmbed( new PriceGrid\Grid() );
			$frontend->register();

			// Cart integration — AJAX add-to-cart + price override.
			$cart = new WooCommerce\CartIntegration( $calculator );
			add_action( 'init', [ $cart, 'register' ] );

			// Checkout bar inside the PizzaTier builder canvas.
			//
			// Priority 10 keeps the Add to Cart bar above PizzaTier's native
			// order bar, which registers at 20 — so in "both" mode the
			// WooCommerce bar comes first. The mode is read at render time
			// rather than here, because settings can change after boot.
			add_action(
				'pizzatier_builder_action_bar',
				function( $instance_id = '' ) use ( $cart ) {
					if ( ! \PizzaTier\Orders\ActionBarMode::shows_woocommerce_bar() ) {
						return;
					}
					$cart->render_cart_button( (string) $instance_id );
				},
				10
			);

			// Order meta — saves config to line items, displays in cart/orders.
			$order_meta = new WooCommerce\OrderMeta();
			add_action( 'init', [ $order_meta, 'register' ] );

			// Order email summary — appends pizza configuration to every WC order
			// email by default, and (optionally) sends a dedicated standalone
			// summary email when the corresponding setting is enabled. The class
			// itself gates each delivery mode internally; we always register it.
			$email_summary = new WooCommerce\OrderEmailSummary();
			add_action( 'init', [ $email_summary, 'register' ] );

			// REST endpoint — server-side price verification.
			$price_endpoint = new REST\PriceEndpoint( $calculator );
			$price_endpoint->register();

			// REST endpoint — admin product-editor live preview. Self-contained
			// so the preview works without the base plugin's opt-in REST API.
			$admin_render = new REST\AdminRenderEndpoint();
			$admin_render->register();
		}

		// ── Admin bar ─────────────────────────────────────────────────────
		// The WooCommerce products link folded into PizzaTier\Admin\AdminBar
		// in 2.0.0; ProAdminBar is gone.

		// ── Filter hooks: supply WC cart data to base plugin's JS ────────
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'pizzatier_show_cart_btn', function() {
				return (bool) pizzatier_get_option( 'show_cart_btn', false );
			} );
			add_filter( 'pizzatier_cart_btn_text', function() {
				$t = (string) pizzatier_get_option( 'cart_btn_text', '' );
				return $t !== '' ? $t : __( 'Add to Cart', 'pizzatier' );
			} );
			add_filter( 'pizzatier_require_crust', function() {
				return (bool) pizzatier_get_option( 'require_crust', false );
			} );
			add_filter( 'pizzatier_require_sauce', function() {
				return (bool) pizzatier_get_option( 'require_sauce', false );
			} );
		}
	}

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}
}
