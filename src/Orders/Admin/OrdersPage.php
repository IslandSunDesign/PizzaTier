<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\CustomerNotes;
use PizzaTier\Orders\Order;
use PizzaTier\Orders\OrderPostType;
use PizzaTier\Orders\OrderSettings;
use PizzaTier\Orders\OrderStatuses;

/**
 * Admin controller for the Pizza Orders screens.
 *
 * Three views live under one page slug:
 *   admin.php?page=pizzatier-orders                → list
 *   admin.php?page=pizzatier-orders&order=123      → detail
 *   admin.php?page=pizzatier-orders&view=settings  → ordering settings
 *
 * All state-changing requests are handled on `admin_init`, before any output,
 * so every action can finish with a clean redirect rather than leaving a
 * re-submittable POST in the browser's history.
 */
class OrdersPage {

	/** Admin page slug. */
	const SLUG = 'pizzatier-orders';

	/** Nonce action for row and detail actions. */
	const NONCE = 'pizzatier_order_action';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_handle_actions' ] );
		// The order CPT has no useful default edit screen (title support only),
		// so send anyone who lands there to the real detail view.
		add_action( 'admin_init', [ $this, 'maybe_redirect_cpt_edit' ] );
		// Since WP 5.6 an untrashed post is restored to 'draft' unless a filter
		// says otherwise. 'draft' is not a valid order status, so a restored
		// order would disappear from every view — put it back where it was.
		add_filter( 'wp_untrash_post_status', [ $this, 'untrash_to_previous_status' ], 10, 3 );
	}

	/**
	 * @param string $new_status      Status WordPress intends to restore to.
	 * @param int    $post_id         Post being untrashed.
	 * @param string $previous_status Status held before trashing.
	 */
	public function untrash_to_previous_status( $new_status, $post_id, $previous_status ) {
		if ( get_post_type( $post_id ) !== OrderPostType::POST_TYPE ) {
			return $new_status;
		}
		return OrderStatuses::is_valid( (string) $previous_status )
			? $previous_status
			: OrderStatuses::DEFAULT_STATUS;
	}

	/** Capability required for every screen and action here. */
	private static function cap(): string {
		return OrderPostType::capability();
	}

	// -------------------------------------------------------------------------
	// URLs
	// -------------------------------------------------------------------------

	public static function list_url( array $args = [] ): string {
		return add_query_arg(
			array_merge( [ 'page' => self::SLUG ], $args ),
			admin_url( 'admin.php' )
		);
	}

	public static function detail_url( int $order_id ): string {
		return self::list_url( [ 'order' => $order_id ] );
	}

	/** Nonce-protected URL for a single-order action. */
	public static function action_url( string $action, int $order_id ): string {
		return wp_nonce_url(
			self::list_url(
				[
					'pzt_action' => $action,
					'order_id'   => $order_id,
				]
			),
			self::NONCE
		);
	}

	// -------------------------------------------------------------------------
	// Action handling
	// -------------------------------------------------------------------------

	/**
	 * Handle every state-changing request for these screens.
	 * Runs on `admin_init`, before output.
	 */
	public function maybe_handle_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page routing only; each branch verifies its own nonce.
		if ( ! isset( $_REQUEST['page'] ) || self::SLUG !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( self::cap() ) ) {
			return;
		}

		$this->handle_settings_save();
		$this->handle_detail_actions();
		$this->handle_bulk_actions();
		$this->handle_row_action();
	}

	/**
	 * Save the ordering settings form.
	 */
	private function handle_settings_save(): void {
		if ( ! isset( $_POST['pizzatier_orders_settings_save'] ) ) {
			return;
		}

		check_admin_referer( 'pizzatier_orders_settings' );

		$checkboxes = [
			'enabled', 'require_name', 'require_phone', 'require_email',
			'login_required', 'notes_enabled', 'quantity_enabled',
			'size_enabled', 'request_time', 'notify_admin',
		];

		foreach ( $checkboxes as $key ) {
			update_option(
				OrderSettings::PREFIX . $key,
				isset( $_POST[ 'pzt_' . $key ] ) ? 'yes' : 'no'
			);
		}

		$texts = [
			'button_label'     => 'sanitize_text_field',
			'note_placeholder' => 'sanitize_text_field',
			'confirm_message'  => 'sanitize_text_field',
			'admin_email'      => 'sanitize_email',
		];

		foreach ( $texts as $key => $sanitizer ) {
			if ( isset( $_POST[ 'pzt_' . $key ] ) ) {
				update_option(
					OrderSettings::PREFIX . $key,
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
					call_user_func( $sanitizer, wp_unslash( $_POST[ 'pzt_' . $key ] ) )
				);
			}
		}

		$ints = [ 'max_quantity' => 99, 'note_maxlength' => 2000, 'rate_limit' => 1000, 'retention_months' => 120 ];
		foreach ( $ints as $key => $ceiling ) {
			if ( isset( $_POST[ 'pzt_' . $key ] ) ) {
				$value = absint( wp_unslash( $_POST[ 'pzt_' . $key ] ) );
				update_option( OrderSettings::PREFIX . $key, min( $ceiling, $value ) );
			}
		}

		// Enabled fulfilment methods.
		$available = array_keys( Order::fulfillment_methods() );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
		$chosen    = isset( $_POST['pzt_fulfillment'] ) ? (array) wp_unslash( $_POST['pzt_fulfillment'] ) : [];
		$chosen    = array_values( array_intersect( array_map( 'sanitize_key', $chosen ), $available ) );
		if ( empty( $chosen ) ) {
			$chosen = [ 'pickup' ];
		}
		update_option( OrderSettings::PREFIX . 'fulfillment', $chosen );

		// Order route — where a placed order goes.
		//
		// The legacy `action_bar_mode` in the cart-and-pricing option is written
		// alongside it, kept in step rather than migrated away. Its sanitiser
		// still runs on the commerce settings screen and third-party code still
		// reads it, so leaving the two to drift is exactly the bug that made
		// this setting confusing in the first place.
		if ( isset( $_POST['pzt_route'] ) ) {
			$pzt_route = sanitize_key( wp_unslash( $_POST['pzt_route'] ) );

			if ( \PizzaTier\Orders\OrderRoute::is_valid( $pzt_route ) ) {
				update_option( OrderSettings::PREFIX . \PizzaTier\Orders\OrderRoute::KEY, $pzt_route );

				$pzt_mirror = [
					\PizzaTier\Orders\OrderRoute::WOOCOMMERCE  => \PizzaTier\Orders\ActionBarMode::WOOCOMMERCE,
					\PizzaTier\Orders\OrderRoute::WOO_CHECKOUT => \PizzaTier\Orders\ActionBarMode::WOOCOMMERCE,
					\PizzaTier\Orders\OrderRoute::ORDERS       => \PizzaTier\Orders\ActionBarMode::ORDERS,
					\PizzaTier\Orders\OrderRoute::BOTH         => \PizzaTier\Orders\ActionBarMode::ORDERS,
					\PizzaTier\Orders\OrderRoute::NOTIFY       => \PizzaTier\Orders\ActionBarMode::ORDERS,
				];

				$pzt_commerce = get_option( \PizzaTier\Commerce\Admin\Settings::OPTION_NAME, [] );
				if ( ! is_array( $pzt_commerce ) ) {
					$pzt_commerce = [];
				}
				$pzt_commerce[ \PizzaTier\Orders\ActionBarMode::KEY ] = $pzt_mirror[ $pzt_route ];
				update_option( \PizzaTier\Commerce\Admin\Settings::OPTION_NAME, $pzt_commerce );
			}
		}

		// Product the cart routes add to when the builder is not on a product page.
		if ( isset( $_POST['pzt_cart_product_id'] ) ) {
			$pzt_product_id = absint( wp_unslash( $_POST['pzt_cart_product_id'] ) );
			if ( 0 === $pzt_product_id || \PizzaTier\Orders\OrderProduct::is_pizza_product( $pzt_product_id ) ) {
				update_option( OrderSettings::PREFIX . \PizzaTier\Orders\OrderProduct::SETTING_KEY, $pzt_product_id );
			}
		}

		// Webhook. An unparseable URL is stored as empty rather than rejected
		// silently, so a typo switches the webhook off instead of leaving the
		// previous endpoint quietly receiving orders.
		if ( isset( $_POST['pzt_webhook_url'] ) ) {
			$pzt_hook = trim( esc_url_raw( wp_unslash( $_POST['pzt_webhook_url'] ) ) );
			if ( '' !== $pzt_hook && ! wp_http_validate_url( $pzt_hook ) ) {
				$pzt_hook = '';
			}
			update_option( OrderSettings::PREFIX . 'webhook_url', $pzt_hook );
		}

		if ( isset( $_POST['pzt_webhook_secret'] ) ) {
			update_option(
				OrderSettings::PREFIX . 'webhook_secret',
				sanitize_text_field( wp_unslash( $_POST['pzt_webhook_secret'] ) )
			);
		}

		// Initial status for new orders.
		if ( isset( $_POST['pzt_initial_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['pzt_initial_status'] ) );
			if ( OrderStatuses::is_valid( $status ) ) {
				update_option( OrderSettings::PREFIX . 'initial_status', $status );
			}
		}

		// Uninstall behaviour lives outside the orders prefix because
		// uninstall.php reads it without loading the plugin.
		update_option(
			'pizzatier_setting_delete_orders_on_uninstall',
			isset( $_POST['pzt_delete_on_uninstall'] ) ? 'yes' : 'no'
		);

		wp_safe_redirect( self::list_url( [ 'view' => 'settings', 'saved' => 1 ] ) );
		exit;
	}

	/**
	 * Status changes and staff notes submitted from the detail view.
	 */
	private function handle_detail_actions(): void {
		if ( ! isset( $_POST['pizzatier_order_detail_action'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$order    = Order::get( $order_id );

		if ( ! $order ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['pizzatier_order_detail_action'] ) );

		if ( 'set_status' === $action ) {
			$status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
			$note   = isset( $_POST['status_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['status_note'] ) ) : '';
			$order->set_status( $status, $note );
		}

		if ( 'add_note' === $action ) {
			$note = isset( $_POST['staff_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['staff_note'] ) ) : '';
			$order->add_staff_note( $note );
		}

		if ( 'save_customer_notes' === $action ) {
			// The user ID travels in the form rather than being re-derived, but
			// it is still checked against the order so a tampered field cannot
			// write notes onto an unrelated account.
			$posted_user = isset( $_POST['customer_user_id'] ) ? absint( wp_unslash( $_POST['customer_user_id'] ) ) : 0;
			$linked_user = CustomerNotes::resolve_user_for_order( $order );

			if ( $posted_user > 0 && $posted_user === $linked_user ) {
				$notes = isset( $_POST['customer_private_notes'] )
					? sanitize_textarea_field( wp_unslash( $_POST['customer_private_notes'] ) )
					: '';
				CustomerNotes::set( $posted_user, $notes );
			}
		}

		wp_safe_redirect( self::detail_url( $order_id ) );
		exit;
	}

	/**
	 * Bulk status changes and trashing from the list screen.
	 */
	private function handle_bulk_actions(): void {
		if ( ! isset( $_POST['order_ids'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-pizza_orders' );

		$action = '';
		foreach ( [ 'action', 'action2' ] as $field ) {
			if ( isset( $_POST[ $field ] ) && '-1' !== $_POST[ $field ] ) {
				$action = sanitize_key( wp_unslash( $_POST[ $field ] ) );
				break;
			}
		}

		if ( '' === $action ) {
			return;
		}

		$ids     = array_map( 'absint', (array) wp_unslash( $_POST['order_ids'] ) );
		$changed = 0;

		foreach ( $ids as $id ) {
			// Trashed orders still resolve through Order::get(), so restore and
			// permanent delete are handled before the model is required.
			if ( 'untrash' === $action ) {
				if ( wp_untrash_post( $id ) ) {
					$changed++;
				}
				continue;
			}

			if ( 'delete' === $action ) {
				if ( wp_delete_post( $id, true ) ) {
					$changed++;
				}
				continue;
			}

			$order = Order::get( $id );
			if ( ! $order ) {
				continue;
			}

			if ( 'trash' === $action ) {
				if ( wp_trash_post( $id ) ) {
					$changed++;
				}
				continue;
			}

			if ( 0 === strpos( $action, 'status_' ) ) {
				$status = substr( $action, 7 );
				if ( $order->set_status( $status, __( 'Changed in bulk.', 'pizzatier' ) ) ) {
					$changed++;
				}
			}
		}

		wp_safe_redirect( self::list_url( [ 'view' => 'classic', 'changed' => $changed ] ) );
		exit;
	}

	/**
	 * Single-row trash / untrash / delete links.
	 */
	private function handle_row_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked immediately below.
		if ( ! isset( $_GET['pzt_action'], $_GET['order_id'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$action   = sanitize_key( wp_unslash( $_GET['pzt_action'] ) );
		$order_id = absint( wp_unslash( $_GET['order_id'] ) );

		if ( ! Order::get( $order_id ) && 'untrash' !== $action ) {
			return;
		}

		if ( 'trash' === $action ) {
			wp_trash_post( $order_id );
		} elseif ( 'untrash' === $action ) {
			wp_untrash_post( $order_id );
		} elseif ( 'delete' === $action ) {
			wp_delete_post( $order_id, true );
		}

		wp_safe_redirect( self::list_url( [ 'view' => 'classic' ] ) );
		exit;
	}

	/**
	 * Redirect the bare CPT edit screen to the purpose-built detail view.
	 */
	public function maybe_redirect_cpt_edit(): void {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post'] ) );
			if ( get_post_type( $post_id ) === OrderPostType::POST_TYPE ) {
				wp_safe_redirect( self::detail_url( $post_id ) );
				exit;
			}
		}

		if ( 'edit.php' === $pagenow
			&& isset( $_GET['post_type'] )
			&& OrderPostType::POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) {
			wp_safe_redirect( self::list_url() );
			exit;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/** Entry point called by AdminMenu. */
	public function render(): void {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view orders.', 'pizzatier' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view routing.
		$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;
		$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap pzt-orders">';

		if ( 'settings' === $view ) {
			$this->render_settings();
		} elseif ( $order_id > 0 ) {
			$this->render_detail( $order_id );
		} elseif ( 'classic' === $view ) {
			$this->render_list();
		} else {
			// Default view since 2.2.0: the live dashboard + AJAX list.
			( new OrdersDashboard() )->render();
		}

		echo '</div>';
	}

	// ── List ──────────────────────────────────────────────────────────────

	private function render_list(): void {
		$table = new OrdersListTable();
		$table->prepare_items();

		?>
		<div class="pzt-orders-header">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pizza Orders', 'pizzatier' ); ?> <span class="pzt-orders-muted">(<?php esc_html_e( 'classic list', 'pizzatier' ); ?>)</span></h1>
			<a href="<?php echo esc_url( self::list_url() ); ?>" class="page-title-action">
				<?php esc_html_e( '← Dashboard', 'pizzatier' ); ?>
			</a>
			<a href="<?php echo esc_url( self::list_url( [ 'view' => 'settings' ] ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Ordering Settings', 'pizzatier' ); ?>
			</a>
		</div>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$changed = isset( $_GET['changed'] ) ? absint( wp_unslash( $_GET['changed'] ) ) : 0;
		if ( $changed > 0 ) :
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of orders updated. */
						esc_html( _n( '%d order updated.', '%d orders updated.', $changed, 'pizzatier' ) ),
						(int) $changed
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! OrderSettings::is_on( 'enabled' ) ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Online ordering is currently switched off, so no new orders can come in.', 'pizzatier' ); ?>
					<a href="<?php echo esc_url( self::list_url( [ 'view' => 'settings' ] ) ); ?>">
						<?php esc_html_e( 'Turn it on', 'pizzatier' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php
			// No wp_nonce_field() here on purpose — WP_List_Table emits the
			// bulk-pizza_orders nonce itself, and a second field with the same
			// name would just duplicate it.
			echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
			echo '<input type="hidden" name="view" value="classic" />';
			$table->views();
			$table->search_box( __( 'Search orders', 'pizzatier' ), 'pizzatier-order-search' );
			$table->display();
			?>
		</form>
		<?php
	}

	// ── Detail ────────────────────────────────────────────────────────────

	private function render_detail( int $order_id ): void {
		$order = Order::get( $order_id );

		if ( ! $order ) {
			echo '<h1>' . esc_html__( 'Order not found', 'pizzatier' ) . '</h1>';
			echo '<p><a href="' . esc_url( self::list_url() ) . '">' . esc_html__( '← Back to orders', 'pizzatier' ) . '</a></p>';
			return;
		}

		$customer    = $order->get_customer();
		$fulfillment = $order->get_fulfillment();
		$methods     = Order::fulfillment_methods();
		$totals      = $order->get_totals();
		$timestamp   = strtotime( $order->get_date_gmt() . ' UTC' );

		?>
		<div class="pzt-orders-header">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $order->get_number() ); ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in status_badge().
				echo self::status_badge( $order->get_status() );
				?>
			</h1>
			<a href="<?php echo esc_url( self::list_url() ); ?>" class="page-title-action">
				<?php esc_html_e( '← All orders', 'pizzatier' ); ?>
			</a>
		</div>

		<div class="pzt-order-detail">

			<div class="pzt-order-detail__main">

				<!-- Items -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Order', 'pizzatier' ); ?></h2>

					<?php foreach ( $order->get_items() as $index => $item ) : ?>
					<div class="pzt-order-item">
						<div class="pzt-order-item__head">
							<span class="pzt-order-item__name">
								<?php echo esc_html( ( $index + 1 ) . '. ' . $item['name'] ); ?>
								<?php if ( '' !== $item['size']['label'] ) : ?>
									<span class="pzt-order-item__size"><?php echo esc_html( $item['size']['label'] ); ?></span>
								<?php endif; ?>
							</span>
							<span class="pzt-order-item__qty">× <?php echo esc_html( (string) $item['quantity'] ); ?></span>
						</div>

						<?php if ( ! empty( $item['layers'] ) ) : ?>
						<ul class="pzt-order-layers">
							<?php foreach ( $item['layers'] as $layer ) : ?>
							<li class="pzt-order-layer pzt-order-layer--<?php echo esc_attr( $layer['type'] ); ?>">
								<span class="pzt-order-layer__type"><?php echo esc_html( $layer['type'] ); ?></span>
								<span class="pzt-order-layer__name">
									<?php if ( $layer['post_id'] > 0 ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $layer['post_id'] ) ); ?>">
											<?php echo esc_html( $layer['name'] ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $layer['name'] ); ?>
									<?php endif; ?>
								</span>
								<?php if ( 'whole' !== $layer['coverage'] ) : ?>
									<span class="pzt-order-layer__coverage"><?php echo esc_html( $layer['coverage_label'] ); ?></span>
								<?php endif; ?>
								<?php if ( $layer['price'] > 0 ) : ?>
									<span class="pzt-order-layer__price"><?php echo esc_html( self::format_money( $layer['price'], $totals['currency'] ) ); ?></span>
								<?php endif; ?>
							</li>
							<?php endforeach; ?>
						</ul>
						<?php endif; ?>

						<?php if ( '' !== $item['notes'] ) : ?>
						<p class="pzt-order-item__note">
							<strong><?php esc_html_e( 'Note:', 'pizzatier' ); ?></strong>
							<?php echo esc_html( $item['notes'] ); ?>
						</p>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>

					<?php if ( $totals['total'] > 0 ) : ?>
					<table class="pzt-order-totals">
						<tbody>
							<?php
							$rows = [
								__( 'Subtotal', 'pizzatier' )     => $totals['subtotal'],
								__( 'Delivery', 'pizzatier' )     => $totals['delivery_fee'],
								__( 'Tax', 'pizzatier' )          => $totals['tax'],
								__( 'Tip', 'pizzatier' )          => $totals['tip'],
							];
							foreach ( $rows as $label => $amount ) :
								if ( $amount <= 0 ) { continue; }
								?>
								<tr>
									<th><?php echo esc_html( $label ); ?></th>
									<td><?php echo esc_html( self::format_money( $amount, $totals['currency'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if ( $totals['discount'] > 0 ) : ?>
								<tr>
									<th><?php esc_html_e( 'Discount', 'pizzatier' ); ?></th>
									<td>&minus;<?php echo esc_html( self::format_money( $totals['discount'], $totals['currency'] ) ); ?></td>
								</tr>
							<?php endif; ?>
							<tr class="pzt-order-totals__grand">
								<th><?php esc_html_e( 'Total', 'pizzatier' ); ?></th>
								<td><?php echo esc_html( self::format_money( $totals['total'], $totals['currency'] ) ); ?></td>
							</tr>
						</tbody>
					</table>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $order->get_customer_note() ) : ?>
				<!-- Customer note -->
				<div class="pzt-order-card pzt-order-card--note">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Note from the customer', 'pizzatier' ); ?></h2>
					<p><?php echo esc_html( $order->get_customer_note() ); ?></p>
				</div>
				<?php endif; ?>

				<!-- Staff notes -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Internal notes', 'pizzatier' ); ?></h2>
					<p class="pzt-orders-muted"><?php esc_html_e( 'Only staff can see these. They never appear on receipts or in emails to the customer.', 'pizzatier' ); ?></p>

					<?php $staff_notes = $order->get_staff_notes(); ?>
					<?php if ( ! empty( $staff_notes ) ) : ?>
					<ul class="pzt-order-notes">
						<?php foreach ( array_reverse( $staff_notes ) as $note ) : ?>
						<li class="pzt-order-note">
							<p class="pzt-order-note__body"><?php echo esc_html( $note['note'] ); ?></p>
							<p class="pzt-order-note__meta">
								<?php echo esc_html( self::describe_user( (int) $note['user_id'] ) ); ?>
								&middot;
								<?php echo esc_html( self::format_time( $note['time'] ) ); ?>
							</p>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>

					<form method="post" class="pzt-order-form">
						<?php wp_nonce_field( self::NONCE ); ?>
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
						<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
						<input type="hidden" name="pizzatier_order_detail_action" value="add_note" />
						<textarea name="staff_note" rows="2" class="large-text"
						          placeholder="<?php esc_attr_e( 'Add an internal note…', 'pizzatier' ); ?>"></textarea>
						<p><button type="submit" class="button"><?php esc_html_e( 'Add Note', 'pizzatier' ); ?></button></p>
					</form>
				</div>

				<!-- History -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'History', 'pizzatier' ); ?></h2>
					<ul class="pzt-order-history">
						<?php foreach ( array_reverse( $order->get_status_history() ) as $entry ) : ?>
						<li class="pzt-order-history__item">
							<span class="pzt-order-history__change">
								<?php if ( '' !== $entry['from'] ) : ?>
									<?php echo esc_html( OrderStatuses::label( $entry['from'] ) ); ?>
									&rarr;
								<?php endif; ?>
								<strong><?php echo esc_html( OrderStatuses::label( $entry['to'] ) ); ?></strong>
							</span>
							<span class="pzt-order-history__meta">
								<?php echo esc_html( self::describe_user( (int) $entry['user_id'] ) ); ?>
								&middot;
								<?php echo esc_html( self::format_time( $entry['time'] ) ); ?>
							</span>
							<?php if ( '' !== $entry['note'] ) : ?>
								<span class="pzt-order-history__note"><?php echo esc_html( $entry['note'] ); ?></span>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>

			</div><!-- /.pzt-order-detail__main -->

			<div class="pzt-order-detail__side">

				<!-- Status -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Status', 'pizzatier' ); ?></h2>
					<form method="post" class="pzt-order-form">
						<?php wp_nonce_field( self::NONCE ); ?>
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
						<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
						<input type="hidden" name="pizzatier_order_detail_action" value="set_status" />

						<select name="new_status" class="widefat">
							<?php foreach ( OrderStatuses::labels() as $status => $label ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, $order->get_status() ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
							<?php endforeach; ?>
						</select>

						<p class="description"><?php echo esc_html( OrderStatuses::description( $order->get_status() ) ); ?></p>

						<textarea name="status_note" rows="2" class="large-text"
						          placeholder="<?php esc_attr_e( 'Why? (optional)', 'pizzatier' ); ?>"></textarea>

						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Update Status', 'pizzatier' ); ?></button></p>
					</form>
				</div>

				<!-- Customer -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Customer', 'pizzatier' ); ?></h2>
					<dl class="pzt-order-facts">
						<?php if ( '' !== $customer['name'] ) : ?>
							<dt><?php esc_html_e( 'Name', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $customer['name'] ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $customer['phone'] ) : ?>
							<dt><?php esc_html_e( 'Phone', 'pizzatier' ); ?></dt>
							<dd><a href="tel:<?php echo esc_attr( rawurlencode( $customer['phone'] ) ); ?>"><?php echo esc_html( $customer['phone'] ); ?></a></dd>
						<?php endif; ?>
						<?php if ( '' !== $customer['email'] ) : ?>
							<dt><?php esc_html_e( 'Email', 'pizzatier' ); ?></dt>
							<dd><a href="mailto:<?php echo esc_attr( $customer['email'] ); ?>"><?php echo esc_html( $customer['email'] ); ?></a></dd>
						<?php endif; ?>
						<?php if ( '' !== $customer['company'] ) : ?>
							<dt><?php esc_html_e( 'Company', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $customer['company'] ); ?></dd>
						<?php endif; ?>
					</dl>

					<?php
					$linked_user = CustomerNotes::resolve_user_for_order( $order );
					$matched_by_email = ( $linked_user > 0 && 0 === $order->get_user_id() );

					if ( $linked_user > 0 ) :
						?>
						<p>
							<a href="<?php echo esc_url( get_edit_user_link( $linked_user ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Open customer profile', 'pizzatier' ); ?>
							</a>
						</p>
						<?php if ( $matched_by_email ) : ?>
							<p class="pzt-orders-muted">
								<?php esc_html_e( 'Ordered as a guest, matched to this account by email address.', 'pizzatier' ); ?>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<p class="pzt-orders-muted"><?php esc_html_e( 'Ordered as a guest, with no matching account.', 'pizzatier' ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( $linked_user > 0 && CustomerNotes::can_manage() ) : ?>
				<!-- Private customer notes. Staff only: never rendered on any
				     customer-facing surface, and never included in order email. -->
				<div class="pzt-order-card pzt-order-card--private">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Private customer notes', 'pizzatier' ); ?></h2>
					<p class="pzt-orders-muted">
						<?php esc_html_e( 'About this customer, across all their orders. The customer never sees these.', 'pizzatier' ); ?>
					</p>
					<form method="post" class="pzt-order-form">
						<?php wp_nonce_field( self::NONCE ); ?>
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
						<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
						<input type="hidden" name="customer_user_id" value="<?php echo esc_attr( (string) $linked_user ); ?>" />
						<input type="hidden" name="pizzatier_order_detail_action" value="save_customer_notes" />
						<textarea name="customer_private_notes" rows="5" class="large-text"
						          maxlength="<?php echo esc_attr( (string) CustomerNotes::MAX_LENGTH ); ?>"
						          placeholder="<?php esc_attr_e( 'Anything the team should know when this customer orders…', 'pizzatier' ); ?>"><?php echo esc_textarea( CustomerNotes::get( $linked_user ) ); ?></textarea>
						<p><button type="submit" class="button"><?php esc_html_e( 'Save Notes', 'pizzatier' ); ?></button></p>
					</form>
				</div>
				<?php endif; ?>

				<!-- Fulfilment -->
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Fulfilment', 'pizzatier' ); ?></h2>
					<dl class="pzt-order-facts">
						<dt><?php esc_html_e( 'Method', 'pizzatier' ); ?></dt>
						<dd>
							<?php
							echo esc_html(
								isset( $methods[ $fulfillment['method'] ] )
									? $methods[ $fulfillment['method'] ]
									: $fulfillment['method']
							);
							?>
						</dd>

						<?php if ( '' !== $fulfillment['requested_time'] ) : ?>
							<dt><?php esc_html_e( 'Requested for', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $fulfillment['requested_time'] ); ?></dd>
						<?php endif; ?>

						<?php $address = $order->get_address_line(); ?>
						<?php if ( '' !== $address ) : ?>
							<dt><?php esc_html_e( 'Address', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $address ); ?></dd>
						<?php endif; ?>

						<?php if ( '' !== $fulfillment['instructions'] ) : ?>
							<dt><?php esc_html_e( 'Instructions', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $fulfillment['instructions'] ); ?></dd>
						<?php endif; ?>

						<?php if ( '' !== $fulfillment['table'] ) : ?>
							<dt><?php esc_html_e( 'Table', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $fulfillment['table'] ); ?></dd>
						<?php endif; ?>

						<dt><?php esc_html_e( 'Placed', 'pizzatier' ); ?></dt>
						<dd>
							<?php
							echo esc_html(
								$timestamp
									? wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp )
									: '—'
							);
							?>
						</dd>
					</dl>
				</div>

				<!-- Provenance -->
				<?php $source = $order->get_source(); ?>
				<div class="pzt-order-card">
					<h2 class="pzt-order-card__title"><?php esc_html_e( 'Where it came from', 'pizzatier' ); ?></h2>
					<dl class="pzt-order-facts pzt-order-facts--small">
						<dt><?php esc_html_e( 'Source', 'pizzatier' ); ?></dt>
						<dd><?php echo esc_html( $source['origin'] ); ?></dd>
						<?php if ( '' !== $source['template'] ) : ?>
							<dt><?php esc_html_e( 'Template', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $source['template'] ); ?></dd>
						<?php endif; ?>
						<?php if ( '' !== $source['url'] ) : ?>
							<dt><?php esc_html_e( 'Page', 'pizzatier' ); ?></dt>
							<dd><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $source['url'] ); ?></a></dd>
						<?php endif; ?>
						<?php if ( '' !== $source['ip'] ) : ?>
							<dt><?php esc_html_e( 'IP', 'pizzatier' ); ?></dt>
							<dd><?php echo esc_html( $source['ip'] ); ?></dd>
						<?php endif; ?>
					</dl>
				</div>

			</div><!-- /.pzt-order-detail__side -->
		</div><!-- /.pzt-order-detail -->
		<?php
	}

	// ── Settings ──────────────────────────────────────────────────────────

	private function render_settings(): void {
		$methods  = Order::fulfillment_methods();
		$enabled  = (array) OrderSettings::get( 'fulfillment' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$saved = isset( $_GET['saved'] );

		?>
		<div class="pzt-orders-header">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ordering Settings', 'pizzatier' ); ?></h1>
			<a href="<?php echo esc_url( self::list_url() ); ?>" class="page-title-action">
				<?php esc_html_e( '← All orders', 'pizzatier' ); ?>
			</a>
		</div>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pizzatier' ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pizzatier_orders_settings' ); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
			<input type="hidden" name="view" value="settings" />
			<input type="hidden" name="pizzatier_orders_settings_save" value="1" />

			<h2><?php esc_html_e( 'General', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					$this->checkbox_row( 'enabled', __( 'Accept orders', 'pizzatier' ), __( 'Show the order bar in the builder and accept new orders.', 'pizzatier' ) );
					$this->text_row( 'button_label', __( 'Button label', 'pizzatier' ), OrderSettings::button_label() );
					$this->checkbox_row( 'login_required', __( 'Require login', 'pizzatier' ), __( 'Only logged-in customers may place orders.', 'pizzatier' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'When a customer orders', 'pizzatier' ); ?></th>
						<td>
							<?php
							$pzt_route        = \PizzaTier\Orders\OrderRoute::get();
							$pzt_labels       = \PizzaTier\Orders\OrderRoute::labels();
							$pzt_descriptions = \PizzaTier\Orders\OrderRoute::descriptions();
							$pzt_has_woo      = class_exists( 'WooCommerce' );
							?>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Where orders are sent', 'pizzatier' ); ?></legend>
								<?php foreach ( $pzt_labels as $pzt_key => $pzt_label ) : ?>
									<?php $pzt_blocked = \PizzaTier\Orders\OrderRoute::requires_woocommerce( $pzt_key ) && ! $pzt_has_woo; ?>
									<label class="pzt-orders-check">
										<input type="radio" name="pzt_route"
										       value="<?php echo esc_attr( $pzt_key ); ?>"
										       <?php checked( $pzt_key, $pzt_route ); ?>
										       <?php disabled( $pzt_blocked ); ?> />
										<strong><?php echo esc_html( $pzt_label ); ?></strong>
									</label>
									<p class="description" style="margin:0 0 .9em 1.9em;">
										<?php echo esc_html( isset( $pzt_descriptions[ $pzt_key ] ) ? $pzt_descriptions[ $pzt_key ] : '' ); ?>
									</p>
								<?php endforeach; ?>
							</fieldset>
							<?php if ( ! $pzt_has_woo ) : ?>
								<p class="description">
									<em><?php esc_html_e( 'The cart routes need WooCommerce installed and active.', 'pizzatier' ); ?></em>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $pzt_has_woo ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pizza product', 'pizzatier' ); ?></th>
						<td>
							<?php
							$pzt_products = \PizzaTier\Orders\OrderProduct::choices();
							$pzt_chosen   = \PizzaTier\Orders\OrderSettings::get_int( \PizzaTier\Orders\OrderProduct::SETTING_KEY );
							?>
							<?php if ( empty( $pzt_products ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'No pizza products found. Create a product and choose a builder template on it before using a cart route away from a product page.', 'pizzatier' ); ?>
								</p>
							<?php else : ?>
								<select name="pzt_cart_product_id">
									<option value="0"><?php esc_html_e( '— None —', 'pizzatier' ); ?></option>
									<?php foreach ( $pzt_products as $pzt_pid => $pzt_title ) : ?>
										<option value="<?php echo esc_attr( (string) $pzt_pid ); ?>" <?php selected( (int) $pzt_pid, $pzt_chosen ); ?>>
											<?php echo esc_html( $pzt_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Which product the cart routes add to when the builder is on a normal page rather than a product page. On a product page the product itself is always used and this is ignored.', 'pizzatier' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'New order status', 'pizzatier' ); ?></th>
						<td>
							<select name="pzt_initial_status">
								<?php foreach ( OrderStatuses::labels() as $status => $label ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, OrderSettings::initial_status() ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The status every new order starts in.', 'pizzatier' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Fulfilment', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Methods offered', 'pizzatier' ); ?></th>
						<td>
							<?php foreach ( $methods as $key => $label ) : ?>
							<label class="pzt-orders-check">
								<input type="checkbox" name="pzt_fulfillment[]" value="<?php echo esc_attr( $key ); ?>"
								       <?php checked( in_array( $key, $enabled, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label><br />
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Delivery asks for an address; dine-in asks for a table number.', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<?php
					$this->checkbox_row( 'request_time', __( 'Ask for a time', 'pizzatier' ), __( 'Let customers say when they need the order.', 'pizzatier' ) );
					$this->checkbox_row( 'size_enabled', __( 'Show size picker', 'pizzatier' ), __( 'Offer your Size options during checkout.', 'pizzatier' ) );
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Customer details', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					$this->checkbox_row( 'require_name', __( 'Require name', 'pizzatier' ), '' );
					$this->checkbox_row( 'require_phone', __( 'Require phone', 'pizzatier' ), '' );
					$this->checkbox_row( 'require_email', __( 'Require email', 'pizzatier' ), '' );
					if ( ! OrderSettings::is_on( 'require_email' ) ) :
						?>
						<tr>
							<th scope="row"></th>
							<td>
								<div class="notice notice-warning inline" style="margin:0;padding:8px 12px;">
									<p style="margin:0;">
										<strong><?php esc_html_e( 'Personal-data requests:', 'pizzatier' ); ?></strong>
										<?php esc_html_e( 'WordPress finds personal data by email address. Orders placed without one cannot be located by Tools → Export or Erase Personal Data, and have to be found by hand from the Orders screen. Require an email if you need those requests to be fully automatic.', 'pizzatier' ); ?>
									</p>
								</div>
							</td>
						</tr>
						<?php
					endif;
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Notes and quantity', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					$this->checkbox_row( 'notes_enabled', __( 'Allow order notes', 'pizzatier' ), __( 'Let customers add special requests for the kitchen.', 'pizzatier' ) );
					$this->text_row( 'note_placeholder', __( 'Note placeholder', 'pizzatier' ), OrderSettings::note_placeholder() );
					$this->number_row( 'note_maxlength', __( 'Note length limit', 'pizzatier' ), 1, 2000 );
					$this->checkbox_row( 'quantity_enabled', __( 'Show quantity stepper', 'pizzatier' ), '' );
					$this->number_row( 'max_quantity', __( 'Maximum quantity', 'pizzatier' ), 1, 99 );
					?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Notifications and abuse control', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					$this->checkbox_row( 'notify_admin', __( 'Email the store', 'pizzatier' ), __( 'Send a summary to the store whenever an order arrives.', 'pizzatier' ) );
					$this->text_row( 'admin_email', __( 'Notification address', 'pizzatier' ), OrderSettings::admin_email() );
					$this->text_row( 'confirm_message', __( 'Confirmation message', 'pizzatier' ), OrderSettings::confirm_message() );
					$this->number_row( 'rate_limit', __( 'Orders per hour per visitor', 'pizzatier' ), 0, 1000, __( 'Set to 0 to switch the limit off.', 'pizzatier' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'pizzatier' ); ?></th>
						<td>
							<input type="url" class="regular-text code" name="pzt_webhook_url"
							       placeholder="https://"
							       value="<?php echo esc_attr( (string) OrderSettings::get( 'webhook_url' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Every placed order is POSTed here as JSON — a kitchen display, a POS, or an automation service such as Zapier or Make. Leave empty to switch it off.', 'pizzatier' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook secret', 'pizzatier' ); ?></th>
						<td>
							<input type="text" class="regular-text code" name="pzt_webhook_secret"
							       autocomplete="off"
							       value="<?php echo esc_attr( (string) OrderSettings::get( 'webhook_secret' ) ); ?>" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: HTTP header name. */
									esc_html__( 'Optional. When set, each request carries an HMAC-SHA256 signature of the body in the %s header so the receiver can verify it came from this site.', 'pizzatier' ),
									'<code>' . esc_html( \PizzaTier\Orders\RouteDispatcher::SIGNATURE_HEADER ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php
			// The "notify only" route throws the record away once the order has
			// been sent. If there is nowhere to send it, every order would be
			// lost — so warn here, and refuse to discard at runtime.
			$pzt_notify_only = \PizzaTier\Orders\OrderRoute::NOTIFY === \PizzaTier\Orders\OrderRoute::get();
			$pzt_can_deliver = OrderSettings::is_on( 'notify_admin' ) || '' !== trim( (string) OrderSettings::get( 'webhook_url' ) );
			?>
			<?php if ( $pzt_notify_only && ! $pzt_can_deliver ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Orders are set to be sent without being recorded, but there is nowhere to send them. Switch on the store email or set a webhook URL. Until then, orders will be kept in the list rather than lost.', 'pizzatier' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Data', 'pizzatier' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					$this->number_row(
						'retention_months',
						__( 'Auto-anonymise after', 'pizzatier' ),
						0,
						120,
						__( 'Months. After this long an order\'s name, contact details, address and notes are cleared automatically; the order number, date, items and total are kept so the transaction record survives. Set to 0 to switch this off. Retention periods differ by country — check yours before enabling.', 'pizzatier' )
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'On uninstall', 'pizzatier' ); ?></th>
						<td>
							<label class="pzt-orders-check">
								<input type="checkbox" name="pzt_delete_on_uninstall" value="1"
								       <?php checked( 'yes', (string) get_option( 'pizzatier_setting_delete_orders_on_uninstall', 'no' ) ); ?> />
								<?php esc_html_e( 'Delete all order history when the plugin is uninstalled', 'pizzatier' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Orders are transaction records, so they are kept even if the plugin is removed.', 'pizzatier' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	// ── Settings field helpers ────────────────────────────────────────────

	private function checkbox_row( string $key, string $label, string $description ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label class="pzt-orders-check">
					<input type="checkbox" name="pzt_<?php echo esc_attr( $key ); ?>" value="1"
					       <?php checked( OrderSettings::is_on( $key ) ); ?> />
					<?php echo esc_html( $description ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	private function text_row( string $key, string $label, string $value ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="text" class="regular-text" name="pzt_<?php echo esc_attr( $key ); ?>"
				       value="<?php echo esc_attr( $value ); ?>" />
			</td>
		</tr>
		<?php
	}

	private function number_row( string $key, string $label, int $min, int $max, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<input type="number" class="small-text" name="pzt_<?php echo esc_attr( $key ); ?>"
				       min="<?php echo esc_attr( (string) $min ); ?>"
				       max="<?php echo esc_attr( (string) $max ); ?>"
				       value="<?php echo esc_attr( (string) OrderSettings::get_int( $key ) ); ?>" />
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	// -------------------------------------------------------------------------
	// Presentation helpers
	// -------------------------------------------------------------------------

	/** A coloured status pill. Returns pre-escaped HTML. */
	public static function status_badge( string $status ): string {
		return sprintf(
			'<span class="pzt-order-badge" style="--pzt-badge:%s">%s</span>',
			esc_attr( OrderStatuses::color( $status ) ),
			esc_html( OrderStatuses::label( $status ) )
		);
	}

	/**
	 * Format a money amount.
	 *
	 * PizzaTier has no currency settings of its own — it does not price pizzas.
	 * When a premium extension records a currency on the order, its symbol is
	 * used if recognised, otherwise the code is shown alongside the number.
	 */
	public static function format_money( float $amount, string $currency = '' ): string {
		$symbols = [
			'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
			'AUD' => 'A$', 'CAD' => 'C$', 'NZD' => 'NZ$',
		];

		$number = number_format_i18n( $amount, 2 );

		if ( '' === $currency ) {
			return $number;
		}

		$currency = strtoupper( $currency );

		return isset( $symbols[ $currency ] )
			? $symbols[ $currency ] . $number
			: $number . ' ' . $currency;
	}

	/** Display name for a user ID, or a fallback for system entries. */
	private static function describe_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return __( 'Customer', 'pizzatier' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown user', 'pizzatier' );
	}

	/** Format a stored GMT timestamp in the site's timezone. */
	private static function format_time( string $gmt ): string {
		$timestamp = strtotime( $gmt . ' UTC' );
		if ( ! $timestamp ) {
			return $gmt;
		}
		return wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp );
	}
}
