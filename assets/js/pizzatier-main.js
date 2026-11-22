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
 *
 * It also defines window.PizzaTierAPI — a shared registry/facade that every
 * template's custom.js registers with (see "Shared PizzaTierAPI registry").
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
		// Calling RotatePizza twice used to start a second rAF loop that
		// StopPizza() could never cancel (only the latest id was tracked).
		StopPizza( divId );
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

	// ── Shared PizzaTierAPI registry (facade) ─────────────────────────
	//
	// Every template's custom.js used to assign its own object to
	// window.PizzaTierAPI, so two different templates on one page clobbered
	// each other (the last one loaded won, and the other builder's state could
	// no longer be read or restored). This file is a script dependency of every
	// template's custom.js, so it defines a single facade here. Templates call
	// PizzaTierAPI.register( theirApi ) (or registerInstance( id, inst ) for
	// templates that register instance objects directly), and the facade routes
	// getState(id) / setState(id, s) to whichever template owns that id.
	//
	// The methods are plain functions on the facade object so wrappers (e.g.
	// topping-multiplier.js wrapping getState) keep working.
	function isFn( f ) { return typeof f === 'function'; }

	function hasOwn( o, k ) { return Object.prototype.hasOwnProperty.call( o, k ); }

	function restBase() {
		return ( window.wpApiSettings && window.wpApiSettings.root ) ? window.wpApiSettings.root : '/wp-json/';
	}

	function createFacade() {
		var apis = [];
		var own  = {};   // id → instance object (registerInstance)

		function apiIds( api ) {
			var ids = [];
			try {
				if ( isFn( api.getAllInstances ) ) {
					ids = api.getAllInstances() || [];
				} else if ( isFn( api.getInstances ) ) {
					ids = Object.keys( api.getInstances() || {} );
				}
			} catch ( e ) { ids = []; }
			return ids;
		}

		function apiOwns( api, id ) {
			return apiIds( api ).indexOf( id ) !== -1;
		}

		function ownerApi( id ) {
			for ( var i = 0; i < apis.length; i++ ) {
				if ( apiOwns( apis[ i ], id ) ) { return apis[ i ]; }
			}
			return null;
		}

		var facade = {
			__pztFacade: true,
			_apis:       apis,
			_instances:  own,

			/** Register a template-level API object ({getState, setState, getAllInstances, …}). */
			register: function ( api ) {
				if ( ! api || api === facade || typeof api !== 'object' ) { return facade; }
				if ( apis.indexOf( api ) === -1 ) { apis.push( api ); }
				return facade;
			},

			/** Register a single builder instance object (must expose getState / setState). */
			registerInstance: function ( id, inst ) {
				if ( id && inst ) { own[ String( id ) ] = inst; }
			},

			getInstance: function ( id ) {
				if ( ! id ) { return null; }
				if ( hasOwn( own, id ) ) { return own[ id ]; }
				for ( var i = 0; i < apis.length; i++ ) {
					var api = apis[ i ], inst = null;
					try {
						if ( isFn( api.getInstance ) ) { inst = api.getInstance( id ); }
						if ( ! inst && isFn( api.getInstances ) ) {
							var map = api.getInstances() || {};
							inst = hasOwn( map, id ) ? map[ id ] : null;
						}
					} catch ( e ) { inst = null; }
					if ( inst ) { return inst; }
				}
				return null;
			},

			/** id → instance object map (only templates that expose instance objects). */
			getInstances: function () {
				var out = {}, k;
				for ( var i = 0; i < apis.length; i++ ) {
					if ( ! isFn( apis[ i ].getInstances ) ) { continue; }
					var map = null;
					try { map = apis[ i ].getInstances(); } catch ( e ) { map = null; }
					if ( map && typeof map === 'object' ) {
						for ( k in map ) { if ( hasOwn( map, k ) && ! hasOwn( out, k ) ) { out[ k ] = map[ k ]; } }
					}
				}
				for ( k in own ) { if ( hasOwn( own, k ) ) { out[ k ] = own[ k ]; } }
				return out;
			},

			/** Every registered instance id, across all templates on the page. */
			getAllInstances: function () {
				var ids = [], k;
				for ( k in own ) { if ( hasOwn( own, k ) ) { ids.push( k ); } }
				for ( var i = 0; i < apis.length; i++ ) {
					var list = apiIds( apis[ i ] );
					for ( var j = 0; j < list.length; j++ ) {
						if ( ids.indexOf( list[ j ] ) === -1 ) { ids.push( list[ j ] ); }
					}
				}
				return ids;
			},

			getState: function ( id ) {
				var i, s = null;
				if ( id === undefined || id === null || id === '' ) {
					// Bare getState(): the single builder on the page, if there is exactly one.
					var ids = facade.getAllInstances();
					if ( ids.length === 1 ) { return facade.getState( ids[ 0 ] ); }
					if ( ids.length === 0 && apis.length === 1 && isFn( apis[ 0 ].getState ) ) {
						try { return apis[ 0 ].getState(); } catch ( e ) { return null; }
					}
					return null;
				}
				id = String( id );
				if ( hasOwn( own, id ) ) {
					return ( own[ id ] && isFn( own[ id ].getState ) ) ? own[ id ].getState() : null;
				}
				var owner = ownerApi( id );
				if ( owner && isFn( owner.getState ) ) { return owner.getState( id ); }
				for ( i = 0; i < apis.length; i++ ) {
					if ( ! isFn( apis[ i ].getState ) ) { continue; }
					try { s = apis[ i ].getState( id ); } catch ( e ) { s = null; }
					if ( s ) { return s; }
				}
				// Legacy (Scaffold): getState( 'SC_varName' ) resolved window[ varName ].
				var g = window[ id ];
				if ( g && typeof g === 'object' && g !== facade && isFn( g.getState ) && ! g.nodeType ) {
					try { return g.getState(); } catch ( e ) { return null; }
				}
				return null;
			},

			setState: function ( id, newState ) {
				// Tolerate the single-argument form setState( state ).
				if ( id && typeof id === 'object' && newState === undefined ) {
					newState = id;
					id = null;
				}
				if ( ! id ) {
					var ids = facade.getAllInstances();
					if ( ids.length !== 1 ) { return; }
					id = ids[ 0 ];
				}
				id = String( id );
				if ( hasOwn( own, id ) ) {
					if ( own[ id ] && isFn( own[ id ].setState ) ) { own[ id ].setState( newState ); }
					return;
				}
				var owner = ownerApi( id );
				if ( owner && isFn( owner.setState ) ) { owner.setState( id, newState ); return; }
				var inst = facade.getInstance( id );
				if ( inst && isFn( inst.setState ) ) { inst.setState( newState ); return; }
				var g = window[ id ];
				if ( g && typeof g === 'object' && g !== facade && isFn( g.setState ) && ! g.nodeType ) {
					g.setState( newState );
				}
			},

			renderPizza: function ( layers ) {
				for ( var i = 0; i < apis.length; i++ ) {
					if ( isFn( apis[ i ].renderPizza ) ) { return apis[ i ].renderPizza( layers ); }
				}
				var nonce = ( window.wpApiSettings && window.wpApiSettings.nonce ) ? window.wpApiSettings.nonce : '';
				return fetch( restBase() + 'pizzatier/v1/render', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: JSON.stringify( layers || {} )
				} ).then( function ( r ) { return r.json(); } )
				   .then( function ( d ) { return d.html || ''; } );
			},

			getLayerUrl: function ( type, slug ) {
				for ( var i = 0; i < apis.length; i++ ) {
					if ( isFn( apis[ i ].getLayerUrl ) ) { return apis[ i ].getLayerUrl( type, slug ); }
				}
				return fetch( restBase() + 'pizzatier/v1/layer-url?type=' + encodeURIComponent( type ) + '&slug=' + encodeURIComponent( slug ) )
					.then( function ( r ) { return r.json(); } )
					.then( function ( d ) { return d.url || ''; } );
			},

			renderStatic: function ( selectorOrEl, pizzaState ) {
				for ( var i = 0; i < apis.length; i++ ) {
					if ( isFn( apis[ i ].renderStatic ) ) { return apis[ i ].renderStatic( selectorOrEl, pizzaState ); }
				}
				return undefined;
			}
		};

		return facade;
	}

	if ( ! ( window.PizzaTierAPI && window.PizzaTierAPI.__pztFacade ) ) {
		var pztPrevApi = window.PizzaTierAPI;
		var pztFacade  = createFacade();
		// Something defined an API before us — keep it reachable through the facade.
		if ( pztPrevApi && typeof pztPrevApi === 'object' ) {
			if ( isFn( pztPrevApi.getAllInstances ) || isFn( pztPrevApi.getInstances ) ) {
				pztFacade.register( pztPrevApi );
			}
		}
		window.PizzaTierAPI = pztFacade;
	}

	// ── Shared notice helper (aria-live toast) ────────────────────────
	// Used by templates that previously only fired events nobody listened for
	// (e.g. "maximum toppings reached"). Styled by .pzt-notice in each
	// template's template.css; falls back to sensible defaults.
	window.PizzaTierNotice = window.PizzaTierNotice || {
		show: function ( rootEl, msg, duration ) {
			if ( ! rootEl || ! msg ) { return; }
			var settings = window.pizzatierSettings || {};
			if ( ( settings.toastStyle || '' ) === 'none' ) { return; }
			var dur = parseInt( duration || settings.toastDuration, 10 ) || 3000;
			var el  = rootEl.querySelector( '.pzt-notice' );
			if ( ! el ) {
				el = document.createElement( 'div' );
				el.className = 'pzt-notice';
				el.setAttribute( 'role', 'status' );
				el.setAttribute( 'aria-live', 'polite' );
				rootEl.appendChild( el );
			}
			el.textContent = String( msg );
			el.hidden = false;
			el.classList.add( 'pzt-notice--visible' );
			if ( el._pztTimer ) { clearTimeout( el._pztTimer ); }
			el._pztTimer = setTimeout( function () {
				el.classList.remove( 'pzt-notice--visible' );
				el.hidden = true;
			}, dur );
		},
		maxToppings: function ( rootEl, max ) {
			var settings = window.pizzatierSettings || {};
			var msg = settings.textMaxToppings || ( 'Maximum ' + max + ' toppings reached!' );
			this.show( rootEl, String( msg ).replace( '{max}', String( max ) ) );
		}
	};

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

