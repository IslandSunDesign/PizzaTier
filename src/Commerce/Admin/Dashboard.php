<?php
/**
 * PizzaTier Dashboard
 *
 * Cart & pricing overview page.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard {

	/** Top-level menu slug. */
	const MENU_SLUG = 'pizzatier';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Menu registration moved to PizzaTier\\Admin\\AdminMenu in 2.0.0.
	 *
	 * PizzaTier used to own a second top-level menu at position 56, directly
	 * alongside PizzaTier's. After the merge there is one menu, and one class
	 * that owns it, so the sidebar ordering and the group headers stay
	 * predictable. This class now only renders its page.
	 *
	 * Kept as a no-op so any code still calling it does not fatal.
	 *
	 * @deprecated 2.0.0 Menus are registered by PizzaTier\\Admin\\AdminMenu.
	 */
	public function register(): void {
	}

	// -------------------------------------------------------------------------
	// Dashboard render
	// -------------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wc_active  = class_exists( 'WooCommerce' );
		$pzl_active = defined( 'PIZZATIER_VERSION' );

		$has_pizza_products = $wc_active ? (int) ( new \WP_Query( [
			'post_type'   => 'product',
			'post_status' => 'publish',
			'fields'      => 'ids',
			'nopaging'    => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type as a taxonomy term; there is no meta or column alternative. Result sets are small and admin-only.
			'tax_query'   => [ [ 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'pizza' ] ],
		] ) )->found_posts : 0;

		$_builder_counts = class_exists( 'PizzaTier\\Template\\TemplateLoader' )
			? ( new \PizzaTier\Template\TemplateLoader() )->get_available_templates()
			: [];
		$has_builders    = count( $_builder_counts );

		$_preset_counts = $pzl_active ? wp_count_posts( 'pizzatier_presets' ) : null;
		$has_presets    = $_preset_counts ? (int) ( $_preset_counts->publish ?? 0 ) : 0;

		// Setup progress
		$setup_done  = get_option( 'pizzatier_setup_done', [] );
		$setup_total = 7;
		$setup_count = count( array_filter( $setup_done ) );
		if ( $wc_active )  $setup_count = min( $setup_total, $setup_count + 1 );
		if ( $pzl_active ) $setup_count = min( $setup_total, $setup_count + 1 );
		$setup_pct = (int) round( ( $setup_count / $setup_total ) * 100 );

		?>
		<div class="wrap pztc-dash">
			<?php $this->render_styles(); ?>

			<!-- Header -->
			<div class="pztc-dash__header">
				<div class="pztc-dash__header-inner">
					<div class="pztc-dash__brand">
						<span class="pztc-dash__brand-icon">🍕</span>
						<div>
							<h1 class="pztc-dash__brand-title">PizzaTier</h1>
							<p class="pztc-dash__brand-version">
								v<?php echo esc_html( PIZZATIER_VERSION ); ?>
								<?php if ( $pzl_active ) : ?>
									&nbsp;&middot;&nbsp; <?php esc_html_e( 'PizzaTier', 'pizzatier' ); ?> v<?php echo esc_html( PIZZATIER_VERSION ); ?>
								<?php endif; ?>
							</p>
						</div>
					</div>
					<div class="pztc-dash__header-links">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce-setup-guide' ) ); ?>" class="pztc-dash__header-btn">
							<?php esc_html_e( 'Setup Guide', 'pizzatier' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce-help' ) ); ?>" class="pztc-dash__header-btn pztc-dash__header-btn--outline">
							<?php esc_html_e( 'Help & Docs', 'pizzatier' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- Hero intro -->
			<div class="pztc-dash__hero">
				<div class="pztc-dash__hero-text">
					<h2 class="pztc-dash__hero-title"><?php esc_html_e( 'WooCommerce Pizza Integration — Supercharged.', 'pizzatier' ); ?></h2>
					<p class="pztc-dash__hero-desc">
						<?php esc_html_e( 'PizzaTier connects your PizzaTier visual builder directly to WooCommerce. Customers build their pizza, see a live price update, and add it to the cart in one seamless flow — all fully configurable from this dashboard.', 'pizzatier' ); ?>
					</p>
					<div class="pztc-dash__hero-features">
						<span>✓ <?php esc_html_e( 'Live server-side pricing', 'pizzatier' ); ?></span>
						<span>✓ <?php esc_html_e( 'Cart &amp; order meta storage', 'pizzatier' ); ?></span>
						<span>✓ <?php esc_html_e( 'Pizza presets', 'pizzatier' ); ?></span>
						<span>✓ <?php esc_html_e( 'Size selector &amp; price grid', 'pizzatier' ); ?></span>
						<span>✓ <?php esc_html_e( 'Custom product type', 'pizzatier' ); ?></span>
					</div>
				</div>
				<div class="pztc-dash__hero-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-new-pizza' ) ); ?>" class="pztc-dash__header-btn">
						<?php esc_html_e( '✦ New Pizza Wizard', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pizzatier_presets' ) ); ?>" class="pztc-dash__header-btn pztc-dash__header-btn--outline">
						<?php esc_html_e( '+ New Preset', 'pizzatier' ); ?>
					</a>
				</div>
			</div>

			<!-- Status row -->
			<div class="pztc-dash__status-row">

				<div class="pztc-dash__status-card <?php echo $wc_active ? 'pztc-dash__status-card--ok' : 'pztc-dash__status-card--warn'; ?>">
					<span class="dashicons <?php echo $wc_active ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<div>
						<strong><?php esc_html_e( 'WooCommerce', 'pizzatier' ); ?></strong>
						<span><?php echo esc_html( $wc_active
							? ( defined( 'WC_VERSION' ) ? 'v' . WC_VERSION : __( 'Active', 'pizzatier' ) )
							: __( 'Not active', 'pizzatier' ) );
						?></span>
					</div>
				</div>

				<div class="pztc-dash__status-card <?php echo $pzl_active ? 'pztc-dash__status-card--ok' : 'pztc-dash__status-card--warn'; ?>">
					<span class="dashicons <?php echo $pzl_active ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<div>
						<strong><?php esc_html_e( 'PizzaTier', 'pizzatier' ); ?></strong>
						<span><?php echo $pzl_active
							? 'v' . esc_html( PIZZATIER_VERSION )
							: esc_html__( 'Not active', 'pizzatier' );
						?></span>
					</div>
				</div>

				<div class="pztc-dash__status-card">
					<span class="dashicons dashicons-products"></span>
					<div>
						<strong><?php esc_html_e( 'Pizza Products', 'pizzatier' ); ?></strong>
						<span>
							<?php if ( $has_pizza_products > 0 ) : ?>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&product_type=pizza' ) ); ?>">
									<?php echo esc_html( $has_pizza_products ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
									<?php esc_html_e( 'Create first', 'pizzatier' ); ?>
								</a>
							<?php endif; ?>
						</span>
					</div>
				</div>

				<div class="pztc-dash__status-card">
					<span class="dashicons dashicons-layout"></span>
					<div>
						<strong><?php esc_html_e( 'PizzaTier Templates', 'pizzatier' ); ?></strong>
						<span>
							<?php if ( $has_builders > 0 ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-content' ) ); ?>">
									<?php echo esc_html( $has_builders ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>">
									<?php esc_html_e( 'Create first', 'pizzatier' ); ?>
								</a>
							<?php endif; ?>
						</span>
					</div>
				</div>

				<div class="pztc-dash__status-card">
					<span class="dashicons dashicons-food"></span>
					<div>
						<strong><?php esc_html_e( 'Pizza Presets', 'pizzatier' ); ?></strong>
						<span>
							<?php if ( $has_presets > 0 ) : ?>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pizzatier_presets' ) ); ?>">
									<?php echo esc_html( $has_presets ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pizzatier_presets' ) ); ?>">
									<?php esc_html_e( 'Create first', 'pizzatier' ); ?>
								</a>
							<?php endif; ?>
						</span>
					</div>
				</div>

			</div><!-- .pztc-dash__status-row -->

			<!-- Main grid -->
			<div class="pztc-dash__grid">

				<!-- Left: quick nav cards -->
				<div class="pztc-dash__main">

					<h2 class="pztc-dash__section-title"><?php esc_html_e( 'Quick Actions', 'pizzatier' ); ?></h2>

					<div class="pztc-dash__cards">

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-new-pizza' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-plus-alt"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( '✦ New Pizza Wizard', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'Step-by-step: title → preset → layers → pricing → publish.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&product_type=pizza' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-list-view"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'All Pizza Products', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'View and manage existing pizza products.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-cart"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'Orders', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'View WooCommerce orders containing pizza products.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-admin-settings"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'Cart & Pricing', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'Configure size selector, price bar, cart behaviour, and defaults.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-admin-appearance"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'New Builder', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'Create a new PizzaTier builder to assign to a product.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pizzatier_presets' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-food"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'New Pizza Preset', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'Build a pre-configured pizza customers can start from.', 'pizzatier' ); ?></span>
						</a>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce-help' ) ); ?>" class="pztc-dash__card">
							<span class="pztc-dash__card-icon dashicons dashicons-editor-help"></span>
							<span class="pztc-dash__card-title"><?php esc_html_e( 'Help & Docs', 'pizzatier' ); ?></span>
							<span class="pztc-dash__card-desc"><?php esc_html_e( 'Reference for price grids, cart flow, hooks, and FAQ.', 'pizzatier' ); ?></span>
						</a>

					</div><!-- .pztc-dash__cards -->
				</div><!-- .pztc-dash__main -->

				<!-- Right: setup progress + settings summary -->
				<div class="pztc-dash__sidebar">

					<!-- Setup progress widget -->
					<div class="pztc-dash__widget">
						<div class="pztc-dash__widget-header">
							<span class="dashicons dashicons-awards"></span>
							<strong><?php esc_html_e( 'Setup Progress', 'pizzatier' ); ?></strong>
						</div>
						<div class="pztc-dash__progress-bar-wrap">
							<div class="pztc-dash__progress-bar">
								<div class="pztc-dash__progress-fill" style="width:<?php echo esc_attr( $setup_pct ); ?>%"></div>
							</div>
							<span class="pztc-dash__progress-label">
								<?php echo esc_html( $setup_pct ); ?>%
								<?php esc_html_e( 'complete', 'pizzatier' ); ?>
							</span>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce-setup-guide' ) ); ?>" class="button button-primary pztc-dash__widget-cta">
							<?php $setup_pct >= 100
								? esc_html_e( 'Review Checklist', 'pizzatier' )
								: esc_html_e( 'Continue Setup', 'pizzatier' );
							?>
						</a>
					</div>

					<!-- Key settings summary -->
					<div class="pztc-dash__widget">
						<div class="pztc-dash__widget-header">
							<span class="dashicons dashicons-admin-settings"></span>
							<strong><?php esc_html_e( 'Current Settings', 'pizzatier' ); ?></strong>
						</div>
						<ul class="pztc-dash__settings-list">
							<?php
							$setting_rows = [
								[ __( 'Builder position', 'pizzatier' ),  pizzatier_get_option( 'builder_position_default', 'before_cart' ) ],
								[ __( 'Size selector',    'pizzatier' ),  pizzatier_get_option( 'show_size_selector', true )  ? __( 'Visible', 'pizzatier' ) : __( 'Hidden', 'pizzatier' ) ],
								[ __( 'Live price bar',   'pizzatier' ),  pizzatier_get_option( 'show_price_bar', true )      ? __( 'Visible', 'pizzatier' ) : __( 'Hidden', 'pizzatier' ) ],
								[ __( 'Cart button',      'pizzatier' ),  pizzatier_get_option( 'show_cart_btn', false )      ? __( 'Enabled', 'pizzatier' ) : __( 'Disabled', 'pizzatier' ) ],
								[ __( 'Require crust',    'pizzatier' ),  pizzatier_get_option( 'require_crust', false )      ? __( 'Yes', 'pizzatier' ) : __( 'No', 'pizzatier' ) ],
								[ __( 'Require sauce',    'pizzatier' ),  pizzatier_get_option( 'require_sauce', false )      ? __( 'Yes', 'pizzatier' ) : __( 'No', 'pizzatier' ) ],
							];
							foreach ( $setting_rows as $row ) :
							?>
								<li class="pztc-dash__settings-row">
									<span class="pztc-dash__settings-key"><?php echo esc_html( $row[0] ); ?></span>
									<span class="pztc-dash__settings-val"><?php echo esc_html( $row[1] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) ); ?>" class="pztc-dash__widget-link">
							<?php esc_html_e( 'Edit settings →', 'pizzatier' ); ?>
						</a>
					</div>

				</div><!-- .pztc-dash__sidebar -->

			</div><!-- .pztc-dash__grid -->

		</div><!-- .pztc-dash -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_menu_icon(): string {
		// Inline SVG pizza slice as base64 data URI for the menu icon
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 2 L18 17 L2 17 Z" stroke-linejoin="round"/><circle cx="10" cy="10" r="1.2" fill="currentColor" stroke="none"/><circle cx="7" cy="13" r="1" fill="currentColor" stroke="none"/><circle cx="13" cy="13" r="1" fill="currentColor" stroke="none"/></svg>';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Builds a data: URI from inline SVG markup defined above; nothing is decoded or executed.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		/* ── Dashboard layout ──────────────────────────────────────── */
		.pztc-dash { max-width: 1100px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

		/* Header */
		.pztc-dash__header { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); border-radius: 10px; padding: 24px 28px; margin-bottom: 20px; border-bottom: 3px solid #ff6b35; }
		.pztc-dash__header-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
		.pztc-dash__brand { display: flex; align-items: center; gap: 14px; }
		.pztc-dash__brand-icon { font-size: 36px; line-height: 1; }
		.pztc-dash__brand-title { color: #fff; font-size: 22px; font-weight: 700; margin: 0; line-height: 1.2; }
		.pztc-dash__brand-version { color: rgba(255,255,255,.5); font-size: 12px; margin: 2px 0 0; }
		.pztc-dash__header-links { display: flex; gap: 10px; flex-wrap: wrap; }
		.pztc-dash__header-btn { display: inline-flex; align-items: center; padding: 8px 18px; background: #ff6b35; color: #fff !important; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .2s; border: 2px solid transparent; }
		.pztc-dash__header-btn:hover { background: #e05a28; }
		.pztc-dash__header-btn--outline { background: transparent; border-color: rgba(255,255,255,.3); color: rgba(255,255,255,.8) !important; }
		.pztc-dash__header-btn--outline:hover { border-color: #ff6b35; color: #fff !important; background: transparent; }

		/* Status row */
		.pztc-dash__status-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
		.pztc-dash__status-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 14px 16px; }
		.pztc-dash__status-card .dashicons { font-size: 22px !important; color: #aaa; flex-shrink: 0; }
		.pztc-dash__status-card--ok .dashicons { color: #46b450; }
		.pztc-dash__status-card--warn .dashicons { color: #f0b429; }
		.pztc-dash__status-card strong { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #888; }
		.pztc-dash__status-card span:last-child { font-size: 14px; font-weight: 600; color: #1a1a2e; }
		.pztc-dash__status-card a { color: #ff6b35; text-decoration: none; }
		.pztc-dash__status-card a:hover { text-decoration: underline; }

		/* Main grid */
		.pztc-dash__grid { display: grid; grid-template-columns: 1fr 280px; gap: 24px; align-items: start; }
		@media (max-width: 900px) { .pztc-dash__grid { grid-template-columns: 1fr; } }

		.pztc-dash__section-title { font-size: 14px; text-transform: uppercase; letter-spacing: .06em; color: #888; margin: 0 0 14px; font-weight: 600; }

		/* Quick action cards */
		.pztc-dash__cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
		.pztc-dash__card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 18px 16px; text-decoration: none; color: inherit; transition: border-color .2s, box-shadow .2s, transform .15s; }
		.pztc-dash__card:hover { border-color: #ff6b35; box-shadow: 0 4px 16px rgba(232,105,42,.12); transform: translateY(-2px); color: inherit; }
		.pztc-dash__card-icon { font-size: 26px !important; color: #ff6b35; margin-bottom: 4px; }
		.pztc-dash__card-title { font-size: 14px; font-weight: 700; color: #1a1a2e; }
		.pztc-dash__card-desc { font-size: 12px; color: #888; line-height: 1.4; }

		/* Sidebar widgets */
		.pztc-dash__sidebar { display: flex; flex-direction: column; gap: 16px; }
		.pztc-dash__widget { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 18px 20px; }
		.pztc-dash__widget-header { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 14px; font-weight: 700; color: #1a1a2e; }
		.pztc-dash__widget-header .dashicons { color: #ff6b35; font-size: 18px !important; }
		.pztc-dash__widget-cta { display: block; text-align: center; margin-top: 12px; width: 100%; box-sizing: border-box; }

		/* Progress bar */
		.pztc-dash__progress-bar-wrap { }
		.pztc-dash__progress-bar { background: #f0f0f0; border-radius: 99px; height: 8px; overflow: hidden; margin-bottom: 6px; }
		.pztc-dash__progress-fill { height: 100%; background: #ff6b35; border-radius: 99px; transition: width .4s ease; }
		.pztc-dash__progress-label { font-size: 12px; color: #888; }

		/* Settings list */
		.pztc-dash__settings-list { margin: 0 0 10px; padding: 0; list-style: none; }
		.pztc-dash__settings-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; }
		.pztc-dash__settings-row:last-child { border-bottom: none; }
		.pztc-dash__settings-key { color: #666; }
		.pztc-dash__settings-val { font-weight: 600; color: #1a1a2e; }
		.pztc-dash__widget-link { font-size: 12px; color: #ff6b35; text-decoration: none; }
		.pztc-dash__widget-link:hover { text-decoration: underline; }

		/* Hero intro */
		.pztc-dash__hero { background: linear-gradient(135deg, #f8f9fb 0%, #fff7f4 100%); border: 1px solid #f0e8e0; border-radius: 10px; padding: 24px 28px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
		.pztc-dash__hero-text { flex: 1; min-width: 260px; }
		.pztc-dash__hero-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin: 0 0 8px; }
		.pztc-dash__hero-desc { font-size: 13px; color: #555; line-height: 1.6; margin: 0 0 14px; max-width: 560px; }
		.pztc-dash__hero-features { display: flex; flex-wrap: wrap; gap: 8px; }
		.pztc-dash__hero-features span { font-size: 12px; color: #1a7a4a; background: #e8f5ee; border-radius: 20px; padding: 3px 10px; font-weight: 500; }
		.pztc-dash__hero-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
		.pztc-dash__hero-actions .pztc-dash__header-btn--outline { border-color: #ff6b35; color: #ff6b35 !important; }
		.pztc-dash__hero-actions .pztc-dash__header-btn--outline:hover { background: #ff6b35; color: #fff !important; }
		</style>
		<?php
	}
}
