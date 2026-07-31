<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode Generator — visual configurator for all PizzaTier shortcodes.
 * Supports: [pizza_builder], [pizza_static], [pizza_layer], [pizza_layer_info]
 */
class ShortcodeGenerator {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Fetch all CPT posts for dropdowns
		$q_args   = [ 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
		$crusts   = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_crusts'   ] ) );
		$sauces   = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_sauces'   ] ) );
		$cheeses  = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_cheeses'  ] ) );
		$toppings = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_toppings' ] ) );
		$drizzles = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_drizzles' ] ) );
		$cuts     = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_cuts'     ] ) );
		$sizes    = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_sizes'    ] ) );

		// Template list
		$plugin_tpl_dir = PIZZATIER_TEMPLATES_DIR;
		$theme_tpl_dir  = get_stylesheet_directory() . '/pzttemplates/';
		$templates      = [];
		foreach ( [ $plugin_tpl_dir, $theme_tpl_dir ] as $dir ) {
			if ( is_dir( $dir ) ) {
				foreach ( (array) scandir( $dir ) as $f ) {
					if ( $f !== '.' && $f !== '..' && is_dir( $dir . $f ) ) {
						$templates[] = $f;
					}
				}
			}
		}

		$all_tabs = [ 'crust', 'sauce', 'cheese', 'toppings', 'drizzle', 'slicing', 'yourpizza' ];
		$layer_types = [ 'topping', 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];

		?>
		<div class="wrap pscg-wrap">
		<?php $this->render_styles(); ?>

		<!-- ══ Header ════════════════════════════════════════════════ -->
		<div class="pscg-header">
			<span class="dashicons dashicons-editor-code pscg-header__icon"></span>
			<div style="flex:1;">
				<h1 class="pscg-header__title"><?php esc_html_e( 'Shortcode Generator', 'pizzatier' ); ?></h1>
				<p class="pscg-header__sub"><?php esc_html_e( 'Configure any PizzaTier shortcode and copy it directly to your clipboard.', 'pizzatier' ); ?></p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-help#shortcodes' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">
					<span class="dashicons dashicons-editor-help" style="font-size:14px;width:14px;height:14px;"></span> <?php esc_html_e( 'Shortcode Docs', 'pizzatier' ); ?>
				</a>
			</div>
		</div>

		<!-- ══ Shortcode type selector ═══════════════════════════════ -->
		<div class="pscg-type-tabs">
			<button class="pscg-type-tab pscg-type-tab--active" data-type="builder">
				<span class="dashicons dashicons-hammer"></span>
				<span class="pscg-type-tab__label">[pizza_builder]</span>
				<span class="pscg-type-tab__desc">Interactive builder</span>
			</button>
			<button class="pscg-type-tab" data-type="static">
				<span class="dashicons dashicons-format-image"></span>
				<span class="pscg-type-tab__label">[pizza_static]</span>
				<span class="pscg-type-tab__desc">Static pizza display</span>
			</button>
			<button class="pscg-type-tab" data-type="layer">
				<span class="dashicons dashicons-image-filter"></span>
				<span class="pscg-type-tab__label">[pizza_layer]</span>
				<span class="pscg-type-tab__desc">Single layer image</span>
			</button>
			<button class="pscg-type-tab" data-type="layerinfo">
				<span class="dashicons dashicons-info-outline"></span>
				<span class="pscg-type-tab__label">[pizza_layer_info]</span>
				<span class="pscg-type-tab__desc">Layer field value</span>
			</button>
		</div>

		<!-- ══ Builder configurator ══════════════════════════════════ -->
		<div class="pscg-form" id="pscg-form-builder">
			<div class="pscg-card">
				<div class="pscg-card__head"><h2>Interactive Builder — <code>[pizza_builder]</code></h2></div>
				<div class="pscg-card__body">
					<div class="pscg-grid">
						<div class="pscg-field">
							<label>Builder ID <span class="pscg-hint">Unique ID — required for multiple builders on one page</span></label>
							<input type="text" class="pscg-input" id="b-id" placeholder="pizza-1">
						</div>
						<div class="pscg-field">
							<label>Template <span class="pscg-hint">Leave blank to use active template</span></label>
							<select class="pscg-select" id="b-template">
								<option value="">— default active template —</option>
								<?php foreach ( $templates as $tpl ) : ?>
								<option value="<?php echo esc_attr( $tpl ); ?>"><?php echo esc_html( $tpl ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Max Toppings <span class="pscg-hint">0 = use plugin setting</span></label>
							<input type="number" class="pscg-input" id="b-max-toppings" min="0" placeholder="0">
						</div>
						<div class="pscg-field">
							<label>Default Crust</label>
							<select class="pscg-select" id="b-default-crust">
								<option value="">— use plugin default —</option>
								<?php foreach ( $crusts as $p ) : $s = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Default Sauce</label>
							<select class="pscg-select" id="b-default-sauce">
								<option value="">— use plugin default —</option>
								<?php foreach ( $sauces as $p ) : $s = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Default Cheese</label>
							<select class="pscg-select" id="b-default-cheese">
								<option value="">— use plugin default —</option>
								<?php foreach ( $cheeses as $p ) : $s = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<!-- Shape & animation -->
					<div class="pscg-grid" style="margin-top:14px;">
						<div class="pscg-field">
							<label>Pizza Shape <span class="pscg-hint">Overrides global setting</span></label>
							<select class="pscg-select" id="b-pizza-shape">
								<option value="">— use global setting —</option>
								<option value="round">⬤ Round</option>
								<option value="square">■ Square</option>
								<option value="rectangle">▬ Rectangle / Oval</option>
								<option value="custom">✦ Custom</option>
							</select>
						</div>
						<div class="pscg-field">
							<label>Aspect Ratio <span class="pscg-hint">rectangle &amp; custom</span></label>
							<input type="text" class="pscg-input" id="b-pizza-aspect" placeholder="4 / 3">
						</div>
						<div class="pscg-field">
							<label>Border Radius <span class="pscg-hint">custom shape only</span></label>
							<input type="text" class="pscg-input" id="b-pizza-radius" placeholder="8px">
						</div>
						<div class="pscg-field">
							<label>Layer Animation <span class="pscg-hint">Overrides global setting</span></label>
							<select class="pscg-select" id="b-layer-anim">
								<option value="">— use global setting —</option>
								<option value="fade">✦ Fade In</option>
								<option value="scale-in">⊕ Scale In (bouncy)</option>
								<option value="slide-up">↑ Slide Up</option>
								<option value="flip-in">↻ Flip In (3-D)</option>
								<option value="drop-in">↓ Drop In</option>
								<option value="instant">⚡ Instant</option>
							</select>
						</div>
						<div class="pscg-field">
							<label>Animation Speed <span class="pscg-hint">ms — overrides global setting</span></label>
							<input type="number" class="pscg-input" id="b-layer-anim-speed" min="80" max="800" step="20" placeholder="320">
						</div>
					</div>
					<!-- Tabs visibility -->
					<div class="pscg-field pscg-field--full" style="margin-top:14px;">
						<label>Visible Tabs <span class="pscg-hint">Uncheck to hide a tab from the builder. Leave all checked to show all.</span></label>
						<div class="pscg-checkboxes">
							<?php foreach ( $all_tabs as $tab ) : ?>
							<label class="pscg-cb-label">
								<input type="checkbox" class="pscg-cb-tab" value="<?php echo esc_attr( $tab ); ?>" checked>
								<?php echo esc_html( ucfirst( $tab ) ); ?>
							</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Static pizza configurator ═════════════════════════════ -->
		<div class="pscg-form" id="pscg-form-static" style="display:none;">
			<div class="pscg-card">
				<div class="pscg-card__head"><h2>Static Display — <code>[pizza_static]</code></h2></div>
				<div class="pscg-card__body">
					<p class="pscg-desc">Renders a non-interactive pizza image. Specify layers individually, or pick a saved preset.</p>
					<div class="pscg-grid">
						<div class="pscg-field">
							<label>Preset <span class="pscg-hint">Outputs <code>[pizza_preset]</code> — renders the full saved pizza incl. toppings (requires PizzaTier)</span></label>
							<select class="pscg-select" id="s-preset">
								<option value="">— none (specify layers below) —</option>
								<?php
								$presets = get_posts( array_merge( $q_args, [ 'post_type' => 'pizzatier_presets' ] ) );
								foreach ( $presets as $p ) : ?>
								<option value="<?php echo esc_attr( (string) $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Crust</label>
							<select class="pscg-select" id="s-crust">
								<option value="">— none —</option>
								<?php foreach ( $crusts as $p ) : $sl = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Sauce</label>
							<select class="pscg-select" id="s-sauce">
								<option value="">— none —</option>
								<?php foreach ( $sauces as $p ) : $sl = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Cheese</label>
							<select class="pscg-select" id="s-cheese">
								<option value="">— none —</option>
								<?php foreach ( $cheeses as $p ) : $sl = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Drizzle</label>
							<select class="pscg-select" id="s-drizzle">
								<option value="">— none —</option>
								<?php foreach ( $drizzles as $p ) : $sl = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Cut</label>
							<select class="pscg-select" id="s-cut">
								<option value="">— none —</option>
								<?php foreach ( $cuts as $p ) : $sl = sanitize_title( $p->post_title ); ?>
								<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<!-- Toppings multi-select -->
					<div class="pscg-field pscg-field--full">
						<label>Toppings <span class="pscg-hint">Hold Ctrl / Cmd to select multiple</span></label>
						<select class="pscg-select" id="s-toppings" multiple size="6">
							<?php foreach ( $toppings as $p ) : $sl = sanitize_title( $p->post_title ); ?>
							<option value="<?php echo esc_attr( $sl ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Single layer image configurator ═══════════════════════ -->
		<div class="pscg-form" id="pscg-form-layer" style="display:none;">
			<div class="pscg-card">
				<div class="pscg-card__head"><h2>Single Layer Image — <code>[pizza_layer]</code></h2></div>
				<div class="pscg-card__body">
					<p class="pscg-desc">Outputs a single layer <code>&lt;img&gt;</code> tag. Useful for menu pages or product listings.</p>
					<div class="pscg-grid">
						<div class="pscg-field">
							<label>Layer Type</label>
							<select class="pscg-select" id="l-type">
								<?php foreach ( $layer_types as $lt ) : ?>
								<option value="<?php echo esc_attr( $lt ); ?>"><?php echo esc_html( ucfirst( $lt ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Item <span class="pscg-hint">Select a specific item</span></label>
							<select class="pscg-select" id="l-slug">
								<option value="">— select layer type first —</option>
							</select>
						</div>
						<div class="pscg-field">
							<label>Image Type</label>
							<select class="pscg-select" id="l-image">
								<option value="layer">Layer image (stack PNG)</option>
								<option value="list">List image (thumbnail)</option>
							</select>
						</div>
						<div class="pscg-field">
							<label>CSS Class <span class="pscg-hint">Optional extra CSS class</span></label>
							<input type="text" class="pscg-input" id="l-class" placeholder="my-custom-class">
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Layer info configurator ═══════════════════════════════ -->
		<div class="pscg-form" id="pscg-form-layerinfo" style="display:none;">
			<div class="pscg-card">
				<div class="pscg-card__head"><h2>Layer Field Value — <code>[pizza_layer_info]</code></h2></div>
				<div class="pscg-card__body">
					<p class="pscg-desc">Outputs a single SCF field value as escaped text. Good for displaying ingredient lists or descriptions.</p>
					<div class="pscg-grid">
						<div class="pscg-field">
							<label>Layer Type</label>
							<select class="pscg-select" id="li-type">
								<?php foreach ( $layer_types as $lt ) : ?>
								<option value="<?php echo esc_attr( $lt ); ?>"><?php echo esc_html( ucfirst( $lt ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="pscg-field">
							<label>Slug</label>
							<input type="text" class="pscg-input" id="li-slug" placeholder="pepperoni">
						</div>
						<div class="pscg-field">
							<label>Field name <span class="pscg-hint">SCF field key, e.g. <code>topping_ingredients</code></span></label>
							<input type="text" class="pscg-input" id="li-field" placeholder="topping_ingredients">
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Output ════════════════════════════════════════════════ -->
		<div class="pscg-output-card">
			<label class="pscg-output-label">Generated Shortcode</label>
			<div class="pscg-output-row">
				<code class="pscg-output" id="pscg-output">[pizza_builder]</code>
				<button class="button button-primary pscg-copy-btn" id="pscg-copy-btn">
					<span class="dashicons dashicons-clipboard"></span> Copy
				</button>
			</div>
			<div id="pscg-copy-notice" class="pscg-copy-notice" style="display:none;">✓ Copied to clipboard!</div>
		</div>

		</div><!-- /.wrap -->
		<?php
	}

	private function render_styles(): void { ?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
	<?php }

}
