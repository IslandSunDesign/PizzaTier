<?php
/**
 * Pizza product add-to-cart template.
 *
 * Replaces the default WooCommerce add-to-cart form for products of type "pizza".
 * The builder is injected above this point by FrontendEmbed (via WC hooks).
 * This template renders the final "Add to Cart" submission area beneath it.
 *
 * WooCommerce locates this file via the woocommerce_locate_template filter
 * registered in FrontendEmbed::register_frontend_hooks().
 *
 * @package PizzaTier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

// Resolve the builder instance index for this product page.
// FrontendEmbed increments a static counter each time render_builder_section() runs;
// the first (and normally only) instance is always idx=1. We pass this as data-instance
// so cart.js can find the right PizzaTierBuilderInstances entry.
$pizzatier_instance_idx = 1;

/*
 * The standard WooCommerce add-to-cart hooks. FrontendEmbed renders the
 * builder on woocommerce_before_add_to_cart_form for the default
 * "before cart" position; before 2.4.0 this template never fired it, so on
 * classic themes the builder did not appear at all in that position.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
do_action( 'woocommerce_before_add_to_cart_form' );

/*
 * Every builder template draws its own checkout bar (price, quantity and
 * Add to Cart), so this second button is only needed when the product has
 * no builder template (2.4.1: it showed as a duplicate under the builder).
 */
$pizzatier_has_template = '' !== (string) get_post_meta( $product->get_id(), '_pizzatier_builder_template', true );
/**
 * Whether to show the separate Add to Cart button below the pizza builder.
 *
 * @since 2.4.1
 *
 * @param bool       $show    Default: only when no builder template is set.
 * @param WC_Product $product Product.
 */
$pizzatier_show_button = (bool) apply_filters( 'pizzatier_show_product_add_to_cart_button', ! $pizzatier_has_template, $product );
if ( $pizzatier_show_button ) :
?>
<div class="pztc-add-to-cart-wrap" id="pztc-add-to-cart-wrap">

	<?php
	/**
	 * Fires inside the pizza add-to-cart wrapper before the button.
	 */
	do_action( 'pizzatier_commerce_before_add_to_cart_button', $product );
	?>

	<div class="pztc-atc-notice" id="pztc-atc-notice" role="alert" aria-live="polite" style="display:none;"></div>

	<button
		type="button"
		id="pztc-main-add-to-cart"
		class="pztc-btn pztc-btn--atc single_add_to_cart_button button alt pztc-add-to-cart-btn"
		data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
		data-instance="<?php echo esc_attr( $pizzatier_instance_idx ); ?>"
		data-pztc-instance="<?php echo esc_attr( $pizzatier_instance_idx ); ?>"
		aria-label="<?php echo esc_attr( sprintf(
			/* translators: %s: product name */
			__( 'Add %s to cart', 'pizzatier' ),
			$product->get_name()
		) ); ?>"
	>
		<svg class="pztc-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
			<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
		</svg>
		<span class="pztc-btn__label"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></span>
	</button>

	<?php do_action( 'pizzatier_commerce_after_add_to_cart_button', $product ); ?>

</div><!-- .pztc-add-to-cart-wrap -->
<?php
endif;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
do_action( 'woocommerce_after_add_to_cart_form' );
