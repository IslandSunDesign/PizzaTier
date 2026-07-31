/* PizzaTier — Preset Builder admin JS (classic editor) */
/* eslint-disable no-var */
(function () {
	'use strict';

	// ── State ─────────────────────────────────────────────────────────────
	// pizzatier_commercePresets.state is null (new preset) or a plain object (existing).

	var cfg   = window.pizzatier_commercePresets || {};
	var saved = cfg.state;
	var state = ( saved && typeof saved === 'object' && ! Array.isArray( saved ) ) ? saved : {};
	if ( ! Array.isArray( state.topping_ids ) ) { state.topping_ids = []; }

	// layer type → state key
	var MAP = {
		crust:   'crust_id',
		sauce:   'sauce_id',
		cheese:  'cheese_id',
		drizzle: 'drizzle_id',
		cut:     'cut_id',
		size:    'size_id',
		topping: 'topping_ids',
	};

	var NAMES = {
		crust_id:   'Crust',
		sauce_id:   'Sauce',
		cheese_id:  'Cheese',
		drizzle_id: 'Drizzle',
		cut_id:     'Cut Style',
		size_id:    'Size',
	};

	// ── Persist ────────────────────────────────────────────────────────────

	function persistState() {
		var input = document.getElementById( 'pizzatier_commerce_preset_layers_input' );
		if ( input ) {
			input.value = JSON.stringify( state );
		}
	}

	// ── Canvas preview ─────────────────────────────────────────────────────

	function updateCanvas() {
		var canvas = document.getElementById( 'pztc-preview-canvas' );
		var empty  = document.getElementById( 'pztc-canvas-empty' );
		if ( ! canvas ) { return; }

		var imgs = canvas.querySelectorAll( '.pztc-layer-img' );
		var hasAny = false;

		imgs.forEach( function ( img ) {
			var t  = img.dataset.layerType;
			var id = parseInt( img.dataset.layerId, 10 );
			var show;

			if ( t === 'topping' ) {
				show = state.topping_ids.indexOf( id ) > -1;
			} else {
				var mk = MAP[ t ];
				show = !! ( mk && parseInt( state[ mk ], 10 ) === id );
			}

			img.classList.toggle( 'pztc-visible', show );
			if ( show ) { hasAny = true; }
		} );

		if ( empty ) { empty.style.display = hasAny ? 'none' : 'flex'; }
	}

	// ── Summary ────────────────────────────────────────────────────────────

	function updateSummary() {
		var rows = document.getElementById( 'pztc-summary-rows' );
		if ( ! rows ) { return; }

		var parts = [];

		Object.keys( NAMES ).forEach( function ( k ) {
			if ( state[ k ] && parseInt( state[ k ], 10 ) > 0 ) {
				var el  = document.querySelector( '.pztc-item[data-id="' + state[ k ] + '"][data-type="' + k.replace( '_id', '' ) + '"]' );
				parts.push( { key: NAMES[ k ], val: el ? el.dataset.label : '#' + state[ k ] } );
			}
		} );

		if ( state.topping_ids.length ) {
			var names = state.topping_ids.map( function ( tid ) {
				var el = document.querySelector( '.pztc-item[data-id="' + tid + '"][data-type="topping"]' );
				return el ? el.dataset.label : '#' + tid;
			} );
			parts.push( { key: 'Toppings', val: names.join( ', ' ) } );
		}

		if ( ! parts.length ) {
			rows.innerHTML = '<p style="color:#bbb;font-style:italic;font-size:12px;">No layers selected yet.</p>';
			return;
		}

		rows.innerHTML = parts.map( function ( p ) {
			return '<div class="pztc-preset-summary__item">'
				+ '<span class="pztc-preset-summary__label">' + p.key + '</span>'
				+ '<span class="pztc-preset-summary__value">' + p.val + '</span>'
				+ '</div>';
		} ).join( '' );
	}

	// ── Public API (called from onclick in PHP HTML) ────────────────────────

	window.pizzatier_commerceSelectItem = function ( el ) {
		var type    = el.dataset.type;
		var id      = parseInt( el.dataset.id, 10 );
		var section = el.closest( '.pztc-psec' );
		var isMulti = section && section.dataset.multi === '1';

		if ( isMulti ) {
			var idx = state.topping_ids.indexOf( id );
			if ( idx > -1 ) {
				state.topping_ids.splice( idx, 1 );
				el.classList.remove( 'pztc-selected-topping' );
			} else {
				state.topping_ids.push( id );
				el.classList.add( 'pztc-selected-topping' );
			}
			var badge = section.querySelector( '.pztc-psec__badge' );
			if ( badge ) { badge.textContent = state.topping_ids.length || ''; }

		} else {
			var grid = el.closest( '.pztc-item-grid' );
			if ( grid ) {
				grid.querySelectorAll( '.pztc-item' ).forEach( function ( i ) {
					i.classList.remove( 'pztc-selected' );
				} );
			}
			el.classList.add( 'pztc-selected' );
			var mk = MAP[ type ];
			if ( mk ) { state[ mk ] = id > 0 ? id : 0; }

			var lbl = document.getElementById( 'pztc-sel-label-' + type );
			if ( lbl ) { lbl.textContent = id > 0 ? ( el.dataset.label || 'Selected' ) : 'None'; }
		}

		persistState();
		updateCanvas();
		updateSummary();
	};

	window.pizzatier_commerceToggleSection = function ( header ) {
		var section = header.closest( '.pztc-psec' );
		if ( section ) { section.classList.toggle( 'pztc-open' ); }
	};

	// ── Shortcode copy ─────────────────────────────────────────────────────

	window.pizzatier_commerceCopyShortcode = function () {
		var el   = document.getElementById( 'pztc-sc-display' );
		var btn  = document.getElementById( 'pztc-sc-copy-btn' );
		if ( ! el ) { return; }
		var text = el.textContent.trim();
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text )
				.then( function () { pizzatier_commerceFlashCopied( btn ); } )
				.catch( function () { pizzatier_commerceFallbackCopy( text, btn ); } );
		} else {
			pizzatier_commerceFallbackCopy( text, btn );
		}
	};

	function pizzatier_commerceFallbackCopy( text, btn ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
		document.body.appendChild( ta );
		ta.focus(); ta.select();
		try { document.execCommand( 'copy' ); } catch ( e ) {}
		document.body.removeChild( ta );
		pizzatier_commerceFlashCopied( btn );
	}

	function pizzatier_commerceFlashCopied( btn ) {
		if ( ! btn ) { return; }
		var orig = btn.innerHTML;
		btn.innerHTML = '<span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;margin-top:1px"></span> Copied!';
		btn.classList.add( 'pztc-copied' );
		setTimeout( function () { btn.innerHTML = orig; btn.classList.remove( 'pztc-copied' ); }, 2000 );
	}

	// ── Init ───────────────────────────────────────────────────────────────

	function init() {
		persistState();
		updateCanvas();
		updateSummary();

		var postForm = document.getElementById( 'post' );
		if ( postForm ) { postForm.addEventListener( 'submit', persistState ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

}());
