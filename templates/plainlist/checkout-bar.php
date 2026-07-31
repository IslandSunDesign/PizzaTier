<?php
/**
 * PizzaTier — Checkout Bar: Plainlist
 * Minimal, text-first, borderless, respects user-set accent colour.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
if ( ! isset( $instance_id ) ) { $instance_id = ''; }
// Settings come from an add-on when one is installed. pzt_addon_setting()
// returns the supplied default otherwise, so this file is safe to include on a
// site with no premium extension active.
$show_qty   = (bool) pzt_addon_setting( 'show_quantity_selector', true );
$max_qty    = max( 1, (int) pzt_addon_setting( 'max_quantity', 99 ) );
$show_notes = (bool) pzt_addon_setting( 'enable_order_notes', false );
$note_ph    = (string) pzt_addon_setting( 'order_note_placeholder', '' );
if ( '' === $note_ph ) { $note_ph = __( 'Any special requests?', 'pizzatier' ); }

// CTA label: Plainlist template setting wins, then the Pro global cart-button
// text, then the default. Lets the Plainlist template own its own CTA wording.
$cta_label  = trim( (string) get_option( 'plainlist_setting_cart_btn_text', '' ) );
if ( '' === $cta_label ) {
    $cta_label = trim( (string) pzt_addon_setting( 'cart_btn_text', '' ) );
}
if ( '' === $cta_label ) {
    $cta_label = __( 'Add to Cart', 'pizzatier' );
}
?>
<div class="pztc-checkout-bar pztc-checkout-bar--plainlist"
     id="pztc-checkout-bar-<?php echo esc_attr($instance_id); ?>"
     data-instance="<?php echo esc_attr($instance_id); ?>">

    <div class="pztc-bar-row">
        <div class="pztc-bar-row__summary">
            <span class="pztc-bar-row__size-label" id="pztc-bar-size-<?php echo esc_attr($instance_id); ?>"></span>
            <span class="pztc-bar-row__price" id="pztc-bar-price-<?php echo esc_attr($instance_id); ?>">—</span>
        </div>

        <?php if ($show_qty) : ?>
        <div class="pztc-bar-qty" data-instance="<?php echo esc_attr($instance_id); ?>" data-max="<?php echo esc_attr($max_qty); ?>">
            <button type="button" class="pztc-qty-btn pztc-qty-btn--minus" data-instance="<?php echo esc_attr($instance_id); ?>" disabled aria-label="<?php esc_attr_e('Decrease quantity','pizzatier'); ?>">−</button>
            <span class="pztc-qty-value" id="pztc-qty-<?php echo esc_attr($instance_id); ?>" data-qty="1">1</span>
            <button type="button" class="pztc-qty-btn pztc-qty-btn--plus"  data-instance="<?php echo esc_attr($instance_id); ?>" aria-label="<?php esc_attr_e('Increase quantity','pizzatier'); ?>">+</button>
        </div>
        <?php endif; ?>

        <button type="button"
                class="pztc-bar-row__btn pztc-add-to-cart-btn"
                id="pztc-checkout-btn-<?php echo esc_attr($instance_id); ?>"
                data-instance="<?php echo esc_attr($instance_id); ?>"
                aria-live="polite">
            <?php echo esc_html( $cta_label ); ?>
        </button>
    </div>

    <?php if ($show_notes) : ?>
    <div class="pztc-bar-notes" style="margin-top:10px;">
        <textarea class="pztc-bar-notes__input pztc-order-note-input"
                  data-instance="<?php echo esc_attr($instance_id); ?>"
                  rows="2" maxlength="500"
                  placeholder="<?php echo esc_attr($note_ph); ?>"></textarea>
    </div>
    <?php endif; ?>
</div>
