<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\CustomerNotes;
use PizzaTier\Orders\Order;
use PizzaTier\Orders\OrderPostType;
use PizzaTier\Orders\OrderSettings;
use PizzaTier\Orders\OrderStatuses;

/**
 * AJAX backend for the Pizza Orders dashboard.
 *
 * Every endpoint verifies the same nonce and the orders capability, then
 * returns JSON via wp_send_json_*. The dashboard front end (orders-dashboard.js)
 * is the only consumer.
 *
 * Endpoints:
 *   pizzatier_orders_snapshot    Counts, today's numbers, incoming board.
 *   pizzatier_orders_query       Filterable / sortable / paged order list.
 *   pizzatier_orders_set_status  Change one order's status.
 *   pizzatier_orders_bulk        Bulk status change / trash / restore / delete.
 *   pizzatier_orders_detail      Full order for the quick-view drawer.
 *   pizzatier_orders_add_note    Append an internal staff note.
 */
class OrdersAjax {

	/** Nonce action shared by every endpoint here. */
	const NONCE = 'pizzatier_orders_ajax';

	/**
	 * Ceiling on how many orders a single list query will load and sort.
	 *
	 * Sorting by total or customer name means reading serialized meta, which
	 * cannot be done in SQL, so matching orders are summarised in PHP and
	 * sorted there. The cap keeps that bounded; a small shop stays far under
	 * it, and past the cap the newest orders win with a notice in the UI.
	 */
	const QUERY_CEILING = 500;

	/** How many orders the incoming board shows at most. */
	const INCOMING_LIMIT = 30;

	public function register(): void {
		add_action( 'wp_ajax_pizzatier_orders_snapshot',   [ $this, 'snapshot' ] );
		add_action( 'wp_ajax_pizzatier_orders_query',      [ $this, 'query' ] );
		add_action( 'wp_ajax_pizzatier_orders_set_status', [ $this, 'set_status' ] );
		add_action( 'wp_ajax_pizzatier_orders_bulk',       [ $this, 'bulk' ] );
		add_action( 'wp_ajax_pizzatier_orders_detail',     [ $this, 'detail' ] );
		add_action( 'wp_ajax_pizzatier_orders_add_note',   [ $this, 'add_note' ] );
	}

	/** Nonce + capability gate. Ends the request on failure. */
	private function guard(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( OrderPostType::capability() ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage orders.', 'pizzatier' ) ], 403 );
		}
	}

	// -------------------------------------------------------------------------
	// Snapshot — counts, today, incoming
	// -------------------------------------------------------------------------

	public function snapshot(): void {
		$this->guard();

		$counts = OrderPostType::counts();

		wp_send_json_success(
			[
				'counts'     => $counts,
				'trash'      => $this->trashed_count(),
				'open_count' => OrderPostType::open_count(),
				'accepting'  => OrderSettings::is_on( 'enabled' ),
				'today'      => $this->today_stats(),
				'incoming'   => $this->incoming_orders(),
				'latest_id'  => $this->latest_order_id(),
				'now_gmt'    => time(),
			]
		);
	}

	/** Newest order ID, used by the front end to detect fresh arrivals. */
	private function latest_order_id(): int {
		$ids = get_posts(
			[
				'post_type'              => OrderPostType::POST_TYPE,
				'post_status'            => OrderStatuses::all(),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private function trashed_count(): int {
		$counts = (array) wp_count_posts( OrderPostType::POST_TYPE, 'readable' );
		return isset( $counts['trash'] ) ? (int) $counts['trash'] : 0;
	}

	/**
	 * Today's headline numbers, in the site's timezone.
	 *
	 * Revenue excludes cancelled / refunded / failed orders; the order count
	 * includes every submission so a rough day still shows its real volume.
	 */
	private function today_stats(): array {
		$ids = get_posts(
			[
				'post_type'              => OrderPostType::POST_TYPE,
				'post_status'            => OrderStatuses::all(),
				'posts_per_page'         => self::QUERY_CEILING,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'date_query'             => [
					[
						'after'     => 'today',
						'inclusive' => true,
						'column'    => 'post_date',
					],
				],
			]
		);

		$excluded = [ 'pzt-cancelled', 'pzt-refunded', 'pzt-failed' ];
		$revenue  = 0.0;
		$counted  = 0;
		$pizzas   = 0;
		$methods  = [];
		$currency = '';

		foreach ( $ids as $id ) {
			$order = Order::get( (int) $id );
			if ( ! $order ) {
				continue;
			}

			$pizzas += $order->get_item_count();

			$method             = $order->get_fulfillment()['method'];
			$methods[ $method ] = isset( $methods[ $method ] ) ? $methods[ $method ] + 1 : 1;

			if ( in_array( $order->get_status(), $excluded, true ) ) {
				continue;
			}

			$totals   = $order->get_totals();
			$revenue += (float) $totals['total'];
			if ( '' === $currency && '' !== $totals['currency'] ) {
				$currency = $totals['currency'];
			}
			$counted++;
		}

		return [
			'orders'          => count( $ids ),
			'pizzas'          => $pizzas,
			'revenue'         => round( $revenue, 2 ),
			'revenue_display' => OrdersPage::format_money( $revenue, $currency ),
			'average_display' => OrdersPage::format_money( $counted > 0 ? $revenue / $counted : 0, $currency ),
			'methods'         => $methods,
		];
	}

	/**
	 * Orders still needing attention, oldest first — the kitchen queue.
	 *
	 * @return array<int,array>
	 */
	private function incoming_orders(): array {
		$ids = get_posts(
			[
				'post_type'              => OrderPostType::POST_TYPE,
				'post_status'            => OrderStatuses::open_statuses(),
				'posts_per_page'         => self::INCOMING_LIMIT,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);

		$out = [];
		foreach ( $ids as $id ) {
			$order = Order::get( (int) $id );
			if ( $order ) {
				$out[] = $this->summarize( $order );
			}
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// List query
	// -------------------------------------------------------------------------

	public function query(): void {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$status      = isset( $_REQUEST['status'] )      ? sanitize_key( wp_unslash( $_REQUEST['status'] ) )                : '';
		$search      = isset( $_REQUEST['search'] )      ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) )         : '';
		$fulfillment = isset( $_REQUEST['fulfillment'] ) ? sanitize_key( wp_unslash( $_REQUEST['fulfillment'] ) )           : '';
		$date_from   = isset( $_REQUEST['date_from'] )   ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) )      : '';
		$date_to     = isset( $_REQUEST['date_to'] )     ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) )        : '';
		$orderby     = isset( $_REQUEST['orderby'] )     ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) )               : 'date';
		$order_dir   = isset( $_REQUEST['order'] )       ? sanitize_key( wp_unslash( $_REQUEST['order'] ) )                 : 'desc';
		$paged       = isset( $_REQUEST['paged'] )       ? max( 1, absint( wp_unslash( $_REQUEST['paged'] ) ) )             : 1;
		$per_page    = isset( $_REQUEST['per_page'] )    ? min( 100, max( 5, absint( wp_unslash( $_REQUEST['per_page'] ) ) ) ) : 20;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $orderby, [ 'date', 'number', 'total', 'customer', 'status' ], true ) ) {
			$orderby = 'date';
		}
		$order_dir = ( 'asc' === $order_dir ) ? 'asc' : 'desc';

		// Which post statuses to query.
		if ( 'trash' === $status ) {
			$statuses = [ 'trash' ];
		} elseif ( 'open' === $status ) {
			$statuses = OrderStatuses::open_statuses();
		} elseif ( OrderStatuses::is_valid( $status ) ) {
			$statuses = [ $status ];
		} else {
			$statuses = OrderStatuses::all();
		}

		$args = [
			'post_type'              => OrderPostType::POST_TYPE,
			'post_status'            => $statuses,
			'posts_per_page'         => self::QUERY_CEILING,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		];

		$date_query = [];
		if ( '' !== $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_query['after'] = $date_from;
		}
		if ( '' !== $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_query['before'] = $date_to . ' 23:59:59';
		}
		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
			$args['date_query']      = [ $date_query ];
		}

		$ids = $this->search_ids( $args, $search );

		// Summarise, then filter by fulfilment method in PHP — the method sits
		// inside one serialized meta blob, so SQL can't select on it reliably.
		$rows = [];
		foreach ( $ids as $id ) {
			// Order::get() resolves trashed orders too — it only checks the post type.
			$order = Order::get( (int) $id );
			if ( ! $order ) {
				continue;
			}
			$row = $this->summarize( $order, 'trash' === $status );
			if ( '' !== $fulfillment && $row['fulfillment']['method'] !== $fulfillment ) {
				continue;
			}
			$rows[] = $row;
		}

		$this->sort_rows( $rows, $orderby, $order_dir );

		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged = min( $paged, $pages );
		$rows  = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );

		wp_send_json_success(
			[
				'rows'    => $rows,
				'total'   => $total,
				'pages'   => $pages,
				'paged'   => $paged,
				'capped'  => count( $ids ) >= self::QUERY_CEILING,
				'counts'  => OrderPostType::counts(),
				'trash'   => $this->trashed_count(),
			]
		);
	}

	/**
	 * Resolve matching order IDs, honouring a free-text search.
	 *
	 * The search term is matched against the order number (post title) and
	 * against the customer meta blob (name / phone / email), then merged.
	 *
	 * @return int[]
	 */
	private function search_ids( array $args, string $search ): array {
		if ( '' === $search ) {
			return array_map( 'intval', get_posts( $args ) );
		}

		$title_args      = $args;
		$title_args['s'] = $search;
		$by_title        = get_posts( $title_args );

		$meta_args               = $args;
		$meta_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded lookup, capped result set.
			[
				'key'     => Order::META_CUSTOMER,
				'value'   => $search,
				'compare' => 'LIKE',
			],
		];
		$by_meta = get_posts( $meta_args );

		$ids = array_map( 'intval', array_merge( $by_title, $by_meta ) );
		$ids = array_values( array_unique( $ids ) );
		rsort( $ids ); // Newest (highest ID) first, matching the base ordering closely enough pre-sort.

		return array_slice( $ids, 0, self::QUERY_CEILING );
	}

	/** In-place sort of summarised rows. */
	private function sort_rows( array &$rows, string $orderby, string $dir ): void {
		$sign = ( 'asc' === $dir ) ? 1 : -1;

		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby, $sign ) {
				switch ( $orderby ) {
					case 'number':
						$cmp = strnatcasecmp( $a['number'], $b['number'] );
						break;
					case 'total':
						$cmp = $a['total'] <=> $b['total'];
						break;
					case 'customer':
						$cmp = strcasecmp( $a['customer']['name'], $b['customer']['name'] );
						break;
					case 'status':
						$cmp = strcmp( $a['status'], $b['status'] );
						break;
					default:
						$cmp = $a['ts'] <=> $b['ts'];
				}
				// Stable tie-break on time so equal rows keep a sensible order.
				if ( 0 === $cmp ) {
					$cmp = $a['ts'] <=> $b['ts'];
				}
				return $sign * $cmp;
			}
		);
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	public function set_status(): void {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$order_id = isset( $_POST['order_id'] )   ? absint( wp_unslash( $_POST['order_id'] ) )                       : 0;
		$status   = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) )               : '';
		$note     = isset( $_POST['note'] )       ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) )          : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$order = Order::get( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'pizzatier' ) ], 404 );
		}
		if ( ! OrderStatuses::is_valid( $status ) ) {
			wp_send_json_error( [ 'message' => __( 'That is not a valid status.', 'pizzatier' ) ], 400 );
		}

		$order->set_status( $status, $note );

		wp_send_json_success(
			[
				'row'        => $this->summarize( Order::get( $order_id ) ),
				'counts'     => OrderPostType::counts(),
				'open_count' => OrderPostType::open_count(),
				'message'    => sprintf(
					/* translators: 1: order number, 2: status label. */
					__( '%1$s marked %2$s.', 'pizzatier' ),
					$order->get_number(),
					OrderStatuses::label( $status )
				),
			]
		);
	}

	public function bulk(): void {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$op  = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$ids     = array_filter( $ids );
		$changed = 0;

		foreach ( $ids as $id ) {
			if ( 'trash' === $op ) {
				if ( get_post_type( $id ) === OrderPostType::POST_TYPE && wp_trash_post( $id ) ) {
					$changed++;
				}
				continue;
			}
			if ( 'untrash' === $op ) {
				if ( get_post_type( $id ) === OrderPostType::POST_TYPE && wp_untrash_post( $id ) ) {
					$changed++;
				}
				continue;
			}
			if ( 'delete' === $op ) {
				if ( get_post_type( $id ) === OrderPostType::POST_TYPE && wp_delete_post( $id, true ) ) {
					$changed++;
				}
				continue;
			}
			if ( 0 === strpos( $op, 'status_' ) ) {
				$status = substr( $op, 7 );
				$order  = Order::get( $id );
				if ( $order && $order->set_status( $status, __( 'Changed in bulk.', 'pizzatier' ) ) ) {
					$changed++;
				}
			}
		}

		wp_send_json_success(
			[
				'changed'    => $changed,
				'counts'     => OrderPostType::counts(),
				'trash'      => $this->trashed_count(),
				'open_count' => OrderPostType::open_count(),
				'message'    => sprintf(
					/* translators: %d: number of orders updated. */
					_n( '%d order updated.', '%d orders updated.', $changed, 'pizzatier' ),
					$changed
				),
			]
		);
	}

	public function detail(): void {
		$this->guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

		$order = Order::get( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'pizzatier' ) ], 404 );
		}

		$data            = $order->to_array();
		$data['summary'] = $this->summarize( $order );

		// Display-ready extras the drawer would otherwise recompute.
		$totals                    = $order->get_totals();
		$data['totals_display']    = [
			'subtotal'     => OrdersPage::format_money( $totals['subtotal'], $totals['currency'] ),
			'tax'          => OrdersPage::format_money( $totals['tax'], $totals['currency'] ),
			'delivery_fee' => OrdersPage::format_money( $totals['delivery_fee'], $totals['currency'] ),
			'discount'     => OrdersPage::format_money( $totals['discount'], $totals['currency'] ),
			'tip'          => OrdersPage::format_money( $totals['tip'], $totals['currency'] ),
			'total'        => OrdersPage::format_money( $totals['total'], $totals['currency'] ),
		];
		$data['address_line']      = $order->get_address_line();
		$data['placed_display']    = $this->format_local( $order->get_date_gmt() );
		$data['history_display']   = $this->describe_history( $order );
		$data['notes_display']     = $this->describe_notes( $order );

		// Per-layer / per-item money strings.
		foreach ( $data['items'] as $i => $item ) {
			$data['items'][ $i ]['line_total_display'] = OrdersPage::format_money( (float) $item['line_total'], $totals['currency'] );
			if ( isset( $item['layers'] ) && is_array( $item['layers'] ) ) {
				foreach ( $item['layers'] as $j => $layer ) {
					$data['items'][ $i ]['layers'][ $j ]['price_display'] =
						( (float) $layer['price'] > 0 ) ? OrdersPage::format_money( (float) $layer['price'], $totals['currency'] ) : '';
				}
			}
		}

		// Never ship raw provenance (IP / user agent) to the drawer; the full
		// detail page still shows it for whoever needs it.
		unset( $data['source'], $data['staff_notes'], $data['status_history'] );

		wp_send_json_success( $data );
	}

	public function add_note(): void {
		$this->guard();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$note     = isset( $_POST['note'] )     ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$order = Order::get( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'pizzatier' ) ], 404 );
		}
		if ( '' === trim( $note ) ) {
			wp_send_json_error( [ 'message' => __( 'The note is empty.', 'pizzatier' ) ], 400 );
		}

		$order->add_staff_note( $note );

		wp_send_json_success(
			[
				'notes_display' => $this->describe_notes( $order ),
				'message'       => __( 'Note added.', 'pizzatier' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Shapes
	// -------------------------------------------------------------------------

	/**
	 * Compact, display-ready order summary for board cards and list rows.
	 */
	private function summarize( Order $order, bool $trashed = false ): array {
		$customer    = $order->get_customer();
		$fulfillment = $order->get_fulfillment();
		$totals      = $order->get_totals();
		$methods     = Order::fulfillment_methods();
		$status      = $trashed ? 'trash' : $order->get_status();
		$ts          = strtotime( $order->get_date_gmt() . ' UTC' );
		$ts          = $ts ? $ts : 0;

		return [
			'id'            => $order->get_id(),
			'number'        => $order->get_number(),
			'ts'            => $ts,
			'placed'        => $this->format_local( $order->get_date_gmt() ),
			'age_minutes'   => $ts > 0 ? (int) floor( ( time() - $ts ) / 60 ) : 0,
			'status'        => $status,
			'status_label'  => $trashed ? __( 'Trash', 'pizzatier' ) : OrderStatuses::label( $status ),
			'status_color'  => $trashed ? '#8a8f98' : OrderStatuses::color( $status ),
			'is_open'       => ! $trashed && OrderStatuses::is_open( $status ),
			'customer'      => [
				'name'  => $customer['name'],
				'phone' => $customer['phone'],
				'email' => $customer['email'],
			],
			'items_summary' => $this->items_summary( $order ),
			'pizza_count'   => $order->get_item_count(),
			'fulfillment'   => [
				'method'         => $fulfillment['method'],
				'method_label'   => isset( $methods[ $fulfillment['method'] ] ) ? $methods[ $fulfillment['method'] ] : $fulfillment['method'],
				'requested_time' => $fulfillment['requested_time'],
				'table'          => $fulfillment['table'],
			],
			'has_note'      => '' !== $order->get_customer_note(),
			'total'         => (float) $totals['total'],
			'total_display' => $totals['total'] > 0 ? OrdersPage::format_money( $totals['total'], $totals['currency'] ) : '',
			'next'          => $trashed ? null : $this->next_step( $status, $fulfillment['method'] ),
			'detail_url'    => OrdersPage::detail_url( $order->get_id() ),
		];
	}

	/** "2× Large Pepperoni, 1× Custom Pizza" — capped for card display. */
	private function items_summary( Order $order ): string {
		$parts = [];
		foreach ( $order->get_items() as $item ) {
			$name = isset( $item['name'] ) && '' !== $item['name'] ? $item['name'] : __( 'Custom Pizza', 'pizzatier' );
			$size = isset( $item['size']['label'] ) ? (string) $item['size']['label'] : '';
			$qty  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

			$label   = trim( ( '' !== $size ? $size . ' ' : '' ) . $name );
			$parts[] = $qty . '× ' . $label;
		}

		$summary = implode( ', ', array_slice( $parts, 0, 4 ) );
		if ( count( $parts ) > 4 ) {
			$summary .= ' …';
		}
		return $summary;
	}

	/**
	 * The single sensible next step for an order, or null when it is done.
	 *
	 * Delivery orders pass through Out for Delivery; pickup and dine-in go
	 * straight from Ready to Completed.
	 *
	 * @return array{status:string,label:string}|null
	 */
	private function next_step( string $status, string $method ) {
		$map = [
			'pzt-new'          => [ 'pzt-confirmed', __( 'Confirm', 'pizzatier' ) ],
			'pzt-confirmed'    => [ 'pzt-preparing', __( 'Start preparing', 'pizzatier' ) ],
			'pzt-preparing'    => [ 'pzt-ready', __( 'Mark ready', 'pizzatier' ) ],
			'pzt-ready'        => ( 'delivery' === $method )
				? [ 'pzt-out-delivery', __( 'Send for delivery', 'pizzatier' ) ]
				: [ 'pzt-completed', __( 'Complete', 'pizzatier' ) ],
			'pzt-out-delivery' => [ 'pzt-completed', __( 'Mark delivered', 'pizzatier' ) ],
		];

		/**
		 * Filter the next-step map used by the dashboard's one-tap buttons.
		 *
		 * @param array  $map    status => [ next status, button label ].
		 * @param string $status Current status.
		 * @param string $method Fulfilment method of the order.
		 */
		$map = (array) apply_filters( 'pizzatier_orders_next_step_map', $map, $status, $method );

		if ( ! isset( $map[ $status ] ) || ! OrderStatuses::is_valid( $map[ $status ][0] ) ) {
			return null;
		}

		return [
			'status' => (string) $map[ $status ][0],
			'label'  => (string) $map[ $status ][1],
		];
	}

	private function format_local( string $gmt ): string {
		$ts = strtotime( $gmt . ' UTC' );
		if ( ! $ts ) {
			return $gmt;
		}
		return wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $ts );
	}

	/** @return array<int,array{change:string,by:string,when:string,note:string}> */
	private function describe_history( Order $order ): array {
		$out = [];
		foreach ( array_reverse( $order->get_status_history() ) as $entry ) {
			$from = isset( $entry['from'] ) ? (string) $entry['from'] : '';
			$to   = isset( $entry['to'] ) ? (string) $entry['to'] : '';

			$out[] = [
				'change' => ( '' !== $from ? OrderStatuses::label( $from ) . ' → ' : '' ) . OrderStatuses::label( $to ),
				'by'     => $this->describe_user( isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0 ),
				'when'   => $this->format_local( isset( $entry['time'] ) ? (string) $entry['time'] : '' ),
				'note'   => isset( $entry['note'] ) ? (string) $entry['note'] : '',
			];
		}
		return $out;
	}

	/** @return array<int,array{note:string,by:string,when:string}> */
	private function describe_notes( Order $order ): array {
		$out = [];
		foreach ( array_reverse( $order->get_staff_notes() ) as $note ) {
			$out[] = [
				'note' => isset( $note['note'] ) ? (string) $note['note'] : '',
				'by'   => $this->describe_user( isset( $note['user_id'] ) ? (int) $note['user_id'] : 0 ),
				'when' => $this->format_local( isset( $note['time'] ) ? (string) $note['time'] : '' ),
			];
		}
		return $out;
	}

	private function describe_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return __( 'Customer', 'pizzatier' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown user', 'pizzatier' );
	}
}
