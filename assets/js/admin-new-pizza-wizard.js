/* PizzaTier New Pizza Wizard — admin JS */
/* eslint-disable no-var */

( function () {

	// =========================================================================
	// Step 1 — Product image picker (wp.media)
	// =========================================================================

	function initImagePicker() {
		var btn = document.getElementById( 'pztc-wiz-select-image' );
		if ( ! btn ) return;

		var frame;
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( {
				title:    'Select Product Image',
				button:   { text: 'Use Image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var att  = frame.state().get( 'selection' ).first().toJSON();
				var idEl = document.getElementById( 'pizzatier_commerce_wiz_image_id' );
				var prev = document.getElementById( 'pztc-wiz-image-preview' );
				if ( idEl ) idEl.value = att.id;
				if ( prev ) prev.innerHTML = '<img src="' + att.url + '" alt="" style="max-width:200px;border-radius:6px;">';
			} );
			frame.open();
		} );
	}

	// =========================================================================
	// Step 2 — Preset card selection
	// =========================================================================

	function initPresetCards() {
		document.querySelectorAll( '.pztc-wiz-preset-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				document.querySelectorAll( '.pztc-wiz-preset-card' ).forEach( function ( c ) {
					c.classList.remove( 'pztc-wiz-preset-card--selected' );
				} );
				card.classList.add( 'pztc-wiz-preset-card--selected' );
				var radio = card.querySelector( 'input[type=radio]' );
				if ( radio ) radio.checked = true;
			} );
		} );
	}

	// =========================================================================
	// Step 3 — Layer configurator cards (same logic as main configurator)
	// =========================================================================

	function initLayerCards() {
		document.querySelectorAll( '.pztc-lc-card' ).forEach( function ( card ) {
			card.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'pztc-lc-card__avail' ) ) return;
				var plural = card.dataset.typePlural;
				document.querySelectorAll( '#pztc-wiz-grid-' + plural + ' .pztc-lc-card' ).forEach( function ( c ) {
					c.classList.remove( 'pztc-lc-card--default' );
				} );
				card.classList.add( 'pztc-lc-card--default' );
				var radio = card.querySelector( '.pztc-lc-card__radio' );
				if ( radio ) radio.checked = true;
			} );

			var cb = card.querySelector( '.pztc-lc-card__avail' );
			if ( cb ) {
				cb.addEventListener( 'change', function () {
					card.classList.toggle( 'pztc-lc-card--enabled', cb.checked );
				} );
			}
		} );

		document.querySelectorAll( '.js-wiz-select-all' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var type = cb.dataset.type;
				document.querySelectorAll( '#pztc-wiz-grid-' + type + ' .pztc-lc-card__avail' ).forEach( function ( a ) {
					a.checked = cb.checked;
					a.dispatchEvent( new Event( 'change' ) );
				} );
			} );
		} );
	}

	// =========================================================================
	// Boot
	// =========================================================================

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initImagePicker();
			initPresetCards();
			initLayerCards();
		} );
	} else {
		initImagePicker();
		initPresetCards();
		initLayerCards();
	}

}() );
