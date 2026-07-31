<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Front-end order capture.
 *
 * Renders PizzaTier's own order bar into the builder action-bar area — the
 * `pizzatier_builder_action_bar` hook that every bundled template already
 * fires — and ships the assets that drive the checkout panel.
 *
 * When the site wants the WooCommerce
 * checkout to own that area instead, this bar is suppressed through the
 * `pizzatier_orders_show_bar` filter. The dependency points one way only.
 *
 * Partial resolution order for the bar markup:
 *   1. {stylesheet}/pizzatier/order-bar.php      — child-theme override
 *   2. {template}/pizzatier/order-bar.php        — parent-theme override
 *   3. templates/{active}/order-bar.php          — per-template variant
 *   4. partials/order-bar.php                    — bundled default
 */
class OrderCheckout {

	/** Script/style handle base. */
	const HANDLE = 'pizzatier-orders';

	/**
	 * Instance IDs that have already rendered a bar on this page load, so a
	 * template that fires the hook twice cannot produce duplicate markup.
	 *
	 * @var string[]
	 */
	private static $rendered = [];

	/** Whether any bar rendered, gating the one-time panel markup. */
	private static $panel_printed = false;

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Priority 20 leaves room below for another handler to claim the area
		// first at the default priority of 10.
		add_action( 'pizzatier_builder_action_bar', [ $this, 'render_bar' ], 20, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_panel' ], 20 );
	}

	// -------------------------------------------------------------------------
	// Gating
	// -------------------------------------------------------------------------

	/** Whether the ordering feature is switched on at all. */
	public static function is_enabled(): bool {
		return OrderSettings::is_on( 'enabled' );
	}

	/**
	 * Whether PizzaTier should draw its own order bar.
	 *
	 * The action-bar area is shared with the WooCommerce Add to Cart bar;
	 * ActionBarMode decides which of them own it. Third parties can still veto
	 * through the `pizzatier_orders_show_bar` filter.
	 */
	public static function should_render_bar(): bool {
		$show = self::is_enabled() && ActionBarMode::shows_orders_bar();

		/**
		 * Filter whether PizzaTier's native order bar renders.
		 *
		 * @param bool $show Whether to render.
		 */
		return (bool) apply_filters( 'pizzatier_orders_show_bar', $show );
	}

	/**
	 * Whether the current visitor is allowed to submit an order.
	 */
	public static function visitor_can_order(): bool {
		$can = ! ( OrderSettings::is_on( 'login_required' ) && ! is_user_logged_in() );

		/**
		 * Filter whether the current visitor may place an order.
		 *
		 * @param bool $can Whether ordering is permitted.
		 */
		return (bool) apply_filters( 'pizzatier_orders_visitor_can_order', $can );
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the order bar. Hooked to `pizzatier_builder_action_bar`.
	 *
	 * @param string $instance_id Builder instance identifier.
	 */
	public function render_bar( $instance_id = '' ): void {
		if ( ! self::should_render_bar() ) {
			return;
		}

		$instance_id = sanitize_text_field( (string) $instance_id );
		if ( in_array( $instance_id, self::$rendered, true ) ) {
			return;
		}
		self::$rendered[] = $instance_id;

		$partial = self::locate_partial( 'order-bar.php' );
		if ( '' === $partial ) {
			return;
		}

		// Variables consumed by the partial.
		$template_slug   = self::active_template_slug();
		$button_label    = OrderSettings::button_label();
		$can_order       = self::visitor_can_order();
		$quantity_on     = OrderSettings::is_on( 'quantity_enabled' );
		$max_quantity    = max( 1, OrderSettings::get_int( 'max_quantity' ) );

		include $partial;
	}

	/**
	 * Print the checkout panel once per page, after all bars have rendered.
	 * Hooked to `wp_footer` so the modal sits at the end of the document and
	 * is never trapped inside an overflow-hidden builder column.
	 */
	public function render_panel(): void {
		if ( self::$panel_printed || empty( self::$rendered ) || ! self::should_render_bar() ) {
			return;
		}
		self::$panel_printed = true;

		$partial = self::locate_partial( 'order-panel.php' );
		if ( '' === $partial ) {
			return;
		}

		// Variables consumed by the partial.
		$template_slug = self::active_template_slug();
		$methods       = OrderSettings::enabled_fulfillment_methods();
		$sizes         = self::get_sizes();
		$notes_on      = OrderSettings::is_on( 'notes_enabled' );
		$note_max      = max( 1, OrderSettings::get_int( 'note_maxlength' ) );
		$note_ph       = OrderSettings::note_placeholder();
		$request_time  = OrderSettings::is_on( 'request_time' );
		$require_name  = OrderSettings::is_on( 'require_name' );
		$require_phone = OrderSettings::is_on( 'require_phone' );
		$require_email = OrderSettings::is_on( 'require_email' );

		include $partial;
	}

	/**
	 * Resolve a partial through the theme-override chain.
	 *
	 * @param string $filename Partial filename.
	 * @return string Absolute path, or '' when nothing was found.
	 */
	public static function locate_partial( string $filename ): string {
		$filename = basename( $filename );
		$slug     = self::active_template_slug();

		$candidates = [
			trailingslashit( get_stylesheet_directory() ) . 'pizzatier/' . $filename,
			trailingslashit( get_template_directory() ) . 'pizzatier/' . $filename,
			PIZZATIER_TEMPLATES_DIR . $slug . '/' . $filename,
			PIZZATIER_PLUGIN_DIR . 'partials/' . $filename,
		];

		/**
		 * Filter the candidate paths searched for an order partial.
		 *
		 * @param string[] $candidates Absolute paths, highest priority first.
		 * @param string   $filename   Partial filename.
		 * @param string   $slug       Active template slug.
		 */
		$candidates = (array) apply_filters( 'pizzatier_orders_partial_candidates', $candidates, $filename, $slug );

		foreach ( $candidates as $path ) {
			if ( $path && file_exists( $path ) ) {
				return (string) $path;
			}
		}

		return '';
	}

	/** Active template slug, without assuming the loader class is available. */
	private static function active_template_slug(): string {
		if ( class_exists( '\PizzaTier\Template\TemplateLoader' ) ) {
			$loader = new \PizzaTier\Template\TemplateLoader();
			return $loader->get_active_slug();
		}
		return (string) get_option( 'pizzatier_setting_global_template', 'nightpie' );
	}

	// -------------------------------------------------------------------------
	// Sizes
	// -------------------------------------------------------------------------

	/**
	 * Published Size posts, for the optional size picker.
	 *
	 * @return array<int,array{id:int,slug:string,label:string,diameter:float}>
	 */
	public static function get_sizes(): array {
		if ( ! OrderSettings::is_on( 'size_enabled' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'        => 'pizzatier_sizes',
				'post_status'      => 'publish',
				'posts_per_page'   => 50,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			]
		);

		$sizes = [];
		foreach ( $posts as $post ) {
			$diameter = get_post_meta( $post->ID, '_pizzatier_diameter_inches', true );
			if ( '' === $diameter || false === $diameter ) {
				$diameter = get_post_meta( $post->ID, 'diameter_inches', true );
			}
			$sizes[] = [
				'id'       => (int) $post->ID,
				'slug'     => (string) $post->post_name,
				'label'    => (string) $post->post_title,
				'diameter' => (float) $diameter,
			];
		}

		return $sizes;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the order bar stylesheet and checkout script, and hand the script
	 * its configuration.
	 */
	public function enqueue_assets(): void {
		if ( is_admin() || ! self::should_render_bar() ) {
			return;
		}

		$version = PIZZATIER_VERSION;

		wp_enqueue_style(
			self::HANDLE,
			PIZZATIER_ASSETS_URL . 'css/pizzatier-orders.css',
			[ 'pizzatier-css' ],
			$version
		);

		wp_enqueue_script(
			self::HANDLE,
			PIZZATIER_ASSETS_URL . 'js/pizzatier-orders.js',
			[],
			$version,
			true
		);

		wp_localize_script( self::HANDLE, 'pizzatierOrders', self::js_config() );
	}

	/**
	 * Configuration handed to pizzatier-orders.js.
	 */
	public static function js_config(): array {
		$user      = wp_get_current_user();
		$logged_in = ( $user instanceof \WP_User && $user->ID > 0 );

		$config = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'action'    => OrderSubmission::AJAX_ACTION,
			'nonce'     => wp_create_nonce( OrderSubmission::AJAX_ACTION ),
			'canOrder'  => self::visitor_can_order(),
			'template'  => self::active_template_slug(),
			'pageId'    => (int) get_queried_object_id(),
			'route'     => OrderRoute::get(),
			// The product this page represents, when it is a pizza product. The
			// script prefers the builder wrapper's own data-product-id, which is
			// what distinguishes two builders embedded on one page; this is the
			// fallback for a plain shortcode page. The server validates whatever
			// arrives and falls back to the store's designated product, so this
			// being 0 is not a failure.
			'productId' => (int) ( OrderProduct::is_pizza_product( (int) get_queried_object_id() )
				? get_queried_object_id()
				: 0 ),
			'settings'  => [
				'quantityEnabled' => OrderSettings::is_on( 'quantity_enabled' ),
				'maxQuantity'     => max( 1, OrderSettings::get_int( 'max_quantity' ) ),
				'notesEnabled'    => OrderSettings::is_on( 'notes_enabled' ),
				'noteMaxLength'   => max( 1, OrderSettings::get_int( 'note_maxlength' ) ),
				'requireName'     => OrderSettings::is_on( 'require_name' ),
				'requirePhone'    => OrderSettings::is_on( 'require_phone' ),
				'requireEmail'    => OrderSettings::is_on( 'require_email' ),
				'requestTime'     => OrderSettings::is_on( 'request_time' ),
			],
			// Pre-fill for logged-in customers; guests get empty fields.
			'customer'  => [
				'name'  => $logged_in ? $user->display_name : '',
				'email' => $logged_in ? $user->user_email : '',
			],
			'i18n'      => [
				'orderNow'       => OrderSettings::button_label(),
				'emptyPizza'     => __( 'Build your pizza first, then place your order.', 'pizzatier' ),
				'loginRequired'  => __( 'Please log in to place an order.', 'pizzatier' ),
				'submitting'     => __( 'Sending your order…', 'pizzatier' ),
				'confirmed'      => OrderSettings::confirm_message(),
				/* translators: %s: value inserted into the message. */
				'orderNumber'    => __( 'Your order number is %s.', 'pizzatier' ),
				'genericError'   => __( 'Something went wrong sending your order. Please try again.', 'pizzatier' ),
				'networkError'   => __( 'Could not reach the store. Please check your connection and try again.', 'pizzatier' ),
				'requiredField'  => __( 'This field is required.', 'pizzatier' ),
				'invalidEmail'   => __( 'Please enter a valid email address.', 'pizzatier' ),
				'yourPizza'      => __( 'Your pizza', 'pizzatier' ),
				'noSelection'    => __( 'Nothing selected yet', 'pizzatier' ),
				'close'          => __( 'Close', 'pizzatier' ),
				'placeOrder'     => __( 'Place Order', 'pizzatier' ),
				'placeAnother'   => __( 'Place Another Order', 'pizzatier' ),
				'coverageWhole'  => __( 'Whole', 'pizzatier' ),
				'addedToCart'    => __( 'Your pizza has also been added to your cart.', 'pizzatier' ),
				'goToCart'       => __( 'Go to cart', 'pizzatier' ),
				'cartFailed'     => __( 'We saved your order, but could not add it to your cart.', 'pizzatier' ),
			],
		];

		/**
		 * Filter the configuration passed to the order checkout script.
		 *
		 * @param array $config Config array.
		 */
		return (array) apply_filters( 'pizzatier_orders_js_config', $config );
	}
}
