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


}
