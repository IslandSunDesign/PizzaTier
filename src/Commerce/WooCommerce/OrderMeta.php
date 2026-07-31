<?php
/**
 * Persists pizza configuration through the WooCommerce order flow.
 *
 * Responsibilities:
 *   1. Save the pizza config (size, layers, price breakdown) as custom cart
 *      item data when the item is added to the cart.
 *   2. Display the configuration summary in the cart and checkout pages.
 *   3. Copy cart item meta to the WC order line item on checkout.
 *   4. Display the configuration in the WC order admin screen as a
 *      human-readable breakdown table.
 *
 * Meta keys used (all on order line items):
 *   _pizzatier_commerce_size        string   e.g. 'Large'
 *   _pizzatier_commerce_layers      array    [ { layerId, fraction, price } ]
 *   _pizzatier_commerce_total       float    Server-verified total
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderMeta {

	// ── Meta key constants ────────────────────────────────────────────────────
	// Cart item data keys: NO leading underscore — WooCommerce silently strips
	// underscore-prefixed keys from $cart_item_values before passing to
	// woocommerce_checkout_create_order_line_item, so they would never reach
	// save_to_order_item(). These cart keys are converted to underscore-prefixed
	// order line item meta inside save_to_order_item().
	const CART_SIZE         = 'pizzatier_commerce_size';
	const CART_LAYERS       = 'pizzatier_commerce_layers';
	const CART_INPUT_LAYERS = 'pizzatier_commerce_input_layers';
	const CART_TOTAL        = 'pizzatier_commerce_total';
	const CART_BASE_PRICE   = 'pizzatier_commerce_base_price';
	const CART_ORDER_NOTE   = 'pizzatier_commerce_order_note';
	const CART_KEY          = 'pizzatier_commerce_cart_key';

	// Order line item meta keys: underscore prefix hides them from WC's raw
	// meta display while keeping them accessible via get_meta().
	const META_SIZE         = '_pizzatier_size';
	const META_LAYERS       = '_pizzatier_layers';
	const META_INPUT_LAYERS = '_pizzatier_input_layers';
	const META_TOTAL        = '_pizzatier_total';
	const META_BASE_PRICE   = '_pizzatier_base_price';
	const META_ORDER_NOTE   = '_pizzatier_order_note';

	// ── Display label constants (prefixed with underscore = hidden by default) ──
	const DISPLAY_SIZE      = 'pizzatier_commerce_display_size';
	const DISPLAY_LAYERS    = 'pizzatier_commerce_display_layers';

	// -------------------------------------------------------------------------
	// Coverage portion contract (single source of truth, PHP side)
	// -------------------------------------------------------------------------
	//
	// `fraction` is the generic coverage SIZE used by the price grid
	// ('Whole'|'Half'|'Quarter'). `portion` is WHICH specific portion the
	// topping sits on ('half-left', 'quarter-top-right', …) — the kitchen
	// needs this to plate the pie correctly. These helpers canonicalise a raw
	// portion slug and resolve its human-readable label.

	/**
	 * Map of canonical portion slug → human label (translatable).
	 *
	 * @return array<string,string>
	 */
	public static function portion_labels(): array {
		return [
			'half-left'            => __( 'Left Half', 'pizzatier' ),
			'half-right'           => __( 'Right Half', 'pizzatier' ),
			'quarter-top-left'     => __( 'Top-Left Quarter', 'pizzatier' ),
			'quarter-top-right'    => __( 'Top-Right Quarter', 'pizzatier' ),
			'quarter-bottom-left'  => __( 'Bottom-Left Quarter', 'pizzatier' ),
			'quarter-bottom-right' => __( 'Bottom-Right Quarter', 'pizzatier' ),
		];
	}

	/**
	 * Canonicalise a raw portion value to its slug, or '' when it carries no
	 * specific side (bare 'whole'/'half'/'quarter' or anything unrecognised).
	 */
	public static function canonical_portion( string $raw ): string {
		$s = strtolower( trim( $raw ) );
		$s = str_replace( [ ' ', '_' ], '-', $s );
		$alias = [
			'halfleft'             => 'half-left',
			'halfright'            => 'half-right',
			'quartertopleft'       => 'quarter-top-left',
			'quartertopright'      => 'quarter-top-right',
			'quarterbottomleft'    => 'quarter-bottom-left',
			'quarterbottomright'   => 'quarter-bottom-right',
		];
		if ( isset( $alias[ $s ] ) ) { $s = $alias[ $s ]; }
		$labels = self::portion_labels();
		return isset( $labels[ $s ] ) ? $s : '';
	}

	/**
	 * Human label for a canonical portion slug ('' for none).
	 */
	public static function portion_label( string $portion ): string {
		$labels = self::portion_labels();
		return $labels[ $portion ] ?? '';
	}

	/**
	 * Best coverage string to show the customer/kitchen for a layer entry.
	 * Prefers the specific portion label; falls back to a client-supplied
	 * portionLabel, then to the generic pricing fraction.
	 *
	 * @param array $layer  A breakdown / input layer entry.
	 */
	public static function coverage_display( array $layer ): string {
		$portion = self::canonical_portion( (string) ( $layer['portion'] ?? '' ) );
		if ( $portion !== '' ) {
			return self::portion_label( $portion );
		}
		$client = trim( (string) ( $layer['portionLabel'] ?? '' ) );
		if ( $client !== '' ) {
			return $client;
		}
		return (string) ( $layer['fraction'] ?? '' );
	}


	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Persist custom pizza data through the WC session so it survives page
		// reloads between add-to-cart and checkout. Without this hook WooCommerce
		// only re-hydrates standard cart item keys from the session.
		add_filter( 'woocommerce_get_cart_item_from_session',        [ $this, 'load_from_session' ], 10, 2 );

		// Cart item display.
		add_filter( 'woocommerce_get_item_data',                     [ $this, 'display_in_cart' ], 10, 2 );

		// Append a compact layer summary to the product name in cart and order views.
		add_filter( 'woocommerce_cart_item_name',                    [ $this, 'append_layer_description' ], 5, 3 );
		add_filter( 'woocommerce_order_item_name',                   [ $this, 'append_order_item_layer_description' ], 5, 2 );

		// Transfer cart meta to order line item on checkout.
		add_action( 'woocommerce_checkout_create_order_line_item',   [ $this, 'save_to_order_item' ], 10, 4 );

		// Display in order admin (WC order items meta box).
		add_action( 'woocommerce_after_order_itemmeta',               [ $this, 'display_in_order_admin' ], 10, 3 );

		// Display in order confirmation / my-account order detail.
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ $this, 'format_order_item_meta' ], 10, 2 );

		// Cart edit link — inject "Edit" link on cart item titles.
		if ( (bool) pizzatier_get_option( 'allow_cart_edit', false ) ) {
			add_filter( 'woocommerce_cart_item_name',    [ $this, 'append_cart_edit_link' ], 10, 3 );
			add_action( 'template_redirect',             [ $this, 'handle_cart_edit_remove' ] );
		}

		// Order-again pre-fill — intercept WC's "order again" flow.
		if ( (bool) pizzatier_get_option( 'allow_reorder', false ) ) {
			add_filter( 'woocommerce_order_again_redirect', [ $this, 'handle_order_again_redirect' ], 10, 2 );
		}
	}

	// -------------------------------------------------------------------------
	// Session persistence
	// -------------------------------------------------------------------------

	/**
	 * Re-hydrate pizza cart item data from the WooCommerce session.
	 *
	 * WooCommerce stores the full cart item array in the session but only
	 * restores a subset of keys when the cart is loaded on the next page request.
	 * Without this hook, any key that WC doesn't know about — including all our
	 * pizzatier_commerce_* keys — would be silently dropped, causing the checkout hook to
	 * receive an empty $cart_item_values and write no order meta.
	 *
	 * @param array $cart_item      Cart item array (being built by WC from session).
	 * @param array $session_values Raw session data for this cart item.
	 * @return array
	 */
	public function load_from_session( array $cart_item, array $session_values ): array {
		$keys = [
			self::CART_SIZE,
			self::CART_LAYERS,
			self::CART_INPUT_LAYERS,
			self::CART_TOTAL,
			self::CART_BASE_PRICE,
			self::CART_ORDER_NOTE,
			self::CART_KEY,
		];
		foreach ( $keys as $key ) {
			if ( isset( $session_values[ $key ] ) ) {
				$cart_item[ $key ] = $session_values[ $key ];
			}
		}
		return $cart_item;
	}

	// -------------------------------------------------------------------------
	// Cart display
	// -------------------------------------------------------------------------

	/**
	 * Add the pizza configuration summary to the cart item display.
	 *
	 * WooCommerce calls this filter for every item in the cart — we only act
	 * on pizza products that have our custom data.
	 *
	 * @param array $item_data  Existing item data rows.
	 * @param array $cart_item  Cart item array including our custom meta.
	 * @return array
	 */
	public function display_in_cart( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item[ self::CART_SIZE ] ) ) {
			return $item_data;
		}

		// Size row.
		$item_data[] = [
			'key'     => __( 'Size', 'pizzatier' ),
			'value'   => esc_html( $cart_item[ self::CART_SIZE ] ),
			'display' => '',
		];

		// Base price row.
		$base_price = (float) ( $cart_item[ self::CART_BASE_PRICE ] ?? 0 );
		if ( $base_price > 0 ) {
			$item_data[] = [
				'key'     => __( 'Base Price', 'pizzatier' ),
				'value'   => wc_price( $base_price ),
				'display' => '',
			];
		}

		// Resolve layers — prefer the priced breakdown; fall back to raw client input.
		$layers = $this->resolve_layers_for_display(
			$cart_item[ self::CART_LAYERS ]       ?? [],
			$cart_item[ self::CART_INPUT_LAYERS ] ?? []
		);

		if ( ! empty( $layers ) ) {
			$groups = $this->group_layers_by_type( $layers );
			foreach ( $this->type_order() as $type ) {
				if ( empty( $groups[ $type ] ) ) {
					continue;
				}
				$lines = [];
				foreach ( $groups[ $type ] as $layer ) {
					$lines[] = $this->format_layer_line_cart( $layer );
				}
				$item_data[] = [
					'key'     => $this->type_label( $type ),
					'value'   => implode( '<br>', $lines ),
					'display' => '',
				];
			}
			// Any unlisted type (future-proofing).
			foreach ( $groups as $type => $type_layers ) {
				if ( in_array( $type, $this->type_order(), true ) ) {
					continue;
				}
				$lines = array_map( [ $this, 'format_layer_line_cart' ], $type_layers );
				$item_data[] = [
					'key'     => $this->type_label( $type ),
					'value'   => implode( '<br>', $lines ),
					'display' => '',
				];
			}
		}

		// Order note row.
		$order_note = isset( $cart_item[ self::CART_ORDER_NOTE ] )
			? (string) $cart_item[ self::CART_ORDER_NOTE ]
			: '';
		if ( $order_note !== '' ) {
			$item_data[] = [
				'key'     => __( 'Note', 'pizzatier' ),
				'value'   => esc_html( $order_note ),
				'display' => '',
			];
		}

		return $item_data;
	}

	// -------------------------------------------------------------------------
	// Cart / order item product name — layer description subtitle
	// -------------------------------------------------------------------------

	/**
	 * Append a compact pizza layer summary as a subtitle beneath the product
	 * name in the WooCommerce cart.
	 *
	 * Rendered as a <span> so themes can style it.  Only runs for pizza items
	 * that have layer data stored.
	 *
	 * @param string $name           Product name HTML (may already contain links).
	 * @param array  $cart_item      WC cart item data array.
	 * @param string $cart_item_key  Cart item hash.
	 * @return string
	 */
	public function append_layer_description( string $name, array $cart_item, string $cart_item_key ): string {
		if ( empty( $cart_item[ self::CART_SIZE ] ) ) {
			return $name;
		}

		$size   = (string) $cart_item[ self::CART_SIZE ];
		$layers = $this->resolve_layers_for_display(
			$cart_item[ self::CART_LAYERS ]       ?? [],
			$cart_item[ self::CART_INPUT_LAYERS ] ?? []
		);

		$subtitle = $this->build_layer_subtitle( $size, $layers );
		if ( '' === $subtitle ) {
			return $name;
		}

		return $name . '<span class="pztc-cart-item-layers">' . $subtitle . '</span>';
	}

	/**
	 * Append the pizza layer summary to the product name in order-view contexts
	 * (order confirmation page, my-account, admin order screen header).
	 *
	 * @param string                 $name  Product name (may be HTML).
	 * @param \WC_Order_Item_Product $item
	 * @return string
	 */
	public function append_order_item_layer_description( string $name, $item ): string {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			return $name;
		}

		$size   = (string) $item->get_meta( self::META_SIZE );
		$layers = $this->resolve_layers_for_display(
			$item->get_meta( self::META_LAYERS ),
			$item->get_meta( self::META_INPUT_LAYERS )
		);

		if ( ! $size ) {
			return $name;
		}

		$subtitle = $this->build_layer_subtitle( $size, $layers );
		if ( '' === $subtitle ) {
			return $name;
		}

		return $name . '<span class="pztc-cart-item-layers">' . $subtitle . '</span>';
	}

	/**
	 * Build the human-readable layer subtitle string.
	 *
	 * Groups layers by type (Crust / Sauce / Cheese / Drizzle / Toppings) so
	 * the summary reads naturally.  Example output:
	 *   "Large — Crust: Thin Crispy · Sauce: Marinara · Toppings: Pepperoni (Half), Mushrooms"
	 *
	 * @param string $size
	 * @param array  $layers  Breakdown array from META_LAYERS.
	 * @return string  Safe HTML substring (no outer wrapper).
	 */
	private function build_layer_subtitle( string $size, array $layers ): string {
		if ( empty( $size ) ) {
			return '';
		}

		$groups = $this->group_layers_by_type( $layers );

		if ( empty( $groups ) ) {
			return esc_html( $size );
		}

		$parts = [ esc_html( $size ) ];

		foreach ( $this->type_order() as $type ) {
			if ( empty( $groups[ $type ] ) ) {
				continue;
			}
			$items = [];
			foreach ( $groups[ $type ] as $layer ) {
				$display  = esc_html( $layer['layerName'] ?? $layer['layerId'] ?? '' );
				$coverage = self::coverage_display( $layer );
				// Always show coverage — even "Whole" — so the subtitle is unambiguous.
				if ( $coverage !== '' ) {
					$display .= ' (' . esc_html( $coverage ) . ')';
				}
				if ( $display !== '' ) {
					$items[] = $display;
				}
			}
			if ( ! empty( $items ) ) {
				$parts[] = esc_html( $this->type_label( $type ) ) . ': ' . implode( ', ', $items );
			}
		}
		// Any unlisted type.
		foreach ( $groups as $type => $type_layers ) {
			if ( in_array( $type, $this->type_order(), true ) ) {
				continue;
			}
			$items = [];
			foreach ( $type_layers as $layer ) {
				$display = esc_html( $layer['layerName'] ?? $layer['layerId'] ?? '' );
				if ( $display !== '' ) {
					$items[] = $display;
				}
			}
			if ( ! empty( $items ) ) {
				$parts[] = esc_html( $this->type_label( $type ) ) . ': ' . implode( ', ', $items );
			}
		}

		return implode( ' &middot; ', $parts );
	}

	// -------------------------------------------------------------------------
	// Order line item — save
	// -------------------------------------------------------------------------

	/**
	 * Copy pizza meta from cart item to the WC order line item on checkout.
	 *
	 * @param \WC_Order_Item_Product $item
	 * @param string                 $cart_item_key
	 * @param array                  $cart_item_values
	 * @param \WC_Order              $order
	 */
	public function save_to_order_item(
		\WC_Order_Item_Product $item,
		string $cart_item_key,
		array $cart_item_values,
		\WC_Order $order
	): void {
		if ( empty( $cart_item_values[ self::CART_SIZE ] ) ) {
			return;
		}

		$breakdown    = $cart_item_values[ self::CART_LAYERS ]       ?? [];
		$input_layers = $cart_item_values[ self::CART_INPUT_LAYERS ] ?? [];
		$size         = (string) $cart_item_values[ self::CART_SIZE ];
		$product_id   = (int) ( $cart_item_values['product_id'] ?? 0 );

		if ( ! is_array( $breakdown ) )    { $breakdown    = []; }
		if ( ! is_array( $input_layers ) ) { $input_layers = []; }

		// If the priced breakdown is empty but the customer DID select layers,
		// synthesize a breakdown from the raw client input. This guarantees the
		// order line item NEVER persists a stripped-down "base price only" record
		// when the customer actually configured a pizza.
		if ( empty( $breakdown ) && ! empty( $input_layers ) ) {
			$breakdown = $this->synthesize_breakdown_from_input( $product_id, $size, $input_layers );
		}

		// ── Raw internal meta (blob storage used by display and price logic) ─────
		$item->add_meta_data( self::META_SIZE,         $size,                                              true );
		$item->add_meta_data( self::META_LAYERS,       $breakdown,                                         true );
		$item->add_meta_data( self::META_INPUT_LAYERS, $input_layers,                                      true );
		$item->add_meta_data( self::META_TOTAL,        $cart_item_values[ self::CART_TOTAL ]      ?? 0.0,  true );
		$item->add_meta_data( self::META_BASE_PRICE,   $cart_item_values[ self::CART_BASE_PRICE ] ?? 0.0,  true );

		$note = isset( $cart_item_values[ self::CART_ORDER_NOTE ] )
			? (string) $cart_item_values[ self::CART_ORDER_NOTE ]
			: '';
		if ( $note !== '' ) {
			$item->add_meta_data( self::META_ORDER_NOTE, $note, true );
		}

		// ── Individual flat meta key per layer type ───────────────────────────────
		// Writing these separately makes the full pizza build visible to every
		// downstream system that reads WC order item meta directly: packing slips,
		// 3rd-party OMS tools, REST API consumers, custom reports, and WC's own
		// admin meta display (surfaced via format_order_item_meta).
		//
		// Key format  : _pizzatier_commerce_{type}  e.g. _pizzatier_commerce_crust, _pizzatier_commerce_toppings
		// Value format: "Name (Coverage) +$price, Name (Coverage)" — one string
		//               per type group, comma-separated within the group.
		//
		// Underscore prefix keeps them hidden from WC's raw meta table while
		// still being retrievable via get_meta().
		if ( is_array( $breakdown ) && ! empty( $breakdown ) ) {
			$groups = $this->group_layers_by_type( $breakdown );
			$all_types = array_merge(
				$this->type_order(),
				array_diff( array_keys( $groups ), $this->type_order() )
			);
			$cur = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
			$dec = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;

			foreach ( $all_types as $type ) {
				if ( empty( $groups[ $type ] ) ) {
					continue;
				}
				$parts = [];
				foreach ( $groups[ $type ] as $layer ) {
					$name     = $layer['layerName'] ?? $layer['layerId'] ?? '';
					$coverage = self::coverage_display( $layer );
					$price    = isset( $layer['price'] ) ? (float) $layer['price'] : null;

					$entry = $name;
					// Always include coverage — even "Whole" — so values are unambiguous.
					if ( $coverage !== '' ) {
						$entry .= ' (' . $coverage . ')';
					}
					if ( $price !== null && $price > 0 ) {
						$entry .= ' +' . $cur . number_format( $price, $dec );
					}
					if ( $name !== '' ) {
						$parts[] = $entry;
					}
				}
				if ( ! empty( $parts ) ) {
					// _pizzatier_commerce_crust | _pizzatier_commerce_sauce | _pizzatier_commerce_cheese |
					// _pizzatier_commerce_drizzle | _pizzatier_commerce_cut | _pizzatier_commerce_toppings
					$meta_key = '_pizzatier_' . ( 'topping' === $type ? 'toppings' : $type );
					$item->add_meta_data( $meta_key, implode( ', ', $parts ), true );
				}
			}
		}
	}

	/**
	 * Build a breakdown array from raw client-input layers, looking each layer's
	 * price up via the per-product price grid. Returns the same shape as
	 * PriceCalculator::breakdown_entry().
	 *
	 * Used as a safety net inside save_to_order_item() when CART_LAYERS arrives
	 * empty for any reason (zero-grid fallback, future code paths, race
	 * conditions, etc.) — it ensures the order line item is NEVER persisted
	 * without the customer's chosen layers.
	 *
	 * @param int    $product_id
	 * @param string $size
	 * @param array  $input_layers  Sanitized layers from the client payload.
	 * @return array  Breakdown array.
	 */
	private function synthesize_breakdown_from_input( int $product_id, string $size, array $input_layers ): array {
		$breakdown = [];

		// Try to use the price grid for accurate pricing; fall back to 0 if it
		// is unavailable for any reason.
		$grid = null;
		if ( class_exists( '\\PizzaTier\Commerce\PriceGrid\\Grid' ) ) {
			$grid = new \PizzaTier\Commerce\PriceGrid\Grid();
		}

		foreach ( $input_layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$layer_id = (string) ( $layer['layerId'] ?? '' );
			if ( '' === $layer_id ) {
				continue;
			}

			$layer_post_id = (int) ( $layer['layerPostId'] ?? 0 );
			$fraction      = (string) ( $layer['fraction']  ?? 'Whole' );
			$portion       = self::canonical_portion( (string) ( $layer['portion'] ?? '' ) );
			$portion_label = (string) ( $layer['portionLabel'] ?? '' );
			$layer_type    = (string) ( $layer['layerType'] ?? '' );
			$layer_name    = (string) ( $layer['layerName'] ?? '' );

			if ( '' === $layer_name ) {
				$layer_name = ucwords( str_replace( [ '-', '_' ], ' ', $layer_id ) );
			}

			$price = 0.0;
			if ( $grid && $layer_post_id > 0 ) {
				$looked_up = $grid->get_layer_price( $layer_post_id, $product_id, $size, $fraction );
				if ( null === $looked_up ) {
					// Try the product-level grid fallback.
					$looked_up = $grid->get_price( $product_id, $size, $fraction );
				}
				if ( is_numeric( $looked_up ) ) {
					$price = (float) $looked_up;
				}
			}

			$breakdown[] = [
				'layerId'         => $layer_id,
				'layerName'       => $layer_name,
				'fraction'        => $fraction,
				'portion'         => $portion,
				'portionLabel'    => $portion_label,
				'layerType'       => $layer_type,
				'layerPostId'     => $layer_post_id,
				'price'           => $price,
				'price_formatted' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price ) ) : (string) $price,
				'note'            => '',
			];
		}

		return $breakdown;
	}

	// -------------------------------------------------------------------------
	// Order admin display
	// -------------------------------------------------------------------------

	/**
	 * Output a human-readable pizza configuration table inside the WC order
	 * items metabox in wp-admin.
	 *
	 * @param int                    $item_id
	 * @param \WC_Order_Item_Product $item
	 * @param \WC_Product|false      $product
	 */
	public function display_in_order_admin( int $item_id, $item, $product ): void {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			return;
		}

		$size       = $item->get_meta( self::META_SIZE );
		$total      = $item->get_meta( self::META_TOTAL );
		$base_price = $item->get_meta( self::META_BASE_PRICE );
		$order_note = (string) $item->get_meta( self::META_ORDER_NOTE );

		$layers = $this->resolve_layers_for_display(
			$item->get_meta( self::META_LAYERS ),
			$item->get_meta( self::META_INPUT_LAYERS )
		);

		if ( ! $size ) {
			return;
		}

		$cur     = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		$dec     = wc_get_price_decimals();
		$groups  = $this->group_layers_by_type( $layers );
		?>
		<div class="pztc-order-meta">
			<table class="pztc-order-meta__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Layer', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Price', 'pizzatier' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Base pizza price row -->
					<tr class="pztc-order-meta__base-row">
						<td><em><?php esc_html_e( 'Base', 'pizzatier' ); ?></em></td>
						<td colspan="2"><em><?php esc_html_e( 'Base pizza price', 'pizzatier' ); ?></em></td>
						<td><?php echo esc_html( $cur . number_format( (float) $base_price, $dec ) ); ?></td>
					</tr>

					<?php
					// Render each type group in canonical order.
					$all_types = array_merge( $this->type_order(), array_diff( array_keys( $groups ), $this->type_order() ) );
					foreach ( $all_types as $type ) :
						if ( empty( $groups[ $type ] ) ) {
							continue;
						}
						$type_label = $this->type_label( $type );
						$type_layers = $groups[ $type ];
						$row_count   = count( $type_layers );
						foreach ( $type_layers as $idx => $layer ) :
							$layer_name = $layer['layerName'] ?? $layer['layerId'] ?? '';
							$fraction   = self::coverage_display( $layer );
							$price      = isset( $layer['price'] ) ? (float) $layer['price'] : 0.0;
							$note       = $layer['note'] ?? '';
							?>
							<tr class="pztc-order-meta__layer-row pztc-order-meta__type--<?php echo esc_attr( $type ); ?>">
								<?php if ( 0 === $idx ) : ?>
								<td rowspan="<?php echo esc_attr( $row_count ); ?>" class="pztc-order-meta__type-cell">
									<strong><?php echo esc_html( $type_label ); ?></strong>
								</td>
								<?php endif; ?>
								<td>
									<?php echo esc_html( $layer_name ); ?>
									<?php if ( $note ) : ?>
										<br><em style="font-size:10px;color:#888;"><?php echo esc_html( $note ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $fraction ); ?></td>
								<td>
									<?php if ( $price > 0 ) : ?>
										+ <?php echo esc_html( $cur . number_format( $price, $dec ) ); ?>
									<?php elseif ( $note ) : ?>
										<em style="color:#888;">—</em>
									<?php else : ?>
										<?php echo esc_html( $cur . number_format( $price, $dec ) ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr class="pztc-order-meta__total-row">
						<td colspan="3">
							<strong><?php esc_html_e( 'Size:', 'pizzatier' ); ?> <?php echo esc_html( $size ); ?></strong>
						</td>
						<td><strong><?php echo esc_html( $cur . number_format( (float) $total, $dec ) ); ?></strong></td>
					</tr>
					<?php if ( $order_note !== '' ) : ?>
					<tr>
						<td colspan="4" class="pztc-order-meta__note-row">
							<strong><?php esc_html_e( 'Customer note:', 'pizzatier' ); ?></strong>
							<?php echo esc_html( $order_note ); ?>
						</td>
					</tr>
					<?php endif; ?>
				</tfoot>
			</table>
		</div>
		<style>
		.pztc-order-meta { margin: 8px 0 4px; }
		.pztc-order-meta__table { width: 100%; border-collapse: collapse; font-size: 12px; }
		.pztc-order-meta__table th,
		.pztc-order-meta__table td { padding: 4px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
		.pztc-order-meta__table thead th { background: #1a1a2e; color: #fff; }
		.pztc-order-meta__table tfoot td { background: #f9f9f9; }
		.pztc-order-meta__total-row td { border-top: 2px solid #e8692a; }
		.pztc-order-meta__type-cell { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
		.pztc-order-meta__base-row td { font-style: italic; background: #fafafa; }
		.pztc-order-meta__note-row { font-size: 11px; color: #555; background: #fffbe6; border-top: 1px solid #f0b429; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Order confirmation / my-account display
	// -------------------------------------------------------------------------

	/**
	 * Format order item meta for display on the order confirmation page
	 * and in my-account order detail views.
	 *
	 * We hide the raw underscore-prefixed keys and inject readable ones.
	 *
	 * @param array                  $formatted_meta
	 * @param \WC_Order_Item_Product $item
	 * @return array
	 */
	public function format_order_item_meta( array $formatted_meta, \WC_Order_Item $item ): array {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			return $formatted_meta;
		}

		// Remove raw internal blob keys AND the individual flat type keys from
		// WC's public display — we re-emit them in the correct grouped order below.
		$hidden = [
			self::META_SIZE, self::META_LAYERS, self::META_INPUT_LAYERS,
			self::META_TOTAL, self::META_BASE_PRICE, self::META_ORDER_NOTE,
			// Individual flat type keys written by save_to_order_item().
			'_pizzatier_crust', '_pizzatier_sauce', '_pizzatier_cheese',
			'_pizzatier_drizzle', '_pizzatier_cut', '_pizzatier_toppings',
		];
		$formatted_meta = array_filter( $formatted_meta, function ( $meta ) use ( $hidden ) {
			// Also suppress any unknown _pizzatier_commerce_* type keys (future-proofing).
			if ( strpos( (string) $meta->key, '_pizzatier_' ) === 0 ) {
				return false;
			}
			return ! in_array( $meta->key, $hidden, true );
		} );

		$size   = $item->get_meta( self::META_SIZE );
		$layers = $this->resolve_layers_for_display(
			$item->get_meta( self::META_LAYERS ),
			$item->get_meta( self::META_INPUT_LAYERS )
		);

		if ( ! $size ) {
			return array_values( $formatted_meta );
		}

		// Size entry — always first.
		$size_meta               = new \stdClass();
		$size_meta->key          = self::DISPLAY_SIZE;
		$size_meta->display_key  = __( 'Size', 'pizzatier' );
		$size_meta->display_value = esc_html( $size );
		$formatted_meta[]        = $size_meta;

		// Base price line — visible to the customer on the order details page.
		$base_price = (float) $item->get_meta( self::META_BASE_PRICE );
		if ( $base_price > 0 ) {
			$base_meta                = new \stdClass();
			$base_meta->key           = '_pizzatier_display_base_price';
			$base_meta->display_key   = __( 'Base Price', 'pizzatier' );
			$base_meta->display_value = wp_kses_post( wc_price( $base_price ) );
			$formatted_meta[]         = $base_meta;
		}

		// One meta row per layer type group, in canonical order.
		if ( is_array( $layers ) && ! empty( $layers ) ) {
			$groups    = $this->group_layers_by_type( $layers );
			$all_types = array_merge(
				$this->type_order(),
				array_diff( array_keys( $groups ), $this->type_order() )
			);

			$type_index = 0;
			foreach ( $all_types as $type ) {
				if ( empty( $groups[ $type ] ) ) {
					continue;
				}
				$lines = [];
				foreach ( $groups[ $type ] as $layer ) {
					$name     = esc_html( $layer['layerName'] ?? $layer['layerId'] ?? '' );
					$fraction = self::coverage_display( $layer );
					$price    = isset( $layer['price'] ) ? (float) $layer['price'] : null;

					$line = $name;
					// Always show coverage — even "Whole" — so the value is unambiguous.
					if ( $fraction !== '' ) {
						$line .= ' (' . esc_html( $fraction ) . ')';
					}
					// Show price only when it's a real add-on (> 0).
					if ( $price !== null && $price > 0 ) {
						$line .= ' +' . wc_price( $price );
					}
					if ( $name !== '' ) {
						$lines[] = $line;
					}
				}

				if ( empty( $lines ) ) {
					continue;
				}

				$type_meta               = new \stdClass();
				// Unique key per type group so WC doesn't deduplicate them.
				$type_meta->key          = self::DISPLAY_LAYERS . '_' . $type_index;
				$type_meta->display_key  = $this->type_label( $type );
				$type_meta->display_value = implode( ', ', $lines );
				$formatted_meta[]        = $type_meta;
				$type_index++;
			}
		}

		// Order note entry.
		$order_note = (string) $item->get_meta( self::META_ORDER_NOTE );
		if ( $order_note !== '' ) {
			$note_meta               = new \stdClass();
			$note_meta->key          = '_pizzatier_display_note';
			$note_meta->display_key  = __( 'Note', 'pizzatier' );
			$note_meta->display_value = esc_html( $order_note );
			$formatted_meta[]        = $note_meta;
		}

		return array_values( $formatted_meta );
	}

	// -------------------------------------------------------------------------
	// Shared layer grouping helpers
	// -------------------------------------------------------------------------

	/**
	 * Canonical type rendering order.
	 *
	 * @return string[]
	 */
	private function type_order(): array {
		return [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut', 'topping' ];
	}

	/**
	 * Human-readable singular/plural label for a layer type.
	 *
	 * @param string $type  Normalised lowercase type slug.
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

	/**
	 * Pick the best available layers source for display.
	 *
	 * Prefers the priced breakdown (META_LAYERS / CART_LAYERS — has prices, notes
	 * and synthetic entries like flat-rate rows). Falls back to the raw client
	 * input (META_INPUT_LAYERS / CART_INPUT_LAYERS) when the breakdown is empty,
	 * which can happen if the breakdown was never populated by a pricing engine
	 * (e.g. zero-grid fallback) — the input list is always populated with
	 * exactly what the customer selected.
	 *
	 * If the breakdown only contains synthetic internal entries (layerId starting
	 * with "_") and is missing the actual customer-selected layers, the input
	 * list is concatenated so display always shows the layers.
	 *
	 * @param mixed $breakdown    Priced breakdown array (or non-array).
	 * @param mixed $input_layers Raw client input layers (or non-array).
	 * @return array  Best-available layers array (numeric-indexed).
	 */
	private function resolve_layers_for_display( $breakdown, $input_layers ): array {
		$breakdown    = is_array( $breakdown )    ? $breakdown    : [];
		$input_layers = is_array( $input_layers ) ? $input_layers : [];

		// Count "real" entries in breakdown — those whose layerId doesn't start with '_'.
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

		// If the breakdown already contains real entries, prefer it (it has prices).
		if ( ! empty( $real_breakdown_ids ) ) {
			// Append any input layers that were not represented in the breakdown.
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

		// Breakdown empty (or only synthetic) — use input layers verbatim, with a
		// zero price so display logic stays clean. Synthetic breakdown rows
		// (e.g. _flat_rate, _tier) come along for the ride; group_layers_by_type()
		// strips them before display.
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
	 * Group a flat layer breakdown array into buckets keyed by normalised type.
	 * Layers with no type default to 'topping'.
	 *
	 * @param array $layers  META_LAYERS breakdown array.
	 * @return array<string, array>  Type slug => array of layer entries.
	 */
	private function group_layers_by_type( array $layers ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$name = $layer['layerName'] ?? $layer['layerId'] ?? '';
			if ( '' === $name ) {
				continue;
			}
			// Internal synthetic entries (flat-rate, tier charge) start with '_' — skip.
			if ( isset( $layer['layerId'] ) && strpos( (string) $layer['layerId'], '_' ) === 0 ) {
				continue;
			}
			$type = strtolower( (string) ( $layer['layerType'] ?? '' ) );
			if ( '' === $type ) {
				$type = 'topping';
			}
			$groups[ $type ][] = $layer;
		}
		return $groups;
	}

	/**
	 * Format a single layer entry as a display string for cart/checkout item data rows.
	 * Includes coverage (when not Whole) and add-on price (when > 0).
	 *
	 * @param array $layer  Single entry from META_LAYERS.
	 * @return string  HTML-safe string.
	 */
	private function format_layer_line_cart( array $layer ): string {
		$name     = esc_html( $layer['layerName'] ?? $layer['layerId'] ?? '' );
		$type     = strtolower( (string) ( $layer['layerType'] ?? 'topping' ) );
		$fraction = self::coverage_display( $layer );
		$price    = isset( $layer['price'] ) ? (float) $layer['price'] : null;

		$line = $name;

		// Always show coverage so the customer knows exactly what they selected.
		if ( $fraction !== '' ) {
			$line .= ' (' . esc_html( $fraction ) . ')';
		}

		// Show add-on price when it is a real positive charge.
		if ( $price !== null && $price > 0 ) {
			$line .= ' +' . wc_price( $price );
		}

		return $line;
	}

	// -------------------------------------------------------------------------
	// Cart Edit — "Edit" link on cart items
	// -------------------------------------------------------------------------

	/**
	 * Append an "Edit pizza" link to the cart item name for pizza products.
	 *
	 * The link encodes the current configuration (size + layers) into a
	 * signed URL parameter so the product page can rehydrate the builder.
	 * The cart item is removed from the cart once the customer clicks Edit,
	 * so they are effectively replacing it with a new configuration.
	 *
	 * @param string $name           Cart item name HTML.
	 * @param array  $cart_item      Cart item data.
	 * @param string $cart_item_key  Cart item key.
	 * @return string
	 */
	public function append_cart_edit_link( string $name, array $cart_item, string $cart_item_key ): string {
		if ( empty( $cart_item[ self::CART_SIZE ] ) ) {
			return $name;
		}

		$product_id = (int) ( $cart_item['product_id'] ?? 0 );
		if ( ! $product_id ) {
			return $name;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'pizza' !== $product->get_type() ) {
			return $name;
		}

		// Build the encoded configuration payload.
		$edit_layers = $this->resolve_layers_for_display(
			$cart_item[ self::CART_LAYERS ]       ?? [],
			$cart_item[ self::CART_INPUT_LAYERS ] ?? []
		);
		$config = [
			's' => $cart_item[ self::CART_SIZE ],
			'l' => array_map( function ( array $layer ) {
				return [
					'id' => $layer['layerId']   ?? '',
					'fr' => $layer['fraction']   ?? 'Whole',
					'po' => $layer['portion']      ?? '',
					'pl' => $layer['portionLabel'] ?? '',
					'lt' => $layer['layerType'] ?? '',
					'nm' => $layer['layerName'] ?? '',
				];
			}, $edit_layers ),
			'n' => $cart_item[ self::CART_ORDER_NOTE ] ?? '',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Builds a data: URI from inline SVG markup defined above; nothing is decoded or executed.
		$payload   = base64_encode( wp_json_encode( $config ) );
		$signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		$edit_url = add_query_arg(
			[
				'pizzatier_commerce_edit_key'  => $cart_item_key,
				'pizzatier_commerce_cfg'       => $payload,
				'pizzatier_commerce_sig'       => substr( $signature, 0, 16 ),
				'pizzatier_commerce_nonce'     => wp_create_nonce( 'pizzatier_commerce_cart_edit_' . $cart_item_key ),
			],
			get_permalink( $product_id )
		);

		$edit_link = sprintf(
			' <a href="%s" class="pztc-cart-edit-link">%s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit pizza', 'pizzatier' )
		);

		return $name . $edit_link;
	}

	/**
	 * On template_redirect, remove the cart item being edited so the customer
	 * starts fresh with the pre-filled builder. Only fires when a valid
	 * pizzatier_commerce_edit_key + nonce are present in the URL.
	 */
	public function handle_cart_edit_remove(): void {
		if ( empty( $_GET['pizzatier_commerce_edit_key'] ) || empty( $_GET['pizzatier_commerce_nonce'] ) ) {
			return;
		}

		$cart_item_key = sanitize_key( $_GET['pizzatier_commerce_edit_key'] );
		$nonce         = sanitize_key( $_GET['pizzatier_commerce_nonce'] );

		if ( ! wp_verify_nonce( $nonce, 'pizzatier_commerce_cart_edit_' . $cart_item_key ) ) {
			return;
		}

		// Verify the signature on the config payload before trusting it.
		$payload   = sanitize_text_field( wp_unslash( $_GET['pizzatier_commerce_cfg'] ?? '' ) );
		$sig_given = sanitize_key( $_GET['pizzatier_commerce_sig'] ?? '' );
		$sig_calc  = substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, 16 );

		if ( ! hash_equals( $sig_calc, $sig_given ) ) {
			return;
		}

		// Remove the item being edited from the cart.
		if ( WC()->cart && WC()->cart->get_cart_item( $cart_item_key ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
		}
	}

	// -------------------------------------------------------------------------
	// Order Again — pre-fill builder from order history
	// -------------------------------------------------------------------------

	/**
	 * When a customer clicks "Order Again", redirect them to the pizza product
	 * page with their previous configuration pre-filled, rather than silently
	 * adding to the cart (which would skip the builder entirely).
	 *
	 * WooCommerce fires woocommerce_order_again_redirect after adding all
	 * items from the previous order. We intercept and redirect to the first
	 * pizza product page instead, removing it from the cart first.
	 *
	 * @param string    $redirect  Default redirect URL (usually cart page).
	 * @param \WC_Order $order     The original order being repeated.
	 * @return string
	 */
	public function handle_order_again_redirect( string $redirect, \WC_Order $order ): string {
		// Find the first pizza line item in the order.
		$pizza_item = null;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$size = $item->get_meta( self::META_SIZE );
			if ( $size ) {
				$pizza_item = $item;
				break;
			}
		}

		if ( ! $pizza_item ) {
			return $redirect;
		}

		$product_id = (int) $pizza_item->get_product_id();
		if ( ! $product_id ) {
			return $redirect;
		}

		$size   = (string) $pizza_item->get_meta( self::META_SIZE );
		$layers = $this->resolve_layers_for_display(
			$pizza_item->get_meta( self::META_LAYERS ),
			$pizza_item->get_meta( self::META_INPUT_LAYERS )
		);

		if ( ! $size || empty( $layers ) ) {
			return $redirect;
		}

		// Remove the item that WC just added to the cart (WC adds it before this hook fires).
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
				if ( (int) ( $cart_item['product_id'] ?? 0 ) === $product_id
					&& ! empty( $cart_item[ self::CART_SIZE ] ) ) {
					WC()->cart->remove_cart_item( $key );
					break;
				}
			}
		}

		// Build the pre-fill payload (same format as cart edit).
		$note   = (string) $pizza_item->get_meta( self::META_ORDER_NOTE );
		$config = [
			's' => $size,
			'l' => array_map( function ( array $layer ) {
				return [
					'id' => $layer['layerId']   ?? '',
					'fr' => $layer['fraction']   ?? 'Whole',
					'po' => $layer['portion']      ?? '',
					'pl' => $layer['portionLabel'] ?? '',
					'lt' => $layer['layerType'] ?? '',
					'nm' => $layer['layerName'] ?? '',
				];
			}, $layers ),
			'n' => $note,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Builds a data: URI from inline SVG markup defined above; nothing is decoded or executed.
		$payload   = base64_encode( wp_json_encode( $config ) );
		$signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

		return add_query_arg(
			[
				'pizzatier_commerce_cfg'       => $payload,
				'pizzatier_commerce_sig'       => substr( $signature, 0, 16 ),
				'pizzatier_commerce_reorder'   => '1',
			],
			get_permalink( $product_id )
		);
	}
}
