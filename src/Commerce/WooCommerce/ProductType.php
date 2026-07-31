<?php
/**
 * Registers the 'pizza' WooCommerce product type.
 *
 * Uses bracketed namespace syntax so WC_Product_Pizza can live in the global
 * namespace — required for WooCommerce to resolve it by string name.
 *
 * @package PizzaTier\Commerce\WooCommerce
 */

// ============================================================================
// Namespaced section — ProductType bootstrap class
// ============================================================================
namespace PizzaTier\Commerce\WooCommerce {

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	class ProductType {

		public function register(): void {
			add_action( 'init', [ $this, 'ensure_product_type_term' ], 5 );
			add_filter( 'product_type_selector',          [ $this, 'add_pizza_product_type' ] );
			add_filter( 'woocommerce_product_class',      [ $this, 'map_product_class' ], 10, 2 );
			// Priority 5 — save the term before ProductTab::save_meta at 10.
			add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_type_term' ], 5 );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_product_type_script' ] );

			// Hook the add-to-cart template output for pizza products.
			// WC dispatches do_action('woocommerce_pizza_add_to_cart') on single
			// product pages; without a listener nothing renders.
			add_action( 'woocommerce_pizza_add_to_cart', [ $this, 'output_add_to_cart_template' ] );
		}

		// -----------------------------------------------------------------------
		// Taxonomy term management
		// -----------------------------------------------------------------------

		/**
		 * Ensure the 'pizza' term exists in the product_type taxonomy.
		 * WooCommerce reads the type FROM this taxonomy on every load;
		 * without the term it silently falls back to 'simple'.
		 */
		public function ensure_product_type_term(): void {
			if ( ! taxonomy_exists( 'product_type' ) ) {
				return;
			}
			if ( ! get_term_by( 'slug', 'pizza', 'product_type' ) ) {
				wp_insert_term( 'Pizza', 'product_type', [ 'slug' => 'pizza' ] );
			}
		}

		/**
		 * Assign the 'pizza' product_type taxonomy term to the product on save.
		 * This is what makes the type persist across page reloads.
		 *
		 * @param int $post_id
		 */
		public function save_product_type_term( int $post_id ): void {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC verifies nonce
			if ( empty( $_POST['product-type'] ) || 'pizza' !== sanitize_key( $_POST['product-type'] ) ) {
				return;
			}
			wp_set_object_terms( $post_id, 'pizza', 'product_type' );
		}

		// -----------------------------------------------------------------------
		// Filters
		// -----------------------------------------------------------------------

		/** @param array<string,string> $types */
		public function add_pizza_product_type( array $types ): array {
			$types['pizza'] = __( 'Pizza', 'pizzatier' );
			return $types;
		}

		public function map_product_class( string $classname, string $product_type ): string {
			return 'pizza' === $product_type ? 'WC_Product_Pizza' : $classname;
		}

		/**
		 * Output the add-to-cart template for pizza products.
		 *
		 * WooCommerce fires do_action('woocommerce_pizza_add_to_cart') on
		 * single product pages when the product type is 'pizza'.  Without a
		 * listener the entire add-to-cart area is blank.  We load our custom
		 * template (woocommerce/single-product/add-to-cart/pizza.php) here,
		 * the same way WC loads simple.php for simple products.
		 */
		public function output_add_to_cart_template(): void {
			wc_get_template( 'single-product/add-to-cart/pizza.php' );
		}

		// -----------------------------------------------------------------------
		// Admin JS — panel & tab visibility
		// -----------------------------------------------------------------------

		/**
		 * Enqueue the product-type JS that keeps General pricing and the
		 * Pizza Configurator tab visible whenever 'pizza' is selected.
		 *
		 * Only loads on the product edit screen.
		 */
		public function enqueue_product_type_script( string $hook ): void {
			if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
				return;
			}
			$screen = get_current_screen();
			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}

			wp_enqueue_script(
				'pizzatier-commerce-product-type',
				PIZZATIER_PLUGIN_URL . 'assets/js/admin-product-type.js',
				[ 'jquery', 'wc-admin-meta-boxes' ],
				PIZZATIER_VERSION,
				true
			);
		}
	}

} // end namespace PizzaTier\Commerce\WooCommerce


// ============================================================================
// Global namespace section — WC_Product_Pizza
//
// WooCommerce resolves product classes by bare string name ('WC_Product_Pizza')
// with no namespace prefix. The bracketed `namespace {}` block below places
// this class in the true global namespace so WC can find it.
// ============================================================================
namespace {

	if ( ! class_exists( 'WC_Product_Pizza', false ) ) :

	/**
	 * Custom WooCommerce product type for configurable pizza products.
	 *
	 * The class name is dictated by WooCommerce: WC_Product_Factory resolves
	 * product classes as 'WC_Product_' . ucfirst( $product_type ), so this
	 * cannot carry a plugin prefix without breaking product type resolution.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Name is required verbatim by WC_Product_Factory; see docblock above.
	class WC_Product_Pizza extends WC_Product {

		/** @var string */
		protected $product_type = 'pizza';

		public function get_type(): string {
			return 'pizza';
		}

		/** @param string $feature */
		public function supports( $feature ): bool {
			// Explicitly support everything WC checks before rendering the
			// single-product add-to-cart form and template.
			$supported = [
				'ajax_add_to_cart',
				'prices_include_tax',
				'add_to_cart_form',   // required for WC to render the ATC form
				'purchasable',
			];
			return in_array( $feature, $supported, true )
				|| parent::supports( $feature );
		}

		public function is_purchasable(): bool {
			return 'publish' === $this->get_status() && $this->get_id() > 0;
		}

		public function is_in_stock(): bool {
			return true;
		}

		public function get_pizza_builder_template(): string {
			return (string) $this->get_meta( '_pizzatier_builder_template', true );
		}

		public function get_pizza_enabled_layers(): array {
			$layers = $this->get_meta( '_pizzatier_enabled_layers', true );
			return is_array( $layers ) ? array_map( 'intval', $layers ) : [];
		}

		public function get_pizza_price_grid(): ?array {
			$grid = $this->get_meta( '_pizzatier_price_grid', true );
			return is_array( $grid ) ? $grid : null;
		}
	}

	endif; // class_exists WC_Product_Pizza

} // end namespace (global)
