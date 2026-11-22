/**
 * PizzaTier Topping Multiplier — admin screens.
 *
 * Shows only the fields that apply to the chosen method, the value box for the
 * chosen pricing rule, and one "layer" row per allowed portion.
 */
( function () {
	'use strict';

	function syncPricing( fieldset ) {
		var checked = fieldset.querySelector( 'input[type="radio"]:checked' );
		var value   = checked ? checked.value : '';
		fieldset.querySelectorAll( '[data-pztm-show]' ).forEach( function ( el ) {
			el.hidden = el.getAttribute( 'data-pztm-show' ) !== value;
		} );
	}

	document.querySelectorAll( '[data-pztm-pricing]' ).forEach( function ( fs ) {
		fs.addEventListener( 'change', function () { syncPricing( fs ); } );
		syncPricing( fs );
	} );

	document.querySelectorAll( '[data-pztm-box]' ).forEach( function ( box ) {
		var modeSel = box.querySelector( '[data-pztm-mode]' );
		var maxIn   = box.querySelector( '[data-pztm-max]' );
		var defMode = box.getAttribute( 'data-default-mode' ) || 'thickness';
		var defMax  = parseInt( box.getAttribute( 'data-default-max' ), 10 ) || Infinity; // 0 = no limit.

		function sync() {
			var mode = ( modeSel && modeSel.value ) || defMode;
			box.querySelectorAll( '[data-pztm-when]' ).forEach( function ( el ) {
				var modes = el.getAttribute( 'data-pztm-when' ).split( /\s+/ );
				el.hidden = modes.indexOf( mode ) === -1;
			} );
			var max = parseInt( maxIn && maxIn.value, 10 ) || defMax;
			box.querySelectorAll( '[data-pztm-level]' ).forEach( function ( row ) {
				row.hidden = parseInt( row.getAttribute( 'data-pztm-level' ), 10 ) > max;
			} );
		}

		if ( modeSel ) { modeSel.addEventListener( 'change', sync ); }
		if ( maxIn )   { maxIn.addEventListener( 'input', sync ); }
		sync();
	} );
}() );
