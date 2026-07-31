<?php
/**
 * Pizza Configurator — standalone meta box on the WooCommerce product edit screen.
 *
 * UI model: mirrors PizzaTier's own shortcode generator — layer items are
 * shown as thumbnail cards grouped by type (Crusts / Sauces / Cheeses /
 * Toppings / Drizzles / Cuts).  For each type the admin picks exactly ONE
 * default (pre-selected) item.  Any item can additionally be toggled as
 * "available" in the builder.
 *
 * Live preview: fires window.PizzaTierAPI.renderPizza() (the PizzaTier JS
 * API) whenever a default layer card is changed and renders the result into
 * the preview stage inside the meta box.
 *
 * Data stored:
 *   _pizzatier_commerce_builder_template   string  PizzaTier template slug (e.g. 'colorbox')
 *   _pizzatier_commerce_builder_position   string  before_cart | after_title | after_summary
 *   _pizzatier_commerce_enabled_layers     array   string[] of "type:postID" or just postID
 *   _pizzatier_commerce_default_layers     array   [ 'crust'=>postID, 'sauce'=>postID, … ]
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

namespace PizzaTier\Commerce\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductTab {

	// CPT slug suffixes in display order.
	private const LAYER_TYPES = [
		'crusts'   => 'Crusts',
		'sauces'   => 'Sauces',
		'cheeses'  => 'Cheeses',
		'toppings' => 'Toppings',
		'drizzles' => 'Drizzles',
		'cuts'     => 'Cuts',
	];

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		// Standalone meta boxes — registered on 'add_meta_boxes'.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );

		// Keep the WC tab stub so show_if_pizza CSS still drives panel visibility.
		add_filter( 'woocommerce_product_data_tabs',   [ $this, 'register_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ $this, 'render_wc_stub' ] );

		// Save.
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_meta' ] );

		// Assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Meta box registration (two boxes: Configurator + Price Grid)
	// -------------------------------------------------------------------------

	public function register_meta_boxes(): void {
		$this->register_configurator_meta_box();
		$this->register_price_grid_meta_box();
	}

	/**
	 * Pizza Configurator meta box — builder settings, layer config, pricing mode.
	 * Positioned immediately below the product description editor.
	 */
	private function register_configurator_meta_box(): void {
		add_meta_box(
			'pizzatier_commerce_pizza_configurator',
			'<span class="dashicons dashicons-pizza" style="color:#e8692a;margin-right:6px;vertical-align:middle;"></span>' .
			__( 'Pizza Configurator', 'pizzatier' ),
			[ $this, 'render_meta_box' ],
			'product',
			'normal',
			'high'
		);
	}

	/**
	 * Price Grid meta box — the default/fallback size/coverage price table editor.
	 * Positioned below the Pizza Configurator meta box.
	 *
	 * Individual layer items (toppings, crusts, etc.) can override these prices
	 * via their own Pricing Grid meta box (LayerGridMetaBox — Phase 3).
	 */
	private function register_price_grid_meta_box(): void {
		add_meta_box(
			'pizzatier_commerce_price_grid',
			'<span class="dashicons dashicons-grid-view" style="color:#ff6b35;margin-right:6px;vertical-align:middle;"></span>'
				. __( 'Default / Fallback Pricing Grid', 'pizzatier' ),
			[ $this, 'render_price_grid_meta_box' ],
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Price Grid meta box.
	 * Only shows content when product type is Pizza.
	 *
	 * @param \WP_Post $post
	 */
	public function render_price_grid_meta_box( \WP_Post $post ): void {
		// Determine current product type.
		$current_type = '';
		if ( $post->ID ) {
			$p = wc_get_product( $post->ID );
			if ( $p ) {
				$current_type = $p->get_type();
			}
		}
		?>
		<div id="pztc-pricegrid-placeholder" <?php echo 'pizza' === $current_type ? 'style="display:none"' : ''; ?>>
			<p class="pztc-ph-msg" style="padding:12px 0;">
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'Select "Pizza" as the product type to configure the price grid.', 'pizzatier' ); ?>
			</p>
		</div>
		<div id="pztc-pricegrid-body" <?php echo 'pizza' !== $current_type ? 'style="display:none"' : ''; ?>>

			<!-- Help notice -->
			<div class="pztc-fallback-grid-notice" style="
				display:flex;align-items:flex-start;gap:8px;
				padding:9px 12px;margin:0 0 14px;
				background:#ff6b351a;border-left:3px solid #ff6b35;border-radius:3px;
				font-size:13px;line-height:1.5;
			">
				<span class="dashicons dashicons-info-outline" style="color:#ff6b35;margin-top:1px;flex-shrink:0;"></span>
				<span>
					<?php esc_html_e(
						'These prices apply to any layer that does not have its own custom pricing grid set. To set per-ingredient prices, edit the individual topping, crust, sauce, etc. and configure its Pricing Grid.',
						'pizzatier'
					); ?>
				</span>
			</div>

			<?php $this->render_fallback_layer_list( $post->ID ); ?>

			<?php
			$grid_renderer = new \PizzaTier\Commerce\PriceGrid\GridRenderer(
				new \PizzaTier\Commerce\PriceGrid\Grid()
			);
			$grid_renderer->render( $post->ID );
			?>
		</div>
		<?php
	}

	/**
	 * Render a compact summary inside the fallback grid meta box showing which
	 * enabled layers already have custom pricing vs. which still use the fallback.
	 *
	 * Gives admins at-a-glance visibility without leaving the product screen.
	 *
	 * @param int $product_id
	 */
	private function render_fallback_layer_list( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}

		$enabled_raw = get_post_meta( $product_id, '_pizzatier_enabled_layers', true );
		$enabled_ids = is_array( $enabled_raw ) ? array_map( 'absint', array_filter( $enabled_raw ) ) : [];

		if ( empty( $enabled_ids ) ) {
			return;
		}

		$grid          = new \PizzaTier\Commerce\PriceGrid\Grid();
		$using_custom  = [];
		$using_fallback = [];

		foreach ( $enabled_ids as $layer_id ) {
			if ( $layer_id <= 0 ) {
				continue;
			}
			$post = get_post( $layer_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$name = get_the_title( $post );
			if ( $grid->has_layer_grid( $layer_id ) ) {
				$using_custom[] = $name;
			} else {
				$using_fallback[] = $name;
			}
		}

		if ( empty( $using_custom ) && empty( $using_fallback ) ) {
			return;
		}
		?>
		<div class="pztc-fallback-layer-summary" style="margin:0 0 14px;font-size:12px;">
			<?php if ( ! empty( $using_fallback ) ) : ?>
				<p style="margin:0 0 4px;color:#d97706;font-weight:600;">
					<span class="dashicons dashicons-info-outline" style="font-size:14px;vertical-align:middle;"></span>
					<?php esc_html_e( 'Using fallback prices:', 'pizzatier' ); ?>
					<span style="font-weight:400;"><?php echo esc_html( implode( ', ', $using_fallback ) ); ?></span>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $using_custom ) ) : ?>
				<p style="margin:0;color:#16a34a;font-weight:600;">
					<span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle;"></span>
					<?php esc_html_e( 'Custom prices set:', 'pizzatier' ); ?>
					<span style="font-weight:400;"><?php echo esc_html( implode( ', ', $using_custom ) ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// WC tab stub (keeps show_if_pizza CSS working for the WC panels area)
	// -------------------------------------------------------------------------

	public function register_tab( array $tabs ): array {
		$tabs['pizza_configurator'] = [
			'label'    => __( 'Pizza Configurator', 'pizzatier' ),
			'target'   => 'pizzatier_configurator_panel',
			'class'    => [ 'show_if_pizza' ],
			'priority' => 60,
		];
		return $tabs;
	}

	public function render_wc_stub(): void {
		// Empty panel — the real UI is in the standalone meta box above.
		echo '<div id="pizzatier_configurator_panel" class="panel woocommerce_options_panel hidden"></div>';
	}

	public function render_meta_box( \WP_Post $post ): void {
		$pid              = $post->ID;
		$builder_template = (string) get_post_meta( $pid, '_pizzatier_builder_template', true );
		$builder_position = (string) get_post_meta( $pid, '_pizzatier_builder_position', true ) ?: 'before_cart';
		$enabled_raw      = get_post_meta( $pid, '_pizzatier_enabled_layers', true );
		$defaults_raw     = get_post_meta( $pid, '_pizzatier_default_layers',  true );

		$enabled_ids = is_array( $enabled_raw )  ? array_map( 'strval', $enabled_raw )  : [];
		$defaults    = is_array( $defaults_raw ) ? $defaults_raw : [];

		// Determine current product type for initial show/hide.
		$current_type = '';
		if ( $pid ) {
			$p = wc_get_product( $pid );
			if ( $p ) { $current_type = $p->get_type(); }
		}

		$pizzatier_templates = $this->get_pizzatier_templates();
		$layer_data       = $this->get_all_layer_data();

		wp_nonce_field( 'pizzatier_commerce_save_meta', 'pizzatier_commerce_meta_nonce' );
		?>
		<div id="pztc-mb-placeholder" <?php echo 'pizza' === $current_type ? 'style="display:none"' : ''; ?>>
			<p class="pztc-ph-msg">
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'Select "Pizza" as the product type to configure the builder.', 'pizzatier' ); ?>
			</p>
		</div>

		<div id="pztc-mb-body" <?php echo 'pizza' !== $current_type ? 'style="display:none"' : ''; ?>>

			<!-- ── Header ─────────────────────────────────── -->
			<div class="pztc-mb-hdr">
				<div class="pztc-mb-hdr__text">
					<h2><?php esc_html_e( 'Pizza Configurator', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Configure the builder, set default layers, and restrict available toppings for this product.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<!-- ── Builder settings ───────────────────────── -->
			<div class="pztc-mb-section">
				<h3 class="pztc-mb-section__title">
					<span class="dashicons dashicons-admin-appearance"></span>
					<?php esc_html_e( 'Builder Settings', 'pizzatier' ); ?>
				</h3>
				<div class="pztc-mb-fields">

					<div class="pztc-mb-field">
						<label for="pizzatier_commerce_builder_template">
							<?php esc_html_e( 'PizzaTier Template', 'pizzatier' ); ?>
							<span class="pztc-req">*</span>
						</label>
						<div class="pztc-mb-field__ctrl">
							<?php if ( empty( $pizzatier_templates ) ) : ?>
								<p class="pztc-notice pztc-notice--warn">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'No PizzaTier templates found. Make sure PizzaTier is active and its templates folder is intact.', 'pizzatier' ); ?>
								</p>
							<?php else : ?>
								<select id="pizzatier_commerce_builder_template" name="pizzatier_commerce_builder_template">
									<option value=""><?php esc_html_e( '— Select a template —', 'pizzatier' ); ?></option>
									<?php foreach ( $pizzatier_templates as $tpl_slug => $tpl_name ) : ?>
										<option value="<?php echo esc_attr( $tpl_slug ); ?>" <?php selected( $builder_template, $tpl_slug ); ?>>
											<?php echo esc_html( $tpl_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="pztc-field-desc">
									<span class="dashicons dashicons-info-outline"></span>
									<?php printf(
										/* translators: %s: value inserted into the message. */
										wp_kses_post( __( 'Choose the visual theme for this product\'s pizza builder. The template controls the layout and appearance of the builder shown to customers. You can change the active template globally under <a href="%s" target="_blank">PizzaTier &rarr; Template</a>.', 'pizzatier' ) ),
										esc_url( admin_url( 'admin.php?page=pizzatier-template' ) )
									); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<div class="pztc-mb-field">
						<label for="pizzatier_commerce_builder_position"><?php esc_html_e( 'Builder Position', 'pizzatier' ); ?></label>
						<div class="pztc-mb-field__ctrl">
							<select id="pizzatier_commerce_builder_position" name="pizzatier_commerce_builder_position">
								<option value="before_cart"   <?php selected( $builder_position, 'before_cart' ); ?>><?php esc_html_e( 'Above add-to-cart form', 'pizzatier' ); ?></option>
								<option value="after_title"   <?php selected( $builder_position, 'after_title' ); ?>><?php esc_html_e( 'After product title', 'pizzatier' ); ?></option>
								<option value="after_summary" <?php selected( $builder_position, 'after_summary' ); ?>><?php esc_html_e( 'After product summary', 'pizzatier' ); ?></option>
							</select>
						</div>
					</div>

				</div><!-- .pztc-mb-fields -->
			</div><!-- builder settings -->

			<!-- ── Layer configurator ─────────────────────── -->
			<div class="pztc-mb-section pztc-mb-section--layers">
				<h3 class="pztc-mb-section__title">
					<span class="dashicons dashicons-category"></span>
					<?php esc_html_e( 'Layer Configuration', 'pizzatier' ); ?>
				</h3>
				<p class="pztc-mb-section__desc">
					<?php esc_html_e( 'For each layer type, choose a default item (shown on load) and check which items customers can select. Unchecked items are hidden from the builder.', 'pizzatier' ); ?>
				</p>

				<?php if ( empty( $layer_data ) ) : ?>
					<p class="pztc-notice pztc-notice--info">
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'No layers found. Add Crusts, Sauces, Toppings etc. in PizzaTier first.', 'pizzatier' ); ?>
					</p>
				<?php else : ?>

					<!-- Two-column layout: tabs+cards left, live preview right -->
					<div class="pztc-lc-layout">

						<!-- Left: type tabs + card grids -->
						<div class="pztc-lc-left">

							<!-- Type tab nav -->
							<ul class="pztc-lc-nav" role="tablist">
								<?php $first = true; foreach ( self::LAYER_TYPES as $type_plural => $type_label ) :
									if ( empty( $layer_data[ $type_plural ] ) ) continue;
									$count = count( $layer_data[ $type_plural ] );
									$tab_id = 'pztc-lc-tab-' . $type_plural;
									?>
									<li>
										<button
											type="button"
											role="tab"
											id="<?php echo esc_attr( $tab_id ); ?>"
											class="pztc-lc-nav__btn<?php echo $first ? ' pztc-lc-nav__btn--active' : ''; ?>"
											data-tab="<?php echo esc_attr( $type_plural ); ?>"
											aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
										>
											<?php echo esc_html( $type_label ); ?>
											<span class="pztc-lc-nav__count"><?php echo esc_html( $count ); ?></span>
										</button>
									</li>
									<?php $first = false; endforeach; ?>
							</ul>

							<!-- Card panels (one per type) -->
							<?php $first = true; foreach ( self::LAYER_TYPES as $type_plural => $type_label ) :
								if ( empty( $layer_data[ $type_plural ] ) ) continue;
								// Singular type key used in field names and REST API.
								$type_singular = rtrim( $type_plural, 's' ); // crusts→crust, toppings→topping, etc.
								// Special case: drizzles→drizzle (already works), cheeses→cheese
								if ( $type_plural === 'cheeses' ) $type_singular = 'cheese';
								// Toppings support multi-select defaults; all other types are single-select.
								$is_topping_panel  = ( $type_plural === 'toppings' );
								$default_topping_ids = $is_topping_panel
									? array_map( 'strval', (array) ( $defaults['toppings'] ?? [] ) )
									: [];
								$default_id = $is_topping_panel ? '' : (string) ( $defaults[ $type_singular ] ?? '' );
								?>
								<div
									class="pztc-lc-panel<?php echo $first ? ' pztc-lc-panel--active' : ''; ?>"
									id="pztc-lc-panel-<?php echo esc_attr( $type_plural ); ?>"
									role="tabpanel"
									data-type="<?php echo esc_attr( $type_singular ); ?>"
									data-type-plural="<?php echo esc_attr( $type_plural ); ?>"
								>
									<div class="pztc-lc-panel__toolbar">
										<span class="pztc-lc-panel__hint">
											<?php
											if ( $is_topping_panel ) {
												esc_html_e( 'Click ★ to pre-select multiple defaults. Check box to make available.', 'pizzatier' );
											} elseif ( in_array( $type_plural, [ 'crusts', 'sauces', 'cheeses' ], true ) ) {
												esc_html_e( 'Click to set as default (shown on load). Check box to make available.', 'pizzatier' );
											} else {
												esc_html_e( 'Click to set as default. Check boxes to allow in the builder.', 'pizzatier' );
											}
											?>
										</span>
										<label class="pztc-lc-panel__select-all">
											<input type="checkbox" class="js-select-all" data-type="<?php echo esc_attr( $type_plural ); ?>" />
											<?php esc_html_e( 'All', 'pizzatier' ); ?>
										</label>
									</div>

									<div class="pztc-lc-grid" id="pztc-grid-<?php echo esc_attr( $type_plural ); ?>">
										<?php foreach ( $layer_data[ $type_plural ] as $layer ) :
											$lid      = (string) $layer['id'];
											$lname    = $layer['name'];
											$lthumb   = $layer['thumb'];
											$lslug    = $layer['slug'];
											$is_def   = $is_topping_panel
												? in_array( $lid, $default_topping_ids, true )
												: ( $lid === $default_id );
											$is_avail = empty( $enabled_ids ) || in_array( $lid, $enabled_ids, true );
											?>
											<div
												class="pztc-lc-card<?php echo $is_def ? ' pztc-lc-card--default' : ''; ?><?php echo $is_avail ? ' pztc-lc-card--enabled' : ''; ?>"
												data-id="<?php echo esc_attr( $lid ); ?>"
												data-slug="<?php echo esc_attr( $lslug ); ?>"
												data-type="<?php echo esc_attr( $type_singular ); ?>"
												data-type-plural="<?php echo esc_attr( $type_plural ); ?>"
												data-name="<?php echo esc_attr( $lname ); ?>"
											>
												<!-- Default selector: checkbox (toppings) or radio (all other types) -->
												<?php if ( $is_topping_panel ) : ?>
													<input
														type="checkbox"
														name="pizzatier_commerce_default_toppings[]"
														value="<?php echo esc_attr( $lid ); ?>"
														class="pztc-lc-card__radio"
														<?php checked( $is_def ); ?>
													/>
												<?php else : ?>
													<input
														type="radio"
														name="pizzatier_commerce_default_layers[<?php echo esc_attr( $type_singular ); ?>]"
														value="<?php echo esc_attr( $lid ); ?>"
														class="pztc-lc-card__radio"
														<?php checked( $is_def ); ?>
													/>
												<?php endif; ?>

												<!-- Available checkbox -->
												<input
													type="checkbox"
													name="pizzatier_commerce_enabled_layers[]"
													value="<?php echo esc_attr( $lid ); ?>"
													class="pztc-lc-card__avail"
													<?php checked( $is_avail ); ?>
													title="<?php esc_attr_e( 'Available in builder', 'pizzatier' ); ?>"
												/>

												<!-- Default star badge -->
												<span class="pztc-lc-card__star" title="<?php esc_attr_e( 'Default', 'pizzatier' ); ?>">★</span>

												<!-- Thumbnail -->
												<span class="pztc-lc-card__img-wrap">
													<?php if ( $lthumb ) : ?>
														<img src="<?php echo esc_url( $lthumb ); ?>" alt="<?php echo esc_attr( $lname ); ?>" loading="lazy" />
													<?php else : ?>
														<span class="pztc-lc-card__img-placeholder">
															<span class="dashicons dashicons-format-image"></span>
														</span>
													<?php endif; ?>
												</span>

												<span class="pztc-lc-card__name"><?php echo esc_html( $lname ); ?></span>

											</div><!-- .pztc-lc-card -->
										<?php endforeach; ?>
									</div><!-- .pztc-lc-grid -->

								</div><!-- .pztc-lc-panel -->
								<?php $first = false; endforeach; ?>

						</div><!-- .pztc-lc-left -->

						<!-- Right: live preview -->
						<div class="pztc-lc-right">
							<div class="pztc-lc-preview" id="pztc-lc-preview">
								<div class="pztc-lc-preview__hdr">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e( 'Live Preview', 'pizzatier' ); ?>
									<span class="pztc-lc-preview__spinner" id="pztc-preview-spinner" style="display:none;"></span>
								</div>
								<div class="pztc-lc-preview__stage" id="pztc-preview-stage">
									<p class="pztc-lc-preview__empty">
										<?php esc_html_e( 'Select default layers to preview the pizza.', 'pizzatier' ); ?>
									</p>
								</div>
								<div class="pztc-lc-preview__summary" id="pztc-preview-summary"></div>
							</div>
						</div><!-- .pztc-lc-right -->

					</div><!-- .pztc-lc-layout -->

				<?php endif; ?>
			</div><!-- layer configurator -->

			<!-- ── Per-product Pricing Engine Override ──── -->
			<?php $this->render_pricing_override( $post->ID ); ?>

		</div><!-- #pztc-mb-body -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save_meta( int $post_id ): void {
		if (
			! isset( $_POST['pizzatier_commerce_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pizzatier_commerce_meta_nonce'] ) ), 'pizzatier_commerce_save_meta' )
		) {
			return;
		}

		if ( sanitize_key( wp_unslash( $_POST['product-type'] ?? '' ) ) !== 'pizza' ) {
			return;
		}

		// Builder template.
		// Template slug (e.g. 'colorbox', 'metro') — sanitize_key keeps only safe chars.
		$tpl_slug = sanitize_key( $_POST['pizzatier_commerce_builder_template'] ?? '' );
		update_post_meta( $post_id, '_pizzatier_builder_template', $tpl_slug );

		// Builder position.
		$allowed = [ 'before_cart', 'after_title', 'after_summary' ];
		$pos = sanitize_key( $_POST['pizzatier_commerce_builder_position'] ?? 'before_cart' );
		update_post_meta( $post_id, '_pizzatier_builder_position', in_array( $pos, $allowed, true ) ? $pos : 'before_cart' );

		// Enabled (available) layers — string IDs.
		$raw_enabled = is_array( $_POST['pizzatier_commerce_enabled_layers'] ?? null )
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
				? (array) wp_unslash( $_POST['pizzatier_commerce_enabled_layers'] ) : [];
		update_post_meta( $post_id, '_pizzatier_enabled_layers', array_values( array_filter( array_map( 'sanitize_text_field', $raw_enabled ) ) ) );

		// Default layers — [ type_singular => post_id ] map (all types except toppings).
		$raw_defaults = is_array( $_POST['pizzatier_commerce_default_layers'] ?? null )
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
				? (array) wp_unslash( $_POST['pizzatier_commerce_default_layers'] ) : [];
		$defaults = [];
		foreach ( $raw_defaults as $type => $id ) {
			$type = sanitize_key( $type );
			$id   = absint( $id );
			if ( $type && $id ) {
				$defaults[ $type ] = $id;
			}
		}
		// Toppings: multi-select checkboxes posted as pizzatier_commerce_default_toppings[].
		$raw_def_toppings = is_array( $_POST['pizzatier_commerce_default_toppings'] ?? null )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
			? (array) wp_unslash( $_POST['pizzatier_commerce_default_toppings'] ) : [];
		$tip_ids = array_values( array_filter( array_map( 'absint', $raw_def_toppings ) ) );
		if ( $tip_ids ) {
			$defaults['toppings'] = $tip_ids;
		} else {
			unset( $defaults['toppings'], $defaults['topping'] ); // clean up legacy key too
		}
		update_post_meta( $post_id, '_pizzatier_default_layers', $defaults );

		// Per-product pricing mode override.
		$allowed_modes = [ '', 'addon_per_layer', 'flat_per_size', 'highest_wins', 'tiered_by_count', 'free_first_n', 'bundle' ];
		$product_mode = sanitize_key( $_POST['pizzatier_commerce_pricing_mode'] ?? '' );
		$product_mode = in_array( $product_mode, $allowed_modes, true ) ? $product_mode : '';
		if ( '' === $product_mode ) {
			delete_post_meta( $post_id, '_pizzatier_pricing_mode' );
		} else {
			update_post_meta( $post_id, '_pizzatier_pricing_mode', $product_mode );
		}

		// Price grid.
		if ( ! empty( $_POST['pizzatier_commerce_price_grid'] ) && is_array( $_POST['pizzatier_commerce_price_grid'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
				$result = ( new \PizzaTier\Commerce\PriceGrid\Grid() )->save( $post_id, wp_unslash( $_POST['pizzatier_commerce_price_grid'] ) );
			if ( is_wp_error( $result ) ) {
				set_transient( 'pizzatier_commerce_grid_save_error_' . $post_id, $result->get_error_message(), 60 );
			}
		}

		// Flat price grid (non-fraction layer types: crust, cut, topping, …).
		$grid_model = new \PizzaTier\Commerce\PriceGrid\Grid();
		if ( ! empty( $_POST['pizzatier_commerce_flat_grid'] ) && is_array( $_POST['pizzatier_commerce_flat_grid'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
			$flat_data = wp_unslash( $_POST['pizzatier_commerce_flat_grid'] );
			// The flat grid sizes always mirror the main grid sizes for consistency.
			if ( empty( $flat_data['sizes'] ) ) {
				$main_grid        = $grid_model->get( $post_id );
				$flat_data['sizes'] = $main_grid ? $main_grid['sizes'] : $grid_model->default_sizes();
			}
			$flat_result = $grid_model->save_flat( $post_id, $flat_data );
			if ( is_wp_error( $flat_result ) ) {
				set_transient( 'pizzatier_commerce_flat_grid_save_error_' . $post_id, $flat_result->get_error_message(), 60 );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) return;

		$url = PIZZATIER_PLUGIN_URL;
		$ver = PIZZATIER_VERSION;

		wp_enqueue_style( 'pizzatier-commerce-admin', $url . 'assets/css/admin.css', [ 'woocommerce_admin_styles' ], $ver );

		wp_enqueue_script(
			'pizzatier-commerce-admin-product-tab',
			$url . 'assets/js/admin-product-tab.js',
			[ 'jquery', 'wp-api-fetch', 'wc-admin-meta-boxes' ],
			$ver, true
		);

		wp_enqueue_script(
			'pizzatier-commerce-price-grid-import-export',
			$url . 'assets/js/price-grid-import-export.js',
			[ 'jquery', 'pizzatier-commerce-admin-product-tab' ],
			$ver, true
		);

		// Pull the saved defaults so JS can seed the preview on page load.
		$defaults_raw = get_post_meta( (int) get_the_ID(), '_pizzatier_default_layers', true );
		$defaults     = is_array( $defaults_raw ) ? $defaults_raw : [];

		// Build slug map for defaults so JS can seed the preview on page load.
		// Toppings are stored as an array of IDs under 'toppings'; resolve each to a slug.
		$default_slugs = [];
		foreach ( $defaults as $type_singular => $value ) {
			if ( $type_singular === 'toppings' ) {
				$topping_slugs = [];
				foreach ( (array) $value as $tid ) {
					$s = get_post_field( 'post_name', (int) $tid );
					if ( $s ) { $topping_slugs[] = $s; }
				}
				if ( $topping_slugs ) { $default_slugs['toppings'] = $topping_slugs; }
			} else {
				$slug = get_post_field( 'post_name', (int) $value );
				if ( $slug ) { $default_slugs[ $type_singular ] = $slug; }
			}
		}

		wp_localize_script( 'pizzatier-commerce-admin-product-tab', 'pizzatier_commerceAdminData', [
			'nonce'            => wp_create_nonce( 'pizzatier_commerce_admin' ),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'restRoot'         => rest_url(),
			'restNonce'        => wp_create_nonce( 'wp_rest' ),
			'productId'        => (int) get_the_ID(),
			'currencySymbol'   => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
			'defaultSlugs'     => $default_slugs,
			'defaultSizes'     => ( new \PizzaTier\Commerce\PriceGrid\Grid() )->default_sizes(),
			'defaultFractions' => ( new \PizzaTier\Commerce\PriceGrid\Grid() )->default_fractions(),
			'defaultFlatLayerTypes' => ( new \PizzaTier\Commerce\PriceGrid\Grid() )->default_flat_layer_types(),
			'i18n'             => [
				'noTemplate'        => __( 'Please select a PizzaTier template before saving.', 'pizzatier' ),
				'previewEmpty'      => __( 'Select default layers to preview the pizza.', 'pizzatier' ),
				'previewError'      => __( 'Preview unavailable.', 'pizzatier' ),
				'newSize'           => __( 'New Size', 'pizzatier' ),
				'newFraction'       => __( 'New Coverage', 'pizzatier' ),
				'confirmRemoveRow'  => __( 'Remove this size row?', 'pizzatier' ),
				'confirmRemoveCol'  => __( 'Remove this coverage column?', 'pizzatier' ),
				'importSuccess'     => __( 'Grid imported. Review values, then save.', 'pizzatier' ),
				'importError'       => __( 'Could not read CSV file.', 'pizzatier' ),
				'exportFailed'      => __( 'Export failed.', 'pizzatier' ),
				'flatTypePrompt'    => __( 'Enter layer type name (e.g. topping, extra):', 'pizzatier' ),
				'flatTypeDuplicate' => __( 'That layer type row already exists.', 'pizzatier' ),
				'copyCsvSuccess'    => __( 'Grid CSV copied to clipboard.', 'pizzatier' ),
				'copyCsvFail'       => __( 'Could not copy to clipboard. Try Export CSV instead.', 'pizzatier' ),
				'pasteCsvSuccess'   => __( 'Grid applied from pasted CSV. Review and save.', 'pizzatier' ),
				'pasteCsvError'     => __( 'CSV parse error: ', 'pizzatier' ),
				'copyProductNone'   => __( 'Please select a product.', 'pizzatier' ),
				'copyProductSuccess'=> __( 'Grid copied from product. Review and save.', 'pizzatier' ),
				'copyProductError'  => __( 'Could not load that product\'s grid.', 'pizzatier' ),
				'setAllSuccess'     => __( 'All cells updated.', 'pizzatier' ),
				'setAllNoValue'     => __( 'Please enter a price.', 'pizzatier' ),
				'confirmCopyProduct'=> __( 'This will replace the current grid with the selected product\'s grid. Continue?', 'pizzatier' ),
				'loadingProducts'   => __( '— Loading… —', 'pizzatier' ),
				'noGridProducts'    => __( '— No other Pizza products found —', 'pizzatier' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Per-product Pricing Engine Override UI
	// -------------------------------------------------------------------------

	/**
	 * Render a card-grid pricing mode selector specific to this product.
	 * "Use global setting" (empty value) leaves calculation to the Settings page.
	 */
	private function render_pricing_override( int $product_id ): void {
		$current = (string) get_post_meta( $product_id, '_pizzatier_pricing_mode', true );

		$modes = [
			''                => [
				'icon'  => '🌐',
				'title' => __( 'Use global setting', 'pizzatier' ),
				'desc'  => __( 'Inherits the pricing mode from PizzaTier → Settings → Pricing Engine.', 'pizzatier' ),
				'color' => '#6b7280',
			],
			'addon_per_layer' => [
				'icon'  => '🧱',
				'title' => __( 'Add-on per layer', 'pizzatier' ),
				'desc'  => __( 'Each layer adds its grid price (Size × Coverage) to the base price.', 'pizzatier' ),
				'color' => '#ff6b35',
			],
			'flat_per_size' => [
				'icon'  => '📐',
				'title' => __( 'Flat per size', 'pizzatier' ),
				'desc'  => __( 'Grid "Whole" column for the chosen size is a single flat add-on.', 'pizzatier' ),
				'color' => '#3b82f6',
			],
			'highest_wins' => [
				'icon'  => '🏆',
				'title' => __( 'Highest layer wins', 'pizzatier' ),
				'desc'  => __( 'Only the most expensive layer\'s price is added to the base.', 'pizzatier' ),
				'color' => '#8b5cf6',
			],
			'tiered_by_count' => [
				'icon'  => '🪜',
				'title' => __( 'Tiered by count', 'pizzatier' ),
				'desc'  => __( 'Price tier determined by topping count. Grid columns: Tier1, Tier2, Tier3…', 'pizzatier' ),
				'color' => '#0ea5e9',
			],
			'free_first_n' => [
				'icon'  => '🎁',
				'title' => __( 'Free first N', 'pizzatier' ),
				'desc'  => __( 'First N toppings free, rest priced from the grid.', 'pizzatier' ),
				'color' => '#10b981',
			],
			'bundle' => [
				'icon'  => '📦',
				'title' => __( 'Bundle / fixed', 'pizzatier' ),
				'desc'  => __( 'Product price is the total — no grid add-ons applied.', 'pizzatier' ),
				'color' => '#6b7280',
			],
		];
		?>
		<div class="pztc-section pztc-section--pricing-override">
			<div class="pztc-section__heading">
				<span class="dashicons dashicons-chart-bar"></span>
				<h3><?php esc_html_e( 'Pricing Engine', 'pizzatier' ); ?></h3>
				<p class="pztc-section__description">
					<?php esc_html_e( 'Override the global pricing engine for this product only. "Use global setting" inherits from PizzaTier Settings.', 'pizzatier' ); ?>
				</p>
			</div>

			<input type="hidden" id="pizzatier_commerce_pricing_mode_product" name="pizzatier_commerce_pricing_mode" value="<?php echo esc_attr( $current ); ?>">

			<div class="pztc-prod-mode-grid" id="pztc-prod-mode-grid">
				<?php foreach ( $modes as $mode_key => $mode ) :
					$selected = ( $current === $mode_key );
					?>
					<div class="pztc-prod-mode-card<?php echo $selected ? ' pztc-prod-mode-card--selected' : ''; ?>"
						 data-mode="<?php echo esc_attr( $mode_key ); ?>"
						 data-color="<?php echo esc_attr( $mode['color'] ); ?>"
						 role="button" tabindex="0"
						 aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>">
						<span class="pztc-prod-mode-card__icon"><?php echo wp_kses( $mode['icon'], pzt_card_allowed_html() ); ?></span>
						<span class="pztc-prod-mode-card__title"><?php echo esc_html( $mode['title'] ); ?></span>
						<span class="pztc-prod-mode-card__desc"><?php echo esc_html( $mode['desc'] ); ?></span>
						<span class="pztc-prod-mode-card__check">✓</span>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Contextual helper text changes based on mode -->
			<p class="pztc-pricing-mode-hint" id="pztc-pricing-mode-hint" style="margin-top:10px;font-size:12px;color:#646970;"></p>
		</div>


		<?php // JS enqueued via wp_enqueue_script — see enqueue_assets() ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	/**
	 * Return available PizzaTier template slugs → display names.
	 *
	 * Reads the live template directory via TemplateLoader so the list stays
	 * in sync with whatever templates are actually installed (including any
	 * custom templates added via the pizzatier_template_dirs filter).
	 *
	 * @return array<string,string>  [ slug => display_name ]
	 */
	private function get_pizzatier_templates(): array {
		if ( ! class_exists( 'PizzaTier\\Template\\TemplateLoader' ) ) {
			return [];
		}
		$loader    = new \PizzaTier\Template\TemplateLoader();
		$available = $loader->get_available_templates();
		$out       = [];
		foreach ( $available as $slug => $info ) {
			$out[ $slug ] = $info['name'] ?? ucfirst( $slug );
		}
		return $out;
	}

	/**
	 * Return all layer items grouped by their plural type key.
	 *
	 * Each item: [ 'id' => string, 'name' => string, 'slug' => string, 'thumb' => string ]
	 *
	 * Image resolution order:
	 *   1. SCF field  {type_singular}_image   (list/menu image)
	 *   2. SCF field  {type_singular}_layer_image (transparent stack image)
	 *   3. WordPress featured image (thumbnail size)
	 */
	private function get_all_layer_data(): array {
		$groups = [];

		foreach ( self::LAYER_TYPES as $type_plural => $type_label ) {
			$cpt = 'pizzatier_' . $type_plural;

			if ( ! post_type_exists( $cpt ) ) {
				continue;
			}

			$query = new \WP_Query( [
				'post_type'      => $cpt,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			] );

			if ( empty( $query->posts ) ) {
				continue;
			}

			// Derive singular from plural (same logic used on the front end).
			$singular = rtrim( $type_plural, 's' );
			if ( $type_plural === 'cheeses' ) $singular = 'cheese';

			$items = [];
			foreach ( $query->posts as $p ) {
				$items[] = [
					'id'    => (string) $p->ID,
					'name'  => $p->post_title,
					'slug'  => $p->post_name,
					'thumb' => $this->resolve_thumb( $p->ID, $singular ),
				];
			}

			$groups[ $type_plural ] = $items;
		}

		return $groups;
	}

	/**
	 * Resolve the best thumbnail URL for a layer post.
	 * Priority: SCF list image → SCF layer image → WP featured image.
	 */
	private function resolve_thumb( int $post_id, string $type_singular ): string {
		// SCF list/menu image (e.g. topping_image, sauce_image).
		if ( function_exists( 'get_field' ) ) {
			$url = get_field( $type_singular . '_image', $post_id );
			if ( ! $url ) {
				$url = get_field( $type_singular . '_layer_image', $post_id );
			}
			if ( $url ) {
				// get_field on an image field may return an array or a URL string.
				if ( is_array( $url ) ) {
					$url = $url['url'] ?? ( $url['sizes']['thumbnail'] ?? '' );
				}
				return (string) $url;
			}
		}

		// WP featured image fallback.
		return (string) get_the_post_thumbnail_url( $post_id, 'thumbnail' );
	}
}
