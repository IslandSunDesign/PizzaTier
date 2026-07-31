<?php
/**
 * PizzaTier — Checkout Bar: Command Center
 *
 * Included by PizzaTier's CartIntegration via the
 * `pizzatier_builder_action_bar` hook. Lives inside the template folder so it
 * can be customised per-template. The Command Center build renders it in a
 * dedicated full-width dock at the bottom of the builder (see
 * pztp-containers-menu.php) so it is always present and prominent — it does NOT
 * depend on the optional order-summary sidebar.
 *
 * Available variables (provided by CartIntegration::render_cart_button()):
 *   $instance_id  (string) — the builder instance ID
 *
 * Pro JS hooks (kept identical to the shipped templates so the cart wiring works):
 *   #pztc-bar-size-{id}        — size label
 *   #pztc-bar-price-{id}       — price
 *   .pztc-bar-qty / .pztc-qty-btn / #pztc-qty-{id} — quantity stepper
 *   .pztc-order-note-input     — order note
 *   .pztc-add-to-cart-btn / #pztc-checkout-btn-{id}  — add-to-cart CTA
 *
 * All styling is in this template's template.css under
 * `.pztc-checkout-bar--commandcenter`.
 *
 * Override by copying this file to your child theme under:
 *   your-theme/pizzatier/checkout-bar.php
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

/* Command Center lets the site owner customise the Add to Cart label from the
 * template settings page (Templates → Command Center → Add to Cart Button Text). */
$cta_text = sanitize_text_field( (string) get_option( 'commandcenter_setting_cta_text', '' ) );
if ( '' === $cta_text ) { $cta_text = __( 'Add to Cart', 'pizzatier' ); }
?>
<div class="pztc-checkout-bar pztc-checkout-bar--commandcenter"
     id="pztc-checkout-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     role="region" aria-label="<?php esc_attr_e( 'Pizza order summary', 'pizzatier' ); ?>">

    <?php if ( $show_notes ) : ?>
    <div class="pztc-bar-notes">
        <label class="pztc-bar-notes__label" for="pztc-note-cc-<?php echo esc_attr( $instance_id ); ?>">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 2h12v10H2z"/><path d="M5 7h6M5 9.5h4"/></svg>
            <?php esc_html_e( 'Special instructions', 'pizzatier' ); ?>
        </label>
        <textarea id="pztc-note-cc-<?php echo esc_attr( $instance_id ); ?>"
                  class="pztc-bar-notes__input pztc-order-note-input"
                  data-instance="<?php echo esc_attr( $instance_id ); ?>"
                  rows="2" maxlength="500"
                  placeholder="<?php echo esc_attr( $note_ph ); ?>"></textarea>
    </div>
    <?php endif; ?>

    <div class="pztc-bar-row">
        <div class="pztc-bar-row__summary">
            <span class="pztc-bar-row__size-label" id="pztc-bar-size-<?php echo esc_attr( $instance_id ); ?>"></span>
            <span class="pztc-bar-row__price" id="pztc-bar-price-<?php echo esc_attr( $instance_id ); ?>">
                <span class="pztc-bar-row__currency"></span><span class="pztc-bar-row__amount">0.00</span>
            </span>
        </div>

        <?php if ( $show_qty ) : ?>
        <div class="pztc-bar-qty" data-instance="<?php echo esc_attr( $instance_id ); ?>" data-max="<?php echo esc_attr( $max_qty ); ?>">
            <button type="button" class="pztc-qty-btn pztc-qty-btn--minus"
                    data-instance="<?php echo esc_attr( $instance_id ); ?>"
                    disabled
                    aria-label="<?php esc_attr_e( 'Decrease quantity', 'pizzatier' ); ?>">&minus;</button>
            <span class="pztc-qty-value" id="pztc-qty-<?php echo esc_attr( $instance_id ); ?>" data-qty="1">1</span>
            <button type="button" class="pztc-qty-btn pztc-qty-btn--plus"
                    data-instance="<?php echo esc_attr( $instance_id ); ?>"
                    aria-label="<?php esc_attr_e( 'Increase quantity', 'pizzatier' ); ?>">+</button>
        </div>
        <?php endif; ?>

        <button type="button"
                class="pztc-bar-row__btn pztc-add-to-cart-btn"
                id="pztc-checkout-btn-<?php echo esc_attr( $instance_id ); ?>"
                data-instance="<?php echo esc_attr( $instance_id ); ?>"
                aria-live="polite">
            <svg class="pztc-bar-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="pztc-bar-row__btn-text"><?php echo esc_html( $cta_text ); ?></span>
        </button>
    </div>
</div><!-- .pztc-checkout-bar--commandcenter -->
