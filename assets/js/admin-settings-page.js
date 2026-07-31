/* PizzaTier Settings Page — admin JS */
/* eslint-disable no-var */
	( function () {
		'use strict';
		var tabs   = document.querySelectorAll( '#pztc-settings-tabs .pztc-tab' );
		var panels = document.querySelectorAll( '.pztc-panel' );
		if ( ! tabs.length ) return;

		function activate( targetId ) {
			tabs.forEach( function( t ) {
				var active = t.dataset.tab === targetId;
				t.classList.toggle( 'pztc-tab--active', active );
				t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			panels.forEach( function( p ) {
				p.classList.toggle( 'pztc-panel--active', p.id === 'pztc-panel-' + targetId );
			} );
		}

		var initial = window.location.hash
			? window.location.hash.replace( '#', '' )
			: tabs[0].dataset.tab;
		activate( initial );

		tabs.forEach( function( tab ) {
			tab.addEventListener( 'click', function() {
				history.replaceState( null, '', '#' + tab.dataset.tab );
				activate( tab.dataset.tab );
			} );
		} );
	}() );

	/* ── Pricing panel: conditional field visibility ── */
	( function() {
		'use strict';

		// Fields that only apply for specific pricing modes
		// key => space-separated list of modes they are relevant to (empty = always show)
		var MODE_FIELDS = {
			'pizzatier_commerce_field_free_toppings_count':       ['addon_per_layer','free_first_n'],
			'pizzatier_commerce_field_tiered_topping_thresholds': ['tiered_by_count'],
			'pizzatier_commerce_field_min_topping_price':         ['addon_per_layer','free_first_n'],
			'pizzatier_commerce_field_max_topping_price':         ['addon_per_layer','free_first_n'],
		};

		// Fields visible only when non_topping_pricing = 'fixed'
		var NT_FIXED_FIELDS = [
			'pizzatier_commerce_field_crust_fixed_price',
			'pizzatier_commerce_field_sauce_fixed_price',
			'pizzatier_commerce_field_cheese_fixed_price',
			'pizzatier_commerce_field_drizzle_fixed_price',
		];

		function getRow( fieldId ) {
			var el = document.getElementById( fieldId );
			if ( ! el ) return null;
			return el.closest( 'tr' );
		}

		function applyModeVisibility( mode ) {
			Object.keys( MODE_FIELDS ).forEach( function( fieldId ) {
				var row = getRow( fieldId );
				if ( ! row ) return;
				var relevant = MODE_FIELDS[ fieldId ];
				row.style.display = ( ! relevant.length || relevant.indexOf( mode ) > -1 ) ? '' : 'none';
			} );
		}

		function applyNtVisibility( ntMode ) {
			NT_FIXED_FIELDS.forEach( function( fieldId ) {
				var row = getRow( fieldId );
				if ( row ) row.style.display = ntMode === 'fixed' ? '' : 'none';
			} );
		}

		// Watch pricing mode card selection
		var modeGrid = document.getElementById( 'pztc-pricing-mode-grid' );
		var modeInput = document.getElementById( 'pizzatier_commerce_pricing_mode_input' );
		if ( modeGrid && modeInput ) {
			applyModeVisibility( modeInput.value );
			modeGrid.addEventListener( 'click', function( e ) {
				var card = e.target.closest( '.pztc-mode-card' );
				if ( card ) applyModeVisibility( card.dataset.mode || '' );
			} );
		}

		// Watch non_topping_pricing select
		var ntSelect = document.getElementById( 'pizzatier_commerce_field_non_topping_pricing' );
		if ( ntSelect ) {
			applyNtVisibility( ntSelect.value );
			ntSelect.addEventListener( 'change', function() {
				applyNtVisibility( this.value );
			} );
		}

		// Also style the discount fields as a visual group
		var discRows = ['pizzatier_commerce_field_discount_threshold','pizzatier_commerce_field_discount_percent','pizzatier_commerce_field_discount_max_amount'];
		discRows.forEach( function( id ) {
			var row = getRow( id );
			if ( row ) row.classList.add( 'pztc-row-grouped' );
		} );
	}() );
