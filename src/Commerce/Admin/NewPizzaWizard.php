<?php
/**
 * PizzaTier — New Pizza Wizard
 *
 * A guided step-by-step page that takes an admin from zero to a fully
 * published WooCommerce pizza product:
 *
 *   Step 1 — Product basics     (title, short description, image)
 *   Step 2 — Preset (optional)  (pick a pizza preset to seed layers)
 *   Step 3 — Layer config       (builder template + default layers)
 *   Step 4 — Price grid         (size/fraction pricing)
 *   Step 5 — Review & Publish   (summary + one-click publish)
 *
 * All data is held in a PHP session-like transient keyed to the current
 * user so the wizard survives page reloads. On publish, the wizard creates
 * the WooCommerce product, sets all meta, and redirects to the edit screen.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewPizzaWizard {

	const PAGE_SLUG   = 'pizzatier-new-pizza';
	const TRANS_KEY   = 'pizzatier_commerce_wizard_data_';   // + user_id
	const TRANS_TTL   = 3600;                     // 1 hour

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'admin_post_pizzatier_commerce_wizard_step', [ $this, 'handle_step_post' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Menu registration moved to PizzaTier\Admin\AdminMenu in 2.0.0, which owns
	 * the whole sidebar since the merge. This used to register under
	 * PizzaTier's own top-level menu, which no longer exists.
	 *
	 * Kept as a no-op so any code still calling it does not fatal.
	 *
	 * @deprecated 2.0.0 Menus are registered by PizzaTier\Admin\AdminMenu.
	 */
	public function register_menu(): void {
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		// Reuse the existing admin product-tab CSS.
		wp_enqueue_style(
			'pizzatier-commerce-wizard',
			PIZZATIER_PLUGIN_URL . 'assets/css/admin.css',
			[ 'woocommerce_admin_styles' ],
			PIZZATIER_VERSION
		);
		wp_enqueue_media(); // for featured image picker in step 1
		wp_enqueue_script(
			'pizzatier-commerce-new-pizza-wizard',
			PIZZATIER_PLUGIN_URL . 'assets/js/admin-new-pizza-wizard.js',
			[ 'jquery' ],
			PIZZATIER_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Step POST handler
	// -------------------------------------------------------------------------

	public function handle_step_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pizzatier' ) );
		}
		check_admin_referer( 'pizzatier_commerce_wizard_step' );

		$action = sanitize_key( $_POST['pizzatier_commerce_wiz_action'] ?? 'next' );
		$step   = absint( $_POST['pizzatier_commerce_wiz_current_step'] ?? 1 );
		$data   = $this->get_wizard_data();

		if ( 'reset' === $action ) {
			$this->clear_wizard_data();
			wp_safe_redirect( $this->wizard_url( 1 ) );
			exit;
		}

		if ( 'back' === $action ) {
			wp_safe_redirect( $this->wizard_url( max( 1, $step - 1 ) ) );
			exit;
		}

		// Merge posted data for current step.
		switch ( $step ) {
			case 1:
				$data['title']       = sanitize_text_field( wp_unslash( $_POST['pizzatier_commerce_wiz_title'] ?? '' ) );
				$data['description'] = wp_kses_post( wp_unslash( $_POST['pizzatier_commerce_wiz_description'] ?? '' ) );
				$data['image_id']    = absint( $_POST['pizzatier_commerce_wiz_image_id'] ?? 0 );
				break;

			case 2:
				$data['preset_id'] = absint( $_POST['pizzatier_commerce_wiz_preset_id'] ?? 0 );
				break;

			case 3:
				$data['builder_template'] = sanitize_key( $_POST['pizzatier_commerce_wiz_builder_template'] ?? '' );
				$data['builder_position'] = sanitize_key( $_POST['pizzatier_commerce_wiz_builder_position'] ?? 'before_cart' );
				// Default layers map.
				$raw_defaults = is_array( $_POST['pizzatier_commerce_wiz_default_layers'] ?? null )
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
					? (array) wp_unslash( $_POST['pizzatier_commerce_wiz_default_layers'] ) : [];
				$defaults = [];
				foreach ( $raw_defaults as $type => $id ) {
					$t = sanitize_key( $type );
					$i = absint( $id );
					if ( $t && $i ) { $defaults[ $t ] = $i; }
				}
				$data['default_layers'] = $defaults;
				// Enabled layers.
				$raw_enabled = is_array( $_POST['pizzatier_commerce_wiz_enabled_layers'] ?? null )
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
					? (array) wp_unslash( $_POST['pizzatier_commerce_wiz_enabled_layers'] ) : [];
				$data['enabled_layers'] = array_values( array_filter( array_map( 'absint', $raw_enabled ) ) );
				break;

			case 4:
				// Price grid.
				if ( ! empty( $_POST['pizzatier_commerce_price_grid'] ) && is_array( $_POST['pizzatier_commerce_price_grid'] ) ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
					$raw_grid = wp_unslash( $_POST['pizzatier_commerce_price_grid'] );
					$sizes     = array_map( 'sanitize_text_field', (array) ( $raw_grid['sizes']     ?? [] ) );
					$fractions = array_map( 'sanitize_text_field', (array) ( $raw_grid['fractions'] ?? [] ) );
					$cells_raw = is_array( $raw_grid['cells'] ?? null ) ? $raw_grid['cells'] : [];
					$cells = [];
					foreach ( $cells_raw as $key => $val ) {
						$clean_key = sanitize_text_field( $key );
						$clean_val = $val !== '' ? floatval( $val ) : null;
						if ( $clean_key && $clean_val !== null ) {
							$cells[ $clean_key ] = $clean_val;
						}
					}
					$data['price_grid'] = compact( 'sizes', 'fractions', 'cells' );
				}
				break;

			case 5:
				// Publish step — create the product.
				if ( 'publish' === $action ) {
					$product_id = $this->create_product( $data );
					if ( is_wp_error( $product_id ) ) {
						wp_safe_redirect( $this->wizard_url( 5, [ 'error' => rawurlencode( $product_id->get_error_message() ) ] ) );
						exit;
					}
					$this->clear_wizard_data();
					wp_safe_redirect( admin_url( 'post.php?post=' . $product_id . '&action=edit&pizzatier_commerce_wizard_done=1' ) );
					exit;
				}
				break;
		}

		$this->save_wizard_data( $data );

		$next = min( 5, $step + 1 );
		wp_safe_redirect( $this->wizard_url( $next ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Create product
	// -------------------------------------------------------------------------

	/**
	 * Create the WooCommerce product from wizard data.
	 *
	 * @param array $data Wizard data array.
	 * @return int|\WP_Error  Product ID on success, WP_Error on failure.
	 */
	private function create_product( array $data ) {
		if ( empty( $data['title'] ) ) {
			return new \WP_Error( 'no_title', __( 'Product title is required.', 'pizzatier' ) );
		}

		// Create the WC product.
		$product = new \WC_Product_Simple();
		$product->set_name( $data['title'] );
		$product->set_description( $data['description'] ?? '' );
		$product->set_status( 'publish' );

		// Ensure pizza product type.
		// WC product types are set via taxonomy term.
		$product_id = $product->save();

		if ( ! $product_id ) {
			return new \WP_Error( 'save_failed', __( 'Failed to create product.', 'pizzatier' ) );
		}

		// Set product type to "pizza".
		wp_set_object_terms( $product_id, 'pizza', 'product_type' );

		// Featured image.
		if ( ! empty( $data['image_id'] ) ) {
			set_post_thumbnail( $product_id, (int) $data['image_id'] );
		}

		// Builder meta.
		if ( ! empty( $data['builder_template'] ) ) {
			update_post_meta( $product_id, '_pizzatier_builder_template', (string) $data['builder_template'] );
		}
		$allowed_pos = [ 'before_cart', 'after_title', 'after_summary' ];
		$pos = $data['builder_position'] ?? 'before_cart';
		update_post_meta( $product_id, '_pizzatier_builder_position', in_array( $pos, $allowed_pos, true ) ? $pos : 'before_cart' );

		// Merge toppings (stored separately in wizard data as 'topping_ids') into the
		// default layers map under the 'toppings' key so build_default_layer_atts()
		// can resolve them into default_toppings="..." shortcode attributes.
		$default_layers = is_array( $data['default_layers'] ?? null ) ? $data['default_layers'] : [];
		if ( ! empty( $data['topping_ids'] ) && is_array( $data['topping_ids'] ) ) {
			$tip_ids = array_values( array_filter( array_map( 'absint', $data['topping_ids'] ) ) );
			if ( $tip_ids ) {
				$default_layers['toppings'] = $tip_ids;
			}
		}
		if ( ! empty( $default_layers ) ) {
			update_post_meta( $product_id, '_pizzatier_default_layers', $default_layers );
		}
		if ( ! empty( $data['enabled_layers'] ) ) {
			update_post_meta( $product_id, '_pizzatier_enabled_layers', $data['enabled_layers'] );
		}

		// Price grid.
		if ( ! empty( $data['price_grid'] ) ) {
			$grid_model = new \PizzaTier\Commerce\PriceGrid\Grid();
			$grid_model->save( $product_id, $data['price_grid'] );
		}

		return $product_id;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Handle "Start over" GET-based reset (nonce-verified).
		if (
			! empty( $_GET['wiz_reset'] ) &&
			! empty( $_GET['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'pizzatier_commerce_wizard_reset' )
		) {
			$this->clear_wizard_data();
			wp_safe_redirect( $this->wizard_url( 1 ) );
			exit;
		}

		$step  = absint( $_GET['wiz_step'] ?? 1 );
		$step  = max( 1, min( 5, $step ) );
		$data  = $this->get_wizard_data();
		$error = sanitize_text_field( wp_unslash( $_GET['error'] ?? '' ) );

		// Pull layer data from PizzaTier CPTs.
		$layer_data    = $this->get_all_layer_data();
		$presets       = $this->get_presets();
		$builders      = $this->get_builders();
		$currency      = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';
		$grid_model    = new \PizzaTier\Commerce\PriceGrid\Grid();
		$grid_sizes    = $data['price_grid']['sizes']     ?? $grid_model->default_sizes();
		$grid_fractions= $data['price_grid']['fractions'] ?? $grid_model->default_fractions();
		$grid_cells    = $data['price_grid']['cells']     ?? [];

		// If step 3 and preset chosen in step 2, seed default layers from preset.
		if ( $step === 3 && ! empty( $data['preset_id'] ) && empty( $data['default_layers'] ) ) {
			$preset_layers = get_post_meta( (int) $data['preset_id'], \PizzaTier\Commerce\Admin\Presets::META_KEY, true );
			if ( is_array( $preset_layers ) ) {
				$data['default_layers'] = array_filter( [
					'crust'   => $preset_layers['crust_id']   ?? 0,
					'sauce'   => $preset_layers['sauce_id']   ?? 0,
					'cheese'  => $preset_layers['cheese_id']  ?? 0,
					'drizzle' => $preset_layers['drizzle_id'] ?? 0,
					'cut'     => $preset_layers['cut_id']     ?? 0,
				] );
				$data['topping_ids'] = $preset_layers['topping_ids'] ?? [];
			}
		}

		?>
		<div class="wrap pztc-wizard-page">
			<?php $this->render_wizard_styles(); ?>

			<!-- Page header -->
			<div class="pztc-wiz-page-header">
				<div class="pztc-wiz-page-header__brand">
					<span style="font-size:28px;">🍕</span>
					<div>
						<h1><?php esc_html_e( 'New Pizza Wizard', 'pizzatier' ); ?></h1>
						<p><?php esc_html_e( 'Build your pizza product from scratch in five steps.', 'pizzatier' ); ?></p>
					</div>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="pztc-wiz-exit-link">
					← <?php esc_html_e( 'Back to Dashboard', 'pizzatier' ); ?>
				</a>
			</div>

			<!-- Step indicator -->
			<div class="pztc-wiz-stepper">
				<?php
				$steps_def = [
					1 => __( 'Product Info', 'pizzatier' ),
					2 => __( 'Preset',       'pizzatier' ),
					3 => __( 'Layers',       'pizzatier' ),
					4 => __( 'Pricing',      'pizzatier' ),
					5 => __( 'Publish',      'pizzatier' ),
				];
				foreach ( $steps_def as $n => $label ) :
					$cls = '';
					if ( $n < $step )  $cls = 'done';
					if ( $n === $step ) $cls = 'active';
					?>
					<div class="pztc-wiz-stepper__item pztc-wiz-stepper__item--<?php echo esc_attr( $cls ); ?>">
						<span class="pztc-wiz-stepper__num"><?php echo $n < $step ? '✓' : esc_html( $n ); ?></span>
						<span class="pztc-wiz-stepper__label"><?php echo esc_html( $label ); ?></span>
					</div>
					<?php if ( $n < 5 ) : ?><div class="pztc-wiz-stepper__line pztc-wiz-stepper__line--<?php echo $n < $step ? 'done' : ''; ?>"></div><?php endif; ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $error ) : ?>
				<div class="notice notice-error" style="margin:0 0 16px;"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<!-- Step content -->
			<div class="pztc-wiz-body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="pztc-wiz-form">
					<?php wp_nonce_field( 'pizzatier_commerce_wizard_step' ); ?>
					<input type="hidden" name="action" value="pizzatier_commerce_wizard_step">
					<input type="hidden" name="pizzatier_commerce_wiz_current_step" value="<?php echo esc_attr( $step ); ?>">
					<input type="hidden" name="pizzatier_commerce_wiz_action" value="next" id="pztc-wiz-action-field">

					<?php
					switch ( $step ) {
						case 1: $this->render_step_1( $data ); break;
						case 2: $this->render_step_2( $data, $presets ); break;
						case 3: $this->render_step_3( $data, $builders, $layer_data ); break;
						case 4: $this->render_step_4( $data, $grid_sizes, $grid_fractions, $grid_cells, $grid_model, $currency ); break;
						case 5: $this->render_step_5( $data, $currency ); break;
					}
					?>

					<!-- Nav buttons -->
					<div class="pztc-wiz-nav">
						<?php if ( $step > 1 ) : ?>
							<button type="submit" class="button pztc-wiz-nav__back"
									onclick="document.getElementById('pztc-wiz-action-field').value='back'">
								← <?php esc_html_e( 'Back', 'pizzatier' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( $step < 5 ) : ?>
							<button type="submit" class="button button-primary pztc-wiz-nav__next">
								<?php esc_html_e( 'Continue', 'pizzatier' ); ?> →
							</button>
						<?php else : ?>
							<button type="submit" class="button button-primary pztc-wiz-nav__publish"
									onclick="document.getElementById('pztc-wiz-action-field').value='publish'">
								🚀 <?php esc_html_e( 'Publish Pizza Product', 'pizzatier' ); ?>
							</button>
						<?php endif; ?>

						<a href="<?php echo esc_url( wp_nonce_url( $this->wizard_url( 1, [ 'wiz_reset' => '1' ] ), 'pizzatier_commerce_wizard_reset' ) ); ?>"
						   class="pztc-wiz-nav__reset"
						   onclick="return confirm('<?php echo esc_js( __( 'Start over? All wizard data will be cleared.', 'pizzatier' ) ); ?>')">
							<?php esc_html_e( 'Start over', 'pizzatier' ); ?>
						</a>
					</div>

				</form>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Step renders
	// -------------------------------------------------------------------------

	private function render_step_1( array $data ): void {
		$title   = $data['title'] ?? '';
		$desc    = $data['description'] ?? '';
		$img_id  = absint( $data['image_id'] ?? 0 );
		$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
		?>
		<div class="pztc-wiz-step">
			<div class="pztc-wiz-step__header">
				<span class="pztc-wiz-step__icon">📋</span>
				<div>
					<h2><?php esc_html_e( 'Step 1: Product Information', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Give your pizza product a name, description, and an image.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<div class="pztc-wiz-fields">
				<div class="pztc-wiz-field pztc-wiz-field--required">
					<label for="pizzatier_commerce_wiz_title"><?php esc_html_e( 'Product Name', 'pizzatier' ); ?> <span class="pztc-req">*</span></label>
					<input type="text" id="pizzatier_commerce_wiz_title" name="pizzatier_commerce_wiz_title"
						   value="<?php echo esc_attr( $title ); ?>" class="regular-text"
						   placeholder="<?php esc_attr_e( 'e.g. Build Your Own Pizza', 'pizzatier' ); ?>"
						   required />
				</div>

				<div class="pztc-wiz-field">
					<label for="pizzatier_commerce_wiz_description"><?php esc_html_e( 'Short Description', 'pizzatier' ); ?></label>
					<textarea id="pizzatier_commerce_wiz_description" name="pizzatier_commerce_wiz_description"
							  rows="4" class="large-text"
							  placeholder="<?php esc_attr_e( 'Describe this pizza product…', 'pizzatier' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
					<p class="pztc-field-hint"><?php esc_html_e( 'Shown on the product page. You can edit it later.', 'pizzatier' ); ?></p>
				</div>

				<div class="pztc-wiz-field">
					<label><?php esc_html_e( 'Product Image', 'pizzatier' ); ?></label>
					<div class="pztc-wiz-image-picker" id="pztc-wiz-image-picker">
						<input type="hidden" name="pizzatier_commerce_wiz_image_id" id="pizzatier_commerce_wiz_image_id" value="<?php echo esc_attr( $img_id ); ?>">
						<div class="pztc-wiz-image-preview" id="pztc-wiz-image-preview">
							<?php if ( $img_url ) : ?>
								<img src="<?php echo esc_url( $img_url ); ?>" alt="">
							<?php else : ?>
								<span class="pztc-wiz-image-placeholder">
									<span class="dashicons dashicons-format-image"></span>
									<span><?php esc_html_e( 'No image selected', 'pizzatier' ); ?></span>
								</span>
							<?php endif; ?>
						</div>
						<div class="pztc-wiz-image-actions">
							<button type="button" class="button" id="pztc-wiz-select-image">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Select Image', 'pizzatier' ); ?>
							</button>
							<?php if ( $img_id ) : ?>
								<button type="button" class="button" id="pztc-wiz-remove-image"><?php esc_html_e( 'Remove', 'pizzatier' ); ?></button>
							<?php endif; ?>
						</div>
					</div>
			</div>
		</div>
		<?php
	}

	private function render_step_2( array $data, array $presets ): void {
		$selected_preset = absint( $data['preset_id'] ?? 0 );
		?>
		<div class="pztc-wiz-step">
			<div class="pztc-wiz-step__header">
				<span class="pztc-wiz-step__icon">🍕</span>
				<div>
					<h2><?php esc_html_e( 'Step 2: Start from a Preset (Optional)', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Choose a pizza preset to pre-fill the default layers in the next step. You can skip this and set layers manually.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<div class="pztc-wiz-preset-grid">

				<!-- No preset option -->
				<label class="pztc-wiz-preset-card pztc-wiz-preset-card--none <?php echo $selected_preset === 0 ? 'pztc-wiz-preset-card--selected' : ''; ?>">
					<input type="radio" name="pizzatier_commerce_wiz_preset_id" value="0" <?php checked( $selected_preset, 0 ); ?> hidden>
					<span class="pztc-wiz-preset-card__icon">✏️</span>
					<span class="pztc-wiz-preset-card__title"><?php esc_html_e( 'Start from scratch', 'pizzatier' ); ?></span>
					<span class="pztc-wiz-preset-card__desc"><?php esc_html_e( 'Configure all layers manually in the next step.', 'pizzatier' ); ?></span>
				</label>

				<?php if ( empty( $presets ) ) : ?>
					<p class="pztc-wiz-no-presets">
						<?php printf(
							/* translators: %s: value inserted into the message. */
							wp_kses_post( __( 'No presets found. <a href="%s" target="_blank">Create a pizza preset →</a>', 'pizzatier' ) ),
							esc_url( admin_url( 'post-new.php?post_type=pizzatier_presets' ) )
						); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $presets as $preset ) :
						$thumb = get_the_post_thumbnail_url( $preset->ID, 'medium' );
						$layers = get_post_meta( $preset->ID, Presets::META_KEY, true );
						$layer_count = is_array( $layers ) ? array_filter( $layers ) : [];
						?>
						<label class="pztc-wiz-preset-card <?php echo $selected_preset === $preset->ID ? 'pztc-wiz-preset-card--selected' : ''; ?>">
							<input type="radio" name="pizzatier_commerce_wiz_preset_id" value="<?php echo esc_attr( $preset->ID ); ?>"
								   <?php checked( $selected_preset, $preset->ID ); ?> hidden>
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" class="pztc-wiz-preset-card__thumb">
							<?php else : ?>
								<span class="pztc-wiz-preset-card__icon">🍕</span>
							<?php endif; ?>
							<span class="pztc-wiz-preset-card__title"><?php echo esc_html( $preset->post_title ); ?></span>
							<span class="pztc-wiz-preset-card__desc">
								<?php echo esc_html( count( $layer_count ) . ' ' . __( 'layers configured', 'pizzatier' ) ); ?>
							</span>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>

			</div>

		<?php
	}

	private function render_step_3( array $data, array $builders, array $layer_data ): void {
		$builder_template = (string) ( $data['builder_template'] ?? '' );
		$builder_position = (string) ( $data['builder_position'] ?? 'before_cart' );
		$saved_defaults   = $data['default_layers'] ?? [];
		$enabled_ids      = array_map( 'strval', $data['enabled_layers'] ?? [] );

		$layer_types = [
			'crusts'   => [ 'Crusts',   'crust'   ],
			'sauces'   => [ 'Sauces',   'sauce'   ],
			'cheeses'  => [ 'Cheeses',  'cheese'  ],
			'toppings' => [ 'Toppings', 'topping' ],
			'drizzles' => [ 'Drizzles', 'drizzle' ],
			'cuts'     => [ 'Cuts',     'cut'     ],
		];
		?>
		<div class="pztc-wiz-step">
			<div class="pztc-wiz-step__header">
				<span class="pztc-wiz-step__icon">🎨</span>
				<div>
					<h2><?php esc_html_e( 'Step 3: Builder & Layer Configuration', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Choose which builder displays on the product page and set default layers.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<div class="pztc-wiz-fields">

				<div class="pztc-wiz-field pztc-wiz-field--required">
					<label for="pizzatier_commerce_wiz_builder_template">
						<?php esc_html_e( 'PizzaTier Builder', 'pizzatier' ); ?> <span class="pztc-req">*</span>
					</label>
					<?php if ( empty( $builders ) ) : ?>
						<p class="pztc-notice pztc-notice--warn">
							<span class="dashicons dashicons-warning"></span>
							<?php printf(
								/* translators: %s: value inserted into the message. */
								wp_kses_post( __( 'No PizzaTier builders found. <a href="%s" target="_blank">Create one first →</a>', 'pizzatier' ) ),
								esc_url( admin_url( 'admin.php?page=pizzatier' ) )
							); ?>
						</p>
					<?php else : ?>
						<select id="pizzatier_commerce_wiz_builder_template" name="pizzatier_commerce_wiz_builder_template" class="regular-text">
							<option value=""><?php esc_html_e( '— Select a builder —', 'pizzatier' ); ?></option>
							<?php foreach ( $builders as $tpl_slug => $tpl_name ) : ?>
								<option value="<?php echo esc_attr( $tpl_slug ); ?>" <?php selected( $builder_template, $tpl_slug ); ?>>
									<?php echo esc_html( $tpl_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="pztc-field-hint"><?php esc_html_e( 'The PizzaTier builder instance (theme + layout) shown to customers.', 'pizzatier' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="pztc-wiz-field">
					<label for="pizzatier_commerce_wiz_builder_position"><?php esc_html_e( 'Builder Position', 'pizzatier' ); ?></label>
					<select id="pizzatier_commerce_wiz_builder_position" name="pizzatier_commerce_wiz_builder_position">
						<option value="before_cart"   <?php selected( $builder_position, 'before_cart' ); ?>><?php esc_html_e( 'Above add-to-cart form', 'pizzatier' ); ?></option>
						<option value="after_title"   <?php selected( $builder_position, 'after_title' ); ?>><?php esc_html_e( 'After product title', 'pizzatier' ); ?></option>
						<option value="after_summary" <?php selected( $builder_position, 'after_summary' ); ?>><?php esc_html_e( 'After product summary', 'pizzatier' ); ?></option>
					</select>
				</div>

			</div>

			<!-- Layer configuration -->
			<?php if ( ! empty( $layer_data ) ) : ?>
				<div class="pztc-wiz-layers-section">
					<h3><?php esc_html_e( 'Default & Available Layers', 'pizzatier' ); ?></h3>
					<p class="pztc-field-hint"><?php esc_html_e( 'Click a card to set it as the default (shown on load). Check the box to make it available in the builder. Unchecked items are hidden from customers.', 'pizzatier' ); ?></p>

					<?php foreach ( $layer_types as $plural => [ $label, $singular ] ) :
						if ( empty( $layer_data[ $plural ] ) ) continue;
						$default_id = (string) ( $saved_defaults[ $singular ] ?? '' );
						?>
						<div class="pztc-wiz-layer-group">
							<h4 class="pztc-wiz-layer-group__title">
								<?php echo esc_html( $label ); ?>
								<label class="pztc-wiz-select-all-label">
									<input type="checkbox" class="js-wiz-select-all" data-type="<?php echo esc_attr( $plural ); ?>">
									<?php esc_html_e( 'All', 'pizzatier' ); ?>
								</label>
							</h4>
							<div class="pztc-lc-grid" id="pztc-wiz-grid-<?php echo esc_attr( $plural ); ?>">
								<?php foreach ( $layer_data[ $plural ] as $layer ) :
									$lid     = (string) $layer['id'];
									$is_def  = ( $lid === $default_id );
									$is_avail = empty( $enabled_ids ) || in_array( $lid, $enabled_ids, true );
									?>
									<div class="pztc-lc-card<?php echo $is_def ? ' pztc-lc-card--default' : ''; ?><?php echo $is_avail ? ' pztc-lc-card--enabled' : ''; ?>"
										 data-id="<?php echo esc_attr( $lid ); ?>"
										 data-slug="<?php echo esc_attr( $layer['slug'] ); ?>"
										 data-type="<?php echo esc_attr( $singular ); ?>"
										 data-type-plural="<?php echo esc_attr( $plural ); ?>"
										 data-name="<?php echo esc_attr( $layer['name'] ); ?>">
										<input type="radio"
											   name="pizzatier_commerce_wiz_default_layers[<?php echo esc_attr( $singular ); ?>]"
											   value="<?php echo esc_attr( $lid ); ?>"
											   class="pztc-lc-card__radio"
											   <?php checked( $is_def ); ?> />
										<input type="checkbox"
											   name="pizzatier_commerce_wiz_enabled_layers[]"
											   value="<?php echo esc_attr( $lid ); ?>"
											   class="pztc-lc-card__avail"
											   <?php checked( $is_avail ); ?>
											   title="<?php esc_attr_e( 'Available in builder', 'pizzatier' ); ?>"/>
										<span class="pztc-lc-card__star" title="Default">★</span>
										<span class="pztc-lc-card__img-wrap">
											<?php if ( $layer['thumb'] ) : ?>
												<img src="<?php echo esc_url( $layer['thumb'] ); ?>" alt="<?php echo esc_attr( $layer['name'] ); ?>" loading="lazy">
											<?php else : ?>
												<span class="pztc-lc-card__img-placeholder"><span class="dashicons dashicons-format-image"></span></span>
											<?php endif; ?>
										</span>
										<span class="pztc-lc-card__name"><?php echo esc_html( $layer['name'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="pztc-notice pztc-notice--info">
					<?php esc_html_e( 'No layer items found. Add Crusts, Sauces, Toppings etc. in PizzaTier first.', 'pizzatier' ); ?>
				</p>
			<?php endif; ?>
		</div>

	}

	private function render_step_4( array $data, array $sizes, array $fractions, array $cells, \PizzaTier\Commerce\PriceGrid\Grid $grid_model, string $currency ): void {
		$renderer = new \PizzaTier\Commerce\PriceGrid\GridRenderer( $grid_model );
		?>
		<div class="pztc-wiz-step">
			<div class="pztc-wiz-step__header">
				<span class="pztc-wiz-step__icon">💲</span>
				<div>
					<h2><?php esc_html_e( 'Step 4: Price Grid', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Set the price per topping, per size, and per coverage area.', 'pizzatier' ); ?></p>
				</div>
			</div>
			<?php $renderer->render_table_standalone( $sizes, $fractions, $cells, $currency ); ?>
		</div>
		<?php
	}

	private function render_step_5( array $data, string $currency ): void {
		$title    = $data['title'] ?? '';
		$img_id   = absint( $data['image_id'] ?? 0 );
		$img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
		$builder  = (string) ( $data['builder_template'] ?? '' );
		$builder_title = $builder !== '' ? ucfirst( $builder ) : __( 'None', 'pizzatier' );
		$defaults = $data['default_layers'] ?? [];
		$grid     = $data['price_grid'] ?? null;
		?>
		<div class="pztc-wiz-step">
			<div class="pztc-wiz-step__header">
				<span class="pztc-wiz-step__icon">🚀</span>
				<div>
					<h2><?php esc_html_e( 'Step 5: Review & Publish', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Review your pizza product below, then hit Publish.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<div class="pztc-wiz-review">

				<div class="pztc-wiz-review__row">
					<span class="pztc-wiz-review__label"><?php esc_html_e( 'Product Name', 'pizzatier' ); ?></span>
					<span class="pztc-wiz-review__value">
						<?php echo $title ? esc_html( $title ) : '<em>' . esc_html__( 'Not set', 'pizzatier' ) . '</em>'; ?>
					</span>
				</div>

				<?php if ( $img_url ) : ?>
					<div class="pztc-wiz-review__row">
						<span class="pztc-wiz-review__label"><?php esc_html_e( 'Product Image', 'pizzatier' ); ?></span>
						<span class="pztc-wiz-review__value"><img src="<?php echo esc_url( $img_url ); ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;"></span>
					</div>
				<?php endif; ?>

				<div class="pztc-wiz-review__row">
					<span class="pztc-wiz-review__label"><?php esc_html_e( 'Builder', 'pizzatier' ); ?></span>
					<span class="pztc-wiz-review__value"><?php echo esc_html( $builder_title ); ?></span>
				</div>

				<div class="pztc-wiz-review__row">
					<span class="pztc-wiz-review__label"><?php esc_html_e( 'Default Layers', 'pizzatier' ); ?></span>
					<span class="pztc-wiz-review__value">
						<?php
						if ( empty( $defaults ) ) {
							echo '<em>' . esc_html__( 'None configured', 'pizzatier' ) . '</em>';
						} else {
							$parts = [];
							foreach ( $defaults as $type => $id ) {
								$parts[] = '<strong>' . esc_html( ucfirst( $type ) ) . ':</strong> ' . esc_html( get_the_title( (int) $id ) );
							}
							echo wp_kses( implode( ' &nbsp;·&nbsp; ', $parts ), [ 'strong' => [] ] );
						}
						?>
					</span>
				</div>

				<div class="pztc-wiz-review__row">
					<span class="pztc-wiz-review__label"><?php esc_html_e( 'Price Grid', 'pizzatier' ); ?></span>
					<span class="pztc-wiz-review__value">
						<?php if ( $grid ) :
							echo esc_html( count( $grid['sizes'] ) . ' ' . __( 'sizes', 'pizzatier' ) );
							echo ' &nbsp;×&nbsp; ';
							echo esc_html( count( $grid['fractions'] ) . ' ' . __( 'coverage columns', 'pizzatier' ) );
						else :
							echo '<em>' . esc_html__( 'Not configured (can be set after publishing)', 'pizzatier' ) . '</em>';
						endif; ?>
					</span>
				</div>

			</div>

			<div class="pztc-wiz-review__note">
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'After publishing you will be taken to the full product edit screen where you can make further adjustments.', 'pizzatier' ); ?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Wizard styles
	// -------------------------------------------------------------------------

	private function render_wizard_styles(): void {
		?>
		<style>
		.pztc-wizard-page { max-width: 900px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

		/* Page header */
		.pztc-wiz-page-header { display:flex; align-items:center; justify-content:space-between; background:linear-gradient(135deg,#1a1e23 0%,#2d3748 100%); border-radius:10px; padding:20px 24px; margin-bottom:20px; border-bottom:3px solid #ff6b35; }
		.pztc-wiz-page-header__brand { display:flex; align-items:center; gap:14px; }
		.pztc-wiz-page-header__brand h1 { color:#fff; margin:0; font-size:20px; }
		.pztc-wiz-page-header__brand p { color:rgba(255,255,255,.55); margin:2px 0 0; font-size:13px; }
		.pztc-wiz-exit-link { color:rgba(255,255,255,.7); text-decoration:none; font-size:13px; }
		.pztc-wiz-exit-link:hover { color:#fff; }

		/* Stepper */
		.pztc-wiz-stepper { display:flex; align-items:center; background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:16px 20px; margin-bottom:20px; gap:0; }
		.pztc-wiz-stepper__item { display:flex; align-items:center; gap:8px; }
		.pztc-wiz-stepper__num { width:28px; height:28px; border-radius:50%; background:#e0e0e0; color:#888; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
		.pztc-wiz-stepper__item--active .pztc-wiz-stepper__num { background:#ff6b35; color:#fff; }
		.pztc-wiz-stepper__item--done .pztc-wiz-stepper__num { background:#46b450; color:#fff; }
		.pztc-wiz-stepper__label { font-size:13px; font-weight:600; color:#aaa; }
		.pztc-wiz-stepper__item--active .pztc-wiz-stepper__label { color:#1a1a2e; }
		.pztc-wiz-stepper__item--done .pztc-wiz-stepper__label { color:#46b450; }
		.pztc-wiz-stepper__line { flex:1; height:2px; background:#e0e0e0; margin:0 12px; }
		.pztc-wiz-stepper__line--done { background:#46b450; }

		/* Body */
		.pztc-wiz-body { background:#fff; border:1px solid #e0e0e0; border-radius:10px; padding:28px; margin-bottom:16px; }

		/* Step */
		.pztc-wiz-step__header { display:flex; align-items:flex-start; gap:14px; margin-bottom:24px; padding-bottom:16px; border-bottom:1px solid #f0f0f0; }
		.pztc-wiz-step__icon { font-size:32px; line-height:1; flex-shrink:0; }
		.pztc-wiz-step__header h2 { font-size:17px; margin:0 0 4px; color:#1a1a2e; }
		.pztc-wiz-step__header p { font-size:13px; color:#666; margin:0; }

		/* Fields */
		.pztc-wiz-fields { display:flex; flex-direction:column; gap:18px; }
		.pztc-wiz-field label { display:block; font-weight:600; font-size:13px; color:#1a1a2e; margin-bottom:6px; }
		.pztc-field-hint { font-size:12px; color:#888; margin:4px 0 0; }
		.pztc-req { color:#ff6b35; }

		/* Image picker */
		.pztc-wiz-image-picker { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
		.pztc-wiz-image-preview { width:120px; height:120px; border:2px dashed #e0e0e0; border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#fafafa; }
		.pztc-wiz-image-preview img { width:100%; height:100%; object-fit:cover; }
		.pztc-wiz-image-placeholder { display:flex; flex-direction:column; align-items:center; gap:4px; color:#bbb; font-size:11px; }
		.pztc-wiz-image-placeholder .dashicons { font-size:32px !important; }
		.pztc-wiz-image-actions { display:flex; flex-direction:column; gap:8px; }

		/* Preset grid */
		.pztc-wiz-preset-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-top:8px; }
		.pztc-wiz-preset-card { display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px 12px; border:2px solid #e0e0e0; border-radius:10px; background:#fff; cursor:pointer; text-align:center; transition:border-color .15s, background .15s; }
		.pztc-wiz-preset-card:hover { border-color:#ff6b35; background:#fff7f3; }
		.pztc-wiz-preset-card--selected { border-color:#ff6b35; background:#fff7f3; }
		.pztc-wiz-preset-card--none { border-style:dashed; }
		.pztc-wiz-preset-card__icon { font-size:32px; }
		.pztc-wiz-preset-card__thumb { width:80px; height:80px; object-fit:cover; border-radius:50%; }
		.pztc-wiz-preset-card__title { font-size:13px; font-weight:700; color:#1a1a2e; }
		.pztc-wiz-preset-card__desc { font-size:11px; color:#888; }
		.pztc-wiz-no-presets { font-size:13px; color:#aaa; }

		/* Layer groups in step 3 */
		.pztc-wiz-layers-section { margin-top:24px; }
		.pztc-wiz-layers-section h3 { font-size:14px; text-transform:uppercase; letter-spacing:.04em; color:#888; margin:0 0 6px; }
		.pztc-wiz-layer-group { margin-bottom:20px; }
		.pztc-wiz-layer-group__title { display:flex; align-items:center; justify-content:space-between; font-size:13px; font-weight:700; color:#1a1a2e; margin:0 0 8px; padding:8px 0; border-bottom:1px solid #eee; }
		.pztc-wiz-select-all-label { display:flex; align-items:center; gap:4px; font-size:12px; font-weight:400; color:#888; cursor:pointer; }

		/* Review */
		.pztc-wiz-review { border:1px solid #e8e8e8; border-radius:8px; overflow:hidden; margin-bottom:16px; }
		.pztc-wiz-review__row { display:flex; align-items:center; padding:12px 16px; border-bottom:1px solid #f5f5f5; gap:16px; }
		.pztc-wiz-review__row:last-child { border-bottom:none; }
		.pztc-wiz-review__label { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#888; font-weight:600; width:140px; flex-shrink:0; }
		.pztc-wiz-review__value { font-size:13px; color:#1a1a2e; }
		.pztc-wiz-review__note { font-size:12px; color:#666; display:flex; align-items:center; gap:6px; }
		.pztc-wiz-review__note .dashicons { color:#888; font-size:14px !important; }

		/* Nav */
		.pztc-wiz-nav { display:flex; align-items:center; gap:12px; padding-top:20px; border-top:1px solid #f0f0f0; margin-top:4px; }
		.pztc-wiz-nav__back { }
		.pztc-wiz-nav__next, .pztc-wiz-nav__publish { padding:8px 24px !important; font-size:14px !important; }
		.pztc-wiz-nav__publish { background:#46b450 !important; border-color:#3d9e43 !important; }
		.pztc-wiz-nav__publish:hover { background:#3d9e43 !important; }
		.pztc-wiz-nav__reset { margin-left:auto; font-size:12px; color:#aaa; text-decoration:none; }
		.pztc-wiz-nav__reset:hover { color:#ff6b35; }
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	private function get_wizard_data(): array {
		$raw = get_transient( self::TRANS_KEY . get_current_user_id() );
		return is_array( $raw ) ? $raw : [];
	}

	private function save_wizard_data( array $data ): void {
		set_transient( self::TRANS_KEY . get_current_user_id(), $data, self::TRANS_TTL );
	}

	private function clear_wizard_data(): void {
		delete_transient( self::TRANS_KEY . get_current_user_id() );
	}

	private function wizard_url( int $step, array $extra = [] ): string {
		return add_query_arg(
			array_merge( [ 'page' => self::PAGE_SLUG, 'wiz_step' => $step ], $extra ),
			admin_url( 'admin.php' )
		);
	}

	private function get_presets(): array {
		return get_posts( [
			'post_type'   => 'pizzatier_presets',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		] );
	}

	/**
	 * Return available PizzaTier template slugs → display names.
	 *
	 * @return array<string,string>  [ slug => display_name ]
	 */
	private function get_builders(): array {
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

	private function get_all_layer_data(): array {
		$types = [
			'crusts'   => 'crust',
			'sauces'   => 'sauce',
			'cheeses'  => 'cheese',
			'toppings' => 'topping',
			'drizzles' => 'drizzle',
			'cuts'     => 'cut',
		];
		$result = [];
		foreach ( $types as $plural => $singular ) {
			$cpt = 'pizzatier_' . $plural;
			if ( ! post_type_exists( $cpt ) ) continue;
			$posts = get_posts( [ 'post_type' => $cpt, 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ] );
			$result[ $plural ] = array_map( function( $p ) use ( $singular ) {
				$thumb = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
				$layer_img = get_post_meta( $p->ID, '_pizzatier_layer_image', true );
				return [
					'id'    => (string) $p->ID,
					'name'  => $p->post_title,
					'slug'  => $p->post_name,
					'thumb' => $layer_img ?: ( $thumb ?: '' ),
				];
			}, $posts );
		}
		return $result;
	}
}
