( function () {
	'use strict';

	var cfg      = window.pizzatierContentHub || {};
	var NONCE    = cfg.nonce    || '';
	var AJAX_URL = cfg.ajaxUrl  || '';
	var CPT_DATA = cfg.cptData  || {};
	var current  = cfg.active   || '';
	var currentView = cfg.view  || 'list';

	// Per-slug enabled columns, learned from server responses.
	var colsBySlug = {};

	var $rail        = document.querySelector( '.plch-rail' );
	var $main        = document.getElementById( 'plch-main' );
	var $panel       = document.getElementById( 'plch-panel-content' );
	var $loading     = document.getElementById( 'plch-loading' );
	var $header      = document.getElementById( 'plch-header' );
	var $headerIcon  = document.getElementById( 'plch-header-icon' );
	var $headerLabel = document.getElementById( 'plch-header-label' );
	var $headerDesc  = document.getElementById( 'plch-header-desc' );
	var $addBtn      = document.getElementById( 'plch-add-btn' );
	var $addSingular = document.getElementById( 'plch-add-singular' );
	var $wpListBtn   = document.getElementById( 'plch-wp-list-btn' );

	// Composite cache so a slug rendered in different views/columns is stored separately.
	var panelCache = {};
	function cacheKey( slug, view, cols ) {
		return slug + '|' + view + '|' + ( cols ? cols.slice().sort().join( ',' ) : '' );
	}
	if ( $panel ) {
		panelCache[ cacheKey( current, currentView, null ) ] = $panel.innerHTML;
	}

	// When a column toggle triggers a reload we re-open the menu afterwards.
	var reopenColMenu = false;

	function showLoading() {
		if ( $loading ) { $loading.style.display = 'flex'; }
		if ( $panel )   { $panel.classList.add( 'plch-fading' ); }
	}
	function hideLoading() {
		if ( $loading ) { $loading.style.display = 'none'; }
		if ( $panel )   { $panel.classList.remove( 'plch-fading' ); }
	}

	function setActiveRailItem( slug ) {
		var meta = CPT_DATA[ slug ];

		document.querySelectorAll( '.plch-rail__item' ).forEach( function ( el ) {
			var s        = el.getAttribute( 'data-slug' );
			var isActive = ( s === slug );
			el.classList.toggle( 'plch-rail__item--active', isActive );
			el.setAttribute( 'aria-current', isActive ? 'page' : 'false' );

			var icon = el.querySelector( '.plch-rail__icon' );
			var m    = CPT_DATA[ s ];
			if ( m && icon ) {
				icon.style.background = isActive ? m.color + '20' : '';
				icon.style.color      = isActive ? m.color : '';
			}
			var count = el.querySelector( '.plch-rail__count' );
			if ( count ) {
				count.style.background = isActive ? '#dce8f7' : '';
				count.style.color      = isActive ? '#2271b1' : '';
			}
		} );

		if ( ! meta ) { return; }
		if ( $headerIcon )  { $headerIcon.className = 'dashicons ' + meta.icon + ' plch-header__icon'; $headerIcon.style.color = meta.color; }
		if ( $headerLabel ) { $headerLabel.textContent = meta.label; }
		if ( $headerDesc )  { $headerDesc.textContent  = meta.desc; }
		if ( $addBtn )      { $addBtn.href = meta.addUrl; }
		if ( $addSingular ) { $addSingular.textContent = meta.singular; }
		if ( $header )      { $header.style.borderColor = meta.color + '60'; }
		if ( $wpListBtn && meta.wpListUrl ) { $wpListBtn.href = meta.wpListUrl; }
	}

	function reinitTableLinks( slug ) {
		var hubBase = AJAX_URL.replace( 'admin-ajax.php', 'admin.php' ) + '?page=pizzatier-content';
		document.querySelectorAll( '.plch-main .wp-list-table th a' ).forEach( function ( a ) {
			try {
				var url     = new URL( a.href, window.location.href );
				var orderby = url.searchParams.get( 'orderby' );
				var order   = url.searchParams.get( 'order' );
				if ( orderby ) {
					a.href = hubBase + '&pl_cpt=' + encodeURIComponent( slug ) +
					         '&orderby=' + encodeURIComponent( orderby ) +
					         '&order=' + encodeURIComponent( order || 'asc' );
				}
			} catch ( e ) {}
		} );
	}

	function afterSwap( slug ) {
		setActiveRailItem( slug );
		reinitTableLinks( slug );
		history.replaceState( null, '', window.location.pathname + '?page=pizzatier-content&pl_cpt=' + slug );
		if ( reopenColMenu ) {
			reopenColMenu = false;
			var pop = document.querySelector( '.plch-colmenu__pop' );
			var btn = document.querySelector( '.plch-colmenu__btn' );
			if ( pop && btn ) { pop.hidden = false; btn.setAttribute( 'aria-expanded', 'true' ); }
		}
	}

	/**
	 * Load (or reload) a panel.
	 * opts: { view: 'list'|'grid', cols: [..], force: bool }
	 */
	function loadPanel( slug, opts ) {
		opts = opts || {};
		var view = opts.view || currentView;
		var cols = opts.cols || colsBySlug[ slug ] || null;
		var key  = cacheKey( slug, view, cols );

		if ( ! opts.force && slug === current && view === currentView && panelCache[ key ] ) {
			return;
		}

		// Serve from cache when we have an exact match and nothing is being persisted.
		if ( ! opts.force && panelCache[ key ] && ! opts.view && ! opts.cols ) {
			current     = slug;
			currentView = view;
			if ( $panel ) { $panel.innerHTML = panelCache[ key ]; }
			afterSwap( slug );
			return;
		}

		document.querySelectorAll( '.plch-rail__item' ).forEach( function ( el ) {
			el.classList.toggle(
				'plch-rail__item--loading',
				el.getAttribute( 'data-slug' ) !== current && el.getAttribute( 'data-slug' ) !== slug
			);
		} );
		showLoading();

		var formData = new FormData();
		formData.append( 'action', 'pizzatier_content_panel' );
		formData.append( 'nonce', NONCE );
		formData.append( 'cpt', slug );
		if ( opts.view ) { formData.append( 'view', opts.view ); }
		if ( opts.cols ) { formData.append( 'cols', JSON.stringify( opts.cols ) ); }

		fetch( AJAX_URL, { method: 'POST', body: formData } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( d.success && d.data && typeof d.data.html === 'string' ) {
					current     = slug;
					currentView = d.data.view || view;
					if ( d.data.cols ) { colsBySlug[ slug ] = d.data.cols; }
					panelCache[ cacheKey( slug, currentView, colsBySlug[ slug ] ) ] = d.data.html;
					if ( $panel ) { $panel.innerHTML = d.data.html; }
					afterSwap( slug );
				}
			} )
			.catch( function ( err ) { console.error( 'PizzaTier ContentHub:', err ); } )
			.finally( function () {
				hideLoading();
				document.querySelectorAll( '.plch-rail__item' ).forEach( function ( el ) {
					el.classList.remove( 'plch-rail__item--loading' );
				} );
			} );
	}

	// ── Rail: switch CPT ────────────────────────────────────────────────
	if ( $rail ) {
		$rail.addEventListener( 'click', function ( e ) {
			var item = e.target.closest( '.plch-rail__item' );
			if ( ! item ) { return; }
			if ( e.target.closest( '.plch-rail__add' ) ) { return; }
			e.preventDefault();
			var slug = item.getAttribute( 'data-slug' );
			if ( slug ) { loadPanel( slug ); }
		} );
	}

	// ── Toolbar (delegated — panel HTML is swapped on every load) ────────
	if ( $main ) {
		$main.addEventListener( 'click', function ( e ) {

			// View toggle
			var vbtn = e.target.closest( '.plch-vbtn' );
			if ( vbtn ) {
				var view = vbtn.getAttribute( 'data-view' );
				if ( view && view !== currentView ) {
					loadPanel( current, { view: view, force: true } );
				}
				return;
			}

			// Columns menu open/close
			var cbtn = e.target.closest( '.plch-colmenu__btn' );
			if ( cbtn ) {
				var pop = cbtn.parentNode.querySelector( '.plch-colmenu__pop' );
				if ( pop ) {
					var willOpen = pop.hidden;
					pop.hidden = ! willOpen;
					cbtn.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' );
				}
				return;
			}

			// Grid "select all"
			var checkAll = e.target.closest( '#plch-grid-checkall' );
			if ( checkAll ) {
				var boxes = document.querySelectorAll( '.plch-card__check input[type="checkbox"]' );
				boxes.forEach( function ( b ) { b.checked = checkAll.checked; } );
				return;
			}

			// Click outside an open columns menu → close it
			if ( ! e.target.closest( '.plch-colmenu' ) ) {
				var openPop = document.querySelector( '.plch-colmenu__pop:not([hidden])' );
				if ( openPop ) {
					openPop.hidden = true;
					var ob = document.querySelector( '.plch-colmenu__btn' );
					if ( ob ) { ob.setAttribute( 'aria-expanded', 'false' ); }
				}
			}
		} );

		// Column checkbox toggles
		$main.addEventListener( 'change', function ( e ) {
			var cb = e.target.closest( '.plch-col-cb' );
			if ( ! cb ) { return; }
			var enabled = [];
			document.querySelectorAll( '.plch-col-cb' ).forEach( function ( box ) {
				if ( box.checked ) { enabled.push( box.getAttribute( 'data-col' ) ); }
			} );
			reopenColMenu = true;
			loadPanel( current, { cols: enabled, force: true } );
		} );
	}

	// ── Bulk form: rewrite action so it posts back to the hub ───────────
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || form.id !== 'plch-bulk-form' ) { return; }
		var hubBase = window.location.pathname + '?page=pizzatier-content&pl_cpt=' + encodeURIComponent( current );
		form.action = hubBase;
		// Let native submit proceed (no preventDefault)
	} );

	// ── Search form: keep pl_cpt in sync ────────────────────────────────
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form || form.id !== 'plch-search-form' ) { return; }
		var cptInput = form.querySelector( 'input[name="pl_cpt"]' );
		if ( cptInput ) { cptInput.value = current; }
	} );

	reinitTableLinks( current );

} )();
