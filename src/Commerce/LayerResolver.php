<?php
/**
 * Server-side resolution of a submitted pizza configuration.
 *
 * Every price PizzaTier calculates — the REST price check, the WooCommerce
 * add-to-cart, native Pizza Orders — runs the submitted layers through here
 * first. Only the layer SLUG and the chosen coverage come from the browser;
 * everything that affects the price or the kitchen ticket is looked up:
 *
 *   layerPostId  the ingredient post with that slug (a price grid is read
 *                from it), never the client's number;
 *   layerType    from the post type, never the client's claim;
 *   layerName    the post title;
 *   fraction     derived from the specific portion when one is given, so a
 *                "Left Half" can never be charged as a Quarter;
 *   portionLabel the canonical label for that portion.
 *
 * Unknown slugs are dropped (they cannot be made by the kitchen), and so are
 * repeats of the same layer — except added portions of a topping, which the
 * Topping Multiplier validates against its own per-topping rules.
 *
 * Before 2.4.0 the calculator priced whatever post ID and type the browser
 * sent, so a modified request could charge pepperoni at a cheaper item's grid
 * or label a topping as a free "cut".
 *
 * Idempotent: resolving an already-resolved list returns the same list.
 *
 * PHP 7.4 compatible.
 *
 * @package PizzaTier\Commerce
 * @since   2.4.0
 */

namespace PizzaTier\Commerce;

use PizzaTier\Commerce\WooCommerce\OrderMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LayerResolver {

	/** Ingredient post type → layer type used by the pricing engines. */
	const TYPES = [
		'pizzatier_crusts'   => 'crust',
		'pizzatier_sauces'   => 'sauce',
		'pizzatier_cheeses'  => 'cheese',
		'pizzatier_toppings' => 'topping',
		'pizzatier_drizzles' => 'drizzle',
		'pizzatier_cuts'     => 'cut',
	];

	/** Transient holding the slug index between requests. */
	const CACHE_KEY = 'pizzatier_layer_index';

	/** @var array<string,array<string,array{id:int,type:string,title:string}>>|null slug → type → post */
	private static $index = null;

	/**
	 * @param array $layers     Submitted layers (layerId, fraction, portion, layerType, …).
	 * @param int   $product_id Product being priced (for derived multiplier grids).
	 * @return array<int,array<string,mixed>> Resolved layers.
	 */
	public static function resolve( array $layers, int $product_id = 0 ): array {
		$multiplier = class_exists( \PizzaTier\Multiplier\Config::class ) && \PizzaTier\Multiplier\Config::enabled();

		// Topping Multiplier: "pepperoni~2" → a second "pepperoni" right
		// after the first, validated against the topping's maximum.
		if ( $multiplier ) {
			\PizzaTier\Multiplier\Pricing\SyntheticGrid::set_product( $product_id );
			$layers = \PizzaTier\Multiplier\Pricing\RequestRewriter::expand( $layers );
		}

		$index = self::index();
		$out   = [];
		$seen  = [];
		$base  = []; // slug → resolved first portion (extras copy its coverage).

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$slug = sanitize_title( (string) ( $layer['layerId'] ?? '' ) );
			if ( '' === $slug || ! isset( $index[ $slug ] ) ) {
				continue; // Unknown ingredient.
			}

			// Use the type the client named when the slug exists under several
			// types (a "bbq" sauce and a "bbq" drizzle). A named type that has
			// no such ingredient is refused rather than swapped for whichever
			// type does: before 2.4.3 a "bbq" topping request quietly became
			// the "bbq" drizzle, priced and allowlisted as that (2.4.3).
			$claimed = strtolower( (string) ( $layer['layerType'] ?? '' ) );
			$claimed = rtrim( $claimed, 's' );
			if ( 'slicing' === $claimed ) {
				$claimed = 'cut';
			}
			if ( in_array( $claimed, self::TYPES, true ) ) {
				if ( ! isset( $index[ $slug ][ $claimed ] ) ) {
					continue;
				}
				$post = $index[ $slug ][ $claimed ];
			} else {
				$post = reset( $index[ $slug ] );
			}
			$type = $post['type'];
			$key  = $type . ':' . $slug;

			$n = ( $seen[ $key ] ?? 0 ) + 1;
			$seen[ $key ] = $n;

			if ( $n > 1 ) {
				// A repeat: only an added topping portion may repeat.
				if ( 'topping' !== $type || ! $multiplier ) {
					continue;
				}
				$conf = \PizzaTier\Multiplier\Config::topping( $slug );
				if ( null === $conf || $n > (int) $conf['max'] ) {
					continue;
				}
				// Cart & Pricing → "Max of same topping" (was client-side only).
				$same_max = function_exists( 'pizzatier_get_option' ) ? (int) pizzatier_get_option( 'max_same_topping', 0 ) : 0;
				if ( $same_max > 0 && $n > $same_max ) {
					continue;
				}
				$first = $base[ $key ];
				$out[] = [
					'layerId'      => $slug,
					'layerName'    => '', // Named below once the level is known.
					'fraction'     => $first['fraction'],
					'portion'      => $first['portion'],
					'portionLabel' => $first['portionLabel'],
					'layerType'    => 'topping',
					'layerPostId'  => \PizzaTier\Multiplier\Config::price_post_id( $conf, $n ),
					'layerBaseId'  => $first['layerBaseId'],
					'_n'           => $n,
				];
				continue;
			}

			if ( 'topping' === $type ) {
				// Any coverage the customer picked (even a bare 'whole') decides
				// the price column, so a claimed 'Quarter' cannot discount a
				// topping the kitchen will spread over the whole pizza.
				$raw_por  = trim( (string) ( $layer['portion'] ?? '' ) );
				$portion  = OrderMeta::canonical_portion( $raw_por );
				$fraction = self::fraction_of( '' !== $raw_por ? $raw_por : (string) ( $layer['fraction'] ?? '' ) );
				$label    = '' !== $portion ? OrderMeta::portion_label( $portion ) : $fraction;
			} else {
				$portion  = '';
				$fraction = 'Whole';
				$label    = 'Whole';
			}

			$entry = [
				'layerId'      => $slug,
				'layerName'    => $post['title'],
				'fraction'     => $fraction,
				'portion'      => $portion,
				'portionLabel' => $label,
				'layerType'    => $type,
				'layerPostId'  => $post['id'],
				// The ingredient itself, even when layerPostId is a synthetic
				// multiplier grid: allowlists check this.
				'layerBaseId'  => $post['id'],
			];
			$base[ $key ] = $entry;
			$out[]        = $entry;
		}

		// Names for toppings with added portions ("Pepperoni (Double)",
		// "Pepperoni — 2nd portion").
		if ( $multiplier ) {
			$levels = [];
			foreach ( $out as $e ) {
				if ( 'topping' === $e['layerType'] ) {
					$levels[ $e['layerId'] ] = ( $levels[ $e['layerId'] ] ?? 0 ) + 1;
				}
			}
			foreach ( $out as $i => $e ) {
				$level = $levels[ $e['layerId'] ] ?? 1;
				if ( 'topping' !== $e['layerType'] || $level < 2 ) {
					continue;
				}
				$conf = \PizzaTier\Multiplier\Config::topping( $e['layerId'] );
				if ( null === $conf ) {
					continue;
				}
				if ( isset( $e['_n'] ) ) {
					$out[ $i ]['layerName'] = \PizzaTier\Multiplier\Config::extra_name( $conf, (int) $e['_n'], $level );
				} else {
					$out[ $i ]['layerName'] = \PizzaTier\Multiplier\Config::base_name( (string) $conf['title'], $level, (string) $conf['method'] );
				}
			}
		}

		foreach ( $out as $i => $e ) {
			unset( $out[ $i ]['_n'] );
		}
		return array_values( $out );
	}

	/**
	 * Server-side check of Cart & Pricing → Topping Rules (maximum and
	 * minimum toppings per pizza). Until 2.4.3 these were enforced only by the
	 * builder script. A minimum of 0 (the default) allows a plain pizza.
	 *
	 * @param array $layers Resolved layers (layerId, layerType).
	 * @return string|null Customer-facing error, or null when within limits.
	 */
	public static function topping_limit_error( array $layers ): ?string {
		if ( ! function_exists( 'pizzatier_get_option' ) ) {
			return null;
		}
		$max = (int) pizzatier_get_option( 'max_toppings', 0 );
		$min = (int) pizzatier_get_option( 'min_toppings', 0 );
		if ( $max <= 0 && $min <= 0 ) {
			return null;
		}

		$distinct = [];
		$portions = 0;
		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) || 'topping' !== rtrim( strtolower( (string) ( $layer['layerType'] ?? '' ) ), 's' ) ) {
				continue;
			}
			$distinct[ (string) ( $layer['layerId'] ?? '' ) ] = true;
			$portions++;
		}

		// Added portions count toward the maximum when the Topping
		// Multiplier says so, exactly as the builder counts them.
		$counts_portions = class_exists( \PizzaTier\Multiplier\Config::class )
			&& \PizzaTier\Multiplier\Config::enabled()
			&& \PizzaTier\Multiplier\Config::get( 'count_toward_max' );
		$used = $counts_portions ? $portions : count( $distinct );

		if ( $max > 0 && $used > $max ) {
			/* translators: %d: maximum number of toppings. */
			return sprintf( _n( 'A pizza can have at most %d topping.', 'A pizza can have at most %d toppings.', $max, 'pizzatier' ), $max );
		}
		if ( $min > 0 && count( $distinct ) < $min ) {
			/* translators: %d: minimum number of toppings. */
			return sprintf( _n( 'Please choose at least %d topping.', 'Please choose at least %d toppings.', $min, 'pizzatier' ), $min );
		}
		return null;
	}

	/** Generic coverage size for any coverage/portion string. */
	public static function fraction_of( string $raw ): string {
		$s = strtolower( $raw );
		if ( 0 === strpos( $s, 'quarter' ) ) {
			return 'Quarter';
		}
		if ( 0 === strpos( $s, 'half' ) ) {
			return 'Half';
		}
		if ( 0 === strpos( $s, 'third' ) ) {
			return 'Third';
		}
		return 'Whole';
	}

	/**
	 * Slug → type → post for every published ingredient, keyed by both
	 * post_name and sanitize_title( title ) — the templates use the latter.
	 *
	 * @return array<string,array<string,array{id:int,type:string,title:string}>>
	 */
	private static function index(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		// Every public price quote used to load the whole ingredient catalogue.
		// The index is now cached and rebuilt only when an ingredient changes
		// (see register_cache_invalidation()) (2.4.3).
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			self::$index = $cached;
			return self::$index;
		}

		self::$index = [];
		$posts       = get_posts(
			[
				'post_type'        => array_keys( self::TYPES ),
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
			]
		);
		foreach ( $posts as $p ) {
			// Password-protected ingredients are not on the public menu.
			if ( '' !== (string) $p->post_password ) {
				continue;
			}
			$type  = self::TYPES[ $p->post_type ];
			$entry = [
				'id'    => (int) $p->ID,
				'type'  => $type,
				'title' => (string) get_the_title( $p ),
			];
			foreach ( array_unique( [ (string) $p->post_name, sanitize_title( (string) $p->post_title ) ] ) as $slug ) {
				if ( '' !== $slug && ! isset( self::$index[ $slug ][ $type ] ) ) {
					self::$index[ $slug ][ $type ] = $entry;
				}
			}
		}
		set_transient( self::CACHE_KEY, self::$index, 12 * HOUR_IN_SECONDS );
		return self::$index;
	}

	/** Forget the cached index. */
	public static function flush(): void {
		self::$index = null;
		delete_transient( self::CACHE_KEY );
	}

	/** Rebuild the cached index whenever an ingredient is saved, binned or deleted. */
	public static function register_cache_invalidation(): void {
		$maybe_flush = static function ( $post_id ) {
			if ( isset( self::TYPES[ (string) get_post_type( (int) $post_id ) ] ) ) {
				self::flush();
			}
		};
		add_action( 'save_post', $maybe_flush );
		add_action( 'trashed_post', $maybe_flush );
		add_action( 'untrashed_post', $maybe_flush );
		add_action( 'before_delete_post', $maybe_flush );
	}
}
