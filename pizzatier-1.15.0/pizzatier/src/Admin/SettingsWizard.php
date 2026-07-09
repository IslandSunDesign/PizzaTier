<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Settings Wizard
 *
 * A friendly, novice-oriented step-through of the key plugin settings.
 * Designed for small business owners and non-developers.
 * Each step maps to a real Settings option and can be marked done or skipped.
 */
class SettingsWizard {

	/** Steps stored persistently so the user can resume any time. */
	private function get_done(): array {
		return (array) get_option( 'pizzatier_wizard_done', [] );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// ── Handle step save ─────────────────────────────────────────────
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_wizard' ) ) {

			// Mark/unmark step done
			if ( isset( $_POST['pzwiz_mark_done'] ) ) {
				$done_arr = $this->get_done();
				$key = sanitize_key( $_POST['pzwiz_mark_done'] );
				$state = ( isset( $_POST['pzwiz_state'] ) && $_POST['pzwiz_state'] === '0' ) ? false : true;
				if ( $state ) {
					$done_arr[ $key ] = true;
				} else {
					unset( $done_arr[ $key ] );
				}
				update_option( 'pizzatier_wizard_done', $done_arr );
			}

			// Save wizard step settings
			if ( isset( $_POST['pzwiz_save'] ) ) {
				$this->save_step( sanitize_key( $_POST['pzwiz_save'] ) );
			}

			// Reset wizard
			if ( isset( $_POST['pzwiz_reset'] ) ) {
				delete_option( 'pizzatier_wizard_done' );
			}

			wp_safe_redirect( remove_query_arg( [] ) );
			exit;
		}

		$done       = $this->get_done();
		$active_tab = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : '';

		// ── Load CPT options for dropdowns ────────────────────────────────
		$q        = [ 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
		$crusts   = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_crusts' ] ) );
		$sauces   = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_sauces' ] ) );
		$cheeses  = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_cheeses' ] ) );
		$drizzles = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_drizzles' ] ) );
		$cuts     = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_cuts' ] ) );

		// $opt_key is always a hardcoded option key supplied by get_steps()
		// (e.g. 'pizzatier_setting_crust_defaultcrust'); it never derives from
		// request input. (The sanitized $_POST['pzwiz_mark_done'] above is used
		// only as an array index into the fixed 'pizzatier_wizard_done' option,
		// never as an option name.)
		$g = fn( string $opt_key, string $default = '' ) => (string) get_option( $opt_key, $default );

		// ── Step definitions ──────────────────────────────────────────────
		// Each step: key, title, icon, plain-English intro, fields array
		$steps = $this->get_steps( $crusts, $sauces, $cheeses, $drizzles, $cuts, $g );

		$total      = count( $steps );
		$done_count = count( array_filter( $steps, fn( $s ) => ! empty( $done[ $s['key'] ] ) ) );
		$pct        = $total > 0 ? (int) round( $done_count / $total * 100 ) : 0;

		if ( $active_tab === '' ) {
			$active_tab = $steps[0]['key'];
		}

		?>
		<div class="wrap pzwiz-wrap">
		<?php $this->render_styles(); ?>

		<!-- ═══ Header ═══════════════════════════════════════════════════ -->
		<div class="pzwiz-header">
			<span class="dashicons dashicons-admin-settings pzwiz-header__icon"></span>
			<div class="pzwiz-header__text">
				<h1 class="pzwiz-header__title"><?php esc_html_e( 'Settings Wizard', 'pizzatier' ); ?></h1>
				<p class="pzwiz-header__sub"><?php esc_html_e( 'Step-by-step settings guide — no jargon, plain English. Work through each section at your own pace.', 'pizzatier' ); ?></p>
			</div>
			<div class="pzwiz-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-settings' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-settings" style="margin-top:3px;"></span>
					<?php esc_html_e( 'Full Settings Page', 'pizzatier' ); ?>
				</a>
			</div>
		</div>

		<!-- ═══ Progress bar ══════════════════════════════════════════════ -->
		<div class="pzwiz-progress-wrap">
			<div class="pzwiz-progress-bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
		</div>
		<p class="pzwiz-progress-label">
			<?php printf( /* translators: 1: completed sections, 2: total sections, 3: percent complete. */ esc_html__( '%1$d of %2$d sections complete (%3$d%%)', 'pizzatier' ), (int) $done_count, (int) $total, (int) $pct ); ?>
			<?php if ( $done_count > 0 ) : ?>
				&nbsp;·&nbsp;
				<form method="post" action="" style="display:inline;">
					<?php wp_nonce_field( 'pizzatier_wizard' ); ?>
					<button type="submit" name="pzwiz_reset" value="1" class="pzwiz-reset-link"
					        onclick="return confirm('<?php esc_attr_e( 'Reset all wizard progress?', 'pizzatier' ); ?>');">
						<?php esc_html_e( 'Reset progress', 'pizzatier' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</p>

		<!-- ═══ Two-column layout: sidebar + content ══════════════════════ -->
		<div class="pzwiz-layout">

			<!-- Sidebar step list -->
			<nav class="pzwiz-sidebar" aria-label="Wizard steps">
				<?php foreach ( $steps as $idx => $step ) :
					$is_done    = ! empty( $done[ $step['key'] ] );
					$is_active  = $step['key'] === $active_tab;
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-wizard&step=' . $step['key'] ) ); ?>"
				   class="pzwiz-step-link<?php echo $is_active ? ' pzwiz-step-link--active' : ''; ?><?php echo $is_done ? ' pzwiz-step-link--done' : ''; ?>">
					<span class="pzwiz-step-num"><?php echo esc_html( $is_done ? '✓' : (string) ( $idx + 1 ) ); ?></span>
					<span class="pzwiz-step-label">
						<span class="pzwiz-step-title"><?php echo esc_html( $step['title'] ); ?></span>
						<?php if ( $is_done ) : ?>
						<span class="pzwiz-step-badge pzwiz-step-badge--done"><?php esc_html_e( 'Done', 'pizzatier' ); ?></span>
						<?php elseif ( ! empty( $step['optional'] ) ) : ?>
						<span class="pzwiz-step-badge pzwiz-step-badge--opt"><?php esc_html_e( 'Optional', 'pizzatier' ); ?></span>
						<?php endif; ?>
					</span>
				</a>
				<?php endforeach; ?>
			</nav>

			<!-- Step content panel -->
			<?php foreach ( $steps as $idx => $step ) :
				if ( $step['key'] !== $active_tab ) { continue; }
				$is_done = ! empty( $done[ $step['key'] ] );

				// Prev/next
				$prev_key = $idx > 0             ? $steps[ $idx - 1 ]['key'] : null;
				$next_key = $idx < $total - 1    ? $steps[ $idx + 1 ]['key'] : null;
			?>
			<div class="pzwiz-content">

				<div class="pzwiz-step-header">
					<span class="pzwiz-step-header__num"><?php echo esc_html( (string)( $idx + 1 ) ); ?></span>
					<div>
						<h2 class="pzwiz-step-header__title">
							<span class="dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
							<?php echo esc_html( $step['title'] ); ?>
							<?php if ( ! empty( $step['optional'] ) ) : ?><span class="pzwiz-opt-tag"><?php esc_html_e( 'Optional', 'pizzatier' ); ?></span><?php endif; ?>
						</h2>
						<p class="pzwiz-step-header__desc"><?php echo wp_kses_post( $step['intro'] ); ?></p>
					</div>
				</div>

				<?php if ( $is_done ) : ?>
				<div class="pzwiz-done-banner">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'You\'ve marked this section as done. You can still edit the settings below and save anytime.', 'pizzatier' ); ?>
					<form method="post" action="" style="display:inline;margin-left:10px;">
						<?php wp_nonce_field( 'pizzatier_wizard' ); ?>
						<input type="hidden" name="pzwiz_mark_done" value="<?php echo esc_attr( $step['key'] ); ?>">
						<input type="hidden" name="pzwiz_state" value="0">
						<button type="submit" class="button button-small"><?php esc_html_e( 'Mark undone', 'pizzatier' ); ?></button>
					</form>
				</div>
				<?php endif; ?>

				<!-- Settings form for this step -->
				<form method="post" action="">
					<?php wp_nonce_field( 'pizzatier_wizard' ); ?>
					<input type="hidden" name="pzwiz_save" value="<?php echo esc_attr( $step['key'] ); ?>">

					<div class="pzwiz-fields">
						<?php $this->render_step_fields( $step, $g ); ?>
					</div>

					<div class="pzwiz-step-footer">
						<button type="submit" class="button button-primary pzwiz-save-btn">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Save this section', 'pizzatier' ); ?>
						</button>

						<?php if ( ! $is_done ) : ?>
						<button type="submit" name="pzwiz_mark_done" value="<?php echo esc_attr( $step['key'] ); ?>" class="button">
							<input type="hidden" name="pzwiz_state" value="1">
							<?php esc_html_e( 'Skip / Mark done', 'pizzatier' ); ?>
						</button>
						<?php endif; ?>

						<div class="pzwiz-nav-btns">
							<?php if ( $prev_key ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-wizard&step=' . $prev_key ) ); ?>" class="button">← <?php esc_html_e( 'Previous', 'pizzatier' ); ?></a>
							<?php endif; ?>
							<?php if ( $next_key ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-wizard&step=' . $next_key ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Next', 'pizzatier' ); ?> →</a>
							<?php endif; ?>
							<?php if ( ! $next_key ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-setup' ) ); ?>" class="button button-primary">
								🎉 <?php esc_html_e( 'Finish — view Setup Guide', 'pizzatier' ); ?>
							</a>
							<?php endif; ?>
						</div>
					</div>

				</form>

			</div><!-- /.pzwiz-content -->
			<?php endforeach; ?>

		</div><!-- /.pzwiz-layout -->
		</div><!-- /.wrap -->
		<?php
	}

	// ── Save step settings ────────────────────────────────────────────────────

	private function save_step( string $step_key ): void {
		check_admin_referer( 'pizzatier_wizard' );

		/** Map of step key → option keys it owns (must match actual Settings.php keys) */
		$step_options = [
			'defaults'    => [
				'pizzatier_setting_crust_defaultcrust',
				'pizzatier_setting_sauce_defaultsauce',
				'pizzatier_setting_cheese_defaultcheese',
				'pizzatier_setting_drizzle_defaultdrizzle',
				'pizzatier_setting_cut_defaultcut',
			],
			'toppings'    => [
				'pizzatier_setting_topping_maxtoppings',
			],
			'display'     => [
				'pizzatier_setting_pizza_size_max',
				'pizzatier_setting_pizza_shape',
				'pizzatier_setting_pizza_border_color',
				'pizzatier_setting_global_color',
			],
			'appearance'  => [
				'pizzatier_setting_branding_primary_color',
				'pizzatier_setting_branding_secondary_color',
				'pizzatier_setting_typo_font_family',
			],
			'layout'      => [
				'pizzatier_setting_layout_tab_order',
				'pizzatier_setting_layout_hide_empty',
				'pizzatier_setting_layout_step_by_step',
			],
			'messaging'   => [
				'pizzatier_setting_branding_tagline',
				'pizzatier_setting_settings_demonotice',
			],
			'ux'          => [
				'pizzatier_setting_cx_show_summary',
				'pizzatier_setting_cx_special_instructions',
				'pizzatier_setting_cx_special_instr_max',
				'pizzatier_setting_cx_review_modal',
				'pizzatier_setting_cx_show_start_over',
			],
			'animations'  => [
				'pizzatier_setting_layer_anim',
				'pizzatier_setting_layer_anim_speed',
			],
			'accessibility' => [
				'pizzatier_setting_a11y_focus_ring',
				'pizzatier_setting_a11y_reduce_motion',
				'pizzatier_setting_perf_lazy_load',
			],
		];

		if ( ! isset( $step_options[ $step_key ] ) ) {
			return;
		}

		$yes_no_keys = [
			'pizzatier_setting_layout_hide_empty',
			'pizzatier_setting_layout_step_by_step',
			'pizzatier_setting_cx_show_summary',
			'pizzatier_setting_cx_special_instructions',
			'pizzatier_setting_cx_review_modal',
			'pizzatier_setting_cx_show_start_over',
			'pizzatier_setting_a11y_reduce_motion',
			'pizzatier_setting_perf_lazy_load',
		];

		foreach ( $step_options[ $step_key ] as $opt_key ) {
			if ( in_array( $opt_key, $yes_no_keys, true ) ) {
				$val = isset( $_POST[ $opt_key ] ) && $_POST[ $opt_key ] === 'yes' ? 'yes' : 'no';
				update_option( sanitize_key( $opt_key ), $val );
			} elseif ( isset( $_POST[ $opt_key ] ) ) {
				update_option( sanitize_key( $opt_key ), sanitize_text_field( wp_unslash( (string) $_POST[ $opt_key ] ) ) );
			}
		}

		// Also mark step done when saving
		$done = $this->get_done();
		$done[ $step_key ] = true;
		update_option( 'pizzatier_wizard_done', $done );
	}

	// ── Step definitions ──────────────────────────────────────────────────────

	private function get_steps( array $crusts, array $sauces, array $cheeses, array $drizzles, array $cuts, callable $g ): array {
		return [
			[
				'key'   => 'defaults',
				'title' => __( 'Default Selections', 'pizzatier' ),
				'icon'  => 'dashicons-category',
				'intro' => __( 'When someone opens your pizza builder, what should already be selected? Think of this like "the house pizza" — the default crust, sauce, and cheese that loads up automatically so customers can start customizing right away without having to pick from scratch.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'layer_picker',
						'key'     => 'pizzatier_setting_crust_defaultcrust',
						'label'   => __( 'Default Crust', 'pizzatier' ),
						'tip'     => __( 'Which crust should be pre-selected? Pick your most popular or standard crust.', 'pizzatier' ),
						'items'   => $crusts,
						'current' => $g( 'pizzatier_setting_crust_defaultcrust' ),
					],
					[
						'type'    => 'layer_picker',
						'key'     => 'pizzatier_setting_sauce_defaultsauce',
						'label'   => __( 'Default Sauce', 'pizzatier' ),
						'tip'     => __( 'The sauce shown when the builder first opens. Classic tomato is a safe default for most pizzerias.', 'pizzatier' ),
						'items'   => $sauces,
						'current' => $g( 'pizzatier_setting_sauce_defaultsauce' ),
					],
					[
						'type'    => 'layer_picker',
						'key'     => 'pizzatier_setting_cheese_defaultcheese',
						'label'   => __( 'Default Cheese', 'pizzatier' ),
						'tip'     => __( 'Pre-selected cheese type. Mozzarella is the most common default.', 'pizzatier' ),
						'items'   => $cheeses,
						'current' => $g( 'pizzatier_setting_cheese_defaultcheese' ),
					],
					[
						'type'    => 'layer_picker',
						'key'     => 'pizzatier_setting_drizzle_defaultdrizzle',
						'label'   => __( 'Default Drizzle (optional)', 'pizzatier' ),
						'tip'     => __( 'Leave blank if you don\'t want a drizzle pre-selected.', 'pizzatier' ),
						'items'   => $drizzles,
						'current' => $g( 'pizzatier_setting_drizzle_defaultdrizzle' ),
						'blank'   => __( '— None —', 'pizzatier' ),
					],
					[
						'type'    => 'layer_picker',
						'key'     => 'pizzatier_setting_cut_defaultcut',
						'label'   => __( 'Default Cut Style (optional)', 'pizzatier' ),
						'tip'     => __( 'Leave blank if you don\'t want a cut style pre-selected.', 'pizzatier' ),
						'items'   => $cuts,
						'current' => $g( 'pizzatier_setting_cut_defaultcut' ),
						'blank'   => __( '— None —', 'pizzatier' ),
					],
				],
			],
			[
				'key'   => 'toppings',
				'title' => __( 'Topping Rules', 'pizzatier' ),
				'icon'  => 'dashicons-star-filled',
				'intro' => __( 'Set the maximum number of toppings a customer can add. Set to 0 for unlimited. This helps you control the ordering experience and match how your kitchen actually works.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'number',
						'key'     => 'pizzatier_setting_topping_maxtoppings',
						'label'   => __( 'Maximum Toppings Allowed', 'pizzatier' ),
						'tip'     => __( 'The most toppings a customer can add to one pizza. Set to 0 for unlimited. Most pizzerias allow 5–8 toppings on a standard build.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_topping_maxtoppings', '0' ),
						'min'     => 0,
						'max'     => 99,
						'placeholder' => '0 = unlimited',
					],
				],
			],
			[
				'key'   => 'display',
				'title' => __( 'Pizza Display', 'pizzatier' ),
				'icon'  => 'dashicons-format-image',
				'intro' => __( 'Control how the pizza visualizer looks — its maximum size on screen, whether it\'s round or square, and its border/accent colors. This is purely visual and doesn\'t affect ordering. Pick whatever looks best on your website.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'number',
						'key'     => 'pizzatier_setting_pizza_size_max',
						'label'   => __( 'Pizza Max Display Size (pixels)', 'pizzatier' ),
						'tip'     => __( 'The maximum width of the pizza visualizer. 600px is a good default for most layouts. The pizza will still scale down on smaller screens.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_pizza_size_max', '600' ),
						'min'     => 100,
						'max'     => 1200,
						'placeholder' => '600',
					],
					[
						'type'    => 'select',
						'key'     => 'pizzatier_setting_pizza_shape',
						'label'   => __( 'Pizza Shape', 'pizzatier' ),
						'tip'     => __( 'Round (circle) looks like a real pizza. Square works well for pan/Detroit-style pizzas. Rectangle is good for sheet pizzas. Custom lets you set your own aspect ratio and radius.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_pizza_shape', 'round' ),
						'options' => [
							'round'     => __( 'Round (circle)', 'pizzatier' ),
							'square'    => __( 'Square', 'pizzatier' ),
							'rectangle' => __( 'Rectangle (pan/sheet style)', 'pizzatier' ),
							'custom'    => __( 'Custom (set aspect ratio and radius)', 'pizzatier' ),
						],
					],
					[
						'type'    => 'color',
						'key'     => 'pizzatier_setting_pizza_border_color',
						'label'   => __( 'Pizza Border Color', 'pizzatier' ),
						'tip'     => __( 'The thin line around the pizza image. Use a warm brown or orange to make it look like a crust edge.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_pizza_border_color', '#c8a46e' ),
					],
					[
						'type'    => 'color',
						'key'     => 'pizzatier_setting_global_color',
						'label'   => __( 'Accent / Highlight Color', 'pizzatier' ),
						'tip'     => __( 'The main color used for selected items, buttons, and highlights throughout the builder. Pick something that matches your brand.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_global_color', '#ff6b35' ),
					],
				],
			],
			[
				'key'   => 'appearance',
				'title' => __( 'Look & Feel', 'pizzatier' ),
				'icon'  => 'dashicons-art',
				'intro' => __( 'Give the builder your brand\'s personality. Set your primary and secondary brand colors, and choose a font family. These work alongside your chosen template\'s own styling.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'color',
						'key'     => 'pizzatier_setting_branding_primary_color',
						'label'   => __( 'Brand Primary Color', 'pizzatier' ),
						'tip'     => __( 'Your main brand color — used for buttons, selected states, and highlights. This should match the primary color on your website.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_branding_primary_color', '#ff6b35' ),
					],
					[
						'type'    => 'color',
						'key'     => 'pizzatier_setting_branding_secondary_color',
						'label'   => __( 'Brand Secondary Color', 'pizzatier' ),
						'tip'     => __( 'A supporting color — used for hover states, secondary buttons, and accents. Often a darker or lighter shade of your primary color.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_branding_secondary_color', '#e55a2b' ),
					],
					[
						'type'    => 'text',
						'key'     => 'pizzatier_setting_typo_font_family',
						'label'   => __( 'Font Family', 'pizzatier' ),
						'tip'     => __( 'The font used inside the builder. Leave blank to use your theme\'s default font. To use a Google Font, enter its exact name (e.g. "Lato" or "Poppins") — make sure the font is already loaded by your theme.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_typo_font_family', '' ),
						'placeholder' => __( 'e.g. Lato, Poppins, or leave blank', 'pizzatier' ),
					],
				],
			],
			[
				'key'   => 'layout',
				'title' => __( 'Builder Layout', 'pizzatier' ),
				'icon'  => 'dashicons-layout',
				'intro' => __( 'Control the order of the ingredient tabs and a couple of layout options. The tab order determines the steps customers follow when building their pizza — put the most important choices first.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'text',
						'key'     => 'pizzatier_setting_layout_tab_order',
						'label'   => __( 'Tab Order', 'pizzatier' ),
						'tip'     => __( 'List the ingredient categories in the order you want them to appear as tabs, separated by commas. For example: crust, sauce, cheese, toppings, drizzle, slicing. Leave blank for the default order.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_layout_tab_order', '' ),
						'placeholder' => 'crust, sauce, cheese, toppings, drizzle, slicing',
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_layout_hide_empty',
						'label'   => __( 'Hide Tabs With No Items', 'pizzatier' ),
						'tip'     => __( 'If you haven\'t added any drizzles yet, should the "Drizzle" tab be hidden? Turn this on to keep the builder tidy — tabs only appear once you\'ve added content for them.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_layout_hide_empty', 'no' ),
						'toggle_label' => __( 'Hide tabs that have no published items', 'pizzatier' ),
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_layout_step_by_step',
						'label'   => __( 'Step-by-Step Mode', 'pizzatier' ),
						'tip'     => __( 'Locks customers to one tab at a time, stepping through crust → sauce → cheese → toppings in order. Great for guided ordering workflows.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_layout_step_by_step', 'no' ),
						'toggle_label' => __( 'Guide customers through tabs one step at a time', 'pizzatier' ),
					],
				],
			],
			[
				'key'   => 'messaging',
				'title' => __( 'Text & Messaging', 'pizzatier' ),
				'icon'  => 'dashicons-format-chat',
				'intro' => __( 'Customise the words customers see inside the builder — the tagline shown in the header and an optional demo/notice banner. This is your chance to give the experience your restaurant\'s voice and personality.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'text',
						'key'     => 'pizzatier_setting_branding_tagline',
						'label'   => __( 'Builder Tagline', 'pizzatier' ),
						'tip'     => __( 'A short tagline shown in the builder header — something like "Build Your Perfect Pizza" or "Create Your Masterpiece". Leave blank to hide it.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_branding_tagline', '' ),
						'placeholder' => __( 'e.g. Build Your Perfect Pizza', 'pizzatier' ),
					],
					[
						'type'    => 'text',
						'key'     => 'pizzatier_setting_settings_demonotice',
						'label'   => __( 'Demo / Notice Banner', 'pizzatier' ),
						'tip'     => __( 'An optional notice shown above the builder — useful for "This is a demo" messages, promotions, or reminders like "Delivery orders close at 9 PM". Leave blank to hide it.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_settings_demonotice', '' ),
						'placeholder' => __( 'Leave blank to hide', 'pizzatier' ),
					],
				],
			],
			[
				'key'      => 'ux',
				'title'    => __( 'Customer Experience', 'pizzatier' ),
				'icon'     => 'dashicons-smiley',
				'optional' => true,
				'intro'    => __( 'Fine-tune the little extras that make the ordering experience smooth and professional. These are all optional — turn on the ones that fit your workflow.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_cx_show_summary',
						'label'   => __( 'Show Order Summary Panel', 'pizzatier' ),
						'tip'     => __( 'Shows a running list of what the customer has chosen so far (e.g. "Thin Crust + Tomato Sauce + Mozzarella + Pepperoni"). Helps customers review their choices before confirming.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_cx_show_summary', 'no' ),
						'toggle_label' => __( 'Show a summary of selected ingredients', 'pizzatier' ),
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_cx_show_start_over',
						'label'   => __( 'Show "Start Over" Button', 'pizzatier' ),
						'tip'     => __( 'Adds a "Start Over" button that resets all selections. Useful for customers who want to try a completely different build.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_cx_show_start_over', 'yes' ),
						'toggle_label' => __( 'Show a button to reset all selections', 'pizzatier' ),
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_cx_special_instructions',
						'label'   => __( 'Allow Special Instructions', 'pizzatier' ),
						'tip'     => __( 'Adds a text box where customers can type notes for the kitchen — like "well done", "no salt", or "nut allergy". Useful for accommodating dietary needs.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_cx_special_instructions', 'no' ),
						'toggle_label' => __( 'Show a "Special instructions" text field', 'pizzatier' ),
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_cx_review_modal',
						'label'   => __( 'Show Confirmation Screen', 'pizzatier' ),
						'tip'     => __( 'Pops up a final "review your order" screen before the customer confirms. Reduces mistakes and gives them one last chance to check everything.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_cx_review_modal', 'no' ),
						'toggle_label' => __( 'Show a "Review your order" pop-up before confirming', 'pizzatier' ),
					],
				],
			],
			[
				'key'      => 'animations',
				'title'    => __( 'Animations', 'pizzatier' ),
				'icon'     => 'dashicons-controls-play',
				'optional' => true,
				'intro'    => __( 'Control whether ingredient layers animate when added to the pizza. Animations look great and make the builder feel alive — but you can turn them off if you prefer a simpler, faster feel.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'select',
						'key'     => 'pizzatier_setting_layer_anim',
						'label'   => __( 'Layer Animation Style', 'pizzatier' ),
						'tip'     => __( 'How ingredients appear on the pizza when selected. "Fade in" is subtle and clean. "Drop in" feels more dramatic. "Instant" shows them immediately with no animation.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_layer_anim', 'fade' ),
						'options' => [
							'instant'  => __( 'Instant — no animation', 'pizzatier' ),
							'fade'     => __( 'Fade in — smooth and subtle', 'pizzatier' ),
							'drop-in'  => __( 'Drop in — falls from above', 'pizzatier' ),
							'scale-in' => __( 'Scale in — grows from small', 'pizzatier' ),
							'slide-up' => __( 'Slide up — enters from below', 'pizzatier' ),
							'flip-in'  => __( 'Flip in — 3D rotation reveal', 'pizzatier' ),
						],
					],
					[
						'type'    => 'range',
						'key'     => 'pizzatier_setting_layer_anim_speed',
						'label'   => __( 'Animation Speed', 'pizzatier' ),
						'tip'     => __( 'How fast the animation plays, in milliseconds. 200ms is quick and snappy. 500ms is slower and more dramatic. Most people prefer 250–350ms.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_layer_anim_speed', '320' ),
						'min'     => 50,
						'max'     => 800,
						'step'    => 10,
						'suffix'  => 'ms',
					],
				],
			],
			[
				'key'      => 'accessibility',
				'title'    => __( 'Accessibility & Performance', 'pizzatier' ),
				'icon'     => 'dashicons-universal-access',
				'optional' => true,
				'intro'    => __( 'A few settings that make the builder more usable for everyone — including customers with disabilities — and can help the page load faster. These are safe to leave on their defaults.', 'pizzatier' ),
				'fields' => [
					[
						'type'    => 'select',
						'key'     => 'pizzatier_setting_a11y_focus_ring',
						'label'   => __( 'Keyboard Focus Ring Style', 'pizzatier' ),
						'tip'     => __( 'When someone navigates with a keyboard instead of a mouse, this shows a visible outline around the currently focused item. Important for accessibility and required by some regulations.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_a11y_focus_ring', 'default' ),
						'options' => [
							'default' => __( 'Theme default', 'pizzatier' ),
							'bold'    => __( 'Bold outline (high visibility)', 'pizzatier' ),
							'glow'    => __( 'Glow ring', 'pizzatier' ),
							'none'    => __( 'None (not recommended)', 'pizzatier' ),
						],
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_a11y_reduce_motion',
						'label'   => __( 'Respect "Reduce Motion" Setting', 'pizzatier' ),
						'tip'     => __( 'Some users turn on "Reduce Motion" in their device settings (common for people with vestibular disorders or motion sensitivity). Turning this on respects that preference and disables animations for them.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_a11y_reduce_motion', 'no' ),
						'toggle_label' => __( 'Disable animations for users who prefer reduced motion', 'pizzatier' ),
					],
					[
						'type'    => 'toggle',
						'key'     => 'pizzatier_setting_perf_lazy_load',
						'label'   => __( 'Lazy Load Ingredient Images', 'pizzatier' ),
						'tip'     => __( 'Only load ingredient images when they\'re about to come into view on screen. This makes the page load faster, especially if you have a lot of toppings. Recommended: on.', 'pizzatier' ),
						'value'   => $g( 'pizzatier_setting_perf_lazy_load', 'yes' ),
						'toggle_label' => __( 'Load images only when needed (faster page load)', 'pizzatier' ),
					],
				],
			],
		];
	}

	// ── Render fields for a step ──────────────────────────────────────────────

	private function render_step_fields( array $step, callable $g ): void {
		foreach ( $step['fields'] as $field ) {
			$key = $field['key'];
			?>
			<div class="pzwiz-field">
				<div class="pzwiz-field__head">
					<label class="pzwiz-field__label" for="pzwiz-<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
					</label>
					<?php if ( ! empty( $field['tip'] ) ) : ?>
					<p class="pzwiz-field__tip"><?php echo esc_html( $field['tip'] ); ?></p>
					<?php endif; ?>
				</div>
				<div class="pzwiz-field__control">
					<?php
					switch ( $field['type'] ) {

						case 'layer_picker':
							$blank = $field['blank'] ?? null;
							echo '<select name="' . esc_attr( $key ) . '" id="pzwiz-' . esc_attr( $key ) . '" class="pzwiz-select">';
							if ( $blank !== null ) {
								echo '<option value=""' . selected( $field['current'], '', false ) . '>' . esc_html( $blank ) . '</option>';
							}
							foreach ( $field['items'] as $post ) {
								echo '<option value="' . esc_attr( $post->post_name ) . '"' . selected( $field['current'], $post->post_name, false ) . '>' . esc_html( $post->post_title ) . '</option>';
							}
							echo '</select>';
							if ( empty( $field['items'] ) ) {
								echo '<p class="pzwiz-field__notice">⚠ ' . esc_html__( 'No items found. Add some content first, then come back here.', 'pizzatier' ) . '</p>';
							}
							break;

						case 'toggle':
							$checked = ( $field['value'] === 'yes' ) ? ' checked' : '';
							echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="no">';
							echo '<label class="pzwiz-toggle">';
							echo '<input type="checkbox" id="pzwiz-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="yes"' . $checked . '>'; // phpcs:ignore
							echo '<span class="pzwiz-toggle__track"><span class="pzwiz-toggle__thumb"></span></span>';
							echo '<span class="pzwiz-toggle__lbl">' . esc_html( $field['toggle_label'] ?? $field['label'] ) . '</span>';
							echo '</label>';
							break;

						case 'number':
							$min  = isset( $field['min'] )  ? ' min="' . esc_attr( (string) $field['min'] ) . '"'  : '';
							$max  = isset( $field['max'] )  ? ' max="' . esc_attr( (string) $field['max'] ) . '"'  : '';
							$ph   = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
							echo '<input type="number" id="pzwiz-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" class="pzwiz-input"' . $min . $max . $ph . '>'; // phpcs:ignore
							break;

						case 'text':
							$ph = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
							echo '<input type="text" id="pzwiz-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" class="pzwiz-input pzwiz-input--wide"' . $ph . '>'; // phpcs:ignore
							break;

						case 'select':
							echo '<select name="' . esc_attr( $key ) . '" id="pzwiz-' . esc_attr( $key ) . '" class="pzwiz-select">';
							foreach ( $field['options'] as $ov => $ol ) {
								echo '<option value="' . esc_attr( $ov ) . '"' . selected( $field['value'], $ov, false ) . '>' . esc_html( $ol ) . '</option>';
							}
							echo '</select>';
							break;

						case 'color':
							echo '<div class="pzwiz-color-wrap">';
							echo '<input type="color" id="pzwiz-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" class="pzwiz-color">';
							echo '<input type="text" name="' . esc_attr( $key ) . '_text_display" value="' . esc_attr( $field['value'] ) . '" class="pzwiz-color-text" maxlength="7" readonly>';
							echo '</div>';
							// color sync handled by settings-wizard.js via .pzwiz-color class delegation
							break;

						case 'range':
							$min    = isset( $field['min'] )  ? ' min="' . esc_attr( (string) $field['min'] ) . '"'    : '';
							$max    = isset( $field['max'] )  ? ' max="' . esc_attr( (string) $field['max'] ) . '"'    : '';
							$step_a = isset( $field['step'] ) ? ' step="' . esc_attr( (string) $field['step'] ) . '"'  : '';
							$suffix = $field['suffix'] ?? '';
							$id     = 'pzwiz-' . esc_attr( $key );
							echo '<div class="pzwiz-range-wrap">';
							echo '<input type="range" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" class="pzwiz-range"' . $min . $max . $step_a . '>'; // phpcs:ignore
							echo '<span class="pzwiz-range-val" id="' . esc_attr( $id ) . '-val" data-suffix="' . esc_attr( $suffix ) . '">' . esc_html( $field['value'] ) . esc_html( $suffix ) . '</span>';
							echo '</div>';
							// range sync handled by settings-wizard.js via .pzwiz-range class delegation
							break;
					}
					?>
				</div>
			</div>
			<?php
		}
	}

	// ── Styles ────────────────────────────────────────────────────────────────

	private function render_styles(): void { ?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
	<?php }
}
