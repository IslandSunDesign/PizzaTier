<?php
/**
 * PizzaTierPro — Checkout Bar: Command Center
 *
 * Included by PizzaTierPro's CartIntegration via the
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
 *   #pztpro-bar-size-{id}        — size label
 *   #pztpro-bar-price-{id}       — price
 *   .pztpro-bar-qty / .pztpro-qty-btn / #pztpro-qty-{id} — quantity stepper
 *   .pztpro-order-note-input     — order note
 *   .pztpro-add-to-cart-btn / #pztpro-checkout-btn-{id}  — add-to-cart CTA
 *
 * All styling is in this template's template.css under
 * `.pztpro-checkout-bar--commandcenter`.
 *
 * Override by copying this file to your child theme under:
 *   your-theme/pizzatierpro/checkout-bar.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
if ( ! isset( $instance_id ) ) { $instance_id = ''; }

/* Robust setting access — Pro provides pztpro_get_setting(); guard so the bar
 * can never fatal if it is rendered in a context where Pro helpers are absent. */
$pzt_can_setting = function_exists( 'pztpro_get_setting' );
$show_qty   = $pzt_can_setting ? (bool) pztpro_get_setting( 'show_quantity_selector', true ) : true;
$max_qty    = max( 1, $pzt_can_setting ? (int) pztpro_get_setting( 'max_quantity', 99 ) : 99 );
$show_notes = $pzt_can_setting ? (bool) pztpro_get_setting( 'enable_order_notes', false ) : false;
$note_ph    = ( $pzt_can_setting ? (string) pztpro_get_setting( 'order_note_placeholder', '' ) : '' );
if ( '' === $note_ph ) { $note_ph = __( 'Any special requests?', 'pizzatier' ); }

/* Command Center lets the site owner customise the Add to Cart label from the
 * template settings page (Templates → Command Center → Add to Cart Button Text). */
$cta_text = sanitize_text_field( (string) get_option( 'commandcenter_setting_cta_text', '' ) );
if ( '' === $cta_text ) { $cta_text = __( 'Add to Cart', 'pizzatier' ); }
?>
<div class="pztpro-checkout-bar pztpro-checkout-bar--commandcenter"
     id="pztpro-checkout-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     role="region" aria-label="<?php esc_attr_e( 'Pizza order summary', 'pizzatier' ); ?>">

    <?php if ( $show_notes ) : ?>
    <div class="pztpro-bar-notes">
        <label class="pztpro-bar-notes__label" for="pztpro-note-cc-<?php echo esc_attr( $instance_id ); ?>">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 2h12v10H2z"/><path d="M5 7h6M5 9.5h4"/></svg>
            <?php esc_html_e( 'Special instructions', 'pizzatier' ); ?>
        </label>
        <textarea id="pztpro-note-cc-<?php echo esc_attr( $instance_id ); ?>"
                  class="pztpro-bar-notes__input pztpro-order-note-input"
                  data-instance="<?php echo esc_attr( $instance_id ); ?>"
                  rows="2" maxlength="500"
                  placeholder="<?php echo esc_attr( $note_ph ); ?>"></textarea>
    </div>
    <?php endif; ?>

    <div class="pztpro-bar-row">
        <div class="pztpro-bar-row__summary">
            <span class="pztpro-bar-row__size-label" id="pztpro-bar-size-<?php echo esc_attr( $instance_id ); ?>"></span>
            <span class="pztpro-bar-row__price" id="pztpro-bar-price-<?php echo esc_attr( $instance_id ); ?>">
                <span class="pztpro-bar-row__currency"></span><span class="pztpro-bar-row__amount">0.00</span>
            </span>
        </div>

        <?php if ( $show_qty ) : ?>
        <div class="pztpro-bar-qty" data-instance="<?php echo esc_attr( $instance_id ); ?>" data-max="<?php echo esc_attr( $max_qty ); ?>">
            <button type="button" class="pztpro-qty-btn pztpro-qty-btn--minus"
                    data-instance="<?php echo esc_attr( $instance_id ); ?>"
                    disabled
                    aria-label="<?php esc_attr_e( 'Decrease quantity', 'pizzatier' ); ?>">&minus;</button>
            <span class="pztpro-qty-value" id="pztpro-qty-<?php echo esc_attr( $instance_id ); ?>" data-qty="1">1</span>
            <button type="button" class="pztpro-qty-btn pztpro-qty-btn--plus"
                    data-instance="<?php echo esc_attr( $instance_id ); ?>"
                    aria-label="<?php esc_attr_e( 'Increase quantity', 'pizzatier' ); ?>">+</button>
        </div>
        <?php endif; ?>

        <button type="button"
                class="pztpro-bar-row__btn pztpro-add-to-cart-btn"
                id="pztpro-checkout-btn-<?php echo esc_attr( $instance_id ); ?>"
                data-instance="<?php echo esc_attr( $instance_id ); ?>"
                aria-live="polite">
            <svg class="pztpro-bar-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span class="pztpro-bar-row__btn-text"><?php echo esc_html( $cta_text ); ?></span>
        </button>
    </div>
</div><!-- .pztpro-checkout-bar--commandcenter -->
