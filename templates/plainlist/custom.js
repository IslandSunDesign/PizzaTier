/**
 * Plainlist Template — custom.js
 *
 * Text-only checklist interaction: exclusive toggles, multi-select toppings,
 * step-by-step wizard navigation, and a live selection summary.
 *
 * The PL namespace exposes createInstance(instanceId, opts) which is called
 * inline from pztp-containers-menu.php after the builder HTML.
 *
 * Compatible with the broader PizzaTier JS ecosystem:
 *   - Calls ClearPizza() / AddPizzaTier() / RemovePizzaTier() if available.
 *   - Dispatches 'pizzatier:selection_changed' on the root element.
 */

/* jshint browser:true */
/* global ClearPizza, AddPizzaTier, RemovePizzaTier */

(function ( window, document ) {
	'use strict';

	// ── State store per instance ──────────────────────────────────────
	var instances = {};

	/**
	 * Create a Plainlist instance.
	 *
	 * @param {string} instanceId  The data-instance value on .pl-root
	 * @param {Object} opts        Configuration passed from PHP
	 */
	function createInstance( instanceId, opts ) {

		var root = document.getElementById( instanceId );
		if ( ! root ) { return null; }

		var cfg = {
			tabs:          opts.tabs          || [],
			maxToppings:   opts.maxToppings   || 99,
			stepMode:      opts.stepMode      || false,
			requireSelect: opts.requireSelect || false,
			showSummary:   opts.showSummary   !== false
		};

		// ── Per-instance state ────────────────────────────────────────
		var state = {
			exclusive: {},   // layer_type → { slug, title, layerUrl }
			toppings:  {},   // slug → { title, layerUrl, zindex }
			currentStep: 0
		};

		// ── DOM helpers ───────────────────────────────────────────────

		function q( sel ) { return root.querySelector( sel ); }
		function qa( sel ) { return Array.prototype.slice.call( root.querySelectorAll( sel ) ); }

		var summaryList  = document.getElementById( instanceId + '-summary-list' );
		var toppingCount = document.getElementById( instanceId + '-topping-count' );
		var progressBar  = document.getElementById( instanceId + '-progress-bar' );
		var progressCurr = q( '.pl-progress__current' );
		var stepPrev     = document.getElementById( instanceId + '-step-prev' );
		var stepNext     = document.getElementById( instanceId + '-step-next' );
		var stepSections = qa( '.pl-section--step' );

		// ── Core toggles ──────────────────────────────────────────────

		/**
		 * Toggle an exclusive-select item (crust/sauce/cheese/drizzle/cut).
		 * Deselects the previously selected item in the same section.
		 */
		function plToggleExclusive( layerType, slug, title, layerUrl, itemEl ) {
			var isSelected = itemEl.classList.contains( 'pl-item--selected' );

			// Deselect previous in this layer
			var prev = root.querySelector( '.pl-item--exclusive[data-layer="' + layerType + '"].pl-item--selected' );
			if ( prev ) {
				prev.classList.remove( 'pl-item--selected' );
				prev.setAttribute( 'aria-checked', 'false' );
				var prevInput = prev.querySelector( '.pl-item__input' );
				if ( prevInput ) { prevInput.checked = false; }
				// Notify layer system
				if ( typeof RemovePizzaTier === 'function' && state.exclusive[ layerType ] ) {
					try { RemovePizzaTier( layerType, state.exclusive[ layerType ].slug ); } catch(e) {}
				}
				delete state.exclusive[ layerType ];
			}

			if ( ! isSelected ) {
				// Select this item
				itemEl.classList.add( 'pl-item--selected' );
				itemEl.setAttribute( 'aria-checked', 'true' );
				var input = itemEl.querySelector( '.pl-item__input' );
				if ( input ) { input.checked = true; }
				state.exclusive[ layerType ] = { slug: slug, title: title, layerUrl: layerUrl };
				// Notify layer system
				if ( layerUrl && typeof AddPizzaTier === 'function' ) {
					try { AddPizzaTier( layerType, slug, layerUrl, title ); } catch(e) {}
				}
			}

			refreshSummary();
			dispatchChange();
			refreshStepNext();
		}

		/**
		 * Toggle a topping item (multi-select with max limit).
		 */
		function plToggleTopping( zindex, slug, layerUrl, title, layerId, _layerId2, _thumbUrl, itemEl ) {
			var isSelected = itemEl.classList.contains( 'pl-item--selected' );

			if ( isSelected ) {
				// Remove
				itemEl.classList.remove( 'pl-item--selected' );
				itemEl.setAttribute( 'aria-checked', 'false' );
				var input = itemEl.querySelector( '.pl-item__input' );
				if ( input ) { input.checked = false; }
				delete state.toppings[ slug ];
				if ( typeof RemovePizzaTier === 'function' ) {
					try { RemovePizzaTier( layerId, slug ); } catch(e) {}
				}
			} else {
				// Check max
				var currentCount = Object.keys( state.toppings ).length;
				if ( currentCount >= cfg.maxToppings ) {
					root.dispatchEvent( new CustomEvent( 'pizzatier:max_toppings', { bubbles: true, detail: { instanceId: instanceId } } ) );
					return;
				}
				// Add
				itemEl.classList.add( 'pl-item--selected' );
				itemEl.setAttribute( 'aria-checked', 'true' );
				var inp = itemEl.querySelector( '.pl-item__input' );
				if ( inp ) { inp.checked = true; }
				state.toppings[ slug ] = { title: title, layerUrl: layerUrl, zindex: zindex, coverage: 'whole' };
				plUpdateCoverageChip( itemEl, 'whole' );
				if ( layerUrl && typeof AddPizzaTier === 'function' ) {
					try { AddPizzaTier( 'topping', slug, layerUrl, title, zindex ); } catch(e) {}
				}
			}

			// Update topping badge
			var count = Object.keys( state.toppings ).length;
			if ( toppingCount ) {
				toppingCount.textContent = count;
				toppingCount.style.display = count > 0 ? '' : 'none';
			}

			refreshSummary();
			dispatchChange();
		}

		/**
		 * Reset all selections.
		 */
		function plReset() {
			// Deselect all items
			qa( '.pl-item--selected' ).forEach( function( el ) {
				el.classList.remove( 'pl-item--selected' );
				el.setAttribute( 'aria-checked', 'false' );
				var inp = el.querySelector( '.pl-item__input' );
				if ( inp ) { inp.checked = false; }
			} );

			state.exclusive = {};
			state.toppings  = {};

			if ( toppingCount ) {
				toppingCount.textContent = '0';
				toppingCount.style.display = 'none';
			}

			if ( typeof ClearPizza === 'function' ) {
				try { ClearPizza(); } catch(e) {}
			}

			refreshSummary();
			dispatchChange();
			refreshStepNext();
		}

		/**
		 * Programmatically set selection state (PizzaTier JS API).
		 * Consumed by PizzaTierPro to apply "Default Layers".
		 *
		 * @param {Object} newState { crust|sauce|cheese|drizzle|cut: slug|{slug},
		 *                            toppings: { slug: {…} } }
		 */
		function plSetState( newState ) {
			plReset();
			if ( ! newState || typeof newState !== 'object' ) { return; }

			var baseTypes = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];
			baseTypes.forEach( function( type ) {
				var sel = newState[ type ];
				if ( ! sel ) { return; }
				var slug = ( typeof sel === 'object' ) ? sel.slug : sel;
				if ( ! slug ) { return; }
				var item = root.querySelector( '.pl-item--exclusive[data-layer="' + type + '"][data-slug="' + slug + '"]' );
				if ( item && ! item.classList.contains( 'pl-item--selected' ) ) { item.click(); }
			} );

			if ( newState.toppings && typeof newState.toppings === 'object' ) {
				Object.keys( newState.toppings ).forEach( function( slug ) {
					var item = root.querySelector( '.pl-item[data-layer="toppings"][data-slug="' + slug + '"]' );
					if ( item && ! item.classList.contains( 'pl-item--selected' ) ) { item.click(); }
				} );
			}
		}

		function refreshSummary() {
			if ( ! cfg.showSummary || ! summaryList ) { return; }

			var items = [];

			// Exclusive layers in tab order
			var exclusiveOrder = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];
			exclusiveOrder.forEach( function( layer ) {
				if ( state.exclusive[ layer ] ) {
					items.push( { section: layer.charAt(0).toUpperCase() + layer.slice(1), title: state.exclusive[ layer ].title } );
				}
			} );

			// Toppings
			Object.keys( state.toppings ).forEach( function( slug ) {
				var t   = state.toppings[ slug ];
				var cov = ( t.coverage && t.coverage !== 'whole' ) ? ' (' + covLabel( t.coverage ) + ')' : '';
				items.push( { section: 'Topping', title: t.title + cov } );
			} );

			if ( items.length === 0 ) {
				summaryList.innerHTML = '<li class="pl-summary__empty">' +
					( summaryList.getAttribute( 'data-empty-text' ) || 'No items selected yet.' ) + '</li>';
				return;
			}

			var html = '';
			items.forEach( function( item ) {
				html += '<li class="pl-summary__item">' +
					'<span class="pl-summary__item-section">' + escHtml( item.section ) + '</span>' +
					'<span class="pl-summary__item-title">' + escHtml( item.title ) + '</span>' +
					'</li>';
			} );
			summaryList.innerHTML = html;
		}

		// ── Step mode ─────────────────────────────────────────────────

		function goToStep( index ) {
			if ( ! cfg.stepMode || stepSections.length === 0 ) { return; }
			index = Math.max( 0, Math.min( stepSections.length - 1, index ) );
			state.currentStep = index;

			stepSections.forEach( function( el, i ) {
				var active = ( i === index );
				el.classList.toggle( 'pl-section--active', active );
				el.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
			} );

			if ( stepPrev ) { stepPrev.disabled = ( index === 0 ); }
			if ( stepNext ) { stepNext.textContent = ( index === stepSections.length - 1 ) ? '✓ Done' : ( stepNext.getAttribute( 'data-label-next' ) || 'Next →' ); }

			// Progress
			var pct = Math.round( ( ( index + 1 ) / stepSections.length ) * 100 );
			if ( progressBar ) { progressBar.style.width = pct + '%'; }
			if ( progressCurr ) { progressCurr.textContent = index + 1; }

			refreshStepNext();
		}

		function refreshStepNext() {
			if ( ! cfg.stepMode || ! cfg.requireSelect || ! stepNext ) { return; }
			var currentSection = stepSections[ state.currentStep ];
			if ( ! currentSection ) { stepNext.disabled = false; return; }
			var tab = currentSection.getAttribute( 'data-section' );
			var isExclusiveTab = ( tab !== 'toppings' );
			if ( isExclusiveTab ) {
				var hasSelection = !! ( state.exclusive[ tab === 'slicing' ? 'cut' : tab ] );
				stepNext.disabled = ! hasSelection;
			} else {
				stepNext.disabled = false; // toppings are optional
			}
		}

		// ── Event wiring ──────────────────────────────────────────────

		if ( cfg.stepMode ) {
			if ( stepPrev ) {
				stepPrev.addEventListener( 'click', function() {
					goToStep( state.currentStep - 1 );
				} );
			}
			if ( stepNext ) {
				stepNext.setAttribute( 'data-label-next', stepNext.textContent );
				stepNext.addEventListener( 'click', function() {
					if ( state.currentStep < stepSections.length - 1 ) {
						goToStep( state.currentStep + 1 );
					}
				} );
			}
			goToStep( 0 );
		}

		// ── Dispatch helper ───────────────────────────────────────────

		function dispatchChange() {
			var detail = {
				instanceId: instanceId,
				exclusive:  state.exclusive,
				toppings:   state.toppings
			};
			root.dispatchEvent( new CustomEvent( 'pizzatier:selection_changed', { bubbles: true, detail: detail } ) );
		}

		// ── Topping coverage modal ────────────────────────────────────

		var activeCoverageSlug = null;
		var covModal = document.getElementById( instanceId + '-cov-modal' );

		var COV_LABELS = {
			'whole':                'Whole',
			'half-left':            'Left Half',
			'half-right':           'Right Half',
			'quarter-top-left':     'Top-Left \u00BC',
			'quarter-top-right':    'Top-Right \u00BC',
			'quarter-bottom-left':  'Bottom-Left \u00BC',
			'quarter-bottom-right': 'Bottom-Right \u00BC'
		};
		function covLabel( fr ) { return COV_LABELS[ fr ] || COV_LABELS.whole; }

		/** Reflect a coverage choice on a topping row's chip. */
		function plUpdateCoverageChip( itemEl, fraction ) {
			if ( ! itemEl ) { return; }
			var lbl = itemEl.querySelector( '.pl-item__coverage-label' );
			if ( lbl ) { lbl.textContent = covLabel( fraction ); }
			var btn = itemEl.querySelector( '.pl-item__coverage' );
			if ( btn ) { btn.setAttribute( 'data-fraction', fraction ); }
		}

		/** Open the shared coverage modal for a selected topping. */
		function plOpenCoverage( slug ) {
			if ( ! state.toppings[ slug ] || ! covModal ) { return; }
			activeCoverageSlug = slug;
			var current = state.toppings[ slug ].coverage || 'whole';
			qa( '.pl-cov-opt' ).forEach( function( opt ) {
				opt.classList.toggle( 'pl-cov-opt--active', opt.getAttribute( 'data-fraction' ) === current );
			} );
			covModal.classList.add( 'pl-cov-modal--open' );
			covModal.setAttribute( 'aria-hidden', 'false' );
		}

		/** Close the coverage modal. */
		function plCloseCoverage() {
			activeCoverageSlug = null;
			if ( covModal ) {
				covModal.classList.remove( 'pl-cov-modal--open' );
				covModal.setAttribute( 'aria-hidden', 'true' );
			}
		}

		/** Apply the chosen coverage to the active topping. */
		function plChooseCoverage( fraction ) {
			if ( activeCoverageSlug && state.toppings[ activeCoverageSlug ] ) {
				var slug = activeCoverageSlug;
				state.toppings[ slug ].coverage = fraction;

				var itemEl = root.querySelector( '.pl-item--topping[data-slug="' + slug + '"]' );
				plUpdateCoverageChip( itemEl, fraction );

				/* Best-effort visual coverage: the base layer system styles toppings
				   via tcg-* classes on #pizzatier-topping-{slug}. */
				var layerEl = document.getElementById( 'pizzatier-topping-' + slug );
				if ( layerEl ) {
					layerEl.className = layerEl.className.replace( /\btcg-[a-z-]+\b/g, '' ).replace( /\s+/g, ' ' ).trim();
					layerEl.classList.add( 'tcg-' + fraction );
				}

				refreshSummary();
				dispatchChange();
			}
			plCloseCoverage();
		}

		/* Close the modal on Escape. */
		if ( covModal ) {
			document.addEventListener( 'keydown', function( e ) {
				if ( ( e.key === 'Escape' || e.keyCode === 27 ) && covModal.classList.contains( 'pl-cov-modal--open' ) ) {
					plCloseCoverage();
				}
			} );
		}

		// ── Utility ───────────────────────────────────────────────────

		function escHtml( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}

		// ── Public API ────────────────────────────────────────────────
		var api = {
			plToggleExclusive: plToggleExclusive,
			plToggleTopping:   plToggleTopping,
			plOpenCoverage:    plOpenCoverage,
			plCloseCoverage:   plCloseCoverage,
			plChooseCoverage:  plChooseCoverage,
			plReset:           plReset,
			setState:          plSetState,
			getState:          function() {
				/* Return both the raw state (for internal use) and a normalised
				   layers array so PizzaTierPro frontend-builder.js can read
				   selections via the standard getTemplateLayersNow() path. */
				var layers = [];
				/* Exclusive layers: crust, sauce, cheese, drizzle, cut */
				Object.keys( state.exclusive ).forEach( function( layerType ) {
					var e = state.exclusive[ layerType ];
					if ( e && e.slug ) {
						layers.push({
							id:        e.slug,
							layerId:   e.slug,
							title:     e.title  || e.slug,
							layerName: e.title  || e.slug,
							type:      layerType,
							layerType: layerType,
							fraction:  'whole',
							coverage:  'whole',
							portion:   '',
							coverageLabel: 'Whole'
						});
					}
				});
				/* Toppings */
				Object.keys( state.toppings ).forEach( function( slug ) {
					var t = state.toppings[ slug ];
					var c = window.PizzaTierCoverage
						? window.PizzaTierCoverage.normalize( t.coverage )
						: { portion: '', fraction: 'whole', label: 'Whole' };
					layers.push({
						id:            slug,
						layerId:       slug,
						title:         t.title  || slug,
						layerName:     t.title  || slug,
						type:          'topping',
						layerType:     'topping',
						/* fraction = generic size (price-grid key); portion = the
						   specific portion the topping sits on (kitchen ticket). */
						fraction:      c.fraction,
						coverage:      t.coverage || 'whole',
						portion:       c.portion,
						coverageLabel: c.label
					});
				});
				return {
					exclusive:    state.exclusive,
					toppings:     state.toppings,
					currentStep:  state.currentStep,
					layers:       layers
				};
			}
		};

		instances[ instanceId ] = api;
		return api;
	}

	// ── Expose namespace ──────────────────────────────────────────────
	window.PL = window.PL || {};
	window.PL.createInstance = createInstance;

	/* PizzaTierAPI — standard surface consumed by PizzaTierPro */
	window.PizzaTierAPI = window.PizzaTierAPI || {
		getState: function ( instanceId ) {
			var inst = instances[ instanceId ];
			return inst ? inst.getState() : null;
		},
		getAllInstances: function () {
			return Object.keys( instances );
		},
		setState: function ( instanceId, newState ) {
			var inst = instances[ instanceId ];
			if ( inst && typeof inst.setState === 'function' ) { inst.setState( newState ); }
		}
	};

}( window, document ) );
