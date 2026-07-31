<?php
/**
 * Checkout Bar Layout: split-card
 *
 * Elevated card with a left-aligned price block and a right-aligned
 * cluster containing the quantity stepper and CTA. Subtle lift shadow.
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
<div class="<?php echo esc_attr( H::root_classes( 'split-card', $checkout_bar_template_slug ) ); ?>"
     id="pztc-checkout-bar-<?php echo esc_attr( $instance_id ); ?>"
     data-instance="<?php echo esc_attr( $instance_id ); ?>"
     data-layout="split-card">

	<?php H::render_notes( $instance_id ); ?>

	<div class="pztc-bar-row">
		<?php H::render_summary( $instance_id ); ?>
		<?php H::render_qty( $instance_id ); ?>
		<?php H::render_cta( $instance_id ); ?>
	</div>
</div>
