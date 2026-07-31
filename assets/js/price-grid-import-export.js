/**
 * PizzaTier — Price Grid Import / Export & Dynamic Grid Interactions
 *
 * Handles:
 *   1. Export CSV  — posts to AJAX handler which streams the file.
 *   2. Import CSV  — FileReader parses the file client-side, populates inputs,
 *                    then validates via AJAX before the user saves.
 *   3. Download blank template.
 *   4. Dynamic row/column addition (Add Size, Add Fraction buttons).
 *   5. Row/column removal (× buttons on headers).
 *   6. Inline label renaming (contenteditable spans synced to hidden inputs).
 *
 * Depends on: jQuery, pizzatier_commerceAdminData (localised by ProductTab::enqueue_assets).
 */

/* global pizzatier_commerceAdminData, jQuery */

( function ( $ ) {
	'use strict';

	const DATA    = window.pizzatier_commerceAdminData || {};
	const I18N    = DATA.i18n || {};
	const AJAX    = DATA.ajaxUrl || '';
	const NONCE   = DATA.nonce || '';
	const PROD_ID = DATA.productId || 0;
	const CUR     = DATA.currencySymbol || '$';

	// -------------------------------------------------------------------------
	// Initialise
	// -------------------------------------------------------------------------

	$( function () {
		initExport();
		initBlankTemplate();
		initImport();
		initAddRow();
		initAddCol();
		initRemoveRow();
		initRemoveCol();
		initInlineLabelSync();
		initModeToggle();
		initCopyCsv();
		initPasteCsv();
		initCopyProduct();
		initSetAll();

		// Expose grid-rebuild so wizard + copy modal can call it.
		window.pizzatier_commerceApplyWizardGrid = function( sizes, fractions, cells ) {
			rebuildGridFromData( sizes, fractions, cells );
		};
	} );

	// =========================================================================
	// 1. Export CSV
	// =========================================================================

	function initExport() {
		$( document ).on( 'click', '#pztc-export-csv', function () {
			const $btn = $( this ).prop( 'disabled', true ).text( I18N.validating || 'Exporting…' );

			// Build a hidden form and submit it so the browser downloads the file.
			const $form = $( '<form>', {
				method: 'POST',
				action: AJAX,
				style: 'display:none',
			} );

			$form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'pizzatier_commerce_export_grid' } ) );
			$form.append( $( '<input>', { type: 'hidden', name: 'product_id', value: PROD_ID } ) );
			$form.append( $( '<input>', { type: 'hidden', name: 'nonce', value: NONCE } ) );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();

			// Re-enable button after a short delay.
			setTimeout( function () {
				$btn.prop( 'disabled', false ).html(
					'<span class="dashicons dashicons-download"></span> Export CSV'
				);
			}, 1500 );
		} );
	}

	// =========================================================================
	// 2. Download blank template
	// =========================================================================

	function initBlankTemplate() {
		$( document ).on( 'click', '#pztc-download-blank-template', function ( e ) {
			e.preventDefault();

			const $form = $( '<form>', {
				method: 'POST',
				action: AJAX,
				style: 'display:none',
			} );

			$form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'pizzatier_commerce_export_blank_grid_template' } ) );
			$form.append( $( '<input>', { type: 'hidden', name: 'product_id', value: PROD_ID } ) );
			$form.append( $( '<input>', { type: 'hidden', name: 'nonce', value: NONCE } ) );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();
		} );
	}

	// =========================================================================
	// 3. Import CSV
	// =========================================================================

	function initImport() {
		$( document ).on( 'change', '#pztc-import-csv-file', function () {
			const file = this.files && this.files[0];
			if ( ! file ) return;

			const reader = new FileReader();

			reader.onload = function ( e ) {
				const csv = e.target.result;
				if ( ! csv ) {
					showImportFeedback( I18N.importError || 'Could not read CSV file.', 'error' );
					return;
				}

				const parsed = parseCsv( csv );

				if ( parsed.error ) {
					showImportFeedback( parsed.error, 'error' );
					return;
				}

				// Rebuild the grid UI from the parsed data.
				rebuildGridFromData( parsed.sizes, parsed.fractions, parsed.cells );

				// Validate server-side.
				validateGridViaAjax( parsed, function ( ok, message ) {
					if ( ok ) {
						showImportFeedback( I18N.importSuccess || 'Grid populated from CSV. Review and save.', 'success' );
					} else {
						showImportFeedback( message || 'Validation error.', 'error' );
					}
				} );
			};

			reader.onerror = function () {
				showImportFeedback( I18N.importError || 'Could not read CSV file.', 'error' );
			};

			reader.readAsText( file );

			// Reset the file input so the same file can be re-imported if needed.
			this.value = '';
		} );
	}

	// =========================================================================
	// 4. Add Row (size)
	// =========================================================================

	function initAddRow() {
		$( document ).on( 'click', '#pztc-add-size-row', function () {
			const label    = I18N.newSize || 'New Size';
			const $table   = $( '#pztc-grid-table' );
			const $tbody   = $table.find( 'tbody' );

			// Collect current fraction columns from header.
			const fractions = collectFractions();

			const $tr = buildSizeRow( label, fractions, {} );
			$tr.addClass( 'pztc-grid-row--new' );
			$tbody.append( $tr );

			// Focus the editable label immediately.
			$tr.find( '.pztc-editable-label' ).first().focus();
		} );
	}

	// =========================================================================
	// 5. Add Column (fraction)
	// =========================================================================

	function initAddCol() {
		$( document ).on( 'click', '#pztc-add-fraction-col', function () {
			const label  = I18N.newFraction || 'New Coverage';
			const $table = $( '#pztc-grid-table' );

			// Add header cell.
			const $th = buildFractionHeader( label );
			$table.find( 'thead tr' ).append( $th );

			// Add a cell to every existing body row.
			$table.find( 'tbody tr.pztc-grid-row' ).each( function () {
				const $row    = $( this );
				const size    = $row.data( 'size' ) || '';
				const $td     = buildPriceCell( size, label, '' );
				$td.addClass( 'pztc-grid-cell--new' );
				$row.append( $td );
			} );

			// Focus the new fraction label.
			$th.find( '.pztc-editable-label' ).focus();
		} );
	}

	// =========================================================================
	// 6. Remove Row
	// =========================================================================

	function initRemoveRow() {
		$( document ).on( 'click', '.pztc-remove-row', function () {
			const msg = I18N.confirmRemoveRow || 'Remove this size row?';
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( msg ) ) return;

			$( this ).closest( 'tr.pztc-grid-row' ).remove();
		} );
	}

	// =========================================================================
	// 7. Remove Column
	// =========================================================================

	function initRemoveCol() {
		$( document ).on( 'click', '.pztc-remove-col', function () {
			const msg = I18N.confirmRemoveCol || 'Remove this coverage column?';
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( msg ) ) return;

			const $th       = $( this ).closest( 'th' );
			const $table    = $th.closest( 'table' );
			const colIndex  = $th.index(); // 0-based; 0 = corner cell

			$th.remove();

			// Remove the matching td from every body row.
			$table.find( 'tbody tr' ).each( function () {
				$( this ).find( 'td, th' ).eq( colIndex ).remove();
			} );
		} );
	}

	// =========================================================================
	// 8. Inline label sync (contenteditable → hidden input)
	// =========================================================================

	function initInlineLabelSync() {
		$( document ).on( 'input blur', '.pztc-editable-label', function () {
			const $span = $( this );
			const text  = $span.text().trim();
			const type  = $span.data( 'type' ); // 'size' or 'fraction'

			if ( 'size' === type ) {
				const $row   = $span.closest( 'tr' );
				const $input = $row.find( '.pztc-size-label-input' );
				$input.val( text );
				$row.attr( 'data-size', text );

				// Update all data-size attributes on cells in this row.
				$row.find( '.pztc-grid-cell' ).attr( 'data-size', text );
				$row.find( '.pztc-price-input' ).each( function () {
					const $input2   = $( this );
					const fraction  = $input2.closest( 'td' ).data( 'fraction' ) || '';
					const newKey    = text + '|' + fraction;
					$input2.attr( 'name', 'pizzatier_commerce_price_grid[cells][' + newKey + ']' );
					$input2.data( 'key', newKey );
				} );
			} else if ( 'fraction' === type ) {
				const $th    = $span.closest( 'th' );
				const $input = $th.find( '.pztc-fraction-label-input' );
				$input.val( text );
				$th.attr( 'data-fraction', text );

				// Update all cells in this column.
				const colIndex = $th.index();
				$( '#pztc-grid-table tbody tr' ).each( function () {
					const $cell = $( this ).find( 'td, th' ).eq( colIndex );
					$cell.attr( 'data-fraction', text );
					const size    = $cell.data( 'size' ) || $( this ).data( 'size' ) || '';
					const newKey  = size + '|' + text;
					$cell.find( '.pztc-price-input' )
						.attr( 'name', 'pizzatier_commerce_price_grid[cells][' + newKey + ']' )
						.data( 'key', newKey );
				} );
			}
		} );
	}

	// =========================================================================
	// Grid rebuild — used by CSV import
	// =========================================================================

	/**
	 * Completely replace the grid table with new data.
	 *
	 * @param {string[]} sizes
	 * @param {string[]} fractions
	 * @param {Object}   cells     { 'Size|Fraction': '8.50', … }
	 */
	function rebuildGridFromData( sizes, fractions, cells ) {
		const $wrap  = $( '#pztc-grid-wrap' );
		const $old   = $wrap.find( '.pztc-grid-table-container' );

		// Build thead.
		let $thead = $( '<thead><tr></tr></thead>' );
		let $hrow  = $thead.find( 'tr' );

		$hrow.append( buildCornerCell() );
		fractions.forEach( function ( f ) {
			$hrow.append( buildFractionHeader( f ) );
		} );

		// Build tbody.
		let $tbody = $( '<tbody></tbody>' );
		sizes.forEach( function ( s ) {
			$tbody.append( buildSizeRow( s, fractions, cells ) );
		} );

		const $table = $( '<table class="pztc-grid-table" id="pztc-grid-table"></table>' )
			.attr( 'data-currency', CUR )
			.append( $thead )
			.append( $tbody );

		const $container = $( '<div class="pztc-grid-table-container"></div>' ).append( $table );

		$old.replaceWith( $container );
	}

	// =========================================================================
	// Builder helpers
	// =========================================================================

	function buildCornerCell() {
		return $( '<th class="pztc-grid-corner">' +
			'<span class="pztc-grid-corner-row">Size</span>' +
			'<span class="pztc-grid-corner-sep">↓ / →</span>' +
			'<span class="pztc-grid-corner-col">Coverage</span>' +
			'</th>' );
	}

	function buildFractionHeader( label ) {
		const safeLabel = escAttr( label );
		return $( '<th class="pztc-grid-th pztc-grid-th--fraction">' +
			'<div class="pztc-header-cell">' +
				'<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="fraction" data-original="' + safeLabel + '" title="Click to rename">' + escHtml( label ) + '</span>' +
				'<input type="hidden" name="pizzatier_commerce_price_grid[fractions][]" value="' + safeLabel + '" class="pztc-fraction-label-input" />' +
				'<button type="button" class="pztc-remove-col pztc-grid-remove-btn" title="Remove this column">' +
					'<span class="dashicons dashicons-no-alt"></span>' +
				'</button>' +
			'</div>' +
			'</th>' ).attr( 'data-fraction', label );
	}

	function buildSizeRow( label, fractions, cells ) {
		const safeLabel = escAttr( label );
		const $tr = $( '<tr class="pztc-grid-row"></tr>' ).attr( 'data-size', label );

		// Row header.
		$tr.append( $( '<th class="pztc-grid-th pztc-grid-th--size">' +
			'<div class="pztc-header-cell">' +
				'<span class="pztc-header-label pztc-editable-label" contenteditable="true" data-type="size" data-original="' + safeLabel + '" title="Click to rename">' + escHtml( label ) + '</span>' +
				'<input type="hidden" name="pizzatier_commerce_price_grid[sizes][]" value="' + safeLabel + '" class="pztc-size-label-input" />' +
				'<button type="button" class="pztc-remove-row pztc-grid-remove-btn" title="Remove this row">' +
					'<span class="dashicons dashicons-no-alt"></span>' +
				'</button>' +
			'</div>' +
			'</th>' ) );

		// Price cells.
		fractions.forEach( function ( fraction ) {
			const key   = label + '|' + fraction;
			const price = cells[ key ] !== undefined ? cells[ key ] : '';
			$tr.append( buildPriceCell( label, fraction, price ) );
		} );

		return $tr;
	}

	function buildPriceCell( size, fraction, price ) {
		const key        = size + '|' + fraction;
		const safeKey    = escAttr( key );
		const safePrice  = price !== '' ? parseFloat( price ).toFixed( 2 ) : '';

		return $( '<td class="pztc-grid-cell"></td>' )
			.attr( 'data-size', size )
			.attr( 'data-fraction', fraction )
			.html(
				'<div class="pztc-cell-wrap">' +
					'<span class="pztc-cell-currency">' + escHtml( CUR ) + '</span>' +
					'<input type="number" class="pztc-price-input" min="0" step="0.01" placeholder="0.00"' +
						' name="pizzatier_commerce_price_grid[cells][' + safeKey + ']"' +
						' value="' + escAttr( safePrice ) + '"' +
						' data-key="' + safeKey + '" />' +
				'</div>'
			);
	}

	// =========================================================================
	// CSV parser (client-side)
	// =========================================================================

	/**
	 * Parse a CSV string.
	 * Returns { sizes, fractions, cells } or { error: '...' }.
	 *
	 * @param {string} csvString
	 * @returns {{ sizes: string[], fractions: string[], cells: Object }|{ error: string }}
	 */
	function parseCsv( csvString ) {
		const lines = csvString
			.replace( /\r\n/g, '\n' )
			.replace( /\r/g, '\n' )
			.split( '\n' )
			.filter( function ( l ) { return l.trim() !== ''; } );

		if ( lines.length < 2 ) {
			return { error: 'CSV must have a header row and at least one data row.' };
		}

		const header    = parseCsvLine( lines[0] );
		const fractions = header.slice( 1 ).map( function ( f ) { return f.trim(); } );

		if ( fractions.length === 0 ) {
			return { error: 'No fraction columns found after "Size" in header.' };
		}

		const sizes = [];
		const cells = {};

		for ( let i = 1; i < lines.length; i++ ) {
			const cols = parseCsvLine( lines[i] );
			const size = ( cols[0] || '' ).trim();
			if ( ! size ) continue;

			sizes.push( size );

			fractions.forEach( function ( fraction, j ) {
				const key       = size + '|' + fraction;
				cells[key]      = ( cols[ j + 1 ] || '' ).trim();
			} );
		}

		if ( sizes.length === 0 ) {
			return { error: 'No size rows found in CSV.' };
		}

		return { sizes: sizes, fractions: fractions, cells: cells };
	}

	/**
	 * Parse a single CSV line respecting quoted fields.
	 *
	 * @param {string} line
	 * @returns {string[]}
	 */
	function parseCsvLine( line ) {
		const fields = [];
		let current  = '';
		let inQuotes = false;

		for ( let i = 0; i < line.length; i++ ) {
			const ch = line[i];

			if ( ch === '"' ) {
				if ( inQuotes && line[i + 1] === '"' ) {
					// Escaped quote inside quoted field.
					current += '"';
					i++;
				} else {
					inQuotes = ! inQuotes;
				}
			} else if ( ch === ',' && ! inQuotes ) {
				fields.push( current );
				current = '';
			} else {
				current += ch;
			}
		}

		fields.push( current );
		return fields;
	}

	// =========================================================================
	// Server-side validation
	// =========================================================================

	function validateGridViaAjax( parsedData, callback ) {
		$.post( AJAX, {
			action:    'pizzatier_commerce_validate_grid_csv',
			nonce:     NONCE,
			grid_data: JSON.stringify( parsedData ),
		} )
		.done( function ( response ) {
			if ( response && response.success ) {
				callback( true, response.data && response.data.message );
			} else {
				const msg = ( response && response.data && response.data.message ) || 'Validation failed.';
				callback( false, msg );
			}
		} )
		.fail( function () {
			callback( false, 'Server error during validation.' );
		} );
	}

	// =========================================================================
	// Mode toggle — Grid Editor ↔ Setup Wizard
	// =========================================================================

	function initModeToggle() {
		$( document ).on( 'click', '#pztc-mode-advanced', function() {
			$( '#pztc-grid-advanced-wrap' ).show();
			$( '#pztc-grid-wizard-wrap' ).hide();
			$( '#pztc-mode-advanced' ).addClass( 'pztc-mode-btn--active' );
			$( '#pztc-mode-wizard' ).removeClass( 'pztc-mode-btn--active' );
			$( '#pztc-mode-hint-advanced' ).show();
			$( '#pztc-mode-hint-wizard' ).hide();
		} );
		$( document ).on( 'click', '#pztc-mode-wizard', function() {
			$( '#pztc-grid-advanced-wrap' ).hide();
			$( '#pztc-grid-wizard-wrap' ).show();
			$( '#pztc-mode-wizard' ).addClass( 'pztc-mode-btn--active' );
			$( '#pztc-mode-advanced' ).removeClass( 'pztc-mode-btn--active' );
			$( '#pztc-mode-hint-advanced' ).hide();
			$( '#pztc-mode-hint-wizard' ).show();
		} );
	}

	// =========================================================================
	// UI helpers
	// =========================================================================

	function collectFractions() {
		const fractions = [];
		$( '#pztc-grid-table thead tr th.pztc-grid-th--fraction' ).each( function () {
			fractions.push( $( this ).data( 'fraction' ) || '' );
		} );
		return fractions;
	}

	function showImportFeedback( message, type ) {
		const $fb = $( '#pztc-import-feedback' );
		$fb
			.removeClass( 'is-success is-error' )
			.addClass( 'is-' + type )
			.text( message )
			.show();

		if ( 'success' === type ) {
			setTimeout( function () {
				$fb.fadeOut( 400 );
			}, 5000 );
		}
	}

	// =========================================================================
	// Escape helpers (minimal, for building HTML strings safely)
	// =========================================================================

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escAttr( str ) {
		return escHtml( str );
	}

	// =========================================================================
	// 9. Copy CSV to clipboard
	// =========================================================================

	function initCopyCsv() {
		$( document ).on( 'click', '#pztc-copy-csv', function () {
			const $btn = $( this );
			const csv  = buildCsvFromCurrentGrid();

			if ( ! csv ) {
				showImportFeedback( I18N.copyCsvFail || 'Nothing to copy.', 'error' );
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( csv ).then( function () {
					$btn.find( '.dashicons' ).removeClass( 'dashicons-clipboard' ).addClass( 'dashicons-yes' );
					showImportFeedback( I18N.copyCsvSuccess || 'Copied to clipboard.', 'success' );
					setTimeout( function () {
						$btn.find( '.dashicons' ).removeClass( 'dashicons-yes' ).addClass( 'dashicons-clipboard' );
					}, 2000 );
				} ).catch( function () {
					showImportFeedback( I18N.copyCsvFail || 'Could not copy to clipboard.', 'error' );
				} );
			} else {
				// Fallback for browsers without clipboard API.
				const $ta = $( '<textarea>' ).val( csv ).css( { position: 'fixed', top: 0, left: 0, opacity: 0 } );
				$( 'body' ).append( $ta );
				$ta[0].select();
				try {
					document.execCommand( 'copy' );
					showImportFeedback( I18N.copyCsvSuccess || 'Copied to clipboard.', 'success' );
				} catch ( e ) {
					showImportFeedback( I18N.copyCsvFail || 'Could not copy to clipboard.', 'error' );
				}
				$ta.remove();
			}
		} );
	}

	// =========================================================================
	// 10. Paste CSV text panel
	// =========================================================================

	function initPasteCsv() {
		// Toggle panel open/close.
		$( document ).on( 'click', '#pztc-paste-csv-toggle', function () {
			closePanels( '#pztc-paste-csv-panel' );
			$( '#pztc-paste-csv-panel' ).slideToggle( 200 );
		} );

		$( document ).on( 'click', '#pztc-paste-csv-cancel', function () {
			$( '#pztc-paste-csv-panel' ).slideUp( 200 );
			$( '#pztc-paste-csv-text' ).val( '' );
		} );

		// Apply pasted CSV.
		$( document ).on( 'click', '#pztc-paste-csv-apply', function () {
			const csv = $( '#pztc-paste-csv-text' ).val().trim();
			if ( ! csv ) {
				showImportFeedback( I18N.importError || 'No CSV text.', 'error' );
				return;
			}

			const $btn = $( this ).prop( 'disabled', true );

			$.post( AJAX, {
				action:   'pizzatier_commerce_validate_csv_text',
				nonce:    NONCE,
				csv_text: csv,
			} )
			.done( function ( response ) {
				if ( response && response.success ) {
					const d = response.data;
					rebuildGridFromData( d.sizes, d.fractions, d.cells );
					showImportFeedback( I18N.pasteCsvSuccess || 'Grid applied from CSV. Review and save.', 'success' );
					$( '#pztc-paste-csv-panel' ).slideUp( 200 );
					$( '#pztc-paste-csv-text' ).val( '' );
				} else {
					const msg = ( response && response.data && response.data.message ) || 'Validation error.';
					showImportFeedback( ( I18N.pasteCsvError || 'Error: ' ) + msg, 'error' );
				}
			} )
			.fail( function () {
				showImportFeedback( 'Server error.', 'error' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	// =========================================================================
	// 11. Copy from another product
	// =========================================================================

	function initCopyProduct() {
		var productsLoaded = false;

		// Toggle panel — load product list on first open.
		$( document ).on( 'click', '#pztc-copy-product-toggle', function () {
			closePanels( '#pztc-copy-product-panel' );
			const $panel = $( '#pztc-copy-product-panel' );

			if ( $panel.is( ':visible' ) ) {
				$panel.slideUp( 200 );
				return;
			}

			$panel.slideDown( 200 );

			if ( productsLoaded ) return;
			productsLoaded = true;

			const $sel = $( '#pztc-copy-product-select' );
			$sel.html( '<option value="">' + ( I18N.loadingProducts || '— Loading… —' ) + '</option>' )
				.prop( 'disabled', true );

			$.post( AJAX, {
				action:     'pizzatier_commerce_get_pizza_products',
				nonce:      NONCE,
				exclude_id: PROD_ID,
			} )
			.done( function ( response ) {
				if ( response && response.success && response.data.products.length ) {
					$sel.empty();
					$sel.append( $( '<option>', { value: '', text: '— Select a product —' } ) );
					response.data.products.forEach( function ( p ) {
						const label = p.title + ( p.hasGrid ? '' : ' (no grid)' );
						$sel.append( $( '<option>', { value: p.id, text: label } ) );
					} );
				} else {
					$sel.html( '<option value="">' + ( I18N.noGridProducts || '— No Pizza products found —' ) + '</option>' );
				}
			} )
			.fail( function () {
				$sel.html( '<option value="">— Error loading products —</option>' );
			} )
			.always( function () {
				$sel.prop( 'disabled', false );
				$( '#pztc-copy-product-apply' ).prop( 'disabled', false );
			} );
		} );

		$( document ).on( 'click', '#pztc-copy-product-cancel', function () {
			$( '#pztc-copy-product-panel' ).slideUp( 200 );
		} );

		// Apply copy.
		$( document ).on( 'click', '#pztc-copy-product-apply', function () {
			const sourceId = parseInt( $( '#pztc-copy-product-select' ).val(), 10 );
			if ( ! sourceId ) {
				showImportFeedback( I18N.copyProductNone || 'Please select a product.', 'error' );
				return;
			}

			// eslint-disable-next-line no-alert
			if ( ! window.confirm( I18N.confirmCopyProduct || 'Replace the current grid with the selected product\'s grid?' ) ) {
				return;
			}

			const $btn = $( this ).prop( 'disabled', true );

			$.post( AJAX, {
				action:     'pizzatier_commerce_get_product_grid',
				nonce:      NONCE,
				product_id: sourceId,
			} )
			.done( function ( response ) {
				if ( response && response.success ) {
					const d = response.data;
					rebuildGridFromData( d.sizes, d.fractions, d.cells );
					showImportFeedback( I18N.copyProductSuccess || 'Grid copied. Review and save.', 'success' );
					$( '#pztc-copy-product-panel' ).slideUp( 200 );
				} else {
					showImportFeedback( I18N.copyProductError || 'Could not load grid.', 'error' );
				}
			} )
			.fail( function () {
				showImportFeedback( 'Server error.', 'error' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	// =========================================================================
	// 12. Set all cells to same price
	// =========================================================================

	function initSetAll() {
		// Mirror currency symbol from the grid table into the Set All panel.
		$( document ).on( 'click', '#pztc-set-all-toggle', function () {
			closePanels( '#pztc-set-all-panel' );
			const $panel = $( '#pztc-set-all-panel' );

			if ( $panel.is( ':visible' ) ) {
				$panel.slideUp( 200 );
				return;
			}

			// Sync currency symbol.
			const sym = $( '#pztc-grid-table' ).data( 'currency' ) || CUR;
			$( '#pztc-set-all-currency-sym' ).text( sym );

			$panel.slideDown( 200 );
			$( '#pztc-set-all-value' ).focus();
		} );

		$( document ).on( 'click', '#pztc-set-all-cancel', function () {
			$( '#pztc-set-all-panel' ).slideUp( 200 );
		} );

		$( document ).on( 'click', '#pztc-set-all-apply', function () {
			const val = $( '#pztc-set-all-value' ).val().trim();
			if ( val === '' ) {
				showImportFeedback( I18N.setAllNoValue || 'Please enter a price.', 'error' );
				return;
			}

			const price = parseFloat( val );
			if ( isNaN( price ) || price < 0 ) {
				showImportFeedback( I18N.setAllNoValue || 'Please enter a valid price.', 'error' );
				return;
			}

			const formatted = price.toFixed( 2 );
			$( '#pztc-grid-table tbody .pztc-price-input' ).val( formatted );

			showImportFeedback( I18N.setAllSuccess || 'All cells updated.', 'success' );
			$( '#pztc-set-all-panel' ).slideUp( 200 );
		} );

		// Also handle Enter key in the set-all input.
		$( document ).on( 'keydown', '#pztc-set-all-value', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				$( '#pztc-set-all-apply' ).trigger( 'click' );
			}
		} );
	}

	// =========================================================================
	// Panel helpers
	// =========================================================================

	/**
	 * Close all tool panels except the one given (or close all if none given).
	 *
	 * @param {string} [keepSelector]
	 */
	function closePanels( keepSelector ) {
		var panels = [ '#pztc-paste-csv-panel', '#pztc-copy-product-panel', '#pztc-set-all-panel' ];
		panels.forEach( function ( sel ) {
			if ( sel !== keepSelector ) {
				$( sel ).slideUp( 150 );
			}
		} );
	}

	// =========================================================================
	// CSV builder from current table state
	// =========================================================================

	/**
	 * Build a CSV string from the live grid table (not the saved meta).
	 * Used by Copy CSV.
	 *
	 * @returns {string}
	 */
	function buildCsvFromCurrentGrid() {
		const $table = $( '#pztc-grid-table' );
		if ( ! $table.length ) return '';

		const fractions = [];
		$table.find( 'thead tr th.pztc-grid-th--fraction' ).each( function () {
			fractions.push( $( this ).data( 'fraction' ) || '' );
		} );

		const rows = [];

		// Header.
		rows.push( [ 'Size' ].concat( fractions ) );

		// Data rows.
		$table.find( 'tbody tr.pztc-grid-row' ).each( function () {
			const $row = $( this );
			const size = $row.data( 'size' ) || $row.find( '.pztc-size-label-input' ).val() || '';
			const row  = [ size ];

			fractions.forEach( function ( frac ) {
				const $cell = $row.find( 'td[data-fraction="' + frac + '"]' );
				const val   = $cell.find( '.pztc-price-input' ).val() || '0.00';
				row.push( val );
			} );

			rows.push( row );
		} );

		if ( rows.length < 2 ) return '';

		return rows.map( function ( row ) {
			return row.map( function ( cell ) {
				const s = String( cell );
				// Quote fields that contain commas or quotes.
				if ( s.indexOf( ',' ) !== -1 || s.indexOf( '"' ) !== -1 ) {
					return '"' + s.replace( /"/g, '""' ) + '"';
				}
				return s;
			} ).join( ',' );
		} ).join( '\n' );
	}

} )( jQuery );
