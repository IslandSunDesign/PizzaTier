/* PizzaTier Settings Wizard — admin JS */
/* eslint-disable no-var */
document.addEventListener( 'DOMContentLoaded', function () {
	'use strict';

	// ── Color inputs: sync text display sibling ──────────────────────────
	document.querySelectorAll( '.pzwiz-color' ).forEach( function ( c ) {
		var t = c.parentNode.querySelector( '.pzwiz-color-text' );
		if ( ! t ) { return; }
		c.addEventListener( 'input', function () { t.value = c.value; } );
	} );

	// ── Range inputs: sync value display sibling ─────────────────────────
	document.querySelectorAll( '.pzwiz-range' ).forEach( function ( r ) {
		var valEl = document.getElementById( r.id + '-val' );
		if ( ! valEl ) { return; }
		var suffix = valEl.getAttribute( 'data-suffix' ) || '';
		r.addEventListener( 'input', function () {
			valEl.textContent = r.value + suffix;
		} );
	} );

	// ── Step navigation ──────────────────────────────────────────────────
	// Handled by existing jQuery on the page; no additional JS needed here
} );
