<?php
/**
 * Renders the PizzaTier Settings admin page HTML.
 *
 * UI matches the base PizzaTier dashboard — dark gradient header, plh-style
 * card/tab system, orange accent via CSS variable. Tab state is hash-based
 * with a no-JS fallback (all panels visible).
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsPage {

	/**
	 * Render the full settings page.
	 * Called as the menu page callback by Settings::add_settings_page().
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pizzatier' ) );
		}

		$tabs = [
			'pizzatier_commerce_section_general'              => [ 'label' => __( 'General',         'pizzatier' ), 'icon' => 'dashicons-admin-settings' ],
			// Note: 'Grid Defaults' and 'Pricing Engine' tabs have moved to
			// the dedicated PizzaTier → Pricing page in v1.6.0.
			'pizzatier_commerce_section_toppings'             => [ 'label' => __( 'Toppings',        'pizzatier' ), 'icon' => 'dashicons-carrot'         ],
			'pizzatier_commerce_section_display'              => [ 'label' => __( 'Display',         'pizzatier' ), 'icon' => 'dashicons-visibility'     ],
			'pizzatier_commerce_section_checkout_bar_layout'  => [ 'label' => __( 'Order Bar',       'pizzatier' ), 'icon' => 'dashicons-button'         ],
			'pizzatier_commerce_section_checkout'             => [ 'label' => __( 'Cart & Checkout', 'pizzatier' ), 'icon' => 'dashicons-cart'           ],
			'pizzatier_commerce_section_nutrition'            => [ 'label' => __( 'Nutrition',       'pizzatier' ), 'icon' => 'dashicons-heart'          ],
			'pizzatier_commerce_section_email_summary'        => [ 'label' => __( 'Order Emails',    'pizzatier' ), 'icon' => 'dashicons-email-alt'      ],
			'pizzatier_commerce_section_layer_groups'         => [ 'label' => __( 'Ingredient Groups','pizzatier'),'icon' => 'dashicons-category'       ],
			'pizzatier_commerce_section_advanced'             => [ 'label' => __( 'Advanced',        'pizzatier' ), 'icon' => 'dashicons-admin-tools'    ],
		];
		?>
		<div class="wrap pztc-page-wrap">

			<?php $this->render_styles(); ?>

			<!-- ══ Header ═════════════════════════════════════════════════ -->
			<div class="pztc-header">
				<div class="pztc-header__brand">
					<span class="dashicons dashicons-pizza pztc-header__icon" aria-hidden="true"></span>
					<div>
						<h1 class="pztc-header__title">PizzaTier</h1>
						<p class="pztc-header__tagline">
							<?php esc_html_e( 'WooCommerce integration settings', 'pizzatier' ); ?>
							&mdash; v<?php echo esc_html( PIZZATIER_VERSION ); ?>
						</p>
					</div>
				</div>
				<div class="pztc-header__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="button">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'PizzaTier Dashboard', 'pizzatier' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="button">
						<span class="dashicons dashicons-cart"></span>
						<?php esc_html_e( 'WC Products', 'pizzatier' ); ?>
					</a>
				</div>
			</div>

			<?php settings_errors( Settings::OPTION_NAME ); ?>

			<form method="post" action="options.php" class="pztc-settings-form">
				<?php settings_fields( Settings::OPTION_GROUP ); ?>

				<!-- ══ Tab nav ═══════════════════════════════════════════ -->
				<div class="pztc-card pztc-card--tabs">
					<div class="pztc-card__head">
						<h2 class="pztc-card__title">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Cart & Pricing', 'pizzatier' ); ?>
						</h2>
						<p class="pztc-card__subtitle">
							<?php esc_html_e( 'Configure global defaults for all pizza products in your WooCommerce store.', 'pizzatier' ); ?>
						</p>
					</div>

					<nav class="pztc-tabnav" id="pztc-settings-tabs" role="tablist">
						<?php $first = true; foreach ( $tabs as $id => $tab ) : ?>
						<button
							class="pztc-tab<?php echo $first ? ' pztc-tab--active' : ''; ?>"
							data-tab="<?php echo esc_attr( $id ); ?>"
							role="tab"
							aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
							aria-controls="pztc-panel-<?php echo esc_attr( $id ); ?>"
							type="button"
						>
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<?php echo esc_html( $tab['label'] ); ?>
						</button>
						<?php $first = false; endforeach; ?>
					</nav>

					<div class="pztc-panels">
						<?php $first = true; foreach ( $tabs as $id => $tab ) : ?>
						<div
							class="pztc-panel<?php echo $first ? ' pztc-panel--active' : ''; ?>"
							id="pztc-panel-<?php echo esc_attr( $id ); ?>"
							role="tabpanel"
						>
							<div class="pztc-panel__body">
								<?php $this->render_section_description( $id ); ?>
								<table class="form-table pztc-form-table" role="presentation">
									<?php do_settings_fields( Settings::PAGE_SLUG, $id ); ?>
								</table>
							</div>
						</div>
						<?php $first = false; endforeach; ?>
					</div>
				</div><!-- /.pztc-card--tabs -->

				<!-- ══ Save footer ═══════════════════════════════════════ -->
				<div class="pztc-save-row">
					<?php submit_button( __( 'Save Settings', 'pizzatier' ), 'primary pztc-save-btn', 'submit', false ); ?>
					<span class="pztc-save-row__version">
						<?php printf(
							/* translators: %s: version number */
							esc_html__( 'PizzaTier v%s', 'pizzatier' ),
							esc_html( PIZZATIER_VERSION )
						); ?>
					</span>
				</div>

			</form>

			<!-- ══ Credits ════════════════════════════════════════════════ -->
			<div class="pztc-credits">
				PizzaTier v<?php echo esc_html( PIZZATIER_VERSION ); ?> &mdash;
				crafted by <strong>Ryan Bishop</strong> /
				<a href="https://islandsundesign.com" target="_blank" rel="noopener">Island Sun Design</a>
			</div>

		</div><!-- /.pztc-page-wrap -->

		<?php $this->render_tab_script(); ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Section descriptions (replaces WP's built-in section title row)
	// -------------------------------------------------------------------------

	private function render_section_description( string $section_id ): void {
		$descs = [
			'pizzatier_commerce_section_general'       => __( 'Core behaviour and WooCommerce cart settings for all pizza products.', 'pizzatier' ),
			// Note: pricing engine + grid defaults descriptions are now
			// rendered on the dedicated Pricing page.
			'pizzatier_commerce_section_toppings'      => __( 'Global rules for how many toppings customers can select, required minimums, and half-pizza coverage options.', 'pizzatier' ),
			'pizzatier_commerce_section_display'       => __( 'Customise all text labels and visual styles shown to customers on pizza product pages.', 'pizzatier' ),
			'pizzatier_commerce_section_checkout'      => __( 'Control how pizza products appear in the cart and checkout, including item naming, order notes, and min/max order constraints.', 'pizzatier' ),
			'pizzatier_commerce_section_nutrition'     => __( 'Display calorie, macro, and allergen information for individual ingredients. Data is entered per-ingredient in each item\'s edit screen.', 'pizzatier' ),
			'pizzatier_commerce_section_email_summary' => __( 'Configure per-pizza order notes and how pizza configurations appear in WooCommerce order confirmation emails.', 'pizzatier' ),
			'pizzatier_commerce_section_layer_groups'  => __( 'Group ingredients (toppings, crusts, sauces, cheeses, drizzles) into named categories so templates can render grouped menus.', 'pizzatier' ),
			'pizzatier_commerce_section_advanced'      => __( 'Tax overrides, REST API controls, caching, schema markup, and developer flags.', 'pizzatier' ),
		];
		if ( ! isset( $descs[ $section_id ] ) ) {
			return;
		}
		?>
		<p class="pztc-section-desc"><?php echo esc_html( $descs[ $section_id ] ); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Inline tab script
	// -------------------------------------------------------------------------

	private function render_tab_script(): void {
		?>
		<style>
		/* Grouped table rows — subtle left border accent */
		tr.pztc-row-grouped th { border-left: 3px solid #ff6b35; padding-left: 12px; }
		/* Pricing sub-section label row */
		.pztc-table-section-label th {
			padding-top: 18px !important;
			font-size: 10px !important;
			text-transform: uppercase;
			letter-spacing: .06em;
			color: #9ca3af !important;
			font-weight: 700 !important;
			border-top: 1px solid #f0f0f0;
		}
		.pztc-table-section-label td { padding-top: 0 !important; border-top: 1px solid #f0f0f0; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Inline styles — mirrors base plugin plh-* design tokens
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		/* ── Page wrap ──────────────────────────────────────────────── */
		.pztc-page-wrap { max-width: 1100px; }

		/* ── Header ─────────────────────────────────────────────────── */
		.pztc-header {
			display: flex; align-items: center; justify-content: space-between;
			flex-wrap: wrap; gap: 16px;
			background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%);
			color: #fff; border-radius: 10px;
			padding: 22px 28px; margin-bottom: 20px;
		}
		.pztc-header__brand { display: flex; align-items: center; gap: 16px; }
		.pztc-header__icon {
			font-size: 38px !important; width: 38px !important; height: 38px !important;
			color: #ff6b35;
		}
		.pztc-header__title  { margin: 0; font-size: 24px; font-weight: 700; color: #fff; }
		.pztc-header__tagline { margin: 3px 0 0; color: #8d97a5; font-size: 13px; }
		.pztc-header__actions { display: flex; gap: 8px; flex-wrap: wrap; }
		.pztc-header__actions .button {
			display: inline-flex; align-items: center; gap: 5px;
			padding: 7px 16px; border-radius: 50px; font-size: 13px; font-weight: 600;
			border: 2px solid rgba(255,255,255,.3); color: rgba(255,255,255,.8) !important;
			background: transparent; text-decoration: none; transition: all .2s;
		}
		.pztc-header__actions .button:hover { border-color: #ff6b35; color: #fff !important; background: transparent; box-shadow: none; }
		.pztc-header__actions .dashicons { font-size: 15px !important; width: 15px !important; height: 15px !important; margin: 0; }

		/* ── Generic card ───────────────────────────────────────────── */
		.pztc-card {
			background: #fff; border: 1px solid #e0e3e7;
			border-radius: 10px; margin-bottom: 20px; overflow: hidden;
		}
		.pztc-card__head { padding: 20px 24px 0; }
		.pztc-card__title {
			margin: 0 0 4px; font-size: 16px;
			display: flex; align-items: center; gap: 8px;
		}
		.pztc-card__title .dashicons { color: #646970; font-size: 18px !important; width: 18px !important; height: 18px !important; }
		.pztc-card__subtitle { margin: 0; color: #646970; font-size: 13px; padding-bottom: 4px; }

		/* ── Tab nav ────────────────────────────────────────────────── */
		.pztc-tabnav {
			display: flex; flex-wrap: wrap; border-bottom: 2px solid #e0e3e7;
			padding: 8px 12px 0; background: #f8f9fa; gap: 2px;
		}
		.pztc-tab {
			display: inline-flex; flex-direction: column; align-items: center; gap: 3px;
			padding: 7px 10px 5px; border: none; border-bottom: 2px solid transparent;
			background: transparent; cursor: pointer; font-size: 11px; font-weight: 500;
			color: #646970; white-space: nowrap; margin-bottom: -2px; line-height: 1.2;
			transition: color .15s, border-color .15s; min-width: 64px;
			border-radius: 4px 4px 0 0;
		}
		.pztc-tab:hover { color: #1d2023; background: #eef0f2; }
		.pztc-tab--active { color: #ff6b35; border-bottom-color: #ff6b35; font-weight: 600; background: #fff; }
		.pztc-tab .dashicons {
			font-size: 18px !important; width: 18px !important; height: 18px !important;
			line-height: 1 !important; flex-shrink: 0;
		}

		/* ── Panels ─────────────────────────────────────────────────── */
		.pztc-panels { padding: 0; }
		.pztc-panel { display: none; }
		.pztc-panel--active { display: block; }
		.pztc-panel__body { padding: 20px 24px 24px; }
		.pztc-section-desc { margin: 0 0 16px; color: #646970; font-size: 13px; }

		/* ── Form table overrides ───────────────────────────────────── */
		.pztc-form-table th {
			width: 240px; padding: 14px 20px 14px 0;
			font-size: 13px; font-weight: 600; color: #1d2023; vertical-align: top;
		}
		.pztc-form-table td { padding: 10px 0 14px; vertical-align: top; }
		.pztc-form-table .description { margin-top: 6px !important; font-size: 12px !important; }

		/* Field elements — inherit from admin.css but tighten focus ring */
		.pztc-text-input,
		.pztc-select-input {
			border: 1px solid #8c8f94 !important; border-radius: 4px !important;
			font-size: 13px !important; padding: 7px 10px !important;
			transition: border-color .15s !important;
		}
		.pztc-text-input { width: 320px !important; }
		.pztc-text-input:focus,
		.pztc-select-input:focus {
			border-color: #ff6b35 !important; outline: none !important;
			box-shadow: 0 0 0 2px rgba(255,107,53,.15) !important;
		}
		.pztc-textarea-input {
			border: 1px solid #8c8f94 !important; border-radius: 4px !important;
			font-size: 13px !important; padding: 8px 10px !important;
			width: 280px !important; resize: vertical; line-height: 1.6;
		}
		.pztc-textarea-input:focus {
			border-color: #ff6b35 !important; outline: none !important;
			box-shadow: 0 0 0 2px rgba(255,107,53,.15) !important;
		}

		/* Toggle switch */
		.pztc-toggle-label { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
		.pztc-toggle-input { position: absolute; opacity: 0; width: 0; height: 0; }
		.pztc-toggle-track {
			position: relative; display: inline-block; width: 40px; height: 22px;
			background: #c3c4c7; border-radius: 11px; transition: background .2s; flex-shrink: 0;
		}
		.pztc-toggle-track::after {
			content: ''; position: absolute; top: 3px; left: 3px;
			width: 16px; height: 16px; background: #fff; border-radius: 50%;
			box-shadow: 0 1px 3px rgba(0,0,0,.25); transition: transform .2s;
		}
		.pztc-toggle-input:checked + .pztc-toggle-track { background: #ff6b35; }
		.pztc-toggle-input:checked + .pztc-toggle-track::after { transform: translateX(18px); }
		.pztc-toggle-input:focus + .pztc-toggle-track { box-shadow: 0 0 0 2px rgba(255,107,53,.3); }
		.pztc-field-desc { margin-top: 6px !important; color: #646970 !important; font-size: 12px !important; max-width: 500px; }

		/* ── Save footer ────────────────────────────────────────────── */
		.pztc-save-row {
			display: flex; align-items: center; gap: 20px;
			margin-bottom: 20px; padding: 0;
		}
		.pztc-save-btn.button-primary {
			background: #ff6b35 !important; border-color: #cf5519 !important;
			color: #fff !important; padding: 0 22px !important;
			height: 36px !important; font-size: 14px !important; font-weight: 600 !important;
			border-radius: 4px !important;
		}
		.pztc-save-btn.button-primary:hover,
		.pztc-save-btn.button-primary:focus { background: #cf5519 !important; }
		.pztc-save-row__version { font-size: 12px; color: #9ca3af; }

		/* ── Credits ────────────────────────────────────────────────── */
		.pztc-credits { padding: 4px 0 24px; font-size: 12px; color: #aaa; }
		.pztc-credits a { color: #aaa; text-decoration: none; }
		.pztc-credits a:hover { color: #ff6b35; }

		/* ── Responsive ─────────────────────────────────────────────── */
		@media ( max-width: 782px ) {
			.pztc-header { flex-direction: column; align-items: flex-start; }
			.pztc-text-input { width: 100% !important; max-width: 320px !important; }
			.pztc-form-table th { width: auto; }
		}
		</style>
		<?php
	}
}
