<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Settings page — replaces all WP Customizer entries
 * with a native admin UI. Reads/writes the same option keys the
 * customizer used so front-end output is unchanged.
 */
class Settings {

	/** All option keys managed by this page. */
	private const OPTIONS = [
		// Pizza shape
		'pizzatier_setting_pizza_shape',
		'pizzatier_setting_pizza_aspect',
		'pizzatier_setting_pizza_radius',
		// Layer defaults
		'pizzatier_setting_crust_defaultcrust',
		'pizzatier_setting_sauce_defaultsauce',
		'pizzatier_setting_cheese_defaultcheese',
		'pizzatier_setting_drizzle_defaultdrizzle',
		'pizzatier_setting_cut_defaultcut',
		// Crust
		'pizzatier_setting_crust_padding',
		// Sauce
		'pizzatier_setting_sauce_padding',
		// Cheese
		'pizzatier_cheese_setting_cheesedistance',
		'pizzatier_setting_cheese_padding',
		// Toppings
		'pizzatier_setting_topping_maxtoppings',
		'pizzatier_setting_topping_fractions',
		// Plugin settings
		'pizzatier_setting_settings_demonotice',
		'pizzatier_setting_global_help_content',
		// Content & Data behaviour
		'pizzatier_setting_disable_content_hub',
		'pizzatier_setting_require_complete_data',
		// Pricing & Cart settings moved to PizzaTierPro:
		//   pizzatier_setting_price_display_mode → pztpro_get_setting('price_display_mode')
		//   pizzatier_setting_price_base         → pztpro_get_setting('price_base')
		//   pizzatier_setting_price_currency_pos → pztpro_get_setting('price_currency_pos')
		//   pizzatier_setting_price_update_anim  → pztpro_get_setting('price_update_anim')
		//   pizzatier_setting_price_show_cart_btn  → pztpro_get_setting('show_cart_btn')
		//   pizzatier_setting_price_cart_btn_text  → pztpro_get_setting('cart_btn_text')
		//   pizzatier_setting_price_require_crust  → pztpro_get_setting('require_crust')
		//   pizzatier_setting_price_require_sauce  → pztpro_get_setting('require_sauce')
		//   pizzatier_setting_price_min_order      → pztpro_get_setting('min_order')
		//   pizzatier_setting_price_tax_display    → pztpro_get_setting('tax_display')
		// Accessibility & Performance
		'pizzatier_setting_a11y_reduce_motion',
		'pizzatier_setting_a11y_high_contrast',
		'pizzatier_setting_perf_lazy_load',
		'pizzatier_setting_perf_preload_assets',
		// Customer Experience
		'pizzatier_setting_cx_show_summary',
		'pizzatier_setting_cx_toast_style',
		'pizzatier_setting_cx_toast_duration',
		'pizzatier_setting_cx_text_added',
		'pizzatier_setting_cx_text_removed',
		'pizzatier_setting_cx_text_max_toppings',
		'pizzatier_setting_cx_show_start_over',
		'pizzatier_setting_cx_start_over_label',
		'pizzatier_setting_cx_special_instructions',
		'pizzatier_setting_cx_special_instr_placeholder',
		'pizzatier_setting_cx_special_instr_max',
		'pizzatier_setting_cx_review_modal',
		// Advanced & Developer
		'pizzatier_setting_adv_debug_mode',
		'pizzatier_setting_adv_disable_css',
		'pizzatier_setting_adv_rest_api_enabled',
		'pizzatier_setting_adv_rest_cache_ttl',
		'pizzatier_setting_adv_log_level',
		// Plainlist template settings
		'plainlist_setting_layout_mode',
		'plainlist_setting_accent_color',
		'plainlist_setting_bg_color',
		'plainlist_setting_section_header_color',
		'plainlist_setting_item_text_color',
		'plainlist_setting_divider_color',
		'plainlist_setting_font_family',
		'plainlist_setting_base_font_size',
		'plainlist_setting_heading_size',
		'plainlist_setting_heading_weight',
		'plainlist_setting_text_transform',
		'plainlist_setting_check_style',
		'plainlist_setting_check_size',
		'plainlist_setting_max_width',
		'plainlist_setting_section_gap',
		'plainlist_setting_item_gap',
		'plainlist_setting_columns',
		'plainlist_setting_show_dividers',
		'plainlist_setting_show_section_icons',
		'plainlist_setting_show_item_count',
		'plainlist_setting_show_summary',
		'plainlist_setting_show_reset',
		'plainlist_setting_step_btn_label_next',
		'plainlist_setting_step_btn_label_prev',
		'plainlist_setting_step_show_progress',
		'plainlist_setting_step_require_selection',
		'plainlist_setting_intro_text',
		'plainlist_setting_footer_note',
		'plainlist_setting_summary_heading',
		'plainlist_setting_reset_label',
		// Plainlist — list-row style additions
		'plainlist_setting_list_style',
		'plainlist_setting_selected_style',
		'plainlist_setting_row_padding',
		'plainlist_setting_label_weight',
		// Plainlist — Add-to-Cart button (PizzaTierPro checkout bar)
		'plainlist_setting_cart_btn_text',
		'plainlist_setting_cart_btn_style',
		'plainlist_setting_cart_btn_size',
		'plainlist_setting_cart_btn_bg',
		'plainlist_setting_cart_btn_text_color',
		'plainlist_setting_cart_btn_radius',
		'plainlist_setting_cart_btn_full_width',
		// Scaffold template settings
		'scaffold_setting_accent_color',
		'scaffold_setting_bg_color',
		'scaffold_setting_text_color',
		'scaffold_setting_border_color',
		'scaffold_setting_font_family',
		'scaffold_setting_font_custom',
		'scaffold_setting_base_font_size',
		'scaffold_setting_builder_width',
		'scaffold_setting_tab_style',
		'scaffold_setting_thumb_size',
		'scaffold_setting_card_radius',
		'scaffold_setting_card_cols',
		'scaffold_setting_show_labels',
		'scaffold_setting_anim_speed',
		'scaffold_setting_summary_title',
		// Scaffold — Add-to-Cart button (PizzaTierPro checkout bar)
		'scaffold_setting_cta_text',
		'scaffold_setting_cta_show_icon',
		// Active template — stored separately from Settings page but exported/imported here
		'pizzatier_setting_global_template',
	];

	/**
	 * Public accessor for the full list of option keys this plugin manages.
	 *
	 * Used by the Site Migration tool to walk every persisted setting.
	 * Returns the canonical key list — callers should treat this as read-only.
	 *
	 * @return string[]
	 */
	public static function get_option_keys(): array {
		return self::OPTIONS;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Import: read uploaded JSON
		$import_msg = '';
		if ( isset( $_POST['pizzatier_import_settings'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_settings_save' ) ) {
			$import_msg = $this->import_settings();
		}

		// Save
		if ( isset( $_POST['pizzatier_settings_save'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_settings_save' ) ) {
			$this->save_settings();
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Settings saved.', 'pizzatier' ) . '</strong></p></div>';
		}

		if ( $import_msg ) {
			echo $import_msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped inside import_settings().
		}

		// Load CPT options for dropdowns
		$q = [ 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
		$crusts   = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_crusts'   ] ) );
		$sauces   = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_sauces'   ] ) );
		$cheeses  = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_cheeses'  ] ) );
		$drizzles = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_drizzles' ] ) );
		$cuts     = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_cuts'     ] ) );

		$g = fn( string $key, string $default = '' ) => (string) get_option( $key, $default );

		// Active template
		$active_template = (string) get_option( 'pizzatier_setting_global_template', '' );

		// Load template settings if available
		$template_settings = [];
		if ( $active_template ) {
			$tpl_dirs = [
				get_stylesheet_directory() . '/pzttemplates/' . $active_template . '/',
				PIZZATIER_TEMPLATES_DIR . $active_template . '/',
			];
			foreach ( $tpl_dirs as $dir ) {
				$options_file = $dir . 'pztp-template-options.php';
				if ( file_exists( $options_file ) ) {
					$template_settings = include $options_file;
					if ( ! is_array( $template_settings ) ) { $template_settings = []; }
					break;
				}
			}
		}

		?>
		<div class="wrap pset-wrap">
		<?php $this->render_styles(); ?>

		<div class="pset-header" style="display:flex;align-items:center;gap:16px;justify-content:space-between;flex-wrap:wrap;">
			<div style="display:flex;align-items:center;gap:16px;">
				<span class="dashicons dashicons-admin-settings pset-header__icon"></span>
				<div>
					<h1 class="pset-header__title"><?php esc_html_e( 'Settings', 'pizzatier' ); ?></h1>
					<p class="pset-header__sub"><?php esc_html_e( 'All plugin settings in one place. New to PizzaTier? Try the Settings Wizard for a plain-English guided walk-through.', 'pizzatier' ); ?></p>
				</div>
			</div>
			<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
				<!-- ── Simple / Advanced mode toggle ─────────────────────────
				     Purely client-side — state lives in localStorage under
				     'pset_user_mode'. Adds `pset-mode-simple` to .pset-wrap
				     when active; CSS then swaps technical descriptions for
				     plain-English siblings. Default is 'advanced' so the
				     current UI is unchanged for existing users. -->
				<div class="pset-mode-toggle" role="group" aria-label="<?php esc_attr_e( 'Description style', 'pizzatier' ); ?>">
					<button type="button" class="pset-mode-btn" data-pset-mode="simple" aria-pressed="false">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Simple', 'pizzatier' ); ?>
					</button>
					<button type="button" class="pset-mode-btn pset-mode-btn--active" data-pset-mode="advanced" aria-pressed="true">
						<span class="dashicons dashicons-editor-code"></span>
						<?php esc_html_e( 'Advanced', 'pizzatier' ); ?>
					</button>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-wizard' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;white-space:nowrap;">
					<span class="dashicons dashicons-welcome-learn-more" style="margin-top:3px;margin-right:4px;"></span>
					<?php esc_html_e( '✦ Settings Wizard', 'pizzatier' ); ?>
				</a>
			</div>
		</div>

		<!-- ── Quick-jump pill nav ─────────────────────────────────────── -->
		<nav class="pset-quickjump" aria-label="Jump to section">
			<?php
			$pset_sections = [
				'default-layers'      => [ 'dashicons-category',        __( 'Default Layers', 'pizzatier' ) ],
				'toppings'            => [ 'dashicons-star-filled',      __( 'Toppings', 'pizzatier' ) ],
				'pizza-shape'         => [ 'dashicons-image-crop',       __( 'Pizza Shape', 'pizzatier' ) ],
				'crust-options'       => [ 'dashicons-tag',              __( 'Crust', 'pizzatier' ) ],
				'sauce-cheese'        => [ 'dashicons-admin-generic',    __( 'Sauce & Cheese', 'pizzatier' ) ],
				'plugin-settings'     => [ 'dashicons-info-outline',     __( 'Plugin', 'pizzatier' ) ],
				'content-data'        => [ 'dashicons-database',         __( 'Content & Data', 'pizzatier' ) ],
				'pricing-cart'        => [ 'dashicons-cart',             __( 'Pricing', 'pizzatier' ) ],
				'accessibility-perf'  => [ 'dashicons-universal-access', __( 'A11y & Perf', 'pizzatier' ) ],
				'customer-experience' => [ 'dashicons-smiley',           __( 'Customer UX', 'pizzatier' ) ],
				'advanced-dev'        => [ 'dashicons-editor-code',      __( 'Advanced', 'pizzatier' ) ],
				'data-backup'         => [ 'dashicons-database-import',   __( 'Import/Export', 'pizzatier' ) ],
			];
			if ( $active_template ) {
				$pset_sections['template-settings'] = [ 'dashicons-admin-appearance', ucwords( str_replace( '-', ' ', $active_template ) ) . ' Template' ];
			}
			foreach ( $pset_sections as $pset_slug => [ $pset_icon, $pset_label ] ) :
			?>
			<a href="#pset-body-<?php echo esc_attr( $pset_slug ); ?>" class="pset-quickjump__pill" data-section="<?php echo esc_attr( $pset_slug ); ?>">
				<span class="dashicons <?php echo esc_attr( $pset_icon ); ?>"></span>
				<?php echo esc_html( $pset_label ); ?>
			</a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="" id="pset-form" enctype="multipart/form-data">
		<?php wp_nonce_field( 'pizzatier_settings_save' ); ?>
		<input type="hidden" name="pizzatier_settings_save" value="1">

		<div class="pset-layout">
		<div class="pset-main">

		<!-- ══ Section: Default Layers ═══════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="default-layers">
				<div>
					<h2><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Default Layers', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'These layers are pre-selected when the builder loads, unless overridden by the shortcode attribute.', 'pizzatier' ); ?></p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-default-layers"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-default-layers">
				<div class="pset-grid pset-grid--layers">
					<?php $this->render_layer_picker( __( 'Default Crust',   'pizzatier' ), 'pizzatier_setting_crust_defaultcrust',     $crusts,   $g('pizzatier_setting_crust_defaultcrust') ); ?>
					<?php $this->render_layer_picker( __( 'Default Sauce',   'pizzatier' ), 'pizzatier_setting_sauce_defaultsauce',     $sauces,   $g('pizzatier_setting_sauce_defaultsauce') ); ?>
					<?php $this->render_layer_picker( __( 'Default Cheese',  'pizzatier' ), 'pizzatier_setting_cheese_defaultcheese',   $cheeses,  $g('pizzatier_setting_cheese_defaultcheese') ); ?>
					<?php $this->render_layer_picker( __( 'Default Drizzle', 'pizzatier' ), 'pizzatier_setting_drizzle_defaultdrizzle', $drizzles, $g('pizzatier_setting_drizzle_defaultdrizzle') ); ?>
					<?php $this->render_layer_picker( __( 'Default Cut',     'pizzatier' ), 'pizzatier_setting_cut_defaultcut',         $cuts,     $g('pizzatier_setting_cut_defaultcut') ); ?>
				</div>
			</div>
		</div>

		<!-- ══ Layer Picker Modal ═════════════════════════════════ -->
		<div id="pset-layer-modal" class="pset-modal" role="dialog" aria-modal="true" aria-label="Choose layer" style="display:none;">
			<div class="pset-modal__backdrop"></div>
			<div class="pset-modal__box">
				<div class="pset-modal__head">
					<h3 id="pset-modal-title" class="pset-modal__title"><?php esc_html_e( 'Choose a layer', 'pizzatier' ); ?></h3>
					<button type="button" class="pset-modal__close" aria-label="<?php esc_attr_e( 'Close', 'pizzatier' ); ?>">&times;</button>
				</div>
				<div class="pset-modal__search-wrap">
					<span class="dashicons dashicons-search pset-modal__search-icon"></span>
					<input type="text" id="pset-modal-search" class="pset-modal__search" placeholder="<?php esc_attr_e( 'Searchâ¦', 'pizzatier' ); ?>" autocomplete="off">
				</div>
				<div id="pset-modal-grid" class="pset-modal__grid"></div>
				<div class="pset-modal__foot">
					<button type="button" class="pset-modal__clear button">
						<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Clear selection', 'pizzatier' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- ══ Section: Toppings ═════════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="toppings">
				<div>
					<h2><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Toppings', 'pizzatier' ); ?></h2>
					<p>Controls how many toppings customers can add and what pizza fractions are available.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-toppings"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-toppings">
				<div class="pset-grid">
					<div class="pset-field">
						<label><?php esc_html_e( 'Max Toppings', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Maximum number of toppings a customer can add. 0 = unlimited.', 'pizzatier' ); ?></p>
						<input type="number" name="pizzatier_setting_topping_maxtoppings" min="0"
						       value="<?php echo esc_attr( $g('pizzatier_setting_topping_maxtoppings') ); ?>" class="pset-input">
					</div>
					<div class="pset-field pset-field--full">
						<label><?php esc_html_e( 'Topping Portions', 'pizzatier' ); ?></label>
						<p class="pset-desc">Choose which coverage options are available when customers apply toppings. <strong>Whole</strong> is always shown first. Uncheck any portions you do not want to offer.</p>
						<?php
						$_saved_fractions = get_option( 'pizzatier_setting_topping_fractions', [] );
						if ( ! is_array( $_saved_fractions ) ) {
							// Migrate legacy single-value string → array
							$_lv = (string) $_saved_fractions;
							$_saved_fractions = [ 'whole' ];
							if ( $_lv === 'halves' || $_lv === 'quarters' ) {
								$_saved_fractions[] = 'half-left';
								$_saved_fractions[] = 'half-right';
							}
							if ( $_lv === 'quarters' ) {
								$_saved_fractions[] = 'quarter-top-left';
								$_saved_fractions[] = 'quarter-top-right';
								$_saved_fractions[] = 'quarter-bottom-left';
								$_saved_fractions[] = 'quarter-bottom-right';
							}
						}
						if ( empty( $_saved_fractions ) ) {
							$_saved_fractions = [ 'whole', 'half-left', 'half-right', 'quarter-top-left', 'quarter-top-right', 'quarter-bottom-left', 'quarter-bottom-right' ];
						}
						$_fraction_opts = [
							'whole'                => [ 'Whole',    'dashicons-marker',           'Always available — the full pizza.' ],
							'half-left'            => [ 'Left ½',   'dashicons-arrow-left-alt2',  'Left half of the pizza.' ],
							'half-right'           => [ 'Right ½',  'dashicons-arrow-right-alt2', 'Right half of the pizza.' ],
							'quarter-top-left'     => [ 'Q1 ↖',     'dashicons-editor-ul',        'Top-left quarter.' ],
							'quarter-top-right'    => [ 'Q2 ↗',     'dashicons-editor-ul',        'Top-right quarter.' ],
							'quarter-bottom-left'  => [ 'Q3 ↙',     'dashicons-editor-ul',        'Bottom-left quarter.' ],
							'quarter-bottom-right' => [ 'Q4 ↘',     'dashicons-editor-ul',        'Bottom-right quarter.' ],
						];
						?>
						<div class="pset-portions-grid">
							<?php foreach ( $_fraction_opts as $_fv => [ $_fl, $_fi, $_fd ] ) : ?>
							<label class="pset-portion-box<?php echo in_array( $_fv, $_saved_fractions, true ) ? ' pset-portion-box--on' : ''; ?>"
							       title="<?php echo esc_attr( $_fd ); ?>">
								<input type="checkbox"
								       name="pizzatier_setting_topping_fractions[]"
								       value="<?php echo esc_attr( $_fv ); ?>"
								       <?php checked( in_array( $_fv, $_saved_fractions, true ) ); ?>
								       <?php echo $_fv === 'whole' ? 'disabled checked' : ''; ?>>
								<span class="pset-portion-box__label"><?php echo esc_html( $_fl ); ?></span>
							</label>
							<?php endforeach; ?>
						</div>
						<p class="pset-desc" style="margin-top:6px;">
							<em>Whole is always enabled. Changes here affect all templates and the fraction picker shown to customers.</em>
						</p>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Pizza Shape ══════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="pizza-shape">
				<div>
					<h2><span class="dashicons dashicons-image-crop"></span> <?php esc_html_e( 'Pizza Shape', 'pizzatier' ); ?></h2>
					<p>Controls the shape of the pizza preview in the builder. Can be overridden per-shortcode with <code>pizza_shape="..."</code>.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-pizza-shape"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-pizza-shape">
				<div class="pset-grid">
					<div class="pset-field">
						<label><?php esc_html_e( 'Shape Preset', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Choose a shape for the pizza visualizer.', 'pizzatier' ); ?></p>
						<select name="pizzatier_setting_pizza_shape" class="pset-select" id="pset-pizza-shape">
							<?php foreach ( [
								'round'     => '⬤ Round (circle)',
								'square'    => '■ Square (rounded corners)',
								'rectangle' => '▬ Rectangle / Oval',
								'custom'    => '✦ Custom (set aspect ratio & radius below)',
							] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>"<?php selected( $g('pizzatier_setting_pizza_shape', 'round'), $v ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="pset-field pset-shape-custom">
						<label>Aspect Ratio <span class="pset-hint">(rectangle &amp; custom)</span></label>
						<p class="pset-desc pset-desc--adv">CSS <code>aspect-ratio</code> value, e.g. <code>4 / 3</code>, <code>16 / 9</code>, <code>3 / 4</code>.</p>
						<p class="pset-desc pset-desc--simple">How stretched or squashed the pizza area is. Write it as two numbers separated by a slash — <code>4 / 3</code> is a little wider than tall, <code>1 / 1</code> is a perfect square.</p>
						<input type="text" name="pizzatier_setting_pizza_aspect"
						       value="<?php echo esc_attr( $g('pizzatier_setting_pizza_aspect', '4 / 3') ); ?>" class="pset-input" placeholder="4 / 3">
					</div>
					<div class="pset-field pset-shape-custom">
						<label>Border Radius <span class="pset-hint">(custom shape only)</span></label>
						<p class="pset-desc pset-desc--adv">CSS <code>border-radius</code>, e.g. <code>8px</code>, <code>50%</code>, <code>12px 40px</code>.</p>
						<p class="pset-desc pset-desc--simple">How rounded the corners are. <code>8px</code> is slightly rounded, <code>50%</code> makes it a full circle. Leave blank for the shape's default.</p>
						<input type="text" name="pizzatier_setting_pizza_radius"
						       value="<?php echo esc_attr( $g('pizzatier_setting_pizza_radius', '8px') ); ?>" class="pset-input" placeholder="8px">
					</div>
				</div>
				<!-- Live preview of shape -->
				<div style="margin-top:16px;">
					<p class="pset-desc" style="margin-bottom:6px;">Shape preview:</p>
					<div id="pset-shape-preview" style="
						width:80px; height:80px; background:linear-gradient(135deg,#ff8c42,#ff5722);
						border-radius:50%; transition:all 0.35s cubic-bezier(0.34,1.2,0.64,1);
						display:inline-block; vertical-align:middle; box-shadow:0 4px 16px rgba(0,0,0,0.25);
					"></div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Crust Options ════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="crust-options">
				<div>
					<h2><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Crust Options', 'pizzatier' ); ?></h2>
					<p>Fine-tune how the crust layer is sized and spaced in the visualizer.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-crust-options"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-crust-options">
				<p class="pset-desc" style="padding:0 22px 4px;margin:0;color:#646970;font-size:12px;">
					<span class="dashicons dashicons-info-outline" style="font-size:13px;vertical-align:middle;"></span>
					Crust shape and aspect ratio are controlled globally in <a href="<?php echo esc_url(admin_url('admin.php?page=pizzatier-settings#pset-body-pizza-shape')); ?>">Pizza Shape settings</a>.
				</p>
				<div class="pset-grid">
					<div class="pset-field">
						<label>Crust Padding
							<span class="pset-hint" id="pset-spc-crust_padding-lbl">(<?php echo esc_html( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_crust_padding','0')) ); ?>px)</span>
						</label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Inset padding applied to the crust layer image.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'How much space to leave around the crust so it sits nicely inside the pizza circle. Bigger numbers mean the crust shows up smaller.', 'pizzatier' ); ?></p>
						<div class="pset-range__wrap">
							<input type="range" id="pset-spc-crust_padding-range" min="0" max="80" step="1"
							       value="<?php echo esc_attr( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_crust_padding','0')) ); ?>"
							       class="pset-range__slider pset-spacing-range"
							       data-target="pset-spc-crust_padding-text" data-label="pset-spc-crust_padding-lbl">
							<input type="text" id="pset-spc-crust_padding-text" name="pizzatier_setting_crust_padding"
							       value="<?php echo esc_attr( $g('pizzatier_setting_crust_padding') ?: '0px' ); ?>"
							       class="pset-range__val pset-spacing-text"
							       data-range="pset-spc-crust_padding-range" data-label="pset-spc-crust_padding-lbl"
							       placeholder="0px" style="width:72px;">
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Sauce / Cheese Options ═══════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="sauce-cheese">
				<div>
					<h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Sauce &amp; Cheese Options', 'pizzatier' ); ?></h2>
					<p>Adjust padding and inset distances for the sauce and cheese layers.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-sauce-cheese"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-sauce-cheese">
				<div class="pset-grid">
					<div class="pset-field">
						<label>Sauce Padding
							<span class="pset-hint" id="pset-spc-sauce_padding-lbl">(<?php echo esc_html( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_sauce_padding','0')) ); ?>px)</span>
						</label>
						<p class="pset-desc"><?php esc_html_e( 'Padding between sauce and crust edge.', 'pizzatier' ); ?></p>
						<div class="pset-range__wrap">
							<input type="range" id="pset-spc-sauce_padding-range" min="0" max="80" step="1"
							       value="<?php echo esc_attr( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_sauce_padding','0')) ); ?>"
							       class="pset-range__slider pset-spacing-range"
							       data-target="pset-spc-sauce_padding-text" data-label="pset-spc-sauce_padding-lbl">
							<input type="text" id="pset-spc-sauce_padding-text" name="pizzatier_setting_sauce_padding"
							       value="<?php echo esc_attr( $g('pizzatier_setting_sauce_padding') ?: '0px' ); ?>"
							       class="pset-range__val pset-spacing-text"
							       data-range="pset-spc-sauce_padding-range" data-label="pset-spc-sauce_padding-lbl"
							       placeholder="0px" style="width:72px;">
						</div>
					</div>
					<div class="pset-field">
						<label>Cheese Distance from Edge
							<span class="pset-hint" id="pset-spc-cheese_dist-lbl">(<?php echo esc_html( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_cheese_setting_cheesedistance','0')) ); ?>px)</span>
						</label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'How far inset the cheese layer is.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'How far in from the edge the cheese appears. Bigger numbers make the cheese cover less of the pizza.', 'pizzatier' ); ?></p>
						<div class="pset-range__wrap">
							<input type="range" id="pset-spc-cheese_dist-range" min="0" max="80" step="1"
							       value="<?php echo esc_attr( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_cheese_setting_cheesedistance','0')) ); ?>"
							       class="pset-range__slider pset-spacing-range"
							       data-target="pset-spc-cheese_dist-text" data-label="pset-spc-cheese_dist-lbl">
							<input type="text" id="pset-spc-cheese_dist-text" name="pizzatier_cheese_setting_cheesedistance"
							       value="<?php echo esc_attr( $g('pizzatier_cheese_setting_cheesedistance') ?: '0px' ); ?>"
							       class="pset-range__val pset-spacing-text"
							       data-range="pset-spc-cheese_dist-range" data-label="pset-spc-cheese_dist-lbl"
							       placeholder="0px" style="width:72px;">
						</div>
					</div>
					<div class="pset-field">
						<label>Cheese Padding
							<span class="pset-hint" id="pset-spc-cheese_padding-lbl">(<?php echo esc_html( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_cheese_padding','0')) ); ?>px)</span>
						</label>
						<p class="pset-desc"><?php esc_html_e( 'Padding between cheese and toppings.', 'pizzatier' ); ?></p>
						<div class="pset-range__wrap">
							<input type="range" id="pset-spc-cheese_padding-range" min="0" max="80" step="1"
							       value="<?php echo esc_attr( (string)(int)preg_replace('/[^0-9]/','', $g('pizzatier_setting_cheese_padding','0')) ); ?>"
							       class="pset-range__slider pset-spacing-range"
							       data-target="pset-spc-cheese_padding-text" data-label="pset-spc-cheese_padding-lbl">
							<input type="text" id="pset-spc-cheese_padding-text" name="pizzatier_setting_cheese_padding"
							       value="<?php echo esc_attr( $g('pizzatier_setting_cheese_padding') ?: '0px' ); ?>"
							       class="pset-range__val pset-spacing-text"
							       data-range="pset-spc-cheese_padding-range" data-label="pset-spc-cheese_padding-lbl"
							       placeholder="0px" style="width:72px;">
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Plugin Settings ═════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="plugin-settings">
				<div>
					<h2><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'Plugin Settings', 'pizzatier' ); ?></h2>
					<p>Announcement bar text and builder help content shown to customers.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-plugin-settings"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-plugin-settings">
				<div class="pset-grid pset-grid--wide">
					<div class="pset-field pset-field--full">
						<label><?php esc_html_e( 'Demo / Announcement Bar', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'If set, this message appears as an announcement bar above all pages using PizzaTier. Leave empty to disable.', 'pizzatier' ); ?></p>
						<textarea name="pizzatier_setting_settings_demonotice" class="pset-textarea" rows="2" placeholder="e.g. Now open for online ordering! Order before 8pm for same-day delivery."><?php echo esc_textarea( $g('pizzatier_setting_settings_demonotice') ); ?></textarea>
					</div>
					<div class="pset-field pset-field--full">
						<label><?php esc_html_e( 'Help Screen Content', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Content shown in the builder\'s help modal/tab when customers click the help icon.', 'pizzatier' ); ?></p>
						<textarea name="pizzatier_setting_global_help_content" class="pset-textarea" rows="4"><?php echo esc_textarea( $g('pizzatier_setting_global_help_content') ); ?></textarea>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Cart Integration ═════════════════════════════════════════
		     Pricing logic, price displays, and cart options all live in
		     PizzaTierPro. The base plugin handles ingredients, layouts, and
		     visualisation only; this section just points admins to the right
		     place when Pro is or isn't installed. -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="pricing-cart">
				<div>
					<h2><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Cart Integration', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Pricing and cart features are provided by PizzaTierPro.', 'pizzatier' ); ?></p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-pricing-cart"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-pricing-cart">
				<div class="pset-grid">
					<?php if ( ! class_exists( 'PizzaTierPro\\Pro\\Plugin' ) ) : ?>
					<div class="pset-field pset-field--full">
						<div class="pset-pro-notice">
							<span class="dashicons dashicons-cart"></span>
							<div>
								<strong><?php esc_html_e( 'Pricing &amp; WooCommerce Cart', 'pizzatier' ); ?></strong>
								<p><?php esc_html_e( 'Per-layer pricing grids, base price, currency display, cart buttons, checkout flow, and order/email integration are all handled by PizzaTierPro.', 'pizzatier' ); ?>
								<a href="https://pizzatier.com/pro" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more →', 'pizzatier' ); ?></a></p>
							</div>
						</div>
					</div>
					<?php else : ?>
					<div class="pset-field pset-field--full">
						<div class="pset-pro-notice pset-pro-notice--active">
							<span class="dashicons dashicons-yes-alt"></span>
							<div>
								<strong><?php esc_html_e( 'Pricing &amp; Cart Settings', 'pizzatier' ); ?></strong>
								<p><?php esc_html_e( 'Configure pricing grids, cart buttons, and checkout in ', 'pizzatier' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatierpro-settings' ) ); ?>"><?php esc_html_e( 'PizzaTierPro → Pro Settings', 'pizzatier' ); ?></a>.</p>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- ══ Section: Content & Data ═══════════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="content-data">
				<div>
					<h2><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Content &amp; Data', 'pizzatier' ); ?></h2>
					<p>How layers are managed in the admin and how incomplete layers behave in the builder.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-content-data"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-content-data">
				<div class="pset-grid">
					<div class="pset-field">
						<label><?php esc_html_e( 'Disable the Content Layer Manager', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Bypass the PizzaTier Content Hub and send the sidebar layer links (and the dashboard stat boxes) straight to the standard WordPress post lists. Any direct hit on the Content Hub redirects to the matching WP list.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'Skip the custom layer manager and use the normal WordPress edit screens for each layer type instead.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_disable_content_hub" value="no">
							<input type="checkbox" name="pizzatier_setting_disable_content_hub" value="yes"<?php checked( (string) get_option( 'pizzatier_setting_disable_content_hub', 'no' ), 'yes' ); ?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label"><?php esc_html_e( 'Use WP lists instead of the Content Hub', 'pizzatier' ); ?></span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Hide Incomplete Layers', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Exclude any layer that lacks the custom data needed to render or price it — image-bearing layers without a usable image, and sizes without a diameter. Hidden layers are dropped from the builder and from price calculations, preventing half-configured items from breaking the builder.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'Automatically hide layers that are missing key details (like a layer image) so an unfinished item can\'t break your pizza builder.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_require_complete_data" value="no">
							<input type="checkbox" name="pizzatier_setting_require_complete_data" value="yes"<?php checked( (string) get_option( 'pizzatier_setting_require_complete_data', 'no' ), 'yes' ); ?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label"><?php esc_html_e( 'Ignore layers with insufficient data', 'pizzatier' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Accessibility & Performance ══════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="accessibility-perf">
				<div>
					<h2><span class="dashicons dashicons-universal-access"></span> <?php esc_html_e( 'Accessibility &amp; Performance', 'pizzatier' ); ?></h2>
					<p>WCAG accessibility aids and front-end performance options.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-accessibility-perf"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-accessibility-perf">
				<div class="pset-grid">
					<div class="pset-field">
						<label><?php esc_html_e( 'Reduce Motion', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Disable all animations for users who prefer reduced motion.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_a11y_reduce_motion" value="no">
							<input type="checkbox" name="pizzatier_setting_a11y_reduce_motion" value="yes"<?php checked((string)get_option('pizzatier_setting_a11y_reduce_motion','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Honor prefers-reduced-motion</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'High Contrast Mode', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Force high-contrast colors for all builder UI elements.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_a11y_high_contrast" value="no">
							<input type="checkbox" name="pizzatier_setting_a11y_high_contrast" value="yes"<?php checked((string)get_option('pizzatier_setting_a11y_high_contrast','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Enable high contrast</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Lazy-Load Topping Images', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Only load topping images when they scroll into view.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_perf_lazy_load" value="no">
							<input type="checkbox" name="pizzatier_setting_perf_lazy_load" value="yes"<?php checked((string)get_option('pizzatier_setting_perf_lazy_load','yes'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Enable lazy loading</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Preload Builder Assets', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv">Add <code>&lt;link rel="preload"&gt;</code> hints for critical builder assets.</p>
						<p class="pset-desc pset-desc--simple">Tell the browser to start downloading the pizza images early so the builder appears faster. Recommended to leave on.</p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_perf_preload_assets" value="no">
							<input type="checkbox" name="pizzatier_setting_perf_preload_assets" value="yes"<?php checked((string)get_option('pizzatier_setting_perf_preload_assets','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Preload critical assets</span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Customer Experience ══════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="customer-experience">
				<div>
					<h2><span class="dashicons dashicons-smiley"></span> <?php esc_html_e( 'Customer Experience', 'pizzatier' ); ?></h2>
					<p>Notifications, confirmations, and micro-copy shown to customers during the ordering flow.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-customer-experience"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-customer-experience">
				<div class="pset-grid">
					<div class="pset-field">
						<label><?php esc_html_e( 'Show "Pizza Summary" Panel', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Display a running summary of selected layers alongside the visualizer.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_cx_show_summary" value="no">
							<input type="checkbox" name="pizzatier_setting_cx_show_summary" value="yes"<?php checked((string)get_option('pizzatier_setting_cx_show_summary','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Show summary panel</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Toast Notification Style', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Style of the pop-up when a layer is added or removed.', 'pizzatier' ); ?></p>
						<?php $v = (string) get_option('pizzatier_setting_cx_toast_style','bottom-right'); ?>
						<select name="pizzatier_setting_cx_toast_style" class="pset-select">
							<?php foreach(['bottom-right'=>'Slide-in (bottom-right)','top-center'=>'Slide-in (top-center)','inline'=>'Inline below visualizer','none'=>'None'] as $ov=>$ol):?>
							<option value="<?php echo esc_attr($ov);?>"<?php selected($v,$ov);?>><?php echo esc_html($ol);?></option>
							<?php endforeach;?>
						</select>
					</div>
					<div class="pset-field">
						<label>Toast Duration <span class="pset-hint" id="pset-cx-toast-label">(<?php echo esc_html((string)get_option('pizzatier_setting_cx_toast_duration','2000')); ?>ms)</span></label>
						<p class="pset-desc"><?php esc_html_e( 'How long the toast notification stays visible.', 'pizzatier' ); ?></p>
						<div class="pset-range-wrap">
							<input type="range" name="pizzatier_setting_cx_toast_duration"
							       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_toast_duration','2000')); ?>"
							       min="500" max="5000" step="250" class="pset-range"
							       oninput="document.getElementById('pset-cx-toast-val').textContent=this.value+'ms';document.getElementById('pset-cx-toast-label').textContent='('+this.value+'ms)'">
							<span class="pset-range__val" id="pset-cx-toast-val"><?php echo esc_html((string)get_option('pizzatier_setting_cx_toast_duration','2000')); ?>ms</span>
						</div>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( '"Added" Confirmation Text', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Message shown when an item is added to the pizza.', 'pizzatier' ); ?></p>
						<input type="text" name="pizzatier_setting_cx_text_added"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_text_added','Added to your pizza!')); ?>"
						       class="pset-input" placeholder="Added to your pizza!">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( '"Removed" Confirmation Text', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Message shown when an item is removed.', 'pizzatier' ); ?></p>
						<input type="text" name="pizzatier_setting_cx_text_removed"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_text_removed','Removed from your pizza.')); ?>"
						       class="pset-input" placeholder="Removed from your pizza.">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Max Toppings Warning Text', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Shown when the customer tries to exceed the topping limit.', 'pizzatier' ); ?></p>
						<input type="text" name="pizzatier_setting_cx_text_max_toppings"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_text_max_toppings','You\'ve reached the maximum number of toppings.')); ?>"
						       class="pset-input" placeholder="You've reached the maximum number of toppings.">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Show "Start Over" Button', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Display a button that resets all selections to defaults.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_cx_show_start_over" value="no">
							<input type="checkbox" name="pizzatier_setting_cx_show_start_over" value="yes"<?php checked((string)get_option('pizzatier_setting_cx_show_start_over','yes'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Show reset button</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( '"Start Over" Button Label', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Custom text for the reset button.', 'pizzatier' ); ?></p>
						<input type="text" name="pizzatier_setting_cx_start_over_label"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_start_over_label','Start Over')); ?>"
						       class="pset-input" placeholder="Start Over">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Show Special Instructions Field', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Allow customers to add free-text notes to their order.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_cx_special_instructions" value="no">
							<input type="checkbox" name="pizzatier_setting_cx_special_instructions" value="yes"<?php checked((string)get_option('pizzatier_setting_cx_special_instructions','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Enable special instructions</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Special Instructions Placeholder', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Hint text inside the special instructions text box.', 'pizzatier' ); ?></p>
						<input type="text" name="pizzatier_setting_cx_special_instr_placeholder"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_special_instr_placeholder','Any special requests? (optional)')); ?>"
						       class="pset-input" placeholder="Any special requests? (optional)">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Special Instructions Max Length', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Maximum characters allowed in the instructions field.', 'pizzatier' ); ?></p>
						<input type="number" name="pizzatier_setting_cx_special_instr_max"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_cx_special_instr_max','300')); ?>"
						       class="pset-input" placeholder="300">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Order Review Modal', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Show a "Review your order" confirmation dialog before adding to cart.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_cx_review_modal" value="no">
							<input type="checkbox" name="pizzatier_setting_cx_review_modal" value="yes"<?php checked((string)get_option('pizzatier_setting_cx_review_modal','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Show review modal before cart</span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Section: Advanced & Developer ═════════════════════════════════ -->
		<div class="pset-card">
			<div class="pset-card__head pset-card__head--collapsible" data-pset-toggle="advanced-dev">
				<div>
					<h2><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Advanced &amp; Developer', 'pizzatier' ); ?></h2>
					<p>Debug tools and low-level overrides for developers.</p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-advanced-dev"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-advanced-dev">
				<div class="pset-grid pset-grid--wide">
					<div class="pset-field">
						<label><?php esc_html_e( 'Debug Mode', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Log builder events and state changes to the browser console.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'Show behind-the-scenes activity in the browser\'s developer console. For troubleshooting — leave off during normal use.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_adv_debug_mode" value="no">
							<input type="checkbox" name="pizzatier_setting_adv_debug_mode" value="yes"<?php checked((string)get_option('pizzatier_setting_adv_debug_mode','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Enable console debug output</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Disable All Plugin CSS', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Prevent PizzaTier from enqueueing any front-end stylesheets.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_adv_disable_css" value="no">
							<input type="checkbox" name="pizzatier_setting_adv_disable_css" value="yes"<?php checked((string)get_option('pizzatier_setting_adv_disable_css','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label">Disable plugin front-end CSS</span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Enable REST API', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Enables the PizzaTier REST API endpoints (used by developers and headless setups). Not needed for normal shortcode/block usage — leave off unless you specifically need it.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'Advanced — for developers building custom apps that talk to PizzaTier from the outside. You almost certainly do not need this. Leave it off.', 'pizzatier' ); ?></p>
						<label class="pset-toggle">
							<input type="hidden" name="pizzatier_setting_adv_rest_api_enabled" value="no">
							<input type="checkbox" name="pizzatier_setting_adv_rest_api_enabled" value="yes"<?php checked((string)get_option('pizzatier_setting_adv_rest_api_enabled','no'),'yes');?>>
							<span class="pset-toggle__track"><span class="pset-toggle__thumb"></span></span>
							<span class="pset-toggle__label"><?php esc_html_e( 'Enable REST API endpoints', 'pizzatier' ); ?></span>
						</label>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'REST API Cache TTL (seconds)', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'How long to cache REST API responses server-side. 0 = no cache. Only applies when REST API is enabled above.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'How long (in seconds) to remember API answers so the server does not have to rebuild them every time. Only matters if the setting above is turned on. Set to 0 for no caching.', 'pizzatier' ); ?></p>
						<input type="number" name="pizzatier_setting_adv_rest_cache_ttl"
						       value="<?php echo esc_attr((string)get_option('pizzatier_setting_adv_rest_cache_ttl','300')); ?>"
						       class="pset-input" placeholder="300">
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Server Log Level', 'pizzatier' ); ?></label>
						<p class="pset-desc pset-desc--adv"><?php esc_html_e( 'Verbosity of server-side logging to the WordPress debug log.', 'pizzatier' ); ?></p>
						<p class="pset-desc pset-desc--simple"><?php esc_html_e( 'How much detail to write into WordPress\' behind-the-scenes log. For developers troubleshooting issues — most people can ignore this.', 'pizzatier' ); ?></p>
						<?php $v = (string) get_option('pizzatier_setting_adv_log_level','off'); ?>
						<select name="pizzatier_setting_adv_log_level" class="pset-select">
							<?php foreach(['off'=>'Off','errors'=>'Errors only','warnings'=>'Warnings + Errors','all'=>'All (verbose)'] as $ov=>$ol):?>
							<option value="<?php echo esc_attr($ov);?>"<?php selected($v,$ov);?>><?php echo esc_html($ol);?></option>
							<?php endforeach;?>
						</select>
					</div>
				</div>
			</div>
		</div>


		<!-- ══ Section: Import / Export ══════════════════════════════════ -->
		<div class="pset-card" id="pset-card-data-backup">
			<div class="pset-card__head">
				<div>
					<h2>
						<span class="dashicons dashicons-database-import"></span>
						<?php esc_html_e( 'Import / Export Settings', 'pizzatier' ); ?>
					</h2>
					<p class="pset-desc"><?php esc_html_e( 'Back up your settings as a JSON file, or restore them on a new site.', 'pizzatier' ); ?></p>
				</div>
				<button type="button" class="pset-collapse-btn" aria-expanded="true" aria-controls="pset-body-data-backup"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
			</div>
			<div class="pset-card__body" id="pset-body-data-backup">
				<div class="pset-grid pset-grid--wide">
					<div class="pset-field">
						<label><?php esc_html_e( 'Export Settings', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Download all current PizzaTier settings as a JSON file. Use this to back up your configuration or copy it to another site.', 'pizzatier' ); ?></p>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pizzatier_export_settings' ), 'pizzatier_export_settings' ) ); ?>"
						   class="button button-secondary">
							<span class="dashicons dashicons-download" style="margin-top:3px;margin-right:4px;"></span>
							<?php esc_html_e( 'Download Settings JSON', 'pizzatier' ); ?>
						</a>
					</div>
					<div class="pset-field">
						<label><?php esc_html_e( 'Import Settings', 'pizzatier' ); ?></label>
						<p class="pset-desc"><?php esc_html_e( 'Restore settings from a previously exported JSON file. This will overwrite your current settings.', 'pizzatier' ); ?></p>
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
							<input type="file" name="pizzatier_import_file" accept=".json,application/json" style="max-width:280px;">
							<button type="submit" name="pizzatier_import_settings" value="1" class="button button-secondary"
							        onclick="return confirm('<?php esc_attr_e( 'This will overwrite your current settings. Continue?', 'pizzatier' ); ?>');">
								<span class="dashicons dashicons-upload" style="margin-top:3px;margin-right:4px;"></span>
								<?php esc_html_e( 'Import JSON', 'pizzatier' ); ?>
							</button>
						</div>
						<p class="pset-desc" style="color:#b32d2e;"><?php esc_html_e( 'Importing will replace all current settings immediately. Export a backup first.', 'pizzatier' ); ?></p>
					</div>
				</div>
			</div>
		</div>

				<!-- ══ Section: Template Settings (moved to Template page) ══ -->
		<?php if ( $active_template ) : ?>
		<div class="pset-card">
			<div class="pset-card__head">
				<div>
					<h2>
						<span class="dashicons dashicons-admin-appearance"></span>
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $active_template ) ) ); ?> Template Settings
					</h2>
					<p class="pset-desc">Template-specific settings have moved to the <strong>Template</strong> page, below the template selector.</p>
				</div>
			</div>
			<div class="pset-card__body" id="pset-body-template-settings" style="padding:18px 24px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-template#template-settings' ) ); ?>" class="button button-primary">
					<span class="dashicons dashicons-admin-appearance" style="margin-top:3px;"></span>
					<?php printf( /* translators: %s = template name. */ esc_html__( 'Open %s Template Settings', 'pizzatier' ), esc_html( ucwords( str_replace( '-', ' ', $active_template ) ) ) ); ?>
				</a>
				<p style="margin-top:12px;font-size:13px;color:#646970;">Settings for the active template are now configured directly alongside the template selector.</p>
			</div>
		</div>
		<?php endif; ?>

		</div><!-- /.pset-main -->
		</div><!-- /.pset-layout -->

		<!-- ══ Save Bar ══════════════════════════════════════════════ -->
		<div style="position:sticky;bottom:0;z-index:100;background:linear-gradient(to top,#1a1e23 80%,transparent);padding:14px 0 4px;margin-top:20px;text-align:right;">
			<button type="submit" class="button button-primary" style="display:inline-flex;align-items:center;gap:7px;font-size:14px;padding:8px 22px;height:auto;line-height:1.4;">
				<span class="dashicons dashicons-saved" style="font-size:16px;width:16px;height:16px;"></span>
				<?php esc_html_e( 'Save Settings', 'pizzatier' ); ?>
			</button>
		</div>

		</form>
		</div><!-- /.wrap -->
		<?php $this->render_styles_sidebar(); ?>
		<?php
	}

	/** Called via admin_post_pizzatier_export_settings — fires before any HTML output. */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( -1 ); }
		check_admin_referer( 'pizzatier_export_settings' );
		$this->export_settings();
	}

	private function export_settings(): void {
		$data = [];
		foreach ( self::OPTIONS as $key ) {
			$data[ $key ] = get_option( $key, null );
		}
		// Note: pizzatier_setting_global_template is now included in OPTIONS above.

		$json     = (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'pizzatier-settings-' . gmdate( 'Y-m-d' ) . '.json';

		// Discard any buffered output so download headers can be sent cleanly
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( headers_sent() ) {
			// Headers already committed (e.g. another plugin or the theme
			// printed output before this admin_post handler ran), so a file
			// download can't be sent. Fall back to a no-JavaScript page that
			// presents the export JSON in a read-only textarea to copy/save.
			$back = wp_get_referer() ?: admin_url( 'admin.php?page=pizzatier-settings' );
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'PizzaTier Settings Export', 'pizzatier' ) . '</title></head><body style="font-family:sans-serif;padding:24px;max-width:820px;margin:0 auto;">';
			echo '<h1 style="font-size:18px;">' . esc_html__( 'Settings export', 'pizzatier' ) . '</h1>';
			echo '<p>' . esc_html__( 'Automatic download was unavailable on this server. Copy the text below and save it with this file name:', 'pizzatier' ) . ' <code>' . esc_html( $filename ) . '</code></p>';
			echo '<textarea readonly rows="20" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:12px;">' . esc_textarea( $json ) . '</textarea>';
			echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( 'Back', 'pizzatier' ) . '</a></p>';
			echo '</body></html>';
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON file download (Content-Type: application/json).
		exit;
	}

	private function import_settings(): string {
		check_admin_referer( 'pizzatier_settings_save' );

		if ( empty( $_FILES['pizzatier_import_file']['tmp_name'] ) ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'no file received.', 'pizzatier' ) . '</p></div>';
		}

		$file = $_FILES['pizzatier_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// Check for upload errors
		if ( ! empty( $file['error'] ) && (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'upload error.', 'pizzatier' ) . '</p></div>';
		}

		$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		// Confirm the file came in via a real HTTP upload (not a path injection)
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'invalid upload.', 'pizzatier' ) . '</p></div>';
		}

		// Cap size at 1 MB — settings JSON is tiny
		$max_bytes = 1024 * 1024;
		$size      = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size > $max_bytes ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'file too large.', 'pizzatier' ) . '</p></div>';
		}

		// Restrict to .json filenames
		$orig_name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		if ( $orig_name === '' || strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) ) !== 'json' ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'expected a .json file.', 'pizzatier' ) . '</p></div>';
		}

		$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $raw ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'could not read file.', 'pizzatier' ) . '</p></div>';
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import failed:', 'pizzatier' ) . '</strong> ' . esc_html__( 'invalid JSON.', 'pizzatier' ) . '</p></div>';
		}

		$allowed = array_flip( self::OPTIONS );
		$count   = 0;

		// Keys that are stored as arrays — must not be cast to string
		$array_options = [
			'pizzatier_setting_topping_fractions',
		];

		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) { continue; }
			$key_safe = sanitize_key( $key );

			if ( in_array( $key, $array_options, true ) ) {
				// Sanitise as an array of fraction keys
				$allowed_fractions = [ 'whole', 'half-left', 'half-right', 'quarter-top-left', 'quarter-top-right', 'quarter-bottom-left', 'quarter-bottom-right' ];
				$sanitised         = is_array( $value )
					? array_values( array_intersect( array_map( 'sanitize_key', $value ), $allowed_fractions ) )
					: [];
				if ( ! in_array( 'whole', $sanitised, true ) ) {
					array_unshift( $sanitised, 'whole' );
				}
				update_option( $key_safe, $sanitised );
			} else {
				// All other options treated as sanitised text/HTML
				update_option( $key_safe, wp_kses_post( (string) $value ) );
			}
			$count++;
		}

		$msg = sprintf(
			/* translators: %d = number of settings restored */
			'<strong>' . esc_html__( 'Import successful:', 'pizzatier' ) . '</strong> ' . esc_html__( '%d settings restored.', 'pizzatier' ),
			$count
		);
		return '<div class="notice notice-success is-dismissible"><p>' . $msg . '</p></div>';
	}

	private function save_settings(): void {
		check_admin_referer( 'pizzatier_settings_save' );

		$text_options = [
			'pizzatier_setting_crust_padding',
			'pizzatier_setting_sauce_padding',
			'pizzatier_cheese_setting_cheesedistance',
			'pizzatier_setting_cheese_padding',
			'pizzatier_setting_pizza_aspect',
			'pizzatier_setting_pizza_radius',
			// Customer Experience
			'pizzatier_setting_cx_text_added',
			'pizzatier_setting_cx_text_removed',
			'pizzatier_setting_cx_text_max_toppings',
			'pizzatier_setting_cx_start_over_label',
			'pizzatier_setting_cx_special_instr_placeholder',
		];
		$select_options = [
			'pizzatier_setting_crust_defaultcrust',
			'pizzatier_setting_sauce_defaultsauce',
			'pizzatier_setting_cheese_defaultcheese',
			'pizzatier_setting_drizzle_defaultdrizzle',
			'pizzatier_setting_cut_defaultcut',
			'pizzatier_setting_pizza_shape',
			// Customer Experience
			'pizzatier_setting_cx_toast_style',
			// Advanced
			'pizzatier_setting_adv_log_level',
		];
		$number_options = [
			'pizzatier_setting_topping_maxtoppings',
			// Customer Experience
			'pizzatier_setting_cx_toast_duration',
			'pizzatier_setting_cx_special_instr_max',
			// Advanced
			'pizzatier_setting_adv_rest_cache_ttl',
		];
		$toggle_options = [
			// Content & Data behaviour
			'pizzatier_setting_disable_content_hub',
			'pizzatier_setting_require_complete_data',
			// Accessibility / Performance
			'pizzatier_setting_a11y_reduce_motion',
			'pizzatier_setting_a11y_high_contrast',
			'pizzatier_setting_perf_lazy_load',
			'pizzatier_setting_perf_preload_assets',
			// Customer Experience
			'pizzatier_setting_cx_show_summary',
			'pizzatier_setting_cx_show_start_over',
			'pizzatier_setting_cx_special_instructions',
			'pizzatier_setting_cx_review_modal',
			// Advanced
			'pizzatier_setting_adv_debug_mode',
			'pizzatier_setting_adv_disable_css',
			'pizzatier_setting_adv_rest_api_enabled',
		];
		$textarea_options = [
			// Global help content (plain text)
			'pizzatier_setting_settings_demonotice',
			'pizzatier_setting_global_help_content',
		];
		// Note: pizzatier_setting_price_base (decimal) was removed in 1.2.0;
		// pricing options now live in PizzaTierPro.

		foreach ( $text_options as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		foreach ( $select_options as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_key( $_POST[ $key ] ) );
			}
		}
		foreach ( $number_options as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, (int) $_POST[ $key ] );
			}
		}
		foreach ( $toggle_options as $key ) {
			// Hidden field sends 'no'; checkbox sends 'yes' when checked
			update_option( $key, ( isset( $_POST[ $key ] ) && sanitize_key( wp_unslash( $_POST[ $key ] ) ) === 'yes' ) ? 'yes' : 'no' );
		}
		// Topping fractions — multi-checkbox array
		$_allowed_fractions = [ 'whole', 'half-left', 'half-right', 'quarter-top-left', 'quarter-top-right', 'quarter-bottom-left', 'quarter-bottom-right' ];
		$_posted_fractions  = isset( $_POST['pizzatier_setting_topping_fractions'] ) && is_array( $_POST['pizzatier_setting_topping_fractions'] )
			? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['pizzatier_setting_topping_fractions'] ) ), $_allowed_fractions ) )
			: [];
		// Always include 'whole'
		if ( ! in_array( 'whole', $_posted_fractions, true ) ) {
			array_unshift( $_posted_fractions, 'whole' );
		}
		update_option( 'pizzatier_setting_topping_fractions', $_posted_fractions );

		foreach ( $textarea_options as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
		// Save template-specific settings dynamically
		$active_template = (string) get_option( 'pizzatier_setting_global_template', '' );
		if ( $active_template ) {
			$tpl_dirs = [
				get_stylesheet_directory() . '/pzttemplates/' . $active_template . '/',
				PIZZATIER_TEMPLATES_DIR . $active_template . '/',
			];
			foreach ( $tpl_dirs as $dir ) {
				$options_file = $dir . 'pztp-template-options.php';
				if ( file_exists( $options_file ) ) {
					$tpl_settings = include $options_file;
					if ( is_array( $tpl_settings ) ) {
						foreach ( $tpl_settings as $field ) {
							if ( empty( $field['key'] ) || empty( $field['type'] ) ) { continue; }
							$key = sanitize_key( (string) $field['key'] );
							// Namespace guard: only allow writing to recognised setting keys.
							if ( $key === '' || strpos( $key, '_setting_' ) === false ) { continue; }
							if ( ! isset( $_POST[ $key ] ) && $field['type'] === 'toggle' ) {
								update_option( $key, 'no' );
								continue;
							}
							if ( ! isset( $_POST[ $key ] ) ) { continue; }
							switch ( $field['type'] ) {
								case 'color':
									$v = sanitize_hex_color( wp_unslash( $_POST[ $key ] ) );
									if ( $v ) { update_option( $key, $v ); }
									break;
								case 'number':
								case 'range':
									update_option( $key, (float) $_POST[ $key ] );
									break;
								case 'textarea':
									update_option( $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
									break;
								case 'toggle':
									update_option( $key, sanitize_key( $_POST[ $key ] ) === 'yes' ? 'yes' : 'no' );
									break;
								default:
									update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
									break;
							}
						}
					}
					break;
				}
			}
		}
	}

	private function render_select( string $label, string $key, array $posts, string $current ): void {
		?>
		<div class="pset-field">
			<label><?php echo esc_html( $label ); ?></label>
			<select name="<?php echo esc_attr( $key ); ?>" class="pset-select">
				<option value=""><?php esc_html_e( '— None / Plugin default —', 'pizzatier' ); ?></option>
				<?php foreach ( $posts as $p ) :
					$slug = sanitize_title( $p->post_title );
				?>
				<option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $current, $slug ); ?>><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	private function render_layer_picker( string $label, string $key, array $posts, string $current ): void {
		// Build items array with thumbnail URLs
		$items = [];
		foreach ( $posts as $p ) {
			$slug  = sanitize_title( $p->post_title );
			$thumb = get_the_post_thumbnail_url( $p->ID, 'thumbnail' );
			$items[] = [
				'slug'  => $slug,
				'title' => $p->post_title,
				'thumb' => $thumb ?: '',
			];
		}
		// Find active item for display
		$active_title = '';
		$active_thumb = '';
		foreach ( $items as $item ) {
			if ( $item['slug'] === $current ) {
				$active_title = $item['title'];
				$active_thumb = $item['thumb'];
				break;
			}
		}
		$items_json = wp_json_encode( $items );
		?>
		<div class="pset-field pset-layer-picker-field"
		     data-picker-key="<?php echo esc_attr( $key ); ?>"
		     data-picker-label="<?php echo esc_attr( $label ); ?>"
		     data-picker-items="<?php echo esc_attr( $items_json ); ?>">
			<label><?php echo esc_html( $label ); ?></label>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $current ); ?>">
			<button type="button" class="pset-layer-trigger <?php echo $current ? 'pset-layer-trigger--has-value' : ''; ?>">
				<?php if ( $current && $active_title ) : ?>
				<span class="pset-layer-trigger__thumb">
					<?php if ( $active_thumb ) : ?>
					<img src="<?php echo esc_url( $active_thumb ); ?>" alt="<?php echo esc_attr( $active_title ); ?>">
					<?php else : ?>
					<span class="pset-layer-trigger__placeholder dashicons dashicons-format-image"></span>
					<?php endif; ?>
				</span>
				<span class="pset-layer-trigger__name"><?php echo esc_html( $active_title ); ?></span>
				<?php else : ?>
				<span class="pset-layer-trigger__placeholder dashicons dashicons-plus-alt2"></span>
				<span class="pset-layer-trigger__name pset-hint"><?php esc_html_e( 'None selected', 'pizzatier' ); ?></span>
				<?php endif; ?>
				<span class="pset-layer-trigger__edit dashicons dashicons-edit"></span>
			</button>
		</div>
		<?php
	}

	private function render_styles(): void {
		// CSS is now enqueued via AssetManager: assets/css/settings-page.css
	}

	private function render_styles_sidebar(): void {
		// CSS is now enqueued via AssetManager: assets/css/settings-page.css
	}
}
