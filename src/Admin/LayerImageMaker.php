<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Layer Image Maker
 *
 * An in-browser tool that lets the user upload an image, crop it to a pizza-
 * appropriate aspect ratio, apply adjustments (brightness, contrast, saturation,
 * hue, blur, sharpen, opacity), and download a transparent-background PNG ready
 * to use as a layer image.
 *
 * All processing is done entirely in the browser (Canvas API); nothing is sent
 * to the server from this page. The user can optionally send the result directly
 * to the WordPress media library via a separate AJAX upload action.
 */
class LayerImageMaker {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$aspect_raw = get_option( 'pizzatier_setting_pizza_aspect', '4 / 3' );
		// Normalise "4 / 3" → "4/3" for JS
		$aspect_js  = preg_replace( '/\s+/', '', $aspect_raw );

		wp_enqueue_media();   // needed so we can offer "Send to Media Library"
		?>
		<div class="wrap plim-wrap">
		<?php $this->render_styles(); ?>

		<!-- ══ Header ══════════════════════════════════════════════════ -->
		<div class="plim-header">
			<span class="dashicons dashicons-format-image plim-header__icon"></span>
			<div>
				<h1 class="plim-header__title"><?php esc_html_e( 'Layer Image Maker', 'pizzatier' ); ?></h1>
				<p class="plim-header__sub"><?php esc_html_e( 'Upload an image, crop it to the correct aspect ratio for your pizza layers, adjust colour and transparency, then download as a transparent PNG.', 'pizzatier' ); ?></p>
			</div>
		</div>

		<div class="plim-shell" id="plim-shell">

			<!-- ── Left panel: upload + controls ───────────────────────────── -->
			<aside class="plim-sidebar" id="plim-sidebar">

				<!-- Upload zone -->
				<div class="plim-card" id="plim-upload-card">
					<h2 class="plim-card-h"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Image Source', 'pizzatier' ); ?></h2>
					<div class="plim-drop-zone" id="plim-drop-zone" tabindex="0" role="button" aria-aria-label="<?php esc_attr_e( 'Upload image', 'pizzatier' ); ?>">
						<span class="dashicons dashicons-format-image plim-drop-icon"></span>
						<p><?php esc_html_e( 'Drop an image here, or click to browse', 'pizzatier' ); ?></p>
						<input type="file" id="plim-file-input" accept="image/*" style="display:none;">
					</div>
					<button type="button" class="button plim-media-btn" id="plim-media-btn">
						<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Choose from Media Library', 'pizzatier' ); ?>
					</button>
				</div>

				<!-- Aspect ratio -->
				<div class="plim-card" id="plim-ratio-card">
					<h2 class="plim-card-h"><span class="dashicons dashicons-image-crop"></span> <?php esc_html_e( 'Guide Overlay', 'pizzatier' ); ?></h2>
					<label class="plim-label">Aspect Ratio
						<select id="plim-aspect-preset" class="plim-select">
							<option value="1/1">1:1 — Square</option>
							<option value="4/3" selected>4:3 — Standard Pizza (default)</option>
							<option value="3/2">3:2 — Classic</option>
							<option value="16/9">16:9 — Wide</option>
							<option value="3/4">3:4 — Portrait</option>
							<option value="custom">Custom…</option>
						</select>
					</label>
					<div id="plim-custom-ratio-row" class="plim-custom-ratio-row" style="display:none;">
						<label class="plim-label-sm">W <input type="number" id="plim-ratio-w" min="1" max="99" value="4" class="plim-num-input"></label>
						<span class="plim-ratio-sep">:</span>
						<label class="plim-label-sm">H <input type="number" id="plim-ratio-h" min="1" max="99" value="3" class="plim-num-input"></label>
					</div>
					<label class="plim-label plim-toggle-row">
						<input type="checkbox" id="plim-show-guide" checked>
						<?php esc_html_e( 'Show pizza outline guide', 'pizzatier' ); ?>
					</label>
					<label class="plim-label plim-toggle-row" style="margin-top:4px;">
						<input type="checkbox" id="plim-show-thirds" checked>
						<?php esc_html_e( 'Show rule-of-thirds grid', 'pizzatier' ); ?>
					</label>
				</div>

				<!-- Adjustments -->
				<div class="plim-card" id="plim-adj-card">
					<h2 class="plim-card-h"><span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Adjustments', 'pizzatier' ); ?>
						<button type="button" class="plim-reset-adj button-link" id="plim-reset-adj" title="<?php esc_attr_e( 'Reset all adjustments', 'pizzatier' ); ?>">↺ <?php esc_html_e( 'Reset', 'pizzatier' ); ?></button>
					</h2>

					<?php
					$sliders = [
						[ 'id' => 'plim-brightness',  'label' => __( 'Brightness', 'pizzatier' ),  'min' => -100, 'max' => 100, 'def' => 0,   'unit' => '' ],
						[ 'id' => 'plim-contrast',    'label' => __( 'Contrast', 'pizzatier' ),    'min' => -100, 'max' => 100, 'def' => 0,   'unit' => '' ],
						[ 'id' => 'plim-saturation',  'label' => __( 'Saturation', 'pizzatier' ),  'min' => -100, 'max' => 100, 'def' => 0,   'unit' => '' ],
						[ 'id' => 'plim-hue',         'label' => __( 'Hue Shift', 'pizzatier' ),   'min' => -180, 'max' => 180, 'def' => 0,   'unit' => '°' ],
						[ 'id' => 'plim-blur',        'label' => __( 'Blur', 'pizzatier' ),        'min' => 0,    'max' => 20,  'def' => 0,   'unit' => 'px' ],
						[ 'id' => 'plim-sharpen',     'label' => __( 'Sharpen', 'pizzatier' ),     'min' => 0,    'max' => 10,  'def' => 0,   'unit' => '' ],
						[ 'id' => 'plim-opacity',     'label' => __( 'Opacity', 'pizzatier' ),     'min' => 0,    'max' => 100, 'def' => 100, 'unit' => '%' ],
					];
					foreach ( $sliders as $s ) {
						$mid = (string) $s['id'];
						$min = (int) $s['min'];
						$max = (int) $s['max'];
						$def = (int) $s['def'];
						$unit = (string) $s['unit'];
						?>
						<div class="plim-slider-row">
							<label class="plim-slider-label" for="<?php echo esc_attr( $mid ); ?>">
								<?php echo esc_html( $s['label'] ); ?>
								<span class="plim-slider-val" id="<?php echo esc_attr( $mid ); ?>-val"><?php echo esc_html( $def . $unit ); ?></span>
							</label>
							<input type="range" id="<?php echo esc_attr( $mid ); ?>" class="plim-slider" data-unit="<?php echo esc_attr( $unit ); ?>"
							       min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" value="<?php echo esc_attr( $def ); ?>" step="1">
						</div>
						<?php
					}
					?>

					<!-- Transparency tools -->
					<div class="plim-separator"></div>
					<label class="plim-label plim-toggle-row">
						<input type="checkbox" id="plim-remove-bg">
						<?php esc_html_e( 'Remove background (threshold)', 'pizzatier' ); ?>
					</label>
					<div id="plim-bg-row" class="plim-slider-row" style="display:none;">
						<label class="plim-slider-label" for="plim-bg-thresh">
							Threshold <span class="plim-slider-val" id="plim-bg-thresh-val">30</span>
						</label>
						<input type="range" id="plim-bg-thresh" class="plim-slider" min="1" max="128" value="30" step="1">
					</div>
					<label class="plim-label plim-toggle-row" id="plim-bg-invert-row" style="display:none;margin-top:4px;">
						<input type="checkbox" id="plim-bg-invert">
						<?php esc_html_e( 'Invert selection (keep background)', 'pizzatier' ); ?>
					</label>
				</div>

				<!-- Output -->
				<div class="plim-card" id="plim-out-card">
					<h2 class="plim-card-h"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export', 'pizzatier' ); ?></h2>
					<label class="plim-label"><?php esc_html_e( 'Output size', 'pizzatier' ); ?>
						<select id="plim-out-size" class="plim-select">
							<option value="512">512 px</option>
							<option value="1024" selected>1024 px</option>
							<option value="2048">2048 px</option>
							<option value="original">Original</option>
						</select>
					</label>
					<label class="plim-label" style="margin-top:6px;"><?php esc_html_e( 'File name', 'pizzatier' ); ?>
						<input type="text" id="plim-filename" class="plim-text-input" placeholder="layer-image" value="layer-image">
					</label>
					<div class="plim-out-btns">
						<button type="button" class="button button-primary plim-btn-full" id="plim-download-btn" disabled>
							<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download PNG', 'pizzatier' ); ?>
						</button>
						<button type="button" class="button plim-btn-full" id="plim-send-media-btn" disabled>
							<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Send to Media Library', 'pizzatier' ); ?>
						</button>
					</div>
					<p class="plim-out-note" id="plim-out-note"></p>
				</div>

			</aside><!-- /.plim-sidebar -->

			<!-- ── Right panel: canvas ──────────────────────────────────────── -->
			<main class="plim-canvas-area" id="plim-canvas-area">
				<div class="plim-canvas-toolbar" id="plim-canvas-toolbar">
					<span class="plim-tool-group">
						<button type="button" class="plim-tool-btn plim-tool-btn--active" id="plim-tool-crop" title="<?php esc_attr_e( 'Crop / pan (C)', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-image-crop"></span> <?php esc_html_e( 'Crop', 'pizzatier' ); ?>
						</button>
						<button type="button" class="plim-tool-btn" id="plim-tool-move" title="<?php esc_attr_e( 'Pan (M)', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-move"></span> <?php esc_html_e( 'Pan', 'pizzatier' ); ?>
						</button>
					</span>
					<span class="plim-tool-group">
						<button type="button" class="plim-tool-btn" id="plim-zoom-out" title="<?php esc_attr_e( 'Zoom out (-)', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-minus"></span>
						</button>
						<span class="plim-zoom-level" id="plim-zoom-level">100%</span>
						<button type="button" class="plim-tool-btn" id="plim-zoom-in" title="<?php esc_attr_e( 'Zoom in (+)', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-plus"></span>
						</button>
						<button type="button" class="plim-tool-btn" id="plim-zoom-fit" title="<?php esc_attr_e( 'Fit (F)', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-fullscreen-alt"></span>
						</button>
					</span>
					<span class="plim-tool-group">
						<button type="button" class="plim-tool-btn" id="plim-rotate-ccw" title="<?php esc_attr_e( 'Rotate 90° left', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-undo"></span>
						</button>
						<button type="button" class="plim-tool-btn" id="plim-rotate-cw" title="<?php esc_attr_e( 'Rotate 90° right', 'pizzatier' ); ?>">
							<span class="dashicons dashicons-redo"></span>
						</button>
						<button type="button" class="plim-tool-btn" id="plim-flip-h" title="<?php esc_attr_e( 'Flip horizontal', 'pizzatier' ); ?>">⇄</button>
						<button type="button" class="plim-tool-btn" id="plim-flip-v" title="<?php esc_attr_e( 'Flip vertical', 'pizzatier' ); ?>">⇅</button>
					</span>
					<span class="plim-tool-group plim-tool-group--right">
						<button type="button" class="plim-tool-btn" id="plim-undo-btn" title="<?php esc_attr_e( 'Undo (Ctrl+Z)', 'pizzatier' ); ?>" disabled>
							<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Undo', 'pizzatier' ); ?>
						</button>
						<span class="plim-img-info" id="plim-img-info"></span>
					</span>
				</div>

				<!-- Canvas stage -->
				<div class="plim-stage" id="plim-stage">
					<div class="plim-empty-state" id="plim-empty-state">
						<span class="dashicons dashicons-format-image" style="font-size:52px;width:52px;height:52px;color:#ddd;"></span>
						<p><?php esc_html_e( 'Upload an image to get started', 'pizzatier' ); ?></p>
					</div>
					<!-- Display canvas (what user sees) -->
					<canvas id="plim-canvas" style="display:none;"></canvas>
					<!-- Crop overlay SVG drawn over the canvas -->
					<svg id="plim-guide-svg" style="display:none;position:absolute;top:0;left:0;pointer-events:none;"></svg>
				</div>

				<!-- Preview strip -->
				<div class="plim-preview-strip" id="plim-preview-strip" style="display:none;">
					<div class="plim-preview-item">
						<div class="plim-preview-thumb plim-preview-thumb--dark" id="plim-preview-dark">
							<canvas id="plim-thumb-canvas-dark"></canvas>
						</div>
						<span class="plim-preview-label"><?php esc_html_e( 'On dark', 'pizzatier' ); ?></span>
					</div>
					<div class="plim-preview-item">
						<div class="plim-preview-thumb plim-preview-thumb--check" id="plim-preview-check">
							<canvas id="plim-thumb-canvas-check"></canvas>
						</div>
						<span class="plim-preview-label"><?php esc_html_e( 'Transparency', 'pizzatier' ); ?></span>
					</div>
					<div class="plim-preview-item">
						<div class="plim-preview-thumb plim-preview-thumb--pizza" id="plim-preview-pizza">
							<canvas id="plim-thumb-canvas-pizza"></canvas>
						</div>
						<span class="plim-preview-label"><?php esc_html_e( 'On pizza base', 'pizzatier' ); ?></span>
					</div>
					<div class="plim-preview-item">
						<div class="plim-preview-thumb plim-preview-thumb--light" id="plim-preview-light">
							<canvas id="plim-thumb-canvas-light"></canvas>
						</div>
						<span class="plim-preview-label"><?php esc_html_e( 'On light', 'pizzatier' ); ?></span>
					</div>
				</div>
			</main>

		</div><!-- /.plim-shell -->
		</div><!-- /.wrap -->

		<?php
		// Config passed to JS via wp_localize_script( 'pizzatier-layer-image-maker', 'pizzatierLimConfig', [...] ).
		// JS enqueued via wp_enqueue_script( 'pizzatier-layer-image-maker' ) in AssetManager::enqueue_admin().
	}

	// ── AJAX: receive base64 PNG, save to media library ──────────────────────
	public function ajax_upload_layer_image(): void {
		check_ajax_referer( 'pizzatier_layer_image_maker', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error( 'Forbidden' ); }

		$data     = isset( $_POST['data'] ) ? sanitize_text_field( wp_unslash( $_POST['data'] ) ) : '';
		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : 'layer-image.png';

		// Strip data-URI header
		if ( strpos( $data, 'base64,' ) !== false ) {
			[ , $data ] = explode( 'base64,', $data );
		}
		$raw = base64_decode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! $raw ) { wp_send_json_error( 'Bad data' ); }

		// Bound the decoded payload to avoid large memory allocation / oversized
		// writes. Defaults to the smaller of the upload limit and 8 MB.
		$max_bytes = (int) apply_filters( 'pizzatier_max_layer_image_bytes', min( (int) wp_max_upload_size(), 8 * 1024 * 1024 ) );
		if ( $max_bytes > 0 && strlen( $raw ) > $max_bytes ) {
			wp_send_json_error( 'Image too large' );
		}

		// Verify the decoded bytes are a real image before touching the filesystem.
		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = $finfo->buffer( $raw );
		if ( ! in_array( $real_mime, [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ], true ) ) {
			wp_send_json_error( 'Invalid image data' );
		}

		// Force a safe extension that matches the real MIME type.
		$ext_map  = [ 'image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'image/webp' => '.webp' ];
		$safe_ext = $ext_map[ $real_mime ];
		$filename = pathinfo( $filename, PATHINFO_FILENAME ) . $safe_ext;

		// Write temp file
		$tmp = wp_tempnam( $filename );
		file_put_contents( $tmp, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$attachment_id = media_handle_sideload(
			[
				'name'     => $filename,
				'type'     => $real_mime,
				'tmp_name' => $tmp,
				'error'    => 0,
				'size'     => strlen( $raw ),
			],
			0,
			'',
			[ 'post_title' => pathinfo( $filename, PATHINFO_FILENAME ) ]
		);

		wp_delete_file( $tmp );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( $attachment_id->get_error_message() );
		}

		$url = wp_get_attachment_url( $attachment_id );
		wp_send_json_success( [ 'id' => $attachment_id, 'url' => $url ] );
	}

	// ── Styles ───────────────────────────────────────────────────────────────
	private function render_styles(): void {
		?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
		<?php
	}

	// ── Script ───────────────────────────────────────────────────────────────
	// render_script() removed — JS extracted to assets/js/admin/layer-image-maker.js
	// and enqueued via AssetManager::enqueue_admin()
}
