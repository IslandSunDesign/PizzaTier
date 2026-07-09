<?php
/**
 * PizzaTierPro — Checkout Bar: Plainlist
 * Minimal, text-first, borderless, respects user-set accent colour.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
if ( ! isset( $instance_id ) ) { $instance_id = ''; }
$show_qty   = class_exists('PizzaTierPro\Pro\WooCommerce\CartIntegration') && (bool) pztpro_get_setting('show_quantity_selector', true);
$max_qty    = max(1, (int) pztpro_get_setting('max_quantity', 99));
$show_notes = (bool) pztpro_get_setting('enable_order_notes', false);
$note_ph    = pztpro_get_setting('order_note_placeholder', '') ?: __('Any special requests?', 'pizzatier');

// CTA label: Plainlist template setting wins, then the Pro global cart-button
// text, then the default. Lets the Plainlist template own its own CTA wording.
$cta_label  = trim( (string) get_option( 'plainlist_setting_cart_btn_text', '' ) );
if ( '' === $cta_label && function_exists( 'pztpro_get_setting' ) ) {
    $cta_label = trim( (string) pztpro_get_setting( 'cart_btn_text', '' ) );
}
if ( '' === $cta_label ) {
    $cta_label = __( 'Add to Cart', 'pizzatier' );
}
?>
<div class="pztpro-checkout-bar pztpro-checkout-bar--plainlist"
     id="pztpro-checkout-bar-<?php echo esc_attr($instance_id); ?>"
     data-instance="<?php echo esc_attr($instance_id); ?>">

    <div class="pztpro-bar-row">
        <div class="pztpro-bar-row__summary">
            <span class="pztpro-bar-row__size-label" id="pztpro-bar-size-<?php echo esc_attr($instance_id); ?>"></span>
            <span class="pztpro-bar-row__price" id="pztpro-bar-price-<?php echo esc_attr($instance_id); ?>">—</span>
        </div>

        <?php if ($show_qty) : ?>
        <div class="pztpro-bar-qty" data-instance="<?php echo esc_attr($instance_id); ?>" data-max="<?php echo esc_attr($max_qty); ?>">
            <button type="button" class="pztpro-qty-btn pztpro-qty-btn--minus" data-instance="<?php echo esc_attr($instance_id); ?>" disabled aria-label="<?php esc_attr_e('Decrease quantity','pizzatier'); ?>">−</button>
            <span class="pztpro-qty-value" id="pztpro-qty-<?php echo esc_attr($instance_id); ?>" data-qty="1">1</span>
            <button type="button" class="pztpro-qty-btn pztpro-qty-btn--plus"  data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="<?php esc_attr_e('Increase quantity','pizzatier'); ?>">+</button>
        </div>
        <?php endif; ?>

        <button type="button"
                class="pztpro-bar-row__btn pztpro-add-to-cart-btn"
                id="pztpro-checkout-btn-<?php echo esc_attr($instance_id); ?>"
                data-instance="<?php echo esc_attr($instance_id); ?>"
                aria-live="polite">
            <?php echo esc_html( $cta_label ); ?>
        </button>
    </div>

    <?php if ($show_notes) : ?>
    <div class="pztpro-bar-notes" style="margin-top:10px;">
        <textarea class="pztpro-bar-notes__input pztpro-order-note-input"
                  data-instance="<?php echo esc_attr($instance_id); ?>"
                  rows="2" maxlength="500"
                  placeholder="<?php echo esc_attr($note_ph); ?>"></textarea>
    </div>
    <?php endif; ?>
</div>
