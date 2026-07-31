<?php
/**
 * Checkout Bar Layout: sticky-bottom
 *
 * Fixed to the viewport bottom. Starts off-screen (transform: translateY(100%))
 * and slides up when the .pztc-bar--visible class is added — this is toggled
 * by frontend-builder.js as soon as a size is chosen or a price is computed.
 *
 * Because this bar is position:fixed it would otherwise overlap page content
 * at the bottom of long pages; we render a visually-hollow spacer sibling
 * to reserve room (does nothing on short pages where the bar sits over empty space).
 *
 * @package PizzaTier\Commerce\CheckoutBar\Layouts
 */

use PizzaTier\Commerce\CheckoutBar\LayoutHelpers as H;

if ( ! defined( 'ABSPATH' ) ) { exit; }
// These are not global variables. This partial is included from inside
// CartIntegration::render_cart_button(), so file scope here is that method's
// local scope. The names are a published contract shared with the per-template
// and child-theme checkout-bar.php overrides and must not be renamed.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! isset( $instance_id ) )                { $instance_id                = ''; }
if ( ! isset( $checkout_bar_template_slug ) ) { $checkout_bar_template_slug = ''; }
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="pztc-bar-sticky-spacer" aria-hidden="true"></div>

<div class="<?php echo esc_attr( H::root_classes( 'sticky-bottom', $checkout_bar_template_slug ) ); ?>"
     id="pztc-checkout-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-layout="sticky-bottom"
     role="region"
     aria-label="<?php esc_attr_e( 'Order summary', 'pizzatier' ); ?>">

	<?php H::render_notes( $instance_id ); ?>

	<div class="pztc-bar-row">
		<?php H::render_summary( $instance_id ); ?>
		<?php H::render_qty( $instance_id ); ?>
		<?php H::render_cta( $instance_id ); ?>
	</div>
</div>
