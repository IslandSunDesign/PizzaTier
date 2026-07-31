<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registers the `pizzatier_order` custom post type.
 *
 * Orders are private records: never publicly queryable, never in search, never
 * in nav menus, and never exposed over REST. They are managed through
 * PizzaTier's own admin screens (added in Phase 3) rather than the default
 * post list, so `show_ui` is on but `show_in_menu` is off.
 *
 * The post title holds the human-readable order number. All structured data
 * lives in post meta and is read/written through the Order model.
 *
 * No dependency on PizzaTier or WooCommerce.
 */
class OrderPostType {

	/** The registered post type name. */
	const POST_TYPE = 'pizzatier_order';

	/**
	 * Capability required to view and manage orders.
	 *
	 * Filterable so sites can hand order management to a shop-manager role
	 * without granting full administrator access.
	 */
	public static function capability(): string {
		return (string) apply_filters( 'pizzatier_orders_capability', 'manage_options' );
	}

	/**
	 * Register the post type. Hooked to `init` at priority 0, alongside the
	 * other PizzaTier CPTs, so statuses and the type land together.
	 */
	public function register(): void {

		$labels = [
			'name'               => __( 'Pizza Orders', 'pizzatier' ),
			'singular_name'      => __( 'Pizza Order', 'pizzatier' ),
			'menu_name'          => __( 'Pizza Orders', 'pizzatier' ),
			'name_admin_bar'     => __( 'Pizza Order', 'pizzatier' ),
			'all_items'          => __( 'All Orders', 'pizzatier' ),
			'add_new'            => __( 'Add New', 'pizzatier' ),
			'add_new_item'       => __( 'Add New Order', 'pizzatier' ),
			'edit_item'          => __( 'Edit Order', 'pizzatier' ),
			'new_item'           => __( 'New Order', 'pizzatier' ),
			'view_item'          => __( 'View Order', 'pizzatier' ),
			'search_items'       => __( 'Search Orders', 'pizzatier' ),
			'not_found'          => __( 'No orders found', 'pizzatier' ),
			'not_found_in_trash' => __( 'No orders found in Trash', 'pizzatier' ),
			'archives'           => __( 'Order Archive', 'pizzatier' ),
		];

		$args = [
			'label'               => __( 'Pizza Order', 'pizzatier' ),
			'description'         => __( 'Customer pizza orders submitted from the builder.', 'pizzatier' ),
			'labels'              => $labels,
			// Title only: the order number. Everything else is meta, rendered
			// by the Phase 3 order detail screen.
			'supports'            => [ 'title' ],
			'taxonomies'          => [],
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			// Surfaced under the PizzaTier menu by AdminMenu, not on its own.
			'show_in_menu'        => false,
			'menu_icon'           => 'dashicons-clipboard',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'query_var'           => false,
			'capability_type'     => 'page',
			// Orders can contain customer PII — keep them off the REST API.
			'show_in_rest'        => false,
			'delete_with_user'    => false,
		];

		/**
		 * Filter the pizzatier_order CPT registration args.
		 *
		 * @param array  $args      CPT args array.
		 * @param string $post_type Post type name.
		 */
		$args = (array) apply_filters( 'pizzatier_order_cpt_args', $args, self::POST_TYPE );

		register_post_type( self::POST_TYPE, $args );

		do_action( 'pizzatier_order_cpt_registered' );
	}

	/**
	 * Count orders per status.
	 *
	 * @return array<string,int> status name => count
	 */
	public static function counts(): array {
		$counts = (array) wp_count_posts( self::POST_TYPE, 'readable' );
		$out    = [];
		foreach ( OrderStatuses::all() as $status ) {
			$out[ $status ] = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
		}
		return $out;
	}

	/**
	 * Number of orders still needing staff attention.
	 */
	public static function open_count(): int {
		$counts = self::counts();
		$total  = 0;
		foreach ( OrderStatuses::open_statuses() as $status ) {
			$total += isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
		}
		return $total;
	}
}
