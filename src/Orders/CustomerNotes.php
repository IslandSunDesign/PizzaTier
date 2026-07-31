<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Private, staff-only notes about a customer.
 *
 * These are notes the store writes *about* a customer — "always asks for extra
 * napkins", "buzzer is broken, call on arrival", "disputed an order in March".
 * They are deliberately kept out of every customer-facing surface: receipts,
 * notification emails, the front-end checkout panel, the REST API and the
 * profile page as the customer themselves sees it.
 *
 * Three things enforce that:
 *   1. The meta key is underscore-prefixed, so WordPress treats it as protected:
 *      it never appears in the Custom Fields box and is not exposed over REST.
 *   2. Every read and write path here is gated on the orders capability.
 *   3. The field is only rendered for users who hold that capability, so a
 *      customer visiting their own profile never sees notes written about them.
 *
 * No dependency on PizzaTier or WooCommerce.
 */
class CustomerNotes {

	/**
	 * User meta key. Underscore-prefixed so WordPress treats it as protected,
	 * and brand-neutral so it survives any future rename.
	 */
	const META_KEY = '_pzt_customer_private_notes';

	/** Maximum stored length. */
	const MAX_LENGTH = 5000;

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Profile field — shown on both one's own profile and another user's,
		// but only to staff who can manage orders.
		add_action( 'show_user_profile', [ $this, 'render_profile_field' ] );
		add_action( 'edit_user_profile', [ $this, 'render_profile_field' ] );

		add_action( 'personal_options_update', [ $this, 'save_profile_field' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_profile_field' ] );

		// A compact indicator on the Users list screen.
		add_filter( 'manage_users_columns', [ $this, 'add_users_column' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'render_users_column' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Access control
	// -------------------------------------------------------------------------

	/** Whether the current user may read or write customer notes. */
	public static function can_manage(): bool {
		$can = current_user_can( OrderPostType::capability() );

		/**
		 * Filter whether the current user may see and edit private customer notes.
		 *
		 * @param bool $can Whether access is granted.
		 */
		return (bool) apply_filters( 'pizzatier_can_manage_customer_notes', $can );
	}

	// -------------------------------------------------------------------------
	// Read / write
	// -------------------------------------------------------------------------

	/**
	 * Read the private notes for a customer.
	 *
	 * Returns '' for anyone without the capability, so a stray call from a
	 * front-end template cannot leak the contents.
	 */
	public static function get( int $user_id ): string {
		if ( $user_id <= 0 || ! self::can_manage() ) {
			return '';
		}
		return (string) get_user_meta( $user_id, self::META_KEY, true );
	}

	/**
	 * Read the notes without a capability check.
	 *
	 * Only for code that has already established authorisation — the profile
	 * renderer and the order screen. Kept separate so the check in get() is
	 * never quietly bypassed by accident.
	 */
	private static function get_raw( int $user_id ): string {
		return $user_id > 0 ? (string) get_user_meta( $user_id, self::META_KEY, true ) : '';
	}

	/**
	 * Store the private notes for a customer.
	 *
	 * @return bool True when the write happened.
	 */
	public static function set( int $user_id, string $notes ): bool {
		if ( $user_id <= 0 || ! self::can_manage() ) {
			return false;
		}

		$notes = sanitize_textarea_field( $notes );

		// mbstring is not guaranteed on shared hosting.
		if ( function_exists( 'mb_substr' ) ) {
			$notes = mb_substr( $notes, 0, self::MAX_LENGTH );
		} else {
			$notes = substr( $notes, 0, self::MAX_LENGTH );
		}

		if ( '' === trim( $notes ) ) {
			delete_user_meta( $user_id, self::META_KEY );
		} else {
			update_user_meta( $user_id, self::META_KEY, $notes );
		}

		/**
		 * Fires after a customer's private notes are updated.
		 *
		 * @param int    $user_id Customer user ID.
		 * @param string $notes   The stored notes.
		 */
		do_action( 'pizzatier_customer_notes_updated', $user_id, $notes );

		return true;
	}

	/** Whether a customer has any notes recorded. */
	public static function has_notes( int $user_id ): bool {
		return '' !== trim( self::get_raw( $user_id ) );
	}

	// -------------------------------------------------------------------------
	// Linking an order to a customer account
	// -------------------------------------------------------------------------

	/**
	 * Work out which user account an order belongs to.
	 *
	 * Prefers the ID captured at checkout. Falls back to matching the email
	 * address, so notes still surface when a regular customer happened to order
	 * without logging in.
	 *
	 * @return int User ID, or 0 when the order cannot be tied to an account.
	 */
	public static function resolve_user_for_order( Order $order ): int {
		$user_id = $order->get_user_id();
		if ( $user_id > 0 ) {
			return $user_id;
		}

		$email = $order->get_customer()['email'];
		if ( '' === $email || ! is_email( $email ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );

		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}

	// -------------------------------------------------------------------------
	// Profile screen
	// -------------------------------------------------------------------------

	/**
	 * Render the private notes field on a user profile.
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public function render_profile_field( $user ): void {
		if ( ! ( $user instanceof \WP_User ) || ! self::can_manage() ) {
			return;
		}

		$notes = self::get_raw( (int) $user->ID );

		?>
		<h2 id="pizzatier-customer-notes"><?php esc_html_e( 'PizzaTier — Store Notes', 'pizzatier' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="pzt_customer_private_notes">
							<?php esc_html_e( 'Private customer notes', 'pizzatier' ); ?>
						</label>
					</th>
					<td>
						<?php wp_nonce_field( 'pizzatier_customer_notes_' . (int) $user->ID, 'pzt_customer_notes_nonce' ); ?>
						<textarea name="pzt_customer_private_notes"
						          id="pzt_customer_private_notes"
						          rows="6"
						          class="large-text"
						          maxlength="<?php echo esc_attr( (string) self::MAX_LENGTH ); ?>"
						          placeholder="<?php esc_attr_e( 'Anything the team should know when this customer orders…', 'pizzatier' ); ?>"><?php echo esc_textarea( $notes ); ?></textarea>
						<p class="description">
							<strong><?php esc_html_e( 'Staff only.', 'pizzatier' ); ?></strong>
							<?php esc_html_e( 'These notes are never shown to the customer. They do not appear on receipts, in order confirmation emails, or anywhere on the front end, and only users who can manage orders can read them.', 'pizzatier' ); ?>
						</p>
					</td>
				</tr>

				<?php $this->render_order_history( (int) $user->ID ); ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * A short list of the customer's recent orders, as a row in the profile table.
	 */
	private function render_order_history( int $user_id ): void {
		$orders = get_posts(
			[
				'post_type'      => OrderPostType::POST_TYPE,
				// Explicit list: the pzt-* statuses are excluded from search,
				// so 'any' would match nothing.
				'post_status'    => OrderStatuses::all(),
				'posts_per_page' => 5,
				'meta_key'       => Order::META_USER_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded to 5 rows on an admin profile screen.
				'meta_value'     => (string) $user_id,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( empty( $orders ) ) {
			return;
		}

		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Recent orders', 'pizzatier' ); ?></th>
			<td>
				<ul class="pzt-profile-orders">
					<?php foreach ( $orders as $post ) : ?>
						<?php
						$order = Order::get( (int) $post->ID );
						if ( ! $order ) {
							continue;
						}
						$timestamp = strtotime( $order->get_date_gmt() . ' UTC' );
						?>
						<li>
							<a href="<?php echo esc_url( Admin\OrdersPage::detail_url( $order->get_id() ) ); ?>">
								<?php echo esc_html( $order->get_number() ); ?>
							</a>
							<span class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: order status, 2: order date. */
										__( '%1$s — %2$s', 'pizzatier' ),
										$order->get_status_label(),
										$timestamp ? wp_date( (string) get_option( 'date_format' ), $timestamp ) : ''
									)
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</td>
		</tr>
		<?php
	}

	/**
	 * Persist the profile field.
	 *
	 * @param int $user_id User being saved.
	 */
	public function save_profile_field( $user_id ): void {
		$user_id = (int) $user_id;

		if ( ! self::can_manage() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['pzt_customer_notes_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['pzt_customer_notes_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'pizzatier_customer_notes_' . $user_id ) ) {
			return;
		}

		$notes = isset( $_POST['pzt_customer_private_notes'] )
			? sanitize_textarea_field( wp_unslash( $_POST['pzt_customer_private_notes'] ) )
			: '';

		self::set( $user_id, $notes );
	}

	// -------------------------------------------------------------------------
	// Users list column
	// -------------------------------------------------------------------------

	/**
	 * @param array $columns Existing column map.
	 */
	public function add_users_column( $columns ): array {
		if ( ! self::can_manage() ) {
			return (array) $columns;
		}
		$columns = (array) $columns;
		$columns['pzt_customer_notes'] = __( 'Store Notes', 'pizzatier' );
		return $columns;
	}

	/**
	 * @param string $output      Current column output.
	 * @param string $column_name Column being rendered.
	 * @param int    $user_id     User for this row.
	 */
	public function render_users_column( $output, $column_name, $user_id ): string {
		if ( 'pzt_customer_notes' !== $column_name ) {
			return (string) $output;
		}

		if ( ! self::can_manage() ) {
			return '';
		}

		$notes = trim( self::get_raw( (int) $user_id ) );
		if ( '' === $notes ) {
			return '<span class="pzt-orders-muted">&mdash;</span>';
		}

		// Show a short preview only. The full note stays on the profile screen
		// so long or sensitive notes are not broadcast across a shared display.
		$preview = wp_trim_words( $notes, 8, '…' );

		return sprintf(
			'<a href="%s#pizzatier-customer-notes" title="%s">%s</a>',
			esc_url( get_edit_user_link( (int) $user_id ) ),
			esc_attr__( 'Edit store notes', 'pizzatier' ),
			esc_html( $preview )
		);
	}
}
