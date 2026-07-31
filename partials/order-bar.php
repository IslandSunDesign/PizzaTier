<?php
/**
 * PizzaTier — Order Bar (default)
 *
 * Rendered into the builder action-bar area by
 * PizzaTier\Orders\OrderCheckout::render_bar(), which is hooked to the
 * `pizzatier_builder_action_bar` action that every bundled template fires.
 *
 * Override by copying this file to either:
 *   your-theme/pizzatier/order-bar.php          (all templates)
 *   plugin/templates/{slug}/order-bar.php       (one template)
 *
 * Available variables:
 *   $instance_id   (string) Builder instance ID.
 *   $template_slug (string) Active template slug — also emitted as a modifier
 *                           class so each template's stylesheet can theme the bar.
 *   $button_label  (string) Call-to-action label.
 *   $can_order     (bool)   Whether this visitor is allowed to order.
 *   $quantity_on   (bool)   Whether to show the quantity stepper.
 *   $max_quantity  (int)    Upper bound for the stepper.
 *
 * All styling lives in assets/css/pizzatier-orders.css under
 * `.pzt-order-bar` and `.pzt-order-bar--{template}`.
 *
 * @package PizzaTier\Orders
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included inside OrderCheckout::render_bar(), so these are method-local.

if ( ! isset( $instance_id ) )   { $instance_id   = ''; }
if ( ! isset( $template_slug ) ) { $template_slug = ''; }
if ( ! isset( $button_label ) )  { $button_label  = __( 'Order Now', 'pizzatier' ); }
if ( ! isset( $can_order ) )     { $can_order     = true; }
if ( ! isset( $quantity_on ) )   { $quantity_on   = true; }
if ( ! isset( $max_quantity ) )  { $max_quantity  = 20; }
?>
<div class="pzt-order-bar pzt-order-bar--<?php echo esc_attr( $template_slug ); ?>"
     id="pzt-order-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-template="<?php echo esc_attr( $template_slug ); ?>"
     role="region"
     aria-label="<?php esc_attr_e( 'Pizza order summary', 'pizzatier' ); ?>">

	<div class="pzt-order-bar__summary">
		<span class="pzt-order-bar__title"><?php esc_html_e( 'Your pizza', 'pizzatier' ); ?></span>
		<span class="pzt-order-bar__detail"
		      id="pzt-order-summary-<?php echo esc_attr( $instance_id ); ?>"
		      data-instance="<?php echo esc_attr( $instance_id ); ?>"
		      aria-live="polite"><?php esc_html_e( 'Nothing selected yet', 'pizzatier' ); ?></span>
	</div>

	<?php if ( $quantity_on ) : ?>
	<div class="pzt-order-bar__qty"
	     data-instance="<?php echo esc_attr( $instance_id ); ?>"
	     data-max="<?php echo esc_attr( (string) $max_quantity ); ?>">
		<button type="button"
		        class="pzt-order-qty-btn pzt-order-qty-btn--minus"
		        data-instance="<?php echo esc_attr( $instance_id ); ?>"
		        data-step="-1"
		        disabled
		        aria-label="<?php esc_attr_e( 'Decrease quantity', 'pizzatier' ); ?>">&minus;</button>
		<span class="pzt-order-qty-value"
		      id="pzt-order-qty-<?php echo esc_attr( $instance_id ); ?>"
		      data-qty="1">1</span>
		<button type="button"
		        class="pzt-order-qty-btn pzt-order-qty-btn--plus"
		        data-instance="<?php echo esc_attr( $instance_id ); ?>"
		        data-step="1"
		        aria-label="<?php esc_attr_e( 'Increase quantity', 'pizzatier' ); ?>">+</button>
	</div>
	<?php endif; ?>

	<?php if ( $can_order ) : ?>
	<button type="button"
	        class="pzt-order-bar__btn pzt-order-open-btn"
	        id="pzt-order-btn-<?php echo esc_attr( $instance_id ); ?>"
	        data-instance="<?php echo esc_attr( $instance_id ); ?>">
		<svg class="pzt-order-bar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
		     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<path d="M4 4h2l2.4 12.1a2 2 0 0 0 2 1.6h8.7a2 2 0 0 0 2-1.6L23 7H7"/>
			<circle cx="10" cy="21" r="1"/><circle cx="19" cy="21" r="1"/>
		</svg>
		<span class="pzt-order-bar__btn-text"><?php echo esc_html( $button_label ); ?></span>
	</button>
	<?php else : ?>
	<p class="pzt-order-bar__notice">
		<?php esc_html_e( 'Please log in to place an order.', 'pizzatier' ); ?>
	</p>
	<?php endif; ?>

</div><!-- /.pzt-order-bar -->
