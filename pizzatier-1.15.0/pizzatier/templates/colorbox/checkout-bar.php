<?php
/**
 * PizzaTierPro — Checkout Bar: Colorbox
 *
 * Included by PizzaTierPro's CartIntegration when a pizza product page is
 * loaded. Lives inside the template folder so it can be customised per-template.
 *
 * Bright, rounded "order app" bar matching the Colorbox builder. All styling is
 * in this template's template.css under `.pztpro-checkout-bar--colorbox`.
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
 * Override by copying this file to your child theme under:
 *   your-theme/pizzatierpro/checkout-bar.php
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial; this file is include'd inside a method (render_template / load_template_custom / inject_inline_styles / Pro CartIntegration::render_cart_button), so its top-level variables are method-local, not global.
if ( ! isset( $instance_id ) ) { $instance_id = ''; }

$pzt_can_setting = function_exists( 'pztpro_get_setting' );
$show_qty   = $pzt_can_setting ? (bool) pztpro_get_setting( 'show_quantity_selector', true ) : true;
$max_qty    = max( 1, $pzt_can_setting ? (int) pztpro_get_setting( 'max_quantity', 99 ) : 99 );
$show_notes = $pzt_can_setting ? (bool) pztpro_get_setting( 'enable_order_notes', false ) : false;
$note_ph    = ( $pzt_can_setting ? (string) pztpro_get_setting( 'order_note_placeholder', '' ) : '' );
if ( $note_ph === '' ) { $note_ph = __( 'Any special requests?', 'pizzatier' ); }
?>
<div class="pztpro-checkout-bar pztpro-checkout-bar--colorbox"
     id="pztpro-checkout-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     role="region" aria-label="<?php esc_attr_e( 'Pizza order summary', 'pizzatier' ); ?>">

	<div class="pztpro-bar-row">
		<div class="pztpro-bar-row__summary">
			<span class="pztpro-bar-row__size-label" id="pztpro-bar-size-<?php echo esc_attr( $instance_id ); ?>"></span>
			<span class="pztpro-bar-row__price" id="pztpro-bar-price-<?php echo esc_attr( $instance_id ); ?>">
				<span class="pztpro-bar-row__currency"></span><span class="pztpro-bar-row__amount">0.00</span>
			</span>
		</div>

		<?php if ( $show_qty ) : ?>
		<div class="pztpro-bar-qty" data-instance="<?php echo esc_attr( $instance_id ); ?>" data-max="<?php echo esc_attr( $max_qty ); ?>">
			<button type="button" class="pztpro-qty-btn pztpro-qty-btn--minus" data-instance="<?php echo esc_attr( $instance_id ); ?>" disabled aria-label="<?php esc_attr_e( 'Decrease quantity', 'pizzatier' ); ?>">&minus;</button>
			<span class="pztpro-qty-value" id="pztpro-qty-<?php echo esc_attr( $instance_id ); ?>" data-qty="1">1</span>
			<button type="button" class="pztpro-qty-btn pztpro-qty-btn--plus" data-instance="<?php echo esc_attr( $instance_id ); ?>" aria-label="<?php esc_attr_e( 'Increase quantity', 'pizzatier' ); ?>">+</button>
		</div>
		<?php endif; ?>

		<button type="button"
		        class="pztpro-bar-row__btn pztpro-add-to-cart-btn"
		        id="pztpro-checkout-btn-<?php echo esc_attr( $instance_id ); ?>"
		        data-instance="<?php echo esc_attr( $instance_id ); ?>"
		        aria-live="polite">
			<svg class="pztpro-bar-row__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
				<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
			</svg>
			<span class="pztpro-bar-row__btn-text"><?php esc_html_e( 'Add to Cart', 'pizzatier' ); ?></span>
		</button>
	</div>

	<?php if ( $show_notes ) : ?>
	<div class="pztpro-bar-notes">
		<textarea class="pztpro-bar-notes__input pztpro-order-note-input"
		          data-instance="<?php echo esc_attr( $instance_id ); ?>"
		          rows="2" maxlength="500"
		          placeholder="<?php echo esc_attr( $note_ph ); ?>"></textarea>
	</div>
	<?php endif; ?>

</div><!-- .pztpro-checkout-bar--colorbox -->
