<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\Order;
use PizzaTier\Orders\OrderPostType;
use PizzaTier\Orders\OrderStatuses;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The Pizza Orders list screen.
 *
 * Note on querying: the pzt-* order statuses are registered with
 * `exclude_from_search => true`, which means a `post_status => 'any'` query
 * silently matches nothing. Every query here passes an explicit status list.
 */
class OrdersListTable extends \WP_List_Table {

	/** Rows per page. */
	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			[
				'singular' => 'pizza_order',
				'plural'   => 'pizza_orders',
				'ajax'     => false,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Columns
	// -------------------------------------------------------------------------

	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox" />',
			'number'      => __( 'Order', 'pizzatier' ),
			'date'        => __( 'Placed', 'pizzatier' ),
			'customer'    => __( 'Customer', 'pizzatier' ),
			'items'       => __( 'Pizzas', 'pizzatier' ),
			'fulfillment' => __( 'Fulfilment', 'pizzatier' ),
			'total'       => __( 'Total', 'pizzatier' ),
			'status'      => __( 'Status', 'pizzatier' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'number' => [ 'title', false ],
			'date'   => [ 'date', true ],
		];
	}

	public function get_bulk_actions(): array {
		if ( 'trash' === $this->current_status() ) {
			return [
				'untrash' => __( 'Restore', 'pizzatier' ),
				'delete'  => __( 'Delete permanently', 'pizzatier' ),
			];
		}

		$actions = [];
		foreach ( OrderStatuses::labels() as $status => $label ) {
			/* translators: %s: order status label. */
			$actions[ 'status_' . $status ] = sprintf( __( 'Mark as %s', 'pizzatier' ), $label );
		}
		$actions['trash'] = __( 'Move to Trash', 'pizzatier' );
		return $actions;
	}

	/**
	 * Status filter links above the table, with live counts.
	 */
	protected function get_views(): array {
		$counts  = OrderPostType::counts();
		$base    = admin_url( 'admin.php?page=pizzatier-orders' );
		$current = $this->current_status();
		$total   = array_sum( $counts );

		$views = [];

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $base ),
			'' === $current ? 'current' : '',
			esc_html__( 'All', 'pizzatier' ),
			esc_html( number_format_i18n( $total ) )
		);

		foreach ( OrderStatuses::labels() as $status => $label ) {
			$count = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
			if ( 0 === $count && $current !== $status ) {
				continue;
			}
			$views[ $status ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? 'current' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		$trashed = $this->trashed_count();
		if ( $trashed > 0 || 'trash' === $current ) {
			$views['trash'] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', 'trash', $base ) ),
				'trash' === $current ? 'current' : '',
				esc_html__( 'Trash', 'pizzatier' ),
				esc_html( number_format_i18n( $trashed ) )
			);
		}

		return $views;
	}

	/** How many orders are currently in the trash. */
	private function trashed_count(): int {
		$counts = (array) wp_count_posts( OrderPostType::POST_TYPE, 'readable' );
		return isset( $counts['trash'] ) ? (int) $counts['trash'] : 0;
	}

	// -------------------------------------------------------------------------
	// Query
	// -------------------------------------------------------------------------

	/** Status currently being filtered on, or '' for all. */
	private function current_status(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( 'trash' === $status ) {
			return 'trash';
		}
		return OrderStatuses::is_valid( $status ) ? $status : '';
	}

	/** Current search term, or ''. */
	private function search_term(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search.
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'number' ];

		$paged   = $this->get_pagenum();
		$status  = $this->current_status();
		$search  = $this->search_term();
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = [
			'post_type'      => OrderPostType::POST_TYPE,
			// Explicit list — 'any' would exclude these statuses entirely.
			'post_status'    => '' !== $status ? [ $status ] : OrderStatuses::all(),
			// 'trash' is a core status, so it is passed through unchanged above.
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => in_array( $orderby, [ 'title', 'date' ], true ) ? $orderby : 'date',
			'order'          => 'asc' === strtolower( $order ) ? 'ASC' : 'DESC',
		];

		if ( '' !== $search ) {
			$ids = $this->search_order_ids( $search );
			if ( empty( $ids ) ) {
				$this->items = [];
				$this->set_pagination_args( [ 'total_items' => 0, 'per_page' => self::PER_PAGE, 'total_pages' => 0 ] );
				return;
			}
			$args['post__in'] = $ids;
		}

		$query = new \WP_Query( $args );

		$this->items = [];
		foreach ( $query->posts as $post ) {
			$order_model = Order::get( (int) $post->ID );
			if ( $order_model ) {
				$this->items[] = $order_model;
			}
		}

		$this->set_pagination_args(
			[
				'total_items' => (int) $query->found_posts,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) $query->max_num_pages,
			]
		);
	}

	/**
	 * Order IDs matching a search term.
	 *
	 * Matches the order number (the post title) and the customer block, which
	 * is a serialised array holding name, email and phone. WP_Query cannot OR a
	 * title search against a meta search, so both halves are resolved here and
	 * merged.
	 *
	 * @return int[]
	 */
	private function search_order_ids( string $search ): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Bespoke OR search across title and serialised meta; no core API expresses this, and the list screen is not a hot path.
		$by_title = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s",
				OrderPostType::POST_TYPE,
				$like
			)
		);

		$by_meta = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				   FROM {$wpdb->posts} p
				   INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				  WHERE p.post_type = %s
				    AND m.meta_key IN ( %s, %s )
				    AND m.meta_value LIKE %s",
				OrderPostType::POST_TYPE,
				Order::META_CUSTOMER,
				Order::META_FULFILLMENT,
				$like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$ids = array_map( 'intval', array_unique( array_merge( (array) $by_title, (array) $by_meta ) ) );

		return array_values( array_filter( $ids ) );
	}

	// -------------------------------------------------------------------------
	// Column renderers
	// -------------------------------------------------------------------------

	/**
	 * @param Order $item
	 */
	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="order_ids[]" value="%d" />',
			(int) $item->get_id()
		);
	}

	/**
	 * @param Order $item
	 */
	protected function column_number( $item ): string {
		$url = OrdersPage::detail_url( $item->get_id() );

		if ( 'trash' === $this->current_status() ) {
			$actions = [
				'untrash' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( OrdersPage::action_url( 'untrash', $item->get_id() ) ),
					esc_html__( 'Restore', 'pizzatier' )
				),
				'delete' => sprintf(
					'<a href="%s" class="submitdelete">%s</a>',
					esc_url( OrdersPage::action_url( 'delete', $item->get_id() ) ),
					esc_html__( 'Delete permanently', 'pizzatier' )
				),
			];
		} else {
			$actions = [
				'view' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html__( 'View', 'pizzatier' )
				),
				'trash' => sprintf(
					'<a href="%s" class="submitdelete">%s</a>',
					esc_url( OrdersPage::action_url( 'trash', $item->get_id() ) ),
					esc_html__( 'Trash', 'pizzatier' )
				),
			];
		}

		$source = $item->get_source();
		$origin = 'admin' === $source['origin']
			? '<span class="pzt-orders-origin">' . esc_html__( 'Added manually', 'pizzatier' ) . '</span>'
			: '';

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s%s',
			esc_url( $url ),
			esc_html( $item->get_number() ),
			$origin,
			$this->row_actions( $actions )
		);
	}

	/**
	 * @param Order $item
	 */
	protected function column_date( $item ): string {
		$timestamp = strtotime( $item->get_date_gmt() . ' UTC' );
		if ( ! $timestamp ) {
			return '&mdash;';
		}

		$diff = time() - $timestamp;

		// Fresh orders read better as "12 mins ago" on a kitchen screen.
		if ( $diff >= 0 && $diff < DAY_IN_SECONDS ) {
			return sprintf(
				'<span title="%s">%s</span>',
				esc_attr( wp_date( 'Y-m-d H:i', $timestamp ) ),
				/* translators: %s: human-readable time difference, e.g. "12 mins". */
				esc_html( sprintf( __( '%s ago', 'pizzatier' ), human_time_diff( $timestamp ) ) )
			);
		}

		return esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp ) );
	}

	/**
	 * @param Order $item
	 */
	protected function column_customer( $item ): string {
		$customer = $item->get_customer();
		$out      = [];

		if ( '' !== $customer['name'] ) {
			$out[] = '<strong>' . esc_html( $customer['name'] ) . '</strong>';
		}
		if ( '' !== $customer['phone'] ) {
			$out[] = '<a href="tel:' . esc_attr( rawurlencode( $customer['phone'] ) ) . '">' . esc_html( $customer['phone'] ) . '</a>';
		}
		if ( '' !== $customer['email'] ) {
			$out[] = '<a href="mailto:' . esc_attr( $customer['email'] ) . '">' . esc_html( $customer['email'] ) . '</a>';
		}

		return empty( $out ) ? '&mdash;' : implode( '<br />', $out );
	}

	/**
	 * @param Order $item
	 */
	protected function column_items( $item ): string {
		$items = $item->get_items();
		if ( empty( $items ) ) {
			return '&mdash;';
		}

		$lines = [];
		foreach ( array_slice( $items, 0, 3 ) as $line ) {
			$label = $line['name'];
			if ( '' !== $line['size']['label'] ) {
				$label .= ' — ' . $line['size']['label'];
			}
			if ( $line['quantity'] > 1 ) {
				$label .= ' × ' . $line['quantity'];
			}
			$lines[] = esc_html( $label );
		}

		if ( count( $items ) > 3 ) {
			$lines[] = esc_html(
				sprintf(
					/* translators: %d: number of additional line items. */
					_n( '+%d more', '+%d more', count( $items ) - 3, 'pizzatier' ),
					count( $items ) - 3
				)
			);
		}

		return implode( '<br />', $lines );
	}

	/**
	 * @param Order $item
	 */
	protected function column_fulfillment( $item ): string {
		$fulfillment = $item->get_fulfillment();
		$methods     = Order::fulfillment_methods();
		$label       = isset( $methods[ $fulfillment['method'] ] )
			? $methods[ $fulfillment['method'] ]
			: $fulfillment['method'];

		$out = '<strong>' . esc_html( $label ) . '</strong>';

		if ( '' !== $fulfillment['requested_time'] ) {
			$out .= '<br /><span class="pzt-orders-muted">' . esc_html( $fulfillment['requested_time'] ) . '</span>';
		}

		$address = $item->get_address_line();
		if ( '' !== $address ) {
			$out .= '<br /><span class="pzt-orders-muted">' . esc_html( $address ) . '</span>';
		}

		if ( '' !== $fulfillment['table'] ) {
			$out .= '<br /><span class="pzt-orders-muted">'
				. esc_html( sprintf( /* translators: %s: table number. */ __( 'Table %s', 'pizzatier' ), $fulfillment['table'] ) )
				. '</span>';
		}

		return $out;
	}

	/**
	 * @param Order $item
	 */
	protected function column_total( $item ): string {
		$totals = $item->get_totals();

		// PizzaTier does not price pizzas on its own. Until a premium extension
		// supplies pricing, show the pizza count rather than a misleading 0.00.
		if ( $totals['total'] <= 0 ) {
			$count = $item->get_item_count();
			return '<span class="pzt-orders-muted">'
				. esc_html( sprintf( /* translators: %d: number of pizzas. */ _n( '%d pizza', '%d pizzas', $count, 'pizzatier' ), $count ) )
				. '</span>';
		}

		return esc_html( OrdersPage::format_money( $totals['total'], $totals['currency'] ) );
	}

	/**
	 * @param Order $item
	 */
	protected function column_status( $item ): string {
		return OrdersPage::status_badge( $item->get_status() );
	}

	/**
	 * @param Order  $item
	 * @param string $column_name
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	public function no_items(): void {
		esc_html_e( 'No orders yet. They will appear here as customers place them.', 'pizzatier' );
	}
}
