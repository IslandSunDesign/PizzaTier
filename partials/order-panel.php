<?php
/**
 * PizzaTier — Order Checkout Panel (default)
 *
 * Printed once per page in wp_footer by
 * PizzaTier\Orders\OrderCheckout::render_panel(), so the dialog sits at the end
 * of the document rather than inside an overflow-constrained builder column.
 *
 * Override by copying this file to either:
 *   your-theme/pizzatier/order-panel.php        (all templates)
 *   plugin/templates/{slug}/order-panel.php     (one template)
 *
 * Available variables:
 *   $template_slug (string)  Active template slug, also a modifier class.
 *   $methods       (array)   Enabled fulfilment methods, key => label.
 *   $sizes         (array)   Size options; empty when none are configured.
 *   $notes_on      (bool)    Whether the customer note field shows.
 *   $note_max      (int)     Maximum note length.
 *   $note_ph       (string)  Note placeholder text.
 *   $request_time  (bool)    Whether to ask for a requested time.
 *   $require_name  (bool)
 *   $require_phone (bool)
 *   $require_email (bool)
 *
 * Note: this is deliberately NOT a <form> element. Submission is handled by
 * pizzatier-orders.js over AJAX, and avoiding a form prevents a stray Enter
 * keypress from triggering a full page reload mid-order.
 *
 * @package PizzaTier\Orders
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included inside OrderCheckout::render_panel(), so these are method-local.

if ( ! isset( $template_slug ) ) { $template_slug = ''; }
if ( ! isset( $methods ) || ! is_array( $methods ) ) { $methods = [ 'pickup' => __( 'Pickup', 'pizzatier' ) ]; }
if ( ! isset( $sizes ) || ! is_array( $sizes ) ) { $sizes = []; }
if ( ! isset( $notes_on ) )      { $notes_on      = true; }
if ( ! isset( $note_max ) )      { $note_max      = 500; }
if ( ! isset( $note_ph ) )       { $note_ph       = __( 'Any special requests?', 'pizzatier' ); }
if ( ! isset( $request_time ) )  { $request_time  = true; }
if ( ! isset( $require_name ) )  { $require_name  = true; }
if ( ! isset( $require_phone ) ) { $require_phone = true; }
if ( ! isset( $require_email ) ) { $require_email = false; }

$pzt_req = '<span class="pzt-order-required" aria-hidden="true">*</span>';
?>
<div class="pzt-order-panel pzt-order-panel--<?php echo esc_attr( $template_slug ); ?>"
     id="pzt-order-panel"
     hidden>

	<div class="pzt-order-panel__backdrop" data-pzt-order-close="1"></div>

	<div class="pzt-order-panel__dialog"
	     role="dialog"
	     aria-modal="true"
	     aria-labelledby="pzt-order-panel-title">

		<div class="pzt-order-panel__header">
			<h2 class="pzt-order-panel__title" id="pzt-order-panel-title">
				<?php esc_html_e( 'Place your order', 'pizzatier' ); ?>
			</h2>
			<button type="button"
			        class="pzt-order-panel__close"
			        data-pzt-order-close="1"
			        aria-label="<?php esc_attr_e( 'Close', 'pizzatier' ); ?>">&times;</button>
		</div>

		<div class="pzt-order-panel__body">

			<!-- Step 1: review the pizza -->
			<section class="pzt-order-section pzt-order-section--review">
				<h3 class="pzt-order-section__title"><?php esc_html_e( 'Your pizza', 'pizzatier' ); ?></h3>
				<div class="pzt-order-review" id="pzt-order-review" aria-live="polite"></div>
			</section>

			<?php if ( ! empty( $sizes ) ) : ?>
			<!-- Step 2: size -->
			<section class="pzt-order-section">
				<h3 class="pzt-order-section__title"><?php esc_html_e( 'Size', 'pizzatier' ); ?></h3>
				<div class="pzt-order-sizes" role="radiogroup" aria-label="<?php esc_attr_e( 'Pizza size', 'pizzatier' ); ?>">
					<?php foreach ( $sizes as $pzt_i => $pzt_size ) : ?>
					<button type="button"
					        class="pzt-order-size<?php echo 0 === $pzt_i ? ' is-selected' : ''; ?>"
					        role="radio"
					        aria-checked="<?php echo 0 === $pzt_i ? 'true' : 'false'; ?>"
					        data-pzt-size-slug="<?php echo esc_attr( $pzt_size['slug'] ); ?>"
					        data-pzt-size-label="<?php echo esc_attr( $pzt_size['label'] ); ?>">
						<span class="pzt-order-size__label"><?php echo esc_html( $pzt_size['label'] ); ?></span>
						<?php if ( $pzt_size['diameter'] > 0 ) : ?>
						<span class="pzt-order-size__meta">
							<?php
							printf(
								/* translators: %s: pizza diameter in inches. */
								esc_html__( '%s"', 'pizzatier' ),
								esc_html( (string) $pzt_size['diameter'] )
							);
							?>
						</span>
						<?php endif; ?>
					</button>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<!-- Step 3: how to get it -->
			<section class="pzt-order-section">
				<h3 class="pzt-order-section__title"><?php esc_html_e( 'How would you like it?', 'pizzatier' ); ?></h3>
				<div class="pzt-order-methods" role="radiogroup" aria-label="<?php esc_attr_e( 'Fulfilment method', 'pizzatier' ); ?>">
					<?php $pzt_first = true; ?>
					<?php foreach ( $methods as $pzt_key => $pzt_label ) : ?>
					<button type="button"
					        class="pzt-order-method<?php echo $pzt_first ? ' is-selected' : ''; ?>"
					        role="radio"
					        aria-checked="<?php echo $pzt_first ? 'true' : 'false'; ?>"
					        data-pzt-method="<?php echo esc_attr( $pzt_key ); ?>">
						<?php echo esc_html( $pzt_label ); ?>
					</button>
					<?php $pzt_first = false; ?>
					<?php endforeach; ?>
				</div>

				<!-- Delivery address — shown only for the delivery method -->
				<div class="pzt-order-fields pzt-order-fields--address" data-pzt-show-for="delivery" hidden>
					<label class="pzt-order-field">
						<span class="pzt-order-field__label">
							<?php esc_html_e( 'Street address', 'pizzatier' ); ?>
							<?php echo wp_kses_post( $pzt_req ); ?>
						</span>
						<input type="text" class="pzt-order-input" data-pzt-field="address_line1" autocomplete="address-line1">
						<span class="pzt-order-field__error" data-pzt-error-for="address_line1"></span>
					</label>
					<label class="pzt-order-field">
						<span class="pzt-order-field__label"><?php esc_html_e( 'Apartment, suite, etc.', 'pizzatier' ); ?></span>
						<input type="text" class="pzt-order-input" data-pzt-field="address_line2" autocomplete="address-line2">
					</label>
					<div class="pzt-order-field-row">
						<label class="pzt-order-field">
							<span class="pzt-order-field__label"><?php esc_html_e( 'City', 'pizzatier' ); ?></span>
							<input type="text" class="pzt-order-input" data-pzt-field="address_city" autocomplete="address-level2">
						</label>
						<label class="pzt-order-field">
							<span class="pzt-order-field__label"><?php esc_html_e( 'State', 'pizzatier' ); ?></span>
							<input type="text" class="pzt-order-input" data-pzt-field="address_state" autocomplete="address-level1">
						</label>
						<label class="pzt-order-field">
							<span class="pzt-order-field__label"><?php esc_html_e( 'ZIP', 'pizzatier' ); ?></span>
							<input type="text" class="pzt-order-input" data-pzt-field="address_postcode" autocomplete="postal-code">
						</label>
					</div>
					<label class="pzt-order-field">
						<span class="pzt-order-field__label"><?php esc_html_e( 'Delivery instructions', 'pizzatier' ); ?></span>
						<input type="text" class="pzt-order-input" data-pzt-field="delivery_instructions">
					</label>
				</div>

				<!-- Table number — shown only for dine-in -->
				<div class="pzt-order-fields pzt-order-fields--table" data-pzt-show-for="dine_in" hidden>
					<label class="pzt-order-field">
						<span class="pzt-order-field__label"><?php esc_html_e( 'Table number', 'pizzatier' ); ?></span>
						<input type="text" class="pzt-order-input" data-pzt-field="table_number">
					</label>
				</div>

				<?php if ( $request_time ) : ?>
				<label class="pzt-order-field">
					<span class="pzt-order-field__label"><?php esc_html_e( 'When do you need it?', 'pizzatier' ); ?></span>
					<input type="text"
					       class="pzt-order-input"
					       data-pzt-field="requested_time"
					       placeholder="<?php esc_attr_e( 'As soon as possible', 'pizzatier' ); ?>">
				</label>
				<?php endif; ?>
			</section>

			<!-- Step 4: contact details -->
			<section class="pzt-order-section">
				<h3 class="pzt-order-section__title"><?php esc_html_e( 'Your details', 'pizzatier' ); ?></h3>

				<label class="pzt-order-field">
					<span class="pzt-order-field__label">
						<?php esc_html_e( 'Name', 'pizzatier' ); ?>
						<?php echo $require_name ? wp_kses_post( $pzt_req ) : ''; ?>
					</span>
					<input type="text" class="pzt-order-input" data-pzt-field="customer_name" autocomplete="name">
					<span class="pzt-order-field__error" data-pzt-error-for="customer_name"></span>
				</label>

				<div class="pzt-order-field-row">
					<label class="pzt-order-field">
						<span class="pzt-order-field__label">
							<?php esc_html_e( 'Phone', 'pizzatier' ); ?>
							<?php echo $require_phone ? wp_kses_post( $pzt_req ) : ''; ?>
						</span>
						<input type="tel" class="pzt-order-input" data-pzt-field="customer_phone" autocomplete="tel">
						<span class="pzt-order-field__error" data-pzt-error-for="customer_phone"></span>
					</label>

					<label class="pzt-order-field">
						<span class="pzt-order-field__label">
							<?php esc_html_e( 'Email', 'pizzatier' ); ?>
							<?php echo $require_email ? wp_kses_post( $pzt_req ) : ''; ?>
						</span>
						<input type="email" class="pzt-order-input" data-pzt-field="customer_email" autocomplete="email">
						<span class="pzt-order-field__error" data-pzt-error-for="customer_email"></span>
					</label>
				</div>
			</section>

			<?php if ( $notes_on ) : ?>
			<!-- Step 5: notes and specs -->
			<section class="pzt-order-section">
				<h3 class="pzt-order-section__title"><?php esc_html_e( 'Notes for the kitchen', 'pizzatier' ); ?></h3>
				<label class="pzt-order-field">
					<span class="pzt-order-field__label pzt-order-field__label--sr">
						<?php esc_html_e( 'Order notes', 'pizzatier' ); ?>
					</span>
					<textarea class="pzt-order-input pzt-order-input--textarea"
					          data-pzt-field="customer_note"
					          rows="3"
					          maxlength="<?php echo esc_attr( (string) $note_max ); ?>"
					          placeholder="<?php echo esc_attr( $note_ph ); ?>"></textarea>
					<span class="pzt-order-field__hint" data-pzt-counter="customer_note"></span>
				</label>
			</section>
			<?php endif; ?>

			<!--
				Honeypot. Hidden from sight and from assistive technology, but
				left in the DOM: a scripted submission fills it, a human never
				sees it. Styling lives in the stylesheet, not inline, so the
				markup stays WordPress.org compliant.
			-->
			<div class="pzt-order-trap" aria-hidden="true">
				<label>
					<?php esc_html_e( 'Website', 'pizzatier' ); ?>
					<input type="text" data-pzt-field="pzt_website" tabindex="-1" autocomplete="off">
				</label>
			</div>

			<div class="pzt-order-panel__error" id="pzt-order-error" role="alert" hidden></div>

		</div><!-- /.pzt-order-panel__body -->

		<div class="pzt-order-panel__footer">
			<button type="button" class="pzt-order-cancel" data-pzt-order-close="1">
				<?php esc_html_e( 'Keep building', 'pizzatier' ); ?>
			</button>
			<button type="button" class="pzt-order-submit" id="pzt-order-submit">
				<?php esc_html_e( 'Place Order', 'pizzatier' ); ?>
			</button>
		</div>

		<!-- Confirmation, swapped in after a successful submission -->
		<div class="pzt-order-panel__done" id="pzt-order-done" hidden>
			<div class="pzt-order-done__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
				     stroke-linecap="round" stroke-linejoin="round" focusable="false">
					<circle cx="12" cy="12" r="10"/><polyline points="8 12.5 11 15.5 16 9.5"/>
				</svg>
			</div>
			<p class="pzt-order-done__message" id="pzt-order-done-message"></p>
			<p class="pzt-order-done__number" id="pzt-order-done-number"></p>
			<button type="button" class="pzt-order-done__btn" data-pzt-order-close="1">
				<?php esc_html_e( 'Done', 'pizzatier' ); ?>
			</button>
		</div>

	</div><!-- /.pzt-order-panel__dialog -->
</div><!-- /.pzt-order-panel -->
