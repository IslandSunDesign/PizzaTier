/**
 * PizzaTier Legacy Builder JS
 *
 * Contains the jQuery-based layer management functions used by older-style
 * template PHP onclick= handlers and legacy integrations.
 *
 * All internal helpers are scoped inside an IIFE to avoid global pollution.
 * Three functions are intentionally exposed on window because template PHP
 * renders them directly into onclick= attributes:
 *   window.ClearPizza()
 *   window.RotatePizza( id, speed )
 *   window.StopPizza( id )
 */
( function ( $ ) {
	'use strict';

	// ── Private state ─────────────────────────────────────────────────
	var rotationIntervals = {};

	// ── Coverage / portion contract (shared by all templates) ─────────
	//
	// A topping/layer's coverage has TWO distinct facets that downstream
	// consumers (PizzaTier pricing + kitchen tickets) both need:
	//
	//   fraction — the generic SIZE of the coverage ('whole' | 'half' | 'quarter').
	//              This is what the price grid is keyed on.
	//   portion  — WHICH specific portion the topping sits on
	//              ('half-left', 'quarter-top-right', …). The kitchen needs
	//              this to know *where* on the pie the topping goes.
	//
	// Historically only the fraction survived to the order, so a "half"
	// topping never recorded whether it was the left or right half. This
	// helper lets every template emit both consistently. Exposed on window
	// so each template IIFE can normalise without duplicating the maps.
	var COVERAGE_MAP = {
		'whole':                { fraction: 'whole',   label: 'Whole' },
		'half-left':            { fraction: 'half',    label: 'Left Half' },
		'half-right':           { fraction: 'half',    label: 'Right Half' },
		'quarter-top-left':     { fraction: 'quarter', label: 'Top-Left Quarter' },
		'quarter-top-right':    { fraction: 'quarter', label: 'Top-Right Quarter' },
		'quarter-bottom-left':  { fraction: 'quarter', label: 'Bottom-Left Quarter' },
		'quarter-bottom-right': { fraction: 'quarter', label: 'Bottom-Right Quarter' }
	};

	// Aliases for the various slug spellings templates have emitted over time.
	var COVERAGE_ALIAS = {
		'halfleft':            'half-left',
		'half_left':           'half-left',
		'halfright':           'half-right',
		'half_right':          'half-right',
		'quartertopleft':      'quarter-top-left',
		'quarter_top_left':    'quarter-top-left',
		'quartertopright':     'quarter-top-right',
		'quarter_top_right':   'quarter-top-right',
		'quarterbottomleft':   'quarter-bottom-left',
		'quarter_bottom_left': 'quarter-bottom-left',
		'quarterbottomright':  'quarter-bottom-right',
		'quarter_bottom_right':'quarter-bottom-right'
	};

	/**
	 * Resolve a raw coverage value to its canonical portion slug.
	 * Bare 'half'/'quarter' (no side) resolve to '' because the specific
	 * portion is unknown — callers fall back to the fraction in that case.
	 */
	function canonicalPortion( raw ) {
		var s = String( raw == null ? '' : raw ).toLowerCase().replace( /\s+/g, '-' );
		if ( COVERAGE_ALIAS[ s ] ) { s = COVERAGE_ALIAS[ s ]; }
		return COVERAGE_MAP[ s ] ? s : '';
	}

	window.PizzaTierCoverage = {
		/**
		 * Normalise any raw coverage string to a full descriptor:
		 *   { portion, fraction, label }
		 * - portion : canonical specific slug, or '' when only a bare fraction is known
		 * - fraction: generic 'whole' | 'half' | 'quarter' (price-grid key)
		 * - label   : human-readable portion, e.g. 'Left Half'
		 */
		normalize: function ( raw ) {
			var portion = canonicalPortion( raw );
			if ( portion ) {
				return { portion: portion, fraction: COVERAGE_MAP[ portion ].fraction, label: COVERAGE_MAP[ portion ].label };
			}
			// No specific portion — derive the bare fraction from the prefix.
			var s = String( raw == null ? 'whole' : raw ).toLowerCase();
			var frac = s.indexOf( 'quarter' ) === 0 ? 'quarter'
			         : s.indexOf( 'half' )    === 0 ? 'half'
			         : 'whole';
			var lbl = frac.charAt( 0 ).toUpperCase() + frac.slice( 1 );
			return { portion: '', fraction: frac, label: lbl };
		},
		portion:  function ( raw ) { return this.normalize( raw ).portion; },
		fraction: function ( raw ) { return this.normalize( raw ).fraction; },
		label:    function ( raw ) { return this.normalize( raw ).label; }
	};

	// ── Internal helpers ──────────────────────────────────────────────

	function convertToSlug( text ) {
		return text.toLowerCase()
			.replace( / /g, '-' )
			.replace( /[^\w-]+/g, '' );
	}

	function UnderMaxToppings( currentCount ) {
		var max = $( '#MaxToppings' ).val();
		if ( ! max ) { max = 9999; }
		if ( currentCount < max ) {
			$( '#pizzatier-alert' ).fadeOut( 500 );
			return true;
		} else {
			$( '#pizzatier-alert' ).fadeIn( 500 );
			return false;
		}
	}

	// ── Layer management (called from legacy template PHP via window.*) ─

	function SwapPizzaTier( targetLayer, name, imageUrl ) {
		$( '#' + targetLayer ).fadeOut( 100 ).attr( 'src', imageUrl ).fadeIn( 600 );
	}

	function AddPizzaTier( zIndex, shortSlug, imageUrl, alt, layerName, menuItemId ) {
		if ( $( '#' + layerName ).length ) { return false; }
		var currentCount = parseInt( $( '#CurrentToppingsCount' ).val(), 10 );
		if ( ! UnderMaxToppings( currentCount ) ) {
			$( '#pizzatier-ui-menu-section-toppings' ).css( 'outline', '2px solid red' );
			setTimeout( function () {
				$( '#pizzatier-ui-menu-section-toppings' ).css( 'outline', '' );
			}, 600 );
			return false;
		}
		var layerHtml = '<div id="' + layerName + '" class="pizzatier-topping ' + layerName +
			'" style="z-index:' + zIndex + ';"><img title="' + alt + '" alt="' + alt +
			'" src="' + imageUrl + '" onload="jQuery(this).hide().fadeIn(1300);"></div>';
		var liHtml = '<li id="current-topping-' + layerName + '" class="pizza-topping-li-' + zIndex + '">' +
			alt + '<a href="javascript:window.RemovePizzaTier(\'' + layerName + '\',\'' +
			zIndex + '\',\'' + shortSlug + '\');" class="topping-list-remove-button">' +
			'<i class="fa fa-solid fa-trash"></i></a></li>';
		$( '#pizzatier-toppings-wrapper' ).delay( 301 ).append( layerHtml );
		$( '#pizzatier-current-toppings' ).delay( 20 ).append( liHtml ).delay( 20 ).fadeIn( 400 );
		$( '#menu-pizzatier-topping-' + shortSlug ).addClass( 'ToppingSelected' );
		$( '#' + layerName ).removeClass( 'tcg-half-left tcg-half-right tcg-whole tcg-quarter-topleft tcg-quarter-topright tcg-quarter-bottomleft tcg-quarter-bottomright' );
		var coverage = $( "input[type='radio']:checked", '#pztp-topcoverage-control-' + shortSlug ).val();
		$( '#' + layerName ).addClass( 'tcg-' + coverage );
		$( '#CurrentToppingsCount' ).val( currentCount + 1 );
	}

	function RemovePizzaTier( layerName, zIndex, shortSlug ) {
		$( '.' + layerName ).fadeOut( 1200 ).remove();
		$( 'li#current-topping-' + layerName ).fadeOut( 900 ).remove();
		$( '.pizza-topping-li-' + shortSlug ).fadeOut( 600 ).remove();
		$( '#menu-pizzatier-topping-' + shortSlug ).removeClass( 'ToppingSelected' );
		var currentCount = parseInt( $( '#CurrentToppingsCount' ).val(), 10 ) - 1;
		$( '#CurrentToppingsCount' ).val( currentCount );
		var max = $( '#MaxToppings' ).val();
		if ( currentCount < max ) {
			$( '#pizzatier-alert' ).fadeOut( 500 );
		} else {
			$( '#pizzatier-alert' ).fadeIn( 500 );
		}
	}

	function RemoveAllToppings() {
		$( '.pizzatier-topping' ).fadeOut( 600 ).remove();
		$( '#CurrentToppingsCount' ).val( 0 );
	}

	function SwapBasePizzaTier( targetLayer, name, imageUrl ) {
		var wrapped   = 'url(' + imageUrl + ')';
		var titleId   = targetLayer.replace( 'pizzatier-base-layer-', 'pizzatier-basics-tile-title-' );
		var typeSlug  = targetLayer.replace( 'pizzatier-base-layer-', '' );
		$( '#' + targetLayer ).fadeOut( 100 ).delay( 20 ).css( 'backgroundImage', wrapped ).delay( 20 ).fadeIn( 900 );
		$( '#' + titleId ).html( name );
		var newShort = 'menu-pizzatier-topping-' + convertToSlug( name );
		$( '.pizzatier-' + typeSlug + 's-list li' ).removeClass( 'ToppingSelected' );
		$( '#' + newShort ).addClass( 'ToppingSelected' );
	}

	function ChangeSlicing( targetLayer, name, imageUrl ) {
		var wrapped = 'url(' + imageUrl + ')';
		$( '#' + targetLayer ).fadeOut( 100 ).css( 'backgroundImage', wrapped ).fadeIn( 400 );
		$( '#' + targetLayer ).parent().append( $( '#' + targetLayer ) );
	}

	function SetToppingCoverage( area, toppingId, toppingShort ) {
		$( '#' + toppingId ).removeClass( 'tcg-half-left tcg-half-right tcg-whole tcg-quarter-top-left tcg-quarter-top-right tcg-quarter-bottom-left tcg-quarter-bottom-right' );
		$( '#' + toppingId ).addClass( 'tcg-' + area );
		var toppingShortSlug = toppingId.replace( 'pizzatier-topping-', '' );
		var radioId          = 'halfcontrol-' + toppingShortSlug + '-' + area;
		$( '#pizzatier-halves-control-halfcontrol-' + toppingShortSlug + ' img.pizzatier-halves-control' )
			.removeClass( 'pizzatier-halves-control-highlighted' );
		$( '#pizzatier-halves-control-halfcontrol-' + toppingShortSlug + ' img.pizzatier-halves-control-' + area )
			.addClass( 'pizzatier-halves-control-highlighted' );
		var areaNoQuarter = area.replace( 'quarter-', '' );
		var imgSrc = $( '#topping-' + toppingShort + '-halves-control-button-' + areaNoQuarter ).attr( 'src' );
		$( '#topping-fraction-thumb-' + toppingShort ).attr( 'src', imgSrc );
		$( '#' + radioId )[ 0 ].checked = true;
	}

	function OpenToppingFractionBox( toppingId ) {
		$( '#pizzatier-halves-control-halfcontrol-' + toppingId ).fadeOut( 999 );
		$( '#pizzatier-halves-control-fraction-' + toppingId ).fadeIn( 1200 );
	}

	function CloseToppingFractionBox( toppingId ) {
		$( '#pizzatier-halves-control-halfcontrol-' + toppingId ).fadeIn( 999 );
		$( '#pizzatier-halves-control-fraction-' + toppingId ).fadeOut( 1200 );
	}

	// ── Pizza Rotation ────────────────────────────────────────────────
	/*
	 * Usage:
	 *   window.RotatePizza( 'myPizzaDiv', 2 );  // faster
	 *   window.RotatePizza( 'myPizzaDiv', 0.5 ); // slower
	 *   window.StopPizza( 'myPizzaDiv' );
	 */
	function RotatePizza( divId, speed ) {
		if ( speed === undefined ) { speed = 1; }
		var el = document.getElementById( divId );
		if ( ! el ) {
			window.console && window.console.error( 'PizzaTier: RotatePizza — element not found:', divId );
			return;
		}
		var angle = 0;
		function rotate() {
			angle = ( angle + speed ) % 360;
			el.style.transform = 'rotate(' + angle + 'deg)';
			rotationIntervals[ divId ] = requestAnimationFrame( rotate );
		}
		rotate();
	}

	function StopPizza( divId ) {
		if ( rotationIntervals[ divId ] ) {
			cancelAnimationFrame( rotationIntervals[ divId ] );
			delete rotationIntervals[ divId ];
		}
	}

	// ── Global exports ────────────────────────────────────────────────
	// Only these three are exposed because template PHP renders them in onclick= attributes.
	// All other functions remain private to this IIFE.
	window.ClearPizza = function () {
		$( '#pizzatier-pizza .pizzatier-sauce,' +
			'#pizzatier-pizza .pizzatier-cheese,' +
			'#pizzatier-pizza .pizzatier-drizzle,' +
			'#pizzatier-pizza .pizzatier-cut' ).css( { background: 'none' } );
		$( '#pizzatier-pizza .pizzatier-topping' ).fadeOut( 900 ).remove();
		$( '#pizzatier-current-toppings *' ).fadeOut( 600 ).remove();
		$( '.pizzatier-toppings-list-linkboxes .pizza-topping,' +
			'.pizzatier-ui-menu-tab .pizzatier-topping,' +
			'.pizzatier-inner-tile' ).removeClass( 'ToppingSelected' );
		$( '#pizzatier-basics-tile-title-crust' ).html( 'No Crust Chosen' );
		$( '#pizzatier-basics-tile-title-sauce' ).html( 'No Sauce Chosen' );
		$( '#pizzatier-basics-tile-title-cheese' ).html( 'No Cheese Chosen' );
		$( '#pizzatier-basics-tile-title-drizzle' ).html( 'No Drizzle Chosen' );
		$( '#CurrentToppingsCount' ).val( 0 );
	};
	window.RotatePizza = RotatePizza;
	window.StopPizza   = StopPizza;
	// Legacy compatibility: also expose via window for any external code calling these directly.
	// These should not be relied on in new integrations.
	window.RemovePizzaTier       = RemovePizzaTier;
	window.AddPizzaTier          = AddPizzaTier;
	window.SwapPizzaTier         = SwapPizzaTier;
	window.SwapBasePizzaTier     = SwapBasePizzaTier;
	window.ChangeSlicing          = ChangeSlicing;
	window.SetToppingCoverage     = SetToppingCoverage;
	window.OpenToppingFractionBox = OpenToppingFractionBox;
	window.CloseToppingFractionBox= CloseToppingFractionBox;
	window.RemoveAllToppings      = RemoveAllToppings;

} )( jQuery );

