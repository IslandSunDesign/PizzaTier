<?php
/**
 * Price Grid data model.
 *
 * Responsible for reading, writing, validating, and querying price grid data
 * stored as post meta on a pizza product.
 *
 * Storage format (serialised array in _pizzatier_commerce_price_grid):
 * [
 *   'sizes'     => [ 'S', 'M', 'L', 'XL' ],          // row labels
 *   'fractions' => [ 'Whole', 'Half', 'Quarter' ],    // column labels
 *   'cells'     => [ 'S|Whole' => 8.50, 'S|Half' => 5.00, ... ],
 * ]
 *
 * The cell key is always "{size}|{fraction}" (pipe-delimited) to keep it
 * unambiguous even if labels contain spaces.
 *
 * @package PizzaTier\Commerce\PriceGrid
 */

namespace PizzaTier\Commerce\PriceGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Grid {

	/** @var string Post meta key used to store the fraction grid (cheese/sauce/drizzle). */
	const META_KEY = '_pizzatier_price_grid';

	/** @var string Post meta key for the flat grid (crust/cut/topping and any non-fraction layer types). */
	const META_KEY_FLAT = '_pizzatier_price_grid_flat';

	/**
	 * Post meta key stored on every PizzaTier CPT post (toppings, crusts, sauces,
	 * cheeses, drizzles, cuts, sizes) that carries its own size × coverage price grid.
	 *
	 * Format is identical to META_KEY:
	 * [
	 *   'sizes'     => [ 'Small', 'Medium', 'Large', 'XL' ],
	 *   'fractions' => [ 'Whole', 'Half', 'Quarter' ],
	 *   'cells'     => [ 'Small|Whole' => 1.50, 'Small|Half' => 0.90, … ],
	 * ]
	 */
	const LAYER_META_KEY = '_pizzatier_layer_grid';

	/** @var string Separator used between size and fraction in cell keys. */
	const CELL_SEP = '|';

	/**
	 * WordPress option key used to store the site-wide *global* price grids,
	 * one per layer type. Saved on the new Pricing admin page and consulted
	 * by get_layer_price() as a fallback after the per-ingredient layer grid
	 * but before the product-level grid.
	 *
	 * Storage format:
	 * [
	 *   'toppings' => [ 'sizes' => [...], 'fractions' => [...], 'cells' => [...] ],
	 *   'crusts'   => [ ... ],
	 *   'sauces'   => [ ... ],
	 *   'cheeses'  => [ ... ],
	 *   'drizzles' => [ ... ],
	 *   'cuts'     => [ ... ],
	 *   'sizes'    => [ ... ],
	 * ]
	 */
	const GLOBAL_GRIDS_OPTION = 'pizzatier_global_price_grids';

	/**
	 * Canonical layer-type slugs used as keys in the global grids option.
	 * These correspond to the 7 PizzaTier CPTs (the trailing 's' matches
	 * the CPT slug suffix). PriceCalculator layer-type strings come in
	 * singular ('topping', 'crust', etc.); normalise_layer_type() handles
	 * the singular → plural mapping.
	 */
	const GLOBAL_LAYER_TYPES = [ 'toppings', 'crusts', 'sauces', 'cheeses', 'drizzles', 'cuts', 'sizes' ];

	/**
	 * Layer types that support fraction-based pricing (Whole/Half/Quarter etc).
	 * All other layer types use a single flat price per size.
	 */
	const FRACTION_LAYER_TYPES = [ 'cheese', 'sauce', 'drizzle' ];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Load a product's price grid from post meta.
	 * Returns null if no grid has been saved yet.
	 *
	 * @param int $product_id
	 * @return array{sizes:string[],fractions:string[],cells:array<string,float>}|null
	 */
	public function get( int $product_id ): ?array {
		$raw = get_post_meta( $product_id, self::META_KEY, true );

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return null;
		}

		return $this->normalise( $raw );
	}

	/**
	 * Persist a price grid to post meta.
	 * Validates first; returns true on success or a WP_Error on failure.
	 *
	 * @param int   $product_id
	 * @param array $data  Raw data array (may come from $_POST or CSV import).
	 * @return true|\WP_Error
	 */
	public function save( int $product_id, array $data ) {
		$validated = $this->validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		update_post_meta( $product_id, self::META_KEY, $validated );
		return true;
	}

	/**
	 * Delete the price grid for a product.
	 *
	 * @param int $product_id
	 */
	public function delete( int $product_id ): void {
		delete_post_meta( $product_id, self::META_KEY );
	}

	// -------------------------------------------------------------------------
	// Flat grid (non-fraction layer types: crust, cut, topping, …)
	// -------------------------------------------------------------------------

	/**
	 * Load the flat grid (one price per size per layer type) from post meta.
	 * Returns null if no flat grid has been saved yet.
	 *
	 * Storage format:
	 * [
	 *   'layer_types' => [ 'crust', 'topping', 'cut' ],
	 *   'sizes'       => [ 'S', 'M', 'L', 'XL' ],
	 *   'cells'       => [ 'crust|S' => 1.50, 'crust|M' => 2.00, … ],
	 * ]
	 *
	 * @param int $product_id
	 * @return array{layer_types:string[],sizes:string[],cells:array<string,float>}|null
	 */
	public function get_flat( int $product_id ): ?array {
		$raw = get_post_meta( $product_id, self::META_KEY_FLAT, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return null;
		}
		return $this->normalise_flat( $raw );
	}

	/**
	 * Persist the flat grid to post meta.
	 *
	 * @param int   $product_id
	 * @param array $data  Raw array (may come from $_POST or CSV).
	 * @return true|\WP_Error
	 */
	public function save_flat( int $product_id, array $data ) {
		$validated = $this->validate_flat( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		update_post_meta( $product_id, self::META_KEY_FLAT, $validated );
		return true;
	}

	/**
	 * Delete the flat grid for a product.
	 *
	 * @param int $product_id
	 */
	public function delete_flat( int $product_id ): void {
		delete_post_meta( $product_id, self::META_KEY_FLAT );
	}

	// -------------------------------------------------------------------------
	// Per-layer CPT grid (size × coverage grid on each ingredient post)
	// -------------------------------------------------------------------------

	/**
	 * Load the price grid stored on an individual PizzaTier CPT post
	 * (topping, crust, sauce, cheese, drizzle, cut, or size).
	 *
	 * Returns null if no grid has been saved on that layer yet — callers
	 * should treat null as "use the product-level fallback grid".
	 *
	 * @param int $layer_post_id  Post ID of the CPT item (e.g. a Pepperoni post).
	 * @return array{sizes:string[],fractions:string[],cells:array<string,float>}|null
	 */
	public function get_layer_grid( int $layer_post_id ): ?array {
		if ( $layer_post_id <= 0 ) {
			return null;
		}
		$raw = get_post_meta( $layer_post_id, self::LAYER_META_KEY, true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return null;
		}
		return $this->normalise( $raw );
	}

	/**
	 * Persist a price grid to an individual PizzaTier CPT post.
	 *
	 * Uses the same validation pipeline as the product-level grid so all
	 * constraints (label lengths, numeric prices, non-negative values, etc.)
	 * are enforced identically.
	 *
	 * @param int   $layer_post_id
	 * @param array $data  Raw data array (from $_POST or CSV import).
	 * @return true|\WP_Error
	 */
	public function save_layer_grid( int $layer_post_id, array $data ) {
		if ( $layer_post_id <= 0 ) {
			return new \WP_Error(
				'pizzatier_commerce_layer_grid_invalid_id',
				__( 'Invalid layer post ID.', 'pizzatier' )
			);
		}
		$validated = $this->validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		update_post_meta( $layer_post_id, self::LAYER_META_KEY, $validated );
		return true;
	}

	/**
	 * Delete the price grid stored on an individual CPT post.
	 *
	 * After deletion, get_layer_grid() returns null and pricing falls
	 * through to the product-level grid.
	 *
	 * @param int $layer_post_id
	 */
	public function delete_layer_grid( int $layer_post_id ): void {
		delete_post_meta( $layer_post_id, self::LAYER_META_KEY );
	}

	/**
	 * Whether an individual CPT post has its own price grid saved.
	 *
	 * Useful for admin UI to show "Custom prices set" vs "Using product fallback"
	 * badges without loading the full grid data.
	 *
	 * @param int $layer_post_id
	 * @return bool
	 */
	public function has_layer_grid( int $layer_post_id ): bool {
		if ( $layer_post_id <= 0 ) {
			return false;
		}
		$raw = get_post_meta( $layer_post_id, self::LAYER_META_KEY, true );
		return is_array( $raw ) && ! empty( $raw );
	}

	// -------------------------------------------------------------------------
	// Global per-layer-type grids (site-wide defaults)
	// -------------------------------------------------------------------------

	/**
	 * Return the full set of saved global grids, keyed by canonical
	 * layer-type slug. Unsaved types are simply absent from the array.
	 *
	 * @return array<string,array{sizes:string[],fractions:string[],cells:array<string,float>}>
	 */
	public function get_all_global_grids(): array {
		$raw = get_option( self::GLOBAL_GRIDS_OPTION, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( self::GLOBAL_LAYER_TYPES as $type ) {
			if ( isset( $raw[ $type ] ) && is_array( $raw[ $type ] ) && ! empty( $raw[ $type ] ) ) {
				$out[ $type ] = $this->normalise( $raw[ $type ] );
			}
		}
		return $out;
	}

	/**
	 * Load a single global grid for a layer type.
	 * Returns null if no grid has been saved for that type yet.
	 *
	 * @param string $layer_type Singular or plural layer type (e.g. 'topping' or 'toppings').
	 * @return array{sizes:string[],fractions:string[],cells:array<string,float>}|null
	 */
	public function get_global_layer_grid( string $layer_type ): ?array {
		$slug = $this->normalise_global_layer_type( $layer_type );
		if ( '' === $slug ) {
			return null;
		}
		$all = $this->get_all_global_grids();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Whether any global grid is configured for a given layer type.
	 *
	 * @param string $layer_type
	 * @return bool
	 */
	public function has_global_layer_grid( string $layer_type ): bool {
		return null !== $this->get_global_layer_grid( $layer_type );
	}

	/**
	 * Persist a single global grid for a layer type. The wider option value
	 * is read-modify-written so other types are untouched.
	 *
	 * @param string $layer_type Singular or plural layer type slug.
	 * @param array  $data       Raw grid array (sizes / fractions / cells).
	 * @return true|\WP_Error
	 */
	public function save_global_layer_grid( string $layer_type, array $data ) {
		$slug = $this->normalise_global_layer_type( $layer_type );
		if ( '' === $slug ) {
			return new \WP_Error(
				'pizzatier_commerce_global_grid_invalid_type',
				__( 'Invalid layer type.', 'pizzatier' )
			);
		}
		$validated = $this->validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$all = get_option( self::GLOBAL_GRIDS_OPTION, [] );
		if ( ! is_array( $all ) ) {
			$all = [];
		}
		$all[ $slug ] = $validated;
		update_option( self::GLOBAL_GRIDS_OPTION, $all );
		return true;
	}

	/**
	 * Delete the global grid for a single layer type.
	 *
	 * @param string $layer_type Singular or plural slug.
	 */
	public function delete_global_layer_grid( string $layer_type ): void {
		$slug = $this->normalise_global_layer_type( $layer_type );
		if ( '' === $slug ) {
			return;
		}
		$all = get_option( self::GLOBAL_GRIDS_OPTION, [] );
		if ( ! is_array( $all ) || ! isset( $all[ $slug ] ) ) {
			return;
		}
		unset( $all[ $slug ] );
		update_option( self::GLOBAL_GRIDS_OPTION, $all );
	}

	/**
	 * Direct cell lookup against a global per-layer-type grid.
	 *
	 * @param string $layer_type
	 * @param string $size
	 * @param string $fraction
	 * @return float|null
	 */
	public function get_global_layer_price( string $layer_type, string $size, string $fraction ): ?float {
		$grid = $this->get_global_layer_grid( $layer_type );
		if ( null === $grid ) {
			return null;
		}
		$key = $this->cell_key( $size, $fraction );
		if ( ! isset( $grid['cells'][ $key ] ) ) {
			return null;
		}
		return (float) $grid['cells'][ $key ];
	}

	/**
	 * Normalise a layer type (which may arrive singular from PriceCalculator,
	 * e.g. 'topping', 'cheese') to its canonical plural slug used as the
	 * option key (e.g. 'toppings', 'cheeses'). Already-plural slugs pass
	 * through unchanged. Returns an empty string for unrecognised types.
	 *
	 * @param string $layer_type
	 * @return string
	 */
	public function normalise_global_layer_type( string $layer_type ): string {
		$lc = strtolower( trim( $layer_type ) );
		if ( '' === $lc ) {
			return '';
		}
		// Already-plural / canonical match.
		if ( in_array( $lc, self::GLOBAL_LAYER_TYPES, true ) ) {
			return $lc;
		}
		// Singular forms used in PriceCalculator: 'topping', 'crust', 'sauce',
		// 'cheese', 'drizzle', 'cut', 'size'. Suffix 's' gives the canonical slug.
		$plural = $lc . 's';
		if ( in_array( $plural, self::GLOBAL_LAYER_TYPES, true ) ) {
			return $plural;
		}
		// 'cheese' → 'cheeses', 'drizzle' → 'drizzles' — handled by the +s rule above.
		return '';
	}

	/**
	 * Unified price lookup for a single layer in a pizza order.
	 *
	 * Resolution order:
	 *  1. Layer's own grid  (_pizzatier_commerce_layer_grid on the CPT post)
	 *  2. Global per-layer-type grid (pizzatier_global_price_grids option,
	 *     set on the new Pricing admin page) — used when the layer type is
	 *     known and a grid is configured for that type. This is the
	 *     site-wide default that applies to every pizza product unless the
	 *     individual ingredient post or product overrides it.
	 *  3. Product-level grid (_pizzatier_commerce_price_grid on the WC product)
	 *  4. Legacy free-plugin Price Modifier (_pizzatier_price on the CPT post)
	 *     — flat add-on regardless of size/coverage. Existed in PizzaTier
	 *     1.1.x and earlier; removed in 1.2.0 but
	 *     honoured here so existing data isn't silently dropped after
	 *     the consolidation.
	 *
	 * Returns null only when the cell is genuinely missing from all
	 * sources, allowing callers to distinguish "unconfigured" from
	 * "priced at $0".
	 *
	 * @param int    $layer_post_id  Post ID of the CPT item; 0 = skip layer grid.
	 * @param int    $product_id     WC product post ID (fallback source).
	 * @param string $size           Size label, e.g. 'Large'.
	 * @param string $fraction       Coverage label, e.g. 'Half'.
	 * @param string $layer_type     Optional layer type (e.g. 'topping', 'crust') —
	 *                               used to consult the global per-type grid.
	 * @return float|null
	 */
	public function get_layer_price( int $layer_post_id, int $product_id, string $size, string $fraction, string $layer_type = '' ): ?float {
		// 1. Try the layer's own grid first.
		if ( $layer_post_id > 0 ) {
			$layer_grid = $this->get_layer_grid( $layer_post_id );
			if ( null !== $layer_grid ) {
				$key = $this->cell_key( $size, $fraction );
				if ( isset( $layer_grid['cells'][ $key ] ) ) {
					return (float) $layer_grid['cells'][ $key ];
				}
				// Layer has a grid but this specific cell isn't in it.
				// Still fall through — a partially-configured layer grid
				// should defer to the product grid for missing cells rather
				// than silently returning null / blocking checkout.
			}
		}

		// 2. Try the global per-layer-type grid when the type is known.
		if ( '' !== $layer_type ) {
			$global_price = $this->get_global_layer_price( $layer_type, $size, $fraction );
			if ( null !== $global_price ) {
				return $global_price;
			}
		}

		// 3. Try the product-level grid.
		$product_price = $this->get_price( $product_id, $size, $fraction );
		if ( null !== $product_price ) {
			return $product_price;
		}

		// 4. Legacy free-plugin Price Modifier on the layer CPT post.
		//    Stored as a flat numeric value (no size/coverage breakdown);
		//    we apply it as-is regardless of size/fraction. This bridges
		//    sites that configured pricing in PizzaTier ≤1.1.x before
		//    everything moved into the grid system.
		if ( $layer_post_id > 0 ) {
			$legacy = get_post_meta( $layer_post_id, '_pizzatier_price', true );
			if ( '' !== $legacy && null !== $legacy && is_numeric( $legacy ) ) {
				return (float) $legacy;
			}
		}

		return null;
	}

	/**
	 * Look up the flat price for a specific layer type + size combination.
	 * Returns null if the combination is not configured.
	 *
	 * @param int    $product_id
	 * @param string $layer_type  e.g. 'crust', 'topping'
	 * @param string $size        e.g. 'Large'
	 * @return float|null
	 */
	public function get_flat_price( int $product_id, string $layer_type, string $size ): ?float {
		$grid = $this->get_flat( $product_id );
		if ( null === $grid ) {
			return null;
		}
		$key = $layer_type . self::CELL_SEP . $size;
		if ( ! isset( $grid['cells'][ $key ] ) ) {
			return null;
		}
		return (float) $grid['cells'][ $key ];
	}

	/**
	 * Validate and sanitise a raw flat grid data array.
	 *
	 * @param array $data
	 * @return array|\WP_Error
	 */
	public function validate_flat( array $data ) {
		// ── Sizes ──────────────────────────────────────────────────────────
		if ( empty( $data['sizes'] ) || ! is_array( $data['sizes'] ) ) {
			return new \WP_Error( 'pizzatier_commerce_flat_no_sizes', __( 'Flat price grid must have at least one size.', 'pizzatier' ) );
		}
		$sizes = [];
		foreach ( $data['sizes'] as $s ) {
			$s = sanitize_text_field( (string) $s );
			if ( $s !== '' && strlen( $s ) <= 40 ) {
				$sizes[] = $s;
			}
		}
		$sizes = array_values( array_unique( $sizes ) );
		if ( empty( $sizes ) ) {
			return new \WP_Error( 'pizzatier_commerce_flat_no_sizes', __( 'Flat price grid must have at least one non-empty size.', 'pizzatier' ) );
		}

		// ── Layer types ────────────────────────────────────────────────────
		if ( empty( $data['layer_types'] ) || ! is_array( $data['layer_types'] ) ) {
			return new \WP_Error( 'pizzatier_commerce_flat_no_types', __( 'Flat price grid must have at least one layer type.', 'pizzatier' ) );
		}
		$layer_types = [];
		foreach ( $data['layer_types'] as $t ) {
			$t = sanitize_text_field( (string) $t );
			if ( $t !== '' ) {
				$layer_types[] = $t;
			}
		}
		$layer_types = array_values( array_unique( $layer_types ) );
		if ( empty( $layer_types ) ) {
			return new \WP_Error( 'pizzatier_commerce_flat_no_types', __( 'Flat price grid must have at least one layer type.', 'pizzatier' ) );
		}

		// ── Cells ──────────────────────────────────────────────────────────
		$raw_cells = isset( $data['cells'] ) && is_array( $data['cells'] ) ? $data['cells'] : [];
		$cells     = [];
		foreach ( $layer_types as $layer_type ) {
			foreach ( $sizes as $size ) {
				$key = $layer_type . self::CELL_SEP . $size;
				$raw = $raw_cells[ $key ] ?? '';
				if ( '' === $raw || null === $raw ) {
					$cells[ $key ] = 0.00;
					continue;
				}
				if ( ! is_numeric( $raw ) ) {
					return new \WP_Error(
						'pizzatier_commerce_flat_invalid_cell',
						sprintf(
							/* translators: 1: layer type, 2: size label, 3: invalid value */
							__( 'Invalid price for layer type "%1$s" / size "%2$s": "%3$s". Must be a number.', 'pizzatier' ),
							$layer_type, $size, esc_html( (string) $raw )
						)
					);
				}
				$price = round( (float) $raw, 4 );
				if ( $price < 0 ) {
					return new \WP_Error(
						'pizzatier_commerce_flat_negative_price',
						sprintf(
							/* translators: 1: layer type, 2: size label */
							__( 'Price for layer type "%1$s" / size "%2$s" cannot be negative.', 'pizzatier' ),
							$layer_type, $size
						)
					);
				}
				$cells[ $key ] = $price;
			}
		}

		return [
			'layer_types' => $layer_types,
			'sizes'       => $sizes,
			'cells'       => $cells,
		];
	}

	/**
	 * Whether a given layer type uses fraction-based pricing.
	 *
	 * @param string $layer_type
	 * @return bool
	 */
	public function is_fraction_type( string $layer_type ): bool {
		return in_array( strtolower( $layer_type ), self::FRACTION_LAYER_TYPES, true );
	}

	/**
	 * Default layer types for the flat grid (all non-fraction types).
	 *
	 * @return string[]
	 */
	public function default_flat_layer_types(): array {
		return [ 'crust', 'cut', 'topping' ];
	}

	/**
	 * Normalise a raw flat meta array.
	 *
	 * @param array $raw
	 * @return array
	 */
	private function normalise_flat( array $raw ): array {
		$layer_types = isset( $raw['layer_types'] ) && is_array( $raw['layer_types'] ) ? array_map( 'strval', $raw['layer_types'] ) : [];
		$sizes       = isset( $raw['sizes'] )       && is_array( $raw['sizes'] )       ? array_map( 'strval', $raw['sizes'] )       : [];
		$cells       = isset( $raw['cells'] )       && is_array( $raw['cells'] )       ? array_map( 'floatval', $raw['cells'] )     : [];
		return [
			'layer_types' => array_values( $layer_types ),
			'sizes'       => array_values( $sizes ),
			'cells'       => $cells,
		];
	}

	/**
	 * Validate and sanitise raw grid data.
	 *
	 * Accepts the shape submitted by the admin form or REST endpoint:
	 * [
	 *   'sizes'     => string[],
	 *   'fractions' => string[],
	 *   'cells'     => [ 'S|Whole' => '8.50', ... ],  // values may be strings
	 * ]
	 *
	 * @param array $data
	 * @return array|\WP_Error  Sanitised grid array or error.
	 */
	public function validate( array $data ) {
		// ── Sizes ──────────────────────────────────────────────────────────
		if ( empty( $data['sizes'] ) || ! is_array( $data['sizes'] ) ) {
			return new \WP_Error(
				'pizzatier_commerce_grid_no_sizes',
				__( 'Price grid must have at least one size.', 'pizzatier' )
			);
		}

		$sizes = [];
		foreach ( $data['sizes'] as $s ) {
			$s = sanitize_text_field( (string) $s );
			if ( '' === $s ) {
				continue;
			}
			if ( strlen( $s ) > 40 ) {
				return new \WP_Error(
					'pizzatier_commerce_grid_size_too_long',
					__( 'Size labels must be 40 characters or fewer.', 'pizzatier' )
				);
			}
			$sizes[] = $s;
		}

		$sizes = array_values( array_unique( $sizes ) );

		if ( empty( $sizes ) ) {
			return new \WP_Error(
				'pizzatier_commerce_grid_no_sizes',
				__( 'Price grid must have at least one non-empty size.', 'pizzatier' )
			);
		}

		// ── Fractions ──────────────────────────────────────────────────────
		if ( empty( $data['fractions'] ) || ! is_array( $data['fractions'] ) ) {
			return new \WP_Error(
				'pizzatier_commerce_grid_no_fractions',
				__( 'Price grid must have at least one coverage fraction.', 'pizzatier' )
			);
		}

		$fractions = [];
		foreach ( $data['fractions'] as $f ) {
			$f = sanitize_text_field( (string) $f );
			if ( '' === $f ) {
				continue;
			}
			if ( strlen( $f ) > 40 ) {
				return new \WP_Error(
					'pizzatier_commerce_grid_fraction_too_long',
					__( 'Fraction labels must be 40 characters or fewer.', 'pizzatier' )
				);
			}
			$fractions[] = $f;
		}

		$fractions = array_values( array_unique( $fractions ) );

		if ( empty( $fractions ) ) {
			return new \WP_Error(
				'pizzatier_commerce_grid_no_fractions',
				__( 'Price grid must have at least one non-empty coverage fraction.', 'pizzatier' )
			);
		}

		// ── Cells ──────────────────────────────────────────────────────────
		$raw_cells = isset( $data['cells'] ) && is_array( $data['cells'] ) ? $data['cells'] : [];
		$cells     = [];

		foreach ( $sizes as $size ) {
			foreach ( $fractions as $fraction ) {
				$key = $this->cell_key( $size, $fraction );
				$raw = isset( $raw_cells[ $key ] ) ? $raw_cells[ $key ] : '';

				if ( '' === $raw || null === $raw ) {
					// Empty cell is allowed — treat as 0.00.
					$cells[ $key ] = 0.00;
					continue;
				}

				// Accept numeric strings and floats.
				if ( ! is_numeric( $raw ) ) {
					return new \WP_Error(
						'pizzatier_commerce_grid_invalid_cell',
						sprintf(
							/* translators: 1: size label, 2: fraction label, 3: invalid value */
							__( 'Invalid price for %1$s / %2$s: "%3$s". Must be a number.', 'pizzatier' ),
							$size,
							$fraction,
							esc_html( (string) $raw )
						)
					);
				}

				$price = round( (float) $raw, 4 );

				if ( $price < 0 ) {
					return new \WP_Error(
						'pizzatier_commerce_grid_negative_price',
						sprintf(
							/* translators: 1: size label, 2: fraction label */
							__( 'Price for %1$s / %2$s cannot be negative.', 'pizzatier' ),
							$size,
							$fraction
						)
					);
				}

				$cells[ $key ] = $price;
			}
		}

		return [
			'sizes'     => $sizes,
			'fractions' => $fractions,
			'cells'     => $cells,
		];
	}

	/**
	 * Look up the price for a specific size + fraction combination.
	 *
	 * Returns null if the combination is not in the grid or the product has
	 * no grid at all. Callers should treat null as "price not configured".
	 *
	 * @param int    $product_id
	 * @param string $size      e.g. 'Large'
	 * @param string $fraction  e.g. 'Half'
	 * @return float|null
	 */
	public function get_price( int $product_id, string $size, string $fraction ): ?float {
		$grid = $this->get( $product_id );

		if ( null === $grid ) {
			return null;
		}

		$key = $this->cell_key( $size, $fraction );

		if ( ! isset( $grid['cells'][ $key ] ) ) {
			return null;
		}

		return (float) $grid['cells'][ $key ];
	}

	/**
	 * Calculate the total price for a basket of layers.
	 *
	 * Each $layer entry must have 'fraction' (string matching a grid column).
	 * Returns null if any layer combination is missing from the grid.
	 *
	 * @param int    $product_id
	 * @param string $size
	 * @param array  $layers  [ [ 'fraction' => 'Half' ], … ]
	 * @return float|null  Total price, or null if any cell is missing.
	 */
	public function calculate_total( int $product_id, string $size, array $layers ): ?float {
		$total = 0.0;

		foreach ( $layers as $layer ) {
			$fraction = (string) ( $layer['fraction'] ?? '' );
			$price    = $this->get_price( $product_id, $size, $fraction );

			if ( null === $price ) {
				return null; // Signal to caller that grid is incomplete.
			}

			$total += $price;
		}

		return round( $total, 4 );
	}

	/**
	 * Return the grid's sizes array for a product, or the global default if
	 * the product has no grid yet. Useful for rendering UI before the grid
	 * has been saved.
	 *
	 * @param int $product_id
	 * @return string[]
	 */
	public function get_sizes( int $product_id ): array {
		$grid = $this->get( $product_id );

		if ( $grid ) {
			return $grid['sizes'];
		}

		return $this->default_sizes();
	}

	/**
	 * Return the grid's fractions array for a product, or global defaults.
	 *
	 * @param int $product_id
	 * @return string[]
	 */
	public function get_fractions( int $product_id ): array {
		$grid = $this->get( $product_id );

		if ( $grid ) {
			return $grid['fractions'];
		}

		return $this->default_fractions();
	}

	// -------------------------------------------------------------------------
	// Defaults (pulled from PizzaTier settings, with hard-coded fallbacks)
	// -------------------------------------------------------------------------

	/**
	 * Global default size labels.
	 * Phase 5 (Settings page) will allow these to be customised.
	 *
	 * @return string[]
	 */
	public function default_sizes(): array {
		$setting = pizzatier_get_option( 'default_sizes', null );

		if ( is_array( $setting ) && ! empty( $setting ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $setting ) ) );
		}

		return [ 'Small', 'Medium', 'Large', 'XL' ];
	}

	/**
	 * Global default fraction labels.
	 * Phase 5 (Settings page) will allow these to be customised.
	 *
	 * @return string[]
	 */
	public function default_fractions(): array {
		$setting = pizzatier_get_option( 'default_fractions', null );

		if ( is_array( $setting ) && ! empty( $setting ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $setting ) ) );
		}

		return [ 'Whole', 'Half', 'Quarter' ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a normalised cell key from size and fraction labels.
	 *
	 * @param string $size
	 * @param string $fraction
	 * @return string  e.g. 'Large|Half'
	 */
	public function cell_key( string $size, string $fraction ): string {
		return $size . self::CELL_SEP . $fraction;
	}

	/**
	 * Normalise a raw meta array: ensure correct types, strip unknown keys.
	 *
	 * @param array $raw
	 * @return array
	 */
	private function normalise( array $raw ): array {
		$sizes     = isset( $raw['sizes'] )     && is_array( $raw['sizes'] )     ? array_map( 'strval', $raw['sizes'] )     : [];
		$fractions = isset( $raw['fractions'] ) && is_array( $raw['fractions'] ) ? array_map( 'strval', $raw['fractions'] ) : [];
		$cells     = isset( $raw['cells'] )     && is_array( $raw['cells'] )     ? $raw['cells']                            : [];

		// Cast all cell values to float.
		$cells = array_map( 'floatval', $cells );

		return [
			'sizes'     => array_values( $sizes ),
			'fractions' => array_values( $fractions ),
			'cells'     => $cells,
		];
	}
}
