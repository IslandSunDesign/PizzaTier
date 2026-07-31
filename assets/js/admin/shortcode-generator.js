( function () {
	'use strict';

	// Type tab switching
	var typeTabs = document.querySelectorAll( '.pscg-type-tab' );
	var forms    = document.querySelectorAll( '.pscg-form' );

	typeTabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			typeTabs.forEach( function ( t ) { t.classList.remove( 'pscg-type-tab--active' ); } );
			forms.forEach( function ( f ) { f.style.display = 'none'; } );
			tab.classList.add( 'pscg-type-tab--active' );
			var form = document.getElementById( 'pscg-form-' + tab.dataset.type );
			if ( form ) { form.style.display = ''; }
			buildShortcode();
		} );
	} );

	var output = document.getElementById( 'pscg-output' );

	function val( id ) {
		var el = document.getElementById( id );
		return el ? el.value.trim() : '';
	}

	function multiVal( id ) {
		var el = document.getElementById( id );
		if ( ! el ) { return []; }
		return Array.from( el.selectedOptions ).map( function ( o ) { return o.value; } );
	}

	function buildShortcode() {
		var active = document.querySelector( '.pscg-type-tab--active' );
		if ( ! active || ! output ) { return; }
		var type  = active.dataset.type;
		var attrs = [];
		var sc    = '';

		if ( type === 'builder' ) {
			var id  = val( 'b-id' );          if ( id )           { attrs.push( 'id="' + id + '"' ); }
			var tpl = val( 'b-template' );     if ( tpl )          { attrs.push( 'template="' + tpl + '"' ); }
			var max = val( 'b-max-toppings' ); if ( max && max !== '0' ) { attrs.push( 'max_toppings="' + max + '"' ); }
			var dc  = val( 'b-default-crust' ); if ( dc )          { attrs.push( 'default_crust="' + dc + '"' ); }
			var ds  = val( 'b-default-sauce' ); if ( ds )          { attrs.push( 'default_sauce="' + ds + '"' ); }
			var dch = val( 'b-default-cheese' ); if ( dch )        { attrs.push( 'default_cheese="' + dch + '"' ); }
			var shp = val( 'b-pizza-shape' );  if ( shp )          { attrs.push( 'pizza_shape="' + shp + '"' ); }
			var asp = val( 'b-pizza-aspect' ); if ( asp )          { attrs.push( 'pizza_aspect="' + asp + '"' ); }
			var rad = val( 'b-pizza-radius' ); if ( rad )          { attrs.push( 'pizza_radius="' + rad + '"' ); }
			var ani  = val( 'b-layer-anim' );   if ( ani )          { attrs.push( 'layer_anim="' + ani + '"' ); }
			var spd  = val( 'b-layer-anim-speed' ); if ( spd && spd !== '0' ) { attrs.push( 'layer_anim_speed="' + spd + '"' ); }
			var allCbs    = document.querySelectorAll( '.pscg-cb-tab' );
			var hiddenTabs = [];
			allCbs.forEach( function ( cb ) { if ( ! cb.checked ) { hiddenTabs.push( cb.value ); } } );
			if ( hiddenTabs.length && hiddenTabs.length < allCbs.length ) {
				attrs.push( 'hide_tabs="' + hiddenTabs.join( ',' ) + '"' );
			}
			sc = '[pizza_builder' + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';

		} else if ( type === 'static' ) {
			var preset = val( 's-preset' );
			var c  = val( 's-crust' );   if ( c )  { attrs.push( 'crust="' + c + '"' ); }
			var s  = val( 's-sauce' );   if ( s )  { attrs.push( 'sauce="' + s + '"' ); }
			var ch = val( 's-cheese' );  if ( ch ) { attrs.push( 'cheese="' + ch + '"' ); }
			var dr = val( 's-drizzle' ); if ( dr ) { attrs.push( 'drizzle="' + dr + '"' ); }
			var cu = val( 's-cut' );     if ( cu ) { attrs.push( 'cut="' + cu + '"' ); }
			var tops = multiVal( 's-toppings' );
			if ( tops.length ) { attrs.push( 'toppings="' + tops.join( ',' ) + '"' ); }
			if ( preset ) {
				// A preset defines the entire pizza (incl. toppings) and renders via the
				// [pizza_preset] shortcode (PizzaTier), keyed by the preset's post ID.
				sc = '[pizza_preset id="' + preset + '"]';
			} else {
				sc = '[pizza_static' + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';
			}

		} else if ( type === 'layer' ) {
			var lt  = val( 'l-type' );  if ( lt )                     { attrs.push( 'type="' + lt + '"' ); }
			var sl  = val( 'l-slug' );  if ( sl )                     { attrs.push( 'slug="' + sl + '"' ); }
			var img = val( 'l-image' ); if ( img && img !== 'layer' ) { attrs.push( 'image="' + img + '"' ); }
			var cls = val( 'l-class' ); if ( cls )                    { attrs.push( 'class="' + cls + '"' ); }
			sc = '[pizza_layer' + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';

		} else if ( type === 'layerinfo' ) {
			var lit = val( 'li-type' );  if ( lit ) { attrs.push( 'type="' + lit + '"' ); }
			var lis = val( 'li-slug' );  if ( lis ) { attrs.push( 'slug="' + lis + '"' ); }
			var lif = val( 'li-field' ); if ( lif ) { attrs.push( 'field="' + lif + '"' ); }
			sc = '[pizza_layer_info' + ( attrs.length ? ' ' + attrs.join( ' ' ) : '' ) + ']';
		}

		output.textContent = sc;
	}

	// ── Layer type → slug select population ───────────────────────────────
	var lTypeEl = document.getElementById( 'l-type' );
	var lSlugEl = document.getElementById( 'l-slug' );
	var cptItems = ( window.pizzatierSCG && window.pizzatierSCG.cptItems ) ? window.pizzatierSCG.cptItems : {};

	function populateLayerSlugSelect( type ) {
		if ( ! lSlugEl ) { return; }
		var items = cptItems[ type ] || [];
		lSlugEl.innerHTML = '';
		if ( items.length === 0 ) {
			var opt = document.createElement( 'option' );
			opt.value = '';
			opt.textContent = '— no items found —';
			lSlugEl.appendChild( opt );
		} else {
			var blank = document.createElement( 'option' );
			blank.value = '';
			blank.textContent = '— select item —';
			lSlugEl.appendChild( blank );
			items.forEach( function ( item ) {
				var o = document.createElement( 'option' );
				o.value = item.slug;
				o.textContent = item.title + ' (' + item.slug + ')';
				lSlugEl.appendChild( o );
			} );
		}
		buildShortcode();
	}

	if ( lTypeEl ) {
		lTypeEl.addEventListener( 'change', function () {
			populateLayerSlugSelect( lTypeEl.value );
		} );
		// Populate on load with initial type
		populateLayerSlugSelect( lTypeEl.value );
	}

	// When a preset is selected it defines the whole pizza, so the individual
	// static layer fields are superseded — disable and dim them for clarity.
	var presetEl       = document.getElementById( 's-preset' );
	var staticLayerIds = [ 's-crust', 's-sauce', 's-cheese', 's-drizzle', 's-cut', 's-toppings' ];
	function syncStaticFields() {
		if ( ! presetEl ) { return; }
		var locked = presetEl.value !== '';
		staticLayerIds.forEach( function ( fid ) {
			var f = document.getElementById( fid );
			if ( ! f ) { return; }
			f.disabled = locked;
			var wrap = f.closest( '.pscg-field' );
			if ( wrap ) { wrap.style.opacity = locked ? '0.45' : ''; }
		} );
	}
	if ( presetEl ) {
		presetEl.addEventListener( 'change', syncStaticFields );
		syncStaticFields();
	}

	document.querySelectorAll( '.pscg-input, .pscg-select, .pscg-cb-tab, #b-pizza-shape, #b-pizza-aspect, #b-pizza-radius, #b-layer-anim, #b-layer-anim-speed' ).forEach( function ( el ) {
		el.addEventListener( 'change', buildShortcode );
		el.addEventListener( 'input',  buildShortcode );
	} );
	buildShortcode();

	// Copy button
	var copyBtn = document.getElementById( 'pscg-copy-btn' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			var text = output ? output.textContent : '';
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text );
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
			}
			var notice = document.getElementById( 'pscg-copy-notice' );
			if ( notice ) {
				notice.style.display = 'block';
				setTimeout( function () { notice.style.display = 'none'; }, 2500 );
			}
		} );
	}

} )();
