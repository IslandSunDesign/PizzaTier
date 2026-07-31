/**
 * PizzaTier — Checkout Bar UI
 *
 * Handles the quantity stepper (+/− buttons) inside each template's
 * checkout bar. Works alongside cart.js which reads the quantity and
 * note values when the Add to Cart button is clicked.
 *
 * Quantity state is stored in a data attribute on the display element
 * so cart.js can read it without knowing about this module.
 *
 * Selector conventions (DOM):
 *   .pztc-qty-btn--minus [data-instance]  — decrement
 *   .pztc-qty-btn--plus  [data-instance]  — increment
 *   .pztc-qty-value      [id="pztc-qty-{instance}"]  — display + storage
 *   .pztc-order-note-input [data-instance]  — note textarea (read by cart.js)
 *
 * Min: 1  |  Max: configurable via data-max on the qty group, default 99.
 */

( function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Init                                                                 */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'DOMContentLoaded', function () {
		initQtySteppers();
		observeDynamicBars();
		initStickyBars();
	} );

	/* ------------------------------------------------------------------ */
	/* Sticky-bottom bars                                                   */
	/*                                                                      */
	/* The sticky-bottom layout starts off-screen (transform:              */
	/* translateY(100%)) and slides up when .pztc-bar--visible is added. */
	/* We reveal as soon as frontend-builder.js fires its update event     */
	/* with a resolved price (i.e. the customer has picked a valid size).  */
	/* ------------------------------------------------------------------ */

	function initStickyBars() {
		document.addEventListener( 'pizzatier_commerce:checkoutbar:update', function ( e ) {
			var detail = e && e.detail;
			if ( ! detail ) { return; }

			// Reveal every sticky bar on the page — there's typically only one.
			// We reveal on any price update; once visible, it stays visible.
			var bars = document.querySelectorAll(
				'.pztc-checkout-bar--sticky-bottom'
			);
			for ( var i = 0; i < bars.length; i++ ) {
				bars[ i ].classList.add( 'pztc-bar--visible' );
			}
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Quantity steppers                                                    */
	/* ------------------------------------------------------------------ */

	function initQtySteppers() {
		// Delegated — bars may render after DOMContentLoaded (e.g. cached fragments).
		document.body.addEventListener( 'click', function ( e ) {
			var btn = e.target;
			if ( ! btn ) return;

			if ( btn.classList.contains( 'pztc-qty-btn--minus' ) ) {
				adjustQty( btn.dataset.instance, -1 );
			} else if ( btn.classList.contains( 'pztc-qty-btn--plus' ) ) {
				adjustQty( btn.dataset.instance, +1 );
			}
		} );
	}

	/**
	 * Adjust quantity for a given instance.
	 *
	 * @param {string} instanceId
	 * @param {number} delta  +1 or -1
	 */
	function adjustQty( instanceId, delta ) {
		if ( ! instanceId ) return;

		var display   = document.getElementById( 'pztc-qty-' + instanceId );
		if ( ! display ) return;

		var current = parseInt( display.textContent, 10 ) || 1;
		var max     = getMaxQty( instanceId );
		var next    = Math.max( 1, Math.min( max, current + delta ) );

		display.textContent       = next;
		display.dataset.qty       = next;   // cart.js reads this

		// Update minus-button disabled state.
		var btnMinus = document.querySelector(
			'.pztc-qty-btn--minus[data-instance="' + instanceId + '"]'
		);
		var btnPlus = document.querySelector(
			'.pztc-qty-btn--plus[data-instance="' + instanceId + '"]'
		);
		if ( btnMinus ) btnMinus.disabled = ( next <= 1 );
		if ( btnPlus  ) btnPlus.disabled  = ( next >= max );
	}

	/**
	 * Get the max quantity for an instance.
	 * Reads data-max from the .pztc-bar-qty group if set.
	 *
	 * @param {string} instanceId
	 * @returns {number}
	 */
	function getMaxQty( instanceId ) {
		var group = document.querySelector(
			'.pztc-bar-qty[data-instance="' + instanceId + '"]'
		);
		var max = group ? parseInt( group.dataset.max, 10 ) : NaN;
		return isNaN( max ) || max < 1 ? 99 : max;
	}

	/* ------------------------------------------------------------------ */
	/* MutationObserver — handle dynamically injected bars                 */
	/* ------------------------------------------------------------------ */

	function observeDynamicBars() {
		if ( ! window.MutationObserver ) return;
		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( m ) {
				m.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) return;
					if (
						node.classList.contains( 'pztc-checkout-bar' ) ||
						node.querySelector( '.pztc-checkout-bar' )
					) {
						// Reset minus buttons (qty starts at 1 = can't go lower).
						node.querySelectorAll( '.pztc-qty-btn--minus' ).forEach( function ( b ) {
							b.disabled = true;
						} );
					}
				} );
			} );
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

} )();
