( function () {
	'use strict';

	var tabs   = document.querySelectorAll( '.psg-tab' );
	var panels = document.querySelectorAll( '.psg-panel' );

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			tabs.forEach( function ( t ) {
				t.classList.remove( 'psg-tab--active' );
				t.setAttribute( 'aria-selected', 'false' );
			} );
			panels.forEach( function ( p ) {
				p.classList.remove( 'psg-panel--active' );
			} );
			tab.classList.add( 'psg-tab--active' );
			tab.setAttribute( 'aria-selected', 'true' );
			var panel = document.getElementById( 'psg-panel-' + tab.dataset.tab );
			if ( panel ) {
				panel.classList.add( 'psg-panel--active' );
			}
		} );
	} );

} )();
