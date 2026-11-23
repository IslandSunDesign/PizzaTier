<?php
/**
 * One-click repair for per-layer price grids saved as all zeros.
 *
 * Before 2.4.0 the "Layer Pricing" box saved every blank cell as 0.00, so
 * saving a topping (or crust, sauce, …) without filling in its table stored a
 * grid that made that layer free at checkout, while the live price shown in
 * the builder still used the product grid. Because a deliberately free layer
 * looks the same in the database, the grids are not removed automatically:
 * this notice lists them and lets the admin remove them or keep them.
 *
 * PHP 7.4 compatible.
 *
 * @package PizzaTier\Commerce\Admin
 * @since   2.4.0
 */

namespace PizzaTier\Commerce\Admin;

use PizzaTier\Commerce\PriceGrid\Grid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ZeroGridNotice {

	const ACTION     = 'pizzatier_zero_grids';
	const DISMISSED  = 'pizzatier_zero_grids_kept';
	const TRANSIENT  = 'pizzatier_zero_grid_ids';
	const POST_TYPES = [ 'pizzatier_toppings', 'pizzatier_crusts', 'pizzatier_sauces', 'pizzatier_cheeses', 'pizzatier_drizzles', 'pizzatier_cuts', 'pizzatier_sizes' ];

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		// Any change to a layer grid invalidates the cached scan.
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			add_action( $hook, [ $this, 'maybe_flush' ], 10, 3 );
		}
	}

	/**
	 * @param mixed  $meta_ids  Meta ID(s).
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Key.
	 */
	public function maybe_flush( $meta_ids, $object_id, $meta_key ): void {
		if ( Grid::LAYER_META_KEY === $meta_key ) {
			delete_transient( self::TRANSIENT );
		}
	}

	/**
	 * IDs of layers whose own price grid is entirely zero.
	 *
	 * @return int[]
	 */
	public static function find(): array {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$grid = new Grid();
		$ids  = get_posts(
			[
				'post_type'        => self::POST_TYPES,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'meta_key'         => Grid::LAYER_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only scan, cached.
			]
		);
		$zero = [];
		foreach ( $ids as $id ) {
			$g = $grid->get_layer_grid( (int) $id );
			if ( ! $g || empty( $g['cells'] ) ) {
				continue;
			}
			$sum = 0.0;
			foreach ( $g['cells'] as $v ) {
				$sum += abs( (float) $v );
			}
			if ( $sum <= 0.0 ) {
				$zero[] = (int) $id;
			}
		}
		set_transient( self::TRANSIENT, $zero, DAY_IN_SECONDS );
		return $zero;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( false === strpos( $id, 'pizzatier' ) && 'plugins' !== $id && 'dashboard' !== $id ) {
			return;
		}
		$zero = self::find();
		if ( empty( $zero ) ) {
			return;
		}
		$kept = array_map( 'intval', (array) get_option( self::DISMISSED, [] ) );
		$new  = array_values( array_diff( $zero, $kept ) );
		if ( empty( $new ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'PizzaTier: some ingredients have a price table of all zeros.', 'pizzatier' ); ?></strong></p>
			<p><?php esc_html_e( 'Earlier versions saved an untouched “Layer Pricing” table as all zeros, which makes these ingredients free at checkout even though the builder shows a price. Remove the empty tables to use your global or product prices again, or keep them if these ingredients really are free.', 'pizzatier' ); ?></p>
			<ul class="ul-disc">
				<?php foreach ( array_slice( $new, 0, 15 ) as $pid ) : ?>
					<li><a href="<?php echo esc_url( (string) get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a> <span class="description">(<?php echo esc_html( (string) get_post_type( $pid ) ); ?>)</span></li>
				<?php endforeach; ?>
				<?php if ( count( $new ) > 15 ) : ?>
					<?php /* translators: %d: number of further ingredients. */ ?>
					<li><?php echo esc_html( sprintf( __( '…and %d more', 'pizzatier' ), count( $new ) - 15 ) ); ?></li>
				<?php endif; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'do', 'remove', $url ) ); ?>"><?php esc_html_e( 'Remove the all-zero tables', 'pizzatier' ); ?></a>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'do', 'keep', $url ) ); ?>"><?php esc_html_e( 'Keep them — these are meant to be free', 'pizzatier' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'pizzatier' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$do   = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		$zero = self::find();
		if ( 'remove' === $do ) {
			$grid = new Grid();
			foreach ( $zero as $pid ) {
				$grid->delete_layer_grid( (int) $pid );
			}
			delete_transient( self::TRANSIENT );
		} elseif ( 'keep' === $do ) {
			$kept = array_map( 'intval', (array) get_option( self::DISMISSED, [] ) );
			update_option( self::DISMISSED, array_values( array_unique( array_merge( $kept, $zero ) ) ), false );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=pizzatier' ) );
		exit;
	}
}
