<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Setup Guide — step-by-step automated checklist.
 */
class SetupGuide {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Handle checklist item tick via POST
		if ( isset( $_POST['pizzatier_setup_done'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_setup_checklist' ) ) {
			$done = get_option( 'pizzatier_setup_done', [] );
			$key  = sanitize_key( $_POST['pizzatier_setup_done'] );
			if ( isset( $_POST['checked'] ) && $_POST['checked'] === '1' ) {
				$done[ $key ] = true;
			} else {
				unset( $done[ $key ] );
			}
			update_option( 'pizzatier_setup_done', $done );
		}

		// Handle quickstart CTA dismissal
		if (
			isset( $_GET['pizzatier_dismiss_quickstart_cta'] )
			&& check_admin_referer( 'pizzatier_dismiss_quickstart_cta' )
		) {
			update_user_meta( get_current_user_id(), 'pizzatier_quickstart_cta_dismissed', true );
		}
		$show_quickstart_cta = ! get_user_meta( get_current_user_id(), 'pizzatier_quickstart_cta_dismissed', true );

		$done = get_option( 'pizzatier_setup_done', [] );

		// ── Live stats for auto-detection ───────────────────────────────
		$stats = [
			'crusts'   => (int) ( wp_count_posts( 'pizzatier_crusts'   )->publish ?? 0 ),
			'sauces'   => (int) ( wp_count_posts( 'pizzatier_sauces'   )->publish ?? 0 ),
			'cheeses'  => (int) ( wp_count_posts( 'pizzatier_cheeses'  )->publish ?? 0 ),
			'toppings' => (int) ( wp_count_posts( 'pizzatier_toppings' )->publish ?? 0 ),
			'drizzles' => (int) ( wp_count_posts( 'pizzatier_drizzles' )->publish ?? 0 ),
			'cuts'     => (int) ( wp_count_posts( 'pizzatier_cuts'     )->publish ?? 0 ),
		];

		$has_template = get_option( 'pizzatier_setting_global_template', '' ) !== '';
		$has_defaults = get_option( 'pizzatier_setting_crust_defaultcrust', '' ) !== '';

		// ── Extra auto-detection signals ────────────────────────────────
		$has_layer_images = $this->any_layer_image_exists();          // images step
		$builder_embedded = $this->builder_is_embedded();             // shortcode step
		$builder_viewed   = (bool) get_option( 'pizzatier_builder_viewed', false ); // test step

		// ── Checklist definition ─────────────────────────────────────────
		$checklist = [
			[
				'key'        => 'install',
				'label'      => __( 'Install &amp; activate PizzaTier', 'pizzatier' ),
				'desc'       => __( 'You\'re reading this — done!', 'pizzatier' ),
				'auto_done'  => true,
				'link'       => null,
				'link_label' => null,
			],
			[
				'key'        => 'images',
				'label'      => __( 'Prepare your layer images', 'pizzatier' ),
				'desc'       => __( 'Each ingredient needs a transparent PNG layer image (800×800 px). Use PizzaTier → Layer Image Maker to crop, adjust, and export your images — or create them when adding each item. Auto-completes once any layer has an image.', 'pizzatier' ),
				'detected'   => $has_layer_images,
				'auto_done'  => $has_layer_images || isset( $done['images'] ),
				'manual'     => true,
				'link'       => admin_url( 'admin.php?page=pizzatier-layer-maker' ),
				'link_label' => __( 'Layer Image Maker', 'pizzatier' ),
			],
			[
				'key'        => 'crusts',
				'label'      => __( 'Add at least one Crust', 'pizzatier' ),
				'desc'       => __( 'Go to PizzaTier → Crusts and publish a crust with a layer image.', 'pizzatier' ),
				'auto_done'  => $stats['crusts'] > 0,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_crusts' ),
				'link_label' => __( 'Add Crust', 'pizzatier' ),
				'count'      => $stats['crusts'],
			],
			[
				'key'        => 'sauces',
				'label'      => __( 'Add at least one Sauce', 'pizzatier' ),
				'desc'       => __( 'Go to PizzaTier → Sauces and publish a sauce with a layer image.', 'pizzatier' ),
				'auto_done'  => $stats['sauces'] > 0,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_sauces' ),
				'link_label' => __( 'Add Sauce', 'pizzatier' ),
				'count'      => $stats['sauces'],
			],
			[
				'key'        => 'cheeses',
				'label'      => __( 'Add at least one Cheese', 'pizzatier' ),
				'desc'       => __( 'Go to PizzaTier → Cheeses and publish a cheese with a layer image.', 'pizzatier' ),
				'auto_done'  => $stats['cheeses'] > 0,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_cheeses' ),
				'link_label' => __( 'Add Cheese', 'pizzatier' ),
				'count'      => $stats['cheeses'],
			],
			[
				'key'        => 'toppings',
				'label'      => __( 'Add your Toppings', 'pizzatier' ),
				'desc'       => __( 'Toppings are the heart of the builder — add as many as your menu needs.', 'pizzatier' ),
				'auto_done'  => $stats['toppings'] > 0,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_toppings' ),
				'link_label' => __( 'Add Topping', 'pizzatier' ),
				'count'      => $stats['toppings'],
			],
			[
				'key'        => 'drizzles',
				'label'      => __( 'Add Drizzles <em>(optional)</em>', 'pizzatier' ),
				'desc'       => __( 'Finishing touch layers — hot honey, balsamic, ranch. Optional but delightful.', 'pizzatier' ),
				'auto_done'  => $stats['drizzles'] > 0 || isset( $done['drizzles'] ),
				'detected'   => $stats['drizzles'] > 0,
				'optional'   => true,
				'manual'     => true,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_drizzles' ),
				'link_label' => __( 'Add Drizzle', 'pizzatier' ),
				'count'      => $stats['drizzles'],
			],
			[
				'key'        => 'cuts',
				'label'      => __( 'Add Cut styles <em>(optional)</em>', 'pizzatier' ),
				'desc'       => __( 'Slice overlay layers — triangle, square, party, whole. Optional.', 'pizzatier' ),
				'auto_done'  => $stats['cuts'] > 0 || isset( $done['cuts'] ),
				'detected'   => $stats['cuts'] > 0,
				'optional'   => true,
				'manual'     => true,
				'link'       => admin_url( 'post-new.php?post_type=pizzatier_cuts' ),
				'link_label' => __( 'Add Cut Style', 'pizzatier' ),
				'count'      => $stats['cuts'],
			],
			[
				'key'        => 'template',
				'label'      => __( 'Choose a Template', 'pizzatier' ),
				'desc'       => __( 'Pick the visual theme for your pizza builder in PizzaTier → Template.', 'pizzatier' ),
				'auto_done'  => $has_template,
				'link'       => admin_url( 'admin.php?page=pizzatier-template' ),
				'link_label' => __( 'Choose Template', 'pizzatier' ),
			],
			[
				'key'        => 'settings',
				'label'      => __( 'Configure Plugin Settings', 'pizzatier' ),
				'desc'       => __( 'Set your default crust, sauce, max toppings and other options in PizzaTier → Settings. New to WordPress? Use the Settings Wizard for a friendly guided walk-through.', 'pizzatier' ),
				'auto_done'  => $has_defaults,
				'link'       => admin_url( 'admin.php?page=pizzatier-wizard' ),
				'link_label' => __( '✦ Settings Wizard', 'pizzatier' ),
			],
			[
				'key'        => 'shortcode',
				'label'      => __( 'Embed the Builder on a page', 'pizzatier' ),
				'desc'       => __( 'Use the Shortcode Generator to get your <code>[pizza_builder]</code> shortcode, then add it to any page. Auto-completes once the shortcode is found in published content.', 'pizzatier' ),
				'detected'   => $builder_embedded,
				'auto_done'  => $builder_embedded || isset( $done['shortcode'] ),
				'manual'     => true,
				'link'       => admin_url( 'admin.php?page=pizzatier-shortcodes' ),
				'link_label' => __( 'Shortcode Generator', 'pizzatier' ),
			],
			[
				'key'        => 'test',
				'label'      => __( 'View your builder on the front end', 'pizzatier' ),
				'desc'       => __( 'Visit your builder page as a customer and confirm the layers display correctly. Auto-completes the first time the builder renders on your live site.', 'pizzatier' ),
				'detected'   => $builder_viewed,
				'auto_done'  => $builder_viewed || isset( $done['test'] ),
				'manual'     => true,
				'link'       => home_url( '/' ),
				'link_label' => __( 'View Site', 'pizzatier' ),
			],
		];

		$done_count  = count( array_filter( $checklist, fn( $i ) => $i['auto_done'] ?? false ) );
		$total_count = count( $checklist );
		$pct         = (int) round( $done_count / $total_count * 100 );

		?>
		<div class="wrap psg-wrap">

		<?php $this->render_styles(); ?>

		<!-- ══ Header ══════════════════════════════════════════════════ -->
		<div class="psg-header">
			<span class="dashicons dashicons-welcome-learn-more psg-header__icon"></span>
			<div style="flex:1;">
				<h1 class="psg-header__title"><?php esc_html_e( 'Setup Guide', 'pizzatier' ); ?></h1>
				<p class="psg-header__sub"><?php esc_html_e( 'Everything you need to get PizzaTier up and running — in the right order.', 'pizzatier' ); ?></p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">
					<span class="dashicons dashicons-dashboard" style="font-size:14px;width:14px;height:14px;"></span> <?php esc_html_e( 'Dashboard', 'pizzatier' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-settings' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">
					<span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;"></span> <?php esc_html_e( 'Settings', 'pizzatier' ); ?>
				</a>
			</div>
		</div>

		<!-- ══ Progress bar ════════════════════════════════════════════ -->
		<div class="psg-card psg-progress-card">
			<div class="psg-progress-bar-wrap">
				<div class="psg-progress-bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
			</div>
			<div class="psg-progress-labels">
				<span><?php printf( /* translators: 1: completed step count, 2: total step count. */ esc_html__( '%1$d of %2$d steps complete', 'pizzatier' ), (int) $done_count, (int) $total_count ); ?></span>
				<span class="psg-pct"><?php echo esc_html( (string) $pct ); ?>%</span>
			</div>
		</div>

		<!-- ══ Checklist ════════════════════════════════════════════════ -->
		<div class="psg-card">
			<div class="psg-card__head">
				<h2><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Setup Checklist', 'pizzatier' ); ?></h2>
				<p><?php esc_html_e( 'Work through these steps in order. Auto-detected items update as you add content.', 'pizzatier' ); ?></p>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field( 'pizzatier_setup_checklist' ); ?>
				<ol class="psg-checklist">
				<?php foreach ( $checklist as $idx => $item ) :
					$is_done  = $item['auto_done'] ?? false;
					$optional = $item['optional'] ?? false;
					$manual   = ! empty( $item['manual'] );           // step supports a manual fallback toggle
					$detected = ! empty( $item['detected'] );          // real auto-signal fired (independent of manual mark)
					// "Undo" only makes sense when the step is done purely because it
					// was hand-marked — never when an auto-signal is holding it true.
					$manual_only_done = $is_done && $manual && ! $detected;
				?>
					<li class="psg-checklist__item<?php echo $is_done ? ' psg-checklist__item--done' : ''; ?><?php echo $optional ? ' psg-checklist__item--optional' : ''; ?>">
						<div class="psg-cl-status">
							<?php if ( $is_done ) : ?>
								<span class="psg-cl-check psg-cl-check--done dashicons dashicons-yes-alt"></span>
							<?php else : ?>
								<span class="psg-cl-check psg-cl-check--pending dashicons dashicons-marker"></span>
							<?php endif; ?>
						</div>
						<div class="psg-cl-body">
							<div class="psg-cl-title">
								<?php echo wp_kses_post( $item['label'] ); ?>
								<?php if ( isset( $item['count'] ) && $item['count'] > 0 ) : ?>
									<span class="psg-cl-badge"><?php echo esc_html( (string) $item['count'] ); ?> <?php esc_html_e( 'added', 'pizzatier' ); ?></span>
								<?php endif; ?>
								<?php if ( $optional ) : ?>
									<span class="psg-cl-opt-badge"><?php esc_html_e( 'optional', 'pizzatier' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="psg-cl-desc"><?php echo wp_kses_post( $item['desc'] ); ?></div>
						</div>
						<div class="psg-cl-actions">
							<?php if ( ! empty( $item['link'] ) && ! $is_done ) : ?>
							<a href="<?php echo esc_url( $item['link'] ); ?>" class="button button-small">
								<?php echo esc_html( $item['link_label'] ?? __( 'Go', 'pizzatier' ) ); ?> →
							</a>
							<?php elseif ( ! empty( $item['link'] ) ) : ?>
							<a href="<?php echo esc_url( $item['link'] ); ?>" class="button button-small button-secondary">
								<?php echo esc_html( $item['link_label'] ?? __( 'View', 'pizzatier' ) ); ?>
							</a>
							<?php endif; ?>
							<?php if ( $manual && ! $is_done ) : ?>
							<button type="submit" name="pizzatier_setup_done" value="<?php echo esc_attr( $item['key'] ); ?>" class="button button-small psg-mark-done">
								<input type="hidden" name="checked" value="1"><?php echo $optional ? esc_html__( 'Skip / mark done', 'pizzatier' ) : esc_html__( 'Mark done', 'pizzatier' ); ?>
							</button>
							<?php elseif ( $manual_only_done ) : ?>
							<button type="submit" name="pizzatier_setup_done" value="<?php echo esc_attr( $item['key'] ); ?>" class="button button-small psg-mark-undone">
								<input type="hidden" name="checked" value="0"><?php esc_html_e( 'Undo', 'pizzatier' ); ?>
							</button>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
				</ol>
			</form>
		</div>

		<!-- ══ Quickstart CTA ══════════════════════════════════════════ -->
		<?php if ( $show_quickstart_cta ) : ?>
		<div class="psg-quickstart-cta">
			<div class="psg-quickstart-cta__icon">🚀</div>
			<div class="psg-quickstart-cta__body">
				<?php echo wp_kses_post( __( '<strong>New to PizzaTier?</strong> Head to the full Help &amp; Reference page for the complete Quickstart guide — five clear steps from a blank install to a live interactive builder.', 'pizzatier' ) ); ?>
				<div class="psg-quickstart-cta__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-help&section=quickstart' ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'View Quickstart Guide', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-help' ) ); ?>" class="button">
						<span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Help &amp; Reference', 'pizzatier' ); ?>
					</a>
				</div>
			</div>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'pizzatier_dismiss_quickstart_cta', '1' ), 'pizzatier_dismiss_quickstart_cta' ) ); ?>"
			   class="psg-quickstart-cta__dismiss" title="<?php esc_attr_e( 'Dismiss', 'pizzatier' ); ?>">✕</a>
		</div>
		<?php endif; ?>

		<!-- ══ Cart & pricing checklist ══════════════════════════════ -->
		<?php
		/*
		 * The steps that arrived with the PizzaTier merge, rendered inline so
		 * a site has one setup checklist rather than two. Own progress bar and
		 * own stored state; the ticks post back to this same screen.
		 */
		?>
		<div class="psg-card">
			<h2 style="margin-top:0;">
				<span class="dashicons dashicons-cart" style="color:#ff6b35;"></span>
				<?php esc_html_e( 'Selling pizzas', 'pizzatier' ); ?>
			</h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Optional. These steps cover taking payment through WooCommerce. Skip them if you take orders through PizzaTier\'s own ordering system, or if you are only showing the builder.', 'pizzatier' ); ?>
			</p>
			<?php ( new \PizzaTier\Commerce\Admin\SetupGuide() )->render_embedded_checklist(); ?>
		</div>

		<!-- ══ Help footer ════════════════════════════════════════════ -->
		<div class="psg-card psg-card--help">
			<span class="dashicons dashicons-sos"></span>
			<div>
				<h3><?php esc_html_e( 'Need help?', 'pizzatier' ); ?></h3>
				<p><?php printf( wp_kses_post( /* translators: %s = contact link. */ __( 'Check the documentation or reach out through %s.', 'pizzatier' ) ), '<a href="https://islandsundesign.com" target="_blank" rel="noopener">IslandSunDesign.com</a>' ); ?></p>
			</div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="button"><?php esc_html_e( '← Back to Dashboard', 'pizzatier' ); ?></a>
		</div>

		</div><!-- /.wrap -->

		<?php
	}

	/**
	 * Whether any published layer post already has an image — either a native
	 * "{type}_layer_image" meta value or a featured image. Works with or without
	 * SCF/ACF. One bounded query; the Setup Guide is loaded infrequently.
	 */
	private function any_layer_image_exists(): bool {
		global $wpdb;

		$post_types = "'pizzatier_crusts','pizzatier_sauces','pizzatier_cheeses','pizzatier_toppings','pizzatier_drizzles','pizzatier_cuts'";
		$meta_keys  = "'crust_layer_image','sauce_layer_image','cheese_layer_image','topping_layer_image','drizzle_layer_image','cut_layer_image'";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $post_types/$meta_keys are hardcoded constant lists defined above, not user input; no injection vector.
		$found = $wpdb->get_var(
			"SELECT pm.post_id
			   FROM {$wpdb->postmeta} pm
			   INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE p.post_status = 'publish'
			    AND p.post_type IN ( {$post_types} )
			    AND (
			        ( pm.meta_key IN ( {$meta_keys} ) AND pm.meta_value <> '' AND pm.meta_value <> '0' )
			        OR pm.meta_key = '_thumbnail_id'
			    )
			  LIMIT 1"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return ! empty( $found );
	}

	/**
	 * Whether the [pizza_builder] shortcode appears in any published content.
	 */
	private function builder_is_embedded(): bool {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '[pizza_builder' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				  WHERE post_status = 'publish'
				    AND post_content LIKE %s
				  LIMIT 1",
				$like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return ! empty( $found );
	}

	private function render_styles(): void { ?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
	<?php }
}
