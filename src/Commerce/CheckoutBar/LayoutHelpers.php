<?php
/**
 * Shared building blocks for checkout-bar layout partials.
 *
 * Layout files compose these pieces rather than duplicating the same
 * notes/qty/CTA markup six times. Each helper echoes output directly
 * and expects escaped values (it does not re-escape).
 *
 * @package PizzaTier\Commerce\CheckoutBar
 */

namespace PizzaTier\Commerce\CheckoutBar;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LayoutHelpers {

	/** Whether the quantity stepper should be shown. */
	public static function show_quantity(): bool {
		return class_exists( 'PizzaTier\Commerce\WooCommerce\\CartIntegration' )
			&& (bool) pizzatier_get_option( 'show_quantity_selector', true );
	}

	/** Max quantity allowed by the stepper. */
	public static function max_quantity(): int {
		return max( 1, (int) pizzatier_get_option( 'max_quantity', 99 ) );
	}

	/** Whether the order-notes textarea should be shown. */
	public static function show_notes(): bool {
		return (bool) pizzatier_get_option( 'enable_order_notes', false );
	}

	/** Placeholder text for the notes textarea. */
	public static function note_placeholder(): string {
		$ph = (string) pizzatier_get_option( 'order_note_placeholder', '' );
		return $ph !== '' ? $ph : __( 'Any special requests?', 'pizzatier' );
	}

	/**
	 * CTA button label. Honours the "cart_btn_text" setting when set,
	 * otherwise returns the translated "Add to Cart" default.
	 */
	public static function cta_label(): string {
		$t = (string) pizzatier_get_option( 'cart_btn_text', '' );
		return $t !== '' ? $t : __( 'Add to Cart', 'pizzatier' );
	}

	/**
	 * Render the optional order-notes textarea.
	 * Emits nothing when the feature is disabled.
	 */
	public static function render_notes( string $instance_id ): void {
		if ( ! self::show_notes() ) { return; }
		$ph = self::note_placeholder();
		?>
		<div class="pztc-bar-notes">
			<label class="pztc-bar-notes__label" for="pztc-note-<?php echo esc_attr( $instance_id ); ?>">
				<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 2h12v10H2z"/><path d="M5 7h6M5 9.5h4"/></svg>
				<?php esc_html_e( 'Special instructions', 'pizzatier' ); ?>
			</label>
			<textarea id="pztc-note-<?php echo esc_attr( $instance_id ); ?>"
			          class="pztc-bar-notes__input pztc-order-note-input"
			          data-instance="<?php echo esc_attr( $instance_id ); ?>"
			          rows="2" maxlength="500"
			          placeholder="<?php echo esc_attr( $ph ); ?>"></textarea>
		</div>
		<?php
	}

	/**
	 * Render the quantity stepper.
	 * Emits nothing when the feature is disabled.
	 */
	public static function render_qty( string $instance_id ): void {
		if ( ! self::show_quantity() ) { return; }
		$max = self::max_quantity();
		?>
		<div class="pztc-bar-qty" data-instance="<?php echo esc_attr( $instance_id ); ?>" data-max="<?php echo esc_attr( (string) $max ); ?>">
			<button type="button" class="pztc-qty-btn pztc-qty-btn--minus"
			        data-instance="<?php echo esc_attr( $instance_id ); ?>"
			        disabled
			        aria-label="<?php esc_attr_e( 'Decrease quantity', 'pizzatier' ); ?>">&minus;</button>
			<span class="pztc-qty-value" id="pztc-qty-<?php echo esc_attr( $instance_id ); ?>" data-qty="1">1</span>
			<button type="button" class="pztc-qty-btn pztc-qty-btn--plus"
			        data-instance="<?php echo esc_attr( $instance_id ); ?>"
			        aria-label="<?php esc_attr_e( 'Increase quantity', 'pizzatier' ); ?>">+</button>
		</div>
		<?php
	}

	/**
	 * Render the Add-to-Cart button.
	 * $extra_class lets a layout add decoration classes without re-templating.
	 */
	public static function render_cta( string $instance_id, string $extra_class = '' ): void {
		$cls = 'pztc-bar-row__btn pztc-add-to-cart-btn';
		if ( $extra_class ) { $cls .= ' ' . $extra_class; }
		?>
		<button type="button"
		        class="<?php echo esc_attr( $cls ); ?>"
		        id="pztc-checkout-btn-<?php echo esc_attr( $instance_id ); ?>"
		        data-instance="<?php echo esc_attr( $instance_id ); ?>"
		        aria-live="polite">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
			<span class="pztc-checkout-bar__btn-text"><?php echo esc_html( self::cta_label() ); ?></span>
		</button>
		<?php
	}

	/**
	 * Render the size-label + price summary block.
	 * All six layouts use the same IDs so updateCheckoutBar() can find them.
	 */
	public static function render_summary( string $instance_id ): void {
		?>
		<div class="pztc-bar-row__summary">
			<span class="pztc-bar-row__size-label" id="pztc-bar-size-<?php echo esc_attr( $instance_id ); ?>"></span>
			<span class="pztc-bar-row__price" id="pztc-bar-price-<?php echo esc_attr( $instance_id ); ?>">&mdash;</span>
		</div>
		<?php
	}

	/**
	 * Build the outer div's class attribute with palette + layout hooks.
	 *
	 * @param string $layout_slug   e.g. 'stacked-compact'
	 * @param string $template_slug active PizzaTier template (may be empty)
	 */
	public static function root_classes( string $layout_slug, string $template_slug ): string {
		$classes = [ 'pztc-checkout-bar', 'pztc-checkout-bar--' . sanitize_html_class( $layout_slug ) ];
		if ( $template_slug ) {
			$classes[] = 'pztc-checkout-bar--' . sanitize_html_class( $template_slug );
		}
		return implode( ' ', $classes );
	}
}
