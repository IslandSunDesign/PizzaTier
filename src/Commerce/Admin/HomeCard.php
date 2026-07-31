<?php
/**
 * Cart & pricing card on the PizzaTier dashboard.
 *
 * PizzaTier used to ship its own dashboard page. Most of it was product
 * marketing for a separately-sold plugin — a hero panel headed "WooCommerce
 * Pizza Integration — Supercharged" — which stopped being true when the two
 * plugins became one. What was actually useful was the status row: whether
 * WooCommerce is active, how many pizza products and presets exist, and where
 * to go next.
 *
 * That is what this card carries, on PizzaTier's own dashboard, using the
 * existing `pizzatier_admin_home_cards` extension point. The old page is gone
 * and its URL redirects here.
 *
 * @since 2.0.0
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HomeCard {

	public function register(): void {
		add_action( 'pizzatier_admin_home_cards', [ $this, 'render' ] );
	}

	/** Number of published WooCommerce products of type "pizza". */
	private function pizza_product_count(): int {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		$ids = get_posts(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type as a taxonomy term; there is no meta or column alternative. Result sets are small and admin-only.
					[
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => 'pizza',
					],
				],
			]
		);

		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/** Number of published pizza presets. */
	private function preset_count(): int {
		$counts = wp_count_posts( 'pizzatier_presets' );
		return $counts ? (int) ( $counts->publish ?? 0 ) : 0;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wc_active = class_exists( 'WooCommerce' );
		$products  = $this->pizza_product_count();
		$presets   = $this->preset_count();
		?>
		<div class="plh-features-row">
			<div class="plh-card plh-card--feature">
				<div class="plh-card__icon-header">
					<span class="dashicons dashicons-cart"></span>
					<h3><?php esc_html_e( 'Cart &amp; Pricing', 'pizzatier' ); ?></h3>
				</div>
				<div class="plh-card__content">

					<p>
						<strong><?php esc_html_e( 'WooCommerce:', 'pizzatier' ); ?></strong>
						<?php
						if ( $wc_active ) {
							echo esc_html(
								defined( 'WC_VERSION' )
									? sprintf( /* translators: %s: WooCommerce version number. */ __( 'active (v%s)', 'pizzatier' ), WC_VERSION )
									: __( 'active', 'pizzatier' )
							);
						} else {
							esc_html_e( 'not active — per-layer pricing and presets still work; the cart and checkout do not.', 'pizzatier' );
						}
						?>
					</p>

					<?php if ( $wc_active ) : ?>
						<p>
							<strong><?php esc_html_e( 'Pizza products:', 'pizzatier' ); ?></strong>
							<?php
							echo esc_html(
								$products >= 100
									? __( '100+', 'pizzatier' )
									: number_format_i18n( $products )
							);
							?>
						</p>
					<?php endif; ?>

					<p>
						<strong><?php esc_html_e( 'Pizza presets:', 'pizzatier' ); ?></strong>
						<?php echo esc_html( number_format_i18n( $presets ) ); ?>
					</p>

					<p style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . PricingPage::PAGE_SLUG ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Pricing', 'pizzatier' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . BulkPricingPage::PAGE_SLUG ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Bulk Pricing', 'pizzatier' ); ?>
						</a>
						<?php if ( $wc_active ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . NewPizzaWizard::PAGE_SLUG ) ); ?>" class="button button-secondary">
								<?php esc_html_e( '✦ New Pizza', 'pizzatier' ); ?>
							</a>
						<?php endif; ?>
					</p>

				</div>
			</div>
		</div>
		<?php
	}
}
