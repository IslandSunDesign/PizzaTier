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

/** Coverage → CSS clip-path (same map as the Colorbox PizzaStack). */
var SC_COVERAGE_CLIPS = {
  'half-left':            'polygon(0 0, 50% 0, 50% 100%, 0 100%)',
  'half-right':           'polygon(50% 0, 100% 0, 100% 100%, 50% 100%)',
  'quarter-top-left':     'polygon(0 0, 50% 0, 50% 50%, 0 50%)',
  'quarter-top-right':    'polygon(50% 0, 100% 0, 100% 50%, 50% 50%)',
  'quarter-bottom-left':  'polygon(0 50%, 50% 50%, 50% 100%, 0 100%)',
  'quarter-bottom-right': 'polygon(50% 50%, 100% 50%, 100% 100%, 50% 100%)'
};
function scCoverageClip( coverage ) {
  return SC_COVERAGE_CLIPS[ coverage ] || 'none'; // 'whole' / unknown → no clip
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

  /** Card for a layer type + slug (when a call has no trigger element). */
  function findCard( layerType, slug ) {
    var found = null;
    ROOT.querySelectorAll( '.sc-card[data-layer="' + layerType + '"]' ).forEach( function( c ) {
      if ( ! found && c.getAttribute( 'data-slug' ) === String( slug ) ) { found = c; }
    } );
    return found;
  }

  function cardFor( layerType, slug, triggerEl ) {
    var card = ( triggerEl && triggerEl.closest ) ? triggerEl.closest( '.sc-card' ) : null;
    return card || findCard( layerType, slug );
  }

  /** Coverage UI + data-coverage back to a given fraction (default whole). */
  function setCoverageUI( card, fraction ) {
    if ( ! card ) { return; }
    fraction = fraction || 'whole';
    card.setAttribute( 'data-coverage', fraction );
    card.querySelectorAll( '.sc-cov-btn' ).forEach( function( b ) {
      b.classList.toggle( 'sc-cov-btn--active', b.getAttribute( 'data-fraction' ) === fraction );
    } );
  }

  /** Base layers a customer may deselect by clicking Select again. */
  function isOptionalBase( layerType ) { return layerType !== 'crust'; }

  /** Swap an exclusive base layer (crust/sauce/cheese/drizzle/slicing). */
  function swapBase( layerType, slug, title, layerImg, triggerEl ) {
    var card = cardFor( layerType, slug, triggerEl );

    // Clicking an already-selected optional layer deselects it.
    if ( card && card.classList.contains( 'sc-card--selected' ) && isOptionalBase( layerType ) ) {
      removeBase( layerType, slug, card );
      return;
    }

    // Deselect previous card
    ROOT.querySelectorAll( '.sc-card--exclusive[data-layer="' + layerType + '"]' ).forEach( function( c ) {
      c.classList.remove( 'sc-card--selected' );
      var b = c.querySelector( '.sc-card__btn--select' );
      if ( b ) { b.setAttribute( 'aria-pressed', 'false' ); }
    } );
    // Select this card
    if ( card ) {
      card.classList.add( 'sc-card--selected' );
      var selBtn = card.querySelector( '.sc-card__btn--select' );
      if ( selBtn ) { selBtn.setAttribute( 'aria-pressed', 'true' ); }
    }

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
    var card = cardFor( layerType, slug, triggerEl );
    if ( card ) {
      card.classList.remove( 'sc-card--selected' );
      var selBtn = card.querySelector( '.sc-card__btn--select' );
      if ( selBtn ) { selBtn.setAttribute( 'aria-pressed', 'false' ); }
    }
    var img = document.getElementById( ROOT.id + '-layer-' + layerType );
    if ( img ) { img.src = ''; img.style.display = 'none'; }
    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:layerChanged', { detail: { layerType: layerType, slug: '', title: '', layerImg: '' }, bubbles: true } ) );
  }

  /** Add a topping. */
  function addTopping( zindex, slug, layerImg, title, layerId, inputId, triggerEl ) {
    var selected = ROOT.querySelectorAll( '.sc-card--topping.sc-card--selected' ).length;
    if ( selected >= MAX_TOP ) {
      ROOT.dispatchEvent( new CustomEvent( 'pizzatier:maxToppings', { detail: { max: MAX_TOP }, bubbles: true } ) );
      /* Visible / announced feedback (nothing listened for the event). */
      if ( window.PizzaTierNotice ) { window.PizzaTierNotice.maxToppings( ROOT, MAX_TOP ); }
      return;
    }
    var card = cardFor( 'toppings', slug, triggerEl );
    if ( card ) {
      setCoverageUI( card, 'whole' );
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
        el.style.clipPath            = 'none';
        stage.appendChild( el );
      }
    }
    updateSummary();
    ROOT.dispatchEvent( new CustomEvent( 'pizzatier:toppingAdded', { detail: { slug: slug, title: title }, bubbles: true } ) );
  }

  /** Remove a topping. */
  function removeTopping( layerId, slug, triggerEl ) {
    var card = cardFor( 'toppings', slug, triggerEl );
    if ( card ) {
      // Back to whole so a re-added topping does not keep the old half.
      setCoverageUI( card, 'whole' );
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
    var card = cardFor( 'toppings', slug, triggerEl );
    setCoverageUI( card, fraction );
    // Clip the topping's layer image to the chosen portion.
    var layerEl = document.getElementById( ROOT.id + '-tslot-' + slug );
    if ( layerEl ) {
      var clip = scCoverageClip( fraction );
      layerEl.style.clipPath       = clip;
      layerEl.style.webkitClipPath = clip;
    }
    updateSummary();
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
    /* Top-level single-choice layers too: pizzatier-orders.js normaliseState()
       reads crust/sauce/cheese/drizzle/cut directly off the state object. */
    function top( key ) {
      var b = baseLayers[ key ];
      return b ? { slug: b.slug, title: b.title || b.slug } : null;
    }
    return {
      instanceId: ROOT.id,
      layers:     layers,
      toppings:   layers.filter( function( l ) { return l.layerType === 'topping'; } ),
      baseLayers: baseLayers,
      crust:      top( 'crust' ),
      sauce:      top( 'sauce' ),
      cheese:     top( 'cheese' ),
      drizzle:    top( 'drizzle' ),
      cut:        top( 'slicing' ) || top( 'cut' ),
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
    ROOT.querySelectorAll( '.sc-card--topping'     ).forEach( function( c ) { setCoverageUI( c, 'whole' ); } );
    ROOT.querySelectorAll( '.sc-card__btn--select' ).forEach( function( b ) { b.setAttribute( 'aria-pressed', 'false' ); } );
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
      Object.keys( newState.toppings ).forEach( function( key ) {
        var t    = newState.toppings[ key ];
        var slug = ( t && typeof t === 'object' && t.slug ) ? t.slug : key;
        var card = ROOT.querySelector( '.sc-card--topping[data-slug="' + slug + '"]' );
        if ( card && ! card.classList.contains( 'sc-card--selected' ) ) {
          var addBtn = card.querySelector( '.sc-card__btn--add' );
          if ( addBtn ) { addBtn.click(); }
        }
        /* Restore the saved coverage (cart edit / Pro defaults). */
        if ( card && card.classList.contains( 'sc-card--selected' ) && t && typeof t === 'object' && t.coverage ) {
          setCoverage( slug, t.coverage, card );
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
/* The shared facade in pizzatier-main.js (a script dependency of this file)
 * already provides registerInstance(), so normally nothing is defined here.
 * Fallback only when it is missing: build Scaffold's own registry, delegating
 * ids it does not own to any API that was already on the page instead of
 * replacing it (replacing it broke the other template's builder). */
if ( ! window.PizzaTierAPI || typeof window.PizzaTierAPI.registerInstance !== 'function' ) {
  ( function () {
    var _prev      = window.PizzaTierAPI || null;
    var _instances = ( _prev && _prev._instances ) || {};
    function prevOwns( id ) {
      try {
        return !! ( _prev && typeof _prev.getAllInstances === 'function' && _prev.getAllInstances().indexOf( id ) !== -1 );
      } catch ( e ) { return false; }
    }
    window.PizzaTierAPI = {
      _instances:       _instances,
      registerInstance: function ( id, inst ) { _instances[ id ] = inst; },
      getInstance:      function ( id ) { return _instances[ id ] || null; },
      getInstances:     function () { return _instances; },
      getAllInstances:  function () {
        var ids = Object.keys( _instances );
        if ( _prev && typeof _prev.getAllInstances === 'function' ) {
          try {
            _prev.getAllInstances().forEach( function ( k ) { if ( ids.indexOf( k ) === -1 ) { ids.push( k ); } } );
          } catch ( e ) {}
        }
        return ids;
      },
      getState: function ( id ) {
        if ( id && ! _instances[ id ] && prevOwns( id ) ) { return _prev.getState( id ); }
        var inst = ( id && _instances[ id ] ) || ( id && window[ id ] );
        if ( ! inst ) {
          /* bare getState(): return the only instance if there is exactly one */
          var keys = Object.keys( _instances );
          if ( keys.length === 1 ) { inst = _instances[ keys[0] ]; }
        }
        return ( inst && typeof inst.getState === 'function' ) ? inst.getState() : null;
      },
      setState: function ( id, newState ) {
        if ( id && ! _instances[ id ] && prevOwns( id ) && typeof _prev.setState === 'function' ) {
          _prev.setState( id, newState );
          return;
        }
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
