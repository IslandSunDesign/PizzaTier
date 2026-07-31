<?php
/**
 * OrderPizzaMetaBox — dedicated meta box on the WooCommerce order edit screen.
 *
 * Renders a full pizza configuration summary for every line item in the order
 * that contains PizzaTier data (_pizzatier_commerce_size + _pizzatier_commerce_layers).
 *
 * Layout per item:
 *   ┌─────────────────────────────────────────────────┐
 *   │ 🍕  "Build Your Own Pizza"  ×2    Large         │
 *   │─────────────────────────────────────────────────│
 *   │ Base          Base pizza price           $12.00 │
 *   │ Crust         Thin Crispy  (Whole)        $1.50 │
 *   │ Sauce         Marinara     (Whole)        $0.00 │
 *   │ Toppings      Pepperoni    (Half)         $1.25 │
 *   │               Mushrooms    (Whole)        $1.50 │
 *   │─────────────────────────────────────────────────│
 *   │ Total                                    $16.25 │
 *   │ Note: extra crispy please                       │
 *   └─────────────────────────────────────────────────│
 *
 * Compatibility:
 *   - Legacy post-based orders: registered on 'shop_order' post type.
 *   - HPOS (WooCommerce High-Performance Order Storage): registered on
 *     'woocommerce_page_wc-orders' via the add_meta_boxes_{screen_id} hook.
 *   Both paths call the same render callback which resolves the order via
 *   wc_get_order() regardless of storage backend.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\WooCommerce\OrderMeta;

class OrderPizzaMetaBox {

	const META_BOX_ID = 'pizzatier_commerce_order_pizza_build';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Legacy post-based orders (shop_order CPT).
		add_action( 'add_meta_boxes_shop_order',              [ $this, 'add_meta_box' ] );
		// HPOS orders (WooCommerce 7.1+).
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', [ $this, 'add_meta_box' ] );
		// Enqueue CSS only on order edit screens.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box.
	 *
	 * Called by both add_meta_boxes_shop_order and
	 * add_meta_boxes_woocommerce_page_wc-orders so it works on both
	 * legacy and HPOS order screens.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order
	 */
	public function add_meta_box( $post_or_order ): void {
		// Resolve order to confirm it has pizza items before registering the box.
		$order = $this->resolve_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		// Only show the meta box if this order contains at least one pizza item.
		if ( ! $this->order_has_pizza_items( $order ) ) {
			return;
		}

		// Determine which screen to attach to.
		$screen = $post_or_order instanceof \WP_Post ? 'shop_order' : 'woocommerce_page_wc-orders';

		add_meta_box(
			self::META_BOX_ID,
			'<span class="dashicons dashicons-pizza" style="color:#ff6b35;margin-right:6px;vertical-align:middle;font-size:18px;"></span>'
				. esc_html__( 'Pizza Build', 'pizzatier' ),
			[ $this, 'render' ],
			$screen,
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the meta box content.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order
	 */
	public function render( $post_or_order ): void {
		$order = $this->resolve_order( $post_or_order );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'pizzatier' ) . '</p>';
			return;
		}

		$cur = function_exists( 'get_woocommerce_currency_symbol' )
			? get_woocommerce_currency_symbol()
			: '$';
		$dec = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

		$pizza_items = $this->get_pizza_items( $order );

		if ( empty( $pizza_items ) ) {
			echo '<p class="pztc-omb__empty">' . esc_html__( 'No pizza configuration found for this order.', 'pizzatier' ) . '</p>';
			return;
		}

		echo '<div class="pztc-omb">';

		foreach ( $pizza_items as $item_data ) {
			$this->render_item( $item_data, $cur, $dec );
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Item render
	// -------------------------------------------------------------------------

	/**
	 * Render the pizza build card for a single order line item.
	 *
	 * @param array  $item_data  Resolved item data array (see get_pizza_items()).
	 * @param string $cur        Currency symbol.
	 * @param int    $dec        Decimal places.
	 */
	private function render_item( array $item_data, string $cur, int $dec ): void {
		$product_name = $item_data['product_name'];
		$quantity     = (int) $item_data['quantity'];
		$size         = $item_data['size'];
		$base_price   = (float) $item_data['base_price'];
		$total        = (float) $item_data['total'];
		$order_note   = (string) $item_data['order_note'];
		$groups       = $item_data['groups'];
		$all_types    = $item_data['all_types'];

		$format_price = function( float $p ) use ( $cur, $dec ): string {
			return $cur . number_format( $p, $dec );
		};
		?>
		<div class="pztc-omb__item">

			<!-- Item header -->
			<div class="pztc-omb__item-header">
				<span class="pztc-omb__product-name"><?php echo esc_html( $product_name ); ?></span>
				<?php if ( $quantity > 1 ) : ?>
					<span class="pztc-omb__qty">×<?php echo esc_html( $quantity ); ?></span>
				<?php endif; ?>
				<span class="pztc-omb__size-badge"><?php echo esc_html( $size ); ?></span>
			</div>

			<!-- Build table -->
			<table class="pztc-omb__table">
				<thead>
					<tr>
						<th class="pztc-omb__th-type"><?php esc_html_e( 'Type', 'pizzatier' ); ?></th>
						<th class="pztc-omb__th-layer"><?php esc_html_e( 'Layer', 'pizzatier' ); ?></th>
						<th class="pztc-omb__th-coverage"><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></th>
						<th class="pztc-omb__th-price"><?php esc_html_e( 'Price', 'pizzatier' ); ?></th>
					</tr>
				</thead>
				<tbody>

					<!-- Base price row -->
					<tr class="pztc-omb__base-row">
						<td class="pztc-omb__type-cell"><em><?php esc_html_e( 'Base', 'pizzatier' ); ?></em></td>
						<td colspan="2"><em><?php esc_html_e( 'Base pizza price', 'pizzatier' ); ?></em></td>
						<td class="pztc-omb__price-cell"><?php echo esc_html( $format_price( $base_price ) ); ?></td>
					</tr>

					<?php foreach ( $all_types as $type ) : ?>
						<?php if ( empty( $groups[ $type ] ) ) continue; ?>
						<?php
						$type_label  = $this->type_label( $type );
						$type_layers = $groups[ $type ];
						$row_count   = count( $type_layers );
						?>
						<?php foreach ( $type_layers as $row_idx => $layer ) :
							$layer_name = $layer['layerName'] ?? $layer['layerId'] ?? '';
							$fraction   = \PizzaTier\Commerce\WooCommerce\OrderMeta::coverage_display( $layer );
							$price      = isset( $layer['price'] ) ? (float) $layer['price'] : 0.0;
							$note       = (string) ( $layer['note'] ?? '' );
							// Skip synthetic internal entries (_flat_rate, _tier_charge, etc.)
							if ( $layer_name === '' ) continue;
						?>
						<tr class="pztc-omb__layer-row pztc-omb__type--<?php echo esc_attr( $type ); ?>">

							<?php if ( 0 === $row_idx ) : ?>
							<td rowspan="<?php echo esc_attr( $row_count ); ?>" class="pztc-omb__type-cell">
								<strong><?php echo esc_html( $type_label ); ?></strong>
							</td>
							<?php endif; ?>

							<td class="pztc-omb__layer-name">
								<?php echo esc_html( $layer_name ); ?>
								<?php if ( $note !== '' ) : ?>
									<br><em class="pztc-omb__note"><?php echo esc_html( $note ); ?></em>
								<?php endif; ?>
							</td>

							<td class="pztc-omb__coverage">
								<?php echo $fraction !== '' ? esc_html( $fraction ) : '—'; ?>
							</td>

							<td class="pztc-omb__price-cell">
								<?php if ( $price > 0 ) : ?>
									+<?php echo esc_html( $format_price( $price ) ); ?>
								<?php elseif ( $note !== '' ) : ?>
									<em class="pztc-omb__included">—</em>
								<?php else : ?>
									<?php echo esc_html( $format_price( 0.0 ) ); ?>
								<?php endif; ?>
							</td>

						</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>

				</tbody>
				<tfoot>
					<tr class="pztc-omb__total-row">
						<td colspan="3">
							<strong>
								<?php esc_html_e( 'Size:', 'pizzatier' ); ?>
								<?php echo esc_html( $size ); ?>
							</strong>
						</td>
						<td class="pztc-omb__price-cell">
							<strong><?php echo esc_html( $format_price( $total ) ); ?></strong>
						</td>
					</tr>
					<?php if ( $order_note !== '' ) : ?>
					<tr class="pztc-omb__note-row">
						<td colspan="4">
							<strong><?php esc_html_e( 'Customer note:', 'pizzatier' ); ?></strong>
							<?php echo esc_html( $order_note ); ?>
						</td>
					</tr>
					<?php endif; ?>
				</tfoot>
			</table>

		</div><!-- .pztc-omb__item -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue meta box stylesheet on order edit screens only.
	 *
	 * @param string $hook  Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// Legacy orders: post.php / post-new.php on shop_order post type.
		// HPOS orders: woocommerce_page_wc-orders (list) and
		//              woocommerce_page_wc-orders--new / woocommerce_page_wc-orders--edit (edit).
		$is_legacy_order = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
			&& ( get_current_screen() && get_current_screen()->post_type === 'shop_order' );

		$is_hpos_order = in_array( $hook, [
			'woocommerce_page_wc-orders',
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only use of a request value for display; no state is changed.
		], true ) && isset( $_GET['id'] );

		if ( ! $is_legacy_order && ! $is_hpos_order ) {
			return;
		}

		$this->output_inline_styles();
	}

	/**
	 * Output the meta box CSS as an inline style block.
	 *
	 * Keeps the meta box self-contained — no extra enqueue handle needed.
	 */
	private function output_inline_styles(): void {
		// Only output once even if called multiple times.
		static $done = false;
		if ( $done ) return;
		$done = true;
		?>
		<style id="pztc-omb-styles">
		/* PizzaTier — Order Pizza Build meta box */
		.pztc-omb { display: flex; flex-direction: column; gap: 20px; }

		.pztc-omb__item {
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			overflow: hidden;
			background: #fff;
		}

		/* Item header */
		.pztc-omb__item-header {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px 14px;
			background: #1a1a2e;
			color: #fff;
		}
		.pztc-omb__product-name {
			font-weight: 700;
			font-size: 13px;
			flex: 1;
		}
		.pztc-omb__qty {
			background: rgba(255,255,255,.15);
			border-radius: 4px;
			padding: 2px 8px;
			font-size: 12px;
			font-weight: 600;
		}
		.pztc-omb__size-badge {
			background: #ff6b35;
			color: #fff;
			border-radius: 4px;
			padding: 2px 10px;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: .5px;
			text-transform: uppercase;
		}

		/* Build table */
		.pztc-omb__table {
			width: 100%;
			border-collapse: collapse;
			font-size: 12px;
		}
		.pztc-omb__table th,
		.pztc-omb__table td {
			padding: 5px 10px;
			border-bottom: 1px solid #f0f0f0;
			text-align: left;
			vertical-align: middle;
		}
		.pztc-omb__table thead th {
			background: #f7f7f7;
			color: #555;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: .4px;
			font-weight: 600;
			border-bottom: 2px solid #e0e0e0;
		}
		.pztc-omb__th-type     { width: 80px; }
		.pztc-omb__th-coverage { width: 90px; }
		.pztc-omb__th-price    { width: 90px; text-align: right !important; }

		/* Type group cell (rowspan, left column) */
		.pztc-omb__type-cell {
			background: #fafafa;
			font-weight: 600;
			color: #333;
			white-space: nowrap;
			vertical-align: top;
			padding-top: 7px;
			border-right: 2px solid #e8692a;
		}
		.pztc-omb__type-cell em {
			font-weight: 400;
			color: #888;
		}

		/* Base price row */
		.pztc-omb__base-row td {
			background: #fffdf9;
			font-style: italic;
			color: #777;
		}

		/* Layer rows */
		.pztc-omb__layer-row:hover td { background: #fafeff; }
		.pztc-omb__coverage { color: #666; }
		.pztc-omb__note { font-size: 10px; color: #999; }
		.pztc-omb__included { color: #bbb; }

		/* Price column */
		.pztc-omb__price-cell { text-align: right; font-variant-numeric: tabular-nums; }

		/* Total footer */
		.pztc-omb__total-row td {
			background: #f9f9f9;
			border-top: 2px solid #ff6b35;
			padding-top: 7px;
			padding-bottom: 7px;
		}
		.pztc-omb__total-row .pztc-omb__price-cell {
			font-size: 14px;
			color: #1a1a2e;
		}

		/* Customer note row */
		.pztc-omb__note-row td {
			font-size: 11px;
			color: #555;
			background: #fffbe6;
			border-top: 1px solid #f0b429;
		}

		/* Empty state */
		.pztc-omb__empty {
			color: #888;
			font-style: italic;
			padding: 8px 0;
		}
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve the WC_Order from either a WP_Post (legacy) or WC_Order (HPOS).
	 *
	 * @param \WP_Post|\WC_Order|mixed $post_or_order
	 * @return \WC_Order|null
	 */
	private function resolve_order( $post_or_order ): ?\WC_Order {
		if ( $post_or_order instanceof \WC_Order ) {
			return $post_or_order;
		}
		if ( $post_or_order instanceof \WP_Post ) {
			$order = wc_get_order( $post_or_order->ID );
			return ( $order instanceof \WC_Order ) ? $order : null;
		}
		return null;
	}

	/**
	 * Check whether an order has at least one pizza line item.
	 *
	 * @param \WC_Order $order
	 * @return bool
	 */
	private function order_has_pizza_items( \WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			if ( $item->get_meta( OrderMeta::META_SIZE ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Extract and structure all pizza line items from an order.
	 *
	 * Returns one array entry per pizza line item, each containing:
	 *   product_name, quantity, size, base_price, total, order_note,
	 *   groups (layers grouped by type), all_types (canonical type order).
	 *
	 * @param \WC_Order $order
	 * @return array
	 */
	private function get_pizza_items( \WC_Order $order ): array {
		$result = [];

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$size = (string) $item->get_meta( OrderMeta::META_SIZE );
			if ( $size === '' ) {
				continue;
			}

			$layers     = $this->resolve_layers_for_display(
				$item->get_meta( OrderMeta::META_LAYERS ),
				$item->get_meta( OrderMeta::META_INPUT_LAYERS )
			);
			$total      = (float) $item->get_meta( OrderMeta::META_TOTAL );
			$base_price = (float) $item->get_meta( OrderMeta::META_BASE_PRICE );
			$order_note = (string) $item->get_meta( OrderMeta::META_ORDER_NOTE );

			$groups    = $this->group_layers_by_type( $layers );
			$all_types = array_merge(
				$this->type_order(),
				array_diff( array_keys( $groups ), $this->type_order() )
			);

			$product      = $item->get_product();
			$product_name = $product ? $product->get_name() : $item->get_name();

			$result[] = [
				'product_name' => $product_name,
				'quantity'     => $item->get_quantity(),
				'size'         => $size,
				'base_price'   => $base_price,
				'total'        => $total,
				'order_note'   => $order_note,
				'groups'       => $groups,
				'all_types'    => $all_types,
			];
		}

		return $result;
	}

	/**
	 * Pick the best available layers source.
	 *
	 * Mirrors OrderMeta::resolve_layers_for_display(): prefer the priced
	 * breakdown, fall back to the raw client input when the breakdown is
	 * empty or only contains synthetic entries. Guarantees the admin meta
	 * box always shows the customer's real selections, even if the priced
	 * breakdown was never persisted for any reason.
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

	/**
	 * Group a flat layer breakdown array by normalised layer type.
	 * Mirrors OrderMeta::group_layers_by_type().
	 *
	 * @param array $layers
	 * @return array<string, array>
	 */
	private function group_layers_by_type( array $layers ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$name = $layer['layerName'] ?? $layer['layerId'] ?? '';
			if ( $name === '' ) {
				continue;
			}
			// Skip synthetic internal entries (flat rate, tier charge, etc.)
			$lid = (string) ( $layer['layerId'] ?? '' );
			if ( $lid !== '' && strpos( $lid, '_' ) === 0 ) {
				continue;
			}
			$type = strtolower( (string) ( $layer['layerType'] ?? '' ) );
			if ( $type === '' ) {
				$type = 'topping';
			}
			$groups[ $type ][] = $layer;
		}
		return $groups;
	}

	/**
	 * Canonical display order for layer types.
	 *
	 * @return string[]
	 */
	private function type_order(): array {
		return [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut', 'topping' ];
	}

	/**
	 * Human-readable label for a layer type slug.
	 *
	 * @param string $type
	 * @return string
	 */
	private function type_label( string $type ): string {
		$map = [
			'crust'   => _x( 'Crust',    'pizza layer type', 'pizzatier' ),
			'sauce'   => _x( 'Sauce',    'pizza layer type', 'pizzatier' ),
			'cheese'  => _x( 'Cheese',   'pizza layer type', 'pizzatier' ),
			'drizzle' => _x( 'Drizzle',  'pizza layer type', 'pizzatier' ),
			'cut'     => _x( 'Cut',      'pizza layer type', 'pizzatier' ),
			'topping' => _x( 'Toppings', 'pizza layer type', 'pizzatier' ),
		];
		return $map[ $type ] ?? ucfirst( $type );
	}
}
