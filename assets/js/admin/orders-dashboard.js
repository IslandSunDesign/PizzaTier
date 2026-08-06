/**
 * PizzaTier — Pizza Orders live dashboard.
 *
 * Everything on the screen is driven from two admin-ajax endpoints:
 *   snapshot  — status counts, today's numbers, the incoming board
 *   query     — the filterable / sortable / paged list
 * plus small action endpoints (set_status, bulk, detail, add_note).
 *
 * No page ever reloads: actions apply over XHR, then the snapshot and list
 * refresh. A poll (default 15s, pausable) keeps the board current during
 * service, chimes on new orders when sound is enabled, and mirrors the
 * open-order count into the browser tab title.
 *
 * Vanilla JS, no build step, no dependencies.
 */
/* global PizzaTierOrdersDash */
( function () {
	'use strict';

	if ( typeof PizzaTierOrdersDash === 'undefined' ) {
		return;
	}

	var cfg = PizzaTierOrdersDash;
	var i18n = cfg.i18n;

	// ── State ────────────────────────────────────────────────────────────

	var state = {
		status: '',            // '' | 'open' | 'trash' | pzt-* status
		search: '',
		fulfillment: '',
		dateFrom: '',
		dateTo: '',
		orderby: 'date',
		order: 'desc',
		paged: 1,
		pages: 1,
		selected: {},          // id => true
		live: true,
		sound: window.localStorage.getItem( 'pztDashSound' ) === '1',
		latestId: 0,
		openCount: 0,
		firstSnapshot: true,
		pollTimer: null,
		searchTimer: null,
		listRequest: 0,        // request counter to drop stale responses
		baseTitle: document.title
	};

	// ── Small helpers ────────────────────────────────────────────────────

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function esc( text ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( text == null ? '' : String( text ) ) );
		return div.innerHTML;
	}

	function escAttr( text ) {
		return esc( text ).replace( /"/g, '&quot;' );
	}

	function sprintf( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var idx = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var i = pos ? parseInt( pos, 10 ) - 1 : idx++;
			return typeof args[ i ] === 'undefined' ? m : String( args[ i ] );
		} );
	}

	function ajax( action, data, method ) {
		var body = new window.URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( key ) {
			var value = data[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( v ) { body.append( key + '[]', v ); } );
			} else if ( value !== '' && value !== null && typeof value !== 'undefined' ) {
				body.set( key, value );
			}
		} );
		return window.fetch( cfg.ajaxUrl, {
			method: method || 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
			body: body.toString()
		} ).then( function ( res ) {
			if ( ! res.ok ) { throw new Error( 'HTTP ' + res.status ); }
			return res.json();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				var message = json && json.data && json.data.message ? json.data.message : i18n.actionError;
				throw new Error( message );
			}
			return json.data;
		} );
	}

	function toast( message, isError ) {
		var wrap = $( '#pzt-dash-toasts' );
		if ( ! wrap ) { return; }
		var el = document.createElement( 'div' );
		el.className = 'pzt-dash-toast' + ( isError ? ' is-error' : '' );
		el.textContent = message;
		wrap.appendChild( el );
		window.setTimeout( function () { el.classList.add( 'is-leaving' ); }, 3200 );
		window.setTimeout( function () { if ( el.parentNode ) { el.parentNode.removeChild( el ); } }, 3800 );
	}

	function ageText( minutes ) {
		if ( minutes < 1 ) { return i18n.justNow; }
		if ( minutes < 60 ) { return sprintf( i18n.minAgo, minutes ); }
		return sprintf( i18n.hoursAgo, Math.floor( minutes / 60 ) );
	}

	function ageClass( minutes, isOpen ) {
		if ( ! isOpen ) { return ''; }
		if ( minutes >= 30 ) { return 'is-age-hot'; }
		if ( minutes >= 15 ) { return 'is-age-warm'; }
		return '';
	}

	function methodIcon( method ) {
		if ( method === 'delivery' ) { return '🛵'; }
		if ( method === 'dine_in' ) { return '🍽️'; }
		return '🛍️';
	}

	// ── Chime (WebAudio; no asset file needed) ───────────────────────────

	function chime() {
		try {
			var Ctx = window.AudioContext || window.webkitAudioContext;
			if ( ! Ctx ) { return; }
			var ctx = new Ctx();
			[ 880, 1174.7 ].forEach( function ( freq, n ) {
				var osc = ctx.createOscillator();
				var gain = ctx.createGain();
				osc.type = 'sine';
				osc.frequency.value = freq;
				gain.gain.setValueAtTime( 0.0001, ctx.currentTime + n * 0.18 );
				gain.gain.exponentialRampToValueAtTime( 0.25, ctx.currentTime + n * 0.18 + 0.02 );
				gain.gain.exponentialRampToValueAtTime( 0.0001, ctx.currentTime + n * 0.18 + 0.5 );
				osc.connect( gain );
				gain.connect( ctx.destination );
				osc.start( ctx.currentTime + n * 0.18 );
				osc.stop( ctx.currentTime + n * 0.18 + 0.55 );
			} );
			window.setTimeout( function () { ctx.close(); }, 1500 );
		} catch ( e ) {
			// Sound is a nicety; never let it break the dashboard.
		}
	}

	// ── Snapshot (cards, today, incoming) ────────────────────────────────

	function refreshSnapshot() {
		return ajax( 'pizzatier_orders_snapshot', {} ).then( function ( data ) {
			renderCounts( data.counts, data.trash, data.open_count );
			renderToday( data.today );
			renderIncoming( data.incoming );
			renderAccepting( data.accepting );

			// New order detection: a higher max ID than last seen.
			if ( ! state.firstSnapshot && data.latest_id > state.latestId ) {
				toast( i18n.newOrder );
				if ( state.sound ) { chime(); }
				refreshList(); // A new order may belong in the current list view.
			}
			state.latestId = Math.max( state.latestId, data.latest_id );
			state.openCount = data.open_count;
			state.firstSnapshot = false;
			updateTabTitle();
		} ).catch( function () {
			// Poll errors stay quiet; the next tick retries.
		} );
	}

	function renderAccepting( accepting ) {
		var el = $( '#pzt-dash-accepting' );
		if ( ! el ) { return; }
		el.classList.toggle( 'is-on', !! accepting );
		el.classList.toggle( 'is-off', ! accepting );
	}

	function renderCounts( counts, trash, openCount ) {
		var total = 0;
		Object.keys( counts || {} ).forEach( function ( s ) { total += counts[ s ]; } );

		$$( '.pzt-dash-card' ).forEach( function ( card ) {
			var status = card.getAttribute( 'data-status' );
			var countEl = $( '.pzt-dash-card__count', card );
			var value;
			if ( status === '' ) { value = total; }
			else if ( status === 'open' ) { value = openCount; }
			else if ( status === 'trash' ) { value = trash; }
			else { value = counts && counts[ status ] ? counts[ status ] : 0; }

			if ( countEl ) { countEl.textContent = String( value ); }

			// The trash card only appears once something is in the trash.
			if ( status === 'trash' ) {
				card.style.display = ( trash > 0 || state.status === 'trash' ) ? '' : 'none';
			}
			card.classList.toggle( 'is-current', state.status === status );
			card.classList.toggle( 'is-empty', value === 0 );
		} );
	}

	function renderToday( today ) {
		if ( ! today ) { return; }
		var box = $( '#pzt-dash-today' );
		if ( ! box ) { return; }
		var set = function ( key, value ) {
			var el = box.querySelector( '[data-stat="' + key + '"]' );
			if ( el ) { el.textContent = value; }
		};
		set( 'orders', String( today.orders ) );
		set( 'pizzas', String( today.pizzas ) );
		set( 'revenue', today.revenue_display );
		set( 'average', today.average_display );

		var methods = today.methods || {};
		set( 'methods', ( methods.pickup || 0 ) + ' / ' + ( methods.delivery || 0 ) );
	}

	function renderIncoming( orders ) {
		var track = $( '#pzt-dash-incoming' );
		if ( ! track ) { return; }

		if ( ! orders || ! orders.length ) {
			track.innerHTML = '<p class="pzt-dash-empty">' + esc( i18n.noIncoming ) + '</p>';
			return;
		}

		var html = orders.map( function ( o ) {
			var age = ageText( o.age_minutes );
			var name = o.customer.name || i18n.guest;
			var phone = o.customer.phone
				? '<a class="pzt-dash-inc__phone" href="tel:' + escAttr( o.customer.phone ) + '">' + esc( o.customer.phone ) + '</a>'
				: '';
			var requested = o.fulfillment.requested_time
				? '<span class="pzt-dash-inc__requested">⏰ ' + esc( o.fulfillment.requested_time ) + '</span>'
				: '';
			var noteFlag = o.has_note ? '<span class="pzt-dash-inc__noteflag" title="' + escAttr( i18n.customerNote ) + '">📝</span>' : '';
			var nextBtn = o.next
				? '<button type="button" class="button button-primary pzt-dash-next-btn" data-id="' + o.id + '" data-status="' + escAttr( o.next.status ) + '">' + esc( o.next.label ) + ' →</button>'
				: '';

			return '<article class="pzt-dash-inc ' + ageClass( o.age_minutes, true ) + '" data-id="' + o.id + '" style="--pzt-inc:' + escAttr( o.status_color ) + '">'
				+ '<header class="pzt-dash-inc__head">'
				+   '<button type="button" class="pzt-dash-inc__number pzt-dash-open-drawer" data-id="' + o.id + '">' + esc( o.number ) + '</button>'
				+   '<span class="pzt-dash-badge" style="--pzt-badge:' + escAttr( o.status_color ) + '">' + esc( o.status_label ) + '</span>'
				+ '</header>'
				+ '<p class="pzt-dash-inc__meta">'
				+   '<span class="pzt-dash-inc__method">' + methodIcon( o.fulfillment.method ) + ' ' + esc( o.fulfillment.method_label ) + '</span>'
				+   '<span class="pzt-dash-inc__age" title="' + escAttr( o.placed ) + '">' + esc( age ) + '</span>'
				+ '</p>'
				+ '<p class="pzt-dash-inc__items">' + esc( o.items_summary ) + noteFlag + '</p>'
				+ '<p class="pzt-dash-inc__who">' + esc( name ) + ( phone ? ' · ' + phone : '' ) + requested + '</p>'
				+ '<footer class="pzt-dash-inc__actions">'
				+   nextBtn
				+   '<button type="button" class="button pzt-dash-open-drawer" data-id="' + o.id + '">' + esc( i18n.view ) + '</button>'
				+ '</footer>'
				+ '</article>';
		} ).join( '' );

		track.innerHTML = html;
	}

	function updateTabTitle() {
		document.title = state.openCount > 0
			? '(' + state.openCount + ') ' + state.baseTitle
			: state.baseTitle;
	}

	// ── List ─────────────────────────────────────────────────────────────

	function refreshList() {
		var request = ++state.listRequest;
		var tbody = $( '#pzt-dash-rows' );
		if ( tbody ) { tbody.classList.add( 'is-refreshing' ); }

		return ajax( 'pizzatier_orders_query', {
			status: state.status,
			search: state.search,
			fulfillment: state.fulfillment,
			date_from: state.dateFrom,
			date_to: state.dateTo,
			orderby: state.orderby,
			order: state.order,
			paged: state.paged,
			per_page: cfg.perPage
		} ).then( function ( data ) {
			if ( request !== state.listRequest ) { return; } // A newer query superseded this one.
			state.pages = data.pages;
			state.paged = data.paged;
			renderRows( data.rows );
			renderPagination( data.total );
			renderResultCount( data.total, data.capped );
			renderCounts( data.counts, data.trash, state.openCount );
		} ).catch( function ( err ) {
			if ( request !== state.listRequest ) { return; }
			if ( tbody ) {
				tbody.innerHTML = '<tr><td colspan="9" class="pzt-dash-empty">' + esc( err.message || i18n.loadError ) + '</td></tr>';
			}
		} ).then( function () {
			if ( tbody ) { tbody.classList.remove( 'is-refreshing' ); }
		} );
	}

	function renderRows( rows ) {
		var tbody = $( '#pzt-dash-rows' );
		if ( ! tbody ) { return; }

		if ( ! rows.length ) {
			tbody.innerHTML = '<tr><td colspan="9" class="pzt-dash-empty">' + esc( i18n.noOrders ) + '</td></tr>';
			syncBulkBar();
			return;
		}

		tbody.innerHTML = rows.map( function ( o ) {
			var name = o.customer.name || i18n.guest;
			var checked = state.selected[ o.id ] ? ' checked' : '';
			var phone = o.customer.phone
				? '<br><a href="tel:' + escAttr( o.customer.phone ) + '" class="pzt-dash-row-phone">' + esc( o.customer.phone ) + '</a>'
				: '';
			var nextBtn = o.next
				? '<button type="button" class="button button-small button-primary pzt-dash-next-btn" data-id="' + o.id + '" data-status="' + escAttr( o.next.status ) + '">' + esc( o.next.label ) + '</button> '
				: '';
			var requested = o.fulfillment.requested_time
				? '<br><span class="pzt-dash-row-requested">⏰ ' + esc( o.fulfillment.requested_time ) + '</span>'
				: '';
			var noteFlag = o.has_note ? ' <span title="' + escAttr( i18n.customerNote ) + '">📝</span>' : '';

			return '<tr class="' + ageClass( o.age_minutes, o.is_open ) + '" data-id="' + o.id + '">'
				+ '<th class="check-column"><input type="checkbox" class="pzt-dash-check" value="' + o.id + '"' + checked + '></th>'
				+ '<td><button type="button" class="pzt-dash-row-number pzt-dash-open-drawer" data-id="' + o.id + '">' + esc( o.number ) + '</button>' + noteFlag + '</td>'
				+ '<td><span title="' + escAttr( o.placed ) + '">' + esc( o.placed ) + '</span>'
				+   ( o.is_open ? '<br><span class="pzt-dash-row-age">' + esc( ageText( o.age_minutes ) ) + '</span>' : '' ) + '</td>'
				+ '<td>' + esc( name ) + phone + '</td>'
				+ '<td>' + esc( o.items_summary || String( o.pizza_count ) ) + '</td>'
				+ '<td>' + methodIcon( o.fulfillment.method ) + ' ' + esc( o.fulfillment.method_label ) + requested + '</td>'
				+ '<td>' + esc( o.total_display || '—' ) + '</td>'
				+ '<td><span class="pzt-dash-badge" style="--pzt-badge:' + escAttr( o.status_color ) + '">' + esc( o.status_label ) + '</span></td>'
				+ '<td class="pzt-dash-row-actions">' + nextBtn
				+   '<a class="button button-small" href="' + escAttr( o.detail_url ) + '">' + esc( i18n.view ) + '</a>'
				+ '</td>'
				+ '</tr>';
		} ).join( '' );

		syncBulkBar();
	}

	function renderPagination( total ) {
		var prev = $( '#pzt-dash-prev' );
		var next = $( '#pzt-dash-next' );
		var info = $( '#pzt-dash-page-info' );
		if ( prev ) { prev.disabled = state.paged <= 1; }
		if ( next ) { next.disabled = state.paged >= state.pages; }
		if ( info ) { info.textContent = sprintf( i18n.pageInfo, state.paged, state.pages, total ); }
	}

	function renderResultCount( total, capped ) {
		var el = $( '#pzt-dash-result-count' );
		if ( ! el ) { return; }
		el.textContent = capped ? i18n.cappedNotice : '';
	}

	// ── Selection / bulk bar ─────────────────────────────────────────────

	function syncBulkBar() {
		var ids = Object.keys( state.selected );
		var bar = $( '#pzt-dash-bulkbar' );
		if ( ! bar ) { return; }
		bar.hidden = ids.length === 0;
		var count = $( '.pzt-dash-bulkbar__count', bar );
		if ( count ) { count.textContent = sprintf( i18n.selectedCount, ids.length ); }

		var all = $( '#pzt-dash-check-all' );
		var boxes = $$( '.pzt-dash-check' );
		if ( all && boxes.length ) {
			all.checked = boxes.every( function ( b ) { return b.checked; } );
		}
	}

	// ── Actions ──────────────────────────────────────────────────────────

	function setStatus( orderId, status, note ) {
		return ajax( 'pizzatier_orders_set_status', {
			order_id: orderId,
			new_status: status,
			note: note || ''
		} ).then( function ( data ) {
			toast( data.message );
			state.openCount = data.open_count;
			updateTabTitle();
			refreshSnapshot();
			refreshList();
			return data;
		} ).catch( function ( err ) {
			toast( err.message || i18n.actionError, true );
			throw err;
		} );
	}

	function runBulk( op, ids ) {
		return ajax( 'pizzatier_orders_bulk', { op: op, ids: ids } ).then( function ( data ) {
			toast( data.message );
			state.selected = {};
			state.openCount = data.open_count;
			updateTabTitle();
			refreshSnapshot();
			refreshList();
		} ).catch( function ( err ) {
			toast( err.message || i18n.actionError, true );
		} );
	}

	// ── Drawer (quick view) ──────────────────────────────────────────────

	function openDrawer( orderId ) {
		var drawer = $( '#pzt-dash-drawer' );
		var backdrop = $( '#pzt-dash-drawer-backdrop' );
		if ( ! drawer || ! backdrop ) { return; }

		drawer.hidden = false;
		backdrop.hidden = false;
		document.body.classList.add( 'pzt-dash-drawer-open' );
		$( '.pzt-dash-drawer__inner', drawer ).innerHTML = '<p class="pzt-dash-loading">…</p>';

		ajax( 'pizzatier_orders_detail', { order_id: orderId } ).then( function ( data ) {
			renderDrawer( data );
		} ).catch( function ( err ) {
			$( '.pzt-dash-drawer__inner', drawer ).innerHTML =
				'<p class="pzt-dash-empty">' + esc( err.message || i18n.loadError ) + '</p>'
				+ '<p><button type="button" class="button pzt-dash-drawer-close">' + esc( i18n.close ) + '</button></p>';
		} );
	}

	function closeDrawer() {
		var drawer = $( '#pzt-dash-drawer' );
		var backdrop = $( '#pzt-dash-drawer-backdrop' );
		if ( drawer ) { drawer.hidden = true; }
		if ( backdrop ) { backdrop.hidden = true; }
		document.body.classList.remove( 'pzt-dash-drawer-open' );
	}

	function renderDrawer( data ) {
		var drawer = $( '#pzt-dash-drawer' );
		if ( ! drawer ) { return; }
		var s = data.summary;
		var t = data.totals_display;

		var itemsHtml = ( data.items || [] ).map( function ( item, index ) {
			var layers = ( item.layers || [] ).map( function ( layer ) {
				var coverage = layer.coverage && layer.coverage !== 'whole'
					? ' <em>(' + esc( layer.coverage_label || layer.coverage ) + ')</em>'
					: '';
				var price = layer.price_display ? ' <span class="pzt-dash-dr-price">' + esc( layer.price_display ) + '</span>' : '';
				return '<li><span class="pzt-dash-dr-layertype">' + esc( layer.type ) + '</span> ' + esc( layer.name ) + coverage + price + '</li>';
			} ).join( '' );

			var size = item.size && item.size.label ? ' <span class="pzt-dash-dr-size">' + esc( item.size.label ) + '</span>' : '';
			var note = item.notes ? '<p class="pzt-dash-dr-itemnote">📝 ' + esc( item.notes ) + '</p>' : '';

			return '<div class="pzt-dash-dr-item">'
				+ '<p class="pzt-dash-dr-itemhead"><strong>' + ( index + 1 ) + '. ' + esc( item.name || 'Pizza' ) + '</strong>' + size
				+ ' <span class="pzt-dash-dr-qty">× ' + esc( item.quantity ) + '</span>'
				+ ( item.line_total_display && parseFloat( item.line_total ) > 0 ? ' <span class="pzt-dash-dr-price">' + esc( item.line_total_display ) + '</span>' : '' )
				+ '</p>'
				+ ( layers ? '<ul class="pzt-dash-dr-layers">' + layers + '</ul>' : '' )
				+ note
				+ '</div>';
		} ).join( '' );

		var totalsRows = [
			[ i18n.subtotal, data.totals.subtotal, t.subtotal ],
			[ i18n.delivery, data.totals.delivery_fee, t.delivery_fee ],
			[ i18n.tax, data.totals.tax, t.tax ],
			[ i18n.tip, data.totals.tip, t.tip ],
			[ i18n.discount, data.totals.discount, t.discount ]
		].filter( function ( row ) { return parseFloat( row[ 1 ] ) > 0; } )
			.map( function ( row ) { return '<tr><th>' + esc( row[ 0 ] ) + '</th><td>' + esc( row[ 2 ] ) + '</td></tr>'; } )
			.join( '' );

		var totalsHtml = parseFloat( data.totals.total ) > 0
			? '<table class="pzt-dash-dr-totals"><tbody>' + totalsRows
				+ '<tr class="pzt-dash-dr-totals__grand"><th>' + esc( i18n.total ) + '</th><td>' + esc( t.total ) + '</td></tr></tbody></table>'
			: '';

		var statusOptions = Object.keys( cfg.statuses ).map( function ( key ) {
			return '<option value="' + escAttr( key ) + '"' + ( key === s.status ? ' selected' : '' ) + '>'
				+ esc( cfg.statuses[ key ].label ) + '</option>';
		} ).join( '' );

		var custRows = '';
		if ( s.customer.name ) { custRows += '<p><strong>' + esc( s.customer.name ) + '</strong></p>'; }
		if ( s.customer.phone ) { custRows += '<p>' + esc( i18n.phone ) + ': <a href="tel:' + escAttr( s.customer.phone ) + '">' + esc( s.customer.phone ) + '</a></p>'; }
		if ( s.customer.email ) { custRows += '<p>' + esc( i18n.email ) + ': <a href="mailto:' + escAttr( s.customer.email ) + '">' + esc( s.customer.email ) + '</a></p>'; }
		if ( data.address_line ) { custRows += '<p>' + esc( i18n.address ) + ': ' + esc( data.address_line ) + '</p>'; }
		if ( s.fulfillment.requested_time ) { custRows += '<p>' + esc( i18n.requestedFor ) + ': ⏰ ' + esc( s.fulfillment.requested_time ) + '</p>'; }
		if ( s.fulfillment.table ) { custRows += '<p>' + esc( i18n.table ) + ': ' + esc( s.fulfillment.table ) + '</p>'; }
		custRows += '<p>' + esc( i18n.placed ) + ': ' + esc( data.placed_display ) + '</p>';

		var customerNote = data.customer_note
			? '<div class="pzt-dash-dr-section pzt-dash-dr-custnote"><h3>' + esc( i18n.customerNote ) + '</h3><p>' + esc( data.customer_note ) + '</p></div>'
			: '';

		var notesHtml = ( data.notes_display || [] ).map( function ( n ) {
			return '<li><p>' + esc( n.note ) + '</p><small>' + esc( n.by ) + ' · ' + esc( n.when ) + '</small></li>';
		} ).join( '' );

		var historyHtml = ( data.history_display || [] ).map( function ( h ) {
			return '<li><span>' + esc( h.change ) + '</span><small>' + esc( h.by ) + ' · ' + esc( h.when ) + '</small>'
				+ ( h.note ? '<em>' + esc( h.note ) + '</em>' : '' ) + '</li>';
		} ).join( '' );

		$( '.pzt-dash-drawer__inner', drawer ).innerHTML =
			'<header class="pzt-dash-dr-head">'
			+ '<h2>' + esc( s.number ) + ' <span class="pzt-dash-badge" style="--pzt-badge:' + escAttr( s.status_color ) + '">' + esc( s.status_label ) + '</span></h2>'
			+ '<button type="button" class="pzt-dash-drawer-close button" aria-label="' + escAttr( i18n.close ) + '">✕</button>'
			+ '</header>'

			+ '<div class="pzt-dash-dr-quickactions">'
			+ ( s.next ? '<button type="button" class="button button-primary button-hero pzt-dash-next-btn" data-id="' + s.id + '" data-status="' + escAttr( s.next.status ) + '">' + esc( s.next.label ) + ' →</button>' : '' )
			+ '<button type="button" class="button pzt-dash-print-btn" data-id="' + s.id + '">🖨️ ' + esc( i18n.printTicket ) + '</button>'
			+ '<a class="button" href="' + escAttr( s.detail_url ) + '">' + esc( i18n.fullDetails ) + '</a>'
			+ '</div>'

			+ '<div class="pzt-dash-dr-section"><h3>' + esc( i18n.items ) + '</h3>' + itemsHtml + totalsHtml + '</div>'
			+ customerNote
			+ '<div class="pzt-dash-dr-section"><h3>' + methodIcon( s.fulfillment.method ) + ' ' + esc( s.fulfillment.method_label ) + '</h3>' + custRows + '</div>'

			+ '<div class="pzt-dash-dr-section">'
			+ '<h3>' + esc( i18n.status ) + '</h3>'
			+ '<div class="pzt-dash-dr-statusform">'
			+ '<select id="pzt-dash-dr-status">' + statusOptions + '</select>'
			+ '<button type="button" class="button" id="pzt-dash-dr-status-apply" data-id="' + s.id + '">' + esc( i18n.update ) + '</button>'
			+ '</div>'
			+ '</div>'

			+ '<div class="pzt-dash-dr-section">'
			+ '<h3>' + esc( i18n.internalNotes ) + '</h3>'
			+ ( notesHtml ? '<ul class="pzt-dash-dr-notes" id="pzt-dash-dr-notes">' + notesHtml + '</ul>' : '<ul class="pzt-dash-dr-notes" id="pzt-dash-dr-notes"></ul>' )
			+ '<div class="pzt-dash-dr-noteform">'
			+ '<textarea id="pzt-dash-dr-note" rows="2" placeholder="' + escAttr( i18n.notePlaceholder ) + '"></textarea>'
			+ '<button type="button" class="button" id="pzt-dash-dr-note-add" data-id="' + s.id + '">' + esc( i18n.addNote ) + '</button>'
			+ '</div>'
			+ '</div>'

			+ ( historyHtml ? '<div class="pzt-dash-dr-section"><h3>' + esc( i18n.history ) + '</h3><ul class="pzt-dash-dr-history">' + historyHtml + '</ul></div>' : '' );

		// Stash for the print ticket.
		drawer.setAttribute( 'data-order-id', String( s.id ) );
		state.drawerData = data;
	}

	// ── Print ticket ─────────────────────────────────────────────────────

	function printTicket() {
		var data = state.drawerData;
		var target = $( '#pzt-dash-print' );
		if ( ! data || ! target ) { return; }
		var s = data.summary;

		var itemsHtml = ( data.items || [] ).map( function ( item, index ) {
			var layers = ( item.layers || [] ).map( function ( layer ) {
				var coverage = layer.coverage && layer.coverage !== 'whole' ? ' (' + ( layer.coverage_label || layer.coverage ) + ')' : '';
				return '<li>' + esc( layer.name ) + esc( coverage ) + '</li>';
			} ).join( '' );
			var size = item.size && item.size.label ? ' — ' + item.size.label : '';
			return '<div class="ticket-item">'
				+ '<p><strong>' + ( index + 1 ) + '. ' + esc( item.name || 'Pizza' ) + esc( size ) + ' × ' + esc( item.quantity ) + '</strong></p>'
				+ ( layers ? '<ul>' + layers + '</ul>' : '' )
				+ ( item.notes ? '<p class="ticket-note">📝 ' + esc( item.notes ) + '</p>' : '' )
				+ '</div>';
		} ).join( '' );

		target.innerHTML =
			'<div class="ticket">'
			+ '<h1>' + esc( cfg.siteName ) + '</h1>'
			+ '<h2>' + esc( s.number ) + '</h2>'
			+ '<p>' + methodIcon( s.fulfillment.method ) + ' ' + esc( s.fulfillment.method_label )
			+ ( s.fulfillment.requested_time ? ' — ⏰ ' + esc( s.fulfillment.requested_time ) : '' ) + '</p>'
			+ '<p>' + esc( s.customer.name || i18n.guest ) + ( s.customer.phone ? ' · ' + esc( s.customer.phone ) : '' ) + '</p>'
			+ ( data.address_line ? '<p>' + esc( data.address_line ) + '</p>' : '' )
			+ '<hr>' + itemsHtml
			+ ( data.customer_note ? '<hr><p class="ticket-note">📝 ' + esc( data.customer_note ) + '</p>' : '' )
			+ '<hr><p class="ticket-total">' + esc( i18n.total ) + ': ' + esc( data.totals_display.total ) + '</p>'
			+ '<p class="ticket-placed">' + esc( data.placed_display ) + '</p>'
			+ '</div>';

		document.body.classList.add( 'pzt-dash-printing' );
		window.print();
		window.setTimeout( function () {
			document.body.classList.remove( 'pzt-dash-printing' );
			target.innerHTML = '';
		}, 400 );
	}

	// ── Polling ──────────────────────────────────────────────────────────

	function startPolling() {
		stopPolling();
		state.pollTimer = window.setInterval( function () {
			if ( document.hidden ) { return; } // Save requests while the tab is in the background.
			refreshSnapshot();
		}, Math.max( 5, cfg.pollSeconds ) * 1000 );
	}

	function stopPolling() {
		if ( state.pollTimer ) {
			window.clearInterval( state.pollTimer );
			state.pollTimer = null;
		}
	}

	// ── Event wiring ─────────────────────────────────────────────────────

	function bind() {
		// Count cards filter the list.
		document.addEventListener( 'click', function ( e ) {
			var card = e.target.closest ? e.target.closest( '.pzt-dash-card' ) : null;
			if ( card ) {
				state.status = card.getAttribute( 'data-status' );
				state.paged = 1;
				state.selected = {};
				$$( '.pzt-dash-card' ).forEach( function ( c ) {
					c.classList.toggle( 'is-current', c === card );
				} );
				refreshList();
				return;
			}

			var nextBtn = e.target.closest ? e.target.closest( '.pzt-dash-next-btn' ) : null;
			if ( nextBtn ) {
				nextBtn.disabled = true;
				setStatus( parseInt( nextBtn.getAttribute( 'data-id' ), 10 ), nextBtn.getAttribute( 'data-status' ) )
					.then( function () { closeDrawer(); } )
					.catch( function () { nextBtn.disabled = false; } );
				return;
			}

			var opener = e.target.closest ? e.target.closest( '.pzt-dash-open-drawer' ) : null;
			if ( opener ) {
				openDrawer( parseInt( opener.getAttribute( 'data-id' ), 10 ) );
				return;
			}

			if ( e.target.closest && e.target.closest( '.pzt-dash-drawer-close' ) ) {
				closeDrawer();
				return;
			}

			if ( e.target.id === 'pzt-dash-drawer-backdrop' ) {
				closeDrawer();
				return;
			}

			var printBtn = e.target.closest ? e.target.closest( '.pzt-dash-print-btn' ) : null;
			if ( printBtn ) {
				printTicket();
				return;
			}

			if ( e.target.id === 'pzt-dash-dr-status-apply' ) {
				var select = $( '#pzt-dash-dr-status' );
				if ( select ) {
					setStatus( parseInt( e.target.getAttribute( 'data-id' ), 10 ), select.value )
						.then( function () { openDrawer( parseInt( e.target.getAttribute( 'data-id' ), 10 ) ); } );
				}
				return;
			}

			if ( e.target.id === 'pzt-dash-dr-note-add' ) {
				var textarea = $( '#pzt-dash-dr-note' );
				if ( textarea && textarea.value.trim() !== '' ) {
					ajax( 'pizzatier_orders_add_note', {
						order_id: e.target.getAttribute( 'data-id' ),
						note: textarea.value
					} ).then( function ( data ) {
						toast( data.message );
						textarea.value = '';
						var list = $( '#pzt-dash-dr-notes' );
						if ( list ) {
							list.innerHTML = data.notes_display.map( function ( n ) {
								return '<li><p>' + esc( n.note ) + '</p><small>' + esc( n.by ) + ' · ' + esc( n.when ) + '</small></li>';
							} ).join( '' );
						}
					} ).catch( function ( err ) {
						toast( err.message || i18n.actionError, true );
					} );
				}
				return;
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeDrawer(); }
		} );

		// Search with debounce.
		var search = $( '#pzt-dash-search' );
		if ( search ) {
			search.addEventListener( 'input', function () {
				window.clearTimeout( state.searchTimer );
				state.searchTimer = window.setTimeout( function () {
					state.search = search.value.trim();
					state.paged = 1;
					refreshList();
				}, 350 );
			} );
		}

		// Filters.
		[ [ '#pzt-dash-fulfillment', 'fulfillment' ], [ '#pzt-dash-date-from', 'dateFrom' ], [ '#pzt-dash-date-to', 'dateTo' ] ]
			.forEach( function ( pair ) {
				var el = $( pair[ 0 ] );
				if ( el ) {
					el.addEventListener( 'change', function () {
						state[ pair[ 1 ] ] = el.value;
						state.paged = 1;
						refreshList();
					} );
				}
			} );

		var clear = $( '#pzt-dash-clear' );
		if ( clear ) {
			clear.addEventListener( 'click', function () {
				state.search = '';
				state.fulfillment = '';
				state.dateFrom = '';
				state.dateTo = '';
				state.status = '';
				state.paged = 1;
				if ( search ) { search.value = ''; }
				[ '#pzt-dash-fulfillment', '#pzt-dash-date-from', '#pzt-dash-date-to' ].forEach( function ( sel ) {
					var el = $( sel );
					if ( el ) { el.value = ''; }
				} );
				$$( '.pzt-dash-card' ).forEach( function ( c ) {
					c.classList.toggle( 'is-current', c.getAttribute( 'data-status' ) === '' );
				} );
				refreshList();
			} );
		}

		// Sortable headers.
		$$( '.pzt-dash-sort' ).forEach( function ( th ) {
			th.addEventListener( 'click', function () {
				var key = th.getAttribute( 'data-sort' );
				if ( state.orderby === key ) {
					state.order = state.order === 'asc' ? 'desc' : 'asc';
				} else {
					state.orderby = key;
					state.order = key === 'date' ? 'desc' : 'asc';
				}
				state.paged = 1;
				$$( '.pzt-dash-sort' ).forEach( function ( other ) {
					other.classList.remove( 'is-sorted-asc', 'is-sorted-desc' );
				} );
				th.classList.add( state.order === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc' );
				refreshList();
			} );
		} );

		// Pagination.
		var prev = $( '#pzt-dash-prev' );
		var next = $( '#pzt-dash-next' );
		if ( prev ) { prev.addEventListener( 'click', function () { if ( state.paged > 1 ) { state.paged--; refreshList(); } } ); }
		if ( next ) { next.addEventListener( 'click', function () { if ( state.paged < state.pages ) { state.paged++; refreshList(); } } ); }

		// Selection.
		document.addEventListener( 'change', function ( e ) {
			if ( e.target.classList && e.target.classList.contains( 'pzt-dash-check' ) ) {
				var id = e.target.value;
				if ( e.target.checked ) { state.selected[ id ] = true; }
				else { delete state.selected[ id ]; }
				syncBulkBar();
			}
			if ( e.target.id === 'pzt-dash-check-all' ) {
				$$( '.pzt-dash-check' ).forEach( function ( box ) {
					box.checked = e.target.checked;
					if ( e.target.checked ) { state.selected[ box.value ] = true; }
					else { delete state.selected[ box.value ]; }
				} );
				syncBulkBar();
			}
		} );

		// Bulk apply.
		var bulkApply = $( '#pzt-dash-bulk-apply' );
		if ( bulkApply ) {
			bulkApply.addEventListener( 'click', function () {
				var op = $( '#pzt-dash-bulk-op' ).value;
				var ids = Object.keys( state.selected );
				if ( ! op || ! ids.length ) { return; }
				if ( op === 'delete' && ! window.confirm( i18n.confirmDelete ) ) { return; }
				runBulk( op, ids );
			} );
		}

		// Live toggle.
		var live = $( '#pzt-dash-live' );
		if ( live ) {
			live.addEventListener( 'click', function () {
				state.live = ! state.live;
				live.classList.toggle( 'is-on', state.live );
				live.setAttribute( 'aria-pressed', state.live ? 'true' : 'false' );
				$( '.pzt-dash-toggle__state', live ).textContent = state.live ? i18n.live : i18n.paused;
				if ( state.live ) { refreshSnapshot(); startPolling(); }
				else { stopPolling(); }
			} );
		}

		// Sound toggle.
		var sound = $( '#pzt-dash-sound' );
		if ( sound ) {
			var paintSound = function () {
				sound.classList.toggle( 'is-on', state.sound );
				sound.setAttribute( 'aria-pressed', state.sound ? 'true' : 'false' );
				$( '.pzt-dash-toggle__state', sound ).textContent = state.sound ? i18n.soundOn : i18n.soundOff;
			};
			paintSound();
			sound.addEventListener( 'click', function () {
				state.sound = ! state.sound;
				window.localStorage.setItem( 'pztDashSound', state.sound ? '1' : '0' );
				paintSound();
				if ( state.sound ) { chime(); } // Confirm audibly that it works.
			} );
		}

		// Refresh the snapshot as soon as the tab becomes visible again.
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden && state.live ) { refreshSnapshot(); }
		} );
	}

	// ── Boot ─────────────────────────────────────────────────────────────

	bind();
	refreshSnapshot();
	refreshList();
	startPolling();
} )();
