/**
 * PizzaTier — Frontend Builder & Live Pricing
 *
 * Supports multiple independent builder instances on a single page.
 * Each .pztc-builder-section[data-pztc-instance] gets its own
 * isolated state object created by initBuilderInstance().
 *
 * Size is always pre-selected to the first available size on load;
 * the size picker recalculates pricing on every change.
 * A "size required" gate overlays the builder when no size grid is
 * configured, showing a clear prompt.
 *
 * Config is read from:
 *   window.pizzatier_commerceFrontendInstances[n]  — per-instance array
 *   window.pizzatier_commerceFrontend              — fallback/compat alias
 *
 * Public API:
 *   window.PizzaTierBuilder          — first/only instance
 *   window.PizzaTierBuilderInstances — all instances
 */

/* global pizzatier_commerceFrontend, pizzatier_commerceFrontendInstances, jQuery */

( function () {
	'use strict';

	// =========================================================================
	// Boot
	// =========================================================================

	document.addEventListener( 'DOMContentLoaded', function () {
		const sections = document.querySelectorAll( '.pztc-builder-section[data-pztc-instance]' );
		window.PizzaTierBuilderInstances = [];

		if ( sections.length === 0 ) {
			const api = initBuilderInstance( null, window.pizzatier_commerceFrontend || {} );
			window.PizzaTierBuilderInstances.push( api );
			window.PizzaTierBuilder = api;
			applyUrlPrefill();
			return;
		}

		const instanceConfigs = window.pizzatier_commerceFrontendInstances || [];
		sections.forEach( function ( section, i ) {
			const idx = parseInt( section.dataset.pizzatier_commerceInstance, 10 ) || ( i + 1 );
			const cfg = instanceConfigs[i] || instanceConfigs[0] || window.pizzatier_commerceFrontend || {};
			const api = initBuilderInstance( idx, cfg );
			window.PizzaTierBuilderInstances.push( api );
		} );
		window.PizzaTierBuilder = window.PizzaTierBuilderInstances[0];

		applyUrlPrefill();
	} );

	// =========================================================================
	// Per-instance factory
	// =========================================================================

	function initBuilderInstance( idx, CFG ) {
		CFG = CFG || {};
		const GRID          = CFG.priceGrid   || { sizes: [], fractions: [], cells: {} };
		const FLAT_GRID     = CFG.flatGrid    || { layer_types: [], sizes: [], cells: {} };
		// Per-layer price grids: { postId(string): { sizes, fractions, cells } }
		const LAYER_GRIDS   = CFG.layerGrids  || {};
		// Slug → postId map for resolving layerPostId from a layer slug
		const POST_ID_MAP   = CFG.layerPostIdMap || {};
		// Layer types that use the fraction grid (cheese/sauce/drizzle);
		// all others use the flat per-size grid.
		// all others use the flat per-size grid.
		const FRACTION_TYPES = ['cheese','sauce','drizzle'];

		// DOM helpers — prefer instance-suffixed IDs, fall back to bare IDs
		function $id( base ) {
			const el = idx !== null ? document.getElementById( base + '-' + idx ) : null;
			return el || document.getElementById( base );
		}
		function $section() {
			return idx !== null
				? document.querySelector( '.pztc-builder-section[data-pztc-instance="' + idx + '"]' )
				: document.querySelector( '.pztc-builder-section' );
		}

		// ─── Size state ────────────────────────────────────────────────────────
		// Always boot with the first grid size so pricing is immediately valid.
		// The size selector pills have the first one pre-checked via PHP; we
		// mirror that in JS state here so recalculate() never sees an empty size.
		let activeSize = CFG.defaultSize || ( GRID.sizes && GRID.sizes[0] ) || '';

		// sizeChosen: true once a valid size is active (pre-selected first size
		// counts — the "Select a size" prompt only applies when the grid has NO
		// sizes at all). Set to true whenever at least one size is configured so
		// pricing displays immediately on load without requiring a user tap.
		let sizeChosen = ( GRID.sizes && GRID.sizes.length >= 1 );

		// ─── Active layers ─────────────────────────────────────────────────────
		const activeLayers = {};

		// ─── Size selector ─────────────────────────────────────────────────────
		// Handles both the top step-header pills AND the inline bar chips.
		// All share the same radio name (pizzatier_commerce_size_{idx}) and class (pztc-size-radio).
		( function initSizeSelector() {
			var radioName = 'pizzatier_commerce_size_' + ( idx !== null ? idx : '1' );

			// Collect ALL size radio inputs for this instance (top selector + bar chips).
			function allRadios() {
				return Array.prototype.slice.call(
					document.querySelectorAll( 'input.pztc-size-radio[name="' + radioName + '"]' )
				);
			}

			// Update active classes on ALL label wrappers (.pztc-size-option, .pztc-bar-chip).
			function syncActiveClasses( chosenValue ) {
				allRadios().forEach( function ( r ) {
					var lbl = r.closest( '.pztc-size-option, .pztc-bar-chip' );
					if ( lbl ) {
						var isActive = ( r.value === chosenValue );
						lbl.classList.toggle( 'pztc-size-option--active', isActive );
						lbl.classList.toggle( 'pztc-bar-chip--active', isActive );
					}
				} );
			}

			// Apply active class from currently checked radio on load.
			var initialRadio = allRadios().find( function(r){ return r.checked; } )
			                || allRadios()[0];
			if ( initialRadio ) {
				if ( ! initialRadio.checked ) initialRadio.checked = true;
				syncActiveClasses( initialRadio.value );
			}

			// Central handler — fires on any pztc-size-radio change anywhere on page.
			function onSizeChange( chosenRadio ) {
				if ( ! chosenRadio ) return;
				sizeChosen = true;
				activeSize = chosenRadio.value;
				// Sync the checked state across all radios with the same name.
				allRadios().forEach( function(r){ r.checked = ( r.value === activeSize ); } );
				syncActiveClasses( activeSize );
				recalculate();
			}

			// Use document-level delegation so dynamically-rendered bar chips are caught.
			document.addEventListener( 'change', function ( e ) {
				if (
					e.target &&
					e.target.classList.contains( 'pztc-size-radio' ) &&
					e.target.name === radioName
				) {
					onSizeChange( e.target );
				}
			} );

			// Click delegation — catches label clicks before change fires on some devices.
			document.addEventListener( 'click', function ( e ) {
				var lbl = e.target.closest( '.pztc-size-option, .pztc-bar-chip' );
				if ( ! lbl ) return;
				var radio = lbl.querySelector( 'input.pztc-size-radio[name="' + radioName + '"]' );
				if ( radio && radio.value !== activeSize ) {
					radio.checked = true;
					onSizeChange( radio );
				} else if ( radio && ! sizeChosen ) {
					// Already the right size but user tapped it — mark as explicitly chosen.
					sizeChosen = true;
					syncActiveClasses( radio.value );
					recalculate();
				}
			} );

			// Handle native <select> for dropdown style.
			const $nativeSel = idx !== null
				? document.getElementById( 'pztc-size-native-' + idx )
				: document.getElementById( 'pztc-size-native' );
			if ( $nativeSel ) {
				if ( $nativeSel.value ) activeSize = $nativeSel.value;
				$nativeSel.addEventListener( 'change', function () {
					activeSize = $nativeSel.value;
					allRadios().forEach( function(r){ r.checked = ( r.value === activeSize ); } );
					syncActiveClasses( activeSize );
					sizeChosen = true;
					recalculate();
				} );
			}
		}() );

		// ─── PizzaTier event integration ──────────────────────────────────────
		( function () {
			function add( lid, frac, ld ) {
				var nfrac = normaliseFraction( frac );
				var nld   = Object.assign( {}, ld || {} );
				if ( ! nld.type && ! nld.layerType ) { nld.type = resolveLayerType( lid, nld ); }
				if ( nld.portion === undefined ) {
					var pa = resolvePortion( nld.coverage || frac );
					nld.portion = pa.portion;
					if ( ! nld.portionLabel ) { nld.portionLabel = nld.coverageLabel || pa.label; }
				}
				registerLayer( String(lid||''), nfrac, nld );
				recalculate();
			}
			function rem( lid ) { removeLayer( String(lid||'') ); recalculate(); }
			function upd( lid, frac, ld ) {
				const l = String(lid||'');
				var nfrac = normaliseFraction( frac );
				var nld   = Object.assign( {}, ld || {} );
				if ( ! nld.type && ! nld.layerType ) { nld.type = resolveLayerType( l, nld ); }
				if ( nld.portion === undefined ) {
					var pu = resolvePortion( nld.coverage || frac );
					nld.portion = pu.portion;
					if ( ! nld.portionLabel ) { nld.portionLabel = nld.coverageLabel || pu.label; }
				}
				if ( l && activeLayers[l] ) {
					activeLayers[l].fraction  = nfrac || activeLayers[l].fraction;
					activeLayers[l].layerData = nld   || activeLayers[l].layerData;
				} else { registerLayer( l, nfrac || 'Whole', nld ); }
				recalculate();
			}
			function ready() {
				( CFG.preselectedLayers || [] ).forEach( function(l){
					if (!activeLayers[l]) {
						registerLayer( String(l), normaliseFraction('Whole'), { type: resolveLayerType(l, {}) } );
					}
				} );
				applyDefaultLayers();
				recalculate();
			}

			function applyDefaultLayers() {
				var defaults = CFG.defaultLayers;
				if ( ! defaults || typeof defaults !== 'object' ) { return; }
				if ( Object.keys( defaults ).length === 0 ) { return; }

				var state = {};
				var baseTypes = ['crust', 'sauce', 'cheese', 'drizzle', 'cut'];
				baseTypes.forEach( function( type ) {
					if ( defaults[type] ) {
						state[type] = { slug: String(defaults[type]) };
					}
				} );

				if ( Array.isArray( defaults.toppings ) ) {
					state.toppings = {};
					defaults.toppings.forEach( function(slug) {
						state.toppings[String(slug)] = { slug: String(slug) };
					} );
				}

				if ( Object.keys(state).length === 0 ) { return; }

				var instanceId  = idx ? 'pztc-' + idx : null;
				var pollCount   = 0;
				var maxPolls    = 63;

				function tryApply() {
					pollCount++;
					var api = window.PizzaTierAPI;
					if ( ! api || typeof api.setState !== 'function' ) {
						if ( pollCount < maxPolls ) { setTimeout( tryApply, 80 ); }
						return;
					}
					if ( instanceId && typeof api.getAllInstances === 'function' ) {
						var registered = api.getAllInstances();
						if ( registered.indexOf( instanceId ) === -1 ) {
							if ( pollCount < maxPolls ) { setTimeout( tryApply, 80 ); }
							return;
						}
					}
					if ( instanceId && typeof api.setState === 'function' ) {
						try {
							api.setState( instanceId, state );
						} catch(e) {
							try { api.setState( state ); } catch(e2) { /* silent */ }
						}
					} else {
						try { api.setState( state ); } catch(e) { /* silent */ }
					}
					recalculate();
				}

				setTimeout( tryApply, 50 );
			}

			// ── Event listeners (templates that DO fire events) ───────────────────
			document.addEventListener( 'pizzatier:layer:add',    function(e){ if(e.detail) add( e.detail.layerId, e.detail.fraction, e.detail.layerData ); } );
			document.addEventListener( 'pizzatier:layer:remove', function(e){ if(e.detail) rem( e.detail.layerId ); } );
			document.addEventListener( 'pizzatier:layer:update', function(e){ if(e.detail) upd( e.detail.layerId, e.detail.fraction, e.detail.layerData ); } );
			document.addEventListener( 'pizzatier:ready',        function(){ ready(); } );

			const $embed = $id( 'pztc-builder-embed' );
			if ( $embed ) {
				$embed.addEventListener( 'pizzatier:layer:add',    function(e){ if(e.detail) add( e.detail.layerId, e.detail.fraction, e.detail.layerData ); } );
				$embed.addEventListener( 'pizzatier:layer:remove', function(e){ if(e.detail) rem( e.detail.layerId ); } );
				$embed.addEventListener( 'pizzatier:layer:update', function(e){ if(e.detail) upd( e.detail.layerId, e.detail.fraction, e.detail.layerData ); } );
			}

			if ( typeof window.jQuery !== 'undefined' ) {
				jQuery(document)
					.on( 'pizzatier:layer:add',    function(e,d){ const dt=d||(e&&e.detail)||{}; if(dt.layerId) add(dt.layerId,dt.fraction,dt.layerData); } )
					.on( 'pizzatier:layer:remove', function(e,d){ const dt=d||(e&&e.detail)||{}; if(dt.layerId) rem(dt.layerId); } )
					.on( 'pizzatier:layer:update', function(e,d){ const dt=d||(e&&e.detail)||{}; if(dt.layerId) upd(dt.layerId,dt.fraction,dt.layerData); } )
					.on( 'pizzatier:ready',        function(){ ready(); } );
			}

			if ( typeof window.PizzaTier !== 'undefined' && typeof window.PizzaTier.onLayerChange === 'function' ) {
				window.PizzaTier.onLayerChange( function(ev){
					if(!ev) return;
					if ( ev.action==='add'||ev.action==='change'||ev.action==='update' ) add( ev.layerId, ev.fraction, ev.layerData );
					else if ( ev.action==='remove' ) rem( ev.layerId );
				} );
			}

			// ── Universal state polling for templates that don't fire events ───
			// Most PizzaTier templates (colorbox, pocketpie, commandcenter) update
			// window.PizzaTierAPI state internally without dispatching DOM events.
			// We poll for state changes at 120ms intervals and trigger recalculate()
			// whenever the layer composition or size changes.
			( function startPolling() {
				var lastHash = '';

				function snapshotHash() {
					var api = window.PizzaTierAPI;
					if ( !api ) return '';
					// Try all registered instances and take the first one that returns state.
					var state = null;
					// Try getInstances() (commandcenter pattern)
					if ( typeof api.getInstances === 'function' ) {
						var insts = api.getInstances();
						for ( var k in insts ) {
							if ( Object.prototype.hasOwnProperty.call( insts, k ) ) {
								try { state = insts[k].getState ? insts[k].getState() : null; } catch(e){}
								if ( state ) break;
							}
						}
					}
					// Try getAllInstances() (colorbox pattern)
					if ( !state && typeof api.getAllInstances === 'function' ) {
						var keys = api.getAllInstances();
						for ( var i = 0; i < keys.length; i++ ) {
							try { state = api.getState( keys[i] ); } catch(e){}
							if ( state ) break;
						}
					}
					// Direct single-instance getState (pocketpie pattern)
					if ( !state && typeof api.getState === 'function' ) {
						try { state = api.getState(); } catch(e){}
					}
					if ( !state ) return '';
					// Build a lightweight fingerprint of current layers + size.
					// Colorbox/pocketpie state: { crust, sauce, cheese, drizzle, cut, toppings:{} }
					// Commandcenter state may use layers:[] array.
					var parts = [];
					var layerTypes = ['crust','sauce','cheese','drizzle','cut'];
					for ( var t = 0; t < layerTypes.length; t++ ) {
						var lt = layerTypes[t];
						if ( state[lt] ) parts.push( lt + ':' + (state[lt].slug||state[lt].id||'') );
					}
					if ( state.toppings ) {
						var tkeys = Object.keys( state.toppings ).sort();
						for ( var j = 0; j < tkeys.length; j++ ) {
							var tk = tkeys[j];
							parts.push( 'top:' + tk + ':' + (state.toppings[tk].coverage||'whole') );
						}
					}
					if ( Array.isArray( state.layers ) ) {
						var sl = state.layers.slice().sort(function(a,b){ return (a.id||a.layerId||'') < (b.id||b.layerId||'') ? -1 : 1; });
						for ( var m = 0; m < sl.length; m++ ) {
							parts.push( 'lay:' + (sl[m].id||sl[m].layerId||'') + ':' + (sl[m].fraction||sl[m].coverage||'whole') );
						}
					}
					// Also include active size from the Pro size selector.
					parts.push( 'sz:' + activeSize );
					return parts.join('|');
				}

				function poll() {
					var h = snapshotHash();
					if ( h !== lastHash ) {
						lastHash = h;
						recalculate();
					}
					setTimeout( poll, 120 );
				}

				// Start polling after a short delay so the template has time to init.
				setTimeout( poll, 400 );
			}() );

			applyDefaultLayers();
		}() );

		// Trigger an initial price calculation on load so the bar always shows
		// the base price immediately (even before any layer is selected).
		recalculate();

		// Second pass after paint — catches checkout bars that were injected
		// asynchronously (e.g. dynamically appended by a template script) and
		// therefore weren't in the DOM when the first recalculate ran.
		if ( typeof requestAnimationFrame === 'function' ) {
			requestAnimationFrame( function () {
				recalculate();
			} );
		}

		// Layer state
		function registerLayer( lid, frac, ld ) { if(lid) activeLayers[lid] = { fraction:frac, layerData:ld, postId: resolvePostId(lid) }; }
		function removeLayer( lid )              { if(lid && Object.prototype.hasOwnProperty.call(activeLayers,lid)) delete activeLayers[lid]; }

		// =====================================================================
		// Pricing engine
		// =====================================================================

		const NON_TOPPING_TYPES = ['crust','sauce','cheese','drizzle','cut'];
		function isNT(t) { return NON_TOPPING_TYPES.indexOf((t||'').toLowerCase()) > -1; }

		function applyRounding(v) {
			const m = CFG.priceRounding||'';
			if (m==='up')        return Math.ceil(v*100)/100;
			if (m==='nearest5')  return Math.round(v/0.05)*0.05;
			if (m==='nearest25') return Math.round(v/0.25)*0.25;
			return parseFloat(v.toFixed(parseInt(CFG.decimals,10)||2));
		}

		function gridPrice(size,frac) {
			const k=size+'|'+frac, c=GRID.cells||{};
			return Object.prototype.hasOwnProperty.call(c,k) ? parseFloat(c[k]) : null;
		}

		/**
		 * Unified price lookup for a single layer — mirrors PHP Grid::get_layer_price().
		 *
		 * Resolution order:
		 *  1. Layer's own grid (LAYER_GRIDS[String(postId)])
		 *  2. Product-level grid (GRID.cells)
		 *
		 * @param {number|string} postId  CPT post ID (0 = no custom grid).
		 * @param {string}        size    Size label, e.g. 'Large'.
		 * @param {string}        frac    Coverage label, e.g. 'Half'.
		 * @returns {number|null}  Price, or null if missing from both grids.
		 */
		function getLayerPrice(postId, size, frac) {
			const key = size + '|' + frac;
			if ( postId ) {
				const lg = LAYER_GRIDS[ String(postId) ];
				if ( lg && lg.cells && Object.prototype.hasOwnProperty.call(lg.cells, key) ) {
					return parseFloat( lg.cells[key] );
				}
				// Layer has a grid but this cell is missing — fall through to product grid.
			}
			// Fall back to product-level grid.
			return gridPrice(size, frac);
		}

		/**
		 * Resolve the CPT post ID for a layer slug.
		 * Returns 0 if not found (layer will use product-level fallback grid).
		 *
		 * @param {string} slug  Layer slug, e.g. 'pepperoni'.
		 * @returns {number}
		 */
		function resolvePostId(slug) {
			return parseInt( POST_ID_MAP[ slug ] || 0, 10 ) || 0;
		}

		/**
		 * Normalise a template coverage string to the nearest grid fraction label.
		 *
		 * The base plugin uses internal slugs ('whole', 'half-left', 'half-right',
		 * 'quarter-top-left', …) while the price grid columns are admin-defined
		 * labels ('Whole', 'Half', 'Quarter', …).  This function maps the former
		 * to the latter so gridPrice() always finds a matching cell.
		 *
		 * Strategy:
		 *  1. Exact case-insensitive match against known grid fractions.
		 *  2. Prefix match: 'half*' → first grid fraction whose lower-case name
		 *     starts with 'half', 'quarter*' → 'quarter', etc.
		 *  3. Fallback to the first grid fraction (usually 'Whole').
		 *
		 * @param {string} frac  Coverage string from the template event.
		 * @returns {string}     Matching grid fraction label.
		 */
		function normaliseFraction(frac) {
			var fractions = (GRID.fractions && GRID.fractions.length) ? GRID.fractions : ['Whole'];
			var lower = String(frac||'').toLowerCase().replace(/[\s_]+/g,'-');

			// 1. Exact case-insensitive match.
			for (var i=0; i<fractions.length; i++) {
				if (fractions[i].toLowerCase() === lower) return fractions[i];
			}

			// 2. Prefix match: extract the base word ('whole','half','quarter','third').
			var prefixes = ['whole','half','quarter','third','full'];
			for (var p=0; p<prefixes.length; p++) {
				if (lower.indexOf(prefixes[p]) !== -1) {
					for (var j=0; j<fractions.length; j++) {
						if (fractions[j].toLowerCase().indexOf(prefixes[p]) !== -1) {
							return fractions[j];
						}
					}
				}
			}

			// 3. Fallback to first fraction.
			return fractions[0];
		}

		/**
		 * Resolve a raw coverage string to its specific portion descriptor:
		 *   { portion, label }
		 *
		 * Unlike normaliseFraction() (which collapses to the price-grid SIZE),
		 * this preserves WHICH portion the topping sits on so it can travel all
		 * the way to the cart, order and kitchen ticket. Prefers the base
		 * plugin's shared PizzaTierCoverage helper; falls back to a local map
		 * if the base script isn't present.
		 *
		 * Returns portion:'' when only a bare fraction (no side) is known, so
		 * callers can fall back to the fraction label.
		 *
		 * @param {string} raw  Coverage string from the template state.
		 * @returns {{portion:string,label:string}}
		 */
		function resolvePortion(raw) {
			if ( window.PizzaTierCoverage && typeof window.PizzaTierCoverage.normalize === 'function' ) {
				var n = window.PizzaTierCoverage.normalize( raw );
				return { portion: n.portion || '', label: n.label || '' };
			}
			var MAP = {
				'whole':                'Whole',
				'half-left':            'Left Half',
				'half-right':           'Right Half',
				'quarter-top-left':     'Top-Left Quarter',
				'quarter-top-right':    'Top-Right Quarter',
				'quarter-bottom-left':  'Bottom-Left Quarter',
				'quarter-bottom-right': 'Bottom-Right Quarter'
			};
			var ALIAS = {
				'halfleft':'half-left','half_left':'half-left',
				'halfright':'half-right','half_right':'half-right',
				'quartertopleft':'quarter-top-left','quarter_top_left':'quarter-top-left',
				'quartertopright':'quarter-top-right','quarter_top_right':'quarter-top-right',
				'quarterbottomleft':'quarter-bottom-left','quarter_bottom_left':'quarter-bottom-left',
				'quarterbottomright':'quarter-bottom-right','quarter_bottom_right':'quarter-bottom-right'
			};
			var s = String( raw == null ? '' : raw ).toLowerCase().replace(/\s+/g,'-');
			if ( ALIAS[s] ) { s = ALIAS[s]; }
			if ( s === 'whole' ) { return { portion: '', label: 'Whole' }; }
			return MAP[s] ? { portion: s, label: MAP[s] } : { portion: '', label: '' };
		}

		/**
		 * Resolve the layer type string ('crust', 'sauce', 'topping', …) for a
		 * layer slug.  Uses:
		 *   1. layerData.type if the event already supplied it.
		 *   2. CFG.layerTypeMap — the slug→type map built server-side.
		 *   3. Empty string (treated as topping by the pricing engine).
		 *
		 * @param {string} lid       Layer slug / ID.
		 * @param {object} layerData layerData object from the event (may be empty).
		 * @returns {string}
		 */
		function resolveLayerType(lid, layerData) {
			if (layerData && (layerData.type || layerData.layerType)) {
				return String(layerData.type || layerData.layerType);
			}
			var map = CFG.layerTypeMap || {};
			return map[String(lid||'')] || '';
		}

		function ntPrice(type,size,frac,postId) {
			const m=CFG.nonToppingPricing||'grid';
			if (m==='free')  return 0;
			if (m==='fixed') return parseFloat((CFG.fixedPrices||{})[(type||'').toLowerCase()])||0;
			// 'grid' mode: fraction-capable types (cheese/sauce/drizzle) use the
			// fraction grid; all others use the flat per-size grid.
			// For both, check the per-layer grid first (getLayerPrice handles fallback).
			const ltype = (type||'').toLowerCase();
			if (FRACTION_TYPES.indexOf(ltype) !== -1) {
				const p = getLayerPrice(postId||0, size, frac);
				return p !== null ? p : 0;
			}
			// Flat type: check per-layer grid first if it has a Whole entry for this size.
			if ( postId ) {
				const lg = LAYER_GRIDS[ String(postId) ];
				if ( lg && lg.cells ) {
					const wk = size + '|' + frac;
					if ( Object.prototype.hasOwnProperty.call(lg.cells, wk) ) {
						return parseFloat(lg.cells[wk]) || 0;
					}
					// Try first fraction column (usually Whole) as fallback within layer grid.
					if ( lg.fractions && lg.fractions.length ) {
						const wk2 = size + '|' + lg.fractions[0];
						if ( Object.prototype.hasOwnProperty.call(lg.cells, wk2) ) {
							return parseFloat(lg.cells[wk2]) || 0;
						}
					}
				}
			}
			// Flat lookup: key = "layerType|size"
			const fc = FLAT_GRID.cells || {};
			const fk = ltype + '|' + size;
			if (Object.prototype.hasOwnProperty.call(fc, fk)) return parseFloat(fc[fk])||0;
			// Backward-compat fallback: try main grid Whole column
			const fb=gridPrice(size, (GRID.fractions&&GRID.fractions[0])||'Whole');
			return fb!==null?fb:0;
		}

		function mkE(lid,name,frac,type,price,note) {
			var _ls = activeLayers[lid], _ld = _ls && _ls.layerData ? _ls.layerData : {};
			return {layerId:lid,layerName:name,fraction:frac,
				portion:_ld.portion||'',portionLabel:_ld.portionLabel||'',
				layerType:type,price:price!==null?price:null,note:note||''};
		}

		function humanise(id) { return String(id).replace(/[-_]+/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();}); }

		function layerName(lid,ls) { return (ls.layerData&&(ls.layerData.name||ls.layerData.label))||humanise(lid); }

		function layerType(lid,ls) {
			return (ls.layerData&&(ls.layerData.type||ls.layerData.layerType)) || resolveLayerType(lid,ls.layerData||{});
		}

		function engineAddon(size,layers) {
			let add=0,bd=[],miss=false,free=parseInt(CFG.freeToppingsCount,10)||0;
			// If no product-level grid cells exist at all, treat as base-price-only
			// rather than blocking with gridMiss. Layers display in the breakdown
			// but contribute $0 add-on. This handles products without a price grid
			// configured yet, and lets orders go through with the base price.
			const hasAnyGrid = Object.keys(GRID.cells||{}).length > 0
				|| Object.keys(LAYER_GRIDS||{}).length > 0
				|| Object.keys(FLAT_GRID.cells||{}).length > 0;
			for(const lid in layers){
				if(!Object.prototype.hasOwnProperty.call(layers,lid)) continue;
				const ls=layers[lid],fr=ls.fraction||'Whole',type=layerType(lid,ls),pid=ls.postId||0;
				let p;
				if(isNT(type)){p=ntPrice(type,size,fr,pid);}
				else if(free>0){p=0;free--;}
				else{
					p=getLayerPrice(pid,size,fr);
					if(p===null){var _fc=FLAT_GRID.cells||{};var _fk='topping|'+size;p=Object.prototype.hasOwnProperty.call(_fc,_fk)?parseFloat(_fc[_fk]):null;}
					if(p===null){
						if(hasAnyGrid){miss=true;} else{p=0;} // no grid at all → $0 add-on, not blocked
					}
				}
				if(p!==null)add+=p;
				bd.push(mkE(lid,layerName(lid,ls),fr,type,p,''));
			}
			return {addOn:add,breakdown:bd,gridMiss:miss};
		}

		function engineFlat(size,layers) {
			const fp=gridPrice(size,'Whole'),miss=fp===null&&Object.keys(layers).length>0;
			let add=fp||0,bd=[];
			for(const lid in layers){
				if(!Object.prototype.hasOwnProperty.call(layers,lid)) continue;
				const ls=layers[lid],fr=ls.fraction||'Whole',type=layerType(lid,ls),pid=ls.postId||0;
				if(isNT(type)){const p=ntPrice(type,size,fr,pid);add+=p;bd.push(mkE(lid,layerName(lid,ls),fr,type,p,''));}
				else{bd.push(mkE(lid,layerName(lid,ls),fr,type,0,'Included in flat rate'));}
			}
			bd.unshift(mkE('_flat','Flat rate ('+size+')','Whole','',fp,''));
			return {addOn:add,breakdown:bd,gridMiss:miss};
		}

		function engineHighest(size,layers) {
			let add=0,bd=[],topP=0,topId=null;
			for(const lid in layers){
				if(!Object.prototype.hasOwnProperty.call(layers,lid)) continue;
				const ls=layers[lid],fr=ls.fraction||'Whole',type=layerType(lid,ls),pid=ls.postId||0;
				if(isNT(type)){const p=ntPrice(type,size,fr,pid);add+=p;bd.push(mkE(lid,layerName(lid,ls),fr,type,p,''));}
				else{const p=getLayerPrice(pid,size,fr)||0;if(p>topP){topP=p;topId=lid;}bd.push(mkE(lid,layerName(lid,ls),fr,type,0,'Free (highest wins)'));}
			}
			if(topId){add+=topP;for(let i=0;i<bd.length;i++){if(bd[i].layerId===topId){bd[i].price=topP;bd[i].note='Highest-priced layer';break;}}}
			return {addOn:add,breakdown:bd,gridMiss:false};
		}

		function engineTiered(size,layers) {
			let add=0,bd=[],miss=false,cnt=0;
			for(const lid in layers){if(!Object.prototype.hasOwnProperty.call(layers,lid))continue;if(!isNT(layerType(lid,layers[lid])))cnt++;}
			const ths=(CFG.tieredThresholds||[3,6]).slice().sort((a,b)=>a-b);
			let tn=1;for(const t of ths){if(cnt>t)tn++;}
			const tf='Tier'+tn,tp=gridPrice(size,tf);
			if(tp===null&&cnt>0)miss=true; if(tp!==null)add+=tp;
			bd.push(mkE('_tier',tf+' — '+cnt+' toppings',tf,'',tp,''));
			for(const lid in layers){
				if(!Object.prototype.hasOwnProperty.call(layers,lid))continue;
				const ls=layers[lid],fr=ls.fraction||'Whole',type=layerType(lid,ls),pid=ls.postId||0;
				if(isNT(type)){const p=ntPrice(type,size,fr,pid);add+=p;bd.push(mkE(lid,layerName(lid,ls),fr,type,p,''));}
				else{bd.push(mkE(lid,layerName(lid,ls),fr,type,0,'Included in '+tf));}
			}
			return {addOn:add,breakdown:bd,gridMiss:miss};
		}

		function engineFreeN(size,layers) {
			let add=0,bd=[],miss=false,free=parseInt(CFG.freeToppingsCount,10)||0;
			for(const lid in layers){
				if(!Object.prototype.hasOwnProperty.call(layers,lid))continue;
				const ls=layers[lid],fr=ls.fraction||'Whole',type=layerType(lid,ls),pid=ls.postId||0;
				let p,note='';
				if(isNT(type)){p=ntPrice(type,size,fr,pid);}
				else if(free>0){p=0;note='Free topping included';free--;}
				else{p=getLayerPrice(pid,size,fr);if(p===null){var _fc2=FLAT_GRID.cells||{};var _fk2='topping|'+size;p=Object.prototype.hasOwnProperty.call(_fc2,_fk2)?parseFloat(_fc2[_fk2]):null;}if(p===null)miss=true;}
				if(p!==null)add+=p;
				bd.push(mkE(lid,layerName(lid,ls),fr,type,p,note));
			}
			return {addOn:add,breakdown:bd,gridMiss:miss};
		}

		function engineBundle(layers) {
			const bd=[];
			for(const lid in layers){if(!Object.prototype.hasOwnProperty.call(layers,lid))continue;const ls=layers[lid];bd.push(mkE(lid,layerName(lid,ls),ls.fraction||'Whole',layerType(lid,ls),0,'Included in bundle'));}
			return {addOn:0,breakdown:bd,gridMiss:false};
		}

		function calculatePrices() {
			const base=parseFloat(CFG.basePrice)||0, mode=CFG.pricingMode||'addon_per_layer';

			// Guard: if activeSize is empty and we have sizes, snap to first.
			if ( ! activeSize && GRID.sizes && GRID.sizes.length ) {
				activeSize = GRID.sizes[0];
			}

			let r;
			switch(mode){
				case 'flat_per_size':   r=engineFlat(activeSize,activeLayers);    break;
				case 'highest_wins':    r=engineHighest(activeSize,activeLayers); break;
				case 'tiered_by_count': r=engineTiered(activeSize,activeLayers);  break;
				case 'free_first_n':    r=engineFreeN(activeSize,activeLayers);   break;
				case 'bundle':          r=engineBundle(activeLayers);             break;
				default:                r=engineAddon(activeSize,activeLayers);   break;
			}
			let {addOn,breakdown,gridMiss}=r;

			// Size multiplier
			const mult=parseFloat((CFG.sizeMultipliers||{})[activeSize])||1;
			if(mult!==1){addOn*=mult;breakdown=breakdown.map(function(it){return it.price>0?Object.assign({},it,{price:it.price*mult}):it;});}

			// Bulk discount
			let disc=0;
			const dt=parseInt(CFG.discountThreshold,10)||0,dp=parseFloat(CFG.discountPercent)||0,dm=CFG.discountMaxAmount!=null?parseFloat(CFG.discountMaxAmount):null;
			const tc=Object.keys(activeLayers).filter(function(id){return !isNT(layerType(id,activeLayers[id]));}).length;
			if(dt>0&&dp>0&&tc>=dt){disc=addOn*(dp/100);if(dm!==null&&dm>0)disc=Math.min(disc,dm);}

			const finalAdd=Math.max(0,addOn-disc);
			const total=gridMiss?null:applyRounding(base+finalAdd);
			if(CFG.debugMode) console.log('[PizzaTier #'+idx+']',{mode,activeSize,base,addOn,disc,finalAdd,total,breakdown});
			return {total,breakdown,basePrice:base,addOn:finalAdd,discountAmount:disc,toppingCount:tc};
		}

		// =====================================================================
		// Display update
		// =====================================================================

		function recalculate() {
			// Sync activeLayers from the template API state before every calculation.
			// This is the universal path that works regardless of whether the template
			// fires events. getTemplateLayersNow() returns null when PizzaTierAPI is
			// not yet available (e.g. on initial load), in which case we keep whatever
			// activeLayers was already set via events or defaults.
			var tmplLayers = getTemplateLayersNow();
			if ( tmplLayers !== null ) {
				// Replace activeLayers wholesale with the current template state.
				var k;
				for ( k in activeLayers ) {
					if ( Object.prototype.hasOwnProperty.call( activeLayers, k ) ) {
						delete activeLayers[k];
					}
				}
				for ( var i = 0; i < tmplLayers.length; i++ ) {
					var l = tmplLayers[i];
					if ( l.layerId ) {
						activeLayers[ l.layerId ] = {
							fraction:  l.fraction  || 'Whole',
							layerData: { name: l.layerName || '', type: l.layerType || '', label: l.layerName || '' },
							postId:    l.layerPostId || resolvePostId( l.layerId )
						};
					}
				}
			}
			const r=calculatePrices();
			updatePriceBar(r);
			updateCheckoutBar(r);
			updateWcPriceHtml(r);
			updateBreakdownList(r);
			if(CFG.nutritionEnabled) updateNutritionPanel(r);
		}

		/** Update every checkout bar price element on the page for this instance.
		 *
		 *  Discovery is "best-effort": a PizzaTier builder may be rendered via
		 *  the WC product flow (wrapped in .pztc-builder-section[data-pztc-instance])
		 *  OR via a bare [pizza_builder] shortcode with a custom id= attribute
		 *  (producing bar ids like "pztc-bar-price-pltp-metro"). Rather than
		 *  guessing the suffix, we locate bars by DOM proximity and update all
		 *  of them — safer and works regardless of how the builder was embedded. */
		function updateCheckoutBar(r) {
			var cur      = CFG.currencySymbol || '$';
			var priceVal = r.total !== null ? r.total : ( r.basePrice || 0 );
			var promptTx = ( CFG.i18n && CFG.i18n.selectSizePrompt ) || 'Select a size';
			var newText  = cur + formatPrice( priceVal );

			var bars = collectCheckoutBars();
			if ( ! bars.length ) { return; }

			for ( var i = 0; i < bars.length; i++ ) {
				var scope    = bars[i];
				var barPrice = scope.querySelector( '[id^="pztc-bar-price-"]' )
					|| scope.querySelector( '.pztc-bar-row__price' );
				var barSize  = scope.querySelector( '[id^="pztc-bar-size-"]' )
					|| scope.querySelector( '.pztc-bar-row__size-label' );

				if ( ! barPrice ) { continue; }

				if ( ! sizeChosen ) {
					if ( barPrice.textContent !== promptTx ) {
						barPrice.textContent = promptTx;
					}
					barPrice.classList.add( 'pztc-bar-price--prompt' );
				} else {
					barPrice.classList.remove( 'pztc-bar-price--prompt' );
					if ( barPrice.textContent !== newText ) {
						barPrice.textContent = newText;
						barPrice.classList.remove( 'pztc-bar-price--flash' );
						void barPrice.offsetWidth; // force reflow → restart CSS animation
						barPrice.classList.add( 'pztc-bar-price--flash' );
					}
				}

				if ( barSize ) {
					barSize.textContent = sizeChosen ? ( activeSize || '' ) : '';
				}
			}

			/* Public hook so custom layouts / Pro integrations can react. */
			if ( typeof window !== 'undefined' ) {
				try {
					var evt = new CustomEvent( 'pizzatier_commerce:checkoutbar:update', {
						detail: {
							idx:        idx,
							sizeChosen: sizeChosen,
							activeSize: activeSize,
							priceText:  sizeChosen ? newText : promptTx,
							priceValue: sizeChosen ? priceVal : null,
							result:     r
						}
					} );
					document.dispatchEvent( evt );
				} catch ( _e ) {}
			}
		}

		/** Find all .pztc-checkout-bar nodes that belong to THIS builder instance.
		 *
		 *  Resolution order:
		 *   1) Inside our .pztc-builder-section[data-pztc-instance="{idx}"]
		 *      — the WC product embed case. Also checks the section's parent and
		 *      immediately following sibling so bars rendered after the section
		 *      (some templates detach them) are still picked up.
		 *   2) The closest ancestor / nearby element with an id that matches the
		 *      shortcode-generated instanceId (e.g. "pltp-metro") which is the
		 *      builder root element. Every bar inside that root belongs to us.
		 *   3) Fallback: every .pztc-checkout-bar on the page. Safe when there
		 *      is only one builder; harmless extra work when there are several.
		 */
		function collectCheckoutBars() {
			var found = [];
			var seen  = [];
			function push( el ) {
				if ( el && seen.indexOf( el ) === -1 ) { seen.push( el ); found.push( el ); }
			}

			// (1) Pro WC product embed — look inside and around the wrapper section.
			if ( idx !== null ) {
				var sec = document.querySelector( '.pztc-builder-section[data-pztc-instance="' + idx + '"]' );
				if ( sec ) {
					var inside = sec.querySelectorAll( '.pztc-checkout-bar' );
					for ( var a = 0; a < inside.length; a++ ) { push( inside[a] ); }

					// Bars sometimes render as a sibling of the embed, not a descendant.
					var next = sec.nextElementSibling;
					while ( next ) {
						if ( next.classList && next.classList.contains( 'pztc-checkout-bar' ) ) { push( next ); break; }
						if ( next.querySelector ) {
							var inSib = next.querySelectorAll( '.pztc-checkout-bar' );
							for ( var b = 0; b < inSib.length; b++ ) { push( inSib[b] ); }
						}
						next = next.nextElementSibling;
					}

					var parent = sec.parentNode;
					if ( parent && parent.querySelectorAll ) {
						var inParent = parent.querySelectorAll( '.pztc-checkout-bar' );
						for ( var c = 0; c < inParent.length; c++ ) { push( inParent[c] ); }
					}
				}
			}

			// (2) Shortcode embed with a custom id — look inside the builder root.
			if ( ! found.length && CFG && CFG.builderRootId ) {
				var root = document.getElementById( CFG.builderRootId );
				if ( root ) {
					var inRoot = root.querySelectorAll( '.pztc-checkout-bar' );
					for ( var d = 0; d < inRoot.length; d++ ) { push( inRoot[d] ); }
				}
			}

			// (3) Last-resort whole-page sweep. Fine for single-builder pages.
			if ( ! found.length ) {
				var all = document.querySelectorAll( '.pztc-checkout-bar' );
				for ( var e = 0; e < all.length; e++ ) { push( all[e] ); }
			}

			return found;
		}

		function updatePriceBar(r) {
			const $p=$id('pztc-live-price'),$f=$id('pztc-price-fallback');
			const $v=$p?$p.querySelector('.pztc-live-price__value'):null;
			if(r.total===null){
				if($p)$p.style.display='none';
				if($f)$f.style.display='';
				return;
			}
			if($p)$p.style.display='';if($f)$f.style.display='none';
			if($v)$v.textContent=formatPrice(r.total);
			if($p){$p.classList.remove('pztc-price--updated');void $p.offsetWidth;$p.classList.add('pztc-price--updated');}
		}

		function updateWcPriceHtml(r) {
			const $w=document.getElementById('pztc-wc-price-value');if(!$w)return;
			const base=r.basePrice||parseFloat(CFG.basePrice)||0;
			if(!r.breakdown.length){$w.textContent=formatPrice(base);return;}
			if(r.total===null){$w.textContent=(CFG.i18n&&CFG.i18n.priceUnavailable)||'—';return;}
			$w.textContent=formatPrice(r.total);
		}

		function updateBreakdownList(r) {
			const $l=$id('pztc-layer-breakdown');if(!$l)return;
			const bd=r.breakdown||[];
			if(!CFG.showBreakdown||!bd.length){$l.style.display='none';$l.innerHTML='';return;}
			$l.style.display='';
			const cur=CFG.currencySymbol||'$';
			let html='';
			if(CFG.showBaseInBreakdown) html+='<li class="pztc-layer-breakdown__item pztc-layer-breakdown__item--base"><span class="pztc-layer-breakdown__name">Base pizza</span><span class="pztc-layer-breakdown__meta"></span><span class="pztc-layer-breakdown__price">'+escHtml(cur+formatPrice(r.basePrice||0))+'</span></li>';
			for(const it of bd){
				const pStr=(it.price!==null&&it.price!==undefined)?cur+formatPrice(it.price):'—';
				const note=it.note?' <em>('+escHtml(it.note)+')</em>':'';
				const cls=it.layerId&&it.layerId[0]==='_'?' pztc-layer-breakdown__item--tier':'';
				let nbadge='';
				if(CFG.nutritionEnabled&&CFG.nutritionDisplay==='inline'&&CFG.nutritionShowCalories){
					const nd=(CFG.nutritionData||{})[it.layerId];
					if(nd&&nd.calories) nbadge=' <span class="pztc-nutrition-badge">'+escHtml(nd.calories)+' '+escHtml((CFG.i18n&&CFG.i18n.calories)||'cal')+'</span>';
				}
				html+='<li class="pztc-layer-breakdown__item'+cls+'"><span class="pztc-layer-breakdown__name">'+escHtml(it.layerName||it.layerId||'')+note+nbadge+'</span><span class="pztc-layer-breakdown__meta">'+escHtml(it.portionLabel||it.fraction||'')+'</span><span class="pztc-layer-breakdown__price">'+escHtml(pStr)+'</span></li>';
			}
			if(CFG.showSavings&&r.discountAmount>0) html+='<li class="pztc-layer-breakdown__item pztc-layer-breakdown__item--savings"><span class="pztc-layer-breakdown__name">Bulk discount</span><span class="pztc-layer-breakdown__meta"></span><span class="pztc-layer-breakdown__price pztc-price--savings">&#8722;'+escHtml(cur+formatPrice(r.discountAmount))+'</span></li>';
			$l.innerHTML=html;
		}

		// =====================================================================
		// Nutrition panel
		// =====================================================================

		function updateNutritionPanel(r) {
			const display=CFG.nutritionDisplay||'tooltip';
			if(display==='tooltip')      updateNutritionTooltips();
			else if(display==='panel')   updateNutritionSummaryPanel(r);
		}

		function updateNutritionTooltips() {
			const $embed=$id('pztc-builder-embed');if(!$embed)return;
			const nd=CFG.nutritionData||{};
			$embed.querySelectorAll('[data-layer-id]').forEach(function(el){
				const nutr=nd[el.dataset.layerId];
				if(!nutr){el.removeAttribute('data-pztc-tooltip');return;}
				const s=buildNutrStr(nutr);
				if(s){el.setAttribute('data-pztc-tooltip',s);el.classList.add('pztc-has-nutrition');}
			});
		}

		function updateNutritionSummaryPanel(r) {
			if(!CFG.nutritionShowSummary)return;
			const sec=$section();if(!sec)return;
			let $p=sec.querySelector('.pztc-nutrition-panel');
			if(!$p){
				$p=document.createElement('div');$p.className='pztc-nutrition-panel';
				const $em=$id('pztc-builder-embed');
				if($em&&$em.nextSibling)$em.parentNode.insertBefore($p,$em.nextSibling);
				else sec.appendChild($p);
			}
			const tot={calories:0,fat:0,carbs:0,protein:0,sodium:0};
			const ags=new Set();const nd=CFG.nutritionData||{};let any=false;
			for(const lid in activeLayers){
				if(!Object.prototype.hasOwnProperty.call(activeLayers,lid))continue;
				const n=nd[lid];if(!n)continue;any=true;
				if(n.calories&&CFG.nutritionShowCalories)  tot.calories +=parseFloat(n.calories)||0;
				if(n.fat     &&CFG.nutritionShowFat)        tot.fat      +=parseFloat(n.fat)||0;
				if(n.carbs   &&CFG.nutritionShowCarbs)      tot.carbs    +=parseFloat(n.carbs)||0;
				if(n.protein &&CFG.nutritionShowProtein)    tot.protein  +=parseFloat(n.protein)||0;
				if(n.sodium  &&CFG.nutritionShowSodium)     tot.sodium   +=parseFloat(n.sodium)||0;
				if(n.allergens&&CFG.nutritionShowAllergens) n.allergens.split(/[,;]+/).map(function(a){return a.trim();}).filter(Boolean).forEach(function(a){ags.add(a);});
			}
			if(!any){$p.style.display='none';return;}$p.style.display='';
			const lbl=(CFG.i18n&&CFG.i18n.nutritionSummaryLabel)||'Estimated nutrition';
			let html='<div class="pztc-nutrition-panel__heading">'+escHtml(lbl)+'</div><div class="pztc-nutrition-panel__row">';
			if(CFG.nutritionShowCalories&&tot.calories) html+='<span class="pztc-nutrition-panel__item"><strong>'+Math.round(tot.calories)+'</strong> '+escHtml((CFG.i18n&&CFG.i18n.calories)||'cal')+'</span>';
			if(CFG.nutritionShowFat&&tot.fat)           html+='<span class="pztc-nutrition-panel__item"><strong>'+tot.fat.toFixed(1)+'g</strong> fat</span>';
			if(CFG.nutritionShowCarbs&&tot.carbs)       html+='<span class="pztc-nutrition-panel__item"><strong>'+tot.carbs.toFixed(1)+'g</strong> carbs</span>';
			if(CFG.nutritionShowProtein&&tot.protein)   html+='<span class="pztc-nutrition-panel__item"><strong>'+tot.protein.toFixed(1)+'g</strong> protein</span>';
			if(CFG.nutritionShowSodium&&tot.sodium)     html+='<span class="pztc-nutrition-panel__item"><strong>'+Math.round(tot.sodium)+'mg</strong> sodium</span>';
			html+='</div>';
			if(CFG.nutritionShowAllergens&&ags.size) html+='<div class="pztc-nutrition-panel__allergens">Contains: '+escHtml(Array.from(ags).join(', '))+'</div>';
			$p.innerHTML=html;
		}

		function buildNutrStr(n) {
			const p=[];
			if(n.calories &&CFG.nutritionShowCalories)  p.push(n.calories+' cal');
			if(n.fat      &&CFG.nutritionShowFat)        p.push(n.fat+'g fat');
			if(n.carbs    &&CFG.nutritionShowCarbs)      p.push(n.carbs+'g carbs');
			if(n.protein  &&CFG.nutritionShowProtein)    p.push(n.protein+'g protein');
			if(n.sodium   &&CFG.nutritionShowSodium)     p.push(n.sodium+'mg sodium');
			if(n.allergens&&CFG.nutritionShowAllergens)  p.push('Contains: '+n.allergens);
			return p.join(' · ');
		}

		// =====================================================================
		// Helpers
		// =====================================================================

		function formatPrice(v) {
			const dec=parseInt(CFG.decimals,10)||2,ds=CFG.decimalSep!==undefined?CFG.decimalSep:'.',ts=CFG.thousandSep!==undefined?CFG.thousandSep:',';
			const f=parseFloat(v).toFixed(dec),pts=f.split('.');
			let int=pts[0];const d=pts[1]||'';
			if(ts) int=int.replace(/\B(?=(\d{3})+(?!\d))/g,ts);
			return d?int+ds+d:int;
		}

		function escHtml(s) {
			return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
		}

		// =====================================================================
		// Template bridge helpers
		// =====================================================================

		function findTemplateRoot() {
			// Try to find a template root scoped to our builder section first.
			var knownRoots = '.cb-root,.np-root,.mt-root,.rp-root,.pp-root,.pl-root,.sc-root';
			// Also accept any element with data-instance matching our pztc-{idx} id.
			var instanceId = idx !== null ? 'pztc-' + idx : null;

			if ( idx !== null ) {
				var sec = document.querySelector( '.pztc-builder-section[data-pztc-instance="' + idx + '"]' );
				if ( sec ) {
					// Try known root class selectors
					var r = sec.querySelector( knownRoots );
					if ( r ) return r;
					// Try any element with data-instance matching our instanceId
					if ( instanceId ) {
						var byInstance = sec.querySelector( '[data-instance="' + instanceId + '"]' );
						if ( byInstance ) return byInstance;
					}
				}
			}
			// Fallback: any template root on the page
			if ( instanceId ) {
				var pageByInstance = document.querySelector( '[data-instance="' + instanceId + '"]' );
				if ( pageByInstance ) return pageByInstance;
			}
			return document.querySelector( knownRoots );
		}

		function getTemplateLayersNow() {
			var api = window.PizzaTierAPI;
			if ( ! api ) { return null; }

			// Try to obtain state from the API, trying every available strategy:
			// 1. Instance keyed by "pztc-{idx}" (what FrontendEmbed passes as id= to the shortcode).
			// 2. All instances from getAllInstances() / getInstances().
			// 3. Direct no-arg getState() (pocketpie single-instance pattern).
			var s = null;
			var instanceId = idx !== null ? 'pztc-' + idx : null;

			// Strategy 1: exact instance key
			if ( instanceId && typeof api.getState === 'function' ) {
				try { s = api.getState( instanceId ); } catch(e) {}
			}

			// Strategy 2: iterate all registered instances
			if ( !s ) {
				// commandcenter: getInstances() returns the raw map
				if ( typeof api.getInstances === 'function' ) {
					var insts = api.getInstances();
					for ( var k in insts ) {
						if ( Object.prototype.hasOwnProperty.call( insts, k ) ) {
							try { s = insts[k].getState ? insts[k].getState() : null; } catch(e) {}
							if ( s ) break;
						}
					}
				}
				// colorbox: getAllInstances() returns key array
				if ( !s && typeof api.getAllInstances === 'function' ) {
					var keys = api.getAllInstances();
					for ( var i = 0; i < keys.length; i++ ) {
						try { s = api.getState( keys[i] ); } catch(e) {}
						if ( s ) break;
					}
				}
			}

			// Strategy 3: bare getState() (pocketpie fallback)
			if ( !s && typeof api.getState === 'function' ) {
				try { s = api.getState(); } catch(e) {}
			}

			if ( !s ) { return null; }

			// Normalise coverage strings to grid fraction labels.
			// Templates emit varied slugs ('half-left', 'HalfLeft', 'half_left', etc.)
			// — map them all to the canonical grid column labels.
			var frMap = {
				'whole':'Whole',
				'half':'Half',
				'half-left':'Half','half-right':'Half',
				'halfleft':'Half','halfright':'Half',
				'half_left':'Half','half_right':'Half',
				'quarter':'Quarter',
				'quarter-top-left':'Quarter','quarter-top-right':'Quarter',
				'quarter-bottom-left':'Quarter','quarter-bottom-right':'Quarter',
				'quartertopleft':'Quarter','quartertopright':'Quarter',
				'quarterbottomleft':'Quarter','quarterbottomright':'Quarter',
			};

			var layers = [];

			// Modern array-based state (commandcenter-style, plainlist, scaffold via allLayers)
			var layerSource = Array.isArray( s.layers ) ? s.layers
			                : Array.isArray( s.allLayers ) ? s.allLayers
			                : null;
			if ( layerSource ) {
				layerSource.forEach( function(l) {
					var rawFrac = l.fraction || l.coverage || 'Whole';
					var mappedFrac = frMap[ (rawFrac||'whole').toLowerCase() ] || rawFrac;
					// Prefer the explicit specific portion the template passes
					// (l.portion / l.coverage); fall back to whatever fraction slug
					// it gave. This preserves WHICH portion for the kitchen ticket.
					var p = resolvePortion( l.portion || l.coverage || rawFrac );
					layers.push({
						layerId:   l.id    || l.layerId   || l.slug || '',
						layerName: l.title || l.layerName || l.name || '',
						fraction:  normaliseFraction( mappedFrac ),
						portion:      p.portion,
						portionLabel: l.coverageLabel || p.label,
						layerType: l.type  || l.layerType || resolveLayerType( l.id || l.layerId || l.slug || '', l )
					});
				} );
				return layers;
			}

			// Object-based state (colorbox / pocketpie pattern)
			['crust','sauce','cheese','drizzle','cut'].forEach( function(type) {
				var e = s[type];
				if ( e && ( e.slug || e.id ) ) {
					layers.push({
						layerId:   e.slug  || e.id    || '',
						layerName: e.title || e.name  || e.slug || '',
						fraction:  normaliseFraction( 'Whole' ),
						portion:      '',
						portionLabel: 'Whole',
						layerType: type
					});
				}
			} );

			Object.keys( s.toppings || {} ).forEach( function(slug) {
				var t = s.toppings[slug];
				var rawCov = (t.coverage || 'whole').toLowerCase();
				var mappedFrac = frMap[ rawCov ] || 'Whole';
				var p = resolvePortion( t.coverage || rawCov );
				layers.push({
					layerId:   slug,
					layerName: t.title || t.name || slug,
					fraction:  normaliseFraction( mappedFrac ),
					portion:      p.portion,
					portionLabel: t.coverageLabel || p.label,
					layerType: 'topping'
				});
			} );

			return layers;
		}

		// =====================================================================
		// Public API
		// =====================================================================

		return {
			getState: function () {
				// activeLayers is kept current by recalculate() which syncs from
				// getTemplateLayersNow() on every poll cycle (every 120ms).
				// Do a final sync here to catch any change that happened between polls.
				var tmplLayers = getTemplateLayersNow();
				if ( tmplLayers !== null ) {
					var k;
					for ( k in activeLayers ) {
						if ( Object.prototype.hasOwnProperty.call( activeLayers, k ) ) delete activeLayers[k];
					}
					for ( var i = 0; i < tmplLayers.length; i++ ) {
						var l = tmplLayers[i];
						if ( l.layerId ) registerLayer( String(l.layerId), l.fraction||'Whole',
							{name:l.layerName||'',type:l.layerType||'',label:l.layerName||'',
							 portion:l.portion||'',portionLabel:l.portionLabel||''} );
					}
				}
				const r = calculatePrices();
				// Build layers array from activeLayers (now always up-to-date).
				var layers = [];
				for ( var lid in activeLayers ) {
					if ( !Object.prototype.hasOwnProperty.call( activeLayers, lid ) ) continue;
					const ls = activeLayers[lid], ld = ls.layerData || {};
					layers.push({
						layerId:      lid,
						layerName:    ld.name || ld.label || humanise(lid),
						fraction:     ls.fraction || 'Whole',
						portion:      ld.portion      || '',
						portionLabel: ld.portionLabel || '',
						layerType:    ld.type || ld.layerType || resolveLayerType(lid, ld),
						layerPostId:  ls.postId || resolvePostId(lid)
					});
				}
				let orderNote = '';
				if ( CFG.orderNotesEnabled ) {
					const $n = $id('pztc-order-note-input');
					if ( $n ) orderNote = $n.value.trim().substring(0, 500);
				}
				const pid = CFG.productId || 0;
				return { productId:pid, size:activeSize, sizeChosen:sizeChosen, total:r.total,
				         layers:layers, breakdown:r.breakdown, orderNote:orderNote, instanceIdx:idx };
			},
			recalculate: recalculate,
			instanceIdx: idx,

			prefill: function ( cfg ) {
				if ( ! cfg ) return;
				if ( cfg.s ) {
					activeSize = cfg.s;
					const $sel = $id( 'pztc-size-selector' );
					if ( $sel ) {
						$sel.querySelectorAll( '.pztc-size-radio' ).forEach( function ( radio ) {
							const isMatch = radio.value === cfg.s;
							radio.checked = isMatch;
							const label = radio.closest( '.pztc-size-option' );
							if ( label ) label.classList.toggle( 'pztc-size-option--active', isMatch );
						} );
					}
				}
				if ( cfg.n && CFG.orderNotesEnabled ) {
					const $note = $id( 'pztc-order-note-input' );
					if ( $note ) $note.value = cfg.n;
				}
				if ( Array.isArray( cfg.l ) && cfg.l.length ) {
					const prefillEvent = new CustomEvent( 'pizzatier:prefill', {
						bubbles: true,
						detail: {
							layers: cfg.l.map( function ( l ) {
								return {
									layerId:   l.id   || '',
									fraction:  l.fr   || 'Whole',
									portion:   l.po   || '',
									coverage:  l.po   || '',
									layerType: l.lt   || '',
									layerName: l.nm   || '',
								};
							} ),
							size: cfg.s || '',
						},
					} );
					document.dispatchEvent( prefillEvent );
					cfg.l.forEach( function ( l ) {
						if ( l.id ) {
							var pp = resolvePortion( l.po || l.fr );
							registerLayer( String( l.id ), l.fr || 'Whole',
								{ type: l.lt, name: l.nm, portion: pp.portion, portionLabel: l.pl || pp.label } );
						}
					} );
				}
				recalculate();
			},
		};
	}


	// =========================================================================
	// URL pre-fill (cart edit & order-again)
	// =========================================================================

	function applyUrlPrefill() {
		const params  = new URLSearchParams( window.location.search );
		const payload = params.get( 'pizzatier_commerce_cfg' );
		if ( ! payload ) return;
		let cfg;
		try {
			cfg = JSON.parse( atob( payload ) );
		} catch ( e ) {
			return;
		}
		if ( ! cfg || typeof cfg !== 'object' ) return;
		const api = window.PizzaTierBuilder || ( window.PizzaTierBuilderInstances || [] )[0];
		if ( api && typeof api.prefill === 'function' ) {
			setTimeout( function () { api.prefill( cfg ); }, 200 );
		}
		[ 'pizzatier_commerce_cfg', 'pizzatier_commerce_sig', 'pizzatier_commerce_reorder', 'pizzatier_commerce_edit_key', 'pizzatier_commerce_nonce' ].forEach( function ( k ) {
			params.delete( k );
		} );
		const clean = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' );
		window.history.replaceState( {}, '', clean );
	}

} )();
