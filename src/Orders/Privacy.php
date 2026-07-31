<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * GDPR / personal-data tooling for native orders.
 *
 * The site owner running PizzaTier is the data controller; this plugin's job
 * is to make sure they *can* comply, not to comply on their behalf. So this
 * class wires the order store into the three mechanisms WordPress already
 * provides:
 *
 *   • Tools → Export Personal Data  (wp_privacy_personal_data_exporters)
 *   • Tools → Erase Personal Data   (wp_privacy_personal_data_erasers)
 *   • Settings → Privacy            (wp_add_privacy_policy_content)
 *
 * Two design decisions worth stating outright, because they are not obvious:
 *
 * ERASURE ANONYMISES, IT DOES NOT DELETE. Tax and bookkeeping law in most
 * jurisdictions requires transaction records to be retained for years, and
 * GDPR Art. 17(3)(b) lets a controller refuse erasure where processing is
 * necessary for a legal obligation. So an erasure request blanks every
 * personal field and keeps the order number, date, line items and totals.
 * This matches what WooCommerce does, so operators see familiar behaviour.
 *
 * STAFF NOTES ARE EXPORTED BY DEFAULT. Notes an employee writes *about* an
 * identifiable customer are that customer's personal data, and a subject
 * access request under Art. 15 generally reaches them — "staff-only" is a UI
 * property, not a legal exemption. Sites with a reason to withhold them (for
 * instance where a note contains third-party data) can filter them out via
 * 'pizzatier_privacy_export_staff_notes'.
 *
 * @package PizzaTier\Orders
 */
class Privacy {

	/** Identifier shared by the exporter and eraser registrations. */
	const ID = 'pizzatier-orders';

	/** Orders processed per exporter/eraser page. */
	const PER_PAGE = 20;

	/** Daily retention sweep. */
	const CRON_HOOK = 'pizzatier_orders_retention_sweep';

	/** Marks an order whose personal fields have been cleared. */
	const META_ANONYMISED = '_pzt_order_anonymised';

	/**
	 * Lower-cased copy of the customer email, written alongside the customer
	 * block so orders can actually be found by address.
	 *
	 * The customer block itself is a serialised array, which cannot be queried
	 * reliably — and an exporter that cannot find the data is worse than no
	 * exporter at all.
	 */
	const META_EMAIL_INDEX = '_pzt_order_email';

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers',   [ $this, 'register_eraser' ] );
		add_action( 'admin_init',                          [ $this, 'add_privacy_policy_content' ] );

		// Retention sweep.
		add_action( self::CRON_HOOK, [ $this, 'run_retention_sweep' ] );
		add_action( 'init',          [ $this, 'maybe_schedule_sweep' ] );
	}

	/* ═══════════════════════════════════════════════════════════════════
	   REGISTRATION
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ): array {
		$exporters = is_array( $exporters ) ? $exporters : [];

		$exporters[ self::ID ] = [
			'exporter_friendly_name' => __( 'Pizza Orders', 'pizzatier' ),
			'callback'               => [ $this, 'export' ],
		];

		return $exporters;
	}

	/**
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ): array {
		$erasers = is_array( $erasers ) ? $erasers : [];

		$erasers[ self::ID ] = [
			'eraser_friendly_name' => __( 'Pizza Orders', 'pizzatier' ),
			'callback'             => [ $this, 'erase' ],
		];

		return $erasers;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   LOOKUP
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Find every order belonging to an email address.
	 *
	 * Three routes, because no single one is sufficient:
	 *   • the indexed email meta (exact, fast, written since 2.0.5);
	 *   • a LIKE against the serialised customer block, which catches orders
	 *     placed before the index existed;
	 *   • the linked WP user, which catches logged-in orders placed when the
	 *     email field was optional and left blank.
	 *
	 * @param string $email Address being requested.
	 * @param int    $page  1-based page number.
	 * @return int[] Post IDs.
	 */
	private function find_orders( string $email, int $page ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) { return []; }

		$meta_query = [
			'relation' => 'OR',
			[
				'key'     => self::META_EMAIL_INDEX,
				'value'   => $email,
				'compare' => '=',
			],
			[
				'key'     => Order::META_CUSTOMER,
				'value'   => $email,
				'compare' => 'LIKE',
			],
		];

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			$meta_query[] = [
				'key'     => Order::META_USER_ID,
				'value'   => (int) $user->ID,
				'compare' => '=',
			];
		}

		// Custom statuses are registered with exclude_from_search => true, so
		// post_status => 'any' silently matches nothing here.
		$statuses = array_keys( OrderStatuses::all() );
		if ( ! $statuses ) { $statuses = [ OrderStatuses::DEFAULT_STATUS ]; }

		return (array) get_posts( [
			'post_type'      => OrderPostType::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => self::PER_PAGE,
			'paged'          => max( 1, $page ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only, runs once per subject access request; the serialised customer block leaves no indexable alternative for legacy rows.
			'meta_query'     => $meta_query,
		] );
	}

	/* ═══════════════════════════════════════════════════════════════════
	   EXPORT
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Personal-data exporter callback.
	 *
	 * @param string $email_address Subject of the request.
	 * @param int    $page          1-based page.
	 * @return array{data:array,done:bool}
	 */
	public function export( $email_address, $page = 1 ): array {
		$page  = max( 1, (int) $page );
		$email = sanitize_email( (string) $email_address );
		$data  = [];

		if ( '' === $email ) {
			return [ 'data' => [], 'done' => true ];
		}

		$order_ids = $this->find_orders( $email, $page );

		foreach ( $order_ids as $order_id ) {
			$order = Order::get( (int) $order_id );
			if ( ! $order instanceof Order || ! $order->exists() ) { continue; }

			$data[] = [
				'group_id'          => 'pizzatier-orders',
				'group_label'       => __( 'Pizza Orders', 'pizzatier' ),
				'group_description' => __( 'Orders placed through the pizza builder.', 'pizzatier' ),
				'item_id'           => 'pizzatier-order-' . (int) $order_id,
				'data'              => $this->export_order_fields( $order ),
			];
		}

		// Profile notes are keyed to a WordPress account, so they only exist
		// when the address belongs to a registered user. Exported once, on the
		// first page, rather than repeated against every page of orders.
		if ( 1 === $page ) {
			$profile = $this->export_profile_notes( $email );
			if ( $profile ) { $data[] = $profile; }
		}

		return [
			'data' => $data,
			'done' => count( $order_ids ) < self::PER_PAGE,
		];
	}

	/**
	 * Flatten one order into exporter name/value rows.
	 *
	 * @return array<int,array{name:string,value:string}>
	 */
	private function export_order_fields( Order $order ): array {
		$customer    = $order->get_customer();
		$fulfillment = $order->get_fulfillment();

		$rows = [
			[ 'name' => __( 'Order number', 'pizzatier' ), 'value' => $order->get_number() ],
			[ 'name' => __( 'Date', 'pizzatier' ),         'value' => $order->get_date() ],
			[ 'name' => __( 'Status', 'pizzatier' ),       'value' => $order->get_status_label() ],
		];

		$labels = [
			'name'    => __( 'Name', 'pizzatier' ),
			'email'   => __( 'Email', 'pizzatier' ),
			'phone'   => __( 'Phone', 'pizzatier' ),
			'company' => __( 'Company', 'pizzatier' ),
		];
		foreach ( $labels as $key => $label ) {
			if ( '' !== $customer[ $key ] ) {
				$rows[] = [ 'name' => $label, 'value' => $customer[ $key ] ];
			}
		}

		$methods = Order::fulfillment_methods();
		$method  = $fulfillment['method'];
		$rows[]  = [
			'name'  => __( 'Fulfilment method', 'pizzatier' ),
			'value' => isset( $methods[ $method ] ) ? $methods[ $method ] : $method,
		];

		$address = $order->get_address_line();
		if ( '' !== $address ) {
			$rows[] = [ 'name' => __( 'Delivery address', 'pizzatier' ), 'value' => $address ];
		}

		$optional = [
			'requested_time' => __( 'Requested time', 'pizzatier' ),
			'table'          => __( 'Table number', 'pizzatier' ),
			'instructions'   => __( 'Delivery instructions', 'pizzatier' ),
		];
		foreach ( $optional as $key => $label ) {
			if ( '' !== $fulfillment[ $key ] ) {
				$rows[] = [ 'name' => $label, 'value' => $fulfillment[ $key ] ];
			}
		}

		$note = $order->get_customer_note();
		if ( '' !== $note ) {
			$rows[] = [ 'name' => __( 'Order notes', 'pizzatier' ), 'value' => $note ];
		}

		// Line items — what was ordered is as much the subject's data as who
		// ordered it, and it is what makes an export legible to the requester.
		$items = $order->get_items();
		foreach ( $items as $index => $item ) {
			$rows[] = [
				/* translators: %d = line item number */
				'name'  => sprintf( __( 'Item %d', 'pizzatier' ), (int) $index + 1 ),
				'value' => $this->describe_item( $item ),
			];
		}

		$totals = $order->get_totals();
		if ( isset( $totals['total'] ) ) {
			$rows[] = [ 'name' => __( 'Order total', 'pizzatier' ), 'value' => (string) $totals['total'] ];
		}

		/**
		 * Whether internal staff notes are included in a personal-data export.
		 *
		 * Defaults to true: notes written about an identifiable customer are
		 * that customer's personal data and a subject access request generally
		 * reaches them. Return false where a site has a specific reason to
		 * withhold — for example notes that also identify a third party.
		 *
		 * @param bool  $include Whether to include staff notes.
		 * @param Order $order   The order being exported.
		 */
		$include_staff_notes = (bool) apply_filters( 'pizzatier_privacy_export_staff_notes', true, $order );

		if ( $include_staff_notes ) {
			foreach ( $order->get_staff_notes() as $index => $staff_note ) {
				if ( empty( $staff_note['note'] ) ) { continue; }
				$rows[] = [
					/* translators: %d = staff note number */
					'name'  => sprintf( __( 'Internal note %d', 'pizzatier' ), (int) $index + 1 ),
					'value' => (string) $staff_note['note'],
				];
			}
		}

		return $rows;
	}

	/**
	 * One-line description of an order line item.
	 */
	private function describe_item( array $item ): string {
		$name = isset( $item['name'] ) ? (string) $item['name'] : __( 'Pizza', 'pizzatier' );
		$qty  = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;

		$layers = [];
		if ( ! empty( $item['layers'] ) && is_array( $item['layers'] ) ) {
			foreach ( $item['layers'] as $layer ) {
				if ( ! empty( $layer['name'] ) ) { $layers[] = (string) $layer['name']; }
			}
		}

		$out = $qty > 1 ? $qty . ' × ' . $name : $name;
		if ( $layers ) {
			$out .= ' (' . implode( ', ', $layers ) . ')';
		}

		return $out;
	}

	/**
	 * Staff-written notes stored against a customer's user profile.
	 *
	 * @return array|null Exporter group, or null when there is nothing to report.
	 */
	private function export_profile_notes( string $email ) {
		/** This filter is documented in this class, in export_order_fields(). */
		if ( ! apply_filters( 'pizzatier_privacy_export_staff_notes', true, null ) ) {
			return null;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof \WP_User ) { return null; }

		$notes = (string) get_user_meta( (int) $user->ID, CustomerNotes::META_KEY, true );
		if ( '' === trim( $notes ) ) { return null; }

		return [
			'group_id'          => 'pizzatier-customer-notes',
			'group_label'       => __( 'Pizza Customer Notes', 'pizzatier' ),
			'group_description' => __( 'Internal notes recorded about this customer by staff.', 'pizzatier' ),
			'item_id'           => 'pizzatier-customer-notes-' . (int) $user->ID,
			'data'              => [
				[ 'name' => __( 'Notes', 'pizzatier' ), 'value' => $notes ],
			],
		];
	}

	/* ═══════════════════════════════════════════════════════════════════
	   ERASURE
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Personal-data eraser callback.
	 *
	 * Anonymises rather than deletes — see the class docblock.
	 *
	 * @param string $email_address Subject of the request.
	 * @param int    $page          1-based page.
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public function erase( $email_address, $page = 1 ): array {
		$page  = max( 1, (int) $page );
		$email = sanitize_email( (string) $email_address );

		$removed  = false;
		$retained = false;
		$messages = [];

		if ( '' === $email ) {
			return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
		}

		$order_ids = $this->find_orders( $email, $page );

		foreach ( $order_ids as $order_id ) {
			if ( $this->anonymise_order( (int) $order_id ) ) {
				$removed  = true;
				$retained = true;
			}
		}

		if ( $retained ) {
			$messages[] = __( 'Personal details were removed from your pizza orders. The order number, date, items and totals were kept, because tax and accounting rules require the store to retain a record of the transaction.', 'pizzatier' );
		}

		if ( 1 === $page ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof \WP_User ) {
				$notes = (string) get_user_meta( (int) $user->ID, CustomerNotes::META_KEY, true );
				if ( '' !== trim( $notes ) ) {
					delete_user_meta( (int) $user->ID, CustomerNotes::META_KEY );
					$removed = true;
				}
			}
		}

		return [
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => count( $order_ids ) < self::PER_PAGE,
		];
	}

	/**
	 * Strip every personal field from an order, keeping the business record.
	 *
	 * @return bool Whether anything changed.
	 */
	public function anonymise_order( int $order_id ): bool {
		$order = Order::get( $order_id );
		if ( ! $order instanceof Order || ! $order->exists() ) { return false; }

		if ( get_post_meta( $order_id, self::META_ANONYMISED, true ) ) {
			return false;
		}

		$order->set_customer( [ 'name' => '', 'email' => '', 'phone' => '', 'company' => '' ] );

		$fulfillment = $order->get_fulfillment();
		$order->set_fulfillment( [
			'method'         => $fulfillment['method'],
			'requested_time' => '',
			'table'          => '',
			'instructions'   => '',
			'address'        => [ 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'postcode' => '', 'country' => '' ],
		] );

		$order->set_customer_note( '' );

		// Staff notes keep their timestamps so the order's handling history
		// stays auditable, but the note bodies go.
		$staff_notes = $order->get_staff_notes();
		if ( $staff_notes ) {
			foreach ( $staff_notes as $index => $note ) {
				$staff_notes[ $index ]['note'] = __( '[removed at the customer\'s request]', 'pizzatier' );
			}
			update_post_meta( $order_id, Order::META_STAFF_NOTES, $staff_notes );
		}

		delete_post_meta( $order_id, self::META_EMAIL_INDEX );
		update_post_meta( $order_id, Order::META_USER_ID, 0 );
		update_post_meta( $order_id, self::META_ANONYMISED, 1 );

		// The title is the order number, but a customised install may have put
		// a name in it — reset it defensively.
		wp_update_post( [
			'ID'         => $order_id,
			'post_title' => $order->get_number(),
		] );

		/**
		 * Fires after an order has been anonymised.
		 *
		 * @param int $order_id Order post ID.
		 */
		do_action( 'pizzatier_order_anonymised', $order_id );

		return true;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   RETENTION
	   ═══════════════════════════════════════════════════════════════════ */

	/** Schedule the daily sweep when retention is switched on. */
	public function maybe_schedule_sweep(): void {
		$months = OrderSettings::get_int( 'retention_months' );

		if ( $months > 0 ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
			return;
		}

		$scheduled = wp_next_scheduled( self::CRON_HOOK );
		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}
	}

	/**
	 * Anonymise orders older than the configured retention window.
	 *
	 * Anonymises rather than deletes, for the same reason the eraser does:
	 * storage limitation is satisfied by removing the personal data, while the
	 * transaction record the store is obliged to keep survives.
	 *
	 * @return int Number of orders anonymised.
	 */
	public function run_retention_sweep(): int {
		$months = OrderSettings::get_int( 'retention_months' );
		if ( $months <= 0 ) { return 0; }

		$statuses = array_keys( OrderStatuses::all() );
		if ( ! $statuses ) { $statuses = [ OrderStatuses::DEFAULT_STATUS ]; }

		$order_ids = (array) get_posts( [
			'post_type'      => OrderPostType::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'date_query'     => [
				[
					'column' => 'post_date_gmt',
					'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) ),
				],
			],
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs once daily on cron over a capped batch.
			'meta_query'     => [
				[
					'key'     => self::META_ANONYMISED,
					'compare' => 'NOT EXISTS',
				],
			],
		] );

		$count = 0;
		foreach ( $order_ids as $order_id ) {
			if ( $this->anonymise_order( (int) $order_id ) ) { $count++; }
		}

		return $count;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   PRIVACY POLICY
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Suggested privacy-policy text, surfaced in Settings → Privacy.
	 *
	 * Deliberately descriptive rather than prescriptive: it tells the operator
	 * what the plugin stores so they can write an accurate policy, and does not
	 * pretend to be legal wording they can adopt unread.
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }

		$content =
			'<p class="privacy-policy-tutorial">'
			. esc_html__( 'This is suggested text describing what PizzaTier stores. Review it against how your store actually operates, and have it checked before publishing — retention periods in particular vary by country.', 'pizzatier' )
			. '</p>'
			. '<p><strong>' . esc_html__( 'Pizza orders', 'pizzatier' ) . '</strong></p>'
			. '<p>' . esc_html__( 'When you place an order through our pizza builder we store the name, phone number and email address you supply, your delivery address and any delivery instructions where the order is for delivery, the table number where the order is for a table, any notes you add to the order, what you ordered, and the order total. We use this to prepare and fulfil your order and to contact you about it.', 'pizzatier' ) . '</p>'
			. '<p>' . esc_html__( 'Our staff may add internal notes to your order or to your customer record. These form part of your personal data and are included if you ask us for a copy of it.', 'pizzatier' ) . '</p>'
			. '<p>' . esc_html__( 'To prevent abuse of the order form we briefly store a one-way, non-reversible fingerprint of your network address. It cannot be used to identify you and is discarded automatically after one hour.', 'pizzatier' ) . '</p>'
			. '<p>' . esc_html__( 'If you ask us to erase your data we remove your name, contact details, address and notes from your orders. We keep the order number, date, items and total, because tax and accounting rules require us to retain a record of the transaction.', 'pizzatier' ) . '</p>';

		wp_add_privacy_policy_content( 'PizzaTier', $content );
	}
}
