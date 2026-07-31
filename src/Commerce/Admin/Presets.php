<?php
/**
 * PizzaTier — Pizza Presets admin UI
 *
 * Restyled to match the New Pizza Wizard UI (card grid, step header, wizard aesthetic).
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Presets {

	const META_KEY = '_pizzatier_preset_layers';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'init',                  [ $this, 'register_meta' ] );
		add_action( 'add_meta_boxes',        [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post',             [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Admin list-table columns for the pizzatier_presets CPT (Shortcode +
		// Layers). The CPT itself is registered by the base PizzaTier plugin;
		// these hooks only enrich its list view.
		add_filter( 'manage_pizzatier_presets_posts_columns',       [ $this, 'list_columns' ] );
		add_action( 'manage_pizzatier_presets_posts_custom_column', [ $this, 'list_column_content' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Admin list-table columns
	// -------------------------------------------------------------------------

	/**
	 * Insert "Shortcode" and "Layers" columns after the title column.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function list_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['shortcode'] = __( 'Shortcode', 'pizzatier' );
				$new['layers']    = __( 'Layers',    'pizzatier' );
			}
		}
		return $new;
	}

	/**
	 * Render the custom column cells.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function list_column_content( string $column, int $post_id ): void {
		if ( 'shortcode' === $column ) {
			$code = '[pizza_preset id="' . $post_id . '"]';
			echo '<code style="user-select:all;cursor:pointer;" onclick="this.select?.()" title="'
				. esc_attr__( 'Click to select', 'pizzatier' ) . '">'
				. esc_html( $code )
				. '</code>';
		}

		if ( 'layers' === $column ) {
			$meta = get_post_meta( $post_id, self::META_KEY, true );
			if ( ! is_array( $meta ) || empty( $meta ) ) {
				echo '<span style="color:#bbb">' . esc_html__( 'None configured', 'pizzatier' ) . '</span>';
				return;
			}
			$parts = [];
			$map   = [
				'crust_id'   => __( 'Crust',   'pizzatier' ),
				'sauce_id'   => __( 'Sauce',   'pizzatier' ),
				'cheese_id'  => __( 'Cheese',  'pizzatier' ),
				'drizzle_id' => __( 'Drizzle', 'pizzatier' ),
				'cut_id'     => __( 'Cut',     'pizzatier' ),
				'size_id'    => __( 'Size',    'pizzatier' ),
			];
			foreach ( $map as $key => $label ) {
				if ( ! empty( $meta[ $key ] ) ) {
					$p = get_post( (int) $meta[ $key ] );
					if ( $p ) {
						$parts[] = '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $p->post_title );
					}
				}
			}
			if ( ! empty( $meta['topping_ids'] ) && is_array( $meta['topping_ids'] ) ) {
				$names = [];
				foreach ( $meta['topping_ids'] as $tid ) {
					$p = get_post( (int) $tid );
					if ( $p ) { $names[] = esc_html( $p->post_title ); }
				}
				if ( $names ) {
					$parts[] = '<strong>' . esc_html__( 'Toppings', 'pizzatier' ) . ':</strong> ' . implode( ', ', $names );
				}
			}
			if ( $parts ) {
				echo '<div style="font-size:12px;line-height:1.6">' . implode( '<br>', $parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part is built from esc_html() output wrapped in literal markup.
			} else {
				echo '<span style="color:#bbb">' . esc_html__( 'No layers set', 'pizzatier' ) . '</span>';
			}
		}
	}

	// -------------------------------------------------------------------------
	// Register meta for REST API (block editor) persistence
	// -------------------------------------------------------------------------

	public function register_meta(): void {
		register_post_meta( 'pizzatier_presets', self::META_KEY, [
			'show_in_rest'      => [
				'schema' => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
			],
			'single'            => true,
			'type'              => 'object',
			'sanitize_callback' => [ $this, 'sanitize_meta' ],
			'auth_callback'     => function() {
				return current_user_can( 'edit_posts' );
			},
		] );
	}

	/**
	 * Sanitize the meta value (used by register_post_meta sanitize_callback).
	 */
	public function sanitize_meta( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}
		$sanitised    = [];
		$allowed_keys = [ 'crust_id', 'sauce_id', 'cheese_id', 'drizzle_id', 'cut_id', 'topping_ids', 'size_id', 'label' ];
		foreach ( $allowed_keys as $k ) {
			if ( ! isset( $value[ $k ] ) ) {
				continue;
			}
			if ( $k === 'topping_ids' ) {
				$sanitised[ $k ] = array_map( 'absint', (array) $value[ $k ] );
			} elseif ( $k === 'label' ) {
				$sanitised[ $k ] = sanitize_text_field( $value[ $k ] );
			} else {
				$sanitised[ $k ] = absint( $value[ $k ] );
			}
		}
		return $sanitised;
	}

	// -------------------------------------------------------------------------
	// Classic editor save (block editor uses register_meta / REST)
	// -------------------------------------------------------------------------

	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( 'pizzatier_presets' !== $post->post_type ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		if ( ! isset( $_POST['pizzatier_commerce_preset_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pizzatier_commerce_preset_nonce'] ) ), 'pizzatier_commerce_preset_save_' . $post_id ) ) { return; }

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below, per element, before use.
		$raw     = isset( $_POST['pizzatier_commerce_preset_layers'] ) ? wp_unslash( $_POST['pizzatier_commerce_preset_layers'] ) : '';
		if ( $raw === '' ) { return; }
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			update_post_meta( $post_id, self::META_KEY, $this->sanitize_meta( $decoded ) );
		}
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	public function add_meta_boxes(): void {
		add_meta_box(
			'pizzatier_commerce_preset_builder',
			__( 'Pizza Preset Builder', 'pizzatier' ),
			[ $this, 'render_meta_box' ],
			'pizzatier_presets',
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Scripts / Styles
	// -------------------------------------------------------------------------

	public function enqueue_scripts( string $hook ): void {
		global $post;
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		if ( ! $post || 'pizzatier_presets' !== $post->post_type ) {
			return;
		}
		wp_enqueue_script(
			'pizzatier-commerce-admin-presets',
			PIZZATIER_PLUGIN_URL . 'assets/js/admin-presets.js',
			[],
			PIZZATIER_VERSION,
			true
		);
		$raw   = get_post_meta( $post->ID, self::META_KEY, true );
		$state = is_array( $raw ) && ! empty( $raw ) ? $raw : null;
		wp_add_inline_script(
			'pizzatier-commerce-admin-presets',
			'window.pizzatier_commercePresets = ' . wp_json_encode( [
				'state'    => $state,
				'state_key' => self::META_KEY,
			] ) . ';',
			'before'
		);
	}



	// -------------------------------------------------------------------------
	// Meta box render
	// -------------------------------------------------------------------------

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'pizzatier_commerce_preset_save_' . $post->ID, 'pizzatier_commerce_preset_nonce' );

		$saved = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$layers     = $this->get_layer_options();
		$saved_json = wp_json_encode( $saved );
		?>

		<style>
		/* ── Preset Builder — Wizard-style UI ──────────────────────────── */
		.pztc-preset-page {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			max-width: 100%;
		}

		/* Page header bar */
		.pztc-preset-header {
			display: flex; align-items: center; gap: 14px;
			background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); border-radius: 10px;
			padding: 18px 22px; margin-bottom: 20px; border-bottom: 3px solid #ff6b35;
		}
		.pztc-preset-header__icon { font-size: 28px; flex-shrink: 0; }
		.pztc-preset-header__text h2 { color: #fff; margin: 0; font-size: 17px; }
		.pztc-preset-header__text p  { color: rgba(255,255,255,.55); margin: 2px 0 0; font-size: 13px; }

		/* Two-column layout: preview left, pickers right */
		.pztc-preset-wrap {
			display: grid;
			grid-template-columns: 280px 1fr;
			gap: 20px;
		}
		@media (max-width: 960px) { .pztc-preset-wrap { grid-template-columns: 1fr; } }

		/* ── Canvas column ───────────────────────────────────────────── */
		.pztc-canvas-col { display: flex; flex-direction: column; align-items: center; gap: 14px; }

		.pztc-canvas-wrap {
			position: relative;
			width: 240px; height: 240px;
			border-radius: 50%;
			overflow: hidden;
			background: #f5f5f5;
			border: 3px solid #e0e0e0;
			box-shadow: 0 4px 20px rgba(0,0,0,.12);
			flex-shrink: 0;
		}
		.pztc-canvas-wrap img {
			position: absolute; inset: 0;
			width: 100%; height: 100%;
			object-fit: cover; border-radius: 50%;
			display: none;
		}
		.pztc-canvas-wrap img.pztc-visible { display: block; }
		.pztc-canvas-empty {
			position: absolute; inset: 0;
			display: flex; align-items: center; justify-content: center;
			flex-direction: column; gap: 8px;
			color: #bbb; font-size: 13px; text-align: center;
		}
		.pztc-canvas-empty .dashicons { font-size: 48px !important; color: #ddd; }

		/* Legend chips */
		.pztc-layer-legend {
			display: flex; flex-wrap: wrap; justify-content: center; gap: 6px;
			max-width: 260px;
		}
		.pztc-legend-chip {
			display: inline-flex; align-items: center; gap: 4px;
			background: #fff; border: 1px solid #e0e0e0; border-radius: 20px;
			padding: 3px 10px; font-size: 11px; color: #555;
		}
		.pztc-legend-chip span { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

		/* Summary card */
		.pztc-preset-summary {
			width: 100%;
			background: #fff; border: 1px solid #e0e0e0; border-radius: 10px;
			padding: 14px 16px; font-size: 12px;
		}
		.pztc-preset-summary__title {
			font-size: 11px; font-weight: 700; text-transform: uppercase;
			letter-spacing: .05em; color: #888; margin: 0 0 8px;
		}
		.pztc-preset-summary__item {
			display: flex; justify-content: space-between; gap: 8px;
			padding: 4px 0; border-bottom: 1px solid #f5f5f5; font-size: 12px;
		}
		.pztc-preset-summary__item:last-child { border-bottom: none; }
		.pztc-preset-summary__label { color: #888; }
		.pztc-preset-summary__value { color: #1a1a2e; font-weight: 600; text-align: right; }
		#pztc-preset-empty-msg { color: #bbb; font-style: italic; font-size: 12px; }

		/* ── Picker column ───────────────────────────────────────────── */
		.pztc-picker-col { display: flex; flex-direction: column; gap: 2px; }

		/* Section cards — same as wizard card style */
		.pztc-psec {
			background: #fff;
			border: 1px solid #e8e8e8;
			border-radius: 10px;
			overflow: hidden;
		}

		.pztc-psec__header {
			display: flex; align-items: center; gap: 10px;
			padding: 12px 16px;
			background: #f9f9f9;
			border-bottom: 1px solid #eee;
			cursor: pointer; user-select: none;
		}
		.pztc-psec__header:hover { background: #f3f3f3; }
		.pztc-psec__dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
		.pztc-psec__title { font-size: 13px; font-weight: 700; color: #1a1a2e; flex: 1; }
		.pztc-psec__badge {
			font-size: 11px; font-weight: 700; color: #1a7a4a;
			background: #e8f5ee; border-radius: 20px; padding: 1px 8px;
		}
		.pztc-psec__sel {
			font-size: 11px; color: #888; font-style: italic; max-width: 120px;
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.pztc-psec__arrow { color: #aaa; font-size: 16px; transition: transform .2s; }
		.pztc-psec.pztc-open .pztc-psec__arrow { transform: rotate(180deg); }

		.pztc-psec__body { display: none; padding: 12px; }
		.pztc-psec.pztc-open .pztc-psec__body { display: block; }

		/* Item grid inside each section */
		.pztc-item-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));
			gap: 8px;
		}

		.pztc-item {
			display: flex; flex-direction: column; align-items: center; gap: 4px;
			cursor: pointer;
			padding: 8px 4px;
			border-radius: 10px;
			border: 2px solid transparent;
			transition: border-color .15s, background .15s;
			text-align: center;
		}
		.pztc-item:hover { border-color: #ff6b35; background: #fff7f3; }
		.pztc-item.pztc-selected { border-color: #ff6b35; background: #fff3ec; }
		.pztc-item.pztc-selected-topping { border-color: #1a7a4a; background: #f0fff5; }

		.pztc-item-thumb {
			width: 60px; height: 60px;
			border-radius: 50%;
			object-fit: cover;
			background: #f0f0f0;
			flex-shrink: 0;
		}
		.pztc-item-thumb-placeholder {
			width: 60px; height: 60px; border-radius: 50%;
			background: #f0f0f0;
			display: flex; align-items: center; justify-content: center;
			color: #ccc;
		}
		.pztc-item-label { font-size: 10px; color: #555; line-height: 1.2; max-width: 76px; word-break: break-word; }
		.pztc-item.pztc-selected .pztc-item-label { color: #ff6b35; font-weight: 700; }
		.pztc-item.pztc-selected-topping .pztc-item-label { color: #1a7a4a; font-weight: 700; }

		/* "None" option */
		.pztc-item-none .pztc-item-thumb-placeholder {
			border: 2px dashed #ccc; background: transparent; font-size: 18px;
		}

		/* No items notice */
		.pztc-no-items { font-size: 12px; color: #aaa; padding: 8px 4px; }
		.pztc-no-items a { color: #ff6b35; text-decoration: none; }
		</style>

		<input type="hidden" id="pizzatier_commerce_preset_layers_input" name="pizzatier_commerce_preset_layers" value="<?php echo esc_attr( $saved_json ); ?>">

		<div class="pztc-preset-page">

			<!-- Header -->
			<div class="pztc-preset-header">
				<span class="pztc-preset-header__icon">🍕</span>
				<div class="pztc-preset-header__text">
					<h2><?php esc_html_e( 'Pizza Preset Builder', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Click sections below to expand and select layers. The preview updates instantly.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<div class="pztc-preset-wrap">

				<!-- ── Canvas / Preview column ───────────────── -->
				<div class="pztc-canvas-col">
					<div class="pztc-canvas-wrap" id="pztc-preview-canvas">
						<div class="pztc-canvas-empty" id="pztc-canvas-empty">
							<span class="dashicons dashicons-food"></span>
							<span><?php esc_html_e( 'Select layers to preview', 'pizzatier' ); ?></span>
						</div>
						<?php
						$all_items = array_merge(
							$this->flatten_items( $layers['crust'],   'crust'   ),
							$this->flatten_items( $layers['sauce'],   'sauce'   ),
							$this->flatten_items( $layers['cheese'],  'cheese'  ),
							$this->flatten_items( $layers['drizzle'], 'drizzle' ),
							$this->flatten_items( $layers['cut'],     'cut'     ),
							$this->flatten_items( $layers['topping'], 'topping' )
						);
						foreach ( $all_items as $item ) :
							if ( empty( $item['img'] ) ) continue;
							?>
							<img
								src="<?php echo esc_url( $item['img'] ); ?>"
								data-layer-type="<?php echo esc_attr( $item['type'] ); ?>"
								data-layer-id="<?php echo esc_attr( $item['id'] ); ?>"
								alt="<?php echo esc_attr( $item['label'] ); ?>"
								class="pztc-layer-img"
							>
						<?php endforeach; ?>
					</div>

					<!-- Legend -->
					<div class="pztc-layer-legend">
						<?php
						$legend = [
							[ '#c2845a', __( 'Crust',   'pizzatier' ) ],
							[ '#e84c4c', __( 'Sauce',   'pizzatier' ) ],
							[ '#f5c842', __( 'Cheese',  'pizzatier' ) ],
							[ '#5aade8', __( 'Drizzle', 'pizzatier' ) ],
							[ '#aaa',    __( 'Cut',     'pizzatier' ) ],
							[ '#46b450', __( 'Toppings','pizzatier' ) ],
						];
						foreach ( $legend as [ $color, $label ] ) : ?>
							<span class="pztc-legend-chip">
								<span style="background:<?php echo esc_attr( $color ); ?>"></span>
								<?php echo esc_html( $label ); ?>
							</span>
						<?php endforeach; ?>
					</div>

					<!-- Summary card -->
					<div class="pztc-preset-summary">
						<p class="pztc-preset-summary__title"><?php esc_html_e( 'Preset Summary', 'pizzatier' ); ?></p>
						<div id="pztc-summary-rows">
							<p id="pztc-preset-empty-msg"><?php esc_html_e( 'No layers selected yet.', 'pizzatier' ); ?></p>
						</div>
					</div>
				</div>

				<!-- ── Picker column ─────────────────────────── -->
				<div class="pztc-picker-col">
					<?php
					$section_defs = [
						[
							'key'      => 'crust',
							'state_key' => 'crust_id',
							'label'    => __( 'Crust', 'pizzatier' ),
							'color'    => '#c2845a',
							'multi'    => false,
							'items'    => $layers['crust'],
							'cpt'      => 'pizzatier_crusts',
						],
						[
							'key'      => 'sauce',
							'state_key' => 'sauce_id',
							'label'    => __( 'Sauce', 'pizzatier' ),
							'color'    => '#e84c4c',
							'multi'    => false,
							'items'    => $layers['sauce'],
							'cpt'      => 'pizzatier_sauces',
						],
						[
							'key'      => 'cheese',
							'state_key' => 'cheese_id',
							'label'    => __( 'Cheese', 'pizzatier' ),
							'color'    => '#f5c842',
							'multi'    => false,
							'items'    => $layers['cheese'],
							'cpt'      => 'pizzatier_cheeses',
						],
						[
							'key'      => 'topping',
							'state_key' => 'topping_ids',
							'label'    => __( 'Toppings', 'pizzatier' ),
							'color'    => '#46b450',
							'multi'    => true,
							'items'    => $layers['topping'],
							'cpt'      => 'pizzatier_toppings',
						],
						[
							'key'      => 'drizzle',
							'state_key' => 'drizzle_id',
							'label'    => __( 'Drizzle', 'pizzatier' ),
							'color'    => '#5aade8',
							'multi'    => false,
							'items'    => $layers['drizzle'],
							'cpt'      => 'pizzatier_drizzles',
						],
						[
							'key'      => 'cut',
							'state_key' => 'cut_id',
							'label'    => __( 'Cut Style', 'pizzatier' ),
							'color'    => '#aaaaaa',
							'multi'    => false,
							'items'    => $layers['cut'],
							'cpt'      => 'pizzatier_cuts',
						],
						[
							'key'      => 'size',
							'state_key' => 'size_id',
							'label'    => __( 'Default Size', 'pizzatier' ),
							'color'    => '#9b59b6',
							'multi'    => false,
							'items'    => $layers['size'],
							'cpt'      => 'pizzatier_sizes',
						],
					];

					foreach ( $section_defs as $section ) :
						$section_items = $section['items'];
						$is_multi      = $section['multi'];
						$saved_val     = $saved[ $section['state_key'] ] ?? ( $is_multi ? [] : 0 );

						// Build header selection label(s)
						$selected_labels = [];
						if ( $is_multi ) {
							foreach ( (array) $saved_val as $tid ) {
								foreach ( $section_items as $it ) {
									if ( (int) $it['id'] === (int) $tid ) {
										$selected_labels[] = $it['label'];
										break;
									}
								}
							}
						} else {
							foreach ( $section_items as $it ) {
								if ( (int) $it['id'] === (int) $saved_val ) {
									$selected_labels[] = $it['label'];
									break;
								}
							}
						}

						$header_label = ! empty( $selected_labels )
							? implode( ', ', $selected_labels )
							: __( 'None', 'pizzatier' );

						$open_class = ! empty( $selected_labels ) ? ' pztc-open' : '';
						?>

						<div class="pztc-psec<?php echo esc_attr( $open_class ); ?>"
							 data-layer-key="<?php echo esc_attr( $section['key'] ); ?>"
							 data-meta-key="<?php echo esc_attr( $section['state_key'] ); ?>"
							 data-multi="<?php echo $is_multi ? '1' : '0'; ?>">

							<div class="pztc-psec__header" onclick="pizzatier_commerceToggleSection(this)">
								<span class="pztc-psec__dot" style="background:<?php echo esc_attr( $section['color'] ); ?>"></span>
								<span class="pztc-psec__title"><?php echo esc_html( $section['label'] ); ?></span>

								<?php if ( $is_multi && ! empty( $saved_val ) ) : ?>
									<span class="pztc-psec__badge"><?php echo esc_html( count( (array) $saved_val ) ); ?></span>
								<?php else : ?>
									<span class="pztc-psec__sel" id="pztc-sel-label-<?php echo esc_attr( $section['key'] ); ?>">
										<?php echo esc_html( $header_label ); ?>
									</span>
								<?php endif; ?>

								<span class="pztc-psec__arrow dashicons dashicons-arrow-down-alt2"></span>
							</div>

							<div class="pztc-psec__body">
								<?php if ( empty( $section_items ) ) : ?>
									<p class="pztc-no-items">
										<?php
										echo wp_kses(
											sprintf(
												/* translators: 1: layer type label, 2: CPT edit URL */
												__( 'No %1$s found. <a href="%2$s">Add some</a>.', 'pizzatier' ),
												esc_html( strtolower( $section['label'] ) ),
												esc_url( admin_url( 'edit.php?post_type=' . $section['cpt'] ) )
											),
											[ 'a' => [ 'href' => [] ] ]
										);
										?>
									</p>
								<?php else : ?>
									<div class="pztc-item-grid">

										<?php if ( ! $is_multi ) : ?>
											<div class="pztc-item pztc-item-none<?php echo ( (int) $saved_val === 0 ) ? ' pztc-selected' : ''; ?>"
												 data-id="0"
												 data-type="<?php echo esc_attr( $section['key'] ); ?>"
												 onclick="pizzatier_commerceSelectItem(this)">
												<div class="pztc-item-thumb-placeholder">✕</div>
												<span class="pztc-item-label"><?php esc_html_e( 'None', 'pizzatier' ); ?></span>
											</div>
										<?php endif; ?>

										<?php foreach ( $section_items as $item ) :
											if ( $is_multi ) {
												$is_sel = in_array( (int) $item['id'], array_map( 'intval', (array) $saved_val ), true );
											} else {
												$is_sel = ( (int) $saved_val === (int) $item['id'] );
											}
											$sel_class = $is_sel ? ( $is_multi ? ' pztc-selected-topping' : ' pztc-selected' ) : '';
											?>
											<div class="pztc-item<?php echo esc_attr( $sel_class ); ?>"
												 data-id="<?php echo esc_attr( $item['id'] ); ?>"
												 data-type="<?php echo esc_attr( $section['key'] ); ?>"
												 data-label="<?php echo esc_attr( $item['label'] ); ?>"
												 onclick="pizzatier_commerceSelectItem(this)">
												<?php if ( ! empty( $item['img'] ) ) : ?>
													<img src="<?php echo esc_url( $item['img'] ); ?>"
														 class="pztc-item-thumb"
														 alt="<?php echo esc_attr( $item['label'] ); ?>">
												<?php else : ?>
													<div class="pztc-item-thumb-placeholder">
														<span class="dashicons dashicons-format-image"></span>
													</div>
												<?php endif; ?>
												<span class="pztc-item-label"><?php echo esc_html( $item['label'] ); ?></span>
											</div>
										<?php endforeach; ?>

									</div>
								<?php endif; ?>
							</div>

						</div>

					<?php endforeach; ?>
				</div>

			</div><!-- .pztc-preset-wrap -->
		</div><!-- .pztc-preset-page -->

		<?php // Shortcode panel — shown once post has an ID. ?>
		<?php if ( $post->ID ) : ?>
		<div class="pztc-shortcode-panel" style="margin-top:20px;background:#f8f9fb;border:1px solid #e0e0e0;border-radius:10px;padding:18px 20px;">
			<style>
			.pztc-shortcode-panel__title { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0 0 10px; }
			.pztc-shortcode-panel__row { display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
			.pztc-shortcode-panel__code { flex:1;min-width:200px;font-family:monospace;font-size:14px;font-weight:600;background:#fff;border:2px solid #e0e0e0;border-radius:6px;padding:8px 12px;color:#1a1a2e;user-select:all;cursor:text; }
			.pztc-shortcode-panel__copy { display:inline-flex;align-items:center;gap:6px;background:#ff6b35;color:#fff;border:none;border-radius:6px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s;white-space:nowrap; }
			.pztc-shortcode-panel__copy:hover { background:#e05a28; }
			.pztc-shortcode-panel__copy.pztc-copied { background:#1a7a4a; }
			.pztc-shortcode-panel__hint { font-size:12px;color:#888;margin:8px 0 0;line-height:1.5; }
			.pztc-shortcode-panel__hint code { background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:1px 5px;font-size:11px; }
			</style>
			<p class="pztc-shortcode-panel__title"><?php esc_html_e( 'Preset Shortcode', 'pizzatier' ); ?></p>
			<div class="pztc-shortcode-panel__row">
				<span class="pztc-shortcode-panel__code" id="pztc-sc-display"
					onclick="this.focus();document.execCommand('selectAll',false,null)">
					<?php echo esc_html( '[pizza_preset id="' . $post->ID . '"]' ); ?>
				</span>
				<button type="button" class="pztc-shortcode-panel__copy" id="pztc-sc-copy-btn"
					onclick="pizzatier_commerceCopyShortcode()">
					<span class="dashicons dashicons-clipboard" style="font-size:15px;width:15px;height:15px;margin-top:1px"></span>
					<?php esc_html_e( 'Copy', 'pizzatier' ); ?>
				</button>
			</div>
			<p class="pztc-shortcode-panel__hint">
				<?php esc_html_e( 'Displays a static pizza image from this preset — no interactive builder. Paste into any page or post.', 'pizzatier' ); ?>
			</p>
			<table class="pztc-shortcode-panel__attrs" style="margin-top:8px;font-size:11px;border-collapse:collapse;width:100%">
				<tr>
					<td style="padding:2px 8px 2px 0;color:#888;white-space:nowrap;vertical-align:top"><code>template="metro"</code></td>
					<td style="color:#555"><?php esc_html_e( 'Template whose CSS styles the pizza stage (colorbox, metro, nightpieâ¦). Defaults to the active template.', 'pizzatier' ); ?></td>
				</tr>
				<tr>
					<td style="padding:2px 8px 2px 0;color:#888;white-space:nowrap;vertical-align:top"><code>size="300px"</code></td>
					<td style="color:#555"><?php esc_html_e( 'Max-width of the pizza display. Accepts px, %, em, rem, vw. Default: 400px.', 'pizzatier' ); ?></td>
				</tr>
				<tr>
					<td style="padding:2px 8px 2px 0;color:#888;white-space:nowrap;vertical-align:top"><code>align="center"</code></td>
					<td style="color:#555"><?php esc_html_e( 'Horizontal alignment: left, center, or right. Default: center.', 'pizzatier' ); ?></td>
				</tr>
				<tr>
					<td style="padding:2px 8px 2px 0;color:#888;white-space:nowrap;vertical-align:top"><code>title="yes"</code></td>
					<td style="color:#555"><?php esc_html_e( 'Show the preset name above the pizza. Default: no.', 'pizzatier' ); ?></td>
				</tr>
			</table>
		</div>
		<?php endif; ?>

		<?php // JS enqueued via wp_enqueue_script — see enqueue_assets() ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Data helpers
	// -------------------------------------------------------------------------

	private function get_layer_options(): array {
		$types = [
			'crust'   => 'pizzatier_crusts',
			'sauce'   => 'pizzatier_sauces',
			'cheese'  => 'pizzatier_cheeses',
			'topping' => 'pizzatier_toppings',
			'drizzle' => 'pizzatier_drizzles',
			'cut'     => 'pizzatier_cuts',
			'size'    => 'pizzatier_sizes',
		];

		$result = [];
		foreach ( $types as $key => $cpt ) {
			$posts = get_posts( [
				'post_type'   => $cpt,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			] );
			$result[ $key ] = array_map( function( $p ) {
				$thumb     = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
				$layer_img = get_post_meta( $p->ID, '_pizzatier_layer_image', true );
				return [
					'id'    => $p->ID,
					'label' => $p->post_title,
					'img'   => $layer_img ?: ( $thumb ?: '' ),
				];
			}, $posts );
		}
		return $result;
	}

	private function flatten_items( array $items, string $type ): array {
		foreach ( $items as &$item ) {
			$item['type'] = $type;
		}
		return $items;
	}
}
