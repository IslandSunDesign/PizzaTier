<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\Order;
use PizzaTier\Orders\OrderStatuses;

/**
 * Builds the configuration object handed to orders-dashboard.js via
 * wp_localize_script. Kept out of AssetManager so the asset layer stays free
 * of orders knowledge.
 */
class OrdersDashboardConfig {

	/** Snapshot polling interval, in seconds. */
	const POLL_SECONDS = 15;

	public static function build(): array {
		$statuses = [];
		foreach ( OrderStatuses::labels() as $status => $label ) {
			$statuses[ $status ] = [
				'label'   => $label,
				'color'   => OrderStatuses::color( $status ),
				'is_open' => OrderStatuses::is_open( $status ),
			];
		}

		return [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( OrdersAjax::NONCE ),
			'pollSeconds'  => self::POLL_SECONDS,
			'perPage'      => 20,
			'statuses'     => $statuses,
			'methods'      => Order::fulfillment_methods(),
			'settingsUrl'  => OrdersPage::list_url( [ 'view' => 'settings' ] ),
			'siteName'     => get_bloginfo( 'name' ),
			'i18n'         => [
				'live'          => __( 'Live', 'pizzatier' ),
				'paused'        => __( 'Paused', 'pizzatier' ),
				'soundOn'       => __( 'Sound on', 'pizzatier' ),
				'soundOff'      => __( 'Sound off', 'pizzatier' ),
				'noIncoming'    => __( 'All caught up — no orders waiting. 🍕', 'pizzatier' ),
				'noOrders'      => __( 'No orders match these filters.', 'pizzatier' ),
				'loadError'     => __( 'Could not load orders. Check your connection and try again.', 'pizzatier' ),
				'actionError'   => __( 'That didn\'t work — please try again.', 'pizzatier' ),
				'newOrder'      => __( 'New order in!', 'pizzatier' ),
				'justNow'       => __( 'just now', 'pizzatier' ),
				/* translators: %d: minutes. */
				'minAgo'        => __( '%d min ago', 'pizzatier' ),
				/* translators: %d: hours. */
				'hoursAgo'      => __( '%dh ago', 'pizzatier' ),
				/* translators: 1: current page, 2: total pages, 3: order count. */
				'pageInfo'      => __( 'Page %1$s of %2$s — %3$s orders', 'pizzatier' ),
				/* translators: %d: number of selected orders. */
				'selectedCount' => __( '%d selected', 'pizzatier' ),
				'confirmDelete' => __( 'Permanently delete the selected orders? This cannot be undone.', 'pizzatier' ),
				'confirmCancel' => __( 'Cancel this order?', 'pizzatier' ),
				'view'          => __( 'View', 'pizzatier' ),
				'quickView'     => __( 'Quick view', 'pizzatier' ),
				'cancelOrder'   => __( 'Cancel order', 'pizzatier' ),
				'printTicket'   => __( 'Print ticket', 'pizzatier' ),
				'fullDetails'   => __( 'Open full details', 'pizzatier' ),
				'close'         => __( 'Close', 'pizzatier' ),
				'guest'         => __( 'Guest', 'pizzatier' ),
				'note'          => __( 'Note', 'pizzatier' ),
				'customerNote'  => __( 'Note from the customer', 'pizzatier' ),
				'internalNotes' => __( 'Internal notes', 'pizzatier' ),
				'addNote'       => __( 'Add note', 'pizzatier' ),
				'notePlaceholder' => __( 'Add an internal note…', 'pizzatier' ),
				'history'       => __( 'History', 'pizzatier' ),
				'status'        => __( 'Status', 'pizzatier' ),
				'update'        => __( 'Update', 'pizzatier' ),
				'items'         => __( 'Order items', 'pizzatier' ),
				'total'         => __( 'Total', 'pizzatier' ),
				'subtotal'      => __( 'Subtotal', 'pizzatier' ),
				'tax'           => __( 'Tax', 'pizzatier' ),
				'delivery'      => __( 'Delivery', 'pizzatier' ),
				'discount'      => __( 'Discount', 'pizzatier' ),
				'tip'           => __( 'Tip', 'pizzatier' ),
				'requestedFor'  => __( 'Requested for', 'pizzatier' ),
				'address'       => __( 'Address', 'pizzatier' ),
				'table'         => __( 'Table', 'pizzatier' ),
				'placed'        => __( 'Placed', 'pizzatier' ),
				'phone'         => __( 'Phone', 'pizzatier' ),
				'email'         => __( 'Email', 'pizzatier' ),
				'cappedNotice'  => __( 'Showing the newest 500 matching orders. Narrow the date range to see older ones.', 'pizzatier' ),
				'waitingSince'  => __( 'waiting', 'pizzatier' ),
			],
		];
	}
}
