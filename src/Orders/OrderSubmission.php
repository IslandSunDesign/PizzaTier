<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles order submissions from the front-end checkout panel.
 *
 * Security posture:
 *   - Nonce verified on every request.
 *   - Honeypot field rejects the simplest bots without bothering humans.
 *   - Per-IP rate limit, configurable, backed by a transient.
 *   - Every layer, size and coverage value is re-resolved against the database.
 *     Display names, post IDs and prices are read from the CPTs, never taken
 *     from the request, so a tampered payload cannot invent an item or rename
 *     one on the receipt.
 *
 * No hard dependency on WooCommerce. Pricing arrives through the
 * `pizzatier_order_item_price` filter rather than through any direct call;
 * since 2.1.0 Orders\OrderPricing hooks it when WooCommerce and the price
 * grid are available, and any other integration may hook it too.
 */
class OrderSubmission {

	/** AJAX action name, shared with the JS config. */
	const AJAX_ACTION = 'pizzatier_submit_order';

	/** Transient prefix for the per-IP rate limiter. */
	const RATE_PREFIX = 'pizzatier_ord_rl_';

	/**
	 * Layer type => CPT name. Layers arriving under any other type are dropped.
	 */
	private static function layer_types(): array {
		return [
			'crust'   => 'pizzatier_crusts',
			'sauce'   => 'pizzatier_sauces',
			'cheese'  => 'pizzatier_cheeses',
			'drizzle' => 'pizzatier_drizzles',
			'cut'     => 'pizzatier_cuts',
			'topping' => 'pizzatier_toppings',
		];
	}

	public function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'handle' ] );
	}

	// -------------------------------------------------------------------------
	// Handler
	// -------------------------------------------------------------------------

	/**
	 * Validate and persist an incoming order.
	 */
	public function handle(): void {

		// ── Feature gate ──────────────────────────────────────────────────
		if ( ! OrderCheckout::is_enabled() ) {
			wp_send_json_error(
				[
					'message' => __( 'Online ordering is not available right now.', 'pizzatier' ),
					'code'    => 'orders_disabled',
				],
				403
			);
		}

		// ── Nonce ─────────────────────────────────────────────────────────
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::AJAX_ACTION ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Your session expired. Please refresh the page and try again.', 'pizzatier' ),
					'code'    => 'invalid_nonce',
				],
				403
			);
		}

		// ── Honeypot ──────────────────────────────────────────────────────
		// A real browser leaves this hidden field empty. Respond with a
		// success-shaped payload so a bot learns nothing from the difference.
		$trap = isset( $_POST['pzt_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['pzt_website'] ) ) ) : '';
		if ( '' !== $trap ) {
			wp_send_json_success(
				[
					'order_number' => '',
					'message'      => OrderSettings::confirm_message(),
				]
			);
		}

		// ── Login gate ────────────────────────────────────────────────────
		if ( ! OrderCheckout::visitor_can_order() ) {
			wp_send_json_error(
				[
					'message' => __( 'Please log in to place an order.', 'pizzatier' ),
					'code'    => 'login_required',
				],
				403
			);
		}

		// ── Rate limit ────────────────────────────────────────────────────
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error(
				[
					'message' => __( 'You have placed several orders in a short time. Please wait a few minutes and try again.', 'pizzatier' ),
					'code'    => 'rate_limited',
				],
				429
			);
		}

		// ── Customer ──────────────────────────────────────────────────────
		$customer = [
			'name'    => isset( $_POST['customer_name'] )  ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) )  : '',
			'email'   => isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) )      : '',
			'phone'   => isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '',
			'company' => isset( $_POST['customer_company'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_company'] ) ) : '',
		];

		$errors = $this->validate_customer( $customer );
		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Please check the highlighted fields.', 'pizzatier' ),
					'code'    => 'validation_failed',
					'fields'  => $errors,
				],
				400
			);
		}

		// ── Fulfilment ────────────────────────────────────────────────────
		$methods = OrderSettings::enabled_fulfillment_methods();
		$method  = isset( $_POST['fulfillment_method'] ) ? sanitize_key( wp_unslash( $_POST['fulfillment_method'] ) ) : '';
		if ( ! isset( $methods[ $method ] ) ) {
			$method = (string) array_key_first( $methods );
		}

		$fulfillment = [
			'method'         => $method,
			'requested_time' => isset( $_POST['requested_time'] ) ? sanitize_text_field( wp_unslash( $_POST['requested_time'] ) ) : '',
			'instructions'   => isset( $_POST['delivery_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['delivery_instructions'] ) ) : '',
			'table'          => isset( $_POST['table_number'] ) ? sanitize_text_field( wp_unslash( $_POST['table_number'] ) ) : '',
			'address'        => [
				'line1'    => isset( $_POST['address_line1'] )    ? sanitize_text_field( wp_unslash( $_POST['address_line1'] ) )    : '',
				'line2'    => isset( $_POST['address_line2'] )    ? sanitize_text_field( wp_unslash( $_POST['address_line2'] ) )    : '',
				'city'     => isset( $_POST['address_city'] )     ? sanitize_text_field( wp_unslash( $_POST['address_city'] ) )     : '',
				'state'    => isset( $_POST['address_state'] )    ? sanitize_text_field( wp_unslash( $_POST['address_state'] ) )    : '',
				'postcode' => isset( $_POST['address_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['address_postcode'] ) ) : '',
				'country'  => isset( $_POST['address_country'] )  ? sanitize_text_field( wp_unslash( $_POST['address_country'] ) )  : '',
			],
		];

		if ( 'delivery' === $method && '' === trim( $fulfillment['address']['line1'] ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Please enter a delivery address.', 'pizzatier' ),
					'code'    => 'validation_failed',
					'fields'  => [ 'address_line1' => __( 'A street address is required for delivery.', 'pizzatier' ) ],
				],
				400
			);
		}

		// ── Items ─────────────────────────────────────────────────────────
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
		$items_raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]';
		$items_in  = is_string( $items_raw ) ? json_decode( $items_raw, true ) : $items_raw;

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $items_in ) || empty( $items_in ) ) {
			wp_send_json_error(
				[
					'message' => __( 'We could not read your pizza. Please rebuild it and try again.', 'pizzatier' ),
					'code'    => 'invalid_items',
				],
				400
			);
		}

		// The product the builder was embedded in, when there was one. Both the
		// pricing bridge and the cart dispatch need it, and both validate it
		// before use, so an untrusted value can only ever select a real pizza
		// product. Resolved before items so prices are available as they are built.
		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		OrderPricing::set_context_product( $product_id );

		$items = [];
		foreach ( $items_in as $item_in ) {
			if ( ! is_array( $item_in ) ) {
				continue;
			}
			$item = $this->resolve_item( $item_in );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		if ( empty( $items ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Your order is empty. Please build a pizza before ordering.', 'pizzatier' ),
					'code'    => 'empty_order',
				],
				400
			);
		}

		// ── Create the record ─────────────────────────────────────────────
		$order = Order::create( OrderSettings::initial_status() );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error(
				[
					'message' => __( 'We could not save your order. Please try again.', 'pizzatier' ),
					'code'    => 'create_failed',
				],
				500
			);
		}

		$order->set_customer( $customer );
		$order->set_user_id( get_current_user_id() );
		$order->set_fulfillment( $fulfillment );
		$order->set_items( $items );

		if ( OrderSettings::is_on( 'notes_enabled' ) && isset( $_POST['customer_note'] ) ) {
			$note = sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ) );
			$max  = max( 1, OrderSettings::get_int( 'note_maxlength' ) );
			$order->set_customer_note( self::truncate( $note, $max ) );
		}

		$order->set_source(
			[
				'origin'     => 'builder',
				'page_id'    => isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0,
				'url'        => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
				'referrer'   => wp_get_referer() ? wp_get_referer() : '',
				'ip'         => $this->get_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'template'   => isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '',
			]
		);

		$order->recalculate_totals();

		$this->bump_rate_limit();

		/**
		 * Fires once a front-end order has been fully populated.
		 *
		 * @param Order $order The saved order.
		 */
		do_action( 'pizzatier_order_submitted', $order );

		$emailed = OrderSettings::is_on( 'notify_admin' ) ? $this->notify_admin( $order ) : false;

		// ── Route the order ───────────────────────────────────────────────
		// Everything above this point is the same for every store. What differs
		// is where the finished order goes, which is RouteDispatcher's job.
		$dispatch = ( new RouteDispatcher() )->dispatch( $order, $product_id, $emailed );

		// Read everything needed for the response before the record can be
		// discarded — a discarded order has no meta left to read.
		$response = [
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_number(),
			'message'      => OrderSettings::confirm_message(),
			'route'        => OrderRoute::get(),
			'redirect'     => $dispatch['redirect'],
			'cart_added'   => $dispatch['cart_added'],
			'cart_count'   => $dispatch['cart_count'],
			'cart_url'     => $dispatch['cart_url'],
			'stored'       => true,
		];

		// A cart failure on a route that also records the order is a warning,
		// not a failure: the kitchen already has the ticket. The customer is
		// told so they do not go to a checkout with an empty cart.
		if ( ! empty( $dispatch['errors'] ) ) {
			$response['warnings'] = array_values( $dispatch['errors'] );
		}

		if ( ! empty( $dispatch['discard'] ) ) {
			/**
			 * Fires just before a routed order's record is deleted.
			 *
			 * The order is still fully readable here. This is the last chance
			 * for an integration to copy anything it needs off a "notify only"
			 * order before it stops existing.
			 *
			 * @since 2.1.0
			 *
			 * @param Order $order The order about to be discarded.
			 */
			do_action( 'pizzatier_order_discarded', $order );

			wp_delete_post( $order->get_id(), true );

			$response['order_id'] = 0;
			$response['stored']   = false;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Truncate a string to a character length.
	 *
	 * Prefers mb_substr() so multibyte input is cut on a character boundary,
	 * but falls back to substr() because the mbstring extension is not
	 * guaranteed to be present on shared hosting.
	 */
	private static function truncate( string $text, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length );
		}
		return substr( $text, 0, $length );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * @return array<string,string> field name => error message
	 */
	private function validate_customer( array $customer ): array {
		$errors = [];

		if ( OrderSettings::is_on( 'require_name' ) && '' === trim( $customer['name'] ) ) {
			$errors['customer_name'] = __( 'Please tell us your name.', 'pizzatier' );
		}

		if ( OrderSettings::is_on( 'require_phone' ) && '' === trim( $customer['phone'] ) ) {
			$errors['customer_phone'] = __( 'Please give us a contact phone number.', 'pizzatier' );
		}

		$email_required = OrderSettings::is_on( 'require_email' );
		if ( $email_required && '' === trim( $customer['email'] ) ) {
			$errors['customer_email'] = __( 'Please enter your email address.', 'pizzatier' );
		} elseif ( '' !== trim( $customer['email'] ) && ! is_email( $customer['email'] ) ) {
			$errors['customer_email'] = __( 'Please enter a valid email address.', 'pizzatier' );
		}

		/**
		 * Filter the customer validation errors for a submitted order.
		 *
		 * @param array $errors   field => message
		 * @param array $customer Sanitised customer data.
		 */
		return (array) apply_filters( 'pizzatier_order_validation_errors', $errors, $customer );
	}

	// -------------------------------------------------------------------------
	// Item resolution
	// -------------------------------------------------------------------------

	/**
	 * Rebuild one line item from the database using only the slugs the client
	 * sent. Names, post IDs and prices all come from the server.
	 *
	 * @return array|null Null when the item resolved to nothing usable.
	 */
	private function resolve_item( array $item_in ) {
		$types      = self::layer_types();
		$coverages  = $this->allowed_coverages();
		$layers_in  = isset( $item_in['layers'] ) && is_array( $item_in['layers'] ) ? $item_in['layers'] : [];
		$layers_out = [];

		foreach ( $layers_in as $layer_in ) {
			if ( ! is_array( $layer_in ) ) {
				continue;
			}

			$type = sanitize_key( (string) ( isset( $layer_in['type'] ) ? $layer_in['type'] : '' ) );
			$slug = sanitize_title( (string) ( isset( $layer_in['slug'] ) ? $layer_in['slug'] : '' ) );

			if ( ! isset( $types[ $type ] ) || '' === $slug ) {
				continue;
			}

			$post = $this->find_layer_post( $types[ $type ], $slug );
			if ( ! $post instanceof \WP_Post ) {
				// Unknown slug — drop it rather than record an item the kitchen
				// cannot make.
				continue;
			}

			$coverage = sanitize_text_field( (string) ( isset( $layer_in['coverage'] ) ? $layer_in['coverage'] : 'whole' ) );
			if ( ! in_array( $coverage, $coverages, true ) ) {
				$coverage = 'whole';
			}

			$layers_out[] = [
				'type'           => $type,
				'slug'           => $slug,
				'name'           => $post->post_title,
				'post_id'        => (int) $post->ID,
				'coverage'       => $coverage,
				'coverage_label' => $this->coverage_label( $coverage ),
				'price'          => 0.0,
			];
		}

		if ( empty( $layers_out ) ) {
			return null;
		}

		// ── Size ──────────────────────────────────────────────────────────
		$size      = [ 'slug' => '', 'label' => '', 'post_id' => 0, 'diameter' => 0.0 ];
		$size_in   = isset( $item_in['size'] ) && is_array( $item_in['size'] ) ? $item_in['size'] : [];
		$size_slug = sanitize_title( (string) ( isset( $size_in['slug'] ) ? $size_in['slug'] : '' ) );

		if ( '' !== $size_slug ) {
			$size_post = $this->find_layer_post( 'pizzatier_sizes', $size_slug );
			if ( $size_post instanceof \WP_Post ) {
				$diameter = get_post_meta( $size_post->ID, '_pizzatier_diameter_inches', true );
				if ( '' === $diameter || false === $diameter ) {
					$diameter = get_post_meta( $size_post->ID, 'diameter_inches', true );
				}
				$size = [
					'slug'     => $size_slug,
					'label'    => $size_post->post_title,
					'post_id'  => (int) $size_post->ID,
					'diameter' => (float) $diameter,
				];
			}
		}

		$max_qty  = max( 1, OrderSettings::get_int( 'max_quantity' ) );
		$quantity = OrderSettings::is_on( 'quantity_enabled' )
			? min( $max_qty, max( 1, (int) ( isset( $item_in['quantity'] ) ? $item_in['quantity'] : 1 ) ) )
			: 1;

		$notes = sanitize_textarea_field( (string) ( isset( $item_in['notes'] ) ? $item_in['notes'] : '' ) );
		$notes = self::truncate( $notes, max( 1, OrderSettings::get_int( 'note_maxlength' ) ) );

		$item = [
			'instance_id'  => sanitize_text_field( (string) ( isset( $item_in['instance_id'] ) ? $item_in['instance_id'] : '' ) ),
			'template'     => sanitize_key( (string) ( isset( $item_in['template'] ) ? $item_in['template'] : '' ) ),
			'name'         => __( 'Custom Pizza', 'pizzatier' ),
			'preset_id'    => 0,
			'size'         => $size,
			'layers'       => $layers_out,
			'quantity'     => $quantity,
			'notes'        => $notes,
			'unit_price'   => 0.0,
			'line_total'   => 0.0,
			'price_source' => 'none',
		];

		/**
		 * Filter the unit price for a resolved order line item.
		 *
		 * Since 2.1.0, Orders\OrderPricing hooks this filter and prices the
		 * item from the product's price grid when WooCommerce and the
		 * calculator are available. Any other integration may also return a
		 * float to attach authoritative server-side pricing; an item nobody
		 * prices stays at zero with a price_source of 'none'.
		 *
		 * @param float|null $price Unit price, or null when unpriced.
		 * @param array      $item  The resolved item.
		 */
		$price = apply_filters( 'pizzatier_order_item_price', null, $item );

		if ( null !== $price && is_numeric( $price ) ) {
			$item['unit_price']   = round( (float) $price, 2 );
			$item['line_total']   = round( $item['unit_price'] * $quantity, 2 );
			$item['price_source'] = 'grid';
		}

		return $item;
	}

	/**
	 * Look up a layer post by slug within one CPT.
	 *
	 * @return \WP_Post|null
	 */
	private function find_layer_post( string $post_type, string $slug ) {
		$post = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			return $post;
		}

		// Some templates emit a sanitised title rather than the stored slug.
		$found = get_posts(
			[
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'title'            => $slug,
				'suppress_filters' => false,
			]
		);

		return ! empty( $found ) ? $found[0] : null;
	}

	/**
	 * Coverage fractions the site allows.
	 *
	 * @return string[]
	 */
	private function allowed_coverages(): array {
		if ( function_exists( 'pz_get_enabled_fractions' ) ) {
			return pz_get_enabled_fractions();
		}
		return [ 'whole' ];
	}

	/** Human label for a coverage fraction. */
	private function coverage_label( string $coverage ): string {
		$labels = [
			'whole'                 => __( 'Whole', 'pizzatier' ),
			'half-left'             => __( 'Left Half', 'pizzatier' ),
			'half-right'            => __( 'Right Half', 'pizzatier' ),
			'quarter-top-left'      => __( 'Top Left Quarter', 'pizzatier' ),
			'quarter-top-right'     => __( 'Top Right Quarter', 'pizzatier' ),
			'quarter-bottom-left'   => __( 'Bottom Left Quarter', 'pizzatier' ),
			'quarter-bottom-right'  => __( 'Bottom Right Quarter', 'pizzatier' ),
		];

		/**
		 * Filter the coverage fraction labels used on order records.
		 *
		 * @param array $labels fraction => label
		 */
		$labels = (array) apply_filters( 'pizzatier_order_coverage_labels', $labels );

		return isset( $labels[ $coverage ] ) ? (string) $labels[ $coverage ] : $coverage;
	}

	// -------------------------------------------------------------------------
	// Rate limiting
	// -------------------------------------------------------------------------

	/** Whether this IP is still under its hourly submission allowance. */
	private function check_rate_limit(): bool {
		$limit = OrderSettings::get_int( 'rate_limit' );
		if ( $limit <= 0 ) {
			return true;
		}
		$count = (int) get_transient( self::RATE_PREFIX . $this->ip_hash() );
		return $count < $limit;
	}

	/** Record one successful submission against this IP. */
	private function bump_rate_limit(): void {
		$limit = OrderSettings::get_int( 'rate_limit' );
		if ( $limit <= 0 ) {
			return;
		}
		$key   = self::RATE_PREFIX . $this->ip_hash();
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Hash of the requesting IP, used as the rate-limit key so no raw address
	 * ends up in the options table.
	 */
	private function ip_hash(): string {
		return md5( $this->get_ip() . wp_salt( 'nonce' ) );
	}

	/** Best-effort requesting IP address. */
	private function get_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	// -------------------------------------------------------------------------
	// Notification
	// -------------------------------------------------------------------------

	/**
	 * Email the store a plain-text summary of a new order.
	 *
	 * Deliberately excludes internal staff notes and any private customer notes
	 * — those are staff-screen only.
	 *
	 * @return bool Whether the mail was handed to the mailer. The "notify only"
	 *              route uses this to decide whether the record is safe to
	 *              discard, so a silent void return will not do.
	 */
	private function notify_admin( Order $order ): bool {
		$to = OrderSettings::admin_email();
		if ( '' === $to ) {
			return false;
		}

		$customer    = $order->get_customer();
		$fulfillment = $order->get_fulfillment();
		$methods     = Order::fulfillment_methods();
		$method      = isset( $methods[ $fulfillment['method'] ] ) ? $methods[ $fulfillment['method'] ] : $fulfillment['method'];

		$lines   = [];
		$lines[] = sprintf(
			/* translators: %s: order number. */
			__( 'New pizza order: %s', 'pizzatier' ),
			$order->get_number()
		);
		$lines[] = '';
		$lines[] = __( 'Customer', 'pizzatier' );
		$lines[] = '  ' . $customer['name'];
		if ( '' !== $customer['phone'] ) {
			$lines[] = '  ' . $customer['phone'];
		}
		if ( '' !== $customer['email'] ) {
			$lines[] = '  ' . $customer['email'];
		}
		$lines[] = '';
		$lines[] = __( 'Fulfilment', 'pizzatier' ) . ': ' . $method;
		if ( '' !== $fulfillment['requested_time'] ) {
			$lines[] = __( 'Requested for', 'pizzatier' ) . ': ' . $fulfillment['requested_time'];
		}
		$address = $order->get_address_line();
		if ( '' !== $address ) {
			$lines[] = __( 'Address', 'pizzatier' ) . ': ' . $address;
		}
		$lines[] = '';
		$lines[] = __( 'Order', 'pizzatier' );

		foreach ( $order->get_items() as $index => $item ) {
			$heading = '  ' . ( $index + 1 ) . '. ' . $item['name'];
			if ( '' !== $item['size']['label'] ) {
				$heading .= ' — ' . $item['size']['label'];
			}
			$heading .= ' × ' . $item['quantity'];
			$lines[]  = $heading;

			foreach ( $item['layers'] as $layer ) {
				$suffix  = ( 'whole' !== $layer['coverage'] && '' !== $layer['coverage_label'] )
					? ' (' . $layer['coverage_label'] . ')'
					: '';
				$lines[] = '       - ' . $layer['name'] . $suffix;
			}

			if ( '' !== $item['notes'] ) {
				$lines[] = '       ' . __( 'Note', 'pizzatier' ) . ': ' . $item['notes'];
			}
		}

		$customer_note = $order->get_customer_note();
		if ( '' !== $customer_note ) {
			$lines[] = '';
			$lines[] = __( 'Order note', 'pizzatier' ) . ': ' . $customer_note;
		}

		$lines[] = '';
		$lines[] = __( 'Manage this order:', 'pizzatier' );
		$lines[] = admin_url( 'admin.php?page=pizzatier-orders&order=' . $order->get_id() );

		$subject = sprintf(
			/* translators: 1: site name, 2: order number. */
			__( '[%1$s] New pizza order %2$s', 'pizzatier' ),
			wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ),
			$order->get_number()
		);

		$body = implode( "\n", $lines );

		/**
		 * Filter the new-order notification email.
		 *
		 * @param array $email   to, subject, body, headers
		 * @param Order $order   The order.
		 */
		$email = (array) apply_filters(
			'pizzatier_order_admin_email',
			[
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => [],
			],
			$order
		);

		return (bool) wp_mail( $email['to'], $email['subject'], $email['body'], $email['headers'] );
	}
}
