<?php
/**
 * Settings + per-topping configuration.
 *
 * Two methods are supported for every topping:
 *
 *  thickness — "use the existing topping". Customers pick a thickness
 *              (Regular, Double, Triple …) with a stepper on the topping and
 *              in its coverage dialog. The topping's own image is re-layered,
 *              rotated, for each added portion, priced by the pricing rule.
 *
 *  layers    — "add a new topping layer each time". Customers tap "Add another
 *              layer"; each layer is listed as its own entry with a remove
 *              button. A layer uses the topping post linked for that level
 *              (its own art and price grid) when the admin linked one, and
 *              otherwise a rotated copy of the topping priced by the pricing
 *              rule (2.4.2: before, no linked layers meant Thickness).
 *
 * PHP 7.4 compatible.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Config {

	/** Global settings option. */
	const OPTION = 'pizzatier_multiplier_settings';

	/** Topping post type owned by PizzaTier. */
	const TOPPING_CPT = 'pizzatier_toppings';

	/** Per-topping meta keys. */
	const META_MODE         = '_pizzatier_mult_mode';          // '' | off | thickness | layers
	const META_MAX          = '_pizzatier_mult_max';           // int, 0 = inherit
	const META_PRICING      = '_pizzatier_mult_pricing';       // '' | full | percent | fixed | free
	const META_PRICE_VALUE  = '_pizzatier_mult_price_value';   // float
	const META_LEVEL_LAYERS = '_pizzatier_mult_level_layers';  // int[] keyed by level (2..)
	const META_PORTION_ONLY = '_pizzatier_mult_portion_only';  // '1' = hide from menus

	/**
	 * Default safety ceiling on portions per topping (base + extras) when the
	 * store sets "No limit". It only stops a forged request from making the
	 * server price thousands of layers; raise it with the
	 * `pizzatier_multiplier_portion_ceiling` filter (2.4.1; was a fixed 6).
	 */
	const DEFAULT_CEILING = 99;

	/** Largest value the ceiling filter may return. */
	const MAX_CEILING = 999;

	/** Separator between a topping slug and its portion number in client layer IDs. */
	const SEP = '~';

	/** @var array|null Request cache of resolved toppings keyed by slug. */
	private static $map = null;

	/** @var int[]|null Request cache of portion-only post IDs. */
	private static $portion_only = null;

	// -------------------------------------------------------------------------
	// Global settings
	// -------------------------------------------------------------------------

	public static function defaults(): array {
		return [
			'enabled'             => 1,
			'default_mode'        => 'thickness',
			'default_max'         => 0, // 0 = no limit (up to ceiling()).
			'pricing'             => 'full',
			'percent'             => 100,
			'fixed'               => 0,
			'level_labels'        => 'Regular, Double, Triple, Quadruple, Quintuple, Sextuple',
			'control_label'       => 'Thickness',
			'base_label'          => '{name} ({level})',
			'extra_label'         => '{name} — {ordinal} portion',
			'layers_label'        => 'Layers',
			'add_layer_label'     => 'Add another layer',
			'base_label_layers'   => '{name} ({n} layers)',
			'layer_label'         => '{name} — layer {n}',
			'extra_label_layers'  => '{layer}',
			'visual'              => 'scatter',
			'count_toward_max'    => 1,
			'hide_portion_layers' => 1,
			'order_note'          => 1,
		];
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	/**
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function enabled(): bool {
		return (bool) self::get( 'enabled' );
	}

	public static function pricing_modes(): array {
		return [
			'full'    => __( 'Charge as another full topping (price grid)', 'pizzatier' ),
			'percent' => __( 'Percentage of the topping price', 'pizzatier' ),
			'fixed'   => __( 'Fixed amount per added portion', 'pizzatier' ),
			'free'    => __( 'Free (no charge)', 'pizzatier' ),
		];
	}

	public static function methods(): array {
		return [
			'thickness' => __( 'Thickness — customers choose Regular, Double, Triple …', 'pizzatier' ),
			'layers'    => __( 'Separate layers — customers add another layer each time', 'pizzatier' ),
			'off'       => __( 'Off — one portion only', 'pizzatier' ),
		];
	}

	// -------------------------------------------------------------------------
	// Labels
	// -------------------------------------------------------------------------

	/** @return string[] Level labels, index 0 = level 1. */
	public static function level_labels(): array {
		$raw    = (string) self::get( 'level_labels' );
		$labels = array_values(
            array_filter(
                array_map(
                    function ( $s ) {
                        return trim( $s, " \n\r\t\v\0" );
                    },
                    explode( ',', $raw )
                ),
                'strlen' 
            ) 
        );
		return $labels;
	}

	/**
	 * Highest number of portions (base + extras) any topping can reach.
	 */
	public static function ceiling(): int {
		/**
		 * Filter the absolute maximum portions per topping, used when a
		 * topping or the store default is set to "No limit".
		 *
		 * @since 2.4.1
		 *
		 * @param int $ceiling Default 99.
		 */
		$c = (int) apply_filters( 'pizzatier_multiplier_portion_ceiling', self::DEFAULT_CEILING );
		return max( 2, min( self::MAX_CEILING, $c ) );
	}

	/**
	 * Store default maximum portions: 0 = no limit.
	 */
	public static function default_max(): int {
		$m = (int) self::get( 'default_max' );
		return $m >= 2 ? min( self::ceiling(), $m ) : 0;
	}

	public static function level_label( int $level ): string {
		$labels = self::level_labels();
		if ( isset( $labels[ $level - 1 ] ) ) {
			return $labels[ $level - 1 ];
		}
		/* translators: %d: number of portions, e.g. 4 → "4×". */
		return sprintf( __( '%d×', 'pizzatier' ), $level );
	}

	public static function ordinal( int $n ): string {
		$mod100 = $n % 100;
		if ( $mod100 >= 11 && $mod100 <= 13 ) {
			return $n . 'th';
		}
		switch ( $n % 10 ) {
			case 1:
				return $n . 'st';
			case 2:
				return $n . 'nd';
			case 3:
				return $n . 'rd';
			default:
				return $n . 'th';
		}
	}

	/**
	 * Name for the first (base) portion when more than one is ordered,
	 * e.g. "Pepperoni (Double)" or, with separate layers, "Pepperoni (3 layers)".
	 *
	 * @param string $title  Topping title.
	 * @param int    $level  Portions in all.
	 * @param string $method thickness | layers.
	 */
	public static function base_name( string $title, int $level, string $method = 'thickness' ): string {
		if ( $level < 2 ) {
			return $title;
		}
		$tpl = 'layers' === $method ? (string) self::get( 'base_label_layers' ) : (string) self::get( 'base_label' );
		return self::fill( $tpl, $title, $level, $level, '' );
	}

	/**
	 * Name for an added portion, e.g. "Pepperoni — 2nd portion", or the linked
	 * layer's own title in the separate-layers method.
	 */
	public static function extra_name( array $topping, int $n, int $level ): string {
		$layer_title = '';
		$tpl         = (string) self::get( 'extra_label' );
		if ( 'layers' === $topping['method'] ) {
			$tpl         = (string) self::get( 'layer_label' );
			$pid         = self::level_post_id( $topping, $n );
			$layer_title = $pid ? (string) get_the_title( $pid ) : '';
			$tpl_layers  = (string) self::get( 'extra_label_layers' );
			if ( '' !== $layer_title && '' !== trim( $tpl_layers, " \n\r\t\v\0" ) ) {
				$tpl = $tpl_layers;
			}
		}
		$name = self::fill( $tpl, (string) $topping['title'], $n, $level, $layer_title );
		return '' !== trim( $name, " \n\r\t\v\0" ) ? $name : $topping['title'] . ' #' . $n;
	}

	private static function fill( string $tpl, string $title, int $n, int $level, string $layer ): string {
		return strtr(
			$tpl,
			[
				'{name}'    => $title,
				'{n}'       => (string) $n,
				'{ordinal}' => self::ordinal( $n ),
				'{level}'   => self::level_label( $level ),
				'{layer}'   => '' !== $layer ? $layer : $title,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Per-topping resolution
	// -------------------------------------------------------------------------

	/**
	 * Every topping that allows more than one portion, keyed by every slug form
	 * a template may emit (post_name and sanitize_title(title)).
	 *
	 * @return array<string,array>
	 */
	public static function toppings(): array {
		if ( null !== self::$map ) {
			return self::$map;
		}
		self::$map = [];

		if ( ! post_type_exists( self::TOPPING_CPT ) ) {
			return self::$map;
		}

		$posts = get_posts(
			[
				'post_type'        => self::TOPPING_CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		foreach ( $posts as $post ) {
			$resolved = self::resolve_post( $post );
			if ( null === $resolved ) {
				continue;
			}
			foreach ( self::slug_forms( $post ) as $slug ) {
				self::$map[ $slug ] = $resolved;
			}
		}

		return self::$map;
	}

	/** Forget cached resolution (after saves). */
	public static function flush(): void {
		self::$map          = null;
		self::$portion_only = null;
	}

	/**
	 * @return array|null Resolved config, or null when the topping is off.
	 */
	public static function topping( string $slug ) {
		$map = self::toppings();
		return $map[ $slug ] ?? null;
	}

	/** @return string[] */
	public static function slug_forms( \WP_Post $post ): array {
		$forms = [ (string) $post->post_name, sanitize_title( (string) get_the_title( $post ) ) ];
		return array_values( array_unique( array_filter( $forms, 'strlen' ) ) );
	}

	/**
	 * Resolve one topping post against the global defaults.
	 *
	 * @return array|null
	 */
	public static function resolve_post( \WP_Post $post ) {
		$id   = (int) $post->ID;
		$mode = (string) get_post_meta( $id, self::META_MODE, true );
		if ( '' === $mode ) {
			$mode = (string) self::get( 'default_mode' );
		}
		if ( ! in_array( $mode, [ 'thickness', 'layers' ], true ) ) {
			return null; // off.
		}
		// Layers that only exist to be stacked on another topping never get
		// their own multiplier.
		if ( in_array( $id, self::portion_only_ids(), true ) ) {
			return null;
		}

		$max = (int) get_post_meta( $id, self::META_MAX, true );
		if ( $max <= 0 ) {
			$max = self::default_max();
		}
		if ( $max <= 0 ) {
			$max = self::ceiling(); // No limit.
		}
		$max = max( 1, min( self::ceiling(), $max ) );
		if ( $max < 2 ) {
			return null;
		}

		$levels = [];
		if ( 'layers' === $mode ) {
			// Optional: without linked layers each added layer is a copy of
			// this topping, priced by the pricing rule.
			$levels = self::level_layers( $id );
		}

		$pricing = (string) get_post_meta( $id, self::META_PRICING, true );
		$value   = get_post_meta( $id, self::META_PRICE_VALUE, true );
		if ( '' === $pricing ) {
			$pricing = (string) self::get( 'pricing' );
			$value   = 'percent' === $pricing ? self::get( 'percent' ) : self::get( 'fixed' );
		}
		if ( ! isset( self::pricing_modes()[ $pricing ] ) ) {
			$pricing = 'full';
		}

		return [
			'id'      => $id,
			'slug'    => (string) $post->post_name,
			'title'   => (string) get_the_title( $post ),
			'method'  => $mode,
			'max'     => $max,
			'pricing' => $pricing,
			'value'   => max( 0.0, (float) $value ),
			'levels'  => $levels,
		];
	}

	/**
	 * Linked layer post IDs keyed by level number (2..ceiling()). Only
	 * published topping posts survive.
	 *
	 * @return array<int,int>
	 */
	public static function level_layers( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_LEVEL_LAYERS, true );
		$out = [];
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $level => $pid ) {
			$level = (int) $level;
			$pid   = (int) $pid;
			if ( $level < 2 || $level > self::ceiling() || $pid <= 0 || $pid === $post_id ) {
				continue;
			}
			if ( self::TOPPING_CPT !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$out[ $level ] = $pid;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Linked layer for portion $n. Levels without their own layer repeat the
	 * nearest lower one, so linking only "level 2" makes every added portion
	 * use it.
	 */
	public static function level_post_id( array $topping, int $n ): int {
		$levels = $topping['levels'] ?? [];
		for ( $i = $n; $i >= 2; $i-- ) {
			if ( ! empty( $levels[ $i ] ) ) {
				return (int) $levels[ $i ];
			}
		}
		return 0;
	}

	/**
	 * Post ID the pricing engine should use for added portion $n.
	 *
	 *  - layers method  → the linked layer (its own price grid)
	 *  - thickness/full → the topping itself (charged like another topping)
	 *  - thickness/other→ a synthetic ID whose price grid is derived on the fly
	 */
	public static function price_post_id( array $topping, int $n ): int {
		if ( 'layers' === $topping['method'] ) {
			$pid = self::level_post_id( $topping, $n );
			if ( $pid ) {
				return $pid;
			}
		}
		if ( 'full' === $topping['pricing'] ) {
			return (int) $topping['id'];
		}
		return Pricing\SyntheticGrid::encode( (int) $topping['id'] );
	}

	/**
	 * Post IDs that are only used as extra-portion layers and should be hidden
	 * from the topping menus.
	 *
	 * @return int[]
	 */
	public static function portion_only_ids(): array {
		if ( null !== self::$portion_only ) {
			return self::$portion_only;
		}
		$ids = get_posts(
			[
				'post_type'        => self::TOPPING_CPT,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => self::META_PORTION_ONLY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Small CPT, admin-flagged rows only.
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- See above.
			]
		);
		$ids = array_map( 'intval', $ids );

		if ( self::get( 'hide_portion_layers' ) ) {
			$linked = get_posts(
				[
					'post_type'        => self::TOPPING_CPT,
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_key'         => self::META_LEVEL_LAYERS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- Small CPT.
				]
			);
			foreach ( $linked as $owner ) {
				$raw = get_post_meta( (int) $owner, self::META_LEVEL_LAYERS, true );
				if ( is_array( $raw ) ) {
					foreach ( $raw as $pid ) {
						if ( (int) $pid > 0 && (int) $pid !== (int) $owner ) {
							$ids[] = (int) $pid;
						}
					}
				}
			}
		}

		self::$portion_only = array_values( array_unique( $ids ) );
		return self::$portion_only;
	}

	/**
	 * Layer image URL for a topping post — mirrors the templates' lookup.
	 */
	public static function layer_image( int $post_id ): string {
		$url = '';
		if ( function_exists( 'pzl_get_field' ) ) {
			$url = (string) ( pzl_get_field( 'topping_layer_image', $post_id ) ?: pzl_get_field( 'topping_image', $post_id ) );
		}
		if ( '' === $url ) {
			$url = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		}
		return $url;
	}

	// -------------------------------------------------------------------------
	// Client layer IDs
	// -------------------------------------------------------------------------

	/**
	 * Split a client layer ID like "pepperoni~2" into [ 'pepperoni', 2 ].
	 *
	 * @return array{0:string,1:int}|null
	 */
	public static function parse_extra_id( string $layer_id ) {
		if ( ! preg_match( '/^(.+)' . preg_quote( self::SEP, '/' ) . '(\d{1,3})$/', $layer_id, $m ) ) {
			return null;
		}
		$n = (int) $m[2];
		if ( $n < 2 || $n > self::ceiling() ) {
			return null;
		}
		return [ (string) $m[1], $n ];
	}
}
