/**
 * PizzaTier — Native Order Checkout
 *
 * Drives the order bar rendered into the builder action-bar area and the
 * checkout panel printed in the footer.
 *
 * Reads the current pizza from window.PizzaTierAPI, which every bundled
 * template exposes. Three different state shapes ship across the templates and
 * normaliseState() flattens all of them:
 *
 *   1. Standard  — { crust, sauce, cheese, drizzle, cut, toppings:{slug:{…}} }
 *                  (colorbox, nightpie, rustic, metro, pocketpie, scaffold)
 *   2. Grouped   — { exclusive:{ type:{…} }, toppings:{…} }   (plainlist)
 *   3. Wrapped   — { selections:{ crust, …, toppings:[], slicing, size } }
 *                  (commandcenter)
 *
 * Only slugs and coverage fractions are sent to the server. Names, post IDs and
 * prices are all re-resolved server-side, so nothing here is trusted.
 *
 * Config: window.pizzatierOrders (localised by OrderCheckout::enqueue_assets).
 */

( function () {
	'use strict';

	var CFG = window.pizzatierOrders || {};
	var I18N = CFG.i18n || {};
	var SETTINGS = CFG.settings || {};

	/** Per-instance UI state, keyed by builder instance ID. */
	var instances = {};

	/** The instance the open panel belongs to. */
	var activeInstance = '';

	/** Element that had focus before the panel opened, for focus restore. */
	var lastFocused = null;

	// =========================================================================
	// Helpers
	// =========================================================================

	function $( sel, root ) {
		return ( root || document ).querySelector( sel );
	}

	function $$( sel, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) );
	}

	function text( el, value ) {
		if ( el ) { el.textContent = value; }
	}

	function getInstanceState( id ) {
		if ( ! instances[ id ] ) {
			instances[ id ] = { quantity: 1, sizeSlug: '', sizeLabel: '' };
		}
		return instances[ id ];
	}

	// =========================================================================
	// Reading the builder
	// =========================================================================

	/**
	 * Pull raw state for one instance out of window.PizzaTierAPI.
	 * Templates expose slightly different accessors, so try each in turn.
	 */
	function readRawState( instanceId ) {
		var api = window.PizzaTierAPI;
		if ( ! api ) { return null; }

		var state = null;

		if ( instanceId && typeof api.getState === 'function' ) {
			try { state = api.getState( instanceId ); } catch ( e ) { state = null; }
		}

		if ( ! state && typeof api.getInstances === 'function' ) {
			try {
				var map = api.getInstances() || {};
				for ( var key in map ) {
					if ( ! Object.prototype.hasOwnProperty.call( map, key ) ) { continue; }
					if ( map[ key ] && typeof map[ key ].getState === 'function' ) {
						state = map[ key ].getState();
						if ( state ) { break; }
					}
				}
			} catch ( e ) { /* fall through */ }
		}

		if ( ! state && typeof api.getAllInstances === 'function' && typeof api.getState === 'function' ) {
			try {
				var ids = api.getAllInstances() || [];
				for ( var i = 0; i < ids.length; i++ ) {
					state = api.getState( ids[ i ] );
					if ( state ) { break; }
				}
			} catch ( e ) { /* fall through */ }
		}

		if ( ! state && typeof api.getState === 'function' ) {
			try { state = api.getState(); } catch ( e ) { state = null; }
		}

		return state;
	}

	/** Exclusive (single-choice) layer types, in the order they stack. */
	var EXCLUSIVE_TYPES = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];

	/** Aliases templates use for the cut layer. */
	var CUT_ALIASES = [ 'cut', 'slicing', 'cuts' ];

	/**
	 * Flatten any template's state into a plain layer list:
	 *   [ { type, slug, name, coverage } ]
	 */
	function normaliseState( raw ) {
		if ( ! raw || typeof raw !== 'object' ) { return []; }

		// Shape 3 — commandcenter wraps everything in `selections`.
		var src = raw.selections && typeof raw.selections === 'object' ? raw.selections : raw;

		var layers = [];

		function pushExclusive( type, entry ) {
			if ( ! entry ) { return; }
			var slug = entrySlug( entry );
			if ( ! slug ) { return; }
			layers.push( {
				type: type,
				slug: slug,
				name: entryName( entry ) || slug,
				coverage: 'whole'
			} );
		}

		// Shape 2 — plainlist groups single-choice layers under `exclusive`.
		if ( src.exclusive && typeof src.exclusive === 'object' ) {
			for ( var exKey in src.exclusive ) {
				if ( ! Object.prototype.hasOwnProperty.call( src.exclusive, exKey ) ) { continue; }
				pushExclusive( canonicalType( exKey ), src.exclusive[ exKey ] );
			}
		}

		// Shape 1 and 3 — single-choice layers sit directly on the object.
		for ( var i = 0; i < EXCLUSIVE_TYPES.length; i++ ) {
			var type = EXCLUSIVE_TYPES[ i ];
			var entry = null;

			if ( 'cut' === type ) {
				for ( var a = 0; a < CUT_ALIASES.length; a++ ) {
					if ( src[ CUT_ALIASES[ a ] ] ) { entry = src[ CUT_ALIASES[ a ] ]; break; }
				}
			} else {
				entry = src[ type ];
			}

			if ( entry && ! hasType( layers, type ) ) {
				pushExclusive( type, entry );
			}
		}

		// Toppings — an object map on most templates, an array on commandcenter.
		var toppings = src.toppings;
		if ( toppings && typeof toppings === 'object' ) {
			if ( Object.prototype.toString.call( toppings ) === '[object Array]' ) {
				for ( var t = 0; t < toppings.length; t++ ) {
					pushTopping( layers, toppings[ t ], null );
				}
			} else {
				for ( var slug in toppings ) {
					if ( ! Object.prototype.hasOwnProperty.call( toppings, slug ) ) { continue; }
					pushTopping( layers, toppings[ slug ], slug );
				}
			}
		}

		return layers;
	}

	function pushTopping( layers, entry, fallbackSlug ) {
		if ( ! entry ) { return; }
		var slug = entrySlug( entry ) || fallbackSlug;
		if ( ! slug ) { return; }
		layers.push( {
			type: 'topping',
			slug: slug,
			name: entryName( entry ) || slug,
			coverage: ( entry && entry.coverage ) ? String( entry.coverage ) : 'whole'
		} );
	}

	function entrySlug( entry ) {
		if ( ! entry ) { return ''; }
		if ( typeof entry === 'string' ) { return entry; }
		return String( entry.slug || entry.id || entry.value || '' );
	}

	function entryName( entry ) {
		if ( ! entry || typeof entry === 'string' ) { return ''; }
		return String( entry.title || entry.name || entry.label || '' );
	}

	function canonicalType( key ) {
		var k = String( key ).toLowerCase().replace( /s$/, '' );
		if ( 'slicing' === k ) { return 'cut'; }
		return k;
	}

	function hasType( layers, type ) {
		for ( var i = 0; i < layers.length; i++ ) {
			if ( layers[ i ].type === type ) { return true; }
		}
		return false;
	}

	// =========================================================================
	// Summary rendering
	// =========================================================================

	/** One-line summary shown in the order bar. */
	function summarise( layers ) {
		if ( ! layers.length ) { return I18N.noSelection || 'Nothing selected yet'; }

		var names = [];
		for ( var i = 0; i < layers.length; i++ ) {
			names.push( layers[ i ].name );
		}
		if ( names.length > 4 ) {
			return names.slice( 0, 4 ).join( ', ' ) + ' +' + ( names.length - 4 );
		}
		return names.join( ', ' );
	}

	/** Refresh every order bar's summary line. */
	function refreshBars() {
		$$( '.pzt-order-bar' ).forEach( function ( bar ) {
			var id = bar.getAttribute( 'data-instance' ) || '';
			var detail = $( '.pzt-order-bar__detail', bar );
			text( detail, summarise( normaliseState( readRawState( id ) ) ) );
		} );
	}

	/** Grouped, readable review list inside the panel. */
	function renderReview( layers ) {
		var host = $( '#pzt-order-review' );
		if ( ! host ) { return; }

		host.innerHTML = '';

		if ( ! layers.length ) {
			var empty = document.createElement( 'p' );
			empty.className = 'pzt-order-review__empty';
			empty.textContent = I18N.emptyPizza || 'Build your pizza first.';
			host.appendChild( empty );
			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'pzt-order-review__list';

		layers.forEach( function ( layer ) {
			var li = document.createElement( 'li' );
			li.className = 'pzt-order-review__item pzt-order-review__item--' + layer.type;

			var name = document.createElement( 'span' );
			name.className = 'pzt-order-review__name';
			name.textContent = layer.name;
			li.appendChild( name );

			if ( layer.coverage && 'whole' !== layer.coverage ) {
				var cov = document.createElement( 'span' );
				cov.className = 'pzt-order-review__coverage';
				cov.textContent = prettyCoverage( layer.coverage );
				li.appendChild( cov );
			}

			list.appendChild( li );
		} );

		host.appendChild( list );
	}

	function prettyCoverage( coverage ) {
		return String( coverage )
			.replace( /-/g, ' ' )
			.replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
	}

	// =========================================================================
	// Panel
	// =========================================================================

	function openPanel( instanceId ) {
		var panel = $( '#pzt-order-panel' );
		if ( ! panel ) { return; }

		var layers = normaliseState( readRawState( instanceId ) );
		if ( ! layers.length ) {
			flashBarNotice( instanceId, I18N.emptyPizza || 'Build your pizza first.' );
			return;
		}

		activeInstance = instanceId;
		lastFocused = document.activeElement;

		renderReview( layers );
		clearErrors();

		// Reset between openings so a second order does not inherit the first's
		// confirmation screen.
		showEl( $( '.pzt-order-panel__body', panel ), true );
		showEl( $( '.pzt-order-panel__footer', panel ), true );
		showEl( $( '#pzt-order-done' ), false );

		panel.hidden = false;
		document.body.classList.add( 'pzt-order-open' );

		syncMethodFields();

		var firstInput = $( '.pzt-order-input', panel );
		if ( firstInput ) { firstInput.focus(); }
	}

	function closePanel() {
		var panel = $( '#pzt-order-panel' );
		if ( ! panel ) { return; }
		panel.hidden = true;
		document.body.classList.remove( 'pzt-order-open' );
		activeInstance = '';
		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	function showEl( el, visible ) {
		if ( el ) { el.hidden = ! visible; }
	}

	function flashBarNotice( instanceId, message ) {
		var bar = $( '.pzt-order-bar[data-instance="' + cssEscape( instanceId ) + '"]' );
		if ( ! bar ) { return; }
		var detail = $( '.pzt-order-bar__detail', bar );
		if ( ! detail ) { return; }
		var previous = detail.textContent;
		detail.textContent = message;
		bar.classList.add( 'is-warning' );
		window.setTimeout( function () {
			detail.textContent = previous;
			bar.classList.remove( 'is-warning' );
		}, 2600 );
	}

	function cssEscape( value ) {
		return String( value ).replace( /["\\]/g, '\\$&' );
	}

	/** Show the address block for delivery, the table block for dine-in. */
	function syncMethodFields() {
		var selected = $( '.pzt-order-method.is-selected' );
		var method = selected ? selected.getAttribute( 'data-pzt-method' ) : '';

		$$( '.pzt-order-fields[data-pzt-show-for]' ).forEach( function ( block ) {
			block.hidden = block.getAttribute( 'data-pzt-show-for' ) !== method;
		} );
	}

	function selectedMethod() {
		var selected = $( '.pzt-order-method.is-selected' );
		return selected ? selected.getAttribute( 'data-pzt-method' ) : 'pickup';
	}

	function fieldValue( name ) {
		var el = $( '[data-pzt-field="' + name + '"]' );
		return el ? String( el.value || '' ).trim() : '';
	}

	// =========================================================================
	// Validation & errors
	// =========================================================================

	function clearErrors() {
		$$( '.pzt-order-field__error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		$$( '.pzt-order-input.has-error' ).forEach( function ( el ) {
			el.classList.remove( 'has-error' );
		} );
		var box = $( '#pzt-order-error' );
		if ( box ) { box.hidden = true; text( box, '' ); }
	}

	function setFieldError( name, message ) {
		var slot = $( '[data-pzt-error-for="' + name + '"]' );
		if ( slot ) { slot.textContent = message; }
		var input = $( '[data-pzt-field="' + name + '"]' );
		if ( input ) { input.classList.add( 'has-error' ); }
	}

	function setGlobalError( message ) {
		var box = $( '#pzt-order-error' );
		if ( ! box ) { return; }
		box.textContent = message;
		box.hidden = false;
	}

	/**
	 * Client-side checks, purely for fast feedback. The server revalidates
	 * everything regardless of what happens here.
	 */
	function validate() {
		clearErrors();
		var ok = true;

		if ( SETTINGS.requireName && '' === fieldValue( 'customer_name' ) ) {
			setFieldError( 'customer_name', I18N.requiredField || 'This field is required.' );
			ok = false;
		}
		if ( SETTINGS.requirePhone && '' === fieldValue( 'customer_phone' ) ) {
			setFieldError( 'customer_phone', I18N.requiredField || 'This field is required.' );
			ok = false;
		}

		var email = fieldValue( 'customer_email' );
		if ( SETTINGS.requireEmail && '' === email ) {
			setFieldError( 'customer_email', I18N.requiredField || 'This field is required.' );
			ok = false;
		} else if ( '' !== email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			setFieldError( 'customer_email', I18N.invalidEmail || 'Please enter a valid email address.' );
			ok = false;
		}

		if ( 'delivery' === selectedMethod() && '' === fieldValue( 'address_line1' ) ) {
			setFieldError( 'address_line1', I18N.requiredField || 'This field is required.' );
			ok = false;
		}

		return ok;
	}

	// =========================================================================
	// Submission
	// =========================================================================

	/**
	 * Which WooCommerce product this builder belongs to.
	 *
	 * FrontendEmbed wraps each embedded builder in .pztc-builder-section and
	 * puts the product ID on it, which is the only reliable answer when a page
	 * carries more than one builder. A plain shortcode page has no wrapper, so
	 * the localised page-level value is used instead. Either way the server
	 * validates the result and can fall back to the store's chosen product, so
	 * returning 0 here is a normal outcome rather than an error.
	 *
	 * @param {string} instanceId Builder instance ID.
	 * @returns {number}
	 */
	function resolveProductId( instanceId ) {
		var bar = $( '.pzt-order-bar[data-instance="' + cssEscape( instanceId ) + '"]' );
		var section = bar ? bar.closest( '.pztc-builder-section' ) : null;

		if ( ! section ) {
			section = $( '.pztc-builder-section[data-product-id]' );
		}

		if ( section ) {
			var id = parseInt( section.getAttribute( 'data-product-id' ), 10 );
			if ( id > 0 ) { return id; }
		}

		return parseInt( CFG.productId, 10 ) > 0 ? parseInt( CFG.productId, 10 ) : 0;
	}

	function submitOrder() {
		if ( ! validate() ) { return; }

		var layers = normaliseState( readRawState( activeInstance ) );
		if ( ! layers.length ) {
			setGlobalError( I18N.emptyPizza || 'Build your pizza first.' );
			return;
		}

		var ui = getInstanceState( activeInstance );
		var sizeEl = $( '.pzt-order-size.is-selected' );

		var item = {
			instance_id: activeInstance,
			template: CFG.template || '',
			quantity: ui.quantity,
			notes: fieldValue( 'customer_note' ),
			layers: layers.map( function ( layer ) {
				return { type: layer.type, slug: layer.slug, coverage: layer.coverage };
			} ),
			size: sizeEl ? { slug: sizeEl.getAttribute( 'data-pzt-size-slug' ) } : {}
		};

		var body = new window.FormData();
		body.append( 'action', CFG.action );
		body.append( 'nonce', CFG.nonce );
		body.append( 'items', JSON.stringify( [ item ] ) );
		body.append( 'template', CFG.template || '' );
		body.append( 'page_id', String( CFG.pageId || 0 ) );
		body.append( 'page_url', window.location.href );
		body.append( 'product_id', String( resolveProductId( activeInstance ) ) );
		body.append( 'fulfillment_method', selectedMethod() );

		[
			'customer_name', 'customer_email', 'customer_phone', 'customer_note',
			'requested_time', 'delivery_instructions', 'table_number',
			'address_line1', 'address_line2', 'address_city',
			'address_state', 'address_postcode', 'address_country',
			'pzt_website'
		].forEach( function ( name ) {
			body.append( name, fieldValue( name ) );
		} );

		var button = $( '#pzt-order-submit' );
		setSubmitting( button, true );

		window.fetch( CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( response ) {
				return response.json().catch( function () { return null; } );
			} )
			.then( function ( payload ) {
				setSubmitting( button, false );

				if ( payload && payload.success ) {
					showConfirmation( payload.data || {} );
					return;
				}

				var data = ( payload && payload.data ) ? payload.data : {};

				if ( data.fields && typeof data.fields === 'object' ) {
					for ( var name in data.fields ) {
						if ( Object.prototype.hasOwnProperty.call( data.fields, name ) ) {
							setFieldError( name, data.fields[ name ] );
						}
					}
				}

				setGlobalError( data.message || I18N.genericError || 'Something went wrong.' );
			} )
			.catch( function () {
				setSubmitting( button, false );
				setGlobalError( I18N.networkError || 'Could not reach the store.' );
			} );
	}

	function setSubmitting( button, busy ) {
		if ( ! button ) { return; }
		button.disabled = busy;
		button.classList.toggle( 'is-busy', busy );
		if ( busy ) {
			button.setAttribute( 'data-label', button.textContent );
			button.textContent = I18N.submitting || 'Sending…';
		} else if ( button.getAttribute( 'data-label' ) ) {
			button.textContent = button.getAttribute( 'data-label' );
			button.removeAttribute( 'data-label' );
		}
	}

	function showConfirmation( data ) {
		var panel = $( '#pzt-order-panel' );
		if ( ! panel ) { return; }

		showEl( $( '.pzt-order-panel__body', panel ), false );
		showEl( $( '.pzt-order-panel__footer', panel ), false );
		showEl( $( '#pzt-order-done' ), true );

		text( $( '#pzt-order-done-message' ), data.message || I18N.confirmed || 'Thanks!' );

		var numberEl = $( '#pzt-order-done-number' );
		if ( data.order_number ) {
			var pattern = I18N.orderNumber || 'Your order number is %s.';
			text( numberEl, pattern.replace( '%s', data.order_number ) );
		} else {
			text( numberEl, '' );
		}

		renderRouteOutcome( data );

		/**
		 * Let themes and add-ons react to a completed order — analytics,
		 * confetti, a redirect, whatever the site needs.
		 */
		document.dispatchEvent( new window.CustomEvent( 'pizzatier:order-placed', { detail: data } ) );

		// A server-supplied redirect is the store's routing decision, so it runs
		// last and unconditionally. The pause lets the customer read the
		// confirmation and gives the event above time to fire.
		if ( data.redirect ) {
			window.setTimeout( function () {
				window.location.href = data.redirect;
			}, 900 );
		}
	}

	/**
	 * Tell the customer what happened beyond the order itself — that the pizza
	 * is in their cart, or that it could not be put there.
	 *
	 * @param {object} data Response payload.
	 */
	function renderRouteOutcome( data ) {
		var host = $( '#pzt-order-done' );
		if ( ! host ) { return; }

		var existing = $( '.pzt-order-done__route', host );
		if ( existing ) { existing.parentNode.removeChild( existing ); }

		var warnings = ( data.warnings && data.warnings.length ) ? data.warnings : [];
		if ( ! data.cart_added && ! warnings.length ) { return; }

		var note = document.createElement( 'p' );
		note.className = 'pzt-order-done__route';

		if ( data.cart_added ) {
			note.appendChild( document.createTextNode( I18N.addedToCart || 'Your pizza has also been added to your cart.' ) );

			if ( data.cart_url && ! data.redirect ) {
				note.appendChild( document.createTextNode( ' ' ) );
				var link = document.createElement( 'a' );
				link.className = 'pzt-order-done__cart-link';
				link.href = data.cart_url;
				link.textContent = I18N.goToCart || 'Go to cart';
				note.appendChild( link );
			}
		} else {
			note.className += ' pzt-order-done__route--warning';
			note.textContent = warnings[ 0 ] || I18N.cartFailed || 'We saved your order, but could not add it to your cart.';
		}

		host.appendChild( note );
	}

	// =========================================================================
	// Quantity stepper
	// =========================================================================

	function stepQuantity( instanceId, delta, max ) {
		var ui = getInstanceState( instanceId );
		ui.quantity = Math.min( max, Math.max( 1, ui.quantity + delta ) );

		var display = $( '#pzt-order-qty-' + cssEscape( instanceId ) );
		if ( display ) {
			display.textContent = String( ui.quantity );
			display.setAttribute( 'data-qty', String( ui.quantity ) );
		}

		var wrap = $( '.pzt-order-bar__qty[data-instance="' + cssEscape( instanceId ) + '"]' );
		if ( wrap ) {
			var minus = $( '.pzt-order-qty-btn--minus', wrap );
			var plus = $( '.pzt-order-qty-btn--plus', wrap );
			if ( minus ) { minus.disabled = ui.quantity <= 1; }
			if ( plus ) { plus.disabled = ui.quantity >= max; }
		}
	}

	// =========================================================================
	// Wiring
	// =========================================================================

	function onDocumentClick( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) { return; }

		// Open the panel.
		var openBtn = target.closest( '.pzt-order-open-btn' );
		if ( openBtn ) {
			event.preventDefault();
			openPanel( openBtn.getAttribute( 'data-instance' ) || '' );
			return;
		}

		// Close it.
		if ( target.closest( '[data-pzt-order-close]' ) ) {
			event.preventDefault();
			closePanel();
			return;
		}

		// Submit.
		if ( target.closest( '#pzt-order-submit' ) ) {
			event.preventDefault();
			submitOrder();
			return;
		}

		// Quantity stepper.
		var qtyBtn = target.closest( '.pzt-order-qty-btn' );
		if ( qtyBtn ) {
			event.preventDefault();
			var id = qtyBtn.getAttribute( 'data-instance' ) || '';
			var wrap = qtyBtn.closest( '.pzt-order-bar__qty' );
			var max = wrap ? parseInt( wrap.getAttribute( 'data-max' ), 10 ) : 20;
			stepQuantity( id, parseInt( qtyBtn.getAttribute( 'data-step' ), 10 ) || 0, max || 20 );
			return;
		}

		// Fulfilment method picker.
		var methodBtn = target.closest( '.pzt-order-method' );
		if ( methodBtn ) {
			event.preventDefault();
			$$( '.pzt-order-method' ).forEach( function ( btn ) {
				var on = btn === methodBtn;
				btn.classList.toggle( 'is-selected', on );
				btn.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
			syncMethodFields();
			return;
		}

		// Size picker.
		var sizeBtn = target.closest( '.pzt-order-size' );
		if ( sizeBtn ) {
			event.preventDefault();
			$$( '.pzt-order-size' ).forEach( function ( btn ) {
				var on = btn === sizeBtn;
				btn.classList.toggle( 'is-selected', on );
				btn.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
			return;
		}

		// Any other click may have changed the pizza — refresh the bars.
		scheduleRefresh();
	}

	var refreshTimer = null;

	function scheduleRefresh() {
		if ( refreshTimer ) { window.clearTimeout( refreshTimer ); }
		refreshTimer = window.setTimeout( refreshBars, 120 );
	}

	function onKeyDown( event ) {
		if ( 'Escape' !== event.key && 'Esc' !== event.key ) { return; }
		var panel = $( '#pzt-order-panel' );
		if ( panel && ! panel.hidden ) {
			closePanel();
		}
	}

	function bindNoteCounter() {
		var field = $( '[data-pzt-field="customer_note"]' );
		var counter = $( '[data-pzt-counter="customer_note"]' );
		if ( ! field || ! counter ) { return; }

		var max = parseInt( field.getAttribute( 'maxlength' ), 10 ) || SETTINGS.noteMaxLength || 500;

		function update() {
			counter.textContent = field.value.length + ' / ' + max;
		}

		field.addEventListener( 'input', update );
		update();
	}

	function prefillCustomer() {
		var customer = CFG.customer || {};
		[ [ 'customer_name', customer.name ], [ 'customer_email', customer.email ] ]
			.forEach( function ( pair ) {
				var el = $( '[data-pzt-field="' + pair[ 0 ] + '"]' );
				if ( el && ! el.value && pair[ 1 ] ) { el.value = pair[ 1 ]; }
			} );
	}

	function init() {
		if ( ! $( '.pzt-order-bar' ) ) { return; }

		document.addEventListener( 'click', onDocumentClick );
		document.addEventListener( 'change', scheduleRefresh );
		document.addEventListener( 'keydown', onKeyDown );

		bindNoteCounter();
		prefillCustomer();
		syncMethodFields();

		// The templates populate their default layers shortly after load, so
		// give them a beat before drawing the first summary.
		refreshBars();
		window.setTimeout( refreshBars, 600 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
