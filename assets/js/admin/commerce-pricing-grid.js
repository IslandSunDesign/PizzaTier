/* PizzaTier Commerce — Pricing page grid interactions.
 * Moved out of an inline <script> block (WordPress.org compliance, v2.3.0).
 * i18n strings arrive via wp_localize_script → window.pizzatierCommercePricingGrid.
 */
var I18N = ( window.pizzatierCommercePricingGrid && window.pizzatierCommercePricingGrid.i18n ) || {};
		( function () {
			'use strict';

			// ── Outer tab switcher (Engine / Defaults / Grids) ────────────
			var tabs   = document.querySelectorAll( '#pztc-pricing-tabs .pztc-tab' );
			var panels = document.querySelectorAll( '#pztc-pricing-tabs ~ .pztc-panels .pztc-panel' );
			var saveRows = document.querySelectorAll( '.pztc-pricing-save-row' );

			function setSaveRowVisibility( tabId ) {
				saveRows.forEach( function ( row ) {
					var formType = row.getAttribute( 'data-form' );
					var visible = ( tabId === 'engine' || tabId === 'defaults' )
						? ( formType === 'engine' )
						: ( formType === 'grids' );
					row.style.display = visible ? '' : 'none';
				} );
			}

			function activate( targetId ) {
				tabs.forEach( function ( t ) {
					var active = t.dataset.tab === targetId;
					t.classList.toggle( 'pztc-tab--active', active );
					t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );
				panels.forEach( function ( p ) {
					p.classList.toggle( 'pztc-panel--active', p.id === 'pztc-pricing-panel-' + targetId );
				} );
				setSaveRowVisibility( targetId );
			}

			if ( tabs.length ) {
				var initial = window.location.hash
					? window.location.hash.replace( '#', '' )
					: tabs[ 0 ].dataset.tab;
				if ( ! document.getElementById( 'pztc-pricing-panel-' + initial ) ) {
					initial = tabs[ 0 ].dataset.tab;
				}
				activate( initial );

				tabs.forEach( function ( tab ) {
					tab.addEventListener( 'click', function () {
						history.replaceState( null, '', '#' + tab.dataset.tab );
						activate( tab.dataset.tab );
					} );
				} );
			}

			// ── Inner pill nav (per-layer-type grid switcher) ─────────────
			var pills = document.querySelectorAll( '.pztc-grid-pill' );
			var bodies = document.querySelectorAll( '.pztc-grid-body' );

			pills.forEach( function ( pill ) {
				pill.addEventListener( 'click', function () {
					var type = pill.getAttribute( 'data-type' );
					pills.forEach( function ( p ) {
						p.classList.toggle( 'pztc-grid-pill--active', p === pill );
					} );
					bodies.forEach( function ( b ) {
						b.classList.toggle( 'pztc-grid-body--active', b.getAttribute( 'data-type' ) === type );
					} );
				} );
			} );

			// ── Set All buttons ────────────────────────────────────────────
			document.querySelectorAll( '.pztc-grid-set-all-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var type = btn.getAttribute( 'data-type' );
					var val = window.prompt( I18N.setAllPrompt || 'Set every cell in this grid to:', '' );
					if ( null === val ) return;
					var num = parseFloat( val );
					if ( isNaN( num ) || num < 0 ) {
						window.alert( I18N.numberGteZero || 'Please enter a number \u2265 0.' );
						return;
					}
					var formatted = num.toFixed( 2 );
					var body = document.getElementById( 'pztc-grid-body-' + type );
					if ( ! body ) return;
					body.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
						inp.value = formatted;
					} );
				} );
			} );

			// ── Clear-grid buttons ────────────────────────────────────────
			document.querySelectorAll( '.pztc-grid-clear-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var type = btn.getAttribute( 'data-type' );
					if ( ! window.confirm( I18N.confirmClearGlobal || 'Clear this global grid?' ) ) {
						return;
					}
					document.getElementById( 'pztc-global-grids-clear-' + type ).value = '1';
					// Visual feedback — fade the grid body so it's clear something changed.
					var body = document.getElementById( 'pztc-grid-body-' + type );
					if ( body ) {
						body.style.opacity = '0.5';
						body.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
							inp.disabled = true;
						} );
					}
				} );
			} );

			// ── Global grids confirmation modal ─────────────────────────────
			var gridsForm = document.getElementById( 'pztc-global-grids-form' );
			var modal     = document.getElementById( 'pztc-confirm-modal' );
			var modalList = document.getElementById( 'pztc-confirm-modal-list' );

			function findDirtyTypes() {
				var dirty = {};
				document.querySelectorAll( '.pztc-grid-input' ).forEach( function ( inp ) {
					var current  = ( inp.value || '' ).trim();
					var original = ( inp.getAttribute( 'data-original' ) || '' ).trim();
					if ( current !== original ) {
						dirty[ inp.getAttribute( 'data-type' ) ] = true;
					}
				} );
				// Also count any pending clears.
				document.querySelectorAll( '[id^="pztc-global-grids-clear-"]' ).forEach( function ( hid ) {
					if ( '1' === hid.value ) {
						var t = hid.id.replace( 'pztc-global-grids-clear-', '' );
						dirty[ t ] = true;
					}
				} );
				return Object.keys( dirty );
			}

			function typeLabel( slug ) {
				var pill = document.querySelector( '.pztc-grid-pill[data-type="' + slug + '"]' );
				return pill ? pill.textContent.trim() : slug;
			}

			function openModal( dirtyTypes ) {
				if ( ! modal ) return;
				modalList.innerHTML = '';
				dirtyTypes.forEach( function ( t ) {
					var li = document.createElement( 'li' );
					li.textContent = typeLabel( t );
					modalList.appendChild( li );
				} );
				modal.hidden = false;
				// Focus the confirm button for keyboard users.
				var confirmBtn = modal.querySelector( '[data-action="confirm"]' );
				if ( confirmBtn ) confirmBtn.focus();
			}

			function closeModal() {
				if ( modal ) modal.hidden = true;
			}

			if ( gridsForm ) {
				var bypassConfirm = false;
				gridsForm.addEventListener( 'submit', function ( e ) {
					if ( bypassConfirm ) return; // second pass after user confirmed
					var dirty = findDirtyTypes();
					if ( 0 === dirty.length ) {
						// Nothing changed — let the form submit (will show "nothing to save" notice).
						return;
					}
					e.preventDefault();
					openModal( dirty );
				} );

				modal.addEventListener( 'click', function ( e ) {
					var action = e.target.getAttribute( 'data-action' );
					if ( 'cancel' === action ) {
						closeModal();
					} else if ( 'confirm' === action ) {
						bypassConfirm = true;
						closeModal();
						gridsForm.submit();
					}
				} );

				document.addEventListener( 'keydown', function ( e ) {
					if ( ! modal.hidden && 'Escape' === e.key ) {
						closeModal();
					}
				} );
			}
		} () );
