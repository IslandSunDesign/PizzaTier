<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Layer Builder Wizard
 *
 * A fully guided, step-by-step admin page that walks the user through:
 *   Step 1 — Choose layer type (topping, crust, sauce, cheese, drizzle, cut, size)
 *   Step 2 — Enter details (name, description, attributes specific to type)
 *   Step 3 — Upload / pick a layer image
 *   Step 4 — Review & save → creates the CPT post and returns the shortcode
 *
 * All steps run on a single page via JS state; no page reloads between steps.
 * On completion the post is created via an AJAX call to this class.
 */
class LayerBuilderWizard {

	/** Layer type definitions */
	public const LAYER_TYPES = [
		'toppings' => [
			'label'       => 'Topping',
			'plural'      => 'Toppings',
			'cpt'         => 'pizzatier_toppings',
			'icon'        => 'dashicons-tag',
			'color'       => '#e74c3c',
			'emoji'       => '🍕',
			'description' => 'Ingredients placed on top of the cheese — pepperoni, mushrooms, peppers, etc.',
			'extra_fields'=> [ 'calories', 'is_vegetarian', 'is_vegan', 'is_gluten_free' ],
		],
		'crusts' => [
			'label'       => 'Crust',
			'plural'      => 'Crusts',
			'cpt'         => 'pizzatier_crusts',
			'icon'        => 'dashicons-admin-page',
			'color'       => '#e67e22',
			'emoji'       => '🫓',
			'description' => 'The base of your pizza — thin, thick, stuffed, gluten-free, etc.',
			'extra_fields'=> [ 'thickness', 'is_gluten_free' ],
		],
		'sauces' => [
			'label'       => 'Sauce',
			'plural'      => 'Sauces',
			'cpt'         => 'pizzatier_sauces',
			'icon'        => 'dashicons-portfolio',
			'color'       => '#c0392b',
			'emoji'       => '🥫',
			'description' => 'The sauce spread on the crust — marinara, white sauce, pesto, BBQ, etc.',
			'extra_fields'=> [ 'spice_level', 'is_vegan' ],
		],
		'cheeses' => [
			'label'       => 'Cheese',
			'plural'      => 'Cheeses',
			'cpt'         => 'pizzatier_cheeses',
			'icon'        => 'dashicons-star-filled',
			'color'       => '#f39c12',
			'emoji'       => '🧀',
			'description' => 'Cheese layer options — mozzarella, parmesan, vegan cheese, etc.',
			'extra_fields'=> [ 'is_vegan', 'is_dairy_free' ],
		],
		'drizzles' => [
			'label'       => 'Drizzle',
			'plural'      => 'Drizzles',
			'cpt'         => 'pizzatier_drizzles',
			'icon'        => 'dashicons-admin-customizer',
			'color'       => '#8e44ad',
			'emoji'       => '💧',
			'description' => 'Finishing drizzles applied after baking — olive oil, balsamic, hot honey, etc.',
			'extra_fields'=> [ 'spice_level' ],
		],
		'cuts' => [
			'label'       => 'Cut',
			'plural'      => 'Cuts',
			'cpt'         => 'pizzatier_cuts',
			'icon'        => 'dashicons-image-crop',
			'color'       => '#2980b9',
			'emoji'       => '✂️',
			'description' => 'How the pizza is cut — traditional slices, square cut, uncut, etc.',
			'extra_fields'=> [],
		],
		'sizes' => [
			'label'       => 'Size',
			'plural'      => 'Sizes',
			'cpt'         => 'pizzatier_sizes',
			'icon'        => 'dashicons-editor-expand',
			'color'       => '#27ae60',
			'emoji'       => '📏',
			'description' => 'Available pizza sizes — personal (6"), small (8"), medium (12"), large (16"), etc.',
			'extra_fields'=> [ 'diameter_inches' ],
		],
	];

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		wp_enqueue_media();
		// Nonce for AJAX save is delivered via wp_localize_script (see AssetManager::enqueue_admin)
		?>
		<div class="wrap plbw-wrap">
		<?php $this->render_styles(); ?>

		<!-- Header -->
		<div class="plbw-header">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="plbw-header-icon" aria-hidden="true">
				<path d="M10 1C5.03 1 1 5.03 1 10s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zM10 2.6c3.37 0 6.27 2.08 7.52 5.06L10 10.1 2.48 7.66C3.73 4.68 6.63 2.6 10 2.6zM2.6 10c0-.38.03-.75.09-1.11L10 11.7l7.31-2.81c.06.36.09.73.09 1.11 0 4.08-3.32 7.4-7.4 7.4S2.6 14.08 2.6 10zM7.2 11.8a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2zM12.4 12.6a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2z"/>
			</svg>
			<div>
				<h1 class="plbw-header__title"><?php esc_html_e( 'Layer Builder Wizard', 'pizzatier' ); ?></h1>
				<p class="plbw-header__sub"><?php esc_html_e( 'Build any pizza layer in minutes — choose a type, fill in the details, add an image, and get your shortcode.', 'pizzatier' ); ?></p>
			</div>
		</div>

		<!-- Step progress bar -->
		<div class="plbw-progress" id="plbw-progress" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="4">
			<?php
			$steps = [
				1 => 'Choose Type',
				2 => 'Details',
				3 => 'Image',
				4 => 'Review & Save',
			];
			foreach ( $steps as $n => $label ) :
			?>
			<div class="plbw-step <?php echo $n === 1 ? 'is-active' : ''; ?>" data-step="<?php echo esc_attr( $n ); ?>">
				<div class="plbw-step-circle"><?php echo esc_html( (string) $n ); ?></div>
				<span class="plbw-step-label"><?php echo esc_html( $label ); ?></span>
			</div>
			<?php if ( $n < 4 ) : ?>
			<div class="plbw-step-connector"></div>
			<?php endif; endforeach; ?>
		</div>

		<!-- ── STEP 1: Choose Layer Type ────────────────────────────── -->
		<div class="plbw-panel" id="plbw-panel-1">
			<h2 class="plbw-panel-title"><?php esc_html_e( 'What type of layer are you adding?', 'pizzatier' ); ?></h2>
			<p class="plbw-panel-sub"><?php esc_html_e( 'Each layer type represents a different part of your pizza build.', 'pizzatier' ); ?></p>

			<div class="plbw-type-grid" id="plbw-type-grid">
				<?php foreach ( self::LAYER_TYPES as $slug => $type ) : ?>
				<button type="button"
					class="plbw-type-card"
					data-type="<?php echo esc_attr( $slug ); ?>"
					data-label="<?php echo esc_attr( $type['label'] ); ?>"
					data-cpt="<?php echo esc_attr( $type['cpt'] ); ?>"
					data-color="<?php echo esc_attr( $type['color'] ); ?>"
					data-extra="<?php echo esc_attr( wp_json_encode( $type['extra_fields'] ) ); ?>"
					style="--plbw-accent:<?php echo esc_attr( $type['color'] ); ?>">
					<span class="plbw-type-emoji" aria-hidden="true"><?php echo esc_html( $type['emoji'] ); ?></span>
					<span class="plbw-type-name"><?php echo esc_html( $type['label'] ); ?></span>
					<span class="plbw-type-desc"><?php echo esc_html( $type['description'] ); ?></span>
					<span class="plbw-type-check dashicons dashicons-yes-alt" aria-hidden="true"></span>
				</button>
				<?php endforeach; ?>
			</div>

			<div class="plbw-nav-row">
				<span></span>
				<button type="button" class="button button-primary plbw-next-btn" id="plbw-step1-next" disabled>
					<?php esc_html_e( 'Next: Enter Details', 'pizzatier' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- ── STEP 2: Details ──────────────────────────────────────── -->
		<div class="plbw-panel" id="plbw-panel-2" style="display:none;">
			<h2 class="plbw-panel-title" id="plbw-step2-title"><?php esc_html_e( 'Layer Details', 'pizzatier' ); ?></h2>

			<div class="plbw-fields">
				<!-- Core fields -->
				<div class="plbw-field-row">
					<label for="plbw-name" class="plbw-label">
						<?php esc_html_e( 'Name', 'pizzatier' ); ?>
						<span class="plbw-required" aria-label="required">*</span>
					</label>
					<input type="text" id="plbw-name" class="regular-text plbw-input" placeholder="<?php esc_attr_e( 'e.g. Pepperoni, Thin Crust, Marinara…', 'pizzatier' ); ?>" maxlength="100">
					<p class="plbw-help"><?php esc_html_e( 'The display name shown to customers.', 'pizzatier' ); ?></p>
				</div>

				<div class="plbw-field-row">
					<label for="plbw-slug" class="plbw-label"><?php esc_html_e( 'Slug', 'pizzatier' ); ?></label>
					<input type="text" id="plbw-slug" class="regular-text plbw-input" placeholder="<?php esc_attr_e( 'auto-generated from name', 'pizzatier' ); ?>" maxlength="60" pattern="[a-z0-9\-]+">
					<p class="plbw-help"><?php esc_html_e( 'URL-friendly identifier. Used in shortcodes and presets. Auto-generated if left blank.', 'pizzatier' ); ?></p>
				</div>

				<div class="plbw-field-row">
					<label for="plbw-description" class="plbw-label"><?php esc_html_e( 'Description', 'pizzatier' ); ?></label>
					<textarea id="plbw-description" class="plbw-input plbw-textarea" rows="3" placeholder="<?php esc_attr_e( 'Optional short description shown in the builder…', 'pizzatier' ); ?>" maxlength="500"></textarea>
				</div>

				<!-- Dynamic extra fields (shown based on type) -->
				<div id="plbw-extra-fields">

					<!-- Calories (toppings) -->
					<div class="plbw-field-row plbw-extra" data-for="toppings" style="display:none;">
						<label for="plbw-calories" class="plbw-label"><?php esc_html_e( 'Calories (per serving)', 'pizzatier' ); ?></label>
						<input type="number" id="plbw-calories" class="small-text plbw-input" min="0" max="9999" value="">
					</div>

					<!-- Thickness (crusts) -->
					<div class="plbw-field-row plbw-extra" data-for="crusts" style="display:none;">
						<label for="plbw-thickness" class="plbw-label"><?php esc_html_e( 'Thickness', 'pizzatier' ); ?></label>
						<select id="plbw-thickness" class="plbw-input plbw-select">
							<option value=""><?php esc_html_e( '— Select —', 'pizzatier' ); ?></option>
							<option value="thin"><?php esc_html_e( 'Thin', 'pizzatier' ); ?></option>
							<option value="medium"><?php esc_html_e( 'Medium', 'pizzatier' ); ?></option>
							<option value="thick"><?php esc_html_e( 'Thick', 'pizzatier' ); ?></option>
							<option value="stuffed"><?php esc_html_e( 'Stuffed', 'pizzatier' ); ?></option>
						</select>
					</div>

					<!-- Diameter (sizes) -->
					<div class="plbw-field-row plbw-extra" data-for="sizes" style="display:none;">
						<label for="plbw-diameter" class="plbw-label"><?php esc_html_e( 'Diameter (inches)', 'pizzatier' ); ?></label>
						<input type="number" id="plbw-diameter" class="small-text plbw-input" min="1" max="48" step="0.5" value="">
					</div>

					<!-- Spice level (sauces, drizzles) -->
					<div class="plbw-field-row plbw-extra" data-for="sauces drizzles" style="display:none;">
						<label for="plbw-spice" class="plbw-label"><?php esc_html_e( 'Spice Level', 'pizzatier' ); ?></label>
						<select id="plbw-spice" class="plbw-input plbw-select">
							<option value=""><?php esc_html_e( '— None —', 'pizzatier' ); ?></option>
							<option value="mild"><?php esc_html_e( 'Mild', 'pizzatier' ); ?></option>
							<option value="medium"><?php esc_html_e( 'Medium', 'pizzatier' ); ?></option>
							<option value="hot"><?php esc_html_e( 'Hot 🌶️', 'pizzatier' ); ?></option>
							<option value="extra_hot"><?php esc_html_e( 'Extra Hot 🌶️🌶️', 'pizzatier' ); ?></option>
						</select>
					</div>

					<!-- Dietary toggles -->
					<div class="plbw-field-row plbw-extra plbw-checkgroup" data-for="toppings crusts sauces cheeses drizzles" style="display:none;">
						<span class="plbw-label"><?php esc_html_e( 'Dietary Flags', 'pizzatier' ); ?></span>
						<div class="plbw-check-row">
							<label class="plbw-check-label plbw-extra" data-for="toppings sauces drizzles" style="display:none;">
								<input type="checkbox" id="plbw-is-vegetarian"> <?php esc_html_e( 'Vegetarian', 'pizzatier' ); ?>
							</label>
							<label class="plbw-check-label plbw-extra" data-for="toppings sauces cheeses drizzles" style="display:none;">
								<input type="checkbox" id="plbw-is-vegan"> <?php esc_html_e( 'Vegan', 'pizzatier' ); ?>
							</label>
							<label class="plbw-check-label plbw-extra" data-for="toppings crusts" style="display:none;">
								<input type="checkbox" id="plbw-is-gf"> <?php esc_html_e( 'Gluten-Free', 'pizzatier' ); ?>
							</label>
							<label class="plbw-check-label plbw-extra" data-for="cheeses" style="display:none;">
								<input type="checkbox" id="plbw-is-dairyfree"> <?php esc_html_e( 'Dairy-Free', 'pizzatier' ); ?>
							</label>
						</div>
					</div>

				</div><!-- #plbw-extra-fields -->

				<div class="plbw-field-row">
					<label for="plbw-sort-order" class="plbw-label"><?php esc_html_e( 'Sort Order', 'pizzatier' ); ?></label>
					<input type="number" id="plbw-sort-order" class="small-text plbw-input" min="0" value="0">
					<p class="plbw-help"><?php esc_html_e( 'Lower numbers appear first in the builder (0 = default).', 'pizzatier' ); ?></p>
				</div>

			</div><!-- .plbw-fields -->

			<div class="plbw-nav-row">
				<button type="button" class="button plbw-back-btn" data-target="1">
					<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button button-primary plbw-next-btn" id="plbw-step2-next">
					<?php esc_html_e( 'Next: Add Image', 'pizzatier' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- ── STEP 3: Image ────────────────────────────────────────── -->
		<div class="plbw-panel" id="plbw-panel-3" style="display:none;">
			<h2 class="plbw-panel-title"><?php esc_html_e( 'Layer Image', 'pizzatier' ); ?></h2>
			<p class="plbw-panel-sub"><?php esc_html_e( 'Upload or choose a transparent PNG image for this layer. You can skip this step and add an image later.', 'pizzatier' ); ?></p>

			<div class="plbw-image-area" id="plbw-image-area">
				<div class="plbw-image-preview" id="plbw-image-preview">
					<span class="dashicons dashicons-format-image plbw-img-icon"></span>
					<p><?php esc_html_e( 'No image selected', 'pizzatier' ); ?></p>
				</div>

				<div class="plbw-image-actions">
					<button type="button" class="button button-primary" id="plbw-choose-image">
						<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Choose from Media Library', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button" id="plbw-upload-image">
						<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload New Image', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button plbw-lim-btn" id="plbw-open-lim">
						<span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Layer Image Maker', 'pizzatier' ); ?>
					</button>
					<button type="button" class="button button-link-delete" id="plbw-remove-image" style="display:none;">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Remove Image', 'pizzatier' ); ?>
					</button>
				</div>

				<input type="hidden" id="plbw-image-id" value="">
				<input type="hidden" id="plbw-image-url" value="">

				<div class="plbw-image-tip">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'For best results, use a transparent PNG at 800×600px (4:3 ratio). Use the Layer Image Maker tool to prepare your image.', 'pizzatier' ); ?>
				</div>
			</div>

			<div class="plbw-nav-row">
				<button type="button" class="button plbw-back-btn" data-target="2">
					<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button button-primary" id="plbw-step3-next">
					<?php esc_html_e( 'Next: Review', 'pizzatier' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>
		</div>

		<!-- ── STEP 4: Review & Save ────────────────────────────────── -->
		<div class="plbw-panel" id="plbw-panel-4" style="display:none;">
			<h2 class="plbw-panel-title"><?php esc_html_e( 'Review & Save', 'pizzatier' ); ?></h2>
			<p class="plbw-panel-sub"><?php esc_html_e( 'Everything looks good? Hit Save Layer to create it.', 'pizzatier' ); ?></p>

			<div class="plbw-review-card" id="plbw-review-card">
				<!-- Filled by JS -->
			</div>

			<div class="plbw-nav-row">
				<button type="button" class="button plbw-back-btn" data-target="3">
					<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'pizzatier' ); ?>
				</button>
				<button type="button" class="button button-primary plbw-save-btn" id="plbw-save-btn">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Layer', 'pizzatier' ); ?>
				</button>
			</div>

			<!-- Saving spinner -->
			<div class="plbw-saving-overlay" id="plbw-saving-overlay" style="display:none;" aria-live="polite">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e( 'Saving your layer…', 'pizzatier' ); ?></p>
			</div>
		</div>

		<!-- ── SUCCESS PANEL ─────────────────────────────────────────── -->
		<div class="plbw-panel plbw-success-panel" id="plbw-success-panel" style="display:none;">
			<div class="plbw-success-inner" id="plbw-success-inner">
				<!-- Filled by JS -->
			</div>
		</div>

		</div><!-- .plbw-wrap -->

		<?php
		// Script is enqueued via AssetManager::enqueue_admin() and localised
		// as window.pizzatierLBW — see enqueue_admin() in AssetManager.php
		?>
		<?php
	}

	/** AJAX: save layer post */
	public function ajax_save_layer(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}
		check_ajax_referer( 'pizzatier_wizard_save', 'nonce' );

		$type     = isset( $_POST['type'] )  ? sanitize_key( wp_unslash( $_POST['type'] ) )    : '';
		$cpt      = isset( $_POST['cpt'] )   ? sanitize_key( wp_unslash( $_POST['cpt'] ) )     : '';
		$name     = isset( $_POST['name'] )  ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug     = isset( $_POST['slug'] )  ? sanitize_title( wp_unslash( $_POST['slug'] ) )  : '';
		$desc     = isset( $_POST['desc'] )  ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '';
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] )                 : 0;
		// Raw JSON — do NOT sanitize_text_field() here (it corrupts valid JSON).
		// Unslash only; each decoded value is sanitized individually below.
		$meta_raw = isset( $_POST['meta'] )  ? wp_unslash( $_POST['meta'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded + per-value sanitized below

		if ( ! $name || ! $cpt ) {
			wp_send_json_error( [ 'message' => __( 'Name and layer type are required.', 'pizzatier' ) ] );
		}

		// Validate CPT is one of ours
		$valid_cpts = [];
		foreach ( self::LAYER_TYPES as $cfg ) {
			$valid_cpts[] = $cfg['cpt'];
		}
		if ( ! in_array( $cpt, $valid_cpts, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid layer type.', 'pizzatier' ) ] );
		}

		// Create the post
		$post_id = wp_insert_post( [
			'post_title'   => $name,
			'post_name'    => $slug ?: sanitize_title( $name ),
			'post_content' => $desc,
			'post_type'    => $cpt,
			'post_status'  => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
		}

		// Save image
		if ( $image_id ) {
			// Validate the attachment exists and is an image before associating it
			$att_post = get_post( $image_id );
			if ( $att_post && $att_post->post_type === 'attachment' && strpos( (string) get_post_mime_type( $image_id ), 'image/' ) === 0 ) {
				update_post_meta( $post_id, '_pizzatier_layer_image_id', $image_id );
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		// Save meta fields
		$meta = json_decode( is_string( $meta_raw ) ? $meta_raw : '{}', true );
		if ( is_array( $meta ) ) {
			$allowed_meta = [ 'calories', 'thickness', 'diameter_inches', 'spice_level',
				'is_vegetarian', 'is_vegan', 'is_gluten_free', 'is_dairy_free', 'sort_order' ];
			foreach ( $allowed_meta as $key ) {
				if ( isset( $meta[ $key ] ) ) {
					update_post_meta( $post_id, '_pizzatier_' . $key, sanitize_text_field( $meta[ $key ] ) );
				}
			}
		}

		// Build shortcode
		$layer_type_map = [
			'toppings' => 'toppings',
			'crusts'   => 'crust',
			'sauces'   => 'sauce',
			'cheeses'  => 'cheese',
			'drizzles' => 'drizzle',
			'cuts'     => 'cut',
			'sizes'    => 'size',
		];
		$sc_type   = $layer_type_map[ $type ] ?? $type;
		$post_slug = get_post_field( 'post_name', $post_id );
		$shortcode = '[pizza_layer type="' . $sc_type . '" slug="' . $post_slug . '"]';

		wp_send_json_success( [
			'post_id'   => $post_id,
			'name'      => $name,
			'shortcode' => $shortcode,
			'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
			'list_url'  => admin_url( 'admin.php?page=pizzatier-content&pl_cpt=' . $type ),
		] );
	}

	private function render_styles(): void {
		?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
		<?php
	}
}
