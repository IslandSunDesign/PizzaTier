/**
 * PizzaTier — block cart / checkout support (2.4.0).
 *
 * The classic cart filters never reach the WooCommerce Cart and Checkout
 * blocks, so the server exposes each pizza line's templated name and signed
 * "Edit pizza" URL as Store API extension data (namespace "pizzatier") and
 * this script applies them.
 */
( function () {
	'use strict';

	var bc = window.wc && window.wc.blocksCheckout;
	if ( ! bc || typeof bc.registerCheckoutFilters !== 'function' ) {
		return;
	}

	function data( ext ) {
		return ( ext && ext.pizzatier ) || null;
	}

	bc.registerCheckoutFilters( 'pizzatier', {
		itemName: function ( value, extensions ) {
			var d = data( extensions );
			return d && d.name ? d.name : value;
		},
		cartItemClass: function ( value, extensions, args ) {
			var d = data( extensions );
			if ( d && d.edit_url && args && args.cartItem && args.cartItem.key ) {
				return ( value ? value + ' ' : '' ) + 'pztc-key-' + String( args.cartItem.key ).replace( /[^a-z0-9]/gi, '' );
			}
			return value;
		}
	} );

	function cartItems() {
		try {
			return window.wp.data.select( 'wc/store/cart' ).getCartData().items || [];
		} catch ( e ) {
			return [];
		}
	}

	function decorate() {
		cartItems().forEach( function ( item ) {
			var d = data( item.extensions );
			if ( ! d || ! d.edit_url ) {
				return;
			}
			var cls = 'pztc-key-' + String( item.key ).replace( /[^a-z0-9]/gi, '' );
			var rows = document.getElementsByClassName( cls );
			for ( var i = 0; i < rows.length; i++ ) {
				var row = rows[ i ];
				if ( row.querySelector( '.pztc-cart-edit-link' ) ) {
					continue;
				}
				var host = row.querySelector( '.wc-block-cart-item__product, .wc-block-components-order-summary-item__description' ) || row;
				var a = document.createElement( 'a' );
				a.className = 'pztc-cart-edit-link';
				a.href = d.edit_url;
				a.textContent = d.edit_label || 'Edit pizza';
				host.appendChild( a );
			}
		} );
	}

	var queued = false;
	function schedule() {
		if ( queued ) {
			return;
		}
		queued = true;
		window.requestAnimationFrame( function () {
			queued = false;
			decorate();
		} );
	}

	if ( window.MutationObserver ) {
		new window.MutationObserver( schedule ).observe( document.body, { childList: true, subtree: true } );
	}
	if ( window.wp && window.wp.data && typeof window.wp.data.subscribe === 'function' ) {
		window.wp.data.subscribe( schedule );
	}
	schedule();
}() );
