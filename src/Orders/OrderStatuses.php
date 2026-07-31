<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registers and describes the custom post statuses used by the
 * pizzatier_order CPT.
 *
 * Statuses are real WordPress post statuses (not meta) so that counting,
 * filtering, list-table views and WP_Query all work natively without extra
 * meta queries.
 *
 * Every status name is kept at or under 20 characters — the `post_status`
 * column in wp_posts is varchar(20) and longer names silently truncate.
 *
 * This class has no dependency on PizzaTier or WooCommerce.
 */
class OrderStatuses {

	/** Status assigned to a freshly submitted order. */
	const DEFAULT_STATUS = 'pzt-new';

	/**
	 * Status definitions.
	 *
	 * name => [ label, colour, is_open, description ]
	 *
	 * `is_open` marks a status as still requiring kitchen/staff attention;
	 * the admin screens use it for the "needs attention" counts.
	 */
	private static function definitions(): array {
		$defs = [
			'pzt-new' => [
				'label'       => __( 'New', 'pizzatier' ),
				'color'       => '#ff6b35',
				'is_open'     => true,
				'description' => __( 'Order received, not yet acknowledged.', 'pizzatier' ),
			],
			'pzt-confirmed' => [
				'label'       => __( 'Confirmed', 'pizzatier' ),
				'color'       => '#2271b1',
				'is_open'     => true,
				'description' => __( 'Order acknowledged by staff.', 'pizzatier' ),
			],
			'pzt-preparing' => [
				'label'       => __( 'Preparing', 'pizzatier' ),
				'color'       => '#b26200',
				'is_open'     => true,
				'description' => __( 'Order is being made in the kitchen.', 'pizzatier' ),
			],
			'pzt-ready' => [
				'label'       => __( 'Ready', 'pizzatier' ),
				'color'       => '#8c5e00',
				'is_open'     => true,
				'description' => __( 'Order is ready for pickup or hand-off.', 'pizzatier' ),
			],
			'pzt-out-delivery' => [
				'label'       => __( 'Out for Delivery', 'pizzatier' ),
				'color'       => '#3858e9',
				'is_open'     => true,
				'description' => __( 'Order has left the store with a driver.', 'pizzatier' ),
			],
			'pzt-completed' => [
				'label'       => __( 'Completed', 'pizzatier' ),
				'color'       => '#1a7f37',
				'is_open'     => false,
				'description' => __( 'Order fulfilled and closed.', 'pizzatier' ),
			],
			'pzt-cancelled' => [
				'label'       => __( 'Cancelled', 'pizzatier' ),
				'color'       => '#8a8f98',
				'is_open'     => false,
				'description' => __( 'Order cancelled before fulfilment.', 'pizzatier' ),
			],
			'pzt-refunded' => [
				'label'       => __( 'Refunded', 'pizzatier' ),
				'color'       => '#8a8f98',
				'is_open'     => false,
				'description' => __( 'Order refunded to the customer.', 'pizzatier' ),
			],
			'pzt-failed' => [
				'label'       => __( 'Failed', 'pizzatier' ),
				'color'       => '#d63638',
				'is_open'     => false,
				'description' => __( 'Order could not be processed.', 'pizzatier' ),
			],
		];

		/**
		 * Filter the pizza order status definitions.
		 *
		 * Add-ons may append statuses here. Names must be <= 20 characters and
		 * should use the pzt- prefix to avoid clashing with core statuses.
		 *
		 * @param array $defs Status definitions keyed by status name.
		 */
		return (array) apply_filters( 'pizzatier_order_statuses', $defs );
	}

	/**
	 * Register every custom status with WordPress.
	 * Hooked to `init` at the same priority as the order CPT.
	 */
	public function register(): void {
		foreach ( self::definitions() as $name => $def ) {
			register_post_status(
				$name,
				[
					'label'                     => $def['label'],
					'public'                    => false,
					'internal'                  => false,
					'private'                   => true,
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					// Built directly rather than via _n_noop() because the
					// singular/plural strings are composed from an already
					// translated label; passing a variable into a gettext call
					// trips the WordPress.org i18n checks.
					'label_count'               => [
						0          => $def['label'] . ' <span class="count">(%s)</span>',
						1          => $def['label'] . ' <span class="count">(%s)</span>',
						'singular' => $def['label'] . ' <span class="count">(%s)</span>',
						'plural'   => $def['label'] . ' <span class="count">(%s)</span>',
						'context'  => null,
						'domain'   => 'pizzatier',
					],
				]
			);
		}

		do_action( 'pizzatier_order_statuses_registered' );
	}

	/**
	 * All status names.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_keys( self::definitions() );
	}

	/**
	 * status name => human label map, for dropdowns.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		$out = [];
		foreach ( self::definitions() as $name => $def ) {
			$out[ $name ] = (string) $def['label'];
		}
		return $out;
	}

	/**
	 * Human label for one status, falling back to the raw name.
	 */
	public static function label( string $status ): string {
		$defs = self::definitions();
		return isset( $defs[ $status ] ) ? (string) $defs[ $status ]['label'] : $status;
	}

	/**
	 * Badge colour for one status.
	 */
	public static function color( string $status ): string {
		$defs = self::definitions();
		return isset( $defs[ $status ] ) ? (string) $defs[ $status ]['color'] : '#8a8f98';
	}

	/**
	 * Short description for one status.
	 */
	public static function description( string $status ): string {
		$defs = self::definitions();
		return isset( $defs[ $status ] ) ? (string) $defs[ $status ]['description'] : '';
	}

	/**
	 * Whether a status still needs staff attention.
	 */
	public static function is_open( string $status ): bool {
		$defs = self::definitions();
		return isset( $defs[ $status ] ) ? (bool) $defs[ $status ]['is_open'] : false;
	}

	/**
	 * Status names that still need staff attention.
	 *
	 * @return string[]
	 */
	public static function open_statuses(): array {
		$out = [];
		foreach ( self::definitions() as $name => $def ) {
			if ( ! empty( $def['is_open'] ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * Whether a status name is one this plugin recognises.
	 */
	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::definitions() );
	}
}
