<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Dashboard — main admin home page.
 *
 * Includes:
 *  - Header bar with version + action buttons
 *  - Live layer stats strip (each box links to its CPT in the Content Hub)
 *  - Setup nag for missing/empty CPTs
 *  - Quick-access icon nav (Help surfaced as a featured item)
 *  - Hero intro
 *  - Shortcode reference + Extend / developer cards
 *  - Pro upsell CTA (dismissable per-user, hidden when Pro active)
 */
class AdminHome {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Handle Pro CTA dismissal
		if (
			isset( $_GET['pizzatier_dismiss_pro_cta'] )
			&& check_admin_referer( 'pizzatier_dismiss_pro_cta' )
		) {
			update_user_meta( get_current_user_id(), 'pizzatier_pro_cta_dismissed', true );
		}

		$show_pro_cta = ! class_exists( 'PizzaTierPro' )
		             && ! get_user_meta( get_current_user_id(), 'pizzatier_pro_cta_dismissed', true );

		// ── Live stats ──────────────────────────────────────────────────
		$stats = [
			'toppings' => (int) ( wp_count_posts( 'pizzatier_toppings' )->publish ?? 0 ),
			'crusts'   => (int) ( wp_count_posts( 'pizzatier_crusts'   )->publish ?? 0 ),
			'sauces'   => (int) ( wp_count_posts( 'pizzatier_sauces'   )->publish ?? 0 ),
			'cheeses'  => (int) ( wp_count_posts( 'pizzatier_cheeses'  )->publish ?? 0 ),
			'drizzles' => (int) ( wp_count_posts( 'pizzatier_drizzles' )->publish ?? 0 ),
			'cuts'     => (int) ( wp_count_posts( 'pizzatier_cuts'     )->publish ?? 0 ),
			'sizes'    => (int) ( wp_count_posts( 'pizzatier_sizes'    )->publish ?? 0 ),
			'presets'  => (int) ( wp_count_posts( 'pizzatier_presets'  )->publish ?? 0 ),
		];
		$total = array_sum( array_values( $stats ) );
		$active_template = (string) get_option( 'pizzatier_setting_global_template', 'nightpie' );

		// ── Setup nags: which essential CPTs are still empty ────────────
		$essential = [ 'crusts', 'sauces', 'cheeses', 'toppings' ];
		$missing   = array_filter( $essential, fn( $k ) => $stats[ $k ] === 0 );


		// ── Quick-access icon nav items ──────────────────────────────────
		$quick_nav = [
			[
				'icon'  => 'dashicons-welcome-learn-more',
				'label' => __( 'Setup Guide', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-setup' ),
				'color' => '#2271b1',
			],
			[
				'icon'     => 'dashicons-sos',
				'label'    => __( 'Help', 'pizzatier' ),
				'href'     => admin_url( 'admin.php?page=pizzatier-help' ),
				'color'    => '#d63638',
				'featured' => true,
			],
			[
				'icon'  => 'dashicons-editor-code',
				'label' => __( 'Shortcode Generator', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-shortcodes' ),
				'color' => '#00a32a',
			],
			[
				'icon'  => 'dashicons-admin-appearance',
				'label' => __( 'Template', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-template' ),
				'color' => '#8c5af8',
			],
			[
				'icon'  => 'dashicons-admin-generic',
				'label' => __( 'Customizer', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-settings' ),
				'color' => '#9b51e0',
			],
			[
				'icon'  => 'dashicons-star-filled',
				'label' => __( 'Toppings', 'pizzatier' ),
				'href'  => admin_url( 'edit.php?post_type=pizzatier_toppings' ),
				'color' => '#f0b849',
			],
			[
				'icon'  => 'dashicons-food',
				'label' => __( 'Presets', 'pizzatier' ),
				'href'  => admin_url( 'edit.php?post_type=pizzatier_presets' ),
				'color' => '#e8692a',
			],
			[
				'icon'  => 'dashicons-migrate',
				'label' => __( 'Site Migration', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-migration' ),
				'color' => '#0073aa',
			],
		];


		?>
		<div class="wrap plh-wrap">

			<?php $this->render_styles(); ?>

			<!-- ══ Header ══════════════════════════════════════════════════ -->
			<div class="plh-header">
				<div class="plh-header__brand">
					<span class="dashicons dashicons-pizza plh-header__icon" aria-hidden="true"></span>
					<div>
						<h1 class="plh-header__title">PizzaTier</h1>
						<p class="plh-header__tagline"><?php
							/* translators: %s = version number */
							printf( esc_html__( 'The WordPress pizza builder — v%s', 'pizzatier' ), esc_html( PIZZATIER_VERSION ) );
						?></p>
					</div>
				</div>
				<div class="plh-header__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-template' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Template', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-setup' ) ); ?>" class="button">
						<span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'Setup Guide', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-shortcodes' ) ); ?>" class="button">
						<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Shortcodes', 'pizzatier' ); ?>
					</a>
				</div>
			</div>

			<!-- ══ Pro upsell CTA ════════════════════════════════════════ -->
			<?php if ( $show_pro_cta ) : ?>
			<div class="plh-pro-cta">
				<span class="plh-pro-cta__icon">🍕</span>
				<div class="plh-pro-cta__text">
					<strong><?php esc_html_e( 'Supercharge with PizzaTierPro', 'pizzatier' ); ?></strong> &mdash;
					<?php esc_html_e( 'Add WooCommerce cart integration, order pricing grids, and more.', 'pizzatier' ); ?>
					<a href="https://pizzatier.com/pro" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more →', 'pizzatier' ); ?></a>
				</div>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'pizzatier_dismiss_pro_cta', '1' ), 'pizzatier_dismiss_pro_cta' ) ); ?>"
				   class="plh-pro-cta__dismiss" title="<?php esc_attr_e( 'Dismiss', 'pizzatier' ); ?>">✕</a>
			</div>
			<?php endif; ?>

			<!-- ══ Setup nag ═════════════════════════════════════════════ -->
			<?php if ( ! empty( $missing ) ) : ?>
			<div class="plh-nag">
				<span class="dashicons dashicons-info-outline"></span>
				<div>
					<strong><?php esc_html_e( 'A few things still need content before your builder works:', 'pizzatier' ); ?></strong>
					<ul class="plh-nag__list">
						<?php foreach ( $missing as $k ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pizzatier_' . $k ) ); ?>">
								<?php printf( /* translators: %s = content type name. */ esc_html__( 'Add your first %s →', 'pizzatier' ), esc_html( ucfirst( $k ) ) ); ?>
							</a>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php endif; ?>

			<!-- ══ Stats strip ════════════════════════════════════════════ -->
			<?php
			$hub_disabled = ( get_option( 'pizzatier_setting_disable_content_hub', 'no' ) === 'yes' );
			$hub_url      = admin_url( 'admin.php?page=pizzatier-content' );
			$total_url    = $hub_disabled ? admin_url( 'edit.php?post_type=pizzatier_toppings' ) : $hub_url;
			?>
			<div class="plh-stats-row">
				<a class="plh-stat plh-stat--total" href="<?php echo esc_url( $total_url ); ?>">
					<span class="plh-stat__number"><?php echo esc_html( $total ); ?></span>
					<span class="plh-stat__label"><?php esc_html_e( 'Total Layers', 'pizzatier' ); ?></span>
				</a>
				<?php
				$stat_display = [
					'toppings' => __( 'Toppings', 'pizzatier' ),
					'crusts'   => __( 'Crusts', 'pizzatier' ),
					'sauces'   => __( 'Sauces', 'pizzatier' ),
					'cheeses'  => __( 'Cheeses', 'pizzatier' ),
					'drizzles' => __( 'Drizzles', 'pizzatier' ),
					'cuts'     => __( 'Cuts', 'pizzatier' ),
				];
				foreach ( $stat_display as $k => $label ) :
					$warn     = $stats[ $k ] === 0 && in_array( $k, $essential, true );
					$stat_url = $hub_disabled
						? admin_url( 'edit.php?post_type=pizzatier_' . $k )
						: add_query_arg( 'pl_cpt', $k, $hub_url );
				?>
				<a class="plh-stat<?php echo $warn ? ' plh-stat--warn' : ''; ?>" href="<?php echo esc_url( $stat_url ); ?>">
					<span class="plh-stat__number"><?php echo esc_html( $stats[ $k ] ); ?></span>
					<span class="plh-stat__label"><?php echo esc_html( $label ); ?></span>
					<?php if ( $warn ) : ?>
					<span class="plh-stat__warn-badge"><?php esc_html_e( 'Needs content', 'pizzatier' ); ?></span>
					<?php endif; ?>
				</a>
				<?php endforeach; ?>
				<a class="plh-stat plh-stat--template" href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-template' ) ); ?>">
					<span class="plh-stat__number plh-stat__number--sm"><?php echo esc_html( ucwords( str_replace( '-', ' ', $active_template ) ) ); ?></span>
					<span class="plh-stat__label"><?php esc_html_e( 'Active Template', 'pizzatier' ); ?></span>
				</a>
			</div>

			<!-- ══ Quick-access icon nav ══════════════════════════════════ -->
			<div class="plh-quicknav">
				<?php foreach ( $quick_nav as $item ) :
					$is_featured = ! empty( $item['featured'] );
					$item_class  = 'plh-quicknav__item' . ( $is_featured ? ' plh-quicknav__item--featured' : '' );
				?>
				<a href="<?php echo esc_url( $item['href'] ); ?>" class="<?php echo esc_attr( $item_class ); ?>"<?php echo $is_featured ? ' style="--pzl-qn-accent:' . esc_attr( $item['color'] ) . '"' : ''; ?>>
					<span class="plh-quicknav__icon" style="background:<?php echo esc_attr( $item['color'] ); ?>20;color:<?php echo esc_attr( $item['color'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
					</span>
					<span class="plh-quicknav__label"><?php echo esc_html( $item['label'] ); ?></span>
				</a>
				<?php endforeach; ?>
				<?php do_action( 'pizzatier_admin_home_quicknav' ); ?>
			</div>

			<!-- ══ Hero intro ══════════════════════════════════════════════ -->
			<div class="plh-hero">
				<div class="plh-hero__inner">
					<div class="plh-hero__copy">
						<h2 class="plh-hero__heading"><?php esc_html_e( 'Build beautiful pizza builders — one layer at a time.', 'pizzatier' ); ?></h2>
						<p class="plh-hero__text"><?php esc_html_e( 'PizzaTier turns your WordPress site into an interactive pizza configurator. Add your ingredients as layer images, choose a template, drop in a shortcode, and your customers build their perfect pizza in real time.', 'pizzatier' ); ?></p>
						<div class="plh-hero__steps">
							<div class="plh-hero__step">
								<span class="plh-hero__step-num">1</span>
								<span><strong><?php esc_html_e( 'Add content', 'pizzatier' ); ?></strong> — <?php esc_html_e( 'upload crusts, sauces, cheeses &amp; toppings as layer images.', 'pizzatier' ); ?></span>
							</div>
							<div class="plh-hero__step">
								<span class="plh-hero__step-num">2</span>
								<span><strong><?php esc_html_e( 'Choose a template', 'pizzatier' ); ?></strong> — <?php esc_html_e( 'pick the visual style for your builder UI.', 'pizzatier' ); ?></span>
							</div>
							<div class="plh-hero__step">
								<span class="plh-hero__step-num">3</span>
								<span><strong><?php esc_html_e( 'Embed &amp; go', 'pizzatier' ); ?></strong> — <?php
									/* translators: [pizza_builder] is a shortcode, keep as-is */
									esc_html_e( 'paste [pizza_builder] on any page and you\'re live.', 'pizzatier' );
								?></span>
							</div>
						</div>
						<div class="plh-hero__btns">
							<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-setup') ); ?>" class="button button-primary">
								<span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'Setup Guide', 'pizzatier' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-template') ); ?>" class="button">
								<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Choose Template', 'pizzatier' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-shortcodes') ); ?>" class="button">
								<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Shortcodes', 'pizzatier' ); ?>
							</a>
						</div>
					</div>
					<div class="plh-hero__stats-side">
						<div class="plh-hero__stat-pill">
							<span class="dashicons dashicons-admin-appearance plh-hero__pill-icon"></span>
							<div>
								<span class="plh-hero__pill-label"><?php esc_html_e( 'Active Template', 'pizzatier' ); ?></span>
								<span class="plh-hero__pill-val"><?php echo esc_html( $active_template ? ucwords( str_replace('-',' ',$active_template) ) : __( 'Not set', 'pizzatier' ) ); ?></span>
							</div>
						</div>
						<div class="plh-hero__stat-pill">
							<span class="dashicons dashicons-images-alt2 plh-hero__pill-icon"></span>
							<div>
								<span class="plh-hero__pill-label"><?php esc_html_e( 'Total Layers Published', 'pizzatier' ); ?></span>
								<span class="plh-hero__pill-val"><?php echo esc_html( $total ); ?></span>
							</div>
						</div>
						<div class="plh-hero__stat-pill">
							<span class="dashicons dashicons-star-filled plh-hero__pill-icon"></span>
							<div>
								<span class="plh-hero__pill-label"><?php esc_html_e( 'Toppings', 'pizzatier' ); ?></span>
								<span class="plh-hero__pill-val"><?php echo esc_html( $stats['toppings'] ); ?></span>
							</div>
						</div>
						<a href="<?php echo esc_url( home_url('/') ); ?>" target="_blank" rel="noopener" class="plh-hero__view-site">
							<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'View Site', 'pizzatier' ); ?>
						</a>
					</div>
				</div>
			</div>

			<!-- ══ Bottom feature cards ═════════════════════════════════ -->
			<div class="plh-features-row">

				<!-- Shortcode reference -->
				<div class="plh-card plh-card--feature">
					<div class="plh-card__icon-header">
						<span class="dashicons dashicons-editor-code"></span>
						<h3><?php esc_html_e( 'Shortcode Reference', 'pizzatier' ); ?></h3>
					</div>
					<div class="plh-card__content">
						<p><code>[pizza_builder]</code><br><span class="plh-sc-desc"><?php esc_html_e( 'Interactive builder on any page.', 'pizzatier' ); ?></span></p>
						<p><code>[pizza_builder id="pizza-1" max_toppings="5"]</code><br><span class="plh-sc-desc"><?php esc_html_e( 'Multiple builders, different settings.', 'pizzatier' ); ?></span></p>
						<p><code>[pizza_static crust="thin-crust" sauce="tomato" toppings="pepperoni"]</code><br><span class="plh-sc-desc"><?php esc_html_e( 'Static pizza display anywhere.', 'pizzatier' ); ?></span></p>
						<p><code>[pizza_layer type="topping" slug="pepperoni"]</code><br><span class="plh-sc-desc"><?php esc_html_e( 'Single layer image anywhere.', 'pizzatier' ); ?></span></p>
						<p style="margin-top:12px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-shortcodes' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Open Shortcode Generator', 'pizzatier' ); ?>
							</a>
						</p>
					</div>
				</div>

				<!-- Extend / developer card -->
				<div class="plh-card plh-card--feature">
					<div class="plh-card__icon-header">
						<span class="dashicons dashicons-admin-plugins"></span>
						<h3><?php esc_html_e( 'Extend PizzaTier', 'pizzatier' ); ?></h3>
					</div>
					<div class="plh-card__content">
						<p><?php
							/* translators: /pzttemplates/your-slug/ and /templates/ are directory paths, keep as-is */
							echo wp_kses_post( __( 'Create a <strong>child theme template</strong> by adding a directory at <code>/pzttemplates/your-slug/</code>. Copy a base template from the plugin\'s <code>/templates/</code> folder, then freely edit layout, partials, and CSS.', 'pizzatier' ) );
						?></p>
						<p><?php
							/* translators: pizzatier_before_builder etc. are PHP hooks, keep as-is */
							echo wp_kses_post( __( 'Hook into any part of the builder with the full <strong>action &amp; filter API</strong> — <code>pizzatier_before_builder</code>, <code>pizzatier_layer_html</code>, <code>pizzatier_tab_order</code>, and more.', 'pizzatier' ) );
						?></p>
						<p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-help' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Developer Hooks Reference', 'pizzatier' ); ?>
							</a>
						</p>
					</div>
				</div>

			</div><!-- /.plh-features-row -->

			<?php do_action( 'pizzatier_admin_home_cards' ); ?>

			<!-- ══ Credits ════════════════════════════════════════════════ -->
			<div class="plh-credits">
				<?php
				printf(
					/* translators: 1: version number, 2: author name, 3: company link HTML */
					wp_kses_post( __( 'PizzaTier v%1$s &mdash; crafted by <strong>%2$s</strong> / %3$s', 'pizzatier' ) ),
					esc_html( PIZZATIER_VERSION ),
					'Ryan Bishop',
					'<a href="https://islandsundesign.com" target="_blank" rel="noopener">Island Sun Design</a>'
				);
				?>
			</div>

		</div><!-- /.plh-wrap -->

		<?php
	}

	private function render_styles(): void {
		?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
		<?php
	}

	}
