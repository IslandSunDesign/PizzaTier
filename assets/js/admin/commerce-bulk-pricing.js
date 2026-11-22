/* PizzaTier Commerce — Bulk Pricing editor.
 * Moved out of an inline <script> block (WordPress.org compliance, v2.3.0).
 * Boot config arrives via wp_add_inline_script → window.PIZZATIER_COMMERCE_BULK.
 */
		( function () {
			'use strict';
			var CFG = window.PIZZATIER_COMMERCE_BULK || {};
			var SEP = '|';

			var mount   = document.getElementById( 'pztc-bulk-mount' );
			var typeSel = document.getElementById( 'pztc-bulk-type' );
			var selOnly = document.getElementById( 'pztc-bulk-selected-only' );
			var search  = document.getElementById( 'pztc-bulk-search' );
			var saveBtn = document.getElementById( 'pztc-bulk-save' );
			var resetBtn= document.getElementById( 'pztc-bulk-reset' );
			var dirtyEl = document.getElementById( 'pztc-bulk-dirtycount' );

			// In-memory model of what's currently rendered: type slug -> payload.
			var MODEL = {};

			function cellKey( size, frac ) { return size + SEP + frac; }
			function esc( s ) { var d = document.createElement( 'div' ); d.textContent = s == null ? '' : String( s ); return d.innerHTML; }
			function sprintf1( tpl, n ) { return String( tpl ).replace( '%d', n ); }

			function ajax( action, extra ) {
				var body = new URLSearchParams();
				body.set( 'action', CFG.actions[ action ] );
				body.set( 'nonce', CFG.nonce );
				Object.keys( extra || {} ).forEach( function ( k ) { body.set( k, extra[ k ] ); } );
				return fetch( CFG.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString()
				} ).then( function ( r ) { return r.json(); } );
			}

			// ── Load a scope ──────────────────────────────────────────────
			function load() {
				mount.innerHTML = '<div class="pztc-bulk-loading"><span class="pztc-spinner"></span> ' + esc( CFG.i18n.loading ) + '</div>';
				ajax( 'load', { type: typeSel.value } ).then( function ( res ) {
					if ( ! res || ! res.success ) {
						mount.innerHTML = '<div class="pztc-bulk-empty">' + esc( ( res && res.data && res.data.message ) || 'Error' ) + '</div>';
						return;
					}
					MODEL = {};
					mount.innerHTML = '';
					var any = false;
					res.data.types.forEach( function ( t ) {
						MODEL[ t.type ] = t;
						mount.appendChild( renderSection( t ) );
						if ( t.items.length ) { any = true; }
					} );
					if ( ! any ) {
						mount.innerHTML = '<div class="pztc-bulk-section"><div class="pztc-bulk-empty">' + esc( CFG.i18n.noItems ) + '</div></div>';
					}
					applyFilter();
					recount();
				} ).catch( function () {
					mount.innerHTML = '<div class="pztc-bulk-empty">Network error. Please reload.</div>';
				} );
			}

			// ── Render one type section ───────────────────────────────────
			function renderSection( t ) {
				var sizes = t.sizes, fracs = t.fractions, isFrac = t.is_fraction;
				var cols = [];
				sizes.forEach( function ( s ) { fracs.forEach( function ( f ) { cols.push( { size: s, frac: f } ); } ); } );

				var sec = document.createElement( 'div' );
				sec.className = 'pztc-bulk-section';
				sec.setAttribute( 'data-type', t.type );

				// Head
				var head = '<div class="pztc-bulk-section__head">' +
					'<div><h2 class="pztc-bulk-section__title"><span class="dashicons dashicons-grid-view"></span>' +
					esc( t.label ) + ' <span class="pztc-bulk-section__count">' + t.items.length + '</span></h2>' +
					'<p class="pztc-bulk-section__desc">' + esc( t.desc ) + '</p></div>' +
					'<div class="pztc-bulk-section__tools">' +
					'<button type="button" class="button pztc-bulk-selall"><span class="dashicons dashicons-yes"></span>' + esc( 'Tick all' ) + '</button>' +
					'<button type="button" class="button pztc-bulk-clearcol"><span class="dashicons dashicons-dismiss"></span>' + esc( 'Revert all' ) + '</button>' +
					'</div></div>';

				var capNote = t.capped ? '<p class="pztc-bulk-capnote">' + esc( sprintf1( CFG.i18n.cappedNote, t.cap ) ) + '</p>' : '';

				// Header rows
				var thItem = '<th class="pztc-bulk-th-item"><label class="pztc-bulk-itemcell"><input type="checkbox" class="pztc-bulk-check pztc-bulk-checkall" /> ' + esc( 'Ingredient' ) + '</label></th>';
				var sizeRow = '<tr class="pztc-bulk-sizes">' + thItem;
				sizes.forEach( function ( s ) {
					sizeRow += '<th class="pztc-bulk-col-size" colspan="' + fracs.length + '">' + esc( s ) + '</th>';
				} );
				sizeRow += '<th></th></tr>';

				var fracRow = '';
				if ( isFrac ) {
					fracRow = '<tr class="pztc-bulk-fracs"><th class="pztc-bulk-th-item"></th>';
					sizes.forEach( function ( s ) {
						fracs.forEach( function ( f, i ) {
							fracRow += '<th class="' + ( i === 0 ? 'pztc-bulk-col-size' : '' ) + '">' + esc( f ) + '</th>';
						} );
					} );
					fracRow += '<th></th></tr>';
				}

				// Fill row ("Set all" per column)
				var fillRow = '<tr class="pztc-bulk-fillrow"><th class="pztc-bulk-th-item pztc-bulk-fill-label">' + esc( 'Set all →' ) + '</th>';
				cols.forEach( function ( c, i ) {
					var firstOfSize = ( i % fracs.length === 0 );
					fillRow += '<td class="' + ( firstOfSize ? 'pztc-bulk-col-size' : '' ) + '">' +
						'<input type="text" inputmode="decimal" class="pztc-bulk-fill-input" data-key="' + esc( cellKey( c.size, c.frac ) ) + '" placeholder="—" aria-label="' + esc( 'Set all ' + c.size + ' ' + c.frac ) + '" />' +
						'</td>';
				} );
				fillRow += '<td></td></tr>';

				// Body
				var body = '';
				t.items.forEach( function ( it ) {
					body += renderRow( t, it, cols, fracs.length );
				} );

				sec.innerHTML = head + capNote +
					'<div class="pztc-bulk-table-wrap"><table class="pztc-bulk-table"><thead>' +
					sizeRow + fracRow + fillRow +
					'</thead><tbody>' + body + '</tbody></table></div>';

				wireSection( sec, t, cols );
				return sec;
			}

			function renderRow( t, it, cols, fracCount ) {
				var cells = ( it.cells && typeof it.cells === 'object' ) ? it.cells : {};
				var globals = ( t.global_cells && typeof t.global_cells === 'object' ) ? t.global_cells : {};
				var badge = it.has_grid
					? '<span class="pztc-bulk-badge pztc-bulk-badge--custom" data-role="badge">' + esc( CFG.i18n.custom ) + '</span>'
					: '<span class="pztc-bulk-badge pztc-bulk-badge--fallback" data-role="badge">' + esc( CFG.i18n.fallback ) + '</span>';

				var row = '<tr data-id="' + it.id + '" data-hasgrid="' + ( it.has_grid ? '1' : '0' ) + '">' +
					'<td class="pztc-bulk-td-item"><div class="pztc-bulk-itemcell">' +
						'<input type="checkbox" class="pztc-bulk-check pztc-bulk-rowcheck" />' +
						'<span class="pztc-bulk-itemcell__name">' + esc( it.title ) + '</span>' +
						badge +
						'<button type="button" class="pztc-bulk-rowclear" title="' + esc( CFG.i18n.confirmClear ) + '"><span class="dashicons dashicons-trash"></span></button>' +
					'</div></td>';

				cols.forEach( function ( c, i ) {
					var key = cellKey( c.size, c.frac );
					var val = ( cells[ key ] != null ) ? cells[ key ] : '';
					var ph  = ( globals[ key ] != null ) ? globals[ key ] : '0.00';
					var firstOfSize = ( i % fracCount === 0 );
					row += '<td class="pztc-price-cell ' + ( firstOfSize ? 'pztc-bulk-col-size' : '' ) + '">' +
						'<span class="pztc-price-cell__cur">' + esc( CFG.currency ) + '</span>' +
						'<input type="text" inputmode="decimal" class="pztc-price-input" ' +
							'data-key="' + esc( key ) + '" data-orig="' + esc( val ) + '" ' +
							'value="' + esc( val ) + '" placeholder="' + esc( ph ) + '" ' +
							'aria-label="' + esc( it.title + ' ' + c.size + ' ' + c.frac ) + '" />' +
					'</td>';
				} );
				row += '<td></td></tr>';
				return row;
			}

			// ── Wire interactions for a section ───────────────────────────
			function wireSection( sec, t, cols ) {
				// Price input edits
				sec.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
					inp.addEventListener( 'input', function () { markInput( inp ); recount(); } );
					inp.addEventListener( 'blur', function () { normalise( inp ); } );
				} );

				// Per-column "Set all"
				sec.querySelectorAll( '.pztc-bulk-fill-input' ).forEach( function ( fill ) {
					fill.addEventListener( 'change', function () { fillColumn( sec, fill.getAttribute( 'data-key' ), fill.value ); } );
					fill.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { fillColumn( sec, fill.getAttribute( 'data-key' ), fill.value ); } } );
				} );

				// Row clear buttons
				sec.querySelectorAll( '.pztc-bulk-rowclear' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () { toggleClear( btn.closest( 'tr' ) ); } );
				} );

				// Tick-all in header + tools
				var checkAll = sec.querySelector( '.pztc-bulk-checkall' );
				var selAll   = sec.querySelector( '.pztc-bulk-selall' );
				var clearAll = sec.querySelector( '.pztc-bulk-clearcol' );
				function setAllChecks( on ) {
					sec.querySelectorAll( '.pztc-bulk-rowcheck' ).forEach( function ( c ) {
						if ( ! c.closest( 'tr' ).classList.contains( 'is-hidden' ) ) { c.checked = on; }
					} );
					if ( checkAll ) { checkAll.checked = on; }
				}
				if ( checkAll ) { checkAll.addEventListener( 'change', function () { setAllChecks( checkAll.checked ); } ); }
				if ( selAll )   { selAll.addEventListener( 'click', function () { setAllChecks( true ); } ); }
				if ( clearAll ) {
					clearAll.addEventListener( 'click', function () {
						sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
							if ( tr.classList.contains( 'is-hidden' ) ) { return; }
							if ( selOnly.checked && ! tr.querySelector( '.pztc-bulk-rowcheck' ).checked ) { return; }
							if ( tr.getAttribute( 'data-hasgrid' ) === '1' && ! tr.classList.contains( 'is-cleared' ) ) { toggleClear( tr ); }
						} );
						recount();
					} );
				}
			}

			// ── Editing helpers ───────────────────────────────────────────
			function markInput( inp ) {
				var tr = inp.closest( 'tr' );
				if ( tr.classList.contains( 'is-cleared' ) ) { return; } // ignore edits on rows queued for revert
				var changed = ( inp.value.trim() !== ( inp.getAttribute( 'data-orig' ) || '' ).trim() );
				inp.classList.toggle( 'is-edited', changed );
				refreshRowBadge( tr );
			}

			/**
			 * Accept "1,50" (decimal comma) as well as "1.50"; before 2.4.0
			 * the comma was stripped and "1,50" became 150.00.
			 */
			function toNumberString( raw ) {
				var v = String( raw ).trim();
				if ( v.indexOf( ',' ) !== -1 && v.indexOf( '.' ) === -1 ) { v = v.replace( ',', '.' ); }
				return v.replace( /[^0-9.]/g, '' );
			}

			function normalise( inp ) {
				var v = toNumberString( inp.value );
				if ( v === '' ) { inp.value = ''; markInput( inp ); return; }
				var n = parseFloat( v );
				if ( isNaN( n ) || n < 0 ) { n = 0; }
				inp.value = n.toFixed( 2 );
				markInput( inp );
			}

			function fillColumn( sec, key, raw ) {
				var v = toNumberString( raw );
				if ( v === '' ) { return; }
				var val = parseFloat( v ); if ( isNaN( val ) || val < 0 ) { val = 0; }
				val = val.toFixed( 2 );
				sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
					if ( tr.classList.contains( 'is-hidden' ) || tr.classList.contains( 'is-cleared' ) ) { return; }
					if ( selOnly.checked && ! tr.querySelector( '.pztc-bulk-rowcheck' ).checked ) { return; }
					var inp = tr.querySelector( '.pztc-price-input[data-key="' + cssEsc( key ) + '"]' );
					if ( inp ) { inp.value = val; markInput( inp ); }
				} );
				recount();
			}

			function toggleClear( tr ) {
				var willClear = ! tr.classList.contains( 'is-cleared' );
				// Only meaningful if the item currently has a saved grid.
				if ( willClear && tr.getAttribute( 'data-hasgrid' ) !== '1' ) { return; }
				tr.classList.toggle( 'is-cleared', willClear );
				refreshRowBadge( tr );
				recount();
			}

			function refreshRowBadge( tr ) {
				var badge = tr.querySelector( '[data-role="badge"]' );
				if ( ! badge ) { return; }
				badge.className = 'pztc-bulk-badge';
				badge.setAttribute( 'data-role', 'badge' );
				if ( tr.classList.contains( 'is-cleared' ) ) {
					badge.classList.add( 'pztc-bulk-badge--cleared' ); badge.textContent = CFG.i18n.cleared;
				} else if ( rowEdited( tr ) ) {
					badge.classList.add( 'pztc-bulk-badge--edited' ); badge.textContent = CFG.i18n.edited;
				} else if ( tr.getAttribute( 'data-hasgrid' ) === '1' ) {
					badge.classList.add( 'pztc-bulk-badge--custom' ); badge.textContent = CFG.i18n.custom;
				} else {
					badge.classList.add( 'pztc-bulk-badge--fallback' ); badge.textContent = CFG.i18n.fallback;
				}
			}

			function rowEdited( tr ) {
				return !! tr.querySelector( '.pztc-price-input.is-edited' );
			}
			function rowDirty( tr ) {
				return tr.classList.contains( 'is-cleared' ) || rowEdited( tr );
			}

			function cssEsc( s ) { return String( s ).replace( /["\\]/g, '\\$&' ); }

			// ── Dirty accounting + save enable ────────────────────────────
			function dirtyRows() {
				return Array.prototype.filter.call( document.querySelectorAll( '.pztc-bulk-table tbody tr' ), rowDirty );
			}
			function recount() {
				var n = dirtyRows().length;
				dirtyEl.textContent = n;
				dirtyEl.classList.toggle( 'is-zero', n === 0 );
				saveBtn.disabled = ( n === 0 );
				resetBtn.disabled = ( n === 0 );
			}

			// ── Filtering ─────────────────────────────────────────────────
			function applyFilter() {
				var q = ( search.value || '' ).trim().toLowerCase();
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					var name = tr.querySelector( '.pztc-bulk-itemcell__name' );
					var show = ! q || ( name && name.textContent.toLowerCase().indexOf( q ) !== -1 );
					tr.classList.toggle( 'is-hidden', ! show );
				} );
			}

			// ── Collect + save ────────────────────────────────────────────
			function collect() {
				var payload = [];
				Object.keys( MODEL ).forEach( function ( type ) {
					var t = MODEL[ type ];
					var sec = mount.querySelector( '.pztc-bulk-section[data-type="' + cssEsc( type ) + '"]' );
					if ( ! sec ) { return; }
					sec.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
						if ( ! rowDirty( tr ) ) { return; }
						var id = parseInt( tr.getAttribute( 'data-id' ), 10 );
						if ( tr.classList.contains( 'is-cleared' ) ) {
							payload.push( { post_id: id, clear: true } );
							return;
						}
						var cells = {};
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							var key = inp.getAttribute( 'data-key' );
							var v = inp.value.trim();
							// Blank = not set: falls back to the global / product price.
							if ( v !== '' ) { cells[ key ] = parseFloat( v ); }
						} );
						payload.push( { post_id: id, sizes: t.sizes, fractions: t.fractions, cells: cells } );
					} );
				} );
				return payload;
			}

			function save() {
				var payload = collect();
				if ( ! payload.length ) { toast( CFG.i18n.nothing, 'warn' ); return; }
				saveBtn.disabled = true; saveBtn.textContent = CFG.i18n.saving;
				ajax( 'save', { items: JSON.stringify( payload ) } ).then( function ( res ) {
					saveBtn.textContent = 'Save changes';
					if ( ! res || ! res.success ) {
						toast( ( res && res.data && res.data.message ) || 'Save failed', 'err' );
						saveBtn.disabled = false;
						return;
					}
					var d = res.data;
					// Commit successfully-saved rows into the new baseline in place;
					// rows the server rejected stay dirty so the edit isn't lost.
					commitSaved( ( d.errors || [] ).map( function ( e ) { return String( e.id ); } ) );
					var parts = [];
					if ( d.saved )   { parts.push( sprintf1( CFG.i18n.savedN, d.saved ) ); }
					if ( d.cleared ) { parts.push( sprintf1( CFG.i18n.clearedN, d.cleared ) ); }
					var kind = 'ok';
					var extra = '';
					if ( d.failed ) {
						parts.push( sprintf1( CFG.i18n.failedN, d.failed ) );
						kind = d.saved || d.cleared ? 'warn' : 'err';
						if ( d.errors && d.errors.length ) {
							extra = '<ul class="pztc-bulk-errlist">' + d.errors.slice( 0, 6 ).map( function ( e ) {
								return '<li>' + esc( e.title ) + ': ' + esc( e.message ) + '</li>';
							} ).join( '' ) + '</ul>';
						}
					}
					toast( ( parts.join( ' ' ) || 'Done' ) + extra, kind, !! extra );
					recount();
				} ).catch( function () {
					saveBtn.textContent = 'Save changes';
					saveBtn.disabled = false;
					toast( 'Network error while saving.', 'err' );
				} );
			}

			// After a save, treat current values as the saved baseline (no reload).
			function commitSaved( failedIds ) {
				failedIds = failedIds || [];
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					if ( failedIds.indexOf( String( tr.getAttribute( 'data-id' ) ) ) !== -1 ) { return; }
					if ( tr.classList.contains( 'is-cleared' ) ) {
						// Reverted to fallback: clear values + baseline.
						tr.classList.remove( 'is-cleared' );
						tr.setAttribute( 'data-hasgrid', '0' );
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							inp.value = ''; inp.setAttribute( 'data-orig', '' ); inp.classList.remove( 'is-edited' );
						} );
						refreshRowBadge( tr );
					} else if ( rowEdited( tr ) ) {
						tr.setAttribute( 'data-hasgrid', '1' );
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							inp.setAttribute( 'data-orig', inp.value.trim() );
							inp.classList.remove( 'is-edited' );
						} );
						refreshRowBadge( tr );
					}
				} );
			}

			function reset() {
				document.querySelectorAll( '.pztc-bulk-table tbody tr' ).forEach( function ( tr ) {
					tr.classList.remove( 'is-cleared' );
					tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
						inp.value = inp.getAttribute( 'data-orig' ) || '';
						inp.classList.remove( 'is-edited' );
					} );
					refreshRowBadge( tr );
				} );
				recount();
			}

			// ── Toast ─────────────────────────────────────────────────────
			var toastEl;
			function toast( html, kind, sticky ) {
				if ( ! toastEl ) {
					toastEl = document.createElement( 'div' );
					toastEl.className = 'pztc-bulk-toast';
					document.body.appendChild( toastEl );
				}
				toastEl.className = 'pztc-bulk-toast pztc-bulk-toast--' + ( kind || 'ok' );
				toastEl.innerHTML = html;
				requestAnimationFrame( function () { toastEl.classList.add( 'is-shown' ); } );
				clearTimeout( toastEl._t );
				toastEl._t = setTimeout( function () { toastEl.classList.remove( 'is-shown' ); }, sticky ? 9000 : 4000 );
			}

			// ── Events ────────────────────────────────────────────────────
			typeSel.addEventListener( 'change', function () {
				if ( dirtyRows().length && ! window.confirm( CFG.i18n.dirtyLeave ) ) {
					return;
				}
				load();
			} );
			search.addEventListener( 'input', applyFilter );
			saveBtn.addEventListener( 'click', save );
			resetBtn.addEventListener( 'click', reset );

			window.addEventListener( 'beforeunload', function ( e ) {
				if ( dirtyRows().length ) { e.preventDefault(); e.returnValue = ''; }
			} );

			// Reflect collapsed admin menu for the sticky bar offset.
			if ( document.body.classList.contains( 'folded' ) ) {
				document.body.classList.add( 'folded-menu' );
			}

			// Initial load.
			load();
		} )();
