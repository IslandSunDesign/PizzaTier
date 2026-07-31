/**
 * PizzaTier — Admin Product Tab JS
 *
 * Handles the Pizza Configurator meta box:
 *
 *  1. Show/hide meta box body when product type changes.
 *  2. Layer-type tab navigation (Crusts / Sauces / Cheeses / Toppings / etc.)
 *  3. Card interaction:
 *       - Click card body  → set as default for that type (radio for single-select
 *         types; toggle checkbox for toppings which support multiple defaults)
 *       - Click checkbox   → toggle item as "available" in builder
 *  4. "All" checkbox — selects/deselects all available checkboxes in active panel.
 *  5. Live preview via window.PizzaTierAPI.renderPizza() (PizzaTier's JS API).
 *     Falls back to the REST endpoint directly if the API isn't loaded yet.
 *  6. Form validation before product save.
 *
 * Depends on: jQuery, wc-admin-meta-boxes.
 * Localised: window.pizzatier_commerceAdminData
 */

/* global pizzatier_commerceAdminData, jQuery */

( function ( $ ) {
	'use strict';

	const D = window.pizzatier_commerceAdminData || {};
	const I = D.i18n || {};

	// Current default slugs per layer type — used for REST preview calls.
	// Seeded from PHP (defaultSlugs), updated on card clicks.
	// For all types except toppings: string slug.
	// For toppings: array of slug strings (multi-select).
	const currentDefaults = Object.assign( {}, D.defaultSlugs || {} );
	if ( ! Array.isArray( currentDefaults.toppings ) ) {
		currentDefaults.toppings = Array.isArray( D.defaultSlugs && D.defaultSlugs.toppings )
			? D.defaultSlugs.toppings.slice()
			: [];
	}

	// Debounce timer for preview refresh.
	let previewTimer = null;

	// =========================================================================
	// Init
	// =========================================================================

	$( function () {
		initMetaBoxVisibility();
		initTypeTabs();
		initCardClicks();
		initSelectAll();
		initFormValidation();

		// Fire initial preview if defaults already saved.
		const hasSingle  = Object.keys( currentDefaults ).some( k => k !== 'toppings' && currentDefaults[ k ] );
		const hasTopping = currentDefaults.toppings.length > 0;
		if ( hasSingle || hasTopping ) {
			schedulePreview();
		}
	} );

	// =========================================================================
	// 1 — Meta box show/hide
	// =========================================================================

	function initMetaBoxVisibility() {
		const $type = $( 'select#product-type' );

		function sync() {
			const isPizza = $type.val() === 'pizza';
			$( '#pztc-mb-placeholder' ).toggle( ! isPizza );
			$( '#pztc-mb-body' ).toggle( isPizza );
			$( '#pztc-pricegrid-placeholder' ).toggle( ! isPizza );
			$( '#pztc-pricegrid-body' ).toggle( isPizza );
		}

		$type.on( 'change', sync );
		sync();
	}

	// =========================================================================
	// 2 — Type tab navigation
	// =========================================================================

	function initTypeTabs() {
		$( document ).on( 'click', '.pztc-lc-nav__btn', function () {
			const type = $( this ).data( 'tab' );

			// Nav buttons.
			$( '.pztc-lc-nav__btn' )
				.removeClass( 'pztc-lc-nav__btn--active' )
				.attr( 'aria-selected', 'false' );
			$( this )
				.addClass( 'pztc-lc-nav__btn--active' )
				.attr( 'aria-selected', 'true' );

			// Panels.
			$( '.pztc-lc-panel' ).removeClass( 'pztc-lc-panel--active' );
			$( '#pztc-lc-panel-' + type ).addClass( 'pztc-lc-panel--active' );
		} );
	}

	// =========================================================================
	// 3 — Card clicks
	// =========================================================================

	function initCardClicks() {
		// Clicking anywhere on the card (except the available checkbox) toggles
		// or sets the default for that layer type.
		$( document ).on( 'click', '.pztc-lc-card', function ( e ) {
			// If user clicked the available checkbox — don't change the default.
			if ( $( e.target ).hasClass( 'pztc-lc-card__avail' ) ) return;

			const $card      = $( this );
			const type       = $card.data( 'type' );        // singular, e.g. 'crust'
			const typePlural = $card.data( 'type-plural' ); // e.g. 'crusts'
			const slug       = $card.data( 'slug' );
			const $radio     = $card.find( '.pztc-lc-card__radio' );

			if ( typePlural === 'toppings' ) {
				// Multi-select: toggle this card's default state.
				const checked = ! $radio.prop( 'checked' );
				$radio.prop( 'checked', checked );
				$card.toggleClass( 'pztc-lc-card--default', checked );

				if ( checked ) {
					if ( currentDefaults.toppings.indexOf( slug ) === -1 ) {
						currentDefaults.toppings.push( slug );
					}
				} else {
					currentDefaults.toppings = currentDefaults.toppings.filter( s => s !== slug );
				}
			} else {
				// Single-select: radio behaviour — clear panel, set this card.
				$radio.prop( 'checked', true );

				$( '#pztc-lc-panel-' + typePlural + ' .pztc-lc-card' )
					.removeClass( 'pztc-lc-card--default' );
				$card.addClass( 'pztc-lc-card--default' );

				// Store slug so preview can use it.
				currentDefaults[ type ] = slug;
			}

			// Refresh preview.
			schedulePreview();
		} );

		// The available checkbox — toggle enabled class, don't affect default.
		$( document ).on( 'change', '.pztc-lc-card__avail', function () {
			$( this ).closest( '.pztc-lc-card' )
				.toggleClass( 'pztc-lc-card--enabled', this.checked );
		} );
	}

	// =========================================================================
	// 4 — Select all checkbox
	// =========================================================================

	function initSelectAll() {
		$( document ).on( 'change', '.js-select-all', function () {
			const type    = $( this ).data( 'type' ); // plural
			const checked = this.checked;
			$( '#pztc-grid-' + type + ' .pztc-lc-card__avail' )
				.prop( 'checked', checked )
				.trigger( 'change' );
		} );
	}

	// =========================================================================
	// 5 — Live preview
	// =========================================================================

	/**
	 * Schedule a preview refresh after a short debounce so rapid clicks
	 * don't fire multiple simultaneous requests.
	 */
	function schedulePreview() {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( refreshPreview, 350 );
	}

	function refreshPreview() {
		const $stage   = $( '#pztc-preview-stage' );
		const $spinner = $( '#pztc-preview-spinner' );
		const $summary = $( '#pztc-preview-summary' );

		if ( ! $stage.length ) return;

		// Build the payload — only include types that have a default set.
		const payload      = {};
		const summaryParts = [];

		const typeMap = {
			crust:   'Crust',
			sauce:   'Sauce',
			cheese:  'Cheese',
			drizzle: 'Drizzle',
			cut:     'Cut',
		};

		for ( const type in typeMap ) {
			if ( currentDefaults[ type ] ) {
				payload[ type ] = currentDefaults[ type ];
				summaryParts.push( '<strong>' + escHtml( typeMap[ type ] ) + ':</strong> ' + escHtml( currentDefaults[ type ] ) );
			}
		}

		// Toppings: multi-select array.
		if ( currentDefaults.toppings && currentDefaults.toppings.length ) {
			payload.toppings = currentDefaults.toppings.slice();
			summaryParts.push( '<strong>Toppings:</strong> ' + currentDefaults.toppings.map( escHtml ).join( ', ' ) );
		}

		if ( Object.keys( payload ).length === 0 ) {
			$stage.html( '<p class="pztc-lc-preview__empty">' + escHtml( I.previewEmpty || 'Select defaults to preview.' ) + '</p>' );
			$summary.html( '' );
			return;
		}

		$spinner.show();

		// Try PizzaTierAPI first (already on page), then fall back to fetch.
		const apiAvailable = typeof window.PizzaTierAPI !== 'undefined' &&
			typeof window.PizzaTierAPI.renderPizza === 'function';

		const promise = apiAvailable
			? window.PizzaTierAPI.renderPizza( payload )
			: fetchViaRest( payload );

		promise
			.then( function ( html ) {
				$spinner.hide();
				if ( html && html.length > 10 ) {
					$stage.html( html );
					injectPreviewCSS();
				} else {
					$stage.html( '<p class="pztc-lc-preview__empty">' + escHtml( I.previewEmpty || 'Select defaults to preview.' ) + '</p>' );
				}
				$summary.html( summaryParts.join( ' &nbsp;·&nbsp; ' ) );
			} )
			.catch( function () {
				$spinner.hide();
				$stage.html( '<p class="pztc-lc-preview__error">' + escHtml( I.previewError || 'Preview unavailable.' ) + '</p>' );
			} );
	}

	/**
	 * Direct REST fetch fallback when PizzaTierAPI isn't loaded yet.
	 *
	 * @param {Object} payload
	 * @returns {Promise<string>} HTML string
	 */
	function fetchViaRest( payload ) {
		// Pro's own admin-only render route. Self-contained so the preview
		// works even when the base plugin's opt-in REST API is disabled.
		const url   = ( D.restRoot || '/wp-json/' ) + 'pizzatier/v1/render';
		const nonce = D.restNonce || '';

		return window.fetch( url, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   nonce,
			},
			body: JSON.stringify( payload ),
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) { return d.html || ''; } );
	}

	/**
	 * Inject a small <style> block so the pizza stage renders correctly
	 * inside the compact preview area without the full frontend stylesheet.
	 */
	function injectPreviewCSS() {
		if ( document.getElementById( 'pztc-preview-inline-css' ) ) return;
		const style = document.createElement( 'style' );
		style.id = 'pztc-preview-inline-css';
		style.textContent = [
			'#pztc-preview-stage .np-pizza-stage-wrap{position:relative;width:100%;max-width:240px;margin:0 auto;}',
			'#pztc-preview-stage .np-pizza-stage{position:relative;width:100%;padding-bottom:100%;overflow:hidden;}',
			'#pztc-preview-stage .np-pizza-stage > div{position:absolute;top:0;left:0;width:100%;height:100%;}',
			'#pztc-preview-stage .np-pizza-stage img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;}',
			'#pztc-preview-stage .pizzatier-layer-closed{position:absolute;top:0;left:0;width:100%;height:100%;}',
			'#pztc-preview-stage .pizzatier-layer-closed img{width:100%;height:100%;object-fit:contain;}',
		].join( '' );
		document.head.appendChild( style );
	}

	// =========================================================================
	// 6 — Form validation
	// =========================================================================

	function initFormValidation() {
		$( '#post' ).on( 'submit', function ( e ) {
			if ( $( 'select#product-type' ).val() !== 'pizza' ) return;
			const $tpl = $( '#pizzatier_commerce_builder_template' );
			if ( $tpl.length && ! $tpl.val() ) {
				e.preventDefault();
				const msg = I.noTemplate || 'Please select a PizzaTier template before saving.';
				if ( typeof window.wp !== 'undefined' && wp.data && wp.data.dispatch ) {
					wp.data.dispatch( 'core/notices' ).createErrorNotice( msg );
				} else {
					// eslint-disable-next-line no-alert
					alert( msg );
				}
				$( 'html, body' ).animate( { scrollTop: $( '#pztc-mb-body' ).offset().top - 80 }, 300 );
				$tpl.focus();
			}
		} );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

} )( jQuery );

/* Pricing mode description hints */
	(function(){
		var grid  = document.getElementById('pztc-prod-mode-grid');
		var input = document.getElementById('pizzatier_commerce_pricing_mode_product');
		var hint  = document.getElementById('pztc-pricing-mode-hint');
		if (!grid || !input) return;

		var hints = {
			'':               '',
			'addon_per_layer':'Each layer add-on is looked up from the price grid cell (Size × Coverage). Most flexible.',
			'flat_per_size':  'Grid "Whole" column for the selected size is charged once for the whole pizza, regardless of layer count.',
			'highest_wins':   'All layers are reviewed; only the grid price of the most expensive one is added to the total.',
			'tiered_by_count':'Name your grid coverage columns Tier1, Tier2, Tier3 etc. Set thresholds in PizzaTier → Settings → Pricing Engine.',
			'free_first_n':   'Configure "Free toppings included" count in PizzaTier → Settings → Pricing Engine.',
			'bundle':         'The WooCommerce product price is the full total. The price grid is informational only — no add-ons are applied.',
		};

		grid.querySelectorAll('.pztc-prod-mode-card').forEach(function(card){
			card.style.setProperty('--pmc', card.dataset.color || '#6b7280');
			card.addEventListener('click', function(){
				grid.querySelectorAll('.pztc-prod-mode-card').forEach(function(c){
					c.classList.remove('pztc-prod-mode-card--selected');
					c.setAttribute('aria-pressed','false');
				});
				card.classList.add('pztc-prod-mode-card--selected');
				card.setAttribute('aria-pressed','true');
				input.value = card.dataset.mode;
				if (hint) hint.textContent = hints[card.dataset.mode] || '';
			});
			card.addEventListener('keydown', function(e){
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
			});
		});

		// Init hint for current selection
		var initCard = grid.querySelector('.pztc-prod-mode-card--selected');
		if (hint && initCard) hint.textContent = hints[initCard.dataset.mode] || '';
	})();
