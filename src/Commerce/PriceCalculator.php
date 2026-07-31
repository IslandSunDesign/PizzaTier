<?php
/**
 * Server-side price calculator.
 *
 * Supports multiple pricing engine modes configured via PizzaTier settings:
 *
 *  addon_per_layer  — (default) base + sum of grid[size][fraction] per layer
 *  flat_per_size    — base + grid[size][Whole] once, regardless of layer count
 *  highest_wins     — base + highest single layer grid price
 *  tiered_by_count  — base + grid[size][TierN] where N is determined by topping count
 *  free_first_n     — base + grid price only for layers beyond the free N count
 *  bundle           — product price only; grid prices ignored
 *
 * Additionally applies:
 *  - Non-topping fixed pricing (crust/sauce/cheese/drizzle flat add-ons)
 *  - Free toppings deduction (first N toppings at no charge)
 *  - Bulk discount (% off add-on total when topping count ≥ threshold)
 *  - Price rounding (WC decimals / up / nearest5 / nearest25)
 *
 * @package PizzaTier\Commerce
 */

namespace PizzaTier\Commerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PizzaTier\Commerce\PriceGrid\Grid;

class PriceCalculator {

	/** @var Grid */
	private Grid $grid;

	// Layer types considered "non-toppings" (base components vs. add-on toppings)
	const NON_TOPPING_TYPES = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];

	public function __construct( Grid $grid ) {
		$this->grid = $grid;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Calculate the total for a pizza configuration.
	 *
	 * @param int    $product_id
	 * @param string $size
	 * @param array  $layers  [ ['layerId'=>string, 'fraction'=>string, 'layerType'=>string?], … ]
	 * @return array|\WP_Error
	 */
	public function calculate( int $product_id, string $size, array $layers ) {
		// ── Validate product ──────────────────────────────────────────────
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new \WP_Error( 'pizzatier_commerce_invalid_product', __( 'Product not found.', 'pizzatier' ), [ 'status' => 404 ] );
		}

		// Accept pizza type OR any product with a PizzaTier builder template configured.
		$has_template = '' !== (string) $product->get_meta( '_pizzatier_builder_template', true );
		if ( 'pizza' !== $product->get_type() && ! $has_template ) {
			return new \WP_Error( 'pizzatier_commerce_not_pizza_product', __( 'This product is not a pizza product.', 'pizzatier' ), [ 'status' => 400 ] );
		}

		// ── Validate size ─────────────────────────────────────────────────
		$size = sanitize_text_field( $size );
		if ( '' === $size ) {
			return new \WP_Error( 'pizzatier_commerce_missing_size', __( 'A pizza size must be selected.', 'pizzatier' ), [ 'status' => 400 ] );
		}

		$valid_sizes = $this->grid->get_sizes( $product_id );
		if ( ! in_array( $size, $valid_sizes, true ) ) {
			return new \WP_Error( 'pizzatier_commerce_invalid_size',
				/* translators: %s: value inserted into the message. */
				sprintf( __( '"%s" is not a valid size for this product.', 'pizzatier' ), esc_html( $size ) ),
				[ 'status' => 400 ] );
		}

		// ── Validate layers ───────────────────────────────────────────────
		if ( ! is_array( $layers ) ) {
			return new \WP_Error( 'pizzatier_commerce_invalid_layers', __( 'Layer data is invalid.', 'pizzatier' ), [ 'status' => 400 ] );
		}

		$enabled_layer_ids = $this->get_enabled_layer_ids( $product_id );
		$valid_fractions   = $this->grid->get_fractions( $product_id );

		// Sanitise and validate each layer entry
		$sanitised_layers = [];
		foreach ( $layers as $index => $layer ) {
			$layer_id      = sanitize_text_field( (string) ( $layer['layerId']      ?? '' ) );
			$fraction      = sanitize_text_field( (string) ( $layer['fraction']     ?? '' ) );
			$layer_type    = sanitize_text_field( (string) ( $layer['layerType']    ?? '' ) );
			// layerPostId: the WP post ID of the CPT item (e.g. Pepperoni post).
			// Used for per-layer grid lookup; 0 means "no per-layer grid, use product fallback".
			$layer_post_id = absint( $layer['layerPostId'] ?? 0 );

			if ( '' === $layer_id ) {
				return new \WP_Error( 'pizzatier_commerce_missing_layer_id',
					/* translators: %s: value inserted into the message. */
					sprintf( __( 'Layer at position %d is missing an ID.', 'pizzatier' ), $index ),
					[ 'status' => 400 ] );
			}

			if ( ! empty( $enabled_layer_ids ) && ! in_array( $layer_id, $enabled_layer_ids, true ) ) {
				return new \WP_Error( 'pizzatier_commerce_layer_not_permitted',
					/* translators: %s: value inserted into the message. */
					sprintf( __( 'Layer "%s" is not available on this product.', 'pizzatier' ), esc_html( $layer_id ) ),
					[ 'status' => 400 ] );
			}

			if ( '' === $fraction ) {
				return new \WP_Error( 'pizzatier_commerce_missing_fraction',
					/* translators: %s: value inserted into the message. */
					sprintf( __( 'Layer "%s" is missing a coverage fraction.', 'pizzatier' ), esc_html( $layer_id ) ),
					[ 'status' => 400 ] );
			}

			// Normalise the fraction string to the nearest configured grid label
			// before validating.  Templates emit varied slugs ('half-left',
			// 'HalfLeft', 'whole', etc.) that must map to canonical labels
			// ('Whole', 'Half', 'Quarter').  normalise_fraction() handles this;
			// after normalisation the strict in_array check is safe.
			if ( ! empty( $valid_fractions ) ) {
				$normalised = $this->normalise_fraction( $fraction, $valid_fractions );
				if ( null !== $normalised ) {
					$fraction = $normalised;
				}

				if ( ! in_array( $fraction, $valid_fractions, true ) ) {
					return new \WP_Error( 'pizzatier_commerce_invalid_fraction',
						/* translators: %1$s: value 1, %2$s: value 2. */
						sprintf( __( 'Coverage fraction "%1$s" for layer "%2$s" is not valid.', 'pizzatier' ), esc_html( $fraction ), esc_html( $layer_id ) ),
						[ 'status' => 400 ] );
				}
			}
			// If valid_fractions is empty the product has no saved grid yet —
			// allow any fraction through so the order still stores correctly.

			$sanitised_layers[] = [
				'layerId'      => $layer_id,
				'fraction'     => $fraction,
				'portion'      => \PizzaTier\Commerce\WooCommerce\OrderMeta::canonical_portion( (string) ( $layer['portion'] ?? '' ) ),
				'portionLabel' => sanitize_text_field( (string) ( $layer['portionLabel'] ?? '' ) ),
				'layerType'    => $layer_type,
				'layerPostId'  => $layer_post_id,
			];
		}

		// ── Route to pricing engine ────────────────────────────────────────
		// Per-product override takes precedence over global setting.
		$product_mode = (string) get_post_meta( $product_id, '_pizzatier_pricing_mode', true );
		$mode         = ( '' !== $product_mode ) ? $product_mode : (string) pizzatier_get_option( 'pricing_mode', 'addon_per_layer' );
		$base_price   = (float) $product->get_price();

		$result = $this->run_engine( $mode, $product_id, $size, $sanitised_layers, $base_price );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		[ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => $free_count ] = $result;

		// ── Apply per-size multiplier ────────────────────────────────────────
		$size_mult = $this->get_size_multiplier( $size );
		if ( $size_mult !== 1.0 ) {
			$add_on = $add_on * $size_mult;
		}

		// ── Apply bulk discount ────────────────────────────────────────────
		$discount_pct       = 0.0;
		$discount_amount    = 0.0;
		$discount_threshold = (int)   pizzatier_get_option( 'discount_threshold', 0 );
		$discount_percent   = (float) pizzatier_get_option( 'discount_percent',   0 );
		$discount_max       = pizzatier_get_option( 'discount_max_amount', '' );
		$topping_count      = count( array_filter( $sanitised_layers, fn( $l ) => ! $this->is_non_topping( $l['layerType'] ) ) );

		if ( $discount_threshold > 0 && $discount_percent > 0 && $topping_count >= $discount_threshold ) {
			$discount_pct    = $discount_percent / 100;
			$discount_amount = round( $add_on * $discount_pct, wc_get_price_decimals() );
			// Cap discount
			if ( $discount_max !== '' && (float) $discount_max > 0 ) {
				$discount_amount = min( $discount_amount, (float) $discount_max );
			}
			$add_on = max( 0, $add_on - $discount_amount );
		}

		// ── Round final total ─────────────────────────────────────────────
		$raw_total  = $base_price + $add_on;
		$total      = $this->apply_rounding( $raw_total );

		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

		return [
			'total'            => $total,
			'total_formatted'  => $this->format_price( $total ),
			'currency_symbol'  => $currency_symbol,
			'size'             => $size,
			'base_price'       => $base_price,
			'add_on'           => $add_on,
			'discount_amount'  => $discount_amount,
			'discount_pct'     => $discount_pct,
			'free_count'       => $free_count,
			'topping_count'    => $topping_count,
			'pricing_mode'     => $mode,
			'breakdown'        => $breakdown,
			'layer_count'      => count( $breakdown ),
		];
	}

	// -------------------------------------------------------------------------
	// Pricing engines
	// -------------------------------------------------------------------------

	/**
	 * @return array{ add_on: float, breakdown: array, free_count: int }|\WP_Error
	 */
	private function run_engine( string $mode, int $product_id, string $size, array $layers, float $base_price ) {
		switch ( $mode ) {
			case 'flat_per_size':    return $this->engine_flat_per_size(   $product_id, $size, $layers );
			case 'highest_wins':     return $this->engine_highest_wins(    $product_id, $size, $layers );
			case 'tiered_by_count':  return $this->engine_tiered_by_count( $product_id, $size, $layers );
			case 'free_first_n':     return $this->engine_free_first_n(    $product_id, $size, $layers );
			case 'bundle':           return $this->engine_bundle( $layers );
			default:                 return $this->engine_addon_per_layer( $product_id, $size, $layers );
		}
	}

	/**
	 * Default: each layer's grid price (size × fraction) summed.
	 * Non-topping layers use either the grid or a fixed add-on depending on setting.
	 */
	private function engine_addon_per_layer( int $product_id, string $size, array $layers ) {
		$breakdown   = [];
		$add_on      = 0.0;
		$free_remain = (int) pizzatier_get_option( 'free_toppings_count', 0 );
		// Check once whether any grid exists at all — if not, all layers price at 0.
		// Includes the site-wide global grids set on the Pricing page; if any are
		// configured, products without their own grid can still produce non-zero
		// prices through the global fallback.
		$has_any_grid = null !== $this->grid->get( $product_id )
			|| null !== $this->grid->get_flat( $product_id )
			|| ! empty( $this->grid->get_all_global_grids() );

		foreach ( $layers as $layer ) {
			$is_nt         = $this->is_non_topping( $layer['layerType'] );
			$layer_post_id = (int) ( $layer['layerPostId'] ?? 0 );

			if ( $is_nt ) {
				[ $price, $err ] = $this->non_topping_price( $product_id, $size, $layer );
				if ( $err ) return $err;
			} else {
				// Topping: check free allowance first.
				if ( $free_remain > 0 ) {
					$price = 0.0;
					$free_remain--;
				} else {
					$price = $this->grid->get_layer_price( $layer_post_id, $product_id, $size, $layer['fraction'], (string) $layer['layerType'] );
					if ( null === $price ) {
						// No grid cell found. If no grid is configured at all, use $0
						// so the order stores with base price only. Otherwise error.
						if ( ! $has_any_grid ) {
							$price = 0.0;
						} else {
							return $this->missing_cell_error( $size, $layer['fraction'] );
						}
					}
					$price = $this->apply_topping_price_bounds( $price );
				}
			}

			$add_on     += $price;
			$breakdown[] = $this->breakdown_entry( $layer, $price );
		}

		return [ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => (int) pizzatier_get_option( 'free_toppings_count', 0 ) - $free_remain ];
	}

	/**
	 * Flat per size: look up grid[size][Whole] once for the whole order.
	 * Non-toppings handled separately by their own pricing rule.
	 */
	private function engine_flat_per_size( int $product_id, string $size, array $layers ) {
		$flat_price = $this->grid->get_price( $product_id, $size, 'Whole' );
		if ( null === $flat_price && ! empty( $layers ) ) {
			return $this->missing_cell_error( $size, 'Whole' );
		}
		$flat_price = $flat_price ?? 0.0;

		$add_on    = $flat_price;
		$breakdown = [];

		foreach ( $layers as $layer ) {
			if ( $this->is_non_topping( $layer['layerType'] ) ) {
				[ $price, $err ] = $this->non_topping_price( $product_id, $size, $layer );
				if ( $err ) return $err;
				$add_on     += $price;
				$breakdown[] = $this->breakdown_entry( $layer, $price );
			} else {
				$breakdown[] = $this->breakdown_entry( $layer, 0.0, __( 'Included in flat rate', 'pizzatier' ) );
			}
		}

		// Prepend the flat rate entry.
		array_unshift( $breakdown, [
			'layerId'         => '_flat_rate',
			/* translators: %s: value inserted into the message. */
			'layerName'       => sprintf( __( 'Flat rate (%s)', 'pizzatier' ), $size ),
			'fraction'        => 'Whole',
			'layerType'       => '',
			'layerPostId'     => 0,
			'price'           => $flat_price,
			'price_formatted' => $this->format_price( $flat_price ),
			'note'            => '',
		] );

		return [ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => 0 ];
	}

	/**
	 * Highest wins: only the most expensive topping grid price is charged.
	 */
	private function engine_highest_wins( int $product_id, string $size, array $layers ) {
		$add_on    = 0.0;
		$breakdown = [];
		$top_price = 0.0;
		$top_layer = null;

		foreach ( $layers as $layer ) {
			$layer_post_id = (int) ( $layer['layerPostId'] ?? 0 );
			if ( $this->is_non_topping( $layer['layerType'] ) ) {
				[ $price, $err ] = $this->non_topping_price( $product_id, $size, $layer );
				if ( $err ) return $err;
				$add_on     += $price;
				$breakdown[] = $this->breakdown_entry( $layer, $price );
			} else {
				$price = $this->grid->get_layer_price( $layer_post_id, $product_id, $size, $layer['fraction'], (string) $layer['layerType'] ) ?? 0.0;
				if ( $price > $top_price ) {
					$top_price = $price;
					$top_layer = $layer;
				}
				$breakdown[] = $this->breakdown_entry( $layer, 0.0, __( 'Free (highest wins)', 'pizzatier' ) );
			}
		}

		if ( $top_layer !== null ) {
			$add_on += $top_price;
			foreach ( $breakdown as &$entry ) {
				if ( $entry['layerId'] === $top_layer['layerId'] ) {
					$entry['price']           = $top_price;
					$entry['price_formatted'] = $this->format_price( $top_price );
					$entry['note']            = __( 'Highest-priced layer', 'pizzatier' );
					break;
				}
			}
			unset( $entry );
		}

		return [ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => 0 ];
	}

	/**
	 * Tiered by count: topping count determines which grid fraction column to use.
	 * Thresholds e.g. "3,6" → ≤3=Tier1, ≤6=Tier2, >6=Tier3
	 */
	private function engine_tiered_by_count( int $product_id, string $size, array $layers ) {
		$toppings  = array_filter( $layers, fn( $l ) => ! $this->is_non_topping( $l['layerType'] ) );
		$count     = count( $toppings );
		$add_on    = 0.0;
		$breakdown = [];

		// Determine tier
		$raw_thresholds = pizzatier_get_option( 'tiered_topping_thresholds', '3,6' );
		$thresholds     = array_map( 'intval', explode( ',', (string) $raw_thresholds ) );
		sort( $thresholds );

		$tier_num = 1;
		foreach ( $thresholds as $t ) {
			if ( $count > $t ) $tier_num++;
		}
		$fraction = 'Tier' . $tier_num;

		// Non-toppings
		foreach ( $layers as $layer ) {
			if ( $this->is_non_topping( $layer['layerType'] ) ) {
				[ $price, $err ] = $this->non_topping_price( $product_id, $size, $layer );
				if ( $err ) return $err;
				$add_on     += $price;
				$breakdown[] = $this->breakdown_entry( $layer, $price );
			}
		}

		// One tier price for all toppings
		$tier_price = $this->grid->get_price( $product_id, $size, $fraction );
		if ( null === $tier_price && $count > 0 ) {
			return $this->missing_cell_error( $size, $fraction );
		}
		$tier_price = $tier_price ?? 0.0;
		$add_on    += $tier_price;

		foreach ( $toppings as $layer ) {
			/* translators: %s: value inserted into the message. */
			$breakdown[] = $this->breakdown_entry( $layer, 0.0, sprintf( __( 'Included in %s', 'pizzatier' ), $fraction ) );
		}

		array_unshift( $breakdown, [
			'layerId'         => '_tier_charge',
			/* translators: %1$s: value 1, %2$s: value 2. */
			'layerName'       => sprintf( __( '%1$s — %2$d toppings', 'pizzatier' ), $fraction, $count ),
			'fraction'        => $fraction,
			'price'           => $tier_price,
			'price_formatted' => $this->format_price( $tier_price ),
			'note'            => '',
		] );

		return [ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => 0 ];
	}

	/**
	 * Free first N: first N toppings at no charge, rest use grid price.
	 */
	private function engine_free_first_n( int $product_id, string $size, array $layers ) {
		$free_allowed = (int) pizzatier_get_option( 'free_toppings_count', 0 );
		$free_remain  = $free_allowed;
		$add_on       = 0.0;
		$breakdown    = [];
		$has_any_grid = null !== $this->grid->get( $product_id )
			|| null !== $this->grid->get_flat( $product_id )
			|| ! empty( $this->grid->get_all_global_grids() );

		foreach ( $layers as $layer ) {
			$layer_post_id = (int) ( $layer['layerPostId'] ?? 0 );
			if ( $this->is_non_topping( $layer['layerType'] ) ) {
				[ $price, $err ] = $this->non_topping_price( $product_id, $size, $layer );
				if ( $err ) return $err;
				$add_on     += $price;
				$breakdown[] = $this->breakdown_entry( $layer, $price );
			} else {
				if ( $free_remain > 0 ) {
					$price = 0.0;
					$free_remain--;
					$note = __( 'Free topping included', 'pizzatier' );
				} else {
					$price = $this->grid->get_layer_price( $layer_post_id, $product_id, $size, $layer['fraction'], (string) $layer['layerType'] );
					if ( null === $price ) {
						if ( ! $has_any_grid ) { $price = 0.0; } else { return $this->missing_cell_error( $size, $layer['fraction'] ); }
					}
					$price = $this->apply_topping_price_bounds( $price );
					$note  = '';
				}
				$add_on     += $price;
				$breakdown[] = $this->breakdown_entry( $layer, $price, $note );
			}
		}

		return [ 'add_on' => $add_on, 'breakdown' => $breakdown, 'free_count' => $free_allowed - $free_remain ];
	}

	/**
	 * Bundle: price is purely the WC product price — no grid add-ons.
	 */
	private function engine_bundle( array $layers ) {
		$breakdown = [];
		foreach ( $layers as $layer ) {
			$breakdown[] = $this->breakdown_entry( $layer, 0.0, __( 'Included in bundle', 'pizzatier' ) );
		}
		return [ 'add_on' => 0.0, 'breakdown' => $breakdown, 'free_count' => 0 ];
	}

	// -------------------------------------------------------------------------
	// Non-topping pricing
	// -------------------------------------------------------------------------

	/**
	 * Price a non-topping layer (crust, sauce, cheese, drizzle, cut) according
	 * to the non_topping_pricing setting.
	 *
	 * Cheese, sauce, and drizzle support fraction-based pricing (Whole/Half/Quarter)
	 * and are looked up from the fraction price grid.
	 * Crust, cut, and any other non-topping types use a flat per-size price from
	 * the flat grid, falling back to the fraction grid if no flat grid is configured.
	 *
	 * @return array{ 0: float, 1: \WP_Error|null }
	 */
	private function non_topping_price( int $product_id, string $size, array $layer ): array {
		$mode = (string) pizzatier_get_option( 'non_topping_pricing', 'grid' );

		if ( 'free' === $mode ) {
			return [ 0.0, null ];
		}

		if ( 'fixed' === $mode ) {
			$type  = strtolower( $layer['layerType'] );
			$price = (float) pizzatier_get_option( $type . '_fixed_price', 0 );
			return [ $price, null ];
		}

		// 'grid' mode (default): use per-layer grid → product grid fallback.
		$layer_type    = strtolower( $layer['layerType'] );
		$layer_post_id = (int) ( $layer['layerPostId'] ?? 0 );

		if ( $this->grid->is_fraction_type( $layer_type ) ) {
			// Fraction-capable type: look up by size + fraction.
			// Per-layer grid is checked first via get_layer_price(); falls back
			// to the product-level grid automatically.
			$price = $this->grid->get_layer_price( $layer_post_id, $product_id, $size, $layer['fraction'], $layer_type );
			if ( null === $price ) {
				return [ 0.0, null ]; // Gracefully default to 0 if cell missing.
			}
			return [ $price, null ];
		}

		// Flat type (crust, cut, topping as non-topping context): try the flat grid.
		// Per-layer grids for flat types still use the fraction grid format (all
		// 7 types were confirmed to use size × coverage grids), so try
		// get_layer_price() first, then fall back to the flat product grid.
		if ( $layer_post_id > 0 && $this->grid->has_layer_grid( $layer_post_id ) ) {
			$price = $this->grid->get_layer_price( $layer_post_id, $product_id, $size, $layer['fraction'], $layer_type );
			if ( null !== $price ) {
				return [ $price, null ];
			}
		}

		// Also try the global per-layer-type grid (consulted by get_layer_price
		// only when the layer has its own grid). This makes the global grid
		// usable for flat-type layers (crust, cut) even when no per-ingredient
		// grid exists yet.
		$global_price = $this->grid->get_global_layer_price( $layer_type, $size, $layer['fraction'] );
		if ( null !== $global_price ) {
			return [ $global_price, null ];
		}

		// No per-layer or global grid: try the product-level flat grid.
		$flat_price = $this->grid->get_flat_price( $product_id, $layer_type, $size );
		if ( null !== $flat_price ) {
			return [ $flat_price, null ];
		}

		// Final fallback: fraction grid (Whole) for backward-compat with existing grids.
		$price = $this->grid->get_price( $product_id, $size, 'Whole' ) ?? 0.0;
		return [ $price, null ];
	}

	// -------------------------------------------------------------------------
	// Rounding
	// -------------------------------------------------------------------------

	private function apply_rounding( float $total ): float {
		$mode = (string) pizzatier_get_option( 'price_rounding', '' );

		switch ( $mode ) {
			case 'up':
				return ceil( $total * 100 ) / 100;
			case 'nearest5':
				return round( $total / 0.05 ) * 0.05;
			case 'nearest25':
				return round( $total / 0.25 ) * 0.25;
			case 'nearest50':
				return round( $total / 0.50 ) * 0.50;
			case 'nearest1':
				return round( $total );
			default:
				return round( $total, wc_get_price_decimals() );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_non_topping( string $layer_type ): bool {
		return in_array( strtolower( $layer_type ), self::NON_TOPPING_TYPES, true );
	}

	private function breakdown_entry( array $layer, float $price, string $note = '' ): array {
		return [
			'layerId'         => $layer['layerId'],
			'layerName'       => $layer['layerName'] ?? '',
			'fraction'        => $layer['fraction'],
			'portion'         => (string) ( $layer['portion'] ?? '' ),
			'portionLabel'    => (string) ( $layer['portionLabel'] ?? '' ),
			'layerType'       => $layer['layerType'] ?? '',
			'layerPostId'     => (int) ( $layer['layerPostId'] ?? 0 ),
			'price'           => $price,
			'price_formatted' => $this->format_price( $price ),
			'note'            => $note,
		];
	}

	private function missing_cell_error( string $size, string $fraction ): \WP_Error {
		return new \WP_Error( 'pizzatier_commerce_missing_grid_cell',
			/* translators: %1$s: value 1, %2$s: value 2. */
			sprintf( __( 'No price configured for size "%1$s" with coverage "%2$s". Please contact the store.', 'pizzatier' ), esc_html( $size ), esc_html( $fraction ) ),
			[ 'status' => 422 ] );
	}

	/**
	 * When no grid is configured at all, return a graceful zero price instead of
	 * an error so orders with base-price-only products still go through.
	 *
	 * @param int    $product_id
	 * @param string $size
	 * @param string $fraction
	 * @return float|null  null = grid configured but cell missing; 0.0 = no grid at all
	 */
	private function missing_cell_price_or_error( int $product_id, string $size, string $fraction ) {
		// If there is literally no grid saved on this product, return 0.0 so the
		// order stores with the base product price only.
		$has_grid = null !== $this->grid->get( $product_id );
		if ( ! $has_grid ) {
			return 0.0;
		}
		return null; // Caller should return missing_cell_error().
	}

	private function get_enabled_layer_ids( int $product_id ): array {
		$raw = get_post_meta( $product_id, '_pizzatier_enabled_layers', true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return [];
		}

		$slugs = [];
		foreach ( $raw as $entry ) {
			$entry = sanitize_text_field( (string) $entry );
			if ( $entry === '' ) {
				continue;
			}

			// _pizzatier_commerce_enabled_layers stores post IDs (integers as strings).
			// The layerId coming from templates is always the post slug
			// (e.g. 'pepperoni', 'thin-crispy').  Resolve post ID → slug so
			// the whitelist comparison works correctly.
			if ( ctype_digit( $entry ) ) {
				$pid = (int) $entry;

				// post_name (canonical WordPress slug).
				$slug = get_post_field( 'post_name', $pid );
				if ( $slug ) {
					$slugs[] = (string) $slug;
				}

				// Colorbox templates compute slugs via sanitize_title(title)
				// rather than post_name.  Add that form too so both match.
				$title_slug = sanitize_title( (string) get_the_title( $pid ) );
				if ( $title_slug && $title_slug !== $slug ) {
					$slugs[] = $title_slug;
				}
			} else {
				// Already a slug (legacy or manually-entered value).
				$slugs[] = $entry;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	private function format_price( float $price ): string {
		return number_format( $price, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
	}

	/**
	 * Apply min/max topping price bounds to a grid-resolved per-topping price.
	 */
	private function apply_topping_price_bounds( float $price ): float {
		$min = pizzatier_get_option( 'min_topping_price', '' );
		$max = pizzatier_get_option( 'max_topping_price', '' );
		if ( $min !== '' && (float) $min > 0 ) {
			$price = max( $price, (float) $min );
		}
		if ( $max !== '' && (float) $max > 0 ) {
			$price = min( $price, (float) $max );
		}
		return $price;
	}

	/**
	 * Return the size-based add-on multiplier for the given size label.
	 * Reads size_price_multipliers setting (array of "Label=float" strings).
	 * Returns 1.0 if no multiplier is configured for this size.
	 */
	private function get_size_multiplier( string $size ): float {
		$raw = pizzatier_get_option( 'size_price_multipliers', [] );
		foreach ( (array) $raw as $line ) {
			$parts = explode( '=', (string) $line, 2 );
			if ( count( $parts ) === 2 && trim( $parts[0] ) === $size ) {
				$mult = (float) trim( $parts[1] );
				return $mult > 0 ? $mult : 1.0;
			}
		}
		return 1.0;
	}

	/**
	 * Normalise a template fraction string to the nearest configured grid fraction.
	 *
	 * Templates may emit coverage values like 'HalfLeft', 'HalfRight', 'QuarterTopLeft'
	 * that don't exist as grid column labels. This maps them to the closest grid fraction
	 * (e.g. any half-coverage → 'Half', any quarter → 'Quarter', otherwise 'Whole').
	 * Returns null only if $valid_fractions is empty.
	 *
	 * @param string   $fraction        Fraction from the template/client.
	 * @param string[] $valid_fractions  Grid fraction labels for this product.
	 * @return string|null
	 */
	private function normalise_fraction( string $fraction, array $valid_fractions ): ?string {
		if ( empty( $valid_fractions ) ) {
			return null;
		}
		$lower = strtolower( $fraction );
		// Try prefix matching: 'half*' → first grid fraction containing 'half' or 'Half'
		foreach ( [ 'half', 'quarter', 'whole', 'third' ] as $prefix ) {
			if ( strpos( $lower, $prefix ) !== false ) {
				foreach ( $valid_fractions as $vf ) {
					if ( stripos( $vf, $prefix ) !== false ) {
						return $vf;
					}
				}
			}
		}
		// Default to the first fraction in the grid (usually 'Whole').
		return $valid_fractions[0];
	}

}