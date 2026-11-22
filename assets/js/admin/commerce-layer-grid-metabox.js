/* PizzaTier Commerce — Layer grid meta box interactions.
 * Moved out of an inline <script> block (WordPress.org compliance, v2.3.0).
 * Data arrives via wp_add_inline_script → window.pizzatierLayerGridData.
 */
		/* PizzaTier — Layer grid meta box interactions */
		( function () {
			'use strict';

			var DATA         = window.pizzatierLayerGridData || {};
			// Decode once (WooCommerce sends entities such as '&#36;'); it is
			// escaped again wherever it is written into markup.
			var currency     = ( function ( raw ) {
				var t = document.createElement( 'textarea' );
				t.innerHTML = String( raw || '$' );
				return t.value;
			}( DATA.currency ) );
			var i18n         = DATA.i18n || {};
			var AJAX         = DATA.ajaxUrl || '';
			var NONCE        = DATA.nonce || '';
			var PLACEHOLDERS = DATA.placeholders || {};
			var HAS_GLOBAL   = !! DATA.hasGlobal;
			var layerProductsLoaded = false;

			// Per-cell placeholder lookup. Falls back to '0.00' when the
			// global grid has no value for this cell — keeps the input
			// looking the same as before when no global fallback applies.
			function placeholderFor( key ) {
				if ( PLACEHOLDERS && Object.prototype.hasOwnProperty.call( PLACEHOLDERS, key ) ) {
					var v = PLACEHOLDERS[ key ];
					if ( v !== '' && v !== null && typeof v !== 'undefined' ) {
						return String( v );
					}
				}
				return '0.00';
			}

			// ── Helpers ────────────────────────────────────────────────────────

			function cellKey( size, fraction ) {
				return size + '|' + fraction;
			}

			function getCurrentSizes() {
				var sizes = [];
				document.querySelectorAll( '#pztc-layer-grid-table .pztc-size-label-input' ).forEach( function ( inp ) {
					if ( inp.value ) sizes.push( inp.value );
				} );
				return sizes;
			}

			function getCurrentFractions() {
				var fractions = [];
				document.querySelectorAll( '#pztc-layer-grid-table .pztc-fraction-label-input' ).forEach( function ( inp ) {
					if ( inp.value ) fractions.push( inp.value );
				} );
				return fractions;
			}

			function makePriceCell( size, fraction, value ) {
				var key = cellKey( size, fraction );
				var td  = document.createElement( 'td' );
				td.className = 'pztc-grid-cell';
				td.setAttribute( 'data-size', size );
				td.setAttribute( 'data-fraction', fraction );
				td.innerHTML =
					'<div class="pztc-cell-wrap">' +
					'<span class="pztc-cell-currency">' + escHtml( currency ) + '</span>' +
					'<input type="number"' +
					' name="pizzatier_commerce_layer_grid[cells][' + escAttr( key ) + ']"' +
					' value="' + escAttr( value || '' ) + '"' +
					' class="pztc-price-input"' +
					' min="0" step="0.01" placeholder="' + escAttr( placeholderFor( key ) ) + '"' +
					' data-key="' + escAttr( key ) + '" />' +
					'</div>';
				return td;
			}

			function makeRemoveBtn( attr, val, labelAttr ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'pztc-grid-remove-btn';
				btn.setAttribute( attr, val );
				btn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
				return btn;
			}

			// ── Add Size row ────────────────────────────────────────────────────

			var addSizeBtn = document.getElementById( 'pztc-layer-add-size-row' );
			if ( addSizeBtn ) {
				addSizeBtn.addEventListener( 'click', function () {
					var label = prompt( i18n.newSize || 'New Size' );
					if ( ! label ) return;
					label = label.trim();
					if ( ! label ) return;

					var fractions = getCurrentFractions();
					var tbody = document.querySelector( '#pztc-layer-grid-table tbody' );
					if ( ! tbody ) return;

					var tr = document.createElement( 'tr' );
					tr.className = 'pztc-grid-row';
					tr.setAttribute( 'data-size', label );

					// Row header cell
					var th = document.createElement( 'th' );
					th.className = 'pztc-grid-th pztc-grid-th--size';
					var wrap = document.createElement( 'div' );
					wrap.className = 'pztc-header-cell';

					var span = document.createElement( 'span' );
					span.className = 'pztc-header-label pztc-editable-label';
					span.setAttribute( 'contenteditable', 'true' );
					span.setAttribute( 'data-type', 'size' );
					span.setAttribute( 'data-original', label );
					span.textContent = label;

					var hiddenInput = document.createElement( 'input' );
					hiddenInput.type = 'hidden';
					hiddenInput.name = 'pizzatier_commerce_layer_grid[sizes][]';
					hiddenInput.value = label;
					hiddenInput.className = 'pztc-size-label-input';

					var removeBtn = makeRemoveBtn( 'data-size', label );
					removeBtn.className += ' pztc-remove-row';
					removeBtn.addEventListener( 'click', function () {
						if ( confirm( i18n.confirmRemoveRow || 'Remove this row?' ) ) {
							tr.remove();
						}
					} );

					// Wire contenteditable → hidden input sync
					span.addEventListener( 'input', function () {
						hiddenInput.value = span.textContent.trim();
						tr.setAttribute( 'data-size', span.textContent.trim() );
						// Re-key all cell inputs in this row
						tr.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
							var frac = inp.closest( 'td' ).getAttribute( 'data-fraction' );
							var newKey = cellKey( span.textContent.trim(), frac );
							inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
							inp.setAttribute( 'data-key', newKey );
						} );
					} );

					wrap.appendChild( span );
					wrap.appendChild( hiddenInput );
					wrap.appendChild( removeBtn );
					th.appendChild( wrap );
					tr.appendChild( th );

					// Price cells for each existing fraction column
					fractions.forEach( function ( frac ) {
						tr.appendChild( makePriceCell( label, frac, '' ) );
					} );

					tbody.appendChild( tr );
				} );
			}

			// ── Add Coverage column ─────────────────────────────────────────────

			var addFracBtn = document.getElementById( 'pztc-layer-add-fraction-col' );
			if ( addFracBtn ) {
				addFracBtn.addEventListener( 'click', function () {
					var label = prompt( i18n.newFraction || 'New Coverage' );
					if ( ! label ) return;
					label = label.trim();
					if ( ! label ) return;

					var table = document.getElementById( 'pztc-layer-grid-table' );
					if ( ! table ) return;

					// Add header <th>
					var headerRow = table.querySelector( 'thead tr' );
					if ( ! headerRow ) return;

					var th = document.createElement( 'th' );
					th.className = 'pztc-grid-th pztc-grid-th--fraction';
					th.setAttribute( 'data-fraction', label );

					var wrap = document.createElement( 'div' );
					wrap.className = 'pztc-header-cell';

					var span = document.createElement( 'span' );
					span.className = 'pztc-header-label pztc-editable-label';
					span.setAttribute( 'contenteditable', 'true' );
					span.setAttribute( 'data-type', 'fraction' );
					span.setAttribute( 'data-original', label );
					span.textContent = label;

					var hiddenInput = document.createElement( 'input' );
					hiddenInput.type  = 'hidden';
					hiddenInput.name  = 'pizzatier_commerce_layer_grid[fractions][]';
					hiddenInput.value = label;
					hiddenInput.className = 'pztc-fraction-label-input';

					var removeBtn = makeRemoveBtn( 'data-fraction', label );
					removeBtn.className += ' pztc-remove-col';
					removeBtn.addEventListener( 'click', function () {
						if ( confirm( i18n.confirmRemoveCol || 'Remove this column?' ) ) {
							var frac = label;
							th.remove();
							table.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
								var cell = row.querySelector( 'td[data-fraction="' + frac + '"]' );
								if ( cell ) cell.remove();
							} );
						}
					} );

					// Sync contenteditable → hidden input + re-key cells in this column
					span.addEventListener( 'input', function () {
						var newLabel = span.textContent.trim();
						hiddenInput.value = newLabel;
						th.setAttribute( 'data-fraction', newLabel );
						table.querySelectorAll( 'tbody td[data-fraction="' + label + '"]' ).forEach( function ( td ) {
							var size   = td.getAttribute( 'data-size' );
							var newKey = cellKey( size, newLabel );
							td.setAttribute( 'data-fraction', newLabel );
							var inp = td.querySelector( '.pztc-price-input' );
							if ( inp ) {
								inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
								inp.setAttribute( 'data-key', newKey );
							}
						} );
						label = newLabel;
					} );

					wrap.appendChild( span );
					wrap.appendChild( hiddenInput );
					wrap.appendChild( removeBtn );
					th.appendChild( wrap );
					headerRow.appendChild( th );

					// Add price cell to every existing body row
					var sizes = getCurrentSizes();
					var bodyRows = table.querySelectorAll( 'tbody tr' );
					bodyRows.forEach( function ( row, idx ) {
						row.appendChild( makePriceCell( sizes[ idx ] || '', label, '' ) );
					} );
				} );
			}

			// ── Remove row / column (delegate for initial rows) ─────────────────

			var tableWrap = document.getElementById( 'pztc-layer-grid-table-wrap' );
			if ( tableWrap ) {
				tableWrap.addEventListener( 'click', function ( e ) {
					var btn = e.target.closest( '.pztc-remove-row' );
					if ( btn ) {
						if ( confirm( i18n.confirmRemoveRow || 'Remove this row?' ) ) {
							var row = btn.closest( 'tr' );
							if ( row ) row.remove();
						}
						return;
					}
					btn = e.target.closest( '.pztc-remove-col' );
					if ( btn ) {
						if ( confirm( i18n.confirmRemoveCol || 'Remove this column?' ) ) {
							// By position: rebuilt or renamed columns carry no
							// (or a stale) data-fraction on their button.
							var colTh = btn.closest( 'th' );
							var table = document.getElementById( 'pztc-layer-grid-table' );
							if ( ! table || ! colTh ) return;
							var idx = colTh.cellIndex;
							table.querySelectorAll( 'tbody tr' ).forEach( function ( tr ) {
								if ( tr.cells[ idx ] ) { tr.cells[ idx ].remove(); }
							} );
							colTh.remove();
						}
					}
				} );

				// Sync contenteditable size/fraction labels → hidden inputs (initial rows)
				tableWrap.addEventListener( 'input', function ( e ) {
					var span = e.target.closest( '.pztc-editable-label' );
					if ( ! span ) return;
					var type   = span.getAttribute( 'data-type' );
					var newVal = span.textContent.trim();

					if ( type === 'size' ) {
						var hiddenInp = span.closest( 'th' ).querySelector( '.pztc-size-label-input' );
						if ( hiddenInp ) hiddenInp.value = newVal;
						var row = span.closest( 'tr' );
						if ( row ) {
							row.setAttribute( 'data-size', newVal );
							row.querySelectorAll( '.pztc-price-input' ).forEach( function ( inp ) {
								var frac   = inp.closest( 'td' ).getAttribute( 'data-fraction' );
								var newKey = cellKey( newVal, frac );
								inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
								inp.setAttribute( 'data-key', newKey );
							} );
						}
					} else if ( type === 'fraction' ) {
						var hiddenInp = span.closest( 'th' ).querySelector( '.pztc-fraction-label-input' );
						if ( hiddenInp ) hiddenInp.value = newVal;
						var oldFrac = span.getAttribute( 'data-original' );
						var table   = document.getElementById( 'pztc-layer-grid-table' );
						if ( table ) {
							table.querySelectorAll( 'td[data-fraction="' + oldFrac + '"]' ).forEach( function ( td ) {
								td.setAttribute( 'data-fraction', newVal );
								var inp = td.querySelector( '.pztc-price-input' );
								if ( inp ) {
									var size   = td.getAttribute( 'data-size' );
									var newKey = cellKey( size, newVal );
									inp.name = 'pizzatier_commerce_layer_grid[cells][' + newKey + ']';
									inp.setAttribute( 'data-key', newKey );
								}
							} );
							var colTh = table.querySelector( 'th[data-fraction="' + oldFrac + '"]' );
							if ( colTh ) colTh.setAttribute( 'data-fraction', newVal );
						}
						span.setAttribute( 'data-original', newVal );
					}
				} );
			}

			// ── Clear custom prices ─────────────────────────────────────────────

			var clearBtn  = document.getElementById( 'pztc-layer-grid-clear' );
			var clearFlag = document.getElementById( 'pztc-layer-grid-clear-flag' );
			if ( clearBtn && clearFlag ) {
				clearBtn.addEventListener( 'click', function () {
					if ( ! confirm( i18n.confirmClear || 'Remove all custom prices for this ingredient?' ) ) {
						return;
					}
					clearFlag.value = '1';

					// Visually zero-out all cells so the admin can see the state.
					document.querySelectorAll( '#pztc-layer-grid-table .pztc-price-input' ).forEach( function ( inp ) {
						inp.value = '';
					} );

					// Update badge to fallback state. Prefer the global-fallback
					// styling/text when a site-wide grid exists for this layer
					// type — that's where the engine will actually source
					// prices once this clear is saved.
					var badge = document.getElementById( 'pztc-layer-grid-badge' );
					if ( badge ) {
						var fbColor, fbIcon, fbText;
						if ( HAS_GLOBAL ) {
							fbColor = '#2563eb';
							fbIcon  = 'dashicons-admin-site-alt3';
							fbText  = i18n.badgeGlobal || 'Using global fallback.';
						} else {
							fbColor = '#d97706';
							fbIcon  = 'dashicons-info-outline';
							fbText  = i18n.badgeFallback || 'Using product fallback.';
						}
						badge.style.background  = fbColor + '1a';
						badge.style.borderColor = fbColor;
						badge.style.color       = fbColor;
						var icon = badge.querySelector( '.dashicons' );
						if ( icon ) {
							icon.className     = 'dashicons ' + fbIcon;
							icon.style.color   = fbColor;
						}
						badge.childNodes[ badge.childNodes.length - 1 ].textContent = ' ' + fbText;
					}

					clearBtn.disabled = true;
				} );
			}

			// ── Feedback helper ────────────────────────────────────────────────

			function showFeedback( message, type ) {
				var fb = document.getElementById( 'pztc-layer-grid-feedback' );
				if ( ! fb ) {
					fb = document.createElement( 'div' );
					fb.id = 'pztc-layer-grid-feedback';
					fb.className = 'pztc-grid-import-feedback';
					fb.style.cssText = 'display:none;';
					var wrap = document.getElementById( 'pztc-layer-grid-table-wrap' );
					if ( wrap && wrap.parentNode ) {
						wrap.parentNode.insertBefore( fb, wrap );
					}
				}
				fb.className = 'pztc-grid-import-feedback is-' + type;
				fb.textContent = message;
				fb.style.display = 'block';
				if ( type === 'success' ) {
					setTimeout( function () { fb.style.display = 'none'; }, 5000 );
				}
			}

			// ── Panel helpers ──────────────────────────────────────────────────

			function closePanels( keepId ) {
				var panels = [
					'pztc-layer-paste-csv-panel',
					'pztc-layer-copy-product-panel',
					'pztc-layer-set-all-panel',
				];
				panels.forEach( function ( id ) {
					if ( id !== keepId ) {
						var el = document.getElementById( id );
						if ( el ) el.style.display = 'none';
					}
				} );
			}

			function togglePanel( id ) {
				var el = document.getElementById( id );
				if ( ! el ) return;
				closePanels( id );
				el.style.display = ( el.style.display === 'none' || el.style.display === '' ) ? 'block' : 'none';
			}

			// ── CSV builder from current layer grid ────────────────────────────

			function buildCsvFromLayerGrid() {
				var table = document.getElementById( 'pztc-layer-grid-table' );
				if ( ! table ) return '';

				var fractions = [];
				table.querySelectorAll( 'thead tr th.pztc-grid-th--fraction' ).forEach( function ( th ) {
					fractions.push( th.getAttribute( 'data-fraction' ) || '' );
				} );

				var rows = [ [ 'Size' ].concat( fractions ) ];

				table.querySelectorAll( 'tbody tr.pztc-grid-row' ).forEach( function ( tr ) {
					var size = tr.getAttribute( 'data-size' ) || '';
					var row  = [ size ];
					fractions.forEach( function ( frac ) {
						var cell = tr.querySelector( 'td[data-fraction="' + frac + '"]' );
						var inp  = cell ? cell.querySelector( '.pztc-price-input' ) : null;
						row.push( inp ? ( inp.value || '0.00' ) : '0.00' );
					} );
					rows.push( row );
				} );

				if ( rows.length < 2 ) return '';

				return rows.map( function ( row ) {
					return row.map( function ( cell ) {
						var s = String( cell );
						if ( s.indexOf( ',' ) !== -1 || s.indexOf( '"' ) !== -1 ) {
							return '"' + s.replace( /"/g, '""' ) + '"';
						}
						return s;
					} ).join( ',' );
				} ).join( '\n' );
			}

			// ── AJAX helper ────────────────────────────────────────────────────

			function ajaxPost( params, success, fail ) {
				var xhr = new XMLHttpRequest();
				xhr.open( 'POST', AJAX );
				xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
				xhr.onload = function () {
					try {
						var resp = JSON.parse( xhr.responseText );
						if ( resp && resp.success ) {
							success( resp );
						} else {
							fail( resp );
						}
					} catch ( e ) {
						fail( null );
					}
				};
				xhr.onerror = function () { fail( null ); };

				var body = Object.keys( params ).map( function ( k ) {
					return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
				} ).join( '&' );
				xhr.send( body );
			}

			// ── Rebuild layer grid table from data ─────────────────────────────

			function rebuildLayerGrid( sizes, fractions, cells ) {
				var wrap  = document.getElementById( 'pztc-layer-grid-table-wrap' );
				if ( ! wrap ) return;

				// Build header.
				var theadHtml = '<thead><tr>';
				theadHtml += '<th class="pztc-grid-corner"><span class="pztc-grid-corner-row">Size</span><span class="pztc-grid-corner-sep">&#8595;/&#8594;</span><span class="pztc-grid-corner-col">Coverage</span></th>';
				fractions.forEach( function ( frac ) {
					var safe = escAttr( frac );
					theadHtml += '<th class="pztc-grid-th pztc-grid-th--fraction" data-fraction="' + safe + '">'
						+ '<div class="pztc-header-cell">'
						+ '<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="fraction" data-original="' + safe + '">' + escHtml( frac ) + '</span>'
						+ '<input type="hidden" name="pizzatier_commerce_layer_grid[fractions][]" value="' + safe + '" class="pztc-fraction-label-input" />'
						+ '<button type="button" class="pztc-remove-col pztc-grid-remove-btn"><span class="dashicons dashicons-no-alt"></span></button>'
						+ '</div></th>';
				} );
				theadHtml += '</tr></thead>';

				// Build tbody.
				var tbodyHtml = '<tbody>';
				sizes.forEach( function ( size ) {
					var safeSize = escAttr( size );
					tbodyHtml += '<tr class="pztc-grid-row" data-size="' + safeSize + '">'
						+ '<th class="pztc-grid-th pztc-grid-th--size">'
						+ '<div class="pztc-header-cell">'
						+ '<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="size" data-original="' + safeSize + '">' + escHtml( size ) + '</span>'
						+ '<input type="hidden" name="pizzatier_commerce_layer_grid[sizes][]" value="' + safeSize + '" class="pztc-size-label-input" />'
						+ '<button type="button" class="pztc-remove-row pztc-grid-remove-btn"><span class="dashicons dashicons-no-alt"></span></button>'
						+ '</div></th>';
					fractions.forEach( function ( frac ) {
						var key   = size + '|' + frac;
						var price = cells && cells[ key ] !== undefined ? cells[ key ] : '';
						tbodyHtml += '<td class="pztc-grid-cell" data-size="' + escAttr( size ) + '" data-fraction="' + escAttr( frac ) + '">'
							+ '<div class="pztc-cell-wrap">'
							+ '<span class="pztc-cell-currency">' + escHtml( currency ) + '</span>'
							+ '<input type="number" name="pizzatier_commerce_layer_grid[cells][' + escAttr( key ) + ']"'
							+ ' value="' + escAttr( price ) + '"'
							+ ' class="pztc-price-input" min="0" step="0.01" placeholder="' + escAttr( placeholderFor( key ) ) + '"'
							+ ' data-key="' + escAttr( key ) + '" />'
							+ '</div></td>';
					} );
					tbodyHtml += '</tr>';
				} );
				tbodyHtml += '</tbody>';

				var newTable = document.createElement( 'div' );
				newTable.className = 'pztc-grid-table-container';
				newTable.innerHTML = '<table class="pztc-grid-table pztc-grid-table--layer" id="pztc-layer-grid-table">' + theadHtml + tbodyHtml + '</table>';

				var oldContainer = wrap.querySelector( '.pztc-grid-table-container' );
				if ( oldContainer ) {
					wrap.replaceChild( newTable, oldContainer );
				} else {
					wrap.appendChild( newTable );
				}
			}

			function escHtml( str ) {
				return String( str )
					.replace( /&/g, '&amp;' )
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' )
					.replace( /"/g, '&quot;' )
					.replace( /'/g, '&#039;' );
			}
			function escAttr( str ) { return escHtml( str ); }

			// ── Copy CSV ────────────────────────────────────────────────────────

			var copyCsvBtn = document.getElementById( 'pztc-layer-copy-csv' );
			if ( copyCsvBtn ) {
				copyCsvBtn.addEventListener( 'click', function () {
					var csv = buildCsvFromLayerGrid();
					if ( ! csv ) {
						showFeedback( i18n.copyCsvFail || 'Nothing to copy.', 'error' );
						return;
					}
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( csv ).then( function () {
							showFeedback( i18n.copyCsvSuccess || 'Copied to clipboard.', 'success' );
						} ).catch( function () {
							showFeedback( i18n.copyCsvFail || 'Could not copy.', 'error' );
						} );
					} else {
						var ta = document.createElement( 'textarea' );
						ta.value = csv;
						ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
						document.body.appendChild( ta );
						ta.select();
						try {
							document.execCommand( 'copy' );
							showFeedback( i18n.copyCsvSuccess || 'Copied to clipboard.', 'success' );
						} catch ( e ) {
							showFeedback( i18n.copyCsvFail || 'Could not copy.', 'error' );
						}
						document.body.removeChild( ta );
					}
				} );
			}

			// ── Paste CSV ───────────────────────────────────────────────────────

			var pasteCsvToggle = document.getElementById( 'pztc-layer-paste-csv-toggle' );
			if ( pasteCsvToggle ) {
				pasteCsvToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-paste-csv-panel' );
				} );
			}

			var pasteCsvCancel = document.getElementById( 'pztc-layer-paste-csv-cancel' );
			if ( pasteCsvCancel ) {
				pasteCsvCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-paste-csv-panel' ).style.display = 'none';
					document.getElementById( 'pztc-layer-paste-csv-text' ).value = '';
				} );
			}

			var pasteCsvApply = document.getElementById( 'pztc-layer-paste-csv-apply' );
			if ( pasteCsvApply ) {
				pasteCsvApply.addEventListener( 'click', function () {
					var csvText = document.getElementById( 'pztc-layer-paste-csv-text' ).value.trim();
					if ( ! csvText ) {
						showFeedback( i18n.pasteCsvError || 'No CSV text.', 'error' );
						return;
					}
					pasteCsvApply.disabled = true;

					ajaxPost(
						{ action: 'pizzatier_commerce_validate_csv_text', nonce: NONCE, csv_text: csvText },
						function ( resp ) {
							var d = resp.data;
							rebuildLayerGrid( d.sizes, d.fractions, d.cells );
							showFeedback( i18n.pasteCsvSuccess || 'Grid applied. Review and save.', 'success' );
							document.getElementById( 'pztc-layer-paste-csv-panel' ).style.display = 'none';
							document.getElementById( 'pztc-layer-paste-csv-text' ).value = '';
							pasteCsvApply.disabled = false;
						},
						function ( resp ) {
							var msg = ( resp && resp.data && resp.data.message ) || 'Error.';
							showFeedback( ( i18n.pasteCsvError || 'Error: ' ) + msg, 'error' );
							pasteCsvApply.disabled = false;
						}
					);
				} );
			}

			// ── Copy from Product ───────────────────────────────────────────────

			var copyProdToggle = document.getElementById( 'pztc-layer-copy-product-toggle' );
			if ( copyProdToggle ) {
				copyProdToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-copy-product-panel' );

					if ( ! layerProductsLoaded ) {
						layerProductsLoaded = true;
						var sel  = document.getElementById( 'pztc-layer-copy-product-select' );
						var applyBtn = document.getElementById( 'pztc-layer-copy-product-apply' );
						sel.disabled = true;
						applyBtn.disabled = true;

						ajaxPost(
							{ action: 'pizzatier_commerce_get_pizza_products', nonce: NONCE, exclude_id: 0 },
							function ( resp ) {
								var products = resp.data.products || [];
								sel.innerHTML = '';
								if ( products.length ) {
									var opt0 = document.createElement( 'option' );
									opt0.value = '';
									opt0.textContent = '— Select a product —';
									sel.appendChild( opt0 );
									products.forEach( function ( p ) {
										var opt = document.createElement( 'option' );
										opt.value = p.id;
										opt.textContent = p.title + ( p.hasGrid ? '' : ' (no grid)' );
										sel.appendChild( opt );
									} );
								} else {
									var optNone = document.createElement( 'option' );
									optNone.value = '';
									optNone.textContent = i18n.noGridProducts || '— No Pizza products found —';
									sel.appendChild( optNone );
								}
								sel.disabled = false;
								applyBtn.disabled = false;
							},
							function () {
								sel.innerHTML = '<option value="">— Error loading products —</option>';
								sel.disabled = false;
							}
						);
					}
				} );
			}

			var copyProdCancel = document.getElementById( 'pztc-layer-copy-product-cancel' );
			if ( copyProdCancel ) {
				copyProdCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-copy-product-panel' ).style.display = 'none';
				} );
			}

			var copyProdApply = document.getElementById( 'pztc-layer-copy-product-apply' );
			if ( copyProdApply ) {
				copyProdApply.addEventListener( 'click', function () {
					var sourceId = parseInt( document.getElementById( 'pztc-layer-copy-product-select' ).value, 10 );
					if ( ! sourceId ) {
						showFeedback( i18n.copyProductNone || 'Please select a product.', 'error' );
						return;
					}
					if ( ! confirm( i18n.confirmCopyProduct || 'Replace the current grid?' ) ) {
						return;
					}
					copyProdApply.disabled = true;

					ajaxPost(
						{ action: 'pizzatier_commerce_get_product_grid', nonce: NONCE, product_id: sourceId },
						function ( resp ) {
							var d = resp.data;
							rebuildLayerGrid( d.sizes, d.fractions, d.cells );
							showFeedback( i18n.copyProductSuccess || 'Grid copied. Review and save.', 'success' );
							document.getElementById( 'pztc-layer-copy-product-panel' ).style.display = 'none';
							copyProdApply.disabled = false;
						},
						function () {
							showFeedback( i18n.copyProductError || 'Could not load grid.', 'error' );
							copyProdApply.disabled = false;
						}
					);
				} );
			}

			// ── Set All ─────────────────────────────────────────────────────────

			var setAllToggle = document.getElementById( 'pztc-layer-set-all-toggle' );
			if ( setAllToggle ) {
				setAllToggle.addEventListener( 'click', function () {
					togglePanel( 'pztc-layer-set-all-panel' );
					setTimeout( function () {
						var inp = document.getElementById( 'pztc-layer-set-all-value' );
						if ( inp ) inp.focus();
					}, 50 );
				} );
			}

			var setAllCancel = document.getElementById( 'pztc-layer-set-all-cancel' );
			if ( setAllCancel ) {
				setAllCancel.addEventListener( 'click', function () {
					document.getElementById( 'pztc-layer-set-all-panel' ).style.display = 'none';
				} );
			}

			var setAllApply = document.getElementById( 'pztc-layer-set-all-apply' );
			if ( setAllApply ) {
				setAllApply.addEventListener( 'click', function () {
					var valStr = document.getElementById( 'pztc-layer-set-all-value' ).value.trim();
					if ( valStr === '' ) {
						showFeedback( i18n.setAllNoValue || 'Please enter a price.', 'error' );
						return;
					}
					var price = parseFloat( valStr );
					if ( isNaN( price ) || price < 0 ) {
						showFeedback( i18n.setAllNoValue || 'Please enter a valid price.', 'error' );
						return;
					}
					var formatted = price.toFixed( 2 );
					document.querySelectorAll( '#pztc-layer-grid-table .pztc-price-input' ).forEach( function ( inp ) {
						inp.value = formatted;
					} );
					showFeedback( i18n.setAllSuccess || 'All cells updated.', 'success' );
					document.getElementById( 'pztc-layer-set-all-panel' ).style.display = 'none';
				} );
			}

			var setAllInput = document.getElementById( 'pztc-layer-set-all-value' );
			if ( setAllInput ) {
				setAllInput.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						if ( setAllApply ) setAllApply.click();
					}
				} );
			}

		} () );
