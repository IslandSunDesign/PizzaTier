<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Order model — the single read/write surface for a pizzatier_order record.
 *
 * All structured order data lives in post meta under the brand-neutral
 * `_pzt_order_*` prefix. Nothing in this class touches PizzaTier or
 * WooCommerce; the cart & pricing feature set simply supplies richer pricing data
 * through the same public setters.
 *
 * Meta schema (v1)
 * ----------------
 *   _pzt_order_number         string  Human order number, mirrors post_title.
 *   _pzt_order_customer       array   name, email, phone, company
 *   _pzt_order_fulfillment    array   method, requested_time, address[], table, instructions
 *   _pzt_order_items          array   line items — see add_item()
 *   _pzt_order_totals         array   subtotal, tax, delivery_fee, discount, tip, total, currency
 *   _pzt_order_customer_note  string  Customer-written note / specs for the whole order.
 *   _pzt_order_staff_notes    array   Internal notes: [ time, user_id, note ]
 *   _pzt_order_status_history array   [ time, from, to, user_id, note ]
 *   _pzt_order_source         array   origin, page_id, url, referrer, ip, user_agent, template
 *   _pzt_order_user_id        int     WP user ID when the customer was logged in, else 0.
 *   _pzt_order_schema         int     Meta schema version, for future migrations.
 *
 * PHP 7.4 compatible — no union types, no match(), no named arguments.
 */
class Order {

	/** Current meta schema version. */
	const SCHEMA_VERSION = 1;

	const META_NUMBER         = '_pzt_order_number';
	const META_CUSTOMER       = '_pzt_order_customer';
	const META_FULFILLMENT    = '_pzt_order_fulfillment';
	const META_ITEMS          = '_pzt_order_items';
	const META_TOTALS         = '_pzt_order_totals';
	const META_CUSTOMER_NOTE  = '_pzt_order_customer_note';
	const META_STAFF_NOTES    = '_pzt_order_staff_notes';
	const META_STATUS_HISTORY = '_pzt_order_status_history';
	const META_SOURCE         = '_pzt_order_source';
	const META_USER_ID        = '_pzt_order_user_id';
	const META_SCHEMA         = '_pzt_order_schema';

	/** Option holding the incrementing order sequence. */
	const OPTION_SEQUENCE = 'pizzatier_order_sequence';

	/** @var int */
	private $id;

	/** @var \WP_Post|null */
	private $post;

	// -------------------------------------------------------------------------
	// Construction
	// -------------------------------------------------------------------------

	/**
	 * @param int $post_id An existing pizzatier_order post ID.
	 */
	public function __construct( int $post_id ) {
		$this->id   = $post_id;
		$this->post = get_post( $post_id );
	}

	/**
	 * Load an order, returning null when the ID is not a pizza order.
	 *
	 * @return self|null
	 */
	public static function get( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || OrderPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post_id );
	}

	/**
	 * Create a new order record.
	 *
	 * Only the shell and the status are written here; callers populate the
	 * record through the setters below. Everything is sanitised on write, so
	 * raw request data may be passed straight in.
	 *
	 * @param string $status Initial status. Falls back to the default when invalid.
	 * @return self|\WP_Error
	 */
	public static function create( string $status = '' ) {
		if ( '' === $status || ! OrderStatuses::is_valid( $status ) ) {
			$status = OrderStatuses::DEFAULT_STATUS;
		}

		$number = self::next_order_number();

		$post_id = wp_insert_post(
			[
				'post_type'   => OrderPostType::POST_TYPE,
				'post_title'  => $number,
				'post_status' => $status,
				'post_name'   => sanitize_title( $number ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_NUMBER, $number );
		update_post_meta( $post_id, self::META_SCHEMA, self::SCHEMA_VERSION );
		update_post_meta( $post_id, self::META_USER_ID, get_current_user_id() );

		$order = new self( (int) $post_id );
		$order->log_status_change( '', $status, __( 'Order created.', 'pizzatier' ) );

		/**
		 * Fires immediately after a pizza order record is created.
		 *
		 * @param int   $post_id Order post ID.
		 * @param Order $order   Order model.
		 */
		do_action( 'pizzatier_order_created', (int) $post_id, $order );

		return $order;
	}

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	public function get_id(): int {
		return $this->id;
	}

	public function exists(): bool {
		return $this->post instanceof \WP_Post
			&& OrderPostType::POST_TYPE === $this->post->post_type;
	}

	/** Human-readable order number, e.g. "PZT-1042". */
	public function get_number(): string {
		$number = (string) get_post_meta( $this->id, self::META_NUMBER, true );
		if ( '' === $number ) {
			$number = $this->post instanceof \WP_Post ? (string) $this->post->post_title : '';
		}
		return $number;
	}

	/** Order creation time as a MySQL datetime string (site timezone). */
	public function get_date(): string {
		return $this->post instanceof \WP_Post ? (string) $this->post->post_date : '';
	}

	/** Order creation time as a GMT MySQL datetime string. */
	public function get_date_gmt(): string {
		return $this->post instanceof \WP_Post ? (string) $this->post->post_date_gmt : '';
	}

	/**
	 * Generate the next order number.
	 *
	 * Uses an incrementing option so numbers stay short and sequential even
	 * when orders are deleted. The prefix and pad width are filterable.
	 */
	public static function next_order_number(): string {
		$next = (int) get_option( self::OPTION_SEQUENCE, 0 ) + 1;
		update_option( self::OPTION_SEQUENCE, $next, false );

		$prefix = (string) apply_filters( 'pizzatier_order_number_prefix', 'PZT-' );
		$pad    = (int) apply_filters( 'pizzatier_order_number_pad', 4 );
		$number = $prefix . str_pad( (string) $next, max( 1, $pad ), '0', STR_PAD_LEFT );

		/**
		 * Filter the generated order number.
		 *
		 * @param string $number   Generated order number.
		 * @param int    $sequence The raw sequence value.
		 */
		return (string) apply_filters( 'pizzatier_order_number', $number, $next );
	}

	// -------------------------------------------------------------------------
	// Status
	// -------------------------------------------------------------------------

	public function get_status(): string {
		return $this->post instanceof \WP_Post ? (string) $this->post->post_status : '';
	}

	public function get_status_label(): string {
		return OrderStatuses::label( $this->get_status() );
	}

	/**
	 * Move the order to a new status, recording the transition in the history
	 * log and firing an action other code can hook.
	 *
	 * @param string $status New status name.
	 * @param string $note   Optional note explaining the change.
	 * @return bool True when the status changed.
	 */
	public function set_status( string $status, string $note = '' ): bool {
		if ( ! OrderStatuses::is_valid( $status ) ) {
			return false;
		}

		$from = $this->get_status();
		if ( $from === $status ) {
			return false;
		}

		$result = wp_update_post(
			[
				'ID'          => $this->id,
				'post_status' => $status,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		$this->post = get_post( $this->id );
		$this->log_status_change( $from, $status, $note );

		/**
		 * Fires after a pizza order changes status.
		 *
		 * @param int    $order_id Order post ID.
		 * @param string $from     Previous status.
		 * @param string $to       New status.
		 * @param Order  $order    Order model.
		 */
		do_action( 'pizzatier_order_status_changed', $this->id, $from, $status, $this );

		return true;
	}

	/**
	 * Append an entry to the status history log.
	 */
	private function log_status_change( string $from, string $to, string $note = '' ): void {
		$history   = $this->get_status_history();
		$history[] = [
			'time'    => current_time( 'mysql', true ),
			'from'    => $from,
			'to'      => $to,
			'user_id' => get_current_user_id(),
			'note'    => sanitize_textarea_field( $note ),
		];
		update_post_meta( $this->id, self::META_STATUS_HISTORY, $history );
	}

	/**
	 * @return array<int,array> Ordered oldest → newest.
	 */
	public function get_status_history(): array {
		$history = get_post_meta( $this->id, self::META_STATUS_HISTORY, true );
		return is_array( $history ) ? $history : [];
	}

	// -------------------------------------------------------------------------
	// Customer
	// -------------------------------------------------------------------------

	/**
	 * @return array{name:string,email:string,phone:string,company:string}
	 */
	public function get_customer(): array {
		$saved = get_post_meta( $this->id, self::META_CUSTOMER, true );
		$saved = is_array( $saved ) ? $saved : [];
		return [
			'name'    => isset( $saved['name'] )    ? (string) $saved['name']    : '',
			'email'   => isset( $saved['email'] )   ? (string) $saved['email']   : '',
			'phone'   => isset( $saved['phone'] )   ? (string) $saved['phone']   : '',
			'company' => isset( $saved['company'] ) ? (string) $saved['company'] : '',
		];
	}

	/**
	 * Store the customer block. Input is sanitised here so callers may pass
	 * raw request values.
	 *
	 * @param array $customer name, email, phone, company
	 */
	public function set_customer( array $customer ): void {
		$clean = [
			'name'    => sanitize_text_field( (string) ( isset( $customer['name'] )    ? $customer['name']    : '' ) ),
			'email'   => sanitize_email(      (string) ( isset( $customer['email'] )   ? $customer['email']   : '' ) ),
			'phone'   => sanitize_text_field( (string) ( isset( $customer['phone'] )   ? $customer['phone']   : '' ) ),
			'company' => sanitize_text_field( (string) ( isset( $customer['company'] ) ? $customer['company'] : '' ) ),
		];
		if ( ! is_email( $clean['email'] ) ) {
			$clean['email'] = '';
		}
		update_post_meta( $this->id, self::META_CUSTOMER, $clean );

		// Mirror the address into a flat, queryable key. The customer block is
		// a serialised array, so a personal-data request could not otherwise
		// locate the order — see PizzaTier\Orders\Privacy.
		if ( '' !== $clean['email'] ) {
			update_post_meta( $this->id, Privacy::META_EMAIL_INDEX, strtolower( $clean['email'] ) );
		} else {
			delete_post_meta( $this->id, Privacy::META_EMAIL_INDEX );
		}
	}

	/** WP user ID of the ordering customer, or 0 for guests. */
	public function get_user_id(): int {
		return (int) get_post_meta( $this->id, self::META_USER_ID, true );
	}

	public function set_user_id( int $user_id ): void {
		update_post_meta( $this->id, self::META_USER_ID, max( 0, $user_id ) );
	}

	// -------------------------------------------------------------------------
	// Fulfillment
	// -------------------------------------------------------------------------

	/**
	 * @return array{method:string,requested_time:string,address:array,table:string,instructions:string}
	 */
	public function get_fulfillment(): array {
		$saved = get_post_meta( $this->id, self::META_FULFILLMENT, true );
		$saved = is_array( $saved ) ? $saved : [];

		$address = isset( $saved['address'] ) && is_array( $saved['address'] ) ? $saved['address'] : [];

		return [
			'method'         => isset( $saved['method'] )         ? (string) $saved['method']         : 'pickup',
			'requested_time' => isset( $saved['requested_time'] ) ? (string) $saved['requested_time'] : '',
			'table'          => isset( $saved['table'] )          ? (string) $saved['table']          : '',
			'instructions'   => isset( $saved['instructions'] )   ? (string) $saved['instructions']   : '',
			'address'        => [
				'line1'    => isset( $address['line1'] )    ? (string) $address['line1']    : '',
				'line2'    => isset( $address['line2'] )    ? (string) $address['line2']    : '',
				'city'     => isset( $address['city'] )     ? (string) $address['city']     : '',
				'state'    => isset( $address['state'] )    ? (string) $address['state']    : '',
				'postcode' => isset( $address['postcode'] ) ? (string) $address['postcode'] : '',
				'country'  => isset( $address['country'] )  ? (string) $address['country']  : '',
			],
		];
	}

	/**
	 * Store the fulfillment block.
	 *
	 * @param array $fulfillment method, requested_time, table, instructions, address[]
	 */
	public function set_fulfillment( array $fulfillment ): void {
		$methods = self::fulfillment_methods();
		$method  = sanitize_key( (string) ( isset( $fulfillment['method'] ) ? $fulfillment['method'] : 'pickup' ) );
		if ( ! isset( $methods[ $method ] ) ) {
			$method = 'pickup';
		}

		$address = isset( $fulfillment['address'] ) && is_array( $fulfillment['address'] )
			? $fulfillment['address']
			: [];

		$clean = [
			'method'         => $method,
			'requested_time' => sanitize_text_field( (string) ( isset( $fulfillment['requested_time'] ) ? $fulfillment['requested_time'] : '' ) ),
			'table'          => sanitize_text_field( (string) ( isset( $fulfillment['table'] ) ? $fulfillment['table'] : '' ) ),
			'instructions'   => sanitize_textarea_field( (string) ( isset( $fulfillment['instructions'] ) ? $fulfillment['instructions'] : '' ) ),
			'address'        => [
				'line1'    => sanitize_text_field( (string) ( isset( $address['line1'] )    ? $address['line1']    : '' ) ),
				'line2'    => sanitize_text_field( (string) ( isset( $address['line2'] )    ? $address['line2']    : '' ) ),
				'city'     => sanitize_text_field( (string) ( isset( $address['city'] )     ? $address['city']     : '' ) ),
				'state'    => sanitize_text_field( (string) ( isset( $address['state'] )    ? $address['state']    : '' ) ),
				'postcode' => sanitize_text_field( (string) ( isset( $address['postcode'] ) ? $address['postcode'] : '' ) ),
				'country'  => sanitize_text_field( (string) ( isset( $address['country'] )  ? $address['country']  : '' ) ),
			],
		];

		update_post_meta( $this->id, self::META_FULFILLMENT, $clean );
	}

	/**
	 * Available fulfillment methods.
	 *
	 * @return array<string,string> key => label
	 */
	public static function fulfillment_methods(): array {
		$methods = [
			'pickup'   => __( 'Pickup', 'pizzatier' ),
			'delivery' => __( 'Delivery', 'pizzatier' ),
			'dine_in'  => __( 'Dine In', 'pizzatier' ),
		];

		/**
		 * Filter the available order fulfillment methods.
		 *
		 * @param array $methods key => label
		 */
		return (array) apply_filters( 'pizzatier_order_fulfillment_methods', $methods );
	}

	/** Formatted single-line delivery address, or '' when none is stored. */
	public function get_address_line(): string {
		$address = $this->get_fulfillment()['address'];
		$parts   = array_filter(
			[
				$address['line1'],
				$address['line2'],
				$address['city'],
				trim( $address['state'] . ' ' . $address['postcode'] ),
				$address['country'],
			],
			function ( $part ) {
				return '' !== trim( (string) $part );
			}
		);
		return implode( ', ', $parts );
	}

	// -------------------------------------------------------------------------
	// Line items
	// -------------------------------------------------------------------------

	/**
	 * All line items on the order.
	 *
	 * @return array<int,array>
	 */
	public function get_items(): array {
		$items = get_post_meta( $this->id, self::META_ITEMS, true );
		return is_array( $items ) ? $items : [];
	}

	/**
	 * Replace all line items at once.
	 *
	 * @param array<int,array> $items Raw item arrays; each is sanitised.
	 */
	public function set_items( array $items ): void {
		$clean = [];
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$clean[] = self::sanitize_item( $item );
			}
		}
		update_post_meta( $this->id, self::META_ITEMS, $clean );
	}

	/**
	 * Append one line item.
	 *
	 * Item shape:
	 *   instance_id   string  Builder instance the pizza came from.
	 *   template      string  Active PizzaTier template slug.
	 *   name          string  Display name, e.g. "Custom Pizza" or a preset name.
	 *   preset_id     int     Source preset post ID, 0 when built from scratch.
	 *   size          array   slug, label, post_id, diameter
	 *   layers        array   see sanitize_layer()
	 *   quantity      int
	 *   notes         string  Per-pizza customer note / special instructions.
	 *   unit_price    float
	 *   line_total    float
	 *   price_source  string  'none' | 'grid' | 'manual' — where pricing came from.
	 *
	 * @return int Index of the appended item.
	 */
	public function add_item( array $item ): int {
		$items   = $this->get_items();
		$items[] = self::sanitize_item( $item );
		update_post_meta( $this->id, self::META_ITEMS, $items );
		return count( $items ) - 1;
	}

	/**
	 * Normalise and sanitise a single line item.
	 */
	private static function sanitize_item( array $item ): array {
		$size   = isset( $item['size'] ) && is_array( $item['size'] ) ? $item['size'] : [];
		$layers = isset( $item['layers'] ) && is_array( $item['layers'] ) ? $item['layers'] : [];

		$clean_layers = [];
		foreach ( $layers as $layer ) {
			if ( is_array( $layer ) ) {
				$clean_layers[] = self::sanitize_layer( $layer );
			}
		}

		$quantity   = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
		$unit_price = round( (float) ( isset( $item['unit_price'] ) ? $item['unit_price'] : 0 ), 2 );
		$line_total = isset( $item['line_total'] )
			? round( (float) $item['line_total'], 2 )
			: round( $unit_price * $quantity, 2 );

		$source = sanitize_key( (string) ( isset( $item['price_source'] ) ? $item['price_source'] : 'none' ) );
		if ( ! in_array( $source, [ 'none', 'grid', 'manual' ], true ) ) {
			$source = 'none';
		}

		return [
			'instance_id'  => sanitize_text_field( (string) ( isset( $item['instance_id'] ) ? $item['instance_id'] : '' ) ),
			'template'     => sanitize_key( (string) ( isset( $item['template'] ) ? $item['template'] : '' ) ),
			'name'         => sanitize_text_field( (string) ( isset( $item['name'] ) ? $item['name'] : '' ) ),
			'preset_id'    => absint( isset( $item['preset_id'] ) ? $item['preset_id'] : 0 ),
			'size'         => [
				'slug'     => sanitize_text_field( (string) ( isset( $size['slug'] ) ? $size['slug'] : '' ) ),
				'label'    => sanitize_text_field( (string) ( isset( $size['label'] ) ? $size['label'] : '' ) ),
				'post_id'  => absint( isset( $size['post_id'] ) ? $size['post_id'] : 0 ),
				'diameter' => (float) ( isset( $size['diameter'] ) ? $size['diameter'] : 0 ),
			],
			'layers'       => $clean_layers,
			'quantity'     => $quantity,
			'notes'        => sanitize_textarea_field( (string) ( isset( $item['notes'] ) ? $item['notes'] : '' ) ),
			'unit_price'   => $unit_price,
			'line_total'   => $line_total,
			'price_source' => $source,
		];
	}

	/**
	 * Normalise and sanitise one layer inside a line item.
	 *
	 * Layer shape:
	 *   type            string  'topping' | 'crust' | 'sauce' | 'cheese' | 'drizzle' | 'cut' | 'size'
	 *   slug            string  Layer slug used by the builder.
	 *   name            string  Display name.
	 *   post_id         int     Source CPT post ID.
	 *   coverage        string  Canonical fraction key, e.g. 'whole', 'half-left'.
	 *   coverage_label  string  Display label, e.g. 'Left Half'.
	 *   price           float   Per-layer price when known.
	 */
	private static function sanitize_layer( array $layer ): array {
		return [
			'type'           => sanitize_key( (string) ( isset( $layer['type'] ) ? $layer['type'] : '' ) ),
			'slug'           => sanitize_text_field( (string) ( isset( $layer['slug'] ) ? $layer['slug'] : '' ) ),
			'name'           => sanitize_text_field( (string) ( isset( $layer['name'] ) ? $layer['name'] : '' ) ),
			'post_id'        => absint( isset( $layer['post_id'] ) ? $layer['post_id'] : 0 ),
			'coverage'       => sanitize_text_field( (string) ( isset( $layer['coverage'] ) ? $layer['coverage'] : 'whole' ) ),
			'coverage_label' => sanitize_text_field( (string) ( isset( $layer['coverage_label'] ) ? $layer['coverage_label'] : '' ) ),
			'price'          => round( (float) ( isset( $layer['price'] ) ? $layer['price'] : 0 ), 2 ),
		];
	}

	/** Total pizza count across all line items. */
	public function get_item_count(): int {
		$count = 0;
		foreach ( $this->get_items() as $item ) {
			$count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
		}
		return $count;
	}

	// -------------------------------------------------------------------------
	// Totals
	// -------------------------------------------------------------------------

	/**
	 * @return array{subtotal:float,tax:float,delivery_fee:float,discount:float,tip:float,total:float,currency:string}
	 */
	public function get_totals(): array {
		$saved = get_post_meta( $this->id, self::META_TOTALS, true );
		$saved = is_array( $saved ) ? $saved : [];
		return [
			'subtotal'     => (float) ( isset( $saved['subtotal'] )     ? $saved['subtotal']     : 0 ),
			'tax'          => (float) ( isset( $saved['tax'] )          ? $saved['tax']          : 0 ),
			'delivery_fee' => (float) ( isset( $saved['delivery_fee'] ) ? $saved['delivery_fee'] : 0 ),
			'discount'     => (float) ( isset( $saved['discount'] )     ? $saved['discount']     : 0 ),
			'tip'          => (float) ( isset( $saved['tip'] )          ? $saved['tip']          : 0 ),
			'total'        => (float) ( isset( $saved['total'] )        ? $saved['total']        : 0 ),
			'currency'     => (string) ( isset( $saved['currency'] )    ? $saved['currency']     : '' ),
		];
	}

	/**
	 * Store the totals block.
	 */
	public function set_totals( array $totals ): void {
		$clean = [
			'subtotal'     => round( (float) ( isset( $totals['subtotal'] )     ? $totals['subtotal']     : 0 ), 2 ),
			'tax'          => round( (float) ( isset( $totals['tax'] )          ? $totals['tax']          : 0 ), 2 ),
			'delivery_fee' => round( (float) ( isset( $totals['delivery_fee'] ) ? $totals['delivery_fee'] : 0 ), 2 ),
			'discount'     => round( (float) ( isset( $totals['discount'] )     ? $totals['discount']     : 0 ), 2 ),
			'tip'          => round( (float) ( isset( $totals['tip'] )          ? $totals['tip']          : 0 ), 2 ),
			'total'        => round( (float) ( isset( $totals['total'] )        ? $totals['total']        : 0 ), 2 ),
			'currency'     => sanitize_text_field( (string) ( isset( $totals['currency'] ) ? $totals['currency'] : '' ) ),
		];
		update_post_meta( $this->id, self::META_TOTALS, $clean );
	}

	/**
	 * Recalculate the subtotal and total from the stored line items.
	 * Preserves any tax, delivery fee, discount and tip already recorded.
	 */
	public function recalculate_totals(): void {
		$totals   = $this->get_totals();
		$subtotal = 0.0;
		foreach ( $this->get_items() as $item ) {
			$subtotal += isset( $item['line_total'] ) ? (float) $item['line_total'] : 0.0;
		}
		$totals['subtotal'] = round( $subtotal, 2 );
		$totals['total']    = round(
			$totals['subtotal'] + $totals['tax'] + $totals['delivery_fee'] + $totals['tip'] - $totals['discount'],
			2
		);
		$this->set_totals( $totals );
	}

	// -------------------------------------------------------------------------
	// Notes
	// -------------------------------------------------------------------------

	/** The customer's own note / special requests for the whole order. */
	public function get_customer_note(): string {
		return (string) get_post_meta( $this->id, self::META_CUSTOMER_NOTE, true );
	}

	public function set_customer_note( string $note ): void {
		update_post_meta( $this->id, self::META_CUSTOMER_NOTE, sanitize_textarea_field( $note ) );
	}

	/**
	 * Internal staff notes. Never shown to the customer.
	 *
	 * @return array<int,array{time:string,user_id:int,note:string}>
	 */
	public function get_staff_notes(): array {
		$notes = get_post_meta( $this->id, self::META_STAFF_NOTES, true );
		return is_array( $notes ) ? $notes : [];
	}

	/**
	 * Append an internal staff note.
	 */
	public function add_staff_note( string $note, int $user_id = 0 ): void {
		$note = sanitize_textarea_field( $note );
		if ( '' === trim( $note ) ) {
			return;
		}
		$notes   = $this->get_staff_notes();
		$notes[] = [
			'time'    => current_time( 'mysql', true ),
			'user_id' => $user_id > 0 ? $user_id : get_current_user_id(),
			'note'    => $note,
		];
		update_post_meta( $this->id, self::META_STAFF_NOTES, $notes );
	}

	// -------------------------------------------------------------------------
	// Source / provenance
	// -------------------------------------------------------------------------

	/**
	 * @return array{origin:string,page_id:int,url:string,referrer:string,ip:string,user_agent:string,template:string}
	 */
	public function get_source(): array {
		$saved = get_post_meta( $this->id, self::META_SOURCE, true );
		$saved = is_array( $saved ) ? $saved : [];
		return [
			'origin'     => (string) ( isset( $saved['origin'] )     ? $saved['origin']     : 'builder' ),
			'page_id'    => (int) ( isset( $saved['page_id'] )       ? $saved['page_id']    : 0 ),
			'url'        => (string) ( isset( $saved['url'] )        ? $saved['url']        : '' ),
			'referrer'   => (string) ( isset( $saved['referrer'] )   ? $saved['referrer']   : '' ),
			'ip'         => (string) ( isset( $saved['ip'] )         ? $saved['ip']         : '' ),
			'user_agent' => (string) ( isset( $saved['user_agent'] ) ? $saved['user_agent'] : '' ),
			'template'   => (string) ( isset( $saved['template'] )   ? $saved['template']   : '' ),
		];
	}

	/**
	 * Store provenance data for the order.
	 */
	public function set_source( array $source ): void {
		$origin = sanitize_key( (string) ( isset( $source['origin'] ) ? $source['origin'] : 'builder' ) );
		if ( ! in_array( $origin, [ 'builder', 'admin', 'import', 'api' ], true ) ) {
			$origin = 'builder';
		}

		$clean = [
			'origin'     => $origin,
			'page_id'    => absint( isset( $source['page_id'] ) ? $source['page_id'] : 0 ),
			'url'        => esc_url_raw( (string) ( isset( $source['url'] ) ? $source['url'] : '' ) ),
			'referrer'   => esc_url_raw( (string) ( isset( $source['referrer'] ) ? $source['referrer'] : '' ) ),
			'ip'         => sanitize_text_field( (string) ( isset( $source['ip'] ) ? $source['ip'] : '' ) ),
			'user_agent' => sanitize_text_field( substr( (string) ( isset( $source['user_agent'] ) ? $source['user_agent'] : '' ), 0, 255 ) ),
			'template'   => sanitize_key( (string) ( isset( $source['template'] ) ? $source['template'] : '' ) ),
		];

		update_post_meta( $this->id, self::META_SOURCE, $clean );
	}

	// -------------------------------------------------------------------------
	// Export
	// -------------------------------------------------------------------------

	/**
	 * The complete order as a plain array — used by the admin detail screen,
	 * exports, and (in later phases) receipts and notification emails.
	 *
	 * Staff notes and provenance are included; callers rendering customer-facing
	 * output must omit them.
	 */
	public function to_array(): array {
		return [
			'id'             => $this->id,
			'number'         => $this->get_number(),
			'date'           => $this->get_date(),
			'date_gmt'       => $this->get_date_gmt(),
			'status'         => $this->get_status(),
			'status_label'   => $this->get_status_label(),
			'customer'       => $this->get_customer(),
			'user_id'        => $this->get_user_id(),
			'fulfillment'    => $this->get_fulfillment(),
			'items'          => $this->get_items(),
			'item_count'     => $this->get_item_count(),
			'totals'         => $this->get_totals(),
			'customer_note'  => $this->get_customer_note(),
			'staff_notes'    => $this->get_staff_notes(),
			'status_history' => $this->get_status_history(),
			'source'         => $this->get_source(),
			'schema'         => (int) get_post_meta( $this->id, self::META_SCHEMA, true ),
		];
	}
}
