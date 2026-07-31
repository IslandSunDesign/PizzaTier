<?php
/**
 * OrderEmailSummary — formatted pizza configuration in WooCommerce emails.
 *
 * Provides two optional delivery modes, each controllable via Pro settings:
 *
 *   1. Append-to-order-confirmation — hooks into `woocommerce_email_order_meta`
 *      so the pizza breakdown appears inside the standard WC order email.
 *
 *   2. Separate pizza summary email — sends a dedicated email to the customer
 *      (and optionally the admin) when an order reaches "processing" status.
 *
 * Both modes are disabled by default and opt-in via Settings → Cart & Checkout.
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderEmailSummary {

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Append pizza summary block to standard WC order emails.
		add_action( 'woocommerce_email_order_meta', [ $this, 'append_to_order_email' ], 20, 3 );

		// Dedicated pizza summary email on order processing.
		add_action( 'woocommerce_order_status_processing', [ $this, 'send_pizza_summary_email' ], 20 );
	}

	// -------------------------------------------------------------------------
	// 1. Append to standard WC order email
	// -------------------------------------------------------------------------

	/**
	 * Appends a formatted pizza configuration table to WooCommerce order emails.
	 *
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @param bool      $plain_text
	 */
	public function append_to_order_email( \WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
		// Default to ON — pizza configuration is essential context for any order
		// email. Admins can opt out by setting 'email_append_to_order' to "0" in
		// the cart & pricing settings, but the default behavior is to always include the
		// pizza summary on every WooCommerce order email.
		$enabled = pizzatier_get_option( 'email_append_to_order', null );
		if ( null !== $enabled && '' !== $enabled && ! (bool) $enabled ) {
			return;
		}

		$pizza_items = $this->get_pizza_items( $order );
		if ( empty( $pizza_items ) ) {
			return;
		}

		if ( $plain_text ) {
			echo $this->render_plain_text( $pizza_items, $order ); // phpcs:ignore
		} else {
			echo $this->render_html( $pizza_items, $order, $sent_to_admin ); // phpcs:ignore
		}
	}

	// -------------------------------------------------------------------------
	// 2. Dedicated pizza summary email
	// -------------------------------------------------------------------------

	/**
	 * Sends a standalone pizza summary email when an order becomes processing.
	 *
	 * @param int $order_id
	 */
	public function send_pizza_summary_email( int $order_id ): void {
		if ( ! (bool) pizzatier_get_option( 'email_send_separate', false ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$pizza_items = $this->get_pizza_items( $order );
		if ( empty( $pizza_items ) ) {
			return;
		}

		$this->dispatch_summary_email( $order, $pizza_items );
	}

	// -------------------------------------------------------------------------
	// Email dispatch
	// -------------------------------------------------------------------------

	/**
	 * Sends the dedicated pizza summary email.
	 *
	 * @param \WC_Order $order
	 * @param array     $pizza_items
	 */
	private function dispatch_summary_email( \WC_Order $order, array $pizza_items ): void {
		$send_to_customer = (bool) pizzatier_get_option( 'email_separate_to_customer', true );
		$send_to_admin    = (bool) pizzatier_get_option( 'email_separate_to_admin', false );

		$subject = $this->interpolate_subject(
			(string) pizzatier_get_option( 'email_separate_subject', '' ),
			$order
		);
		if ( '' === $subject ) {
			/* translators: %s: order number */
			$subject = sprintf( __( 'Your pizza order summary — Order #%s', 'pizzatier' ), $order->get_order_number() );
		}

		$html_body = $this->build_full_email_html( $order, $pizza_items );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		if ( $send_to_customer ) {
			$customer_email = $order->get_billing_email();
			if ( $customer_email ) {
				wp_mail( $customer_email, $subject, $html_body, $headers );
			}
		}

		if ( $send_to_admin ) {
			$admin_email = get_option( 'admin_email' );
			$admin_subject = '[Admin] ' . $subject;
			wp_mail( $admin_email, $admin_subject, $html_body, $headers );
		}
	}

	// -------------------------------------------------------------------------
	// Data extraction
	// -------------------------------------------------------------------------

	/**
	 * Extract pizza line items from an order, returning structured data.
	 *
	 * @param \WC_Order $order
	 * @return array[]  Each entry: { item, product, size, layers, total, base_price, order_note }
	 */
	private function get_pizza_items( \WC_Order $order ): array {
		$results = [];

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$size = $item->get_meta( OrderMeta::META_SIZE );

			if ( ! $size ) {
				continue;
			}

			$layers = $this->resolve_layers_for_display(
				$item->get_meta( OrderMeta::META_LAYERS ),
				$item->get_meta( OrderMeta::META_INPUT_LAYERS )
			);

			$product = $item->get_product();

			$results[] = [
				'item'        => $item,
				'product'     => $product,
				'name'        => $item->get_name(),
				'size'        => $size,
				'layers'      => $layers,
				'total'       => (float) $item->get_meta( OrderMeta::META_TOTAL ),
				'base_price'  => (float) $item->get_meta( OrderMeta::META_BASE_PRICE ),
				'order_note'  => (string) $item->get_meta( OrderMeta::META_ORDER_NOTE ),
			];
		}

		return $results;
	}

	/**
	 * Pick the best available layers source for email rendering.
	 *
	 * Mirrors OrderMeta::resolve_layers_for_display(): prefers the priced
	 * breakdown, falls back to raw client input when the breakdown is empty.
	 * Guarantees email summaries always include the customer's selections,
	 * even when the priced breakdown was unavailable.
	 *
	 * @param mixed $breakdown
	 * @param mixed $input_layers
	 * @return array
	 */
	private function resolve_layers_for_display( $breakdown, $input_layers ): array {
		$breakdown    = is_array( $breakdown )    ? $breakdown    : [];
		$input_layers = is_array( $input_layers ) ? $input_layers : [];

		$real_breakdown_ids = [];
		foreach ( $breakdown as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$lid = (string) ( $entry['layerId'] ?? '' );
			if ( $lid !== '' && strpos( $lid, '_' ) !== 0 ) {
				$real_breakdown_ids[ $lid ] = true;
			}
		}

		if ( ! empty( $real_breakdown_ids ) ) {
			foreach ( $input_layers as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$lid = (string) ( $entry['layerId'] ?? '' );
				if ( $lid === '' || isset( $real_breakdown_ids[ $lid ] ) ) {
					continue;
				}
				$breakdown[] = [
					'layerId'     => $lid,
					'layerName'   => (string) ( $entry['layerName'] ?? '' ),
					'fraction'    => (string) ( $entry['fraction']  ?? 'Whole' ),
					'portion'     => (string) ( $entry['portion']      ?? '' ),
					'portionLabel'=> (string) ( $entry['portionLabel'] ?? '' ),
					'layerType'   => (string) ( $entry['layerType'] ?? '' ),
					'layerPostId' => (int)    ( $entry['layerPostId'] ?? 0 ),
					'price'       => 0.0,
					'note'        => '',
				];
			}
			return $breakdown;
		}

		$out = [];
		foreach ( $breakdown as $entry ) {
			if ( is_array( $entry ) ) {
				$out[] = $entry;
			}
		}
		foreach ( $input_layers as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$lid = (string) ( $entry['layerId'] ?? '' );
			if ( $lid === '' ) {
				continue;
			}
			$out[] = [
				'layerId'     => $lid,
				'layerName'   => (string) ( $entry['layerName'] ?? '' ),
				'fraction'    => (string) ( $entry['fraction']  ?? 'Whole' ),
				'portion'     => (string) ( $entry['portion']      ?? '' ),
				'portionLabel'=> (string) ( $entry['portionLabel'] ?? '' ),
				'layerType'   => (string) ( $entry['layerType'] ?? '' ),
				'layerPostId' => (int)    ( $entry['layerPostId'] ?? 0 ),
				'price'       => 0.0,
				'note'        => '',
			];
		}
		return $out;
	}

	// -------------------------------------------------------------------------
	// HTML rendering
	// -------------------------------------------------------------------------

	/**
	 * Render the HTML pizza summary block for appending inside WC emails.
	 *
	 * @param array     $pizza_items
	 * @param \WC_Order $order
	 * @param bool      $sent_to_admin
	 * @return string
	 */
	private function render_html( array $pizza_items, \WC_Order $order, bool $sent_to_admin ): string {
		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? get_woocommerce_currency_symbol()
			: '$';

		$heading = (string) pizzatier_get_option( 'email_summary_heading', '' );
		if ( '' === $heading ) {
			$heading = __( 'Your Pizza Configuration', 'pizzatier' );
		}

		ob_start();
		?>
		<div style="margin:24px 0;font-family:Arial,sans-serif;">
			<h2 style="font-size:16px;margin-bottom:12px;color:#1a1a2e;"><?php echo esc_html( $heading ); ?></h2>
		<?php foreach ( $pizza_items as $pizza ) : ?>
			<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;">
				<thead>
					<tr>
						<th colspan="4" style="background:#1a1a2e;color:#fff;padding:8px 12px;text-align:left;font-size:14px;">
							<?php echo esc_html( $pizza['name'] ); ?>
							<?php if ( $pizza['size'] ) : ?>
								&mdash; <?php echo esc_html( $pizza['size'] ); ?>
							<?php endif; ?>
						</th>
					</tr>
					<tr style="background:#f5f5f5;">
						<th style="padding:6px 12px;text-align:left;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Type', 'pizzatier' ); ?></th>
						<th style="padding:6px 12px;text-align:left;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Ingredient', 'pizzatier' ); ?></th>
						<th style="padding:6px 12px;text-align:left;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></th>
						<th style="padding:6px 12px;text-align:right;border-bottom:1px solid #ddd;"><?php esc_html_e( 'Price', 'pizzatier' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $pizza['base_price'] > 0 ) : ?>
					<tr>
						<td style="padding:5px 12px;border-bottom:1px solid #eee;color:#777;font-style:italic;" colspan="2"><?php esc_html_e( 'Base pizza', 'pizzatier' ); ?></td>
						<td style="padding:5px 12px;border-bottom:1px solid #eee;color:#777;font-style:italic;">—</td>
						<td style="padding:5px 12px;border-bottom:1px solid #eee;color:#777;text-align:right;"><?php echo esc_html( $currency . number_format( $pizza['base_price'], wc_get_price_decimals() ) ); ?></td>
					</tr>
					<?php endif; ?>
					<?php
					$email_type_labels = [
						'crust'   => __( 'Crust',    'pizzatier' ),
						'sauce'   => __( 'Sauce',    'pizzatier' ),
						'cheese'  => __( 'Cheese',   'pizzatier' ),
						'drizzle' => __( 'Drizzle',  'pizzatier' ),
						'cut'     => __( 'Cut',      'pizzatier' ),
						'topping' => __( 'Toppings', 'pizzatier' ),
					];
					$email_type_order = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut', 'topping' ];
					$email_groups = [];
					foreach ( $pizza['layers'] as $el ) {
						if ( isset( $el['layerId'] ) && strpos( (string) $el['layerId'], '_' ) === 0 ) { continue; }
						$et = strtolower( (string) ( $el['layerType'] ?? '' ) );
						if ( '' === $et ) { $et = 'topping'; }
						$email_groups[ $et ][] = $el;
					}
					$email_all_types = array_merge( $email_type_order, array_diff( array_keys( $email_groups ), $email_type_order ) );
					foreach ( $email_all_types as $etype ) :
						if ( empty( $email_groups[ $etype ] ) ) { continue; }
						$etlabel   = $email_type_labels[ $etype ] ?? ucfirst( $etype );
						$et_layers = $email_groups[ $etype ];
						$et_count  = count( $et_layers );
						foreach ( $et_layers as $ei => $layer ) :
							$name     = $layer['layerName'] ?? $layer['layerId'] ?? '';
							$fraction = \PizzaTier\Commerce\WooCommerce\OrderMeta::coverage_display( $layer );
							$price    = isset( $layer['price'] ) ? (float) $layer['price'] : null;
							$note     = $layer['note'] ?? '';
						?>
						<tr>
							<?php if ( 0 === $ei ) : ?>
							<td rowspan="<?php echo (int) $et_count; ?>" style="padding:5px 12px;border-bottom:1px solid #eee;font-weight:600;vertical-align:top;white-space:nowrap;background:#f9f9f9;"><?php echo esc_html( $etlabel ); ?></td>
							<?php endif; ?>
							<td style="padding:5px 12px;border-bottom:1px solid #eee;">
								<?php echo esc_html( $name ); ?>
								<?php if ( $note ) : ?><br><em style="font-size:11px;color:#999;"><?php echo esc_html( $note ); ?></em><?php endif; ?>
							</td>
							<td style="padding:5px 12px;border-bottom:1px solid #eee;"><?php echo esc_html( $fraction ); ?></td>
							<td style="padding:5px 12px;border-bottom:1px solid #eee;text-align:right;">
								<?php if ( $price > 0 ) : ?>
									<?php echo esc_html( '+' . $currency . number_format( $price, wc_get_price_decimals() ) ); ?>
								<?php elseif ( $note ) : ?>
									<em style="color:#bbb;">Incl.</em>
								<?php else : ?>
									<?php echo esc_html( $currency . '0.00' ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="3" style="padding:8px 12px;border-top:2px solid #e8692a;font-weight:bold;">
							<?php esc_html_e( 'Pizza Total', 'pizzatier' ); ?>
						</td>
						<td style="padding:8px 12px;border-top:2px solid #e8692a;text-align:right;font-weight:bold;">
							<?php echo esc_html( $currency . number_format( $pizza['total'], wc_get_price_decimals() ) ); ?>
						</td>
					</tr>
				</tfoot>
			</table>
			<?php if ( $pizza['order_note'] !== '' ) : ?>
				<p style="margin:-8px 0 20px;font-size:12px;color:#555;padding:6px 12px;background:#fffbe6;border-left:3px solid #f0b429;">
					<strong><?php esc_html_e( 'Note:', 'pizzatier' ); ?></strong>
					<?php echo esc_html( $pizza['order_note'] ); ?>
				</p>
			<?php endif; ?>
		<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build a complete standalone email HTML document.
	 *
	 * @param \WC_Order $order
	 * @param array     $pizza_items
	 * @return string
	 */
	private function build_full_email_html( \WC_Order $order, array $pizza_items ): string {
		$store_name = get_bloginfo( 'name' );
		$body       = $this->render_html( $pizza_items, $order, false );

		$greeting = sprintf(
			/* translators: %s: customer first name */
			__( 'Hi %s,', 'pizzatier' ),
			esc_html( $order->get_billing_first_name() ?: __( 'there', 'pizzatier' ) )
		);

		$intro = sprintf(
			/* translators: %s: order number */
			__( 'Here is a summary of your pizza configuration for Order #%s.', 'pizzatier' ),
			$order->get_order_number()
		);

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
			. '<p>' . esc_html( $greeting ) . '</p>'
			. '<p>' . esc_html( $intro ) . '</p>'
			. $body
			. '<p style="color:#888;font-size:12px;margin-top:24px;">'
			. esc_html( $store_name )
			. '</p></body></html>';
	}

	/**
	 * Plain-text fallback for accessibility / some email clients.
	 *
	 * @param array     $pizza_items
	 * @param \WC_Order $order
	 * @return string
	 */
	private function render_plain_text( array $pizza_items, \WC_Order $order ): string {
		$currency = function_exists( 'get_woocommerce_currency_symbol' )
			? get_woocommerce_currency_symbol()
			: '$';

		$out = "\n" . str_repeat( '=', 40 ) . "\n";
		$out .= strtoupper( __( 'Pizza Configuration', 'pizzatier' ) ) . "\n";
		$out .= str_repeat( '=', 40 ) . "\n\n";

		foreach ( $pizza_items as $pizza ) {
			$out .= $pizza['name'];
			if ( $pizza['size'] ) {
				$out .= ' — ' . $pizza['size'];
			}
			$out .= "\n";
			$out .= str_repeat( '-', 30 ) . "\n";

			$pt_type_order  = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut', 'topping' ];
			$pt_type_labels = [
				'crust'   => __( 'Crust',    'pizzatier' ),
				'sauce'   => __( 'Sauce',    'pizzatier' ),
				'cheese'  => __( 'Cheese',   'pizzatier' ),
				'drizzle' => __( 'Drizzle',  'pizzatier' ),
				'cut'     => __( 'Cut',      'pizzatier' ),
				'topping' => __( 'Toppings', 'pizzatier' ),
			];
			$pt_groups = [];
			foreach ( $pizza['layers'] as $pt_layer ) {
				if ( isset( $pt_layer['layerId'] ) && strpos( (string) $pt_layer['layerId'], '_' ) === 0 ) { continue; }
				$pt_type = strtolower( (string) ( $pt_layer['layerType'] ?? '' ) );
				if ( '' === $pt_type ) { $pt_type = 'topping'; }
				$pt_groups[ $pt_type ][] = $pt_layer;
			}
			$pt_all_types = array_merge( $pt_type_order, array_diff( array_keys( $pt_groups ), $pt_type_order ) );
			foreach ( $pt_all_types as $pt_type ) {
				if ( empty( $pt_groups[ $pt_type ] ) ) { continue; }
				$pt_label = $pt_type_labels[ $pt_type ] ?? ucfirst( $pt_type );
				$out .= '  ' . strtoupper( $pt_label ) . ":\n";
				foreach ( $pt_groups[ $pt_type ] as $layer ) {
					$name     = $layer['layerName'] ?? $layer['layerId'] ?? '';
					$fraction = \PizzaTier\Commerce\WooCommerce\OrderMeta::coverage_display( $layer );
					$price    = isset( $layer['price'] ) ? (float) $layer['price'] : null;
					$note     = $layer['note'] ?? '';
					$out .= '    ' . $name;
					if ( $fraction ) { $out .= ' (' . $fraction . ')'; }
					if ( $price > 0 ) { $out .= ' +' . $currency . number_format( $price, wc_get_price_decimals() ); }
					elseif ( $note ) { $out .= ' [' . $note . ']'; }
					$out .= "\n";
				}
			}

			$out .= __( 'Total:', 'pizzatier' ) . ' ' . $currency . number_format( $pizza['total'], wc_get_price_decimals() ) . "\n";

			if ( $pizza['order_note'] !== '' ) {
				$out .= __( 'Note:', 'pizzatier' ) . ' ' . $pizza['order_note'] . "\n";
			}

			$out .= "\n";
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Replace tokens in a custom subject string.
	 *
	 * Tokens: {order_number}, {site_name}
	 *
	 * @param string    $template
	 * @param \WC_Order $order
	 * @return string
	 */
	private function interpolate_subject( string $template, \WC_Order $order ): string {
		return str_replace(
			[ '{order_number}', '{site_name}' ],
			[ $order->get_order_number(), get_bloginfo( 'name' ) ],
			$template
		);
	}
}
