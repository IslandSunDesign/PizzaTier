<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Admin Menu
 *
 * Sidebar structure:
 *   PizzaTier
 *   ├─ Dashboard
 *   ├─ ── CONTENT ──          (non-clickable group header)
 *   ├─ Toppings               → ContentHub?pl_cpt=toppings
 *   ├─ Crusts                 → ContentHub?pl_cpt=crusts
 *   ├─ Sauces                 → ContentHub?pl_cpt=sauces
 *   ├─ Cheeses                → ContentHub?pl_cpt=cheeses
 *   ├─ Drizzles               → ContentHub?pl_cpt=drizzles
 *   ├─ Cuts                   → ContentHub?pl_cpt=cuts
 *   ├─ Sizes                  → ContentHub?pl_cpt=sizes
 *   ├─ ── TOOLS ──            (non-clickable group header)
 *   ├─ Setup Guide
 *   ├─ Shortcode Generator
 *   ├─ Template
 *   ├─ Settings
 *   └─ Help
 *
 * CPT items link directly into the ContentHub with the correct tab
 * pre-selected — no page navigation, no extra submenu page registered.
 * WordPress supports external URL slugs (http/https) in add_submenu_page;
 * we use that to point straight at the hub with ?pl_cpt= query param.
 *
 * Group headers are registered as submenu pages with a blank callback
 * and styled as non-interactive via inline CSS + a global class.
 */
class AdminMenu {

	/** CPT definitions — slug → display meta */
	private const CPTS = [
		'toppings' => [ 'label' => 'Toppings', 'singular' => 'Topping',  'icon' => '🍕' ],
		'crusts'   => [ 'label' => 'Crusts',   'singular' => 'Crust',    'icon' => '⬤'  ],
		'sauces'   => [ 'label' => 'Sauces',   'singular' => 'Sauce',    'icon' => '🥫' ],
		'cheeses'  => [ 'label' => 'Cheeses',  'singular' => 'Cheese',   'icon' => '🧀' ],
		'drizzles' => [ 'label' => 'Drizzles', 'singular' => 'Drizzle',  'icon' => '💧' ],
		'cuts'     => [ 'label' => 'Cuts',     'singular' => 'Cut',      'icon' => '✂'  ],
		'sizes'    => [ 'label' => 'Sizes',    'singular' => 'Size',     'icon' => '📏' ],

	];

	private function get_icon(): string {
		// Pizza with two topping dots — separate paths for correct SVG rendering
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<path fill="black" d="M10 1C5.03 1 1 5.03 1 10s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z"/>'
			. '<path fill="black" d="M10 2.6c3.37 0 6.27 2.08 7.52 5.06L10 10.1 2.48 7.66C3.73 4.68 6.63 2.6 10 2.6z"/>'
			. '<path fill="black" d="M2.6 10c0-.38.03-.75.09-1.11L10 11.7l7.31-2.81c.06.36.09.73.09 1.11 0 4.08-3.32 7.4-7.4 7.4S2.6 14.08 2.6 10z"/>'
			. '<circle fill="black" cx="7.2" cy="12.9" r="1.1"/>'
			. '<circle fill="black" cx="12.4" cy="13.7" r="1.1"/>'
			. '</svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Builds a data: URI from inline SVG markup defined above; nothing is decoded or executed.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function register(): void {

		// ── Top-level menu ───────────────────────────────────────────────
		add_menu_page(
			__( 'PizzaTier', 'pizzatier' ),
			__( 'PizzaTier', 'pizzatier' ),
			'manage_options',
			'pizzatier',
			[ $this, 'render_home' ],
			$this->get_icon(),
			56
		);

		// Dashboard — same slug as parent makes it the landing page
		add_submenu_page( 'pizzatier', __( 'Dashboard', 'pizzatier' ), __( 'Dashboard', 'pizzatier' ), 'manage_options', 'pizzatier', [ $this, 'render_home' ] );

		// ── BASICS group header ──────────────────────────────────────────
		add_submenu_page( 'pizzatier', '', '<span class="pzl-menu-group-header">' . esc_html__( 'Basics', 'pizzatier' ) . '</span>', 'manage_options', 'pizzatier-group-basics', '__return_null' );

		// ── Basics pages (everyday essentials) ───────────────────────────
		add_submenu_page( 'pizzatier', __( 'Template',            'pizzatier' ), __( 'Template',            'pizzatier' ), 'manage_options', 'pizzatier-template',   [ $this, 'render_template'   ] );
		add_submenu_page( 'pizzatier', __( 'Settings',            'pizzatier' ), __( 'Settings',            'pizzatier' ), 'manage_options', 'pizzatier-settings',   [ $this, 'render_settings'   ] );
		add_submenu_page( 'pizzatier', __( 'Help',                'pizzatier' ), __( 'Help',                'pizzatier' ), 'manage_options', 'pizzatier-help',       [ $this, 'render_help'       ] );
		add_submenu_page( 'pizzatier', __( 'Shortcode Generator', 'pizzatier' ), __( 'Shortcode Generator', 'pizzatier' ), 'manage_options', 'pizzatier-shortcodes', [ $this, 'render_shortcodes' ] );

		// ── Orders ───────────────────────────────────────────────────────
		// Sits in Basics because taking orders is an everyday task. The label
		// carries an awaiting-attention bubble, matching how WordPress badges
		// pending comments.
		if ( \PizzaTier\Orders\OrderCheckout::is_enabled() ) {
			$orders_label = __( 'Pizza Orders', 'pizzatier' );
			$open_orders  = \PizzaTier\Orders\OrderPostType::open_count();
			if ( $open_orders > 0 ) {
				$orders_label .= ' <span class="awaiting-mod"><span class="pending-count">'
					. esc_html( number_format_i18n( $open_orders ) )
					. '</span></span>';
			}

			add_submenu_page(
				'pizzatier',
				__( 'Pizza Orders', 'pizzatier' ),
				$orders_label,
				\PizzaTier\Orders\OrderPostType::capability(),
				\PizzaTier\Orders\Admin\OrdersPage::SLUG,
				[ $this, 'render_orders' ]
			);
		}

		// ── CONTENT group header (non-clickable separator) ───────────────
		// Registered as a submenu with a unique slug; styled to be non-interactive via CSS.
		add_submenu_page( 'pizzatier', '', '<span class="pzl-menu-group-header">' . esc_html__( 'Content', 'pizzatier' ) . '</span>', 'manage_options', 'pizzatier-group-content', '__return_null' );

		// ── CPT items — link into the ContentHub, or straight to the WP list
		//    when the Content Hub has been disabled in Settings. ────────────
		// WordPress accepts full http URLs as menu slugs since WP 3.0.
		$hub_disabled = ( get_option( 'pizzatier_setting_disable_content_hub', 'no' ) === 'yes' );
		$hub          = admin_url( 'admin.php?page=pizzatier-content' );

		foreach ( self::CPTS as $slug => $meta ) {
			$target = $hub_disabled
				? admin_url( 'edit.php?post_type=pizzatier_' . $slug )
				: add_query_arg( 'pl_cpt', $slug, $hub );
			$url    = esc_url( $target );
			$label = '<span class="pzl-cpt-item">'
			       . '<span class="pzl-cpt-icon">' . $meta['icon'] . '</span>'
			       . '<span class="pzl-cpt-label">' . esc_html( $meta['label'] ) . '</span>'
			       . '</span>';

			// Using the full URL as the $menu_slug — WP renders it as-is in <a href>
			add_submenu_page( 'pizzatier', $meta['label'], $label, 'manage_options', $url, null );
		}

		// ── CART & PRICING group header ──────────────────────────────────
		// The screens that arrived with the PizzaTier merge. They are
		// registered here, rather than by their own classes, so one file owns
		// the whole sidebar and the group ordering is predictable.
		//
		add_submenu_page( 'pizzatier', '', '<span class="pzl-menu-group-header">' . esc_html__( 'Cart & Pricing', 'pizzatier' ) . '</span>', 'manage_options', 'pizzatier-group-commerce', '__return_null' );

		add_submenu_page(
			'pizzatier',
			__( 'Pricing', 'pizzatier' ),
			__( 'Pricing', 'pizzatier' ),
			'manage_options',
			\PizzaTier\Commerce\Admin\PricingPage::PAGE_SLUG,
			[ new \PizzaTier\Commerce\Admin\PricingPage( new \PizzaTier\Commerce\PriceGrid\Grid() ), 'render' ]
		);

		add_submenu_page(
			'pizzatier',
			__( 'Bulk Pricing', 'pizzatier' ),
			__( 'Bulk Pricing', 'pizzatier' ),
			'manage_options',
			\PizzaTier\Commerce\Admin\BulkPricingPage::PAGE_SLUG,
			[ new \PizzaTier\Commerce\Admin\BulkPricingPage( new \PizzaTier\Commerce\PriceGrid\Grid() ), 'render' ]
		);

		add_submenu_page(
			'pizzatier',
			__( 'Pizza Presets', 'pizzatier' ),
			__( 'Pizza Presets', 'pizzatier' ),
			'manage_options',
			'edit.php?post_type=pizzatier_presets',
			null
		);

		add_submenu_page(
			'pizzatier',
			__( 'New Pizza Wizard', 'pizzatier' ),
			__( '✦ New Pizza', 'pizzatier' ),
			'manage_options',
			\PizzaTier\Commerce\Admin\NewPizzaWizard::PAGE_SLUG,
			[ new \PizzaTier\Commerce\Admin\NewPizzaWizard(), 'render' ]
		);

		add_submenu_page(
			'pizzatier',
			__( 'Cart & Pricing Settings', 'pizzatier' ),
			__( 'Cart & Pricing', 'pizzatier' ),
			'manage_options',
			'pizzatier-commerce',
			[ new \PizzaTier\Commerce\Admin\SettingsPage(), 'render' ]
		);

		// ── TOOLS group header ───────────────────────────────────────────
		add_submenu_page( 'pizzatier', '', '<span class="pzl-menu-group-header">' . esc_html__( 'Tools', 'pizzatier' ) . '</span>', 'manage_options', 'pizzatier-group-tools', '__return_null' );

		// ── Tool pages ───────────────────────────────────────────────────
		add_submenu_page( 'pizzatier', __( 'Layer Image Maker',   'pizzatier' ), __( 'Layer Image Maker',   'pizzatier' ), 'manage_options', 'pizzatier-layer-maker',[ $this, 'render_layer_maker'] );
		add_submenu_page( 'pizzatier', __( 'Layer Builder Wizard','pizzatier' ), __( '✦ Layer Builder',      'pizzatier' ), 'manage_options', 'pizzatier-layer-wizard',[ $this, 'render_layer_wizard'] );
		add_submenu_page( 'pizzatier', __( 'Setup Guide',         'pizzatier' ), __( 'Setup Guide',         'pizzatier' ), 'manage_options', 'pizzatier-setup',      [ $this, 'render_setup'      ] );
		add_submenu_page( 'pizzatier', __( 'Site Migration',      'pizzatier' ), __( 'Site Migration',      'pizzatier' ), 'manage_options', 'pizzatier-migration',  [ $this, 'render_migration' ] );
		add_submenu_page( 'pizzatier', __( 'Settings Wizard',     'pizzatier' ), __( '✦ Settings Wizard',   'pizzatier' ), 'manage_options', 'pizzatier-wizard',     [ $this, 'render_wizard'     ] );

		// Register Content Hub page (still needs to exist as a real page)
		add_submenu_page( 'pizzatier', __( 'Content Hub', 'pizzatier' ), '', 'manage_options', 'pizzatier-content', [ $this, 'render_content' ] );

		// Sidebar CSS ships in the enqueued combined admin stylesheet
		// (assets/css/admin/pizzatier-admin.css). Only the runtime active-CPT
		// highlight is added inline, attached to that stylesheet handle.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_menu_highlight' ] );
	}

	/**
	 * Attach the runtime active-CPT sidebar highlight to the combined admin
	 * stylesheet via wp_add_inline_style(). The static menu rules live in the
	 * enqueued file; only this small, request-dependent rule must be inline.
	 */
	public function enqueue_menu_highlight(): void {
		if ( ! wp_style_is( 'pizzatier-admin', 'enqueued' ) ) { return; }

		// Read-only sidebar highlight derived from the admin query string. No
		// form is submitted and no state changes here, so nonce verification
		// does not apply.
		$active_cpt = '';
		if ( isset( $_GET['page'], $_GET['pl_cpt'] ) && 'pizzatier-content' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only menu highlight, no state change.
			$active_cpt = sanitize_key( wp_unslash( $_GET['pl_cpt'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only menu highlight, no state change.
		}
		if ( '' === $active_cpt ) { return; }

		$sel = 'pl_cpt=' . $active_cpt;
		$css = '#adminmenu a[href*="' . $sel . '"]{color:#ff8c42!important;font-weight:600;}'
		     . '#adminmenu a[href*="' . $sel . '"]:before{border-left-color:#ff6b35!important;}';
		wp_add_inline_style( 'pizzatier-admin', $css );
	}


	// ── Page renderers ────────────────────────────────────────────────

	/**
	 * When the Content Hub is disabled in Settings, redirect any direct hit on
	 * admin.php?page=pizzatier-content to the corresponding WP list screen so
	 * stale bookmarks / links don't land on a page that's meant to be off.
	 * Hooked on admin_init (before output).
	 */
	public function maybe_redirect_disabled_hub(): void {
		if ( ! is_admin() ) { return; }
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'pizzatier-content' ) { return; }
		if ( get_option( 'pizzatier_setting_disable_content_hub', 'no' ) !== 'yes' ) { return; }

		$slug  = isset( $_GET['pl_cpt'] ) ? sanitize_key( wp_unslash( $_GET['pl_cpt'] ) ) : 'toppings';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$valid = [ 'toppings', 'crusts', 'sauces', 'cheeses', 'drizzles', 'cuts', 'sizes', 'presets' ];
		if ( ! in_array( $slug, $valid, true ) ) { $slug = 'toppings'; }

		wp_safe_redirect( admin_url( 'edit.php?post_type=pizzatier_' . $slug ) );
		exit;
	}

	public function render_layer_maker():   void { ( new LayerImageMaker() )->render(); }
	public function render_layer_wizard():  void { ( new LayerBuilderWizard() )->render(); }
	public function render_orders():     void { ( new \PizzaTier\Orders\Admin\OrdersPage() )->render(); }
	public function render_home():       void { ( new AdminHome() )->render(); }
	public function render_content():    void { ( new ContentHub() )->render(); }
	public function render_setup():      void { ( new SetupGuide() )->render(); }
	public function render_migration():  void { ( new SiteMigration() )->render(); }
	public function render_shortcodes(): void { ( new ShortcodeGenerator() )->render(); }
	public function render_template():   void { ( new TemplateChoice() )->render(); }
	public function render_settings():   void { ( new Settings() )->render(); }
	public function render_wizard():     void { ( new SettingsWizard() )->render(); }
	public function render_help():       void { ( new Help() )->render(); }
}
