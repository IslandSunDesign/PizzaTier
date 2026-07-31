/**
 * PizzaTier — Product Type Panel Visibility
 *
 * Keeps all standard WooCommerce meta boxes, tabs, and panels visible
 * whenever "Pizza" is selected as the product type, and shows/hides the
 * standalone Pizza Configurator meta box based on type.
 *
 * WooCommerce hides panels/tabs that lack a show_if_<type> class when an
 * unknown product type is active. This script adds show_if_pizza to every
 * standard WC element (General, Inventory, Shipping, Linked Products,
 * Attributes, Advanced, Publish box, Short Description, Product Image,
 * Gallery, Categories, Tags) so the full editing UI remains accessible.
 *
 * Two passes are required:
 *   Pass 1 (immediate): adds classes as soon as DOM is ready, before WC hides panels.
 *   Pass 2 (setTimeout 200ms): re-applies after WC's own ready handlers have run.
 *
 * Also re-applies on every product-type dropdown change so switching back
 * to "Pizza" from another type restores visibility correctly.
 */

/* global jQuery */

( function ( $ ) {
	'use strict';

	function pizzatier_commerceAddPizzaClasses() {
		// ── WooCommerce product data tabs ────────────────────────────────────
		// General tab (pricing)
		$( '.product_data_tabs .general_options' ).addClass( 'show_if_pizza' );
		$( '.general_options.general_tab' ).addClass( 'show_if_pizza' );

		// All standard WC tabs that should remain visible for pizza products.
		// WC hides tabs that have no show_if_<type> class when an unknown type
		// is selected; we re-enable the ones that make sense for pizza.
		$( '.product_data_tabs .inventory_options' ).addClass( 'show_if_pizza' );
		$( '.product_data_tabs .shipping_options' ).addClass( 'show_if_pizza' );
		$( '.product_data_tabs .linked_product_options' ).addClass( 'show_if_pizza' );
		$( '.product_data_tabs .attribute_options' ).addClass( 'show_if_pizza' );
		$( '.product_data_tabs .advanced_options' ).addClass( 'show_if_pizza' );

		// ── WooCommerce product data panels ──────────────────────────────────
		// All options groups in the General panel (covers price rows)
		$( '#general_product_data .options_group' ).addClass( 'show_if_pizza' );
		$( '._regular_price_field' ).addClass( 'show_if_pizza' );
		$( '._sale_price_field' ).addClass( 'show_if_pizza' );

		// Inventory, Shipping, Linked Products, Attributes, Advanced panels
		$( '#inventory_product_data' ).addClass( 'show_if_pizza' );
		$( '#inventory_product_data .options_group' ).addClass( 'show_if_pizza' );
		$( '#shipping_product_data' ).addClass( 'show_if_pizza' );
		$( '#shipping_product_data .options_group' ).addClass( 'show_if_pizza' );
		$( '#linked_product_data' ).addClass( 'show_if_pizza' );
		$( '#linked_product_data .options_group' ).addClass( 'show_if_pizza' );
		$( '#product_attributes' ).addClass( 'show_if_pizza' );
		$( '#advanced_product_data' ).addClass( 'show_if_pizza' );
		$( '#advanced_product_data .options_group' ).addClass( 'show_if_pizza' );

		// ── Standard WordPress meta boxes ────────────────────────────────────
		// WC adds hide_if_* to these boxes for unknown product types; restore them.
		$( '#submitdiv' ).addClass( 'show_if_pizza' );
		$( '#postexcerpt' ).addClass( 'show_if_pizza' );   // Short description
		$( '#postimagediv' ).addClass( 'show_if_pizza' );  // Product image
		$( '#woocommerce-product-images' ).addClass( 'show_if_pizza' ); // Gallery
		$( '#categorydiv' ).addClass( 'show_if_pizza' );   // Categories
		$( '#tagsdiv-product_tag' ).addClass( 'show_if_pizza' ); // Tags
		$( '#woocommerce-product-updated-message' ).addClass( 'show_if_pizza' );

		// ── Pizza Configurator tab + panel ───────────────────────────────────
		$( '.product_data_tabs .pizza_configurator_options' ).addClass( 'show_if_pizza' );
		$( '#pizzatier_configurator_panel' ).addClass( 'show_if_pizza' );
		$( '#pizzatier_configurator_panel .options_group' ).addClass( 'show_if_pizza' );
	}

	/**
	 * Show or hide the standalone Pizza Configurator and Price Grid meta boxes
	 * based on product type selection.
	 */
	function pizzatier_commerceSyncMetaBox( isPizza ) {
		var $configurator = $( '#pizzatier_commerce_pizza_configurator' );
		var $priceGrid    = $( '#pizzatier_commerce_price_grid' );

		if ( $configurator.length ) {
			$configurator.toggle( isPizza );
		}
		if ( $priceGrid.length ) {
			$priceGrid.toggle( isPizza );
		}

		// Also sync the inner placeholder/body divs in the price grid meta box.
		if ( isPizza ) {
			$( '#pztc-pricegrid-placeholder' ).hide();
			$( '#pztc-pricegrid-body' ).show();
		} else {
			$( '#pztc-pricegrid-placeholder' ).show();
			$( '#pztc-pricegrid-body' ).hide();
		}
	}

	$( function () {
		var $type = $( 'select#product-type' );

		// Pass 1: immediately on DOM ready
		pizzatier_commerceAddPizzaClasses();
		pizzatier_commerceSyncMetaBox( $type.val() === 'pizza' );

		// Re-apply on every type change
		$( document ).on( 'change.pizzatier_commerce', 'select#product-type', function () {
			pizzatier_commerceAddPizzaClasses();
			pizzatier_commerceSyncMetaBox( $( this ).val() === 'pizza' );
		} );

		// Pass 2: after WC's own ready handlers have run.
		// We do NOT trigger 'change' here because WC's change handler re-hides
		// panels for unknown product types, undoing our show_if_pizza classes.
		// Instead: add classes, then do a nested setTimeout (Pass 3) to re-add
		// them after any synchronous WC hide logic that runs on the same tick.
		setTimeout( function () {
			var isPizza = $type.val() === 'pizza';
			pizzatier_commerceAddPizzaClasses();
			pizzatier_commerceSyncMetaBox( isPizza );

			// Pass 3: re-apply after any deferred WC panel logic.
			setTimeout( function () {
				pizzatier_commerceAddPizzaClasses();
				pizzatier_commerceSyncMetaBox( $type.val() === 'pizza' );
			}, 50 );
		}, 200 );
	} );

} )( jQuery );
