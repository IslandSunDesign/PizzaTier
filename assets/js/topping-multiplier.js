/**
 * PizzaTier Topping Multiplier — front end.
 *
 * Works with every bundled PizzaTier template without modifying them:
 *
 *  1. AMOUNT CONTROL — a "− Double +" stepper is added to each selected
 *     topping's card (next to its coverage picker) and to the template's
 *     coverage dialog where it has one.
 *
 *  2. STATE — window.PizzaTierAPI.getState() (and each registered instance's
 *     getState) is wrapped so the state every consumer reads (live pricing,
 *     cart, native orders) carries one extra topping layer per added portion,
 *     inserted right after its base topping: "pepperoni~2", "pepperoni~3".
 *     The template's own internal state is never mutated.
 *
 *  3. PRICING — each added portion is mapped to the post ID whose price grid
 *     PizzaTier's live pricing should use (the topping, a linked layer, or a
 *     derived grid for percentage / fixed / free pricing). The server repeats
 *     the same resolution from its own settings before charging.
 *
 *  4. PREVIEW — added portions are drawn on the pizza: a rotated copy of the
 *     topping image (Thickness) or the linked layer's image (Separate layers),
 *     clipped to the same coverage as the base topping.
 */
( function () {
	'use strict';

	var CFG = window.pizzatierMultiplierConfig;
	if ( ! CFG || ! CFG.toppings ) { return; }

	var SEP    = CFG.sep || '~';
	var SYN    = Number( CFG.synBase ) || 900000000000;
	var LBL    = CFG.labels || {};
	var VISUAL = CFG.visual || 'scatter';

	var ROOT_SEL     = '.cb-root,.np-root,.mt-root,.rp-root,.pp-root,.pl-root,.sc-root,.cc-root';
	var COVERAGE_SEL = '.cb-coverage,.np-coverage,.mt-coverage,.rp-coverage,.cc-coverage,.pp-coverage,.sc-coverage';
	var MODAL_SEL    = '.cb-cov-modal,.mt-cov-modal,.pl-cov-modal';
	var CARD_RE      = /(^|\s)[a-z]+-(card|chip|item)--topping(\s|$)/;
	/* Rotation per added portion (index = portion number). Beyond these, each
	   copy turns by the golden angle, so any number of copies stays evenly
	   spread and no two land on the same angle. */
	var ANGLES = [ 0, 0, 52, 137, 221, 293, 331 ];
	var GOLDEN = 137.50776;
	function angleFor( n ) {
		if ( n < ANGLES.length ) { return ANGLES[ n ]; }
		return Math.round( ( ( n * GOLDEN ) % 360 ) * 10 ) / 10;
	}

	/* instanceId → { slug: level } */
	var store = {};
	/* instanceId → slug of the topping whose coverage dialog was opened last */
	var lastCardSlug = {};

	// =====================================================================
	// Helpers
	// =====================================================================

	function own( o, k ) { return o && Object.prototype.hasOwnProperty.call( o, k ); }

	function conf( slug ) { return own( CFG.toppings, slug ) ? CFG.toppings[ slug ] : null; }

	function levels( id ) { return store[ id ] || ( store[ id ] = {} ); }

	function cssEsc( s ) {
		s = String( s );
		if ( window.CSS && typeof window.CSS.escape === 'function' ) { return window.CSS.escape( s ); }
		return s.replace( /["\\\]\[]/g, '\\$&' );
	}

	function parseExtra( id ) {
		var m = String( id || '' ).match( new RegExp( '^(.+)' + SEP.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '(\\d{1,3})$' ) );
		return m ? [ m[1], parseInt( m[2], 10 ) ] : null;
	}

	function isToppingType( t ) {
		t = String( t || '' ).toLowerCase();
		return t === 'topping' || t === 'toppings';
	}

	function levelLabel( n ) {
		var a = LBL.levels || [];
		return a[ n - 1 ] || ( n + '×' );
	}

	function ordinal( n ) {
		var m = n % 100;
		if ( m >= 11 && m <= 13 ) { return n + 'th'; }
		switch ( n % 10 ) {
			case 1: return n + 'st';
			case 2: return n + 'nd';
			case 3: return n + 'rd';
		}
		return n + 'th';
	}

	function fill( tpl, name, n, level, layer ) {
		return String( tpl )
			.split( '{name}' ).join( name )
			.split( '{n}' ).join( String( n ) )
			.split( '{ordinal}' ).join( ordinal( n ) )
			.split( '{level}' ).join( levelLabel( level ) )
			.split( '{layer}' ).join( layer || name );
	}

	/* Linked layer for portion n — levels without their own repeat the one below. */
	function levelLayer( c, n ) {
		var lv = c.levels || {};
		for ( var i = n; i >= 2; i-- ) {
			if ( lv[ i ] ) { return lv[ i ]; }
		}
		return null;
	}

	function baseName( c, title, level ) {
		if ( level < 2 ) { return title; }
		var tpl = c.method === 'layers' ? ( LBL.baseLayers || '{name} ({n} layers)' ) : ( LBL.base || '{name} ({level})' );
		return fill( tpl, c.title || title, level, level, '' );
	}

	function extraName( c, title, n, level ) {
		var tpl = LBL.extra || '{name} — {ordinal} portion', layer = '';
		if ( c.method === 'layers' ) {
			tpl = LBL.layer || '{name} — layer {n}';
			var ll = levelLayer( c, n );
			if ( ll && ll.title ) {
				layer = ll.title;
				if ( LBL.extraLayers && String( LBL.extraLayers ).trim() ) { tpl = LBL.extraLayers; }
			}
		}
		var s = fill( tpl, c.title || title, n, level, layer );
		return s.trim() ? s : ( ( c.title || title ) + ' #' + n );
	}

	function pricePostId( c, n ) {
		if ( c.method === 'layers' ) {
			var ll = levelLayer( c, n );
			if ( ll && ll.id ) { return ll.id; }
		}
		return c.pricing === 'full' ? c.id : SYN + c.id;
	}

	// =====================================================================
	// Template state
	// =====================================================================

	function roots() { return Array.prototype.slice.call( document.querySelectorAll( ROOT_SEL ) ); }

	function instanceIdOf( root ) { return root.getAttribute( 'data-instance' ) || root.id || ''; }

	function singleInstanceId() {
		var r = roots();
		return r.length === 1 ? instanceIdOf( r[0] ) : '';
	}

	function api() { return window.PizzaTierAPI || null; }

	function unwrap( fn ) { return ( fn && fn.__pztmOrig ) || fn; }

	/** The template's own state for an instance, before augmentation. */
	function rawState( id ) {
		var a = api(), s = null;
		if ( ! a ) { return null; }
		if ( typeof a.getState === 'function' ) {
			try { s = unwrap( a.getState ).call( a, id ); } catch ( e ) { s = null; }
		}
		if ( ! s && typeof a.getInstances === 'function' ) {
			var map = null;
			try { map = a.getInstances(); } catch ( e ) { map = null; }
			if ( map && map[ id ] && typeof map[ id ].getState === 'function' ) {
				try { s = unwrap( map[ id ].getState ).call( map[ id ] ); } catch ( e ) { s = null; }
			}
		}
		if ( s && s.__pztmRaw ) { s = s.__pztmRaw; }
		return s && typeof s === 'object' ? s : null;
	}

	/** Selected toppings (in template order) from any template's state shape. */
	function selectedToppings( s ) {
		var out = [], seen = {};
		if ( ! s ) { return out; }
		function push( slug, title, cov ) {
			slug = String( slug || '' );
			if ( ! slug || seen[ slug ] || parseExtra( slug ) ) { return; }
			seen[ slug ] = true;
			out.push( { slug: slug, title: String( title || slug ), coverage: String( cov || 'whole' ) } );
		}
		if ( Array.isArray( s.layers ) ) {
			s.layers.forEach( function ( l ) {
				if ( l && isToppingType( l.type || l.layerType ) ) {
					push( l.layerId || l.id || l.slug, l.title || l.layerName || l.name, l.coverage || l.portion || l.fraction );
				}
			} );
			return out;
		}
		var tp = s.toppings;
		if ( tp && typeof tp === 'object' ) {
			if ( Array.isArray( tp ) ) {
				tp.forEach( function ( t ) { if ( t ) { push( t.slug || t.layerId || t.id, t.title || t.name, t.coverage ); } } );
			} else {
				Object.keys( tp ).forEach( function ( k ) {
					var t = tp[ k ] || {};
					push( t.slug || k, t.title || t.name, t.coverage );
				} );
			}
		}
		return out;
	}

	/** Added portions for an instance: one entry per portion beyond the first. */
	function extrasFor( id, sel ) {
		var lv = levels( id ), out = [];
		sel.forEach( function ( t ) {
			var c = conf( t.slug );
			var level = c ? Math.min( lv[ t.slug ] || 1, c.max ) : 1;
			if ( ! c || level < 2 ) { return; }
			for ( var n = 2; n <= level; n++ ) {
				out.push( {
					id:     t.slug + SEP + n,
					slug:   t.slug,
					n:      n,
					level:  level,
					conf:   c,
					title:  extraName( c, t.title, n, level ),
					postId: pricePostId( c, n )
				} );
			}
		} );
		return out;
	}

	function prune( id, sel ) {
		var lv = levels( id ), present = {};
		sel.forEach( function ( t ) { present[ t.slug ] = true; } );
		Object.keys( lv ).forEach( function ( k ) { if ( ! present[ k ] ) { delete lv[ k ]; } } );
	}

	// =====================================================================
	// State augmentation
	// =====================================================================

	function setTitle( obj, name ) {
		var any = false;
		[ 'title', 'layerName', 'name' ].forEach( function ( k ) {
			if ( own( obj, k ) ) { obj[ k ] = name; any = true; }
		} );
		if ( ! any ) { obj.title = name; }
	}

	function copy( o ) {
		var c = {};
		for ( var k in o ) { if ( own( o, k ) ) { c[ k ] = o[ k ]; } }
		return c;
	}

	/**
	 * Return a copy of a template state with every added portion inserted
	 * directly after its base topping, in whichever shapes the state uses.
	 */
	function augment( state, id ) {
		if ( ! state || typeof state !== 'object' || state.__pztm ) { return state; }
		var extras = extrasFor( id, selectedToppings( state ) );
		if ( ! extras.length ) { return state; }

		ensurePriceMaps( extras );

		var byBase = {};
		extras.forEach( function ( e ) { ( byBase[ e.slug ] = byBase[ e.slug ] || [] ).push( e ); } );

		var clone = copy( state );
		Object.defineProperty( clone, '__pztm', { value: true } );
		Object.defineProperty( clone, '__pztmRaw', { value: state } );

		function insertInto( arr, slugOf, onlyToppings ) {
			var out = [];
			arr.forEach( function ( item ) {
				var slug = item ? String( slugOf( item ) || '' ) : '';
				var ex   = byBase[ slug ];
				if ( ! ex || ( onlyToppings && ! isToppingType( item.type || item.layerType ) ) ) {
					out.push( item );
					return;
				}
				var base = copy( item );
				setTitle( base, baseName( ex[0].conf, item.title || item.layerName || item.name || slug, ex[0].level ) );
				out.push( base );
				ex.forEach( function ( e ) {
					var x = copy( item );
					[ 'id', 'layerId', 'slug' ].forEach( function ( k ) { if ( own( x, k ) ) { x[ k ] = e.id; } } );
					if ( ! own( x, 'layerId' ) && ! own( x, 'slug' ) && ! own( x, 'id' ) ) { x.slug = e.id; }
					setTitle( x, e.title );
					x.layerPostId = e.postId;
					x.pztmPortion = e.n;
					out.push( x );
				} );
			} );
			return out;
		}

		function layerSlug( l ) { return l.layerId || l.id || l.slug; }
		function toppingSlug( t ) { return t.slug || t.layerId || t.id; }

		if ( Array.isArray( state.layers ) )    { clone.layers    = insertInto( state.layers, layerSlug, true ); }
		if ( Array.isArray( state.allLayers ) ) { clone.allLayers = insertInto( state.allLayers, layerSlug, true ); }

		if ( state.toppings && typeof state.toppings === 'object' ) {
			if ( Array.isArray( state.toppings ) ) {
				clone.toppings = insertInto( state.toppings, toppingSlug, false );
			} else {
				var map = {};
				Object.keys( state.toppings ).forEach( function ( k ) {
					var t = state.toppings[ k ];
					var ex = byBase[ ( t && t.slug ) || k ];
					if ( ! ex ) { map[ k ] = t; return; }
					var base = copy( t || {} );
					setTitle( base, baseName( ex[0].conf, ( t && ( t.title || t.name ) ) || k, ex[0].level ) );
					map[ k ] = base;
					ex.forEach( function ( e ) {
						var x = copy( t || {} );
						x.slug = e.id;
						setTitle( x, e.title );
						x.layerPostId = e.postId;
						x.pztmPortion = e.n;
						map[ e.id ] = x;
					} );
				} );
				clone.toppings = map;
			}
		}

		if ( state.selections && Array.isArray( state.selections.toppings ) ) {
			clone.selections = copy( state.selections );
			clone.selections.toppings = insertInto( state.selections.toppings, toppingSlug, false );
		}

		return clone;
	}

	function wrapGetState( owner, resolveId ) {
		var fn = owner && owner.getState;
		if ( typeof fn !== 'function' || fn.__pztmWrapped ) { return; }
		var wrapped = function () {
			var s = fn.apply( this, arguments );
			try {
				var id = resolveId( arguments, s );
				return id ? augment( s, id ) : s;
			} catch ( e ) {
				return s;
			}
		};
		wrapped.__pztmWrapped = true;
		wrapped.__pztmOrig    = fn;
		owner.getState        = wrapped;
	}

	/** Wrap the API (re-checked every tick: templates may replace it). */
	function ensureWrapped() {
		var a = api();
		if ( ! a ) { return; }
		wrapGetState( a, function ( args, s ) {
			if ( args.length && typeof args[0] === 'string' && args[0] ) { return args[0]; }
			if ( s && s.instanceId ) { return String( s.instanceId ); }
			return singleInstanceId();
		} );
		if ( typeof a.getInstances === 'function' ) {
			var map = null;
			try { map = a.getInstances(); } catch ( e ) { map = null; }
			if ( map && typeof map === 'object' ) {
				Object.keys( map ).forEach( function ( k ) {
					wrapGetState( map[ k ], function () { return k; } );
				} );
			}
		}
	}

	// =====================================================================
	// Live-pricing integration
	// =====================================================================

	function commerceConfigs() {
		var list = [];
		var arr  = window.pizzatier_commerceFrontendInstances;
		if ( Array.isArray( arr ) ) {
			arr.forEach( function ( c ) { if ( c && list.indexOf( c ) === -1 ) { list.push( c ); } } );
		}
		var one = window.pizzatier_commerceFrontend;
		if ( one && list.indexOf( one ) === -1 ) { list.push( one ); }
		return list;
	}

	function fullPrice( cfg, id, size, frac ) {
		var k  = size + '|' + frac;
		var lg = ( cfg.layerGrids || {} )[ String( id ) ];
		if ( lg && lg.cells && own( lg.cells, k ) ) { return parseFloat( lg.cells[ k ] ) || 0; }
		var g = cfg.priceGrid || {};
		if ( g.cells && own( g.cells, k ) ) { return parseFloat( g.cells[ k ] ) || 0; }
		var fg = cfg.flatGrid || {};
		var fk = 'topping|' + size;
		if ( fg.cells && own( fg.cells, fk ) ) { return parseFloat( fg.cells[ fk ] ) || 0; }
		return 0;
	}

	/** Mirror of the server's SyntheticGrid::derived_grid(). */
	function derivedGrid( cfg, c ) {
		var g     = cfg.priceGrid || {};
		var lg    = ( cfg.layerGrids || {} )[ String( c.id ) ];
		var sizes = ( g.sizes && g.sizes.length ) ? g.sizes.slice() : [ 'Small', 'Medium', 'Large', 'XL' ];
		var fr    = ( g.fractions && g.fractions.length ) ? g.fractions.slice() : [ 'Whole', 'Half', 'Quarter' ];
		if ( lg ) {
			( lg.sizes || [] ).forEach( function ( s ) { if ( sizes.indexOf( s ) === -1 ) { sizes.push( s ); } } );
			( lg.fractions || [] ).forEach( function ( f ) { if ( fr.indexOf( f ) === -1 ) { fr.push( f ); } } );
		}
		var cells = {}, v = Number( c.value ) || 0;
		sizes.forEach( function ( s ) {
			fr.forEach( function ( f ) {
				var p;
				if ( c.pricing === 'free' )       { p = 0; }
				else if ( c.pricing === 'fixed' ) { p = v; }
				else {
					p = fullPrice( cfg, c.id, s, f );
					if ( c.pricing === 'percent' ) { p = p * v / 100; }
				}
				cells[ s + '|' + f ] = Math.round( p * 10000 ) / 10000;
			} );
		} );
		return { sizes: sizes, fractions: fr, cells: cells };
	}

	/**
	 * Teach PizzaTier's live pricing which grid each added portion uses. The
	 * maps are the very objects frontend-builder.js holds, so additions are
	 * picked up on its next recalculation.
	 */
	function ensurePriceMaps( extras ) {
		commerceConfigs().forEach( function ( cfg ) {
			var map   = cfg.layerPostIdMap;
			var grids = cfg.layerGrids;
			if ( ! map || typeof map !== 'object' ) { return; }
			extras.forEach( function ( e ) {
				map[ e.id ] = e.postId;
				var key = String( e.postId );
				if ( ! grids || typeof grids !== 'object' || grids[ key ] ) { return; }
				// Prefer the grid the server computed; fall back to deriving one.
				if ( CFG.grids && CFG.grids[ key ] ) {
					grids[ key ] = CFG.grids[ key ];
				} else if ( e.postId > SYN ) {
					grids[ key ] = derivedGrid( cfg, e.conf );
				}
			} );
		} );
	}

	function recalc() {
		( window.PizzaTierBuilderInstances || [] ).forEach( function ( b ) {
			try { if ( b && typeof b.recalculate === 'function' ) { b.recalculate(); } } catch ( e ) {}
		} );
	}

	// =====================================================================
	// Amount control
	// =====================================================================

	function maxToppings( root ) {
		var m = parseInt( root.getAttribute( 'data-max-toppings' ), 10 );
		if ( ! m || m <= 0 ) { m = parseInt( CFG.globalMax, 10 ) || 0; }
		return m > 0 ? m : 0;
	}

	function canAddPortion( root, id, sel ) {
		if ( ! CFG.countTowardMax ) { return true; }
		var max = maxToppings( root );
		if ( ! max ) { return true; }
		var total = sel.length, lv = levels( id );
		sel.forEach( function ( t ) { var c = conf( t.slug ); if ( c ) { total += Math.min( lv[ t.slug ] || 1, c.max ) - 1; } } );
		return total < max;
	}

	function stop( e ) { e.stopPropagation(); }

	/* Shared: keep pointer/key events away from card-level toggles. */
	function shield( el ) {
		[ 'mousedown', 'mouseup', 'pointerdown', 'pointerup', 'touchstart', 'touchend', 'keydown', 'keyup' ].forEach( function ( type ) {
			el.addEventListener( type, stop );
		} );
	}

	function buildControl( root, id, slug ) {
		var c = conf( slug );
		return c && c.method === 'layers' ? buildLayersControl( root, id, slug ) : buildThicknessControl( root, id, slug );
	}

	/*
	 * Separate-layers method: an "Add another layer" button plus one entry per
	 * added layer, each with its own remove button. Keeps the .pztm-amount
	 * wrapper, __value and __btn--more hooks so themes and scripts can treat
	 * both methods alike.
	 */
	function buildLayersControl( root, id, slug ) {
		var c  = conf( slug );
		var el = document.createElement( 'div' );
		el.className = 'pztm-amount pztm-amount--layers';
		el.setAttribute( 'data-pztm-slug', slug );
		el.setAttribute( 'data-pztm-method', 'layers' );
		el.setAttribute( 'role', 'group' );
		el.setAttribute( 'aria-label', String( LBL.layersGroup || 'Layers of %s' ).replace( '%s', c ? c.title : slug ) );
		el.innerHTML =
			'<span class="pztm-amount__label"></span>' +
			'<span class="pztm-amount__value" aria-live="polite"></span>' +
			'<ul class="pztm-layers"></ul>' +
			'<button type="button" class="pztm-amount__btn pztm-amount__btn--more pztm-layers__add"><span aria-hidden="true">+</span> <span class="pztm-layers__add-text"></span></button>';
		el.querySelector( '.pztm-amount__label' ).textContent = LBL.layersControl || 'Layers';
		el.querySelector( '.pztm-layers__add-text' ).textContent = LBL.addLayer || 'Add another layer';
		shield( el );
		el.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			e.preventDefault();
			var rm = e.target.closest ? e.target.closest( '.pztm-layers__remove' ) : null;
			if ( rm ) { removeLayer( root, id, slug, parseInt( rm.getAttribute( 'data-n' ), 10 ) ); return; }
			var btn = e.target.closest ? e.target.closest( '.pztm-layers__add' ) : null;
			if ( btn && ! btn.disabled ) { step( root, id, slug, 1 ); }
		} );
		return el;
	}

	/* Remove added layer n (2..level). Layers are numbered by position, so the
	   ones above it move down a place. */
	function removeLayer( root, id, slug, n ) {
		step( root, id, slug, -1 );
	}

	function buildThicknessControl( root, id, slug ) {
		var c   = conf( slug );
		var el  = document.createElement( 'div' );
		el.className = 'pztm-amount pztm-amount--thickness';
		el.setAttribute( 'data-pztm-method', 'thickness' );
		el.setAttribute( 'data-pztm-slug', slug );
		el.setAttribute( 'role', 'group' );
		el.setAttribute( 'aria-label', String( LBL.groupLabel || 'Amount of %s' ).replace( '%s', c ? c.title : slug ) );
		el.innerHTML =
			'<span class="pztm-amount__label"></span>' +
			'<span class="pztm-amount__stepper">' +
				'<button type="button" class="pztm-amount__btn pztm-amount__btn--less"><span aria-hidden="true">&minus;</span></button>' +
				'<span class="pztm-amount__value" aria-live="polite"></span>' +
				'<button type="button" class="pztm-amount__btn pztm-amount__btn--more"><span aria-hidden="true">+</span></button>' +
			'</span>';
		el.querySelector( '.pztm-amount__label' ).textContent = LBL.control || 'Thickness';
		el.querySelector( '.pztm-amount__btn--less' ).setAttribute( 'aria-label', LBL.less || 'Less' );
		el.querySelector( '.pztm-amount__btn--more' ).setAttribute( 'aria-label', LBL.more || 'More' );
		shield( el );
		el.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			e.preventDefault();
			var btn = e.target.closest ? e.target.closest( '.pztm-amount__btn' ) : null;
			if ( ! btn || btn.disabled ) { return; }
			step( root, id, slug, btn.classList.contains( 'pztm-amount__btn--more' ) ? 1 : -1 );
		} );
		return el;
	}

	function step( root, id, slug, delta ) {
		var c = conf( slug );
		if ( ! c ) { return; }
		var sel = selectedToppings( rawState( id ) );
		var lv  = levels( id );
		var cur = Math.min( lv[ slug ] || 1, c.max );
		if ( delta > 0 && ( cur >= c.max || ! canAddPortion( root, id, sel ) ) ) {
			flashLimit( root, slug );
			return;
		}
		var next = Math.max( 1, Math.min( c.max, cur + delta ) );
		if ( next === cur ) { return; }
		if ( next === 1 ) { delete lv[ slug ]; } else { lv[ slug ] = next; }
		syncRoot( root );
		recalc();
		try {
			root.dispatchEvent( new CustomEvent( 'pizzatier:portions:change', {
				bubbles: true,
				detail: { instanceId: id, slug: slug, level: next }
			} ) );
		} catch ( e ) {}
	}

	function flashLimit( root, slug ) {
		root.querySelectorAll( '.pztm-amount[data-pztm-slug="' + cssEsc( slug ) + '"]' ).forEach( function ( el ) {
			el.classList.remove( 'pztm-amount--limit' );
			void el.offsetWidth;
			el.classList.add( 'pztm-amount--limit' );
		} );
	}

	function updateControl( ctl, root, id, slug, sel ) {
		var c     = conf( slug );
		var level = Math.min( levels( id )[ slug ] || 1, c.max );
		var room  = canAddPortion( root, id, sel );
		if ( ctl.getAttribute( 'data-pztm-method' ) === 'layers' ) {
			updateLayersControl( ctl, c, slug, sel, level, room );
			return;
		}
		var val   = ctl.querySelector( '.pztm-amount__value' );
		var less  = ctl.querySelector( '.pztm-amount__btn--less' );
		var more  = ctl.querySelector( '.pztm-amount__btn--more' );
		var text  = levelLabel( level );
		if ( val.textContent !== text ) { val.textContent = text; }
		less.disabled = level <= 1;
		more.disabled = level >= c.max || ! room;
		more.title    = ( ! room && level < c.max ) ? ( LBL.limit || '' ) : '';
		ctl.setAttribute( 'data-level', String( level ) );
		ctl.classList.toggle( 'pztm-amount--multi', level > 1 );
	}

	function updateLayersControl( ctl, c, slug, sel, level, room ) {
		var title = c.title;
		sel.forEach( function ( t ) { if ( t.slug === slug && t.title ) { title = t.title; } } );
		var val  = ctl.querySelector( '.pztm-amount__value' );
		var text = level > 1 ? String( LBL.layerCount || '%d layers' ).replace( '%d', String( level ) ) : '';
		if ( val.textContent !== text ) { val.textContent = text; }

		// One entry per added layer; rebuilt only when the names change.
		var names = [];
		for ( var n = 2; n <= level; n++ ) { names.push( extraName( c, title, n, level ) ); }
		var list = ctl.querySelector( '.pztm-layers' );
		if ( list.getAttribute( 'data-names' ) !== names.join( '\n' ) ) {
			list.setAttribute( 'data-names', names.join( '\n' ) );
			list.innerHTML = '';
			names.forEach( function ( name, i ) {
				var li = document.createElement( 'li' );
				li.className = 'pztm-layers__item';
				var span = document.createElement( 'span' );
				span.className = 'pztm-layers__name';
				span.textContent = name;
				var rm = document.createElement( 'button' );
				rm.type = 'button';
				rm.className = 'pztm-layers__remove';
				rm.setAttribute( 'data-n', String( i + 2 ) );
				rm.setAttribute( 'aria-label', String( LBL.removeLayer || 'Remove %s' ).replace( '%s', name ) );
				rm.innerHTML = '<span aria-hidden="true">&times;</span>';
				li.appendChild( span );
				li.appendChild( rm );
				list.appendChild( li );
			} );
		}
		list.hidden = level < 2;

		var add = ctl.querySelector( '.pztm-layers__add' );
		add.disabled = level >= c.max || ! room;
		add.title    = ( ! room && level < c.max ) ? ( LBL.limit || '' ) : '';
		ctl.setAttribute( 'data-level', String( level ) );
		ctl.classList.toggle( 'pztm-amount--multi', level > 1 );
	}

	function cardsFor( root, slug ) {
		var out = [];
		root.querySelectorAll( '[data-slug="' + cssEsc( slug ) + '"]' ).forEach( function ( el ) {
			var cls = typeof el.className === 'string' ? el.className : '';
			if ( ! CARD_RE.test( cls ) ) { return; }
			for ( var i = 0; i < out.length; i++ ) { if ( out[ i ].contains( el ) ) { return; } }
			out.push( el );
		} );
		return out;
	}

	function placeControl( card, ctl ) {
		var cov = card.querySelector( COVERAGE_SEL );
		if ( cov && cov.parentNode ) {
			cov.parentNode.insertBefore( ctl, cov.nextSibling );
		} else {
			card.appendChild( ctl );
		}
	}

	function syncControls( root, id, sel ) {
		var selected = {};
		sel.forEach( function ( t ) { selected[ t.slug ] = t; } );

		root.querySelectorAll( '.pztm-amount' ).forEach( function ( el ) {
			var s = el.getAttribute( 'data-pztm-slug' );
			if ( ! selected[ s ] || ! conf( s ) ) { el.parentNode.removeChild( el ); }
		} );

		sel.forEach( function ( t ) {
			if ( ! conf( t.slug ) ) { return; }
			cardsFor( root, t.slug ).forEach( function ( card ) {
				var ctl = card.querySelector( '.pztm-amount[data-pztm-slug="' + cssEsc( t.slug ) + '"]' );
				if ( ! ctl ) {
					ctl = buildControl( root, id, t.slug );
					placeControl( card, ctl );
				}
				updateControl( ctl, root, id, t.slug, sel );
			} );
		} );

		// Coverage dialog (colorbox, metro, plainlist).
		root.querySelectorAll( MODAL_SEL ).forEach( function ( modal ) {
			var open = /--open(\s|$)/.test( modal.className ) || modal.getAttribute( 'aria-hidden' ) === 'false';
			var slug = lastCardSlug[ id ];
			var ctl  = modal.querySelector( '.pztm-amount' );
			if ( ! open || ! slug || ! selected[ slug ] || ! conf( slug ) ) {
				if ( ctl ) { ctl.parentNode.removeChild( ctl ); }
				return;
			}
			if ( ctl && ctl.getAttribute( 'data-pztm-slug' ) !== slug ) {
				ctl.parentNode.removeChild( ctl );
				ctl = null;
			}
			if ( ! ctl ) {
				ctl = buildControl( root, id, slug );
				ctl.classList.add( 'pztm-amount--modal' );
				var panel = modal.querySelector( '[class*="cov-modal__dialog"],[class*="cov-modal__panel"]' ) || modal;
				panel.appendChild( ctl );
			}
			updateControl( ctl, root, id, slug, sel );
		} );
	}

	// =====================================================================
	// Pizza preview
	// =====================================================================

	function findBaseLayer( root, slug ) {
		var s = cssEsc( slug );
		var el = root.querySelector( '[data-layer-id="layer-topping-' + s + '"]' ) ||
			root.querySelector( '[data-layer-id="pizzatier-topping-' + s + '"]' ) ||
			root.querySelector( 'img[data-topping-slug="' + s + '"]' );
		if ( el ) { return el; }
		var byId = document.getElementById( 'pizzatier-topping-' + slug );
		return byId && root.contains( byId ) && ! CARD_RE.test( String( byId.className ) ) ? byId : null;
	}

	function syncPreview( root, id, sel ) {
		var want = {}, lv = levels( id );
		sel.forEach( function ( t ) {
			var c = conf( t.slug );
			var level = c ? Math.min( lv[ t.slug ] || 1, c.max ) : 1;
			if ( ! c || level < 2 ) { return; }
			if ( VISUAL === 'none' && ! ( c.method === 'layers' && levelLayer( c, 2 ) ) ) { return; }
			var base = findBaseLayer( root, t.slug );
			if ( ! base || ! base.parentNode ) { return; }
			for ( var n = 2; n <= level; n++ ) {
				want[ t.slug + SEP + n ] = { slug: t.slug, n: n, c: c, base: base };
			}
		} );

		root.querySelectorAll( '.pztm-layer' ).forEach( function ( el ) {
			if ( ! want[ el.getAttribute( 'data-pztm-key' ) ] && el.parentNode ) { el.parentNode.removeChild( el ); }
		} );

		Object.keys( want ).sort().forEach( function ( key ) { drawPortion( root, key, want[ key ] ); } );
	}

	function drawPortion( root, key, w ) {
		var base    = w.base;
		var baseImg = base.tagName === 'IMG' ? base : base.querySelector( 'img' );
		if ( ! baseImg ) { return; }

		var src = baseImg.getAttribute( 'src' ) || '';
		var linkedArt = false;
		if ( w.c.method === 'layers' ) {
			var ll = levelLayer( w.c, w.n );
			if ( ll && ll.img ) { src = ll.img; linkedArt = true; }
		}
		if ( ! src ) { return; }

		var el = root.querySelector( '.pztm-layer[data-pztm-key="' + cssEsc( key ) + '"]' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.className = 'pztm-layer';
			el.setAttribute( 'data-pztm-key', key );
			el.setAttribute( 'data-pztm-for', w.slug );
			el.setAttribute( 'aria-hidden', 'true' );
			var img = document.createElement( 'img' );
			img.className = 'pztm-layer__img';
			img.alt       = '';
			img.decoding  = 'async';
			el.appendChild( img );
			var siblings = base.parentNode.querySelectorAll( '.pztm-layer[data-pztm-for="' + cssEsc( w.slug ) + '"]' );
			var after    = siblings.length ? siblings[ siblings.length - 1 ] : base;
			after.parentNode.insertBefore( el, after.nextSibling );
			requestAnimationFrame( function () { el.classList.add( 'pztm-layer--in' ); } );
		}

		var im = el.firstChild;
		if ( im.getAttribute( 'src' ) !== src ) { im.setAttribute( 'src', src ); }

		// Match the base layer's box (works whether the base is a wrapper div or the img itself).
		if ( base.offsetWidth ) {
			el.style.left   = base.offsetLeft + 'px';
			el.style.top    = base.offsetTop + 'px';
			el.style.width  = base.offsetWidth + 'px';
			el.style.height = base.offsetHeight + 'px';
		}

		var bcs = window.getComputedStyle( base );
		el.style.zIndex = ( bcs.zIndex && bcs.zIndex !== 'auto' ) ? bcs.zIndex : '';

		// Same coverage as the base topping (clip lives on the img or its wrapper).
		var ics  = window.getComputedStyle( baseImg );
		var clip = ics.clipPath || ics.webkitClipPath || '';
		if ( ! clip || clip === 'none' ) { clip = bcs.clipPath || bcs.webkitClipPath || ''; }
		clip = ( clip && clip !== 'none' ) ? clip : '';
		if ( el.style.clipPath !== clip ) {
			el.style.clipPath       = clip;
			el.style.webkitClipPath = clip;
		}

		var fit = ics.objectFit || 'contain';
		if ( im.style.objectFit !== fit ) { im.style.objectFit = fit; }

		// A linked layer has its own artwork and sits as drawn; a copy of the
		// topping's own image is turned so the layers read as thicker.
		var rot = ( ! linkedArt && VISUAL === 'scatter' ) ? angleFor( w.n ) : 0;
		var tr  = rot ? 'rotate(' + rot + 'deg)' : '';
		if ( im.style.transform !== tr ) { im.style.transform = tr; }
	}

	// =====================================================================
	// Template topping lists (summary bubbles, tray chips, summary rows, text)
	// =====================================================================

	/* Entry-per-topping lists. `slug` finds which topping an entry shows. */
	var ENTRY_LISTS = [
		{ root: '.cb-root,.np-root,.rp-root', item: '.cb-yp-bubble,.np-yp-bubble,.rp-yp-bubble', name: '.cb-yp-name,.np-yp-name,.rp-yp-name', remove: '.cb-yp-remove,.np-yp-remove,.rp-yp-remove', slug: slugFromOnclick },
		{ root: '.mt-root', item: '.mt-tray__chips .mt-chip[data-layer="toppings"]', name: ':scope > span', remove: '.mt-chip__remove', slug: function ( el ) { return el.getAttribute( 'data-slug' ); } },
		{ root: '.pl-root', item: '.pl-summary__list .pl-summary__item', name: '.pl-summary__item-title', remove: '', slug: slugFromTitle },
		{ root: '.sc-root', item: '.sc-summary__row--topping', name: '.sc-summary__layer-name', remove: '', slug: slugFromTitle }
	];
	/* Comma-joined text lists. */
	var TEXT_LISTS = [
		{ root: '.cc-root', el: '[id$="-sb-toppings-val"],[id$="-yp-toppings-val"]' },
		{ root: '.pp-root', el: '.pp-yp-set' }
	];

	function matches( el, sel ) {
		var fn = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
		return fn ? fn.call( el, sel ) : false;
	}

	function slugFromOnclick( el, sel ) {
		var btn = el.querySelector( '[onclick*="removeTopping"]' );
		var m   = btn ? String( btn.getAttribute( 'onclick' ) ).match( /removeTopping\(\s*'[^']*'\s*,\s*'([^']+)'/ ) : null;
		return m ? m[1] : '';
	}

	function slugFromTitle( el, sel, nameSel ) {
		var nm   = nameSel ? el.querySelector( nameSel ) : el;
		var text = nm ? String( nm.textContent || '' ).trim() : '';
		var best = '';
		sel.forEach( function ( t ) {
			if ( t.title && text.indexOf( t.title ) === 0 && t.title.length > best.length ) { best = t.slug; }
		} );
		return best;
	}

	function titleOf( sel, slug, c ) {
		var title = c ? c.title : slug;
		sel.forEach( function ( t ) { if ( t.slug === slug && t.title ) { title = t.title; } } );
		return title;
	}

	function syncEntries( root, id, sel ) {
		var lv = levels( id );
		ENTRY_LISTS.forEach( function ( a ) {
			if ( ! matches( root, a.root ) ) { return; }
			root.querySelectorAll( a.item ).forEach( function ( item ) {
				if ( item.hasAttribute( 'data-pztm-entry' ) ) { return; }
				var slug = a.slug( item, sel, a.name );
				var c    = slug ? conf( slug ) : null;
				if ( ! c ) { return; }
				var level = Math.min( lv[ slug ] || 1, c.max );
				var title = titleOf( sel, slug, c );

				// Thickness: show the level on the topping's own entry.
				var nameEl = item.querySelector( a.name );
				if ( nameEl ) {
					var tag  = nameEl.querySelector( '.pztm-entry-level' );
					var want = ( c.method !== 'layers' && level > 1 ) ? ' · ' + levelLabel( level ) : '';
					if ( want && ! tag ) {
						tag = document.createElement( 'span' );
						tag.className = 'pztm-entry-level';
						nameEl.appendChild( tag );
					}
					if ( tag ) {
						if ( ! want ) { tag.parentNode.removeChild( tag ); }
						else if ( tag.textContent !== want ) { tag.textContent = want; }
					}
				}

				// Separate layers: one entry per added layer, after the topping's own.
				var names = [];
				if ( c.method === 'layers' ) {
					for ( var n = 2; n <= level; n++ ) { names.push( extraName( c, title, n, level ) ); }
				}
				var existing = [], sib = item.nextElementSibling;
				while ( sib && sib.getAttribute( 'data-pztm-entry' ) === slug ) { existing.push( sib ); sib = sib.nextElementSibling; }
				var sig = names.join( '\n' );
				if ( item.getAttribute( 'data-pztm-sig' ) === sig && existing.length === names.length ) { return; }
				existing.forEach( function ( e ) { e.parentNode.removeChild( e ); } );
				item.setAttribute( 'data-pztm-sig', sig );

				var after = item;
				names.forEach( function ( name, i ) {
					var cl = item.cloneNode( true );
					cl.removeAttribute( 'data-pztm-sig' );
					cl.removeAttribute( 'id' );
					cl.setAttribute( 'data-pztm-entry', slug );
					cl.classList.add( 'pztm-entry' );
					Array.prototype.forEach.call( cl.querySelectorAll( '[id]' ), function ( x ) { x.removeAttribute( 'id' ); } );
					Array.prototype.forEach.call( cl.querySelectorAll( '[onclick]' ), function ( x ) { x.removeAttribute( 'onclick' ); } );
					Array.prototype.forEach.call( cl.querySelectorAll( '.pztm-entry-level' ), function ( x ) { x.parentNode.removeChild( x ); } );
					var nm = cl.querySelector( a.name );
					if ( nm ) {
						var cov = nm.querySelector( '[class*="coverage"], small' );
						nm.textContent = name + ( cov ? ' ' : '' );
						if ( cov ) { nm.appendChild( cov ); }
					}
					var rm = a.remove ? cl.querySelector( a.remove ) : null;
					if ( ! rm ) {
						rm = document.createElement( 'button' );
						rm.type = 'button';
						rm.className = 'pztm-entry-remove';
						rm.innerHTML = '<span aria-hidden="true">&times;</span>';
						cl.appendChild( rm );
					}
					rm.setAttribute( 'data-pztm-remove', slug );
					rm.setAttribute( 'data-n', String( i + 2 ) );
					rm.setAttribute( 'title', String( LBL.removeLayer || 'Remove %s' ).replace( '%s', name ) );
					rm.setAttribute( 'aria-label', String( LBL.removeLayer || 'Remove %s' ).replace( '%s', name ) );
					after.parentNode.insertBefore( cl, after.nextSibling );
					after = cl;
				} );
			} );
		} );

		// Entries whose topping entry has gone (template re-rendered or removed it).
		root.querySelectorAll( '[data-pztm-entry]' ).forEach( function ( cl ) {
			var slug = cl.getAttribute( 'data-pztm-entry' ), p = cl.previousElementSibling;
			while ( p && p.getAttribute( 'data-pztm-entry' ) === slug ) { p = p.previousElementSibling; }
			if ( ! p || ! p.hasAttribute( 'data-pztm-sig' ) ) { cl.parentNode.removeChild( cl ); }
		} );

		TEXT_LISTS.forEach( function ( a ) {
			if ( ! matches( root, a.root ) ) { return; }
			root.querySelectorAll( a.el ).forEach( function ( el ) {
				var cur  = el.textContent;
				var orig = el.getAttribute( 'data-pztm-out' ) === cur ? el.getAttribute( 'data-pztm-orig' ) : cur;
				var out  = rewriteText( orig, sel, lv );
				el.setAttribute( 'data-pztm-orig', orig );
				el.setAttribute( 'data-pztm-out', out );
				if ( out !== cur ) { el.textContent = out; }
			} );
		} );
	}

	function rewriteText( text, sel, lv ) {
		if ( ! text || text.indexOf( ',' ) === -1 && ! sel.some( function ( t ) { return t.title === text.trim(); } ) ) { return text; }
		return text.split( ', ' ).map( function ( token ) {
			var t = null;
			sel.forEach( function ( x ) { if ( x.title && x.title === token.trim() ) { t = x; } } );
			var c = t ? conf( t.slug ) : null;
			if ( ! c ) { return token; }
			var level = Math.min( lv[ t.slug ] || 1, c.max );
			if ( level < 2 ) { return token; }
			if ( c.method !== 'layers' ) { return token + ' (' + levelLabel( level ) + ')'; }
			var out = [ token ];
			for ( var n = 2; n <= level; n++ ) { out.push( extraName( c, t.title, n, level ) ); }
			return out.join( ', ' );
		} ).join( ', ' );
	}

	// Remove buttons on added-layer entries (capture phase, ahead of template handlers).
	document.addEventListener( 'click', function ( e ) {
		var rm = e.target && e.target.closest ? e.target.closest( '[data-pztm-remove]' ) : null;
		if ( ! rm ) { return; }
		var root = rm.closest( ROOT_SEL );
		if ( ! root ) { return; }
		e.preventDefault();
		e.stopPropagation();
		removeLayer( root, instanceIdOf( root ), rm.getAttribute( 'data-pztm-remove' ), parseInt( rm.getAttribute( 'data-n' ), 10 ) );
	}, true );

	/* Templates rebuild their lists from scratch; put the entries back at once. */
	var observed = [];
	function observe( root ) {
		if ( ! window.MutationObserver || observed.indexOf( root ) !== -1 ) { return; }
		observed.push( root );
		var queued = false;
		new window.MutationObserver( function () {
			if ( queued ) { return; }
			queued = true;
			( window.requestAnimationFrame || setTimeout )( function () {
				queued = false;
				try { syncRoot( root ); } catch ( err ) {}
			} );
		} ).observe( root, { childList: true, subtree: true, characterData: true } );
	}

	// =====================================================================
	// Sync loop
	// =====================================================================

	function syncRoot( root ) {
		var id = instanceIdOf( root );
		if ( ! id ) { return; }
		var s = rawState( id );
		if ( ! s ) { return; }
		var sel = selectedToppings( s );
		prune( id, sel );
		syncControls( root, id, sel );
		syncEntries( root, id, sel );
		syncPreview( root, id, sel );
	}

	function syncAll() {
		ensureWrapped();
		roots().forEach( function ( root ) {
			observe( root );
			try { syncRoot( root ); } catch ( e ) {
				if ( window.console && CFG.debug ) { window.console.warn( '[PizzaTier Multiplier]', e ); }
			}
		} );
	}

	// Remember which topping's coverage dialog is being opened.
	document.addEventListener( 'click', function ( e ) {
		var card = e.target && e.target.closest ? e.target.closest( '[data-slug]' ) : null;
		if ( card ) {
			var root = card.closest( ROOT_SEL );
			if ( root ) { lastCardSlug[ instanceIdOf( root ) ] = card.getAttribute( 'data-slug' ); }
		}
	}, true );

	document.addEventListener( 'click', function () {
		setTimeout( syncAll, 0 );
		setTimeout( syncAll, 150 );
	} );

	// Restore portions when PizzaTier re-opens a pizza from the cart / an old order.
	document.addEventListener( 'pizzatier:prefill', function ( e ) {
		var d = ( e && e.detail ) || {};
		var counts = {};
		( d.layers || [] ).forEach( function ( l ) {
			if ( l.layerType && ! isToppingType( l.layerType ) ) { return; }
			var lid = String( l.layerId || '' );
			var p   = parseExtra( lid );
			if ( p ) { lid = p[0]; }
			if ( lid ) { counts[ lid ] = ( counts[ lid ] || 0 ) + 1; }
		} );
		if ( ! Object.keys( counts ).some( function ( k ) { return counts[ k ] > 1; } ) ) { return; }
		var tries = 0;
		( function apply() {
			tries++;
			var done = false;
			roots().forEach( function ( root ) {
				var id = instanceIdOf( root );
				selectedToppings( rawState( id ) ).forEach( function ( t ) {
					var c = conf( t.slug );
					if ( c && counts[ t.slug ] > 1 ) {
						levels( id )[ t.slug ] = Math.min( c.max, counts[ t.slug ] );
						done = true;
					}
				} );
			} );
			if ( done ) { syncAll(); recalc(); }
			else if ( tries < 30 ) { setTimeout( apply, 200 ); }
		}() );
	} );

	ensureWrapped();
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', syncAll );
	} else {
		syncAll();
	}
	setInterval( syncAll, 300 );

	/* Small public surface for themes / custom templates. */
	window.PizzaTierMultiplier = {
		getLevel: function ( instanceId, slug ) { return levels( instanceId )[ slug ] || 1; },
		setLevel: function ( instanceId, slug, level ) {
			var c = conf( slug );
			if ( ! c ) { return false; }
			level = Math.max( 1, Math.min( c.max, parseInt( level, 10 ) || 1 ) );
			if ( level === 1 ) { delete levels( instanceId )[ slug ]; } else { levels( instanceId )[ slug ] = level; }
			syncAll();
			recalc();
			return true;
		},
		augment: augment,
		sync:    syncAll
	};
}() );
