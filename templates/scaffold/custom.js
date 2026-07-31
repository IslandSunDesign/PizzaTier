/* Scaffold Template — Builder JS
 * Instance config is read from data-sc-cfg attribute on .sc-root.
 * This file is enqueued by AssetManager; no inline <script> blocks.
 */

/** HTML-escape a value before inserting into innerHTML. */
function scEscHtml( s ) {
  return String( s )
    .replace( /&/g, '&amp;' )
    .replace( /</g, '&lt;' )
    .replace( />/g, '&gt;' )
    .replace( /"/g, '&quot;' )
    .replace( /'/g, '&#39;' );
}

/* initScaffoldInstance — run once per .sc-root on this page. */
function initScaffoldInstance( ROOT, cfg ) {
  'use strict';

  var VAR      = cfg.varName;
  var DEFAULTS = cfg.defaults;
  var MAX_TOP  = cfg.maxToppings;
  var TOPPINGS = cfg.toppings;

  if ( ! ROOT ) { return; }

  /** Activate a tab and show its panel, hide all others. */
  function activateTab( slug ) {
    ROOT.querySelectorAll( '.sc-tab-btn' ).forEach( function( btn ) {
      var active = btn.getAttribute( 'data-tab' ) === slug;
      btn.classList.toggle( 'sc-tab-btn--active', active );
      btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
    } );
    ROOT.querySelectorAll( '.sc-panel' ).forEach( function( panel ) {
      var show = panel.getAttribute( 'data-panel' ) === slug;
      if ( show ) { panel.removeAttribute( 'hidden' ); }
      else        { panel.setAttribute( 'hidden', '' ); }
    } );
  }

  /** Swap an exclusive base layer (crust/sauce/cheese/drizzle/slicing). */
  function swapBase( layerType, slug, title, layerImg, triggerEl ) {
    // Deselect previous card
    ROOT.querySelectorAll( '.sc-card--exclusive[data-layer="' + layerType + '"]' ).forEach( function( c ) {
      c.classList.remove( 'sc-card--selected' );
    } );
    // Select this card
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    if ( card ) { card.classList.add( 'sc-card--selected' ); }

    // Update layer image in pizza stage
    var img = document.getElementById( ROOT.id + '-layer-' + layerType );
    if ( img ) {
      img.src = layerImg;
      img.style.display = layerImg ? 'block' : 'none';
    }

    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:layerChanged', { detail: { layerType: layerType, slug: slug, title: title, layerImg: layerImg }, bubbles: true } ) );
  }

  /** Remove an exclusive base layer. */
  function removeBase( layerType, slug, triggerEl ) {
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    if ( card ) { card.classList.remove( 'sc-card--selected' ); }
    var img = document.getElementById( ROOT.id + '-layer-' + layerType );
    if ( img ) { img.src = ''; img.style.display = 'none'; }
    updateSummary();
  }

  /** Add a topping. */
  function addTopping( zindex, slug, layerImg, title, layerId, inputId, triggerEl ) {
    var selected = ROOT.querySelectorAll( '.sc-card--topping.sc-card--selected' ).length;
    if ( selected >= MAX_TOP ) {
      ROOT.dispatchEvent( new CustomEvent( 'pizzatier:maxToppings', { detail: { max: MAX_TOP }, bubbles: true } ) );
      return;
    }
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    if ( card ) {
      card.classList.add( 'sc-card--selected' );
      var addBtn = card.querySelector( '.sc-card__btn--add' );
      var remBtn = card.querySelector( '.sc-card__btn--remove' );
      var covEl  = card.querySelector( '.sc-coverage' );
      if ( addBtn ) { addBtn.style.display = 'none'; }
      if ( remBtn ) { remBtn.style.display = ''; }
      if ( covEl )  { covEl.style.display = ''; }
    }
    // Inject layer image into stage
    var stage = document.getElementById( ROOT.id + '-stage' );
    if ( stage && layerImg ) {
      var existing = stage.querySelector( '[data-topping-slug="' + slug + '"]' );
      if ( ! existing ) {
        var el = document.createElement( 'img' );
        el.id                        = ROOT.id + '-tslot-' + slug;
        el.className                 = 'sc-layer sc-layer--topping';
        el.src                       = layerImg;
        el.alt                       = title;
        el.setAttribute( 'data-topping-slug', slug );
        el.style.zIndex              = String( zindex );
        stage.appendChild( el );
      }
    }
    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:toppingAdded', { detail: { slug: slug, title: title }, bubbles: true } ) );
  }

  /** Remove a topping. */
  function removeTopping( layerId, slug, triggerEl ) {
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    if ( card ) {
      card.classList.remove( 'sc-card--selected' );
      var addBtn = card.querySelector( '.sc-card__btn--add' );
      var remBtn = card.querySelector( '.sc-card__btn--remove' );
      var covEl  = card.querySelector( '.sc-coverage' );
      if ( addBtn ) { addBtn.style.display = ''; }
      if ( remBtn ) { remBtn.style.display = 'none'; }
      if ( covEl )  { covEl.style.display = 'none'; }
    }
    var layerEl = document.getElementById( ROOT.id + '-tslot-' + slug );
    if ( layerEl ) { layerEl.remove(); }
    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:toppingRemoved', { detail: { slug: slug }, bubbles: true } ) );
  }

  /** Set coverage fraction on a selected topping. */
  function setCoverage( slug, fraction, triggerEl ) {
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    if ( card ) {
      card.setAttribute( 'data-coverage', fraction );
      card.querySelectorAll( '.sc-cov-btn' ).forEach( function( b ) {
        b.classList.toggle( 'sc-cov-btn--active', b.getAttribute( 'data-fraction' ) === fraction );
      } );
    }
    // TODO: pass fraction through to layer clip-path for visual coverage
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:coverageSet', { detail: { slug: slug, fraction: fraction }, bubbles: true } ) );
  }

  /** Collect current state as a plain object.
   *
   * Returns the standard PizzaTier shape: `layers` is an ARRAY (so Pro's
   * frontend-builder getTemplateLayersNow() can read selections — this is what
   * the working templates such as Command Center return). The per-type map the
   * summary panel needs is exposed separately as `baseLayers`. Previously this
   * returned `layers` as an object, which Pro could not read, leaving the
   * checkout bar reporting "The pizza builder is not ready yet." */
  function getState() {
    var baseLayers = {};
    var layers     = [];
    ROOT.querySelectorAll( '.sc-card--exclusive.sc-card--selected' ).forEach( function( c ) {
      var layerType = c.getAttribute( 'data-layer' );
      var slug      = c.getAttribute( 'data-slug' );
      var title     = c.getAttribute( 'data-title' );
      var normType  = layerType === 'slicing' ? 'cut' : layerType;
      baseLayers[ layerType ] = { slug: slug, title: title, img: c.getAttribute( 'data-layer-img' ) };
      layers.push({
        id:        slug,
        layerId:   slug,
        title:     title || slug,
        layerName: title || slug,
        type:      normType,
        layerType: normType,
        fraction:  'whole',
        coverage:  'whole',
        portion:   '',
        coverageLabel: 'Whole'
      });
    } );
    ROOT.querySelectorAll( '.sc-card--topping.sc-card--selected' ).forEach( function( c ) {
      var slug     = c.getAttribute( 'data-slug' );
      var title    = c.getAttribute( 'data-title' );
      var coverage = c.getAttribute( 'data-coverage' ) || 'whole';
      var cov      = window.PizzaTierCoverage
        ? window.PizzaTierCoverage.normalize( coverage )
        : { portion: '', fraction: 'whole', label: 'Whole' };
      layers.push({
        id:        slug,
        layerId:   slug,
        title:     title || slug,
        layerName: title || slug,
        type:      'topping',
        layerType: 'topping',
        /* fraction = generic size (price-grid key); portion = the specific
           portion the topping sits on (kitchen ticket). */
        fraction:      cov.fraction,
        coverage:      coverage,
        portion:       cov.portion,
        coverageLabel: cov.label
      });
    } );
    var sizeEl = ROOT.querySelector( '.pztc-size-radio:checked' );
    return {
      instanceId: ROOT.id,
      layers:     layers,
      toppings:   layers.filter( function( l ) { return l.layerType === 'topping'; } ),
      baseLayers: baseLayers,
      size:       sizeEl ? sizeEl.value : null
    };
  }

  /** Update the summary panel list. */
  function updateSummary() {
    var list  = document.getElementById( ROOT.id + '-summary-rows' );
    var empty = ROOT.querySelector( '.sc-summary__empty' );
    if ( ! list ) { return; }

    var state = getState();
    var rows  = '';
    var layerLabels = { crust:'Crust', sauce:'Sauce', cheese:'Cheese', drizzle:'Drizzle', slicing:'Slicing' };

    Object.keys( state.baseLayers ).forEach( function( ltype ) {
      var l = state.baseLayers[ ltype ];
      var label = ( layerLabels[ ltype ] || ltype );
      rows += '<li class="sc-summary__row"><span class="sc-summary__layer-type">' + scEscHtml( label ) + '</span><span class="sc-summary__layer-name">' + scEscHtml( l.title ) + '</span></li>';
    } );
    state.toppings.forEach( function( t ) {
      rows += '<li class="sc-summary__row sc-summary__row--topping"><span class="sc-summary__layer-type">Topping</span><span class="sc-summary__layer-name">' + scEscHtml( t.title ) + '</span><span class="sc-summary__coverage">' + scEscHtml( t.coverageLabel || t.coverage ) + '</span></li>';
    } );

    list.innerHTML = rows;
    var hasContent = !! rows;
    if ( empty ) { empty.style.display = hasContent ? 'none' : ''; }

    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:stateChanged', { detail: getState(), bubbles: true } ) );
  }

  /** Reset all choices. */
  function resetAll() {
    ROOT.querySelectorAll( '.sc-card--selected' ).forEach( function( c ) { c.classList.remove( 'sc-card--selected' ); } );
    ROOT.querySelectorAll( '.sc-card__btn--add'    ).forEach( function( b ) { b.style.display = ''; } );
    ROOT.querySelectorAll( '.sc-card__btn--remove' ).forEach( function( b ) { b.style.display = 'none'; } );
    ROOT.querySelectorAll( '.sc-coverage'          ).forEach( function( c ) { c.style.display = 'none'; } );
    ROOT.querySelectorAll( '.sc-layer' ).forEach( function( img ) { img.src = ''; img.style.display = 'none'; } );
    // Remove injected topping layers
    ROOT.querySelectorAll( '.sc-layer--topping' ).forEach( function( el ) { el.remove(); } );
    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:reset', { bubbles: true } ) );
  }

  /**
   * Programmatically set selection state (PizzaTier JS API).
   * Consumed by PizzaTier to apply "Default Layers".
   *
   * @param {Object} newState { crust|sauce|cheese|drizzle|cut: slug|{slug},
   *                            toppings: { slug: {…} } }
   */
  function setState( newState ) {
    resetAll();
    if ( ! newState || typeof newState !== 'object' ) { return; }

    var baseTypes = [ 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ];
    baseTypes.forEach( function( type ) {
      var sel = newState[ type ];
      if ( ! sel ) { return; }
      var slug = ( typeof sel === 'object' ) ? sel.slug : sel;
      if ( ! slug ) { return; }
      // Cuts render under data-layer="slicing" in this template.
      var card = ROOT.querySelector( '.sc-card--exclusive[data-layer="' + type + '"][data-slug="' + slug + '"]' );
      if ( ! card && type === 'cut' ) {
        card = ROOT.querySelector( '.sc-card--exclusive[data-layer="slicing"][data-slug="' + slug + '"]' );
      }
      if ( card ) {
        var selectBtn = card.querySelector( '.sc-card__btn--select' );
        if ( selectBtn ) { selectBtn.click(); }
      }
    } );

    if ( newState.toppings && typeof newState.toppings === 'object' ) {
      Object.keys( newState.toppings ).forEach( function( slug ) {
        var card = ROOT.querySelector( '.sc-card--topping[data-slug="' + slug + '"]' );
        if ( card && ! card.classList.contains( 'sc-card--selected' ) ) {
          var addBtn = card.querySelector( '.sc-card__btn--add' );
          if ( addBtn ) { addBtn.click(); }
        }
      } );
    }
  }

  // ── Public API ──────────────────────────────────────────────────────────────
  window[ VAR ] = {
    instanceId:    ROOT.id,
    activateTab:   activateTab,
    swapBase:      swapBase,
    removeBase:    removeBase,
    addTopping:    addTopping,
    removeTopping: removeTopping,
    setCoverage:   setCoverage,
    getState:      getState,
    setState:      setState,
    resetAll:      resetAll,
  };

  // ── Wire tab clicks ─────────────────────────────────────────────────────────
  ROOT.querySelectorAll( '.sc-tab-btn' ).forEach( function( btn ) {
    btn.addEventListener( 'click', function() {
      activateTab( btn.getAttribute( 'data-tab' ) );
    } );
  } );

  // ── Activate first tab ──────────────────────────────────────────────────────
  var firstTab = ROOT.querySelector( '.sc-tab-btn' );
  if ( firstTab ) { activateTab( firstTab.getAttribute( 'data-tab' ) ); }

  // ── Apply defaults ──────────────────────────────────────────────────────────
  (function applyDefaults() {
    Object.keys( DEFAULTS ).forEach( function( layer ) {
      var defaultSlug = DEFAULTS[ layer ];
      if ( ! defaultSlug ) { return; }
      var card = ROOT.querySelector( '.sc-card--exclusive[data-layer="' + layer + '"][data-slug="' + defaultSlug + '"]' );
      if ( ! card ) { return; }
      var btn = card.querySelector( '.sc-card__btn--select' );
      if ( btn ) { btn.click(); }
    } );
  })();

}

/* PizzaTierAPI — full surface consumed by PizzaTier's frontend-builder.
 * Pro discovers state via getState('pztc-{idx}') (Strategy 1), getInstances()
 * (Strategy 2), or a bare getState() (Strategy 3). Scaffold's .sc-root id IS
 * "pztc-{idx}", so registering each instance under its root id satisfies all
 * three strategies. Previously scaffold exposed only a thin getState/setState
 * surface and returned layers as an object, which Pro could not read — leaving
 * the checkout bar stuck on "The pizza builder is not ready yet." */
if ( ! window.PizzaTierAPI || typeof window.PizzaTierAPI.registerInstance !== 'function' ) {
  ( function () {
    var _instances = ( window.PizzaTierAPI && window.PizzaTierAPI._instances ) || {};
    window.PizzaTierAPI = {
      _instances:       _instances,
      registerInstance: function ( id, inst ) { _instances[ id ] = inst; },
      getInstance:      function ( id ) { return _instances[ id ] || null; },
      getInstances:     function () { return _instances; },
      getAllInstances:  function () { return Object.keys( _instances ); },
      getState: function ( id ) {
        var inst = ( id && _instances[ id ] ) || ( id && window[ id ] );
        if ( ! inst ) {
          /* bare getState(): return the only instance if there is exactly one */
          var keys = Object.keys( _instances );
          if ( keys.length === 1 ) { inst = _instances[ keys[0] ]; }
        }
        return ( inst && typeof inst.getState === 'function' ) ? inst.getState() : null;
      },
      setState: function ( id, newState ) {
        var inst = _instances[ id ] || window[ id ];
        if ( inst && typeof inst.setState === 'function' ) { inst.setState( newState ); }
      }
    };
  }() );
}

/* Boot — initialise every .sc-root[data-sc-cfg] on the page. */
document.querySelectorAll( '.sc-root[data-sc-cfg]' ).forEach( function ( rootEl ) {
  try {
    var cfg = JSON.parse( rootEl.getAttribute( 'data-sc-cfg' ) );
    initScaffoldInstance( rootEl, cfg );

    var instanceId = rootEl.getAttribute( 'id' ) || ( cfg.varName ? cfg.varName : '' );
    var inst       = cfg.varName ? window[ cfg.varName ] : null;
    if ( instanceId && inst ) {
      window.PizzaTierAPI.registerInstance( instanceId, inst );

      /* Signal readiness so PizzaTier's checkout bar can bind. Fire via
         jQuery (the event Pro binds) and a DOM CustomEvent for good measure. */
      if ( window.jQuery ) {
        window.jQuery( document ).trigger( 'pizzatier_instance_ready', [ instanceId, inst ] );
      }
      try {
        document.dispatchEvent( new CustomEvent( 'pizzatier_instance_ready', {
          detail: { instanceId: instanceId, instance: inst }, bubbles: true
        } ) );
      } catch ( _e ) {}
    }
  } catch ( e ) {
    // eslint-disable-next-line no-console
    if ( window.console ) { console.warn( 'PizzaTier Scaffold: config parse error', e ); }
  }
} );
