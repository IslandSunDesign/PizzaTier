<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Help & Reference Page
 *
 * Sections:
 *  1. Quickstart — 5-step walkthrough + first-launch checklist
 *  2. Managing Content — per-CPT step-by-step guides
 *  3. Layer Types — visual stack reference with z-index, fields, image tips
 *  4. Shortcodes — all four shortcodes, full attribute tables, copy-paste examples
 *  5. Shape & Animation — shape presets, animation modes, accessibility
 *  6. Template System — file structure, CSS custom properties, custom template guide
 *  7. FAQ — 12+ Q&A cards in <details> accordion
 *  8. Developer Reference — hooks, JS API, REST endpoints, namespace conventions
 */
class Help {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$active   = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'quickstart'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: selects which help section to display; validated against the allowlist below.
		$sections = $this->get_sections();
		if ( ! array_key_exists( $active, $sections ) ) { $active = 'quickstart'; }
		$hub = admin_url( 'admin.php?page=pizzatier-help' );

		?>
		<div class="wrap plhelp-wrap">
		<?php $this->render_styles(); ?>

		<!-- ══ Header ═══════════════════════════════════════════════════ -->
		<div class="plhelp-header">
			<span class="dashicons dashicons-editor-help plhelp-header__icon"></span>
			<div style="flex:1;">
				<h1 class="plhelp-header__title"><?php esc_html_e( 'Help &amp; Reference', 'pizzatier' ); ?></h1>
				<p class="plhelp-header__sub"><?php esc_html_e( 'Full documentation for setup, content management, shortcodes, templates, and development.', 'pizzatier' ); ?></p>
			</div>
			<div style="display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-setup' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">
					<span class="dashicons dashicons-welcome-learn-more" style="font-size:14px;width:14px;height:14px;"></span> <?php esc_html_e( 'Setup Guide', 'pizzatier' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-shortcodes' ) ); ?>" class="button" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff;">
					<span class="dashicons dashicons-editor-code" style="font-size:14px;width:14px;height:14px;"></span> <?php esc_html_e( 'Shortcodes', 'pizzatier' ); ?>
				</a>
			</div>
		</div>

		<!-- ══ Layout ════════════════════════════════════════════════════ -->
		<div class="plhelp-layout">

			<!-- Left nav -->
			<nav class="plhelp-nav">
				<?php foreach ( $sections as $key => $sec ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'section', $key, $hub ) ); ?>"
				   class="plhelp-nav__item<?php echo $key === $active ? ' plhelp-nav__item--active' : ''; ?>">
					<span class="plhelp-nav__icon"><?php echo esc_html( $sec['icon'] ); ?></span>
					<?php echo esc_html( $sec['title'] ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<!-- Content -->
			<article class="plhelp-content">
				<?php $this->render_section( $active, $sections[ $active ] ); ?>
			</article>

		</div><!-- /.plhelp-layout -->
		</div><!-- /.plhelp-wrap -->
		<?php
	}

	private function get_sections(): array {
		return [
			'quickstart' => [ 'icon' => '🚀', 'title' => __( 'Quickstart', 'pizzatier' )           ],
			'setup'      => [ 'icon' => '🧱', 'title' => __( 'Layer-by-Layer Setup', 'pizzatier' ) ],
			'content'    => [ 'icon' => '📦', 'title' => __( 'Managing Content', 'pizzatier' )      ],
			'layers'     => [ 'icon' => '📚', 'title' => __( 'Layer Type Reference', 'pizzatier' )  ],
			'shortcodes' => [ 'icon' => '⌨', 'title' => __( 'Shortcodes', 'pizzatier' )            ],
			'shapes'     => [ 'icon' => '◉',  'title' => __( 'Shape & Animation', 'pizzatier' )     ],
			'templates'  => [ 'icon' => '🎨', 'title' => __( 'Template System', 'pizzatier' )       ],
			'migration'  => [ 'icon' => '↗',  'title' => __( 'Site Migration', 'pizzatier' )        ],
			'faq'        => [ 'icon' => '❓', 'title' => __( 'FAQ', 'pizzatier' )                   ],
			'developer'  => [ 'icon' => '⚙',  'title' => __( 'Developer Reference', 'pizzatier' )   ],
		];
	}

	private function render_section( string $key, array $meta ): void {
		echo '<h2 class="plhelp-section-title">' . esc_html( $meta['icon'] ) . ' ' . esc_html( $meta['title'] ) . '</h2>';
		$method = 'section_' . $key;
		if ( method_exists( $this, $method ) ) { $this->$method(); }
	}

	// ═══════════════════════════════════════════════════════════════════
	// 1. QUICKSTART
	// ═══════════════════════════════════════════════════════════════════
	private function section_quickstart(): void { ?>
		<p class="plhelp-lead">Go from a fresh plugin install to a live interactive pizza builder in five steps. Each step links directly to the relevant admin page.</p>

		<div class="plhelp-steps">

			<div class="plhelp-step">
				<div class="plhelp-step__num">1</div>
				<div class="plhelp-step__body">
					<h3>Add your layer images</h3>
					<p>Every visual component (crust, sauce, cheese, toppings, drizzles, cuts) is a WordPress post with a <strong>layer image</strong> attached. Head to <strong>Content</strong> and create at least one Crust, one Sauce, one Cheese, and a few Toppings to start.</p>
					<div class="plhelp-checklist">
						<label><input type="checkbox"> Create at least 1 Crust with a layer image</label>
						<label><input type="checkbox"> Create at least 1 Sauce with a layer image</label>
						<label><input type="checkbox"> Create at least 1 Cheese with a layer image</label>
						<label><input type="checkbox"> Create 3–5 Toppings with layer images</label>
					</div>
					<p class="plhelp-tip-inline">💡 <strong>Image tips:</strong> Use transparent PNG or WebP at a consistent square canvas (500×500 px or 1000×1000 px). Keep files under 200 KB. All layers must use the same canvas size or they'll appear offset.</p>
					<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-content') ); ?>" class="button button-primary">Open Content Hub →</a>
				</div>
			</div>

			<div class="plhelp-step">
				<div class="plhelp-step__num">2</div>
				<div class="plhelp-step__body">
					<h3>Set global defaults in Settings</h3>
					<p>In Settings, configure which crust/sauce/cheese loads by default when the builder first appears. Also set your pizza shape, layer animation style, max toppings, and branding.</p>
					<p class="plhelp-tip-inline">⚡ <strong>First time?</strong> The <strong>Settings Wizard</strong> walks you through the most common settings — template, defaults, fractions, colours, layout — in a guided sequence. Use it for first-run configuration; come back to the full Settings page later for fine-tuning.</p>
					<div class="plhelp-checklist">
						<label><input type="checkbox"> Set a Default Crust, Sauce, and Cheese</label>
						<label><input type="checkbox"> Set Max Toppings (0 = unlimited)</label>
						<label><input type="checkbox"> Choose a Pizza Shape and Layer Animation</label>
					</div>
					<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-wizard') ); ?>" class="button button-primary">✦ Settings Wizard →</a>
					<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-settings') ); ?>" class="button">Full Settings →</a>
				</div>
			</div>

			<div class="plhelp-step">
				<div class="plhelp-step__num">3</div>
				<div class="plhelp-step__body">
					<h3>Choose your template</h3>
					<p>Templates control the entire visual design of the builder. PizzaTier ships with seven user-facing templates — <strong>Command Center</strong> (dark navy, step wizard), <strong>NightPie</strong> (dark, modern), <strong>Metro</strong> (clean, card-based), <strong>Colorbox</strong> (bright, colorful tiles), <strong>Fornaia</strong> (warm, rustic), <strong>PocketPie</strong> (mobile-first), and <strong>Plainlist</strong> (text-first, accessible) — plus <strong>Scaffold</strong>, a bare-bones developer starter for building your own. You can also create a custom template in your theme (see Template System).</p>
					<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-template') ); ?>" class="button">Template Settings →</a>
				</div>
			</div>

			<div class="plhelp-step">
				<div class="plhelp-step__num">4</div>
				<div class="plhelp-step__body">
					<h3>Embed the builder on a page</h3>
					<p>Edit any WordPress page and add:</p>
					<pre class="plhelp-code">[pizza_builder]</pre>
					<p>Or insert the <strong>Pizza Builder</strong> Gutenberg block from the block inserter. Use the Shortcode Generator to build more advanced shortcodes with per-page attribute overrides (custom shape, max toppings, hidden tabs, etc.).</p>
					<a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-shortcodes') ); ?>" class="button">Shortcode Generator →</a>
				</div>
			</div>

			<div class="plhelp-step">
				<div class="plhelp-step__num">5</div>
				<div class="plhelp-step__body">
					<h3>Preview and verify</h3>
					<p>Visit the page on the front end. Select a crust, sauce, cheese, and add toppings — the visualizer should update in real time. Open the browser console (F12) if anything appears broken.</p>
					<div class="plhelp-alert plhelp-alert--warn">
						<strong>Common first-time issues:</strong>
						<ul>
							<li>Pizza preview blank → confirm your default Crust has a layer image set in the post's custom fields.</li>
							<li>Layers misaligned → all layer images must use the same canvas size (e.g. all 500×500 px).</li>
							<li>Builder styles missing → some caching plugins strip inline <code>&lt;style&gt;</code> tags; check your caching settings or switch to enqueueing CSS files.</li>
							<li>JavaScript errors in console → check that jQuery is loading (it's a dependency), and that no other plugin is conflicting with the <code>$</code> global.</li>
						</ul>
					</div>
				</div>
			</div>

		</div><!-- /.plhelp-steps -->
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 1b. LAYER-BY-LAYER SETUP  (moved here from the Setup Guide page)
	// ═══════════════════════════════════════════════════════════════════
	private function section_setup(): void {
		// ── Layer guide tabs ─────────────────────────────────────────────
		$layer_tabs = [
			'layermaker' => [
				'label' => 'Layer Image Maker',
				'icon'  => 'dashicons-format-image',
				'intro' => 'Layer Image Maker is a built-in browser tool that lets you prepare any image as a pizza layer PNG — crop, adjust, and export without leaving WordPress. You can also send images directly to your Media Library and attach them to ingredients in one step.',
				'steps' => [
					'Go to <strong>PizzaTier → Layer Image Maker</strong> in the admin sidebar.',
					'Upload a source image by dropping it onto the upload zone, clicking to browse, or choosing from your <strong>Media Library</strong>.',
					'Select an <strong>Aspect Ratio</strong> — use <em>1:1 Square</em> for standard pizza layers (800×800 px), or match your pizza shape setting.',
					'Toggle <strong>Show pizza outline guide</strong> to see the circular pizza mask overlay. Your pizza art should fill roughly 90–95% of the circle. Toppings should spread across the <em>entire</em> canvas.',
					'Use the <strong>Adjustments</strong> panel: Brightness, Contrast, Saturation, Hue Shift, Blur, Sharpen, and Opacity. For cut overlays, drop Opacity to 20–40%. For sauces, a small softening helps blend edges.',
					'Use <strong>Remove background (threshold)</strong> if your image has a plain solid background — drag the threshold slider to erase it and create transparency.',
					'Click <strong>Download PNG</strong> to save the result locally, then upload it to the ingredient post field. Or click <strong>Send to Media Library</strong> to save it directly to WordPress — then attach it without leaving the ingredient editor.',
					'Repeat for each ingredient layer type (crust, sauce, cheese, topping, drizzle, cut). Each should be a separate PNG on a consistent canvas size.',
				],
				'tip'   => 'Workflow tip: open the Layer Image Maker in one browser tab and the ingredient editor (e.g. Add New Topping) in another. Process and send each image to the Media Library, then attach it in the editor without going back and forth.',
				'cpt'   => null,
				'page_link' => 'pizzatier-layer-maker',
			],
			'crusts' => [
				'label' => 'Crusts',
				'icon'  => 'dashicons-tag',
				'intro' => 'Crusts are the foundation of every pizza in the builder. Add at least one crust before testing the visualizer.',
				'steps' => [
					'Go to <strong>PizzaTier → Crusts</strong> and click <strong>Add New</strong>.',
					'Enter a clear title — e.g. <code>Thin Crust</code>, <code>Stuffed Crust</code>, <code>Gluten Free</code>.',
					'<strong>Prepare your layer image</strong> — use <strong>PizzaTier → Layer Image Maker</strong> to upload, crop, adjust brightness/contrast/opacity, and export a transparent PNG ready for upload. Aim for an 800×800 px square canvas with the pizza crust filling roughly 90–95% of the circle area.',
					'Upload the <strong>Crust Layer Image</strong> (<code>crust_layer_image</code>) — the transparent PNG that stacks on the visualizer. Use <strong>PizzaTier → Layer Image Maker</strong> to crop and export your image before uploading.',
					'Optionally upload a <strong>Crust Image</strong> (<code>crust_image</code>) for the selection card thumbnail.',
					'Fill in the <strong>Price Grid</strong> with size and pricing rows if you need per-crust pricing.',
					'Click <strong>Publish</strong>. Repeat for each crust option.',
				],
				'tip'   => 'Use transparent PNGs on a consistent 800×800 px square canvas for the cleanest layer stacking. Name files descriptively — e.g. <code>crust-thin.png</code>.',
				'cpt'   => 'crusts',
			],
			'sauces' => [
				'label' => 'Sauces',
				'icon'  => 'dashicons-admin-generic',
				'intro' => 'Sauces render as a layer directly on top of the crust. Add at least one to enable sauce selection in the builder.',
				'steps' => [
					'Go to <strong>PizzaTier → Sauces</strong> and click <strong>Add New</strong>.',
					'Enter a title — e.g. <code>Classic Tomato</code>, <code>Garlic White</code>, <code>BBQ</code>.',
					'Prepare your layer image using <strong>PizzaTier → Layer Image Maker</strong>: upload your sauce photo, adjust opacity and saturation for a natural look, and export a transparent PNG. Upload this as <strong>Sauce Layer Image</strong> (<code>sauce_layer_image</code>).',
					'Optionally add a <strong>Sauce Image</strong> (<code>sauce_image</code>) for the selection card thumbnail.',
					'Set pricing in the <strong>Price Grid</strong> if sauces have an upcharge.',
					'Click <strong>Publish</strong>.',
				],
				'tip'   => 'Semi-transparent layer images with soft edges look most natural when layered on top of a crust.',
				'cpt'   => 'sauces',
			],
			'cheeses' => [
				'label' => 'Cheeses',
				'icon'  => 'dashicons-category',
				'intro' => 'Cheeses are a separate layer type that sits between the sauce and toppings — great for offering Mozzarella, Vegan, Provolone, and more.',
				'steps' => [
					'Go to <strong>PizzaTier → Cheeses</strong> and click <strong>Add New</strong>.',
					'Give it a clear name — e.g. <code>Mozzarella</code>, <code>Provolone</code>, <code>Dairy Free</code>.',
					'Prepare your image in <strong>Layer Image Maker</strong>: crop to 800×800 px, adjust brightness and contrast, then export. Upload as <strong>Cheese Layer Image</strong> (<code>cheese_layer_image</code>).',
					'Optionally add a card thumbnail (<code>cheese_image</code>).',
					'Click <strong>Publish</strong>.',
				],
				'tip'   => 'A subtle melt pattern with a golden edge makes cheese images look convincingly realistic.',
				'cpt'   => 'cheeses',
			],
			'toppings' => [
				'label' => 'Toppings',
				'icon'  => 'dashicons-star-filled',
				'intro' => 'Toppings are the heart of the builder. Each one gets its own layer image and supports whole / half / quarter coverage placement.',
				'steps' => [
					'Go to <strong>PizzaTier → Toppings</strong> and click <strong>Add New</strong>.',
					'Enter a name — e.g. <code>Pepperoni</code>, <code>Mushrooms</code>, <code>Jalapeños</code>.',
					'Prepare your layer image using <strong>PizzaTier → Layer Image Maker</strong>: upload your topping art, use the rule-of-thirds guide to check coverage across the full 800×800 px canvas, adjust colors, and export a transparent PNG. Important: topping art must cover the <em>entire</em> canvas — PizzaTier clips it per coverage selection (whole, half, quarter).',
					'Upload the exported PNG as <strong>Topping Layer Image</strong> (<code>topping_layer_image</code>). You can do this directly from Layer Image Maker using the <em>Send to Media Library</em> button, then attach it here.',
					'Optionally add a <strong>Topping Image</strong> (<code>topping_image</code>) for the card thumbnail.',
					'Set a <strong>Max Toppings</strong> limit in <em>PizzaTier → Settings</em> if desired.',
					'Click <strong>Publish</strong>. Repeat for each topping.',
					'<em>Pricing:</em> install <strong>PizzaTierPro</strong> to configure per-topping price grids and WooCommerce checkout.',
				],
				'tip'   => 'Spread topping art across the <em>entire</em> 800×800 px canvas — do not centre or crop to one half. PizzaTier clips the image automatically for half/quarter portions.',
				'cpt'   => 'toppings',
			],
			'drizzles' => [
				'label' => 'Drizzles',
				'icon'  => 'dashicons-admin-customizer',
				'intro' => 'Drizzles are optional finishing layers that appear on top of everything — balsamic glaze, hot honey, ranch swirl, etc.',
				'steps' => [
					'Go to <strong>PizzaTier → Drizzles</strong> and click <strong>Add New</strong>.',
					'Enter a name — e.g. <code>Hot Honey</code>, <code>Balsamic</code>.',
					'Prepare your layer image in <strong>Layer Image Maker</strong>: use the opacity slider to create a semi-transparent drizzle look, then export. Upload as <strong>Drizzle Layer Image</strong> (<code>drizzle_layer_image</code>).',
					'Add a card thumbnail (<code>drizzle_image</code>).',
					'Click <strong>Publish</strong>.',
				],
				'tip'   => 'Asymmetric, flowing drizzle patterns look more handcrafted and appetizing than perfectly symmetrical ones.',
				'cpt'   => 'drizzles',
			],
			'cuts' => [
				'label' => 'Cuts',
				'icon'  => 'dashicons-editor-table',
				'intro' => 'Cut styles render as an overlay on the final pizza — triangle slices, square cuts, party-style, or whole.',
				'steps' => [
					'Go to <strong>PizzaTier → Cuts</strong> and click <strong>Add New</strong>.',
					'Enter a name — e.g. <code>8 Slices</code>, <code>Square Cut</code>, <code>Party Style</code>.',
					'Prepare your cut overlay in <strong>Layer Image Maker</strong>: use the opacity slider (typically 20–40%) to keep the cut lines subtle, then export. Upload as <strong>Cut Layer Image</strong> (<code>cut_layer_image</code>).',
					'Click <strong>Publish</strong>.',
				],
				'tip'   => 'Keep cut line images subtle — a low-opacity thin line lets the toppings beneath remain the star.',
				'cpt'   => 'cuts',
			],
			'imageprep' => [
				'label' => 'Image Prep',
				'icon'  => 'dashicons-format-image',
				'intro' => 'Getting your layer images right is the single most important step for a polished result. Use <strong>PizzaTier → Layer Image Maker</strong> to upload, crop, adjust, and export each image in one step — no external editor required.',
				'steps' => [
					'<strong>Use Layer Image Maker:</strong> Go to <strong>PizzaTier → Layer Image Maker</strong>. Upload or choose from your Media Library. Use the pizza outline guide to check placement, adjust brightness / contrast / saturation / opacity with the sliders, then click <strong>Download PNG</strong> — or <em>Send to Media Library</em> to save it directly to WordPress and attach it to your ingredient post.',
					'<strong>Format:</strong> Always use <strong>PNG with transparency</strong> (PNG-24 or PNG-32). JPEG has no transparency support — never use it for layer images.',
					'<strong>Canvas size:</strong> Use a <strong>square canvas — 800×800 px recommended</strong> for all layer types (crusts, sauces, cheeses, toppings, drizzles, cuts). Consistent canvas sizes ensure all layers stack with pixel-perfect alignment.',
					'<strong>Pizza circle placement:</strong> Centre your pizza art so the pizza circle fills roughly <strong>90–95% of the canvas width</strong>, with a small transparent gutter around the edge. This prevents clipping at different display sizes.',
					'<strong>Toppings — spread the full canvas:</strong> Topping images must cover the <em>entire</em> 800×800 px area naturally. PizzaTier applies CSS <code>clip-path</code> to mask the image to the selected portion (whole, half-left, half-right, quarter). If you centre the art on just half the canvas, the other half will be blank.',
					'<strong>Sauce &amp; cheese — soft edges:</strong> Use a soft brush or feathered edge where the sauce/cheese meets the transparent border. Hard edges look artificial at round pizza shapes.',
					'<strong>Drizzles — asymmetric is better:</strong> Irregular, flowing drizzle patterns look more handcrafted and appetising than perfectly symmetrical ones.',
					'<strong>Cut overlays — keep them subtle:</strong> Cut line images should use low opacity (20–40%) thin lines. The toppings beneath should remain the visual star.',
					'<strong>File naming:</strong> Use descriptive, lowercase, hyphenated filenames — e.g. <code>crust-thin.png</code>, <code>topping-pepperoni.png</code>. Avoid spaces and special characters.',
					'<strong>File size:</strong> Compress PNGs before uploading. Target under 200 KB per image. Use tools like <a href="https://tinypng.com" target="_blank" rel="noopener">TinyPNG</a> or <a href="https://squoosh.app" target="_blank" rel="noopener">Squoosh</a> without visibly reducing quality.',
					'<strong>Two image types per ingredient:</strong> (1) <strong>Layer Image</strong> (<code>*_layer_image</code>) — the transparent PNG that stacks on the visualizer pizza. Must be square, transparent background, consistent canvas size. (2) <strong>Card Image</strong> (<code>*_image</code>) — the thumbnail shown on the selection card in the builder UI. Can be JPEG or PNG, ideally a square crop at 200×200 px. You can use the same file for both, but having a tighter-cropped card image gives a cleaner UI.',
				],
				'tip'   => 'Do a quick "stack test" after adding each new layer: open your builder page and confirm the new layer aligns correctly with existing ones before publishing. Catching canvas size mismatches early saves time.',
				'cpt'   => null,
			],
			'settings' => [
				'label' => 'Settings',
				'icon'  => 'dashicons-admin-settings',
				'intro' => 'Fine-tune PizzaTier\'s behavior: set defaults, max toppings, template, and display options.',
				'steps' => [
					'Open <strong>PizzaTier → Settings</strong>.',
					'Set your <strong>Default Crust</strong>, <strong>Default Sauce</strong>, and <strong>Default Cheese</strong> — these pre-load in the builder.',
					'Set <strong>Max Toppings</strong> to limit how many toppings a customer can add.',
					'Configure <strong>Pizza display size</strong>, border, and topping fraction options.',
					'Set a <strong>Demo Notice</strong> or custom <strong>Help Screen</strong> content if needed.',
					'Save all settings.',
				],
				'tip'   => 'Setting sensible defaults (pre-selected crust and sauce) reduces friction and helps customers start building faster.',
				'cpt'   => null,
			],
			'shortcode' => [
				'label' => 'Embed',
				'icon'  => 'dashicons-editor-code',
				'intro' => 'Once your content is populated, embed the builder on any page using a shortcode.',
				'steps' => [
					'Go to <strong>PizzaTier → Shortcode Generator</strong>.',
					'Configure your builder options — template, max toppings, default layers, visible tabs.',
					'Copy the generated <code>[pizza_builder]</code> shortcode.',
					'Paste it into any WordPress page or post using the Block Editor or Classic Editor.',
					'Preview the page to confirm layers load and the visualizer responds to selections.',
				],
				'tip'   => 'You can place multiple builders on the same page by giving each a unique <code>id</code> attribute: <code>[pizza_builder id="pizza-1"]</code>.',
				'cpt'   => null,
			],
		];
		?>
		<div class="psg-card psg-card--tabs">
			<div class="psg-card__head">
				<p><?php esc_html_e( 'Select a section to see step-by-step instructions for setting it up.', 'pizzatier' ); ?></p>
			</div>

			<nav class="psg-tabnav" role="tablist">
				<?php $first = true; foreach ( $layer_tabs as $slug => $tab ) : ?>
				<button class="psg-tab<?php echo $first ? ' psg-tab--active' : ''; ?>"
				        data-tab="<?php echo esc_attr( $slug ); ?>"
				        role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
				        aria-controls="psg-panel-<?php echo esc_attr( $slug ); ?>">
					<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
					<?php echo esc_html( $tab['label'] ); ?>
				</button>
				<?php $first = false; endforeach; ?>
			</nav>

			<div class="psg-panels">
				<?php $first = true; foreach ( $layer_tabs as $slug => $tab ) : ?>
				<div class="psg-panel<?php echo $first ? ' psg-panel--active' : ''; ?>"
				     id="psg-panel-<?php echo esc_attr( $slug ); ?>" role="tabpanel">
					<p class="psg-panel__intro"><?php echo esc_html( $tab['intro'] ); ?></p>
					<ol class="psg-steps">
						<?php foreach ( $tab['steps'] as $step ) : ?>
						<li class="psg-steps__item"><?php echo wp_kses_post( $step ); ?></li>
						<?php endforeach; ?>
					</ol>
					<div class="psg-panel__tip">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php echo esc_html( $tab['tip'] ); ?>
					</div>
					<?php if ( $tab['cpt'] ) : ?>
					<div class="psg-panel__actions">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=pizzatier_' . $tab['cpt'] ) ); ?>" class="button">
							<span class="dashicons dashicons-list-view"></span> View All <?php echo esc_html( $tab['label'] ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pizzatier_' . $tab['cpt'] ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-plus-alt2"></span> Add New <?php echo esc_html( rtrim( $tab['label'], 's' ) ); ?>
						</a>
					</div>
					<?php elseif ( ! empty( $tab['page_link'] ) ) : ?>
				<div class="psg-panel__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . esc_attr( $tab['page_link'] ) ) ); ?>" class="button button-primary">
						<span class="dashicons dashicons-format-image"></span> Open Layer Image Maker
					</a>
				</div>
				<?php elseif ( $slug === 'settings' ) : ?>
					<div class="psg-panel__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-settings' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-admin-settings"></span> Open Settings
						</a>
					</div>
					<?php elseif ( $slug === 'shortcode' ) : ?>
					<div class="psg-panel__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-shortcodes' ) ); ?>" class="button button-primary">
							<span class="dashicons dashicons-editor-code"></span> Open Shortcode Generator
						</a>
					</div>
					<?php endif; ?>
				</div>
				<?php $first = false; endforeach; ?>
			</div>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 2. MANAGING CONTENT
	// ═══════════════════════════════════════════════════════════════════
	private function section_content(): void { ?>
		<p class="plhelp-lead">All pizza content lives in the <strong>Content Hub</strong> — a single admin page with a vertical tab rail for each of the 7 layer types. Click any type in the left rail to switch instantly without leaving the page.</p>

		<div class="plhelp-info-box">
			<span class="dashicons dashicons-info-outline"></span>
			<div>
				<strong>Getting there:</strong> PizzaTier → Content, or click the content type name in the top admin bar (PizzaTier → Toppings, Crusts, etc.). Use the <strong>+ New</strong> pill next to each type in the admin bar to jump directly to the add-new screen.
			</div>
		</div>

		<h3>Adding a new layer item (any type)</h3>

		<div class="plhelp-info-box">
			<span class="dashicons dashicons-superhero"></span>
			<div>
				<strong>Fast path: Layer Builder Wizard.</strong> The <a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-layer-wizard' ) ); ?>">Layer Builder Wizard</a> bundles image upload, title, slug, layer type, and meta fields into a single guided form and publishes the post for you. It's the fastest way to add toppings and ingredients in bulk. The manual flow below still works for advanced cases (custom taxonomy assignments, scheduled publishing, etc.).
			</div>
		</div>

		<p><strong>Manual flow:</strong></p>
		<ol class="plhelp-list plhelp-list--numbered">
			<li>In the Content Hub, click the layer type in the left rail (e.g. Toppings).</li>
			<li>Click <strong>Add New Topping</strong> (or the <strong>+</strong> icon beside the type in the rail).</li>
			<li>Enter a <strong>Title</strong> — this is the public name shown in the builder (e.g. "Pepperoni").</li>
			<li>Set the <strong>Featured Image</strong> — this is the selection card thumbnail.</li>
			<li>In the <strong>Layer Image</strong> custom field (below the editor), paste or upload the full-canvas transparent PNG/WebP for the visual stack.</li>
			<li>Add optional <strong>content</strong> for a description shown in some template styles.</li>
			<li>Click <strong>Publish</strong>.</li>
		</ol>

		<h3>Layer Image vs. Featured Image</h3>
		<table class="plhelp-attr-table">
			<thead><tr><th>Image</th><th>Where it appears</th><th>Recommended size</th></tr></thead>
			<tbody>
				<tr>
					<td><strong>Layer Image</strong> (custom field: <code>pzl_layer_image</code>)</td>
					<td>The pizza visualizer canvas — stacked with all other layers</td>
					<td>500×500 px or 1000×1000 px, transparent PNG/WebP</td>
				</tr>
				<tr>
					<td><strong>Featured Image</strong></td>
					<td>Selection card thumbnail in the builder UI</td>
					<td>200×200 px recommended, any format</td>
				</tr>
			</tbody>
		</table>

		<h3>Managing existing items</h3>
		<p>The Content Hub embeds the standard WordPress list table for each type. All native features work:</p>
		<ul class="plhelp-list">
			<li><strong>Search</strong> — use the search box above the table to find items by title.</li>
			<li><strong>Bulk actions</strong> — select multiple items with checkboxes and delete or trash in bulk.</li>
			<li><strong>Edit</strong> — click any title to open the native WordPress edit screen.</li>
			<li><strong>Quick Edit</strong> — hover over an item and click Quick Edit to change the title without leaving the list.</li>
			<li><strong>Sort</strong> — click column headers (Title, Date, Author) to re-sort the list.</li>
			<li><strong>Pagination</strong> — navigate pages if you have more than 20 items.</li>
		</ul>

		<h3>Sizes and pricing data</h3>
		<p>Sizes are not visual layers — they carry dimension metadata for PizzaTierPro pricing calculations. For each Size post, add these custom fields:</p>
		<table class="plhelp-attr-table">
			<thead><tr><th>Custom Field</th><th>Example value</th><th>Description</th></tr></thead>
			<tbody>
				<tr><td><code>size_diameter_in</code></td><td><code>12</code></td><td>Diameter in inches</td></tr>
				<tr><td><code>size_area_sqin</code></td><td><code>113.1</code></td><td>Area in square inches (π × r²)</td></tr>
			</tbody>
		</table>

		<h3>Tips for a production-ready setup</h3>
		<ul class="plhelp-list">
			<li>Create a "Plain / No Sauce" sauce item so customers can opt out of sauce without breaking the builder.</li>
			<li>Create a "No Cheese" cheese item similarly.</li>
			<li>Keep your layer image filenames descriptive (e.g. <code>topping-pepperoni-layer.png</code>) for easier management in the Media Library.</li>
			<li>Use WordPress <strong>Categories</strong> (available on all PizzaTier CPTs) to group items — some Pro templates use category filtering.</li>
		</ul>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 3. LAYER TYPES REFERENCE
	// ═══════════════════════════════════════════════════════════════════
	private function section_layers(): void {
		$types = [
			[
				'name'   => 'Crust',
				'icon'   => '⬤',
				'color'  => '#c8956c',
				'cpt'    => 'crusts',
				'z'      => 100,
				'desc'   => 'The base canvas. Every pizza starts with a crust. Only one crust can be selected at a time. The crust image defines the visible edge — all other layers must fit within it.',
				'fields' => [
					'Title'          => 'Public name shown in the builder (e.g. "Thin & Crispy", "Stuffed Crust").',
					'Layer Image'    => 'Full-canvas transparent PNG/WebP. The crust rim should be visible around the pizza edge.',
					'Featured Image' => 'Thumbnail shown in the selection card.',
					'Content'        => 'Optional description displayed in some template styles.',
				],
				'tips'   => 'Use a circular or correctly-shaped crust image on a transparent background. The crust anchors the visual stack — if it\'s off-center or wrong size, everything else will be too.',
			],
			[
				'name'   => 'Sauce',
				'icon'   => '🥫',
				'color'  => '#d63638',
				'cpt'    => 'sauces',
				'z'      => 200,
				'desc'   => 'Applied on top of the crust. Only one sauce is active at a time. Sits at z-index 200 in the visual stack.',
				'fields' => [
					'Title'          => 'Public name (e.g. "Classic Tomato", "Garlic White", "BBQ").',
					'Layer Image'    => 'Transparent PNG. Semi-transparent edges create a natural inset blend.',
					'Featured Image' => 'Selection card thumbnail.',
				],
				'tips'   => 'Keep the sauce layer slightly inset from the crust edge (around 5–8% of canvas width) so the crust rim stays visible and the pizza doesn\'t look flat.',
			],
			[
				'name'   => 'Cheese',
				'icon'   => '🧀',
				'color'  => '#dba633',
				'cpt'    => 'cheeses',
				'z'      => 300,
				'desc'   => 'Sits between sauce and toppings. Only one cheese active at a time. Z-index 300.',
				'fields' => [
					'Title'          => 'Public name (e.g. "Mozzarella", "Cheddar", "Dairy-Free").',
					'Layer Image'    => 'Transparent PNG with natural melt texture.',
					'Featured Image' => 'Selection card thumbnail.',
				],
				'tips'   => 'A slight golden-edge gradient on the cheese image looks convincingly melted. For a "No Cheese" option, create a Cheese post with no layer image — just a title of "No Cheese".',
			],
			[
				'name'   => 'Topping',
				'icon'   => '🥓',
				'color'  => '#f0b849',
				'cpt'    => 'toppings',
				'z'      => '400+',
				'desc'   => 'Multiple toppings can be active simultaneously. Each is a separate layer above cheese. Supports whole, half, and quarter coverage via CSS clip-path. Z-index starts at 400.',
				'fields' => [
					'Title'          => 'Public name (e.g. "Pepperoni", "Mushrooms", "Jalapeños").',
					'Layer Image'    => 'Full-canvas transparent PNG showing the topping distributed across the entire pizza area.',
					'Featured Image' => 'Selection card thumbnail.',
					'Content'        => 'Optional description (e.g. allergen info, flavor notes).',
				],
				'tips'   => 'Topping images should cover the whole pizza canvas — coverage (half/quarter) is applied via CSS clip-path at render time. No separate images needed for different coverages.',
			],
			[
				'name'   => 'Drizzle',
				'icon'   => '💧',
				'color'  => '#00a32a',
				'cpt'    => 'drizzles',
				'z'      => 900,
				'desc'   => 'Optional finishing layer above all toppings. Only one drizzle active at a time. Z-index 900.',
				'fields' => [
					'Title'          => 'Public name (e.g. "Balsamic Glaze", "Hot Honey", "Ranch").',
					'Layer Image'    => 'Transparent PNG with a flowing, organic drizzle pattern.',
					'Featured Image' => 'Selection card thumbnail.',
				],
				'tips'   => 'Drizzle images look best with an asymmetric, hand-poured feel — avoid perfectly symmetric radial patterns, which look computer-generated.',
			],
			[
				'name'   => 'Cut',
				'icon'   => '✂',
				'color'  => '#2271b1',
				'cpt'    => 'cuts',
				'z'      => 950,
				'desc'   => 'Slicing overlay applied above everything. Only one cut style active. Z-index 950.',
				'fields' => [
					'Title'          => 'Public name (e.g. "Classic Triangle", "Square Cut", "Party Style", "No Cut").',
					'Layer Image'    => 'Transparent PNG with thin slice lines. Light line weight so toppings show through.',
					'Featured Image' => 'Selection card thumbnail.',
				],
				'tips'   => 'Use ~15–20% opacity for slice lines so toppings remain visible beneath. Always create a "No Cut" option with a blank layer image.',
			],
			[
				'name'   => 'Size',
				'icon'   => '📏',
				'color'  => '#8c5af8',
				'cpt'    => 'sizes',
				'z'      => '—',
				'desc'   => 'Defines available pizza dimensions. Not a visual layer — carries metadata used by PizzaTierPro for pricing.',
				'fields' => [
					'Title'            => 'Public name (e.g. "Small – 10″", "Medium – 12″", "Large – 16″").',
					'size_diameter_in' => 'Custom field: diameter in inches.',
					'size_area_sqin'   => 'Custom field: area in square inches (used for per-area topping pricing in Pro).',
				],
				'tips'   => 'Calculate area accurately: area = π × (diameter/2)². For a 12″ pizza: π × 36 ≈ 113.1 sq in.',
			],
		];
		?>
		<p class="plhelp-lead">PizzaTier's visual stack is built from seven layer types. Each is a WordPress Custom Post Type. Here's the full reference for each type — fields, z-index order, image guidelines, and pro tips.</p>

		<div class="plhelp-stack-diagram">
			<div class="plhelp-stack-diagram__label">Visual Stack (bottom → top)</div>
			<div class="plhelp-stack-diagram__layers">
				<?php $stack = [
					['Crust', '#c8956c', '100'],
					['Sauce', '#d63638', '200'],
					['Cheese', '#dba633', '300'],
					['Toppings', '#f0b849', '400+'],
					['Drizzle', '#00a32a', '900'],
					['Cut', '#2271b1', '950'],
				];
				foreach ( $stack as $i => [$name, $color, $z] ) : ?>
				<div class="plhelp-stack-layer" style="--color:<?php echo esc_attr($color); ?>;--i:<?php echo esc_attr($i); ?>">
					<span class="plhelp-stack-layer__name"><?php echo esc_html($name); ?></span>
					<span class="plhelp-stack-layer__z">z: <?php echo esc_html($z); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="plhelp-layers-grid">
			<?php foreach ( $types as $t ) : ?>
			<div class="plhelp-layer-card">
				<div class="plhelp-layer-card__head" style="border-left-color:<?php echo esc_attr( $t['color'] ); ?>">
					<span class="plhelp-layer-card__icon" style="color:<?php echo esc_attr( $t['color'] ); ?>"><?php echo esc_html( $t['icon'] ); ?></span>
					<div>
						<h3><?php echo esc_html( $t['name'] ); ?></h3>
						<span class="plhelp-badge" style="background:<?php echo esc_attr( $t['color'] ); ?>20;color:<?php echo esc_attr( $t['color'] ); ?>">z-index: <?php echo esc_html( $t['z'] ); ?></span>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-content&pl_cpt=' . $t['cpt'] ) ); ?>" class="button button-small" style="margin-left:auto">Manage →</a>
				</div>
				<p class="plhelp-layer-card__desc"><?php echo esc_html( $t['desc'] ); ?></p>
				<table class="plhelp-fields-table">
					<thead><tr><th>Field</th><th>Purpose</th></tr></thead>
					<tbody>
						<?php foreach ( $t['fields'] as $field => $purpose ) : ?>
						<tr>
							<td><code><?php echo esc_html( $field ); ?></code></td>
							<td><?php echo wp_kses_post( $purpose ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="plhelp-tip">💡 <?php echo esc_html( $t['tips'] ); ?></div>
			</div>
			<?php endforeach; ?>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 4. SHORTCODES
	// ═══════════════════════════════════════════════════════════════════
	private function section_shortcodes(): void { ?>
		<p class="plhelp-lead">PizzaTier provides four shortcodes. Every attribute is optional — defaults come from the global Settings unless overridden at the shortcode level. Use the <a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-shortcodes') ); ?>">Shortcode Generator</a> for a visual builder.</p>

		<!-- ── [pizza_builder] ─────────────────────────────────────────── -->
		<div class="plhelp-sc-block">
			<div class="plhelp-sc-block__head">
				<code class="plhelp-sc-name">[pizza_builder]</code>
				<span class="plhelp-sc-tag">Interactive builder</span>
			</div>
			<p>Renders the full interactive pizza builder. Customers select layers, add toppings, see coverage options, and review their pizza in real time.</p>

			<table class="plhelp-attr-table">
				<thead><tr><th>Attribute</th><th>Values / format</th><th>Default</th><th>Description</th></tr></thead>
				<tbody>
					<tr><td><code>id</code></td><td>any string</td><td>auto</td><td>Unique instance ID. Required only when placing two builders on the same page (e.g. <code>id="pizza-1"</code>).</td></tr>
					<tr><td><code>template</code></td><td>template slug</td><td>active template</td><td>Override the template for this builder only (e.g. <code>template="nightpie"</code>).</td></tr>
					<tr><td><code>max_toppings</code></td><td>integer</td><td>global setting</td><td>Maximum toppings this builder allows. <code>0</code> = unlimited.</td></tr>
					<tr><td><code>show_tabs</code></td><td>comma list</td><td>all</td><td>Whitelist of tabs to show: <code>crust,sauce,cheese,toppings,drizzle,slicing,yourpizza</code></td></tr>
					<tr><td><code>hide_tabs</code></td><td>comma list</td><td>none</td><td>Tabs to hide. Simpler than listing all tabs you want to keep.</td></tr>
					<tr><td><code>default_crust</code></td><td>slug string</td><td>global setting</td><td>Pre-select a crust slug on load (e.g. <code>default_crust="thin-crust"</code>).</td></tr>
					<tr><td><code>default_sauce</code></td><td>slug string</td><td>global setting</td><td>Pre-select a sauce on load.</td></tr>
					<tr><td><code>default_cheese</code></td><td>slug string</td><td>global setting</td><td>Pre-select a cheese on load.</td></tr>
					<tr><td><code>pizza_shape</code></td><td><code>round</code> <code>square</code> <code>rectangle</code> <code>custom</code></td><td>global setting</td><td>Override pizza shape for this builder.</td></tr>
					<tr><td><code>pizza_aspect</code></td><td>CSS ratio e.g. <code>4 / 3</code></td><td>global setting</td><td>Aspect ratio for rectangle/custom shapes.</td></tr>
					<tr><td><code>pizza_radius</code></td><td>CSS value e.g. <code>12px</code></td><td>global setting</td><td>Border radius for custom shape.</td></tr>
					<tr><td><code>layer_anim</code></td><td><code>fade</code> <code>scale-in</code> <code>slide-up</code> <code>flip-in</code> <code>drop-in</code> <code>instant</code></td><td>global setting</td><td>Animation when a layer is added.</td></tr>
				</tbody>
			</table>

			<h4>Copy-paste examples</h4>
			<pre class="plhelp-code"><?php echo esc_html(
'[pizza_builder]

[pizza_builder id="pizza-1" max_toppings="5" default_crust="thin-crust" default_sauce="classic-tomato"]

[pizza_builder hide_tabs="drizzle,slicing" pizza_shape="square"]

[pizza_builder pizza_shape="rectangle" pizza_aspect="4 / 3" layer_anim="scale-in"]

[pizza_builder id="gf-builder" template="nightpie" default_cheese="dairy-free" hide_tabs="sizes,yourpizza"]'
); ?></pre>
		</div>

		<!-- ── [pizza_static] ──────────────────────────────────────────── -->
		<div class="plhelp-sc-block">
			<div class="plhelp-sc-block__head">
				<code class="plhelp-sc-name">[pizza_static]</code>
				<span class="plhelp-sc-tag plhelp-sc-tag--green">Static display</span>
			</div>
			<p>Renders a static pizza image stack — no builder UI. Great for menu pages, featured pizzas, inline displays in blog posts, or order confirmation pages.</p>

			<table class="plhelp-attr-table">
				<thead><tr><th>Attribute</th><th>Values</th><th>Description</th></tr></thead>
				<tbody>
					<tr><td><code>crust</code></td><td>slug</td><td>Crust slug to render.</td></tr>
					<tr><td><code>sauce</code></td><td>slug</td><td>Sauce slug to render.</td></tr>
					<tr><td><code>cheese</code></td><td>slug</td><td>Cheese slug to render.</td></tr>
					<tr><td><code>toppings</code></td><td>comma list</td><td>Topping slugs to stack (e.g. <code>pepperoni,mushrooms</code>).</td></tr>
					<tr><td><code>drizzle</code></td><td>slug</td><td>Drizzle layer to render.</td></tr>
					<tr><td><code>cut</code></td><td>slug</td><td>Cut overlay to render.</td></tr>
				</tbody>
			</table>

			<h4>Copy-paste examples</h4>
			<pre class="plhelp-code"><?php echo esc_html(
'[pizza_static crust="thin-crust" sauce="classic-tomato" cheese="mozzarella" toppings="pepperoni,basil"]

[pizza_static crust="thick-crust" sauce="garlic-white" cheese="cheddar" toppings="chicken,bacon" drizzle="ranch"]

[pizza_static crust="thin-crust" sauce="bbq" cheese="mozzarella" toppings="chicken,red-onion" cut="square-cut"]'
); ?></pre>
		</div>

		<!-- ── [pizza_layer] ──────────────────────────────────────────── -->
		<div class="plhelp-sc-block">
			<div class="plhelp-sc-block__head">
				<code class="plhelp-sc-name">[pizza_layer]</code>
				<span class="plhelp-sc-tag plhelp-sc-tag--purple">Single image</span>
			</div>
			<p>Renders a single layer image anywhere on the page — useful for ingredient spotlights, menu cards, "featured topping" sections, or decorative use.</p>

			<table class="plhelp-attr-table">
				<thead><tr><th>Attribute</th><th>Values</th><th>Description</th></tr></thead>
				<tbody>
					<tr><td><code>type</code></td><td><code>crust</code> <code>sauce</code> <code>cheese</code> <code>topping</code> <code>drizzle</code> <code>cut</code></td><td>The layer type to look up.</td></tr>
					<tr><td><code>slug</code></td><td>layer slug</td><td>The specific layer item to render.</td></tr>
					<tr><td><code>field</code></td><td><code>list</code> (default) · <code>layer</code></td><td><code>list</code> = featured image thumbnail; <code>layer</code> = full canvas layer image.</td></tr>
					<tr><td><code>class</code></td><td>CSS class string</td><td>Extra class(es) added to the <code>&lt;img&gt;</code> element.</td></tr>
				</tbody>
			</table>

			<h4>Copy-paste examples</h4>
			<pre class="plhelp-code"><?php echo esc_html(
'[pizza_layer type="topping" slug="pepperoni"]

[pizza_layer type="crust" slug="thick-crust" field="layer"]

[pizza_layer type="sauce" slug="bbq" class="my-sauce-preview"]'
); ?></pre>
		</div>

		<!-- ── [pizza_layer_info] ─────────────────────────────────────── -->
		<div class="plhelp-sc-block">
			<div class="plhelp-sc-block__head">
				<code class="plhelp-sc-name">[pizza_layer_info]</code>
				<span class="plhelp-sc-tag plhelp-sc-tag--purple">Field value</span>
			</div>
			<p>Outputs the value of any custom field attached to a layer post — useful for displaying allergen info, nutrition data, ingredient notes, or any ACF/SCF field inline in page content.</p>

			<table class="plhelp-attr-table">
				<thead><tr><th>Attribute</th><th>Values</th><th>Description</th></tr></thead>
				<tbody>
					<tr><td><code>type</code></td><td>layer type</td><td>The CPT type: <code>topping</code>, <code>crust</code>, <code>sauce</code>, <code>cheese</code>, <code>drizzle</code>, <code>cut</code>, <code>size</code>.</td></tr>
					<tr><td><code>slug</code></td><td>layer slug</td><td>The slug of the specific layer post to read from.</td></tr>
					<tr><td><code>field</code></td><td>field name</td><td>The custom field key to output (e.g. <code>topping_ingredients</code>, <code>size_diameter_in</code>).</td></tr>
				</tbody>
			</table>

			<h4>Copy-paste examples</h4>
			<pre class="plhelp-code"><?php echo esc_html(
'[pizza_layer_info type="topping" slug="pepperoni" field="topping_ingredients"]

[pizza_layer_info type="size" slug="large-16" field="size_diameter_in"]

[pizza_layer_info type="cheese" slug="mozzarella" field="cheese_melt_factor"]'
); ?></pre>
		</div>

		<div class="plhelp-info-box">
			<span class="dashicons dashicons-editor-code"></span>
			<div>
				<strong>Gutenberg Blocks:</strong> The three builder shortcodes (<code>[pizza_builder]</code>, <code>[pizza_static]</code>, <code>[pizza_layer]</code>) are available as native Gutenberg blocks. The Pizza Builder block includes the same attribute controls in the block sidebar — including per-block shape, animation, and tab visibility overrides. No shortcode syntax required.
			</div>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 5. SHAPE & ANIMATION
	// ═══════════════════════════════════════════════════════════════════
	private function section_shapes(): void { ?>
		<p class="plhelp-lead">PizzaTier supports multiple pizza canvas shapes and six layer-add animations. Set site-wide defaults in <a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-settings') ); ?>">Settings</a>, then override per-shortcode or per-block.</p>

		<h3>Pizza Shapes</h3>
		<div class="plhelp-shape-grid">
			<?php $shapes = [
				[ 'name' => 'Round',     'value' => 'round',     'style' => 'border-radius:50%;',                        'desc' => 'Classic circular pizza. Aspect ratio 1:1. Default.' ],
				[ 'name' => 'Square',    'value' => 'square',    'style' => 'border-radius:8px;',                        'desc' => 'Square pizza with rounded corners. Aspect ratio 1:1.' ],
				[ 'name' => 'Rectangle', 'value' => 'rectangle', 'style' => 'border-radius:12px;width:90px;height:68px;','desc' => 'Pan pizza or sheet pizza. Set your own aspect ratio.' ],
				[ 'name' => 'Custom',    'value' => 'custom',    'style' => 'border-radius:20px 6px;',                   'desc' => 'Full control: set both aspect-ratio and border-radius.' ],
			];
			foreach ( $shapes as $s ) : ?>
			<div class="plhelp-shape-card">
				<div class="plhelp-shape-preview" style="<?php echo esc_attr( $s['style'] ); ?>"></div>
				<strong><?php echo esc_html( $s['name'] ); ?></strong>
				<code>pizza_shape="<?php echo esc_html( $s['value'] ); ?>"</code>
				<p><?php echo esc_html( $s['desc'] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>

		<h4>Additional shape attributes</h4>
		<table class="plhelp-attr-table">
			<thead><tr><th>Attribute</th><th>Used with</th><th>Example</th><th>Description</th></tr></thead>
			<tbody>
				<tr><td><code>pizza_aspect</code></td><td>rectangle, custom</td><td><code>4 / 3</code>, <code>16 / 9</code></td><td>CSS aspect-ratio value. Controls width-to-height ratio.</td></tr>
				<tr><td><code>pizza_radius</code></td><td>custom</td><td><code>12px</code>, <code>50%</code>, <code>20px 6px</code></td><td>CSS border-radius. Overrides the preset's built-in radius.</td></tr>
			</tbody>
		</table>

		<h3>Layer Animations</h3>
		<p>The animation plays when a layer or topping is added to the pizza visualizer. Set a site-wide default in <a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-settings') ); ?>">Settings → Layer Animation</a>. Override per-shortcode with <code>layer_anim="..."</code>.</p>

		<table class="plhelp-attr-table">
			<thead><tr><th>Value</th><th>Effect</th><th>Duration</th><th>Best for</th></tr></thead>
			<tbody>
				<tr><td><code>fade</code></td><td>Simple opacity 0→1</td><td>300ms</td><td>Default. Subtle, professional, works everywhere.</td></tr>
				<tr><td><code>scale-in</code></td><td>Starts at 55% size, springs to full — bouncy cubic-bezier</td><td>320ms</td><td>Playful, energetic menus targeting younger audiences.</td></tr>
				<tr><td><code>slide-up</code></td><td>Enters from 22% below, slides smoothly to position</td><td>320ms</td><td>Modern material-style UIs with vertical hierarchy.</td></tr>
				<tr><td><code>flip-in</code></td><td>3-D Y-axis rotate from 90° to 0° with slight bounce</td><td>400ms</td><td>High-impact, premium reveal feel.</td></tr>
				<tr><td><code>drop-in</code></td><td>Falls from 30% above the visualizer, snaps into place</td><td>320ms</td><td>Fun, gravity-driven interaction.</td></tr>
				<tr><td><code>instant</code></td><td>No animation — appears immediately</td><td>0ms</td><td>Accessibility needs, performance-critical contexts.</td></tr>
			</tbody>
		</table>

		<div class="plhelp-info-box">
			<span class="dashicons dashicons-universal-access-alt"></span>
			<div>
				<strong>Accessibility:</strong> PizzaTier's animation engine respects the OS-level "Reduce Motion" preference (<code>prefers-reduced-motion: reduce</code>). Users with that setting active will always see instant layer changes, regardless of which animation mode is configured.
			</div>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 6. TEMPLATE SYSTEM
	// ═══════════════════════════════════════════════════════════════════
	private function section_templates(): void { ?>
		<p class="plhelp-lead">Templates control the complete visual presentation of the pizza builder — layout, colours, fonts, card styles, and responsive behaviour. PizzaTier ships with seven production-ready user-facing templates (<strong>Command Center</strong>, <strong>NightPie</strong>, <strong>Metro</strong>, <strong>Colorbox</strong>, <strong>Fornaia</strong>, <strong>PocketPie</strong>, <strong>Plainlist</strong>) plus a bare <strong>Scaffold</strong> template for building your own from scratch.</p>

		<h3>How templates work</h3>
		<p>A template is a directory containing at minimum:</p>
		<pre class="plhelp-code">your-template-slug/
  pztp-containers-menu.php   ← main builder HTML + PHP logic
  template.css               ← template-specific styles
  custom.js                  ← template-specific JavaScript
  template-preview.jpg       ← screenshot shown in the Template picker (optional)</pre>

		<h3>Template load order</h3>
		<ol class="plhelp-list plhelp-list--numbered">
			<li><strong>Child theme:</strong> <code>/wp-content/themes/your-child-theme/pzttemplates/your-slug/</code></li>
			<li><strong>Parent theme:</strong> <code>/wp-content/themes/your-theme/pzttemplates/your-slug/</code></li>
			<li><strong>Plugin:</strong> <code>/wp-content/plugins/pizzatier/templates/your-slug/</code></li>
		</ol>
		<p>PizzaTier checks the child theme first — your customisations survive plugin updates safely.</p>

		<h3>Creating a custom template</h3>
		<ol class="plhelp-list plhelp-list--numbered">
			<li>Copy the <code>nightpie</code> directory from <code>/plugins/pizzatier/templates/</code> to your theme's <code>pzttemplates/</code> folder.</li>
			<li>Rename the directory to your slug (e.g. <code>mypizzeria</code>).</li>
			<li>Edit <code>template.css</code> — all main variables are CSS custom properties at the top of the file.</li>
			<li>Restructure HTML in <code>pztp-containers-menu.php</code> as needed. The <code>$atts</code> and <code>$instance_id</code> variables are available.</li>
			<li>Go to <a href="<?php echo esc_url( admin_url('admin.php?page=pizzatier-template') ); ?>">Settings → Template</a> and switch to your new template.</li>
		</ol>

		<h3>Per-template settings (Templates page)</h3>
		<p>Most templates expose customizable settings — colors, fonts, geometry, layout toggles — directly on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-template' ) ); ?>">Templates page</a>. When you activate a template, its settings panel appears below the picker. Changes save to the WordPress options table and are injected as CSS custom property overrides on the front end, so you don't need to edit <code>template.css</code> for routine theming. Each template namespaces its option keys with a <code>{slug}_setting_</code> prefix (e.g. <code>nightpie_setting_*</code>, <code>commandcenter_setting_*</code>).</p>

		<h3>CSS custom properties — direct overrides</h3>
		<p>If you want to override tokens outside the Templates UI (e.g. in your theme stylesheet, a child template, or a custom <code>:root</code> block), each template defines its variables on its scoped root selector. Below are the actual variables shipped by two of the templates.</p>

		<h4>NightPie (<code>:root</code>)</h4>
		<pre class="plhelp-code">/* Colours */
--np-accent:        #ff5722   /* primary action colour */
--np-accent-dim:    rgba(255,87,34,0.15)
--np-bg:            #0e0e12   /* outer dark background */
--np-surface:       #18181f   /* card / panel surfaces */
--np-surface-2:     #22222c
--np-surface-3:     #2c2c38
--np-border:        rgba(255,255,255,0.08)
--np-border-hover:  rgba(255,255,255,0.18)
--np-text:          #f0f0f4
--np-text-muted:    #888898
--np-text-faint:    #444456

/* Geometry */
--np-radius-sm:     10px
--np-radius:        16px
--np-radius-lg:     24px
--np-radius-pill:   999px

/* Change the accent colour only: */
:root { --np-accent: #e63946; --np-accent-dim: rgba(230,57,70,0.15); }</pre>

		<h4>Command Center (<code>.cc-root</code>)</h4>
		<pre class="plhelp-code">/* Colours */
--cc-accent:        #e94560   /* red accent */
--cc-accent-hover:  #ff5572
--cc-step-done:     #3dd68c   /* completed wizard step */
--cc-bg:            #0b1120   /* deep navy background */
--cc-surface:       #16213e   /* cards, sidebar, header */
--cc-surface-2:     #1e2d4f
--cc-text:          #e8eaf6
--cc-text-muted:    #8892b0

/* Geometry */
--cc-radius-sm:     8px
--cc-radius:        12px
--cc-radius-lg:     16px

/* Override accent in a child theme: */
.cc-root { --cc-accent: #2563eb; --cc-accent-hover: #3b82f6; }</pre>

		<div class="plhelp-info-box plhelp-info-box--warn">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong>Always use your theme directory for custom templates.</strong> Files inside <code>/plugins/pizzatier/templates/</code> are overwritten on plugin update. Anything in <code>/pzttemplates/</code> in your theme is safe.
			</div>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 7. SITE MIGRATION
	// ═══════════════════════════════════════════════════════════════════
	private function section_migration(): void { ?>
		<p class="plhelp-lead">Move an entire PizzaTier setup — settings, ingredients, custom fields, taxonomy, and Pro data — to another WordPress installation. Site Migration produces a single JSON file you can carry between sites.</p>

		<h3>What gets exported</h3>
		<ul class="plhelp-list">
			<li><strong>Plugin settings.</strong> Every <code>pizzatier_setting_*</code> option, the active template, custom CSS / custom JS, and Settings-page configuration.</li>
			<li><strong>All eight content types</strong> — Toppings, Crusts, Sauces, Cheeses, Drizzles, Cuts, Sizes, Presets — with full title, slug, content, excerpt, status, and menu order.</li>
			<li><strong>All custom fields (post meta)</strong> per item, including any user-added meta keys (allergens, ingredient lists, melt factors, etc.).</li>
			<li><strong>The Ingredient Groups taxonomy tree</strong> with parent/child relationships, plus per-post term assignments.</li>
			<li><strong>Layer image references</strong> — URL, filename, alt text, and caption — instead of packaged binary files. The destination site sideloads each image into its own media library by URL on import.</li>
			<li><strong>PizzaTierPro data</strong>, when Pro is installed and active. Pro contributes its own settings, pricing grids, and Pro-specific post meta via the <code>pizzatier_export_payload</code> filter.</li>
		</ul>

		<h3>How to migrate a site</h3>
		<ol class="plhelp-list plhelp-list--numbered">
			<li>On the source site, go to <strong>PizzaTier → Site Migration</strong> and click <strong>Download Full Export</strong>. You'll get a <code>pizzatier-site-{date}.json</code> file.</li>
			<li>On the destination WordPress installation, install and activate PizzaTier (and PizzaTierPro, if you used it on the source).</li>
			<li>Go to <strong>PizzaTier → Site Migration</strong> on the destination site, choose which sections to restore, upload the JSON, and click <strong>Run Import</strong>.</li>
			<li>Wait for the sideload — each layer image is downloaded from its original URL into the destination media library. The source site must be reachable over HTTP for this step to succeed.</li>
		</ol>

		<div class="plhelp-info-box plhelp-info-box--warn">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong>Settings overwrite, posts don't.</strong> Importing settings will replace this site's current PizzaTier settings — back them up first. Posts and taxonomy terms are <strong>create-only by slug</strong>: any item with a slug that already exists on the destination is skipped, never overwritten. Re-running the same import is safe.
			</div>
		</div>

		<h3>Image handling</h3>
		<p>Layer images travel as URL references. This keeps export files small (typically a few hundred KB even for stores with hundreds of layer items) and avoids the complexity of bundling binary attachments. Each reference includes the original URL, the file name, the alt text, and any caption — so the imported attachment lands in the destination media library with its metadata intact.</p>
		<p>If the source site goes offline before you import, the JSON still imports cleanly — you just won't get the images. You can swap in replacement images per post afterwards via the standard <strong>Layer Image</strong> meta box.</p>

		<h3>For developers — extending the export</h3>
		<p>PizzaTierPro and other add-ons hook into two filters / actions:</p>
		<table class="plhelp-attr-table">
			<thead><tr><th>Hook</th><th>Type</th><th>Args</th><th>Description</th></tr></thead>
			<tbody>
				<tr>
					<td><code>pizzatier_export_payload</code></td>
					<td>filter</td>
					<td><code>$payload</code></td>
					<td>Add your data to the export array before serialization. Standard convention: contribute under the <code>pro</code> key (Pro) or a clearly namespaced top-level key (other add-ons).</td>
				</tr>
				<tr>
					<td><code>pizzatier_import_payload</code></td>
					<td>action</td>
					<td><code>$payload, $results</code></td>
					<td>Fires after the free-plugin import sections have run. Read your section out of <code>$payload</code> and apply it. Honour create-only-by-slug semantics for anything user-facing.</td>
				</tr>
			</tbody>
		</table>

		<h4>Example: Pro contributes its data</h4>
		<pre class="plhelp-code"><?php echo esc_html(
'add_filter( \'pizzatier_export_payload\', function( $payload ) {
    $payload[\'pro\'] = [
        \'settings\' => [
            \'pro_setting_currency\'   => get_option( \'pro_setting_currency\' ),
            \'pro_setting_price_grid\' => get_option( \'pro_setting_price_grid\' ),
        ],
        // Per-post Pro meta is already exported under each post\'s `meta`
        // key, so you usually don\'t need to duplicate it here.
    ];
    return $payload;
} );

add_action( \'pizzatier_import_payload\', function( $payload, $results ) {
    if ( empty( $payload[\'pro\'] ) ) { return; }
    foreach ( $payload[\'pro\'][\'settings\'] ?? [] as $key => $value ) {
        if ( strpos( $key, \'pro_setting_\' ) !== 0 ) { continue; }
        update_option( sanitize_key( $key ), $value );
    }
}, 10, 2 );'
); ?></pre>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 8. FAQ
	// ═══════════════════════════════════════════════════════════════════
	private function section_faq(): void {
		$faqs = [
			[ 'q' => 'The pizza preview is blank — nothing shows on the canvas.',
			  'a' => 'The most common cause is a missing or broken layer image on the default crust. Go to <strong>Content → Crusts</strong>, open your default crust post, and confirm the <code>pzl_layer_image</code> custom field has a valid image URL. Also open the browser console (F12 → Console) and check for JavaScript errors — if the builder script failed to load, no layers will appear.' ],
			[ 'q' => 'Layers look misaligned or don\'t stack correctly.',
			  'a' => 'All layer images must use the same canvas size with a transparent background. If your crust is 500×500 px but a topping was exported at 400×400 px (with no padding to reach 500×500), it will appear offset. Re-export on a consistent canvas across all assets.' ],
			[ 'q' => 'Can I place two builders on the same page?',
			  'a' => 'Yes. Give each shortcode a unique <code>id</code>: <code>[pizza_builder id="pizza-1"]</code> and <code>[pizza_builder id="pizza-2"]</code>. Each instance manages its own state independently.' ],
			[ 'q' => 'The builder CSS conflicts with my theme.',
			  'a' => 'PizzaTier templates use namespaced CSS classes (e.g. <code>.np-*</code> for NightPie) to avoid conflicts. If you still see issues, open your browser inspector, identify the conflicting rule\'s selector, and add a more specific override in your theme\'s custom CSS or in a child template\'s <code>template.css</code>.' ],
			[ 'q' => 'How do I add WooCommerce cart integration?',
			  'a' => 'WooCommerce integration (add-to-cart, line item breakdown, per-topping pricing) is provided by <strong>PizzaTierPro</strong>. The base plugin handles the visual builder; Pro handles the commerce layer.' ],
			[ 'q' => 'Can I display a static pizza without the full builder?',
			  'a' => 'Yes — use <code>[pizza_static crust="thin-crust" sauce="tomato" cheese="mozzarella" toppings="pepperoni"]</code>. Specify each layer directly in the shortcode attributes. No builder UI is rendered.' ],
			[ 'q' => 'How do I pre-load a state from JavaScript (e.g. from a WooCommerce cart)?',
			  'a' => 'Use the public API: <code>window.PizzaTierAPI.setState("instance-id", stateObject)</code>. See the Developer Reference section for the full state object schema.' ],
			[ 'q' => 'Does PizzaTier respect the "Reduce Motion" accessibility preference?',
			  'a' => 'Yes. The animation engine checks for <code>prefers-reduced-motion: reduce</code>. Users with that setting active always see instant layer changes regardless of the configured animation mode.' ],
			[ 'q' => 'My custom template doesn\'t appear in the Template picker.',
			  'a' => 'Check that the directory is placed in a scanned location (<code>pzttemplates/your-slug/</code> in your theme root or child theme root) and contains a <code>pztp-containers-menu.php</code> file. The slug equals the directory name. After adding it, go to Template settings and refresh.' ],
			[ 'q' => 'How do I set a "No Sauce" or "No Cheese" option?',
			  'a' => 'Create a Sauce (or Cheese) post with the title "No Sauce" and leave the Layer Image field empty. When a customer selects it, the sauce layer is cleared from the canvas. Set <code>default_sauce=""</code> on the shortcode to not pre-select any sauce.' ],
			[ 'q' => 'The + Add New link in the admin bar goes to the wrong screen.',
			  'a' => 'Make sure you\'re using the PizzaTier admin bar item, not a separate WordPress CPT menu item. The PizzaTier admin bar groups its CPTs under the PizzaTier dropdown — the "+ New" pill next to each type links to <code>post-new.php?post_type=pizzatier_{type}</code>.' ],
			[ 'q' => 'Can I use toppings in the "Your Pizza" summary tab without them counting against the max?',
			  'a' => 'The max toppings limit applies to the toppings panel only. Drizzle and cut layers are always unlimited (only one of each active at a time). To exclude certain toppings from the count, you would need a custom filter on <code>pizzatier_max_toppings</code> (see Developer Reference).' ],
		];
		?>
		<p class="plhelp-lead">Common questions about setup, content management, and customisation.</p>
		<div class="plhelp-faq">
			<?php foreach ( $faqs as $i => $faq ) : ?>
			<details class="plhelp-faq__item" <?php echo $i === 0 ? 'open' : ''; ?>>
				<summary class="plhelp-faq__q">
					<span class="plhelp-faq__arrow">▶</span>
					<?php echo esc_html( $faq['q'] ); ?>
				</summary>
				<div class="plhelp-faq__a"><?php echo wp_kses_post( $faq['a'] ); ?></div>
			</details>
			<?php endforeach; ?>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// 8. DEVELOPER REFERENCE
	// ═══════════════════════════════════════════════════════════════════
	private function section_developer(): void { ?>
		<p class="plhelp-lead">PizzaTier is built for extensibility. This reference documents public PHP hooks, the JavaScript API, REST endpoints, namespace conventions, and CPT meta keys.</p>

		<div class="plhelp-dev-banner">
			<div class="plhelp-dev-banner__badge">🔧 Expanding documentation</div>
			<p>A fully-searchable, versioned developer reference with code examples for every hook, endpoint, and API method is in progress. What's below is the complete current public surface — every hook and method PizzaTier exposes today.</p>
		</div>

		<!-- PHP Actions ─────────────────────────────────────────────────── -->
		<h3>PHP Action Hooks</h3>
		<table class="plhelp-attr-table">
			<thead><tr><th>Hook</th><th>Args</th><th>Description</th></tr></thead>
			<tbody>
				<tr>
					<td><code>pizzatier_cpt_registered</code></td>
					<td>—</td>
					<td>Fires after all 8 CPTs have been registered (7 layer types plus the Presets CPT). Use to add taxonomies, modify CPT args, or register dependent functionality.</td>
				</tr>
				<tr>
					<td><code>pizzatier_before_builder</code></td>
					<td><code>$instance_id</code>, <code>$atts</code></td>
					<td>Fires immediately before the builder HTML is output. Use to inject wrapper elements or enqueue additional scripts scoped to this instance.</td>
				</tr>
				<tr>
					<td><code>pizzatier_after_builder</code></td>
					<td><code>$instance_id</code>, <code>$atts</code></td>
					<td>Fires immediately after the builder HTML. Use to inject post-builder UI (e.g. a WooCommerce add-to-cart form).</td>
				</tr>
				<tr>
					<td><code>pizzatier_admin_bar_menu</code></td>
					<td><code>$wp_admin_bar</code></td>
					<td>Add custom items to the PizzaTier dropdown in the WordPress admin bar.</td>
				</tr>
				<tr>
					<td><code>pizzatier_admin_home_quicknav</code></td>
					<td>—</td>
					<td>Inject additional icon cards into the Dashboard's quick-nav row.</td>
				</tr>
				<tr>
					<td><code>pizzatier_admin_home_cards</code></td>
					<td>—</td>
					<td>Inject full-width cards below the feature row on the Dashboard.</td>
				</tr>
				<tr>
					<td><code>pizzatier_import_payload</code></td>
					<td><code>$payload, $results</code></td>
					<td>Fires after the free-plugin import has run on Site Migration. Pro and other add-ons hook here to consume their own section of the JSON payload. See the Site Migration section for an example.</td>
				</tr>
			</tbody>
		</table>

		<!-- PHP Filters ─────────────────────────────────────────────────── -->
		<h3>PHP Filter Hooks</h3>
		<table class="plhelp-attr-table">
			<thead><tr><th>Filter</th><th>Args</th><th>Returns</th><th>Description</th></tr></thead>
			<tbody>
				<tr>
					<td><code>pizzatier_cpt_args_{slug}</code></td>
					<td><code>$args, $post_type</code></td>
					<td><code>$args</code></td>
					<td>Modify CPT registration args per type. Replace <code>{slug}</code> with e.g. <code>toppings</code>. Fires before <code>register_post_type()</code>.</td>
				</tr>
				<tr>
					<td><code>pizzatier_builder_atts</code></td>
					<td><code>$atts</code></td>
					<td><code>$atts</code></td>
					<td>Filter all resolved shortcode/block attributes before the builder template renders. Useful for dynamic defaults.</td>
				</tr>
				<tr>
					<td><code>pizzatier_max_toppings</code></td>
					<td><code>$count, $instance_id</code></td>
					<td><code>$count</code></td>
					<td>Dynamically change the max topping count per builder instance (e.g. based on a selected pizza size or WooCommerce product).</td>
				</tr>
				<tr>
					<td><code>pizzatier_template_path</code></td>
					<td><code>$path, $slug</code></td>
					<td><code>$path</code></td>
					<td>Override the resolved filesystem path for a template file — useful for plugin-to-plugin template sharing or testing.</td>
				</tr>
				<tr>
					<td><code>pizzatier_export_payload</code></td>
					<td><code>$payload</code></td>
					<td><code>$payload</code></td>
					<td>Add data to the Site Migration export JSON before serialization. Pro hooks here to contribute its settings, pricing grids, and Pro-only meta under the <code>pro</code> key.</td>
				</tr>
			</tbody>
		</table>

		<h4>Example: enforce a max toppings based on pizza size</h4>
		<pre class="plhelp-code"><?php echo esc_html(
'add_filter( \'pizzatier_max_toppings\', function( $count, $instance_id ) {
    $size = WC()->session->get( \'selected_pizza_size\' );
    if ( $size === \'small\' ) { return 3; }
    return $count;
}, 10, 2 );'
); ?></pre>

		<!-- JavaScript API ──────────────────────────────────────────────── -->
		<h3>JavaScript API (<code>window.PizzaTierAPI</code>)</h3>
		<p>Available on any page where the builder is loaded. All methods are synchronous unless noted.</p>

		<table class="plhelp-attr-table">
			<thead><tr><th>Method</th><th>Returns</th><th>Description</th></tr></thead>
			<tbody>
				<tr><td><code>getState(instanceId)</code></td><td>state object</td><td>Get the current pizza state for a builder instance.</td></tr>
				<tr><td><code>setState(instanceId, state)</code></td><td>void</td><td>Programmatically set the full pizza state (resets builder, then applies new state).</td></tr>
				<tr><td><code>getAllInstances()</code></td><td>string[]</td><td>List all active builder instance IDs on the page.</td></tr>
				<tr><td><code>renderPizza(layers)</code></td><td>Promise&lt;string&gt;</td><td>Async. Fetches server-rendered pizza HTML stack via REST. Resolves to HTML string.</td></tr>
				<tr><td><code>getLayerUrl(type, slug)</code></td><td>Promise&lt;string&gt;</td><td>Async. Resolves the layer image URL for a type + slug pair.</td></tr>
				<tr><td><code>renderStatic(selectorOrEl, state)</code></td><td>jQuery stage</td><td>Client-side: render a pizza state into any DOM container without a server request.</td></tr>
			</tbody>
		</table>

		<h4>State object schema</h4>
		<pre class="plhelp-code"><?php echo esc_html(
'{
  crust:   { slug: "thin-crust",  title: "Thin Crust",  layerImg: "https://...", thumb: "https://..." },
  sauce:   { slug: "tomato",      title: "Classic Tomato", layerImg: "https://...", thumb: "https://..." },
  cheese:  { slug: "mozzarella",  title: "Mozzarella",  layerImg: "https://...", thumb: "https://..." },
  drizzle: { slug: "balsamic",    title: "Balsamic",    layerImg: "https://...", thumb: "https://..." },
  cut:     { slug: "triangle",    title: "Triangle Cut", layerImg: "https://...", thumb: "https://..." },
  toppings: {
    "pepperoni": { slug: "pepperoni", title: "Pepperoni", layerImg: "https://...", thumb: "https://...", zindex: 400, coverage: "whole" },
    "mushrooms": { slug: "mushrooms", title: "Mushrooms", layerImg: "https://...", thumb: "https://...", zindex: 401, coverage: "half-left" }
  }
}'
); ?></pre>

		<h4>Full usage examples</h4>
		<pre class="plhelp-code"><?php echo esc_html(
'// Get state
var state = window.PizzaTierAPI.getState(\'pizza-1\');

// Set state
window.PizzaTierAPI.setState(\'pizza-1\', {
    crust:  { slug: \'thin-crust\', layerImg: \'...\', title: \'Thin Crust\' },
    sauce:  { slug: \'tomato\',     layerImg: \'...\', title: \'Classic Tomato\' },
    toppings: {
        pepperoni: { slug: \'pepperoni\', layerImg: \'...\', zindex: 400, coverage: \'whole\' }
    }
});

// List all instances
var ids = window.PizzaTierAPI.getAllInstances();  // e.g. [\'pizza-1\', \'pizza-2\']

// Render pizza HTML via REST (async)
window.PizzaTierAPI.renderPizza({
    crust: \'thin-crust\', sauce: \'tomato\', toppings: [\'pepperoni\', \'mushrooms\']
}).then(function(html) {
    document.getElementById(\'my-pizza\').innerHTML = html;
});

// Get a layer URL (async)
window.PizzaTierAPI.getLayerUrl(\'topping\', \'pepperoni\').then(function(url) {
    myImg.src = url;
});

// Client-side static render (no server request)
window.PizzaTierAPI.renderStatic(\'#my-container\', stateObject);'
); ?></pre>

		<!-- REST API ────────────────────────────────────────────────────── -->
		<h3>REST API Endpoints</h3>
		<p>All endpoints are under the <code>/wp-json/pizzatier/v1/</code> namespace. Both endpoints are read-only and publicly accessible — no authentication or nonce is required. They must be enabled first under <strong>Settings &rarr; Advanced &rarr; REST API</strong>.</p>

		<table class="plhelp-attr-table">
			<thead><tr><th>Method</th><th>Endpoint</th><th>Body / Params</th><th>Response</th></tr></thead>
			<tbody>
				<tr>
					<td><code>POST</code></td>
					<td><code>/pizzatier/v1/render</code></td>
					<td><code>{ crust, sauce, cheese, toppings[], drizzle, cut, preset }</code></td>
					<td><code>{ html: "..." }</code> — full pizza stack HTML</td>
				</tr>
				<tr>
					<td><code>GET</code></td>
					<td><code>/pizzatier/v1/layer-url</code></td>
					<td><code>?type=topping&amp;slug=pepperoni</code></td>
					<td><code>{ url: "https://..." }</code></td>
				</tr>

			</tbody>
		</table>

		<!-- Namespace & Meta Keys ──────────────────────────────────────── -->
		<h3>PHP Namespace &amp; Class Map</h3>
		<pre class="plhelp-code"><?php echo esc_html(
'// Core
PizzaTier\Plugin                  — main plugin bootstrap and dependency wiring
PizzaTier\Core\Loader             — action/filter registration aggregator
PizzaTier\Core\Activator          — runs on plugin activation (rewrite flush, defaults)
PizzaTier\Core\Deactivator        — runs on plugin deactivation (cleanup)

// Admin
PizzaTier\Admin\AdminMenu         — admin menu + submenu registration
PizzaTier\Admin\AdminBar          — WP admin bar items
PizzaTier\Admin\AdminHome         — dashboard home page
PizzaTier\Admin\ContentHub        — unified content management (tab rail + list tables)
PizzaTier\Admin\Customizer        — WordPress Customizer integration
PizzaTier\Admin\Settings          — full settings page (all option tabs)
PizzaTier\Admin\SettingsWizard    — guided step-by-step settings wizard
PizzaTier\Admin\Help              — help & reference documentation page
PizzaTier\Admin\SetupGuide        — onboarding checklist
PizzaTier\Admin\ShortcodeGenerator — visual shortcode builder
PizzaTier\Admin\TemplateChoice    — template picker UI + per-template settings panel
PizzaTier\Admin\LayerBuilderWizard — step-by-step wizard for adding ingredients
PizzaTier\Admin\LayerImageMaker   — in-admin layer image generation tool
PizzaTier\Admin\LayerImageMetaBox — layer image custom field meta box
PizzaTier\Admin\SiteMigration     — full-site export/import (settings + CPTs + meta + Pro hook)

// Post Types
PizzaTier\PostTypes\PostTypeRegistrar — all 8 CPT registrations (7 layer types + Presets)

// Shortcodes
PizzaTier\Shortcodes\BuilderShortcode    — [pizza_builder]
PizzaTier\Shortcodes\StaticShortcode     — [pizza_static]
PizzaTier\Shortcodes\LayerImageShortcode — [pizza_layer]
PizzaTier\Shortcodes\LayerInfoShortcode  — [pizza_layer_info]

// Template
PizzaTier\Template\TemplateLoader — template path resolution + file loading
PizzaTier\Template\TemplateAPI    — public template query helpers

// Builder
PizzaTier\Builder\PizzaBuilder    — builder HTML output + state management
PizzaTier\Builder\LayerRenderer   — single layer image rendering
PizzaTier\Builder\LayerDTO        — layer data transfer object

// API & Assets
PizzaTier\Api\PizzaRestApi        — REST route registration (/pizzatier/v1/)
PizzaTier\Assets\AssetManager     — script + style enqueue management
PizzaTier\Frontend\FrontendSettings — frontend JS config injection
PizzaTier\Blocks\BlockRegistrar   — Gutenberg block registration'
); ?></pre>

		<h3>CPT Meta Keys</h3>
		<table class="plhelp-attr-table">
			<thead><tr><th>CPT</th><th>Meta key</th><th>Description</th></tr></thead>
			<tbody>
				<tr><td>All layer types</td><td><code>pzl_layer_image</code></td><td>Full-canvas layer image URL (used in the visual stack)</td></tr>
				<tr><td>Sizes</td><td><code>size_diameter_in</code></td><td>Pizza diameter in inches</td></tr>
				<tr><td>Sizes</td><td><code>size_area_sqin</code></td><td>Pizza area in square inches</td></tr>
			</tbody>
		</table>

		<div class="plhelp-info-box plhelp-info-box--dev">
			<span class="dashicons dashicons-admin-plugins"></span>
			<div>
				<strong>Building an add-on?</strong> Check for <code>class_exists('PizzaTier\Plugin')</code> before hooking in. Use <code>pizzatier_cpt_registered</code> as your init point to guarantee all CPTs are available. The public JS API is available on any page the builder loads — no additional enqueue needed.
			</div>
		</div>
	<?php }

	// ═══════════════════════════════════════════════════════════════════
	// STYLES
	// ═══════════════════════════════════════════════════════════════════
	private function render_styles(): void { ?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
	<?php }
}
