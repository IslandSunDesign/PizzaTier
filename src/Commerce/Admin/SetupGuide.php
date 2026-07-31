<?php
/**
 * PizzaTier Setup Guide
 *
 * A step-by-step checklist that walks new users through configuring
 * PizzaTier from scratch. Auto-detects completion where possible.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupGuide {

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Apply a tick / untick submitted from the checklist.
	 *
	 * Shared by the standalone screen and by the embedded checklist on
	 * PizzaTier's own Setup Guide, so a tick works from either.
	 */
	private function handle_tick(): void {
		if (
			isset( $_POST['pizzatier_commerce_setup_done'], $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_commerce_setup_checklist' )
		) {
			$done = get_option( 'pizzatier_setup_done', [] );
			$key  = sanitize_key( $_POST['pizzatier_commerce_setup_done'] );
			if ( isset( $_POST['checked'] ) && '1' === $_POST['checked'] ) {
				$done[ $key ] = true;
			} else {
				unset( $done[ $key ] );
			}
			update_option( 'pizzatier_setup_done', $done );
		}
	}

	/**
	 * Progress figures for the checklist.
	 *
	 * @return array{0:array,1:array,2:int,3:int,4:int} steps, done, completed, total, percent
	 */
	private function progress(): array {
		$done  = get_option( 'pizzatier_setup_done', [] );
		$steps = $this->get_steps();

		$total     = count( $steps );
		$completed = 0;
		foreach ( $steps as $step ) {
			if ( $step['auto_done'] || ! empty( $done[ $step['key'] ] ) ) {
				$completed++;
			}
		}
		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;

		return [ $steps, $done, $completed, $total, $percent ];
	}

	/**
	 * Render the checklist inside another screen.
	 *
	 * Since 2.0.0 these steps appear as a section of PizzaTier's own Setup
	 * Guide, so there is one setup checklist rather than two. The step markup
	 * is reused verbatim rather than transcribed, so the two cannot drift.
	 *
	 * @since 2.0.0
	 */
	public function render_embedded_checklist(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_tick();
		list( $steps, $done, $completed, $total, $percent ) = $this->progress();

		$this->render_styles();
		?>
		<div class="pztc-setup pztc-setup--embedded">
			<?php $this->render_progress( $completed, $total, $percent ); ?>
			<?php $this->render_steps( $steps, $done ); ?>
		</div>
		<?php
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_tick();
		list( $steps, $done, $completed, $total, $percent ) = $this->progress();

		?>
		<div class="wrap pztc-setup">
			<?php $this->render_styles(); ?>

			<div class="pztc-setup__header">
				<div class="pztc-setup__header-inner">
					<div class="pztc-setup__header-brand">
						<span class="dashicons dashicons-awards pztc-setup__header-icon" aria-hidden="true"></span>
						<div>
							<h1 class="pztc-setup__title"><?php esc_html_e( 'Cart &amp; Pricing Setup Guide', 'pizzatier' ); ?></h1>
							<p class="pztc-setup__subtitle"><?php esc_html_e( 'Follow these steps to get your first WooCommerce pizza product live.', 'pizzatier' ); ?></p>
						</div>
					</div>
					<div class="pztc-setup__header-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-new-pizza' ) ); ?>" class="pztc-setup__hbtn">
							<?php esc_html_e( '✦ New Pizza', 'pizzatier' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) ); ?>" class="pztc-setup__hbtn pztc-setup__hbtn--outline">
							<?php esc_html_e( 'Settings', 'pizzatier' ); ?>
						</a>
					</div>
				</div>

				<?php $this->render_progress( $completed, $total, $percent ); ?>
			</div>

			<?php $this->render_steps( $steps, $done ); ?>

		</div><!-- .pztc-setup -->
		<?php
	}

	/** Progress bar markup. Shared by the standalone screen and the embed. */
	private function render_progress( int $completed, int $total, int $percent ): void {
		?>
				<div class="pztc-setup__progress-wrap" role="progressbar" aria-valuenow="<?php echo esc_attr( $percent ); ?>" aria-valuemin="0" aria-valuemax="100">
					<div class="pztc-setup__progress-bar">
						<div class="pztc-setup__progress-fill" style="width:<?php echo esc_attr( $percent ); ?>%"></div>
					</div>
					<span class="pztc-setup__progress-label">
						<?php
						printf(
							/* translators: 1: completed steps, 2: total steps, 3: percentage */
							esc_html__( '%1$d of %2$d steps complete (%3$d%%)', 'pizzatier' ),
							(int) $completed,
							(int) $total,
							(int) $percent
						);
						?>
					</span>
				</div>
		<?php
	}

	/** Step list markup, including the all-done banner. Shared by both callers. */
	private function render_steps( array $steps, array $done ): void {
		$percent = count( $steps ) > 0
			? (int) round( ( count( array_filter( $steps, function ( $s ) use ( $done ) {
				return $s['auto_done'] || ! empty( $done[ $s['key'] ] );
			} ) ) / count( $steps ) ) * 100 )
			: 0;
		?>
			<div class="pztc-setup__steps">
				<?php foreach ( $steps as $i => $step ) :
					$is_done   = $step['auto_done'] || ! empty( $done[ $step['key'] ] );
					$step_num  = $i + 1;
					?>
					<div class="pztc-setup__step <?php echo $is_done ? 'pztc-setup__step--done' : ''; ?>">

						<div class="pztc-setup__step-indicator">
							<?php if ( $is_done ) : ?>
								<span class="pztc-setup__step-check dashicons dashicons-yes-alt"></span>
							<?php else : ?>
								<span class="pztc-setup__step-num"><?php echo esc_html( $step_num ); ?></span>
							<?php endif; ?>
						</div>

						<div class="pztc-setup__step-body">
							<h3 class="pztc-setup__step-title"><?php echo esc_html( $step['label'] ); ?></h3>
							<p class="pztc-setup__step-desc"><?php echo wp_kses_post( $step['desc'] ); ?></p>

							<?php if ( ! empty( $step['links'] ) ) : ?>
								<div class="pztc-setup__step-links">
									<?php foreach ( $step['links'] as $link ) : ?>
										<a href="<?php echo esc_url( $link['url'] ); ?>" class="button button-secondary pztc-setup__step-link">
											<?php echo esc_html( $link['label'] ); ?>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( ! $step['auto_done'] ) : ?>
								<form method="post" class="pztc-setup__tick-form">
									<?php wp_nonce_field( 'pizzatier_commerce_setup_checklist' ); ?>
									<input type="hidden" name="pizzatier_commerce_setup_done" value="<?php echo esc_attr( $step['key'] ); ?>">
									<?php if ( $is_done ) : ?>
										<input type="hidden" name="checked" value="0">
										<button type="submit" class="button pztc-setup__tick-btn pztc-setup__tick-btn--undo">
											<span class="dashicons dashicons-undo"></span>
											<?php esc_html_e( 'Mark incomplete', 'pizzatier' ); ?>
										</button>
									<?php else : ?>
										<input type="hidden" name="checked" value="1">
										<button type="submit" class="button button-primary pztc-setup__tick-btn">
											<span class="dashicons dashicons-yes"></span>
											<?php esc_html_e( 'Mark complete', 'pizzatier' ); ?>
										</button>
									<?php endif; ?>
								</form>
							<?php endif; ?>
						</div>

					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $percent >= 100 ) : ?>
				<div class="pztc-setup__complete-banner">
					<span class="dashicons dashicons-smiley"></span>
					<strong><?php esc_html_e( 'All steps complete!', 'pizzatier' ); ?></strong>
					<?php esc_html_e( 'Your pizza store is ready. Head to your storefront to test the builder.', 'pizzatier' ); ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="button button-primary" style="margin-left:12px;">
						<?php esc_html_e( 'View Storefront', 'pizzatier' ); ?>
					</a>
				</div>
			<?php endif; ?>

		<?php
	}

	// -------------------------------------------------------------------------
	// Steps definition
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array{key:string, label:string, desc:string, auto_done:bool, links:array}>
	 */
	private function get_steps(): array {
		// Auto-detect state
		$wc_active         = class_exists( 'WooCommerce' );
		$pzl_active        = defined( 'PIZZATIER_VERSION' );
		$has_builder       = $pzl_active && shortcode_exists( 'pizza_builder' );
		$has_pizza_product = $wc_active  && get_posts( [
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type as a taxonomy term; there is no meta or column alternative. Result sets are small and admin-only.
			'tax_query'   => [ [ 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'pizza' ] ],
		] );
		$settings_saved    = '' !== pizzatier_get_option( 'builder_position_default', '' );

		return [
			[
				'key'       => 'wc_active',
				'label'     => __( 'WooCommerce installed & active', 'pizzatier' ),
				'desc'      => $wc_active
					? __( 'WooCommerce is active — great start!', 'pizzatier' )
					: __( 'These steps need WooCommerce, which handles the cart, checkout and payment. Install it from Plugins → Add New. If you would rather take orders without a cart or payment step, use PizzaTier\'s own ordering system instead and skip this section.', 'pizzatier' ),
				'auto_done' => $wc_active,
				'links'     => $wc_active ? [] : [
					[ 'url' => admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ), 'label' => __( 'Install WooCommerce', 'pizzatier' ) ],
				],
			],
			[
				'key'       => 'pzl_active',
				'label'     => __( 'PizzaTier builder ready', 'pizzatier' ),
				'desc'      => $pzl_active
					? sprintf(
						/* translators: %s: PizzaTier version */
						__( 'PizzaTier %s is active.', 'pizzatier' ),
						PIZZATIER_VERSION
					)
					: __( 'PizzaTier requires the PizzaTier base plugin to be active.', 'pizzatier' ),
				'auto_done' => $pzl_active,
				'links'     => [],
			],
			[
				'key'       => 'create_builder',
				'label'     => __( 'Create a PizzaTier builder', 'pizzatier' ),
				'desc'      => __( 'Go to PizzaTier → Add New and build your pizza with layers, toppings, and a template. Publish when ready.', 'pizzatier' ),
				'auto_done' => (bool) $has_builder,
				'links'     => [
					[ 'url' => admin_url( 'admin.php?page=pizzatier' ), 'label' => __( 'Open PizzaTier', 'pizzatier' ) ],
					[ 'url' => admin_url( 'admin.php?page=pizzatier-content' ), 'label' => __( 'Manage Layers', 'pizzatier' ) ],
				],
			],
			[
				'key'       => 'create_product',
				'label'     => __( 'Create a WooCommerce Pizza product', 'pizzatier' ),
				'desc'      => __( 'In WooCommerce → Products → Add New, set the product type to "Pizza". A Pizza Configurator tab will appear in the product data panel.', 'pizzatier' ),
				'auto_done' => (bool) $has_pizza_product,
				'links'     => [
					[ 'url' => admin_url( 'post-new.php?post_type=product' ), 'label' => __( 'Add Pizza Product', 'pizzatier' ) ],
					[ 'url' => admin_url( 'edit.php?post_type=product' ),      'label' => __( 'View Products', 'pizzatier' ) ],
				],
			],
			[
				'key'       => 'configure_product',
				'label'     => __( 'Assign a builder & configure the price grid', 'pizzatier' ),
				'desc'      => __( 'Open your Pizza product, go to the Pizza Configurator tab, select the PizzaTier builder you created, then fill in the Size/Coverage price grid. Save the product.', 'pizzatier' ),
				'auto_done' => false,
				'links'     => $has_pizza_product ? [
					[ 'url' => admin_url( 'edit.php?post_type=product' ), 'label' => __( 'Open Products', 'pizzatier' ) ],
				] : [],
			],
			[
				'key'       => 'configure_settings',
				'label'     => __( 'Review cart & pricing settings', 'pizzatier' ),
				'desc'      => __( 'Visit PizzaTier → Cart & Pricing to configure the size selector, live price bar, cart button, and other display options.', 'pizzatier' ),
				'auto_done' => $settings_saved,
				'links'     => [
					[ 'url' => admin_url( 'admin.php?page=pizzatier-commerce' ), 'label' => __( 'Open Cart & Pricing', 'pizzatier' ) ],
				],
			],
			[
				'key'       => 'test_frontend',
				'label'     => __( 'Test the front-end order flow', 'pizzatier' ),
				'desc'      => __( 'Visit your pizza product page, build a pizza, select a size, and add it to the cart. Confirm the configured price appears at checkout. Mark complete once verified.', 'pizzatier' ),
				'auto_done' => false,
				'links'     => [
					[ 'url' => home_url( '/' ), 'label' => __( 'View Storefront', 'pizzatier' ) ],
				],
			],
		];
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		.pztc-setup { max-width: 820px; }
		.pztc-setup__header-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
		.pztc-setup__header-brand { display: flex; align-items: center; gap: 16px; }
		.pztc-setup__header-icon { font-size: 38px !important; width: 38px !important; height: 38px !important; color: #ff6b35; flex-shrink: 0; }
		.pztc-setup__header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
		.pztc-setup__hbtn { display: inline-flex; align-items: center; padding: 8px 18px; background: #ff6b35; color: #fff !important; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .2s; border: 2px solid transparent; }
		.pztc-setup__hbtn:hover { background: #e05a28; }
		.pztc-setup__hbtn--outline { background: transparent; border-color: rgba(255,255,255,.3); color: rgba(255,255,255,.8) !important; }
		.pztc-setup__hbtn--outline:hover { border-color: #ff6b35; color: #fff !important; background: transparent; }
		.pztc-setup__header { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #fff; border-radius: 10px; padding: 22px 28px; margin-bottom: 24px; border-bottom: 3px solid #ff6b35; }
		.pztc-setup__title { color: #fff; font-size: 22px; margin: 0 0 4px; display: flex; align-items: center; gap: 10px; }
		.pztc-setup__title .dashicons { color: #ff6b35; font-size: 26px !important; }
		.pztc-setup__subtitle { color: #aaa; margin: 0 0 20px; }
		.pztc-setup__progress-wrap { }
		.pztc-setup__progress-bar { background: rgba(255,255,255,.15); border-radius: 99px; height: 10px; overflow: hidden; margin-bottom: 8px; }
		.pztc-setup__progress-fill { background: #ff6b35; height: 100%; border-radius: 99px; transition: width .4s ease; }
		.pztc-setup__progress-label { color: #ccc; font-size: 13px; }
		.pztc-setup__steps { display: flex; flex-direction: column; gap: 12px; }
		.pztc-setup__step { display: flex; gap: 16px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px 24px; transition: border-color .2s; }
		.pztc-setup__step--done { border-color: #46b450; background: #f6fff6; }
		.pztc-setup__step-indicator { flex-shrink: 0; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #f0f0f0; font-weight: 700; font-size: 14px; color: #555; }
		.pztc-setup__step--done .pztc-setup__step-indicator { background: #46b450; }
		.pztc-setup__step-check { color: #fff; font-size: 22px !important; }
		.pztc-setup__step-body { flex: 1; }
		.pztc-setup__step-title { margin: 0 0 6px; font-size: 15px; }
		.pztc-setup__step--done .pztc-setup__step-title { color: #46b450; }
		.pztc-setup__step-desc { color: #666; margin: 0 0 12px; font-size: 13px; }
		.pztc-setup__step-links { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
		.pztc-setup__tick-form { margin-top: 4px; }
		.pztc-setup__tick-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; }
		.pztc-setup__tick-btn--undo { color: #999; border-color: #ccc; }
		.pztc-setup__complete-banner { margin-top: 24px; background: #ff6b35; color: #fff; border-radius: 8px; padding: 20px 24px; display: flex; align-items: center; gap: 12px; font-size: 15px; }
		.pztc-setup__complete-banner .dashicons { font-size: 28px; }
		</style>
		<?php
	}
}
