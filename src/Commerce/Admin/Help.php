<?php
/**
 * PizzaTier Help & Reference Page
 *
 * Tabbed documentation covering: overview, price grid, cart & orders,
 * display settings, developer hooks, and FAQ.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Help {

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sections = $this->get_sections();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only use of a request value for display; no state is changed.
		$active   = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'overview';
		if ( ! array_key_exists( $active, $sections ) ) {
			$active = 'overview';
		}
		$page_url = admin_url( 'admin.php?page=pizzatier-commerce-help' );

		?>
		<div class="wrap pztc-help">
			<?php $this->render_styles(); ?>

			<div class="pztc-help__header">
				<div class="pztc-help__header-inner">
					<div class="pztc-help__header-brand">
						<span class="dashicons dashicons-editor-help pztc-help__header-icon" aria-hidden="true"></span>
						<div>
							<h1 class="pztc-help__title"><?php esc_html_e( 'PizzaTier Help & Reference', 'pizzatier' ); ?></h1>
							<p class="pztc-help__subtitle"><?php esc_html_e( 'Documentation for WooCommerce integration, pricing, cart configuration, and developer hooks.', 'pizzatier' ); ?></p>
						</div>
					</div>
					<div class="pztc-help__header-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier' ) ); ?>" class="pztc-help__hbtn">
							<?php esc_html_e( 'Dashboard', 'pizzatier' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) ); ?>" class="pztc-help__hbtn pztc-help__hbtn--outline">
							<?php esc_html_e( 'Cart & Pricing', 'pizzatier' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="pztc-help__layout">

				<!-- Sidebar nav -->
				<nav class="pztc-help__nav" aria-label="<?php esc_attr_e( 'Help sections', 'pizzatier' ); ?>">
					<?php foreach ( $sections as $slug => $section ) :
						$is_active = $slug === $active;
						?>
						<a href="<?php echo esc_url( add_query_arg( 'section', $slug, $page_url ) ); ?>"
						   class="pztc-help__nav-item <?php echo $is_active ? 'pztc-help__nav-item--active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>"></span>
							<?php echo esc_html( $section['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<!-- Content -->
				<div class="pztc-help__content">
					<?php $this->render_section( $active ); ?>
				</div>

			</div><!-- .pztc-help__layout -->
		</div><!-- .pztc-help -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Sections index
	// -------------------------------------------------------------------------

	private function get_sections(): array {
		return [
			'overview'  => [ 'label' => __( 'Overview', 'pizzatier' ),          'icon' => 'dashicons-info' ],
			'price-grid'=> [ 'label' => __( 'Price Grid', 'pizzatier' ),         'icon' => 'dashicons-grid-view' ],
			'cart'      => [ 'label' => __( 'Cart & Orders', 'pizzatier' ),      'icon' => 'dashicons-cart' ],
			'display'   => [ 'label' => __( 'Display Settings', 'pizzatier' ),   'icon' => 'dashicons-admin-appearance' ],
			'migration' => [ 'label' => __( 'Site Migration', 'pizzatier' ),     'icon' => 'dashicons-migrate' ],
			'developer' => [ 'label' => __( 'Developer Reference', 'pizzatier' ),'icon' => 'dashicons-code-standards' ],
			'faq'       => [ 'label' => __( 'FAQ', 'pizzatier' ),                'icon' => 'dashicons-editor-help' ],
		];
	}

	// -------------------------------------------------------------------------
	// Section renderers
	// -------------------------------------------------------------------------

	/**
	 * Render one section into another page.
	 *
	 * Since 2.0.0 the cart-and-pricing help sections are listed alongside the
	 * rest of PizzaTier's documentation on the single Help screen, which calls
	 * this. The section markup is reused verbatim rather than transcribed into
	 * the host page's conventions, so the content cannot drift; the styles this
	 * class emits are sent once per request, on first use.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Section slug, as returned by get_sections().
	 */
	public function render_embedded_section( string $slug ): void {
		static $styles_sent = false;
		if ( ! $styles_sent ) {
			$this->render_styles();
			$styles_sent = true;
		}
		echo '<div class="pztc-help">';
		$this->render_section( $slug );
		echo '</div>';
	}

	/** @return array<string,array{label:string,icon:string}> */
	public function get_embeddable_sections(): array {
		return $this->get_sections();
	}

	private function render_section( string $slug ): void {
		switch ( $slug ) {
			case 'overview':   $this->section_overview();   break;
			case 'price-grid': $this->section_price_grid(); break;
			case 'cart':       $this->section_cart();       break;
			case 'display':    $this->section_display();    break;
			case 'migration':  $this->section_migration();  break;
			case 'developer':  $this->section_developer();  break;
			case 'faq':        $this->section_faq();        break;
		}
	}

	private function section_overview(): void {
		?>
		<h2><?php esc_html_e( 'What PizzaTier Adds', 'pizzatier' ); ?></h2>
		<p><?php esc_html_e( 'PizzaTier is a WooCommerce integration layer for the PizzaTier pizza builder plugin. It bridges the visual pizza builder with the WooCommerce cart, checkout, and order system.', 'pizzatier' ); ?></p>

		<?php $this->card_grid( [
			[ 'icon' => 'dashicons-products',    'title' => __( 'Pizza Product Type', 'pizzatier' ),  'desc' => __( 'Adds a custom "Pizza" product type to WooCommerce with a dedicated configurator tab in the product editor.', 'pizzatier' ) ],
			[ 'icon' => 'dashicons-grid-view',   'title' => __( 'Price Grid', 'pizzatier' ),          'desc' => __( 'Per-product pricing matrix: set prices by size (rows) and coverage fraction (columns) for precise topping pricing.', 'pizzatier' ) ],
			[ 'icon' => 'dashicons-cart',        'title' => __( 'WooCommerce Cart', 'pizzatier' ),    'desc' => __( 'Server-verified add-to-cart with pizza configuration stored as order line-item meta.', 'pizzatier' ) ],
			[ 'icon' => 'dashicons-visibility',  'title' => __( 'Frontend Builder Embed', 'pizzatier' ), 'desc' => __( 'Automatically injects the PizzaTier builder on pizza product pages with size selector and live price bar.', 'pizzatier' ) ],
			[ 'icon' => 'dashicons-admin-generic','title' => __( 'Cart & Pricing', 'pizzatier' ),       'desc' => __( 'Central settings for cart behaviour, display labels, default grid sizes, and more.', 'pizzatier' ) ],
			[ 'icon' => 'dashicons-list-view',   'title' => __( 'Order Detail', 'pizzatier' ),        'desc' => __( 'Pizza size and toppings breakdown stored on every order line item, visible in admin and customer confirmation.', 'pizzatier' ) ],
		] ); ?>

		<h3><?php esc_html_e( 'Requirements', 'pizzatier' ); ?></h3>
		<ul class="pztc-help__list">
			<li><?php esc_html_e( 'WordPress 6.0+', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'WooCommerce 8.0+', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'PizzaTier 1.0+', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'PHP 7.4+', 'pizzatier' ); ?></li>
		</ul>
		<?php
	}

	private function section_price_grid(): void {
		?>
		<h2><?php esc_html_e( 'Price Grid', 'pizzatier' ); ?></h2>
		<p><?php esc_html_e( 'The price grid defines how much each topping costs depending on pizza size and coverage fraction (e.g. whole pizza, half, quarter).', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'How it works', 'pizzatier' ); ?></h3>
		<ol class="pztc-help__list">
			<li><?php esc_html_e( 'Open a Pizza product → Pizza Configurator tab → Price Grid section.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'Rows = pizza sizes (e.g. Small, Medium, Large, XL).', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'Columns = coverage fractions (e.g. Whole, Half, Quarter).', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'Each cell holds the price per topping for that size + coverage combination.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'Add/remove rows and columns using the + button. Export/import via CSV.', 'pizzatier' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Default sizes & fractions', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'Default labels can be configured globally under PizzaTier → Pro Settings → Grid Defaults. Each product can override these by adding or removing rows/columns.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Price calculation', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'When the customer adds to cart, the selected size and each topping\'s coverage fraction are looked up in the grid. The total is calculated server-side (REST endpoint) — the client-side display is always verified before the cart is updated.', 'pizzatier' ); ?></p>

		<?php $this->notice( __( 'Cells left blank show the configured Fallback Price Label in the live price bar instead of a number. Set fallback text under Pricing.', 'pizzatier' ) ); ?>

		<h3><?php esc_html_e( 'CSV Import / Export', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'Use the Export button to download the current grid as CSV. Edit offline and re-import. The CSV format is: first column = Size, remaining columns = one per fraction. First row = header.', 'pizzatier' ); ?></p>
		<?php
	}

	private function section_cart(): void {
		?>
		<h2><?php esc_html_e( 'Cart & Orders', 'pizzatier' ); ?></h2>

		<h3><?php esc_html_e( 'Add-to-cart flow', 'pizzatier' ); ?></h3>
		<ol class="pztc-help__list">
			<li><?php esc_html_e( 'Customer builds their pizza in the builder and selects a size.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'Clicking "Add to Cart" sends the configuration (product ID, size, layer list with fractions) via AJAX.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'The server verifies the price against the saved price grid.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'The item is added to the WooCommerce cart with the verified price locked in.', 'pizzatier' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'Cart display', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'In the WooCommerce cart and checkout, each pizza item shows its configured size and a toppings list beneath the product name.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Order admin', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'In WooCommerce → Orders, each pizza line item has an expandable breakdown table showing size, each topping with coverage, and the verified total.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Checkout bar', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'An optional checkout bar can appear inside the PizzaTier builder canvas itself (in addition to the standard WooCommerce add-to-cart button). Enable it under Cart & Pricing → General → "Show Add to Cart button in builder".', 'pizzatier' ); ?></p>
		<p><?php esc_html_e( 'The checkout bar HTML lives in each template\'s folder (e.g. templates/colorbox/checkout-bar.php) so it can be styled per-template or overridden by copying to your child theme.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Require crust / sauce', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'Enable "Require crust selection" and/or "Require sauce selection" in Cart & Pricing to block the add-to-cart button until the customer has made those selections.', 'pizzatier' ); ?></p>
		<?php
	}

	private function section_display(): void {
		?>
		<h2><?php esc_html_e( 'Display Settings Reference', 'pizzatier' ); ?></h2>
		<p><?php
			printf(
				/* translators: %s: settings page URL */
				wp_kses( __( 'All settings are at <a href="%s">PizzaTier → Cart & Pricing</a>.', 'pizzatier' ), [ 'a' => [ 'href' => [] ] ] ),
				esc_url( admin_url( 'admin.php?page=pizzatier-commerce' ) )
			);
		?></p>

		<?php $this->settings_table( [
			[ __( 'Show size selector', 'pizzatier' ),          __( 'Shows pill-style size buttons above the builder.', 'pizzatier' ) ],
			[ __( 'Size selector heading', 'pizzatier' ),        __( 'Label above the size pills. Default: "Choose your size".', 'pizzatier' ) ],
			[ __( 'Show live price bar', 'pizzatier' ),          __( 'Shows a dark bar below the builder with the running total.', 'pizzatier' ) ],
			[ __( 'Price bar label', 'pizzatier' ),              __( 'Text left of the price amount. Default: "Your pizza total:".', 'pizzatier' ) ],
			[ __( 'Fallback price label', 'pizzatier' ),         __( 'Shown when a grid cell is unconfigured. Default: "Price calculated on selection".', 'pizzatier' ) ],
			[ __( 'Default builder position', 'pizzatier' ),     __( 'Global default for where the builder is injected on product pages. Per-product override available in Pizza Configurator tab.', 'pizzatier' ) ],
			[ __( 'Show Add to Cart button in builder', 'pizzatier' ), __( 'Renders a checkout bar inside the PizzaTier canvas.', 'pizzatier' ) ],
			[ __( 'Add to Cart button text', 'pizzatier' ),      __( 'Custom label for the in-builder button.', 'pizzatier' ) ],
			[ __( 'Redirect to cart after adding', 'pizzatier' ),__( 'Overrides WooCommerce\'s redirect setting for pizza products only.', 'pizzatier' ) ],
		] ); ?>
		<?php
	}

	private function section_migration(): void {
		?>
		<h2><?php esc_html_e( 'Site Migration', 'pizzatier' ); ?></h2>
		<p><?php esc_html_e( 'Site Migration exports your cart and pricing configuration alongside everything else, so importing on a new installation restores the whole setup in one pass.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'How to migrate', 'pizzatier' ); ?></h3>
		<ol class="pztc-help__list">
			<li><?php
				printf(
					/* translators: %s: link HTML to PizzaTier Site Migration page */
					esc_html__( 'On the source site, go to %1$s and click %2$s.', 'pizzatier' ),
					'<strong>' . esc_html__( 'PizzaTier → Site Migration', 'pizzatier' ) . '</strong>',
					'<strong>' . esc_html__( 'Download Full Export', 'pizzatier' ) . '</strong>'
				); ?></li>
			<li><?php esc_html_e( 'Install both PizzaTier and PizzaTier on the destination site, then activate both. Activate WooCommerce too if your source uses pizza products.', 'pizzatier' ); ?></li>
			<li><?php esc_html_e( 'On the destination, open Site Migration, upload the JSON, and make sure the "PizzaTier data" checkbox is ticked. Run the import.', 'pizzatier' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'What is included in the export', 'pizzatier' ); ?></h3>
		<ul class="pztc-help__list">
			<li><strong><?php esc_html_e( 'Cart &amp; Pricing settings.', 'pizzatier' ); ?></strong> <?php esc_html_e( 'The whole cart & pricing option — pricing mode, free toppings, fractions, sizes, cart behaviour, nutrition toggles, layer-group flags, and advanced options.', 'pizzatier' ); ?></li>
			<li><strong><?php esc_html_e( 'Setup-completion flag.', 'pizzatier' ); ?></strong> <?php esc_html_e( 'Whether you finished the Setup Guide on the source site.', 'pizzatier' ); ?></li>
			<li><strong><?php esc_html_e( 'WooCommerce pizza products.', 'pizzatier' ); ?></strong> <?php esc_html_e( 'Every product whose product_type is "pizza" — title, slug, description, SKU, price, taxonomies, all _pizzatier_commerce_* meta (price grid, builder template, enabled layers, pricing mode), and the featured image (sideloaded by URL on import). Regular WooCommerce products are not touched.', 'pizzatier' ); ?></li>
		</ul>

		<p class="pztc-help__note"><?php esc_html_e( 'Per-layer data — nutrition values and layer price grids on toppings, crusts, sauces, cheeses, drizzles, cuts, sizes and presets — already travels with each layer post through the post-meta walk, so it is not duplicated here.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Import policy', 'pizzatier' ); ?></h3>
		<ul class="pztc-help__list">
			<li><strong><?php esc_html_e( 'Settings overwrite', 'pizzatier' ); ?></strong> — <?php esc_html_e( 'consistent with how the rest of the settings are handled. Local keys absent from the export are preserved — a merge, not a wholesale replace.', 'pizzatier' ); ?></li>
			<li><strong><?php esc_html_e( 'WC products are create-only by slug', 'pizzatier' ); ?></strong> — <?php esc_html_e( 'if a product with the same slug exists on the destination, it\'s skipped. Re-running an import is safe.', 'pizzatier' ); ?></li>
			<li><strong><?php esc_html_e( 'No WooCommerce on the destination?', 'pizzatier' ); ?></strong> <?php esc_html_e( 'Pro settings still import; WC product import is silently skipped.', 'pizzatier' ); ?></li>
		</ul>
		<?php
	}

	private function section_developer(): void {
		?>
		<h2><?php esc_html_e( 'Developer Reference', 'pizzatier' ); ?></h2>

		<h3><?php esc_html_e( 'Action Hooks', 'pizzatier' ); ?></h3>
		<?php $this->hook_table( [
			[ 'pizzatier_commerce_before_add_to_cart_button', '$product',  __( 'Fires inside the pizza add-to-cart wrapper, before the button. Useful for validation notices.', 'pizzatier' ) ],
			[ 'pizzatier_commerce_after_add_to_cart_button',  '$product',  __( 'Fires inside the pizza add-to-cart wrapper, after the button.', 'pizzatier' ) ],
			[ 'pizzatier_builder_action_bar',     '$instance_id', __( 'Fires inside the PizzaTier builder canvas. PizzaTier uses this to inject the checkout bar.', 'pizzatier' ) ],
		] ); ?>

		<h3><?php esc_html_e( 'Filter Hooks (Pro provides)', 'pizzatier' ); ?></h3>
		<?php $this->hook_table( [
			[ 'pizzatier_show_cart_btn',  '(bool)',   __( 'Controls whether the in-builder cart button is shown. Read from the cart & pricing settings.', 'pizzatier' ) ],
			[ 'pizzatier_cart_btn_text',  '(string)', __( 'Label for the in-builder cart button.', 'pizzatier' ) ],
			[ 'pizzatier_require_crust',  '(bool)',   __( 'Whether a crust selection is required before add-to-cart.', 'pizzatier' ) ],
			[ 'pizzatier_require_sauce',  '(bool)',   __( 'Whether a sauce selection is required before add-to-cart.', 'pizzatier' ) ],
		] ); ?>

		<h3><?php esc_html_e( 'REST API', 'pizzatier' ); ?></h3>
		<table class="pztc-help__table">
			<thead><tr><th><?php esc_html_e( 'Endpoint', 'pizzatier' ); ?></th><th><?php esc_html_e( 'Method', 'pizzatier' ); ?></th><th><?php esc_html_e( 'Description', 'pizzatier' ); ?></th></tr></thead>
			<tbody>
				<tr><td><code>/wp-json/pizzatier/v1/price</code></td><td>POST</td><td><?php esc_html_e( 'Calculates and returns the server-verified price for a given product ID, size, and layer configuration.', 'pizzatier' ); ?></td></tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'AJAX Actions', 'pizzatier' ); ?></h3>
		<table class="pztc-help__table">
			<thead><tr><th><?php esc_html_e( 'Action', 'pizzatier' ); ?></th><th><?php esc_html_e( 'Description', 'pizzatier' ); ?></th></tr></thead>
			<tbody>
				<tr><td><code>pizzatier_commerce_add_to_cart</code></td><td><?php esc_html_e( 'Adds a configured pizza to the WooCommerce cart. Requires nonce. Available to guests and logged-in users.', 'pizzatier' ); ?></td></tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Helper Function', 'pizzatier' ); ?></h3>
		<pre class="pztc-help__code">pizzatier_get_option( string $key, $default = null );</pre>
		<p><?php esc_html_e( 'Retrieves a single cart & pricing setting from the options array. Safe to call from themes or other plugins.', 'pizzatier' ); ?></p>

		<h3><?php esc_html_e( 'Checkout Bar Template Override', 'pizzatier' ); ?></h3>
		<p><?php esc_html_e( 'The checkout bar HTML is loaded from the active PizzaTier template folder. To customise it without modifying plugin files, copy the file to your child theme:', 'pizzatier' ); ?></p>
		<pre class="pztc-help__code">your-child-theme/pizzatier/checkout-bar.php</pre>
		<?php
	}

	private function section_faq(): void {
		$faqs = [
			[
				__( 'The Pizza Configurator tab does not appear.', 'pizzatier' ),
				__( 'Make sure the product type is set to "Pizza" in the Product Data dropdown. The tab uses show_if_pizza CSS class so it only appears for pizza products.', 'pizzatier' ),
			],
			[
				__( 'The price grid is not visible in the product editor.', 'pizzatier' ),
				__( 'The price grid is in the Pizza Configurator tab inside the Product Data metabox. If the metabox is collapsed, click its header to expand it. Also ensure the product type is set to "Pizza".', 'pizzatier' ),
			],
			[
				__( 'The builder does not appear on the product page.', 'pizzatier' ),
				__( 'Check that the Pizza Configurator tab has a PizzaTier template selected and saved. Also verify the builder position in the tab or in Cart & Pricing.', 'pizzatier' ),
			],
			[
				__( 'Prices are showing as 0.00.', 'pizzatier' ),
				__( 'The price grid cells need to be filled in for the selected size. If a cell is blank the fallback label is shown. Make sure the size the customer selected has prices in the grid.', 'pizzatier' ),
			],
			[
				__( 'Can I use multiple pizza products with different price grids?', 'pizzatier' ),
				__( 'Yes. The price grid is stored per-product (as post meta). Each pizza product can have completely different sizes, fractions, and prices.', 'pizzatier' ),
			],
			[
				__( 'How do I customise the checkout bar appearance?', 'pizzatier' ),
				__( 'Each PizzaTier template folder contains a checkout-bar.php file. Copy it to your child theme under pizzatier/checkout-bar.php to override the markup, or edit the CSS in PizzaTier\'s assets/css/frontend.css.', 'pizzatier' ),
			],
			[
				__( 'Why does the add-to-cart button show "Configure your pizza above"?', 'pizzatier' ),
				__( 'The JS is waiting for the builder to signal that it is ready. Make sure the PizzaTier shortcode is correctly loading on the page.', 'pizzatier' ),
			],
		];
		?>
		<h2><?php esc_html_e( 'Frequently Asked Questions', 'pizzatier' ); ?></h2>
		<div class="pztc-help__faq">
			<?php foreach ( $faqs as $faq ) : ?>
				<details class="pztc-help__faq-item">
					<summary class="pztc-help__faq-q"><?php echo esc_html( $faq[0] ); ?></summary>
					<div class="pztc-help__faq-a"><?php echo esc_html( $faq[1] ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Component helpers
	// -------------------------------------------------------------------------

	private function card_grid( array $cards ): void {
		echo '<div class="pztc-help__cards">';
		foreach ( $cards as $card ) {
			echo '<div class="pztc-help__card">';
			echo '<span class="pztc-help__card-icon dashicons ' . esc_attr( $card['icon'] ) . '"></span>';
			echo '<strong class="pztc-help__card-title">' . esc_html( $card['title'] ) . '</strong>';
			echo '<p class="pztc-help__card-desc">' . esc_html( $card['desc'] ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function settings_table( array $rows ): void {
		echo '<table class="pztc-help__table"><thead><tr><th>' . esc_html__( 'Setting', 'pizzatier' ) . '</th><th>' . esc_html__( 'Description', 'pizzatier' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><strong>' . esc_html( $row[0] ) . '</strong></td><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function hook_table( array $rows ): void {
		echo '<table class="pztc-help__table"><thead><tr><th>' . esc_html__( 'Hook', 'pizzatier' ) . '</th><th>' . esc_html__( 'Args', 'pizzatier' ) . '</th><th>' . esc_html__( 'Description', 'pizzatier' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td><code>' . esc_html( $row[0] ) . '</code></td><td><code>' . esc_html( $row[1] ) . '</code></td><td>' . esc_html( $row[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function notice( string $text ): void {
		echo '<div class="pztc-help__notice"><span class="dashicons dashicons-info"></span>' . esc_html( $text ) . '</div>';
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	private function render_styles(): void {
		?>
		<style>
		.pztc-help { max-width: 1100px; }
		.pztc-help__header { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #fff; border-radius: 10px; padding: 22px 28px; margin-bottom: 24px; border-bottom: 3px solid #ff6b35; }
		.pztc-help__header-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
		.pztc-help__header-brand { display: flex; align-items: center; gap: 16px; }
		.pztc-help__header-icon { font-size: 38px !important; width: 38px !important; height: 38px !important; color: #ff6b35; flex-shrink: 0; }
		.pztc-help__header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
		.pztc-help__hbtn { display: inline-flex; align-items: center; padding: 8px 18px; background: #ff6b35; color: #fff !important; border-radius: 50px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .2s; border: 2px solid transparent; }
		.pztc-help__hbtn:hover { background: #e05a28; }
		.pztc-help__hbtn--outline { background: transparent; border-color: rgba(255,255,255,.3); color: rgba(255,255,255,.8) !important; }
		.pztc-help__hbtn--outline:hover { border-color: #ff6b35; color: #fff !important; background: transparent; }
		.pztc-help__title { color: #fff; font-size: 22px; margin: 0 0 4px; }
		.pztc-help__subtitle { color: rgba(255,255,255,.6); margin: 0; font-size: 13px; }
		.pztc-help__layout { display: flex; gap: 24px; align-items: flex-start; }
		.pztc-help__nav { width: 200px; flex-shrink: 0; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; position: sticky; top: 32px; }
		.pztc-help__nav-item { display: flex; align-items: center; gap: 8px; padding: 12px 16px; text-decoration: none; color: #444; font-size: 13px; border-bottom: 1px solid #f0f0f0; transition: background .15s; }
		.pztc-help__nav-item:hover { background: #f9f9f9; color: #1a1a2e; }
		.pztc-help__nav-item--active { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #fff; font-weight: 600; }
		.pztc-help__nav-item--active .dashicons { color: #ff6b35; }
		.pztc-help__content { flex: 1; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 28px 32px; }
		.pztc-help__content h2 { margin-top: 0; border-bottom: 2px solid #ff6b35; padding-bottom: 8px; }
		.pztc-help__list { padding-left: 20px; }
		.pztc-help__list li { margin-bottom: 6px; }
		.pztc-help__cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin: 16px 0; }
		.pztc-help__card { background: #f9f9f9; border: 1px solid #e8e8e8; border-radius: 8px; padding: 16px; }
		.pztc-help__card-icon { font-size: 24px !important; color: #ff6b35; display: block; margin-bottom: 8px; }
		.pztc-help__card-title { display: block; margin-bottom: 6px; font-size: 13px; }
		.pztc-help__card-desc { margin: 0; font-size: 12px; color: #666; }
		.pztc-help__table { width: 100%; border-collapse: collapse; margin: 12px 0 20px; font-size: 13px; }
		.pztc-help__table th { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #fff; padding: 8px 12px; text-align: left; }
		.pztc-help__table td { padding: 8px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
		.pztc-help__table tr:nth-child(even) td { background: #f9f9f9; }
		.pztc-help__notice { background: #fff8e5; border-left: 4px solid #ff6b35; border-radius: 4px; padding: 10px 14px; margin: 12px 0; display: flex; gap: 8px; font-size: 13px; }
		.pztc-help__notice .dashicons { color: #ff6b35; flex-shrink: 0; }
		.pztc-help__code { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #ff6b35; padding: 10px 14px; border-radius: 6px; font-size: 13px; overflow-x: auto; }
		.pztc-help__faq { display: flex; flex-direction: column; gap: 8px; }
		.pztc-help__faq-item { border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden; }
		.pztc-help__faq-q { padding: 12px 16px; cursor: pointer; font-weight: 600; font-size: 13px; list-style: none; background: #f9f9f9; }
		.pztc-help__faq-q::-webkit-details-marker { display: none; }
		.pztc-help__faq-item[open] .pztc-help__faq-q { background: linear-gradient(135deg, #1a1e23 0%, #2d3748 100%); color: #fff; }
		.pztc-help__faq-a { padding: 12px 16px; font-size: 13px; color: #555; }
		</style>
		<?php
	}
}
