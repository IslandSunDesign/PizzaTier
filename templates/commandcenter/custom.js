/* ══════════════════════════════════════════════════════════════════════
   PIZZATIER — Command Center Template — custom.js

   Architecture mirrors Colorbox:
   - CC.createInstance(instanceId) → scoped builder instance
   - Each instance maintains its own state and pizza layer stack
   - window.CC_{instanceId} exposed for global access
   - window.PizzaTierAPI public API for external plugins / Pro hooks
   ══════════════════════════════════════════════════════════════════════ */

(function ($) {
    'use strict';

    /* ════════════════════════════════════════════════════════════
       PIZZA STACK RENDERER
       Identical interface to Colorbox so PizzaTierPro can hook in
       ════════════════════════════════════════════════════════════ */
    var PizzaStack = {

        /* $root here is the .cc-canvas element itself (see $stage() below).
           We try to reuse the stage node PizzaBuilder::build_dynamic() already
           rendered (.np-pizza-stage) — adding the cc-/cb- classes so the CSS
           layer-positioning rules apply — rather than creating a second empty
           stage that competes with it. */
        getStage: function ($canvas) {
            if (!$canvas || !$canvas.length) { return $(); }

            /* 1) A stage we (or a prior call) already tagged. */
            var $stage = $canvas.find('.cc-pizza-stage').first();
            if ($stage.length) { return $stage; }

            /* 2) The PHP-rendered .np-pizza-stage — adopt it. */
            $stage = $canvas.find('.np-pizza-stage').first();
            if ($stage.length) {
                $stage.addClass('cc-pizza-stage cb-pizza-stage');
                return $stage;
            }

            /* 3) Any other known stage class rendered by a different path. */
            $stage = $canvas.find('.cb-pizza-stage, .pzl-pizza-stage').first();
            if ($stage.length) {
                $stage.addClass('cc-pizza-stage');
                return $stage;
            }

            /* 4) Nothing there — create one. */
            $stage = $('<div class="cc-pizza-stage cb-pizza-stage"></div>');
            $canvas.append($stage);
            return $stage;
        },

        _animateLayerIn: function ($img, $stage, mode) {
            var $root = $stage.closest('[data-layer-anim-speed]');
            var dur = $root.length ? (parseInt($root.data('layer-anim-speed'), 10) || 320) : 320;

            $img.css({ transition: 'none', opacity: 0, transform: '', filter: '' });

            var applyAnim = function () {
                switch (mode) {
                    case 'instant':
                        $img.css({ opacity: 1 });
                        break;
                    case 'scale-in':
                        $img.css({ transform: 'scale(0.55)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.34,1.56,0.64,1)',
                                opacity: 1, transform: 'scale(1)'
                            });
                        });
                        break;
                    case 'slide-up':
                        $img.css({ transform: 'translateY(22%)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)',
                                opacity: 1, transform: 'translateY(0)'
                            });
                        });
                        break;
                    case 'flip-in':
                        $img.css({ transform: 'rotateY(90deg) scale(0.8)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + (dur + 80) + 'ms ease, transform ' + (dur + 80) + 'ms cubic-bezier(0.34,1.2,0.64,1)',
                                opacity: 1, transform: 'rotateY(0deg) scale(1)'
                            });
                        });
                        break;
                    case 'drop-in':
                        $img.css({ transform: 'translateY(-18%) scale(1.06)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)',
                                opacity: 1, transform: 'translateY(0) scale(1)'
                            });
                        });
                        break;
                    default: /* fade */
                        requestAnimationFrame(function () {
                            $img.css({ transition: 'opacity ' + dur + 'ms ease', opacity: 1 });
                        });
                }
            };
            requestAnimationFrame(applyAnim);
        },

        set: function ($stage, layerId, src, zIndex, cls, coverage) {
            if (!$stage || !$stage.length) { return; }
            var animMode = $stage.closest('[data-layer-anim]').data('layer-anim') || 'fade';

            var $existing = $stage.find('[data-layer-id="' + layerId + '"]');

            if (!src) {
                $existing.remove();
                return;
            }

            var $div;
            if ($existing.length) {
                $div = $existing;
            } else {
                $div = $('<div class="cb-layer-div cc-layer-div"></div>');
                $div.attr('data-layer-id', layerId).css('z-index', zIndex || 1);
                if (cls) { $div.addClass(cls); }
                $stage.append($div);
            }

            $div.css('z-index', zIndex || 1);

            var $img = $div.find('img');
            if (!$img.length) {
                $img = $('<img alt="" />');
                $div.empty().append($img);
            }

            if ($img.attr('src') !== src) {
                $img.css({ opacity: 0 });
                $img.attr('src', src);
                $img.off('load.cc error.cc').on('load.cc', function () {
                    PizzaStack._animateLayerIn($img, $stage, animMode);
                }).on('error.cc', function () {
                    $img.css({ opacity: 0 });
                });
                if ($img[0].complete && $img[0].naturalWidth > 0) {
                    PizzaStack._animateLayerIn($img, $stage, animMode);
                }
            }

            if (coverage && coverage !== 'whole') {
                PizzaStack.setCoverageClip($img, coverage);
            } else {
                $img.css('clip-path', '');
            }
        },

        setCoverageClip: function ($img, coverage) {
            var clips = {
                'half-left':            'polygon(0 0, 50% 0, 50% 100%, 0 100%)',
                'half-right':           'polygon(50% 0, 100% 0, 100% 100%, 50% 100%)',
                'quarter-top-left':     'polygon(0 0, 50% 0, 50% 50%, 0 50%)',
                'quarter-top-right':    'polygon(50% 0, 100% 0, 100% 50%, 50% 50%)',
                'quarter-bottom-left':  'polygon(0 50%, 50% 50%, 50% 100%, 0 100%)',
                'quarter-bottom-right': 'polygon(50% 50%, 100% 50%, 100% 100%, 50% 100%)',
            };
            $img.css('clip-path', clips[coverage] || '');
        },

        remove: function ($stage, layerId) {
            if (!$stage || !$stage.length) { return; }
            $stage.find('[data-layer-id="' + layerId + '"]').remove();
        },

        clear: function ($stage) {
            if ($stage && $stage.length) { $stage.empty(); }
        },
    };

    /* ════════════════════════════════════════════════════════════
       INSTANCE FACTORY
       ════════════════════════════════════════════════════════════ */
    var CC = {

        createInstance: function (instanceId) {

            var $root       = $('#' + instanceId);
            if (!$root.length) { return null; }

            var ccVar       = $root.data('cc-var') || ('CC_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));
            var maxToppings = parseInt($root.data('max-toppings'), 10) || 99;
            var totalSteps  = parseInt($root.data('total-steps'), 10) || 7;

            /* Internal state */
            var state = {
                activeTab:   '',
                doneSet:     {},  /* tab slug → true when user has visited/selected */
                selections:  {
                    crust: null, sauce: null, cheese: null,
                    toppings: [], drizzle: null, slicing: null, size: null,
                },
                toppingCount: 0,
            };

            /* ── DOM helpers ─────────────────────────────────────────── */
            function $panel(tab)  { return $root.find('#' + instanceId + '-panel-' + tab); }
            function $stage()     {
                var $canvas = $root.find('#' + instanceId + '-canvas');
                return PizzaStack.getStage($canvas);
            }

            /* ── Pizza shape / clip ──────────────────────────────────── */
            function applyPizzaShape() {
                var shape  = $root.data('pizza-shape') || 'round';
                var radius = 'round' === shape ? '50%'
                           : 'square' === shape ? '8px'
                           : 'rectangle' === shape ? '8px'
                           : ($root.data('pizza-radius') || '8px');
                $root.find('#' + instanceId + '-canvas').css('border-radius', radius);
            }

            /* ── Step wizard ─────────────────────────────────────────── */
            function setActiveStep(tab) {
                $root.find('.cc-step').each(function () {
                    var $s = $(this);
                    var stab = $s.data('tab');
                    $s.removeClass('cc-step--active cc-step--done');
                    if (stab === tab) {
                        $s.addClass('cc-step--active');
                    } else if (state.doneSet[stab]) {
                        $s.addClass('cc-step--done');
                    }
                });
                updateProgressBar(tab);
            }

            function updateProgressBar(activeTab) {
                var $allSteps = $root.find('.cc-step:not(.cc-step--review)');
                var total = $allSteps.length;
                var activeIndex = -1;
                $allSteps.each(function (i) {
                    if ($(this).data('tab') === activeTab) { activeIndex = i; }
                });
                var pct = total > 0 ? Math.round(((activeIndex + 1) / total) * 100) : 0;
                $root.find('#' + instanceId + '-progress-fill').css('width', pct + '%');
            }

            /* ── Tab navigation ──────────────────────────────────────── */
            function goTab(tab) {
                var $target = $panel(tab);
                if (!$target.length) { return; }

                /* Mark previous as done */
                if (state.activeTab && state.activeTab !== tab) {
                    state.doneSet[state.activeTab] = true;
                }

                $root.find('.cc-panel').removeClass('cc-panel--active');
                $target.addClass('cc-panel--active');
                state.activeTab = tab;

                setActiveStep(tab);
                updateSidebar();

                /* Scroll builder into view on mobile */
                if (window.innerWidth < 860) {
                    $root[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                $(document).trigger('pizzatier_tab_changed', [instanceId, tab]);
            }

            /* ── Sidebar summary sync ────────────────────────────────── */
            function updateSidebar() {
                var layers   = ['crust','sauce','cheese','toppings','drizzle','slicing'];
                layers.forEach(function (key) {
                    var $sb  = $root.find('#' + instanceId + '-sb-' + key + '-val');
                    var $yp  = $root.find('#' + instanceId + '-yp-' + key + '-val');
                    var val;

                    if (key === 'toppings') {
                        val = state.selections.toppings.length
                            ? state.selections.toppings.map(function (t) { return t.title; }).join(', ')
                            : '';
                    } else {
                        val = state.selections[key] ? state.selections[key].title : '';
                    }

                    if ($sb.length) {
                        if (val) {
                            $sb.text(val).removeClass('cc-sidebar__row-value--empty');
                        } else {
                            $sb.text('—').addClass('cc-sidebar__row-value--empty');
                        }
                    }
                    if ($yp.length) {
                        if (val) {
                            $yp.text(val).removeClass('cc-review-row__value--empty');
                        } else {
                            $yp.html('<em>' + (window.pizzatierL10n ? window.pizzatierL10n.none_selected : 'None selected') + '</em>').addClass('cc-review-row__value--empty');
                        }
                    }
                });
            }

            /* ── Topping counter ─────────────────────────────────────── */
            function updateCounter() {
                $root.find('#' + instanceId + '-count').text(state.toppingCount);
                $(document).trigger('pizzatier_topping_count_changed', [instanceId, state.toppingCount, maxToppings]);
            }

            /* ── Card selected state ─────────────────────────────────── */
            function clearExclusiveSelected(layer) {
                $root.find('.cc-card[data-layer="' + layer + '"]').each(function () {
                    var $card = $(this);
                    $card.removeClass('cc-card--selected');
                    $card.find('.cc-btn--add').show();
                    $card.find('.cc-btn--remove').hide();
                    $card.find('.cc-coverage').hide();
                });
            }

            function setCardSelected($card, selected) {
                if (selected) {
                    $card.addClass('cc-card--selected');
                    $card.find('.cc-btn--add').hide();
                    $card.find('.cc-btn--remove').show();
                    if ($card.hasClass('cc-card--topping')) {
                        $card.find('.cc-coverage').show();
                    }
                } else {
                    $card.removeClass('cc-card--selected');
                    $card.find('.cc-btn--add').show();
                    $card.find('.cc-btn--remove').hide();
                    $card.find('.cc-coverage').hide();
                }
            }

            /* ── Public API ──────────────────────────────────────────── */

            /**
             * Swap a base layer (crust / sauce / cheese / drizzle / cut).
             * @param {string} layerType  - 'crust', 'sauce', 'cheese', 'drizzle', 'cut'
             * @param {string} slug
             * @param {string} title
             * @param {string} layerUrl
             * @param {Element} cardEl
             */
            function swapBase(layerType, slug, title, layerUrl, cardEl) {
                var stateKey = layerType === 'cut' ? 'slicing' : layerType;
                var zMap = { crust: 100, sauce: 150, cheese: 200, drizzle: 900, cut: 950, slicing: 950 };
                var z = zMap[layerType] || 200;

                /* Deselect previously selected card for this layer */
                clearExclusiveSelected(layerType);

                /* Toggle: clicking the selected item removes it */
                if (state.selections[stateKey] && state.selections[stateKey].slug === slug) {
                    state.selections[stateKey] = null;
                    PizzaStack.remove($stage(), 'layer-' + layerType);
                    updateSidebar();
                    $(document).trigger('pizzatier_layer_removed', [instanceId, layerType, slug]);
                    return;
                }

                state.selections[stateKey] = { slug: slug, title: title };

                var $card = cardEl ? $(cardEl).closest('.cc-card') : $root.find('.cc-card[data-slug="' + slug + '"][data-layer="' + layerType + '"]');
                setCardSelected($card, true);

                PizzaStack.set($stage(), 'layer-' + layerType, layerUrl, z, 'cc-layer--' + layerType);
                updateSidebar();
                $(document).trigger('pizzatier_layer_added', [instanceId, layerType, slug, title]);
            }

            /**
             * Remove a base layer.
             */
            function removeBase(layerType, slug, cardEl) {
                var stateKey = layerType === 'cut' ? 'slicing' : layerType;
                state.selections[stateKey] = null;
                var $card = cardEl ? $(cardEl).closest('.cc-card') : $root.find('.cc-card[data-slug="' + slug + '"][data-layer="' + layerType + '"]');
                setCardSelected($card, false);
                PizzaStack.remove($stage(), 'layer-' + layerType);
                updateSidebar();
                $(document).trigger('pizzatier_layer_removed', [instanceId, layerType, slug]);
            }

            /**
             * Add a topping.
             */
            function addTopping(zIndex, slug, layerUrl, title, layerId, domId, cardEl) {
                if (state.toppingCount >= maxToppings) {
                    $(document).trigger('pizzatier_max_toppings_reached', [instanceId, maxToppings]);
                    return;
                }

                /* Already added? */
                var existing = state.selections.toppings.filter(function (t) { return t.slug === slug; });
                if (existing.length) { return; }

                state.selections.toppings.push({ slug: slug, title: title, layerId: layerId, zIndex: zIndex, coverage: 'whole' });
                state.toppingCount++;

                var $card = cardEl ? $(cardEl).closest('.cc-card') : $root.find('.cc-card[data-slug="' + slug + '"][data-layer="toppings"]');
                setCardSelected($card, true);

                PizzaStack.set($stage(), layerId, layerUrl, zIndex, 'cc-layer--topping', 'whole');
                updateCounter();
                updateSidebar();
                $(document).trigger('pizzatier_topping_added', [instanceId, slug, title]);
            }

            /**
             * Remove a topping.
             */
            function removeTopping(layerId, slug, cardEl) {
                state.selections.toppings = state.selections.toppings.filter(function (t) { return t.slug !== slug; });
                state.toppingCount = state.selections.toppings.length;

                var $card = cardEl ? $(cardEl).closest('.cc-card') : $root.find('.cc-card[data-slug="' + slug + '"][data-layer="toppings"]');
                setCardSelected($card, false);

                PizzaStack.remove($stage(), layerId);
                updateCounter();
                updateSidebar();
                $(document).trigger('pizzatier_topping_removed', [instanceId, slug]);
            }

            /**
             * Set topping coverage fraction.
             */
            function setCoverage(slug, fraction, btnEl) {
                var topping = null;
                state.selections.toppings.forEach(function (t) { if (t.slug === slug) { topping = t; } });
                if (!topping) { return; }

                topping.coverage = fraction;

                /* Update clip on existing layer image */
                var $layerDiv = $stage().find('[data-layer-id="' + topping.layerId + '"]');
                PizzaStack.setCoverageClip($layerDiv.find('img'), fraction);

                /* Highlight active coverage button */
                if (btnEl) {
                    $(btnEl).closest('.cc-coverage__btns').find('.cc-cov-btn').removeClass('cc-cov-btn--active');
                    $(btnEl).addClass('cc-cov-btn--active');
                }
                $(document).trigger('pizzatier_coverage_changed', [instanceId, slug, fraction]);
            }

            /**
             * Reset all selections.
             */
            function resetAll() {
                state.selections = { crust: null, sauce: null, cheese: null, toppings: [], drizzle: null, slicing: null, size: null };
                state.toppingCount = 0;
                state.doneSet = {};

                $root.find('.cc-card').each(function () {
                    setCardSelected($(this), false);
                    $(this).find('.cc-coverage').hide();
                });

                PizzaStack.clear($stage());
                updateCounter();
                updateSidebar();

                /* Reset steps */
                $root.find('.cc-step').removeClass('cc-step--active cc-step--done');
                $root.find('#' + instanceId + '-progress-fill').css('width', '0%');

                /* Go back to first tab */
                var $firstPanel = $root.find('.cc-panel').first();
                if ($firstPanel.length) {
                    var firstTab = $firstPanel.attr('id').replace(instanceId + '-panel-', '');
                    goTab(firstTab);
                }

                $(document).trigger('pizzatier_reset', [instanceId]);
            }

            /**
             * Programmatically set selection state (PizzaTier JS API).
             * Consumed by PizzaTierPro to apply "Default Layers".
             *
             * @param {Object} newState { crust|sauce|cheese|drizzle|cut: slug|{slug},
             *                            toppings: { slug: {…} } }
             */
            function setState(newState) {
                resetAll();
                if (!newState || typeof newState !== 'object') { return; }

                var baseTypes = ['crust', 'sauce', 'cheese', 'drizzle', 'cut'];
                baseTypes.forEach(function (type) {
                    var sel = newState[type];
                    if (!sel) { return; }
                    var slug = (typeof sel === 'object') ? sel.slug : sel;
                    if (!slug) { return; }
                    var $card = $root.find('.cc-card--exclusive[data-layer="' + type + '"][data-slug="' + slug + '"]');
                    if (!$card.length && type === 'cut') {
                        $card = $root.find('.cc-card--exclusive[data-layer="slicing"][data-slug="' + slug + '"]');
                    }
                    if ($card.length && !$card.hasClass('cc-card--selected')) {
                        $card.find('.cc-btn--add').first().trigger('click');
                    }
                });

                if (newState.toppings && typeof newState.toppings === 'object') {
                    Object.keys(newState.toppings).forEach(function (slug) {
                        var $card = $root.find('.cc-card--topping[data-slug="' + slug + '"]');
                        if ($card.length && !$card.hasClass('cc-card--selected')) {
                            $card.find('.cc-btn--add').first().trigger('click');
                        }
                    });
                }
            }

            /**
             * Get current state (for Pro / external use).
             */
            function getState() {
                /* Build a layers array in the standard PizzaTierPro format so
                   frontend-builder.js getTemplateLayersNow() can read selections.
                   Non-topping types use coverage 'Whole'; toppings carry their
                   per-item coverage value. */
                var layers = [];
                var baseTypes = ['crust', 'sauce', 'cheese', 'drizzle', 'slicing'];
                baseTypes.forEach(function (type) {
                    var sel = state.selections[type];
                    if (sel && sel.slug) {
                        layers.push({
                            id:        sel.slug,
                            layerId:   sel.slug,
                            title:     sel.title || sel.slug,
                            layerName: sel.title || sel.slug,
                            type:      type === 'slicing' ? 'cut' : type,
                            layerType: type === 'slicing' ? 'cut' : type,
                            fraction:  'whole',
                            coverage:  'whole',
                            portion:   '',
                            coverageLabel: 'Whole'
                        });
                    }
                });
                state.selections.toppings.forEach(function (t) {
                    var cov = window.PizzaTierCoverage
                        ? window.PizzaTierCoverage.normalize(t.coverage)
                        : { portion: '', fraction: 'whole', label: 'Whole' };
                    layers.push({
                        id:        t.slug,
                        layerId:   t.slug,
                        title:     t.title || t.slug,
                        layerName: t.title || t.slug,
                        type:      'topping',
                        layerType: 'topping',
                        /* fraction = generic size (price-grid key); portion = the
                           specific portion the topping sits on (kitchen ticket). */
                        fraction:      cov.fraction,
                        coverage:      t.coverage || 'whole',
                        portion:       cov.portion,
                        coverageLabel: cov.label
                    });
                });
                return {
                    instanceId:   instanceId,
                    selections:   state.selections,
                    toppingCount: state.toppingCount,
                    activeTab:    state.activeTab,
                    layers:       layers,
                    size:         state.selections.size || null,
                };
            }

            /* ── Init ─────────────────────────────────────────────────── */
            function init() {
                applyPizzaShape();

                /* Activate first panel */
                var $first = $root.find('.cc-panel--active').first();
                if ($first.length) {
                    var firstTab = $first.attr('id').replace(instanceId + '-panel-', '');
                    state.activeTab = firstTab;
                    setActiveStep(firstTab);
                } else {
                    /* Fall back: activate first panel */
                    var $fp = $root.find('.cc-panel').first();
                    if ($fp.length) {
                        var ftab = $fp.attr('id').replace(instanceId + '-panel-', '');
                        goTab(ftab);
                    }
                }

                updateSidebar();
                updateCounter();

                /* Expose on window for PHP-generated onclick handlers */
                window[ccVar] = instance;

                /* Register with PizzaTierAPI */
                if (window.PizzaTierAPI && typeof window.PizzaTierAPI.registerInstance === 'function') {
                    window.PizzaTierAPI.registerInstance(instanceId, instance);
                }

                $(document).trigger('pizzatier_instance_ready', [instanceId, instance]);
            }

            /* ── Public instance object ──────────────────────────────── */
            var instance = {
                goTab:        goTab,
                swapBase:     swapBase,
                removeBase:   removeBase,
                addTopping:   addTopping,
                removeTopping: removeTopping,
                setCoverage:  setCoverage,
                resetAll:     resetAll,
                getState:     getState,
                setState:     setState,
                /* Legacy compat aliases */
                navNext: function () {
                    /* Find next tab after active */
                    var tabs = [];
                    $root.find('.cc-step').each(function () { tabs.push($(this).data('tab')); });
                    var idx = tabs.indexOf(state.activeTab);
                    if (idx >= 0 && idx < tabs.length - 1) { goTab(tabs[idx + 1]); }
                },
                navPrev: function () {
                    var tabs = [];
                    $root.find('.cc-step').each(function () { tabs.push($(this).data('tab')); });
                    var idx = tabs.indexOf(state.activeTab);
                    if (idx > 0) { goTab(tabs[idx - 1]); }
                },
                instanceId: instanceId,
                _state:     state,  /* internal — for Pro hooks */
            };

            init();
            return instance;
        },
    };

    /* ════════════════════════════════════════════════════════════
       PUBLIC PizzaTierAPI (mirrors Colorbox exposure)
       ════════════════════════════════════════════════════════════ */
    if (!window.PizzaTierAPI) {
        window.PizzaTierAPI = (function () {
            var _instances = {};
            return {
                registerInstance: function (id, inst) { _instances[id] = inst; },
                getInstance:      function (id) { return _instances[id] || null; },
                getInstances:     function () { return _instances; },
                getAllInstances:  function () { return Object.keys(_instances); },
                getState:         function (id) {
                    var inst = _instances[id];
                    return (inst && typeof inst.getState === 'function') ? inst.getState() : null;
                },
                setState:         function (id, newState) {
                    var inst = _instances[id];
                    if (inst && typeof inst.setState === 'function') { inst.setState(newState); }
                },
            };
        }());
    }

    /* Expose CC namespace globally */
    window.CC = CC;

    /* ── Auto-init any .cc-root already in DOM (block editor etc.) ── */
    $(document).ready(function () {
        $('.cc-root[data-cc-var]').each(function () {
            var ccVar      = $(this).data('cc-var');
            var instanceId = $(this).attr('id');
            if (instanceId && ccVar && !window[ccVar]) {
                var inst = CC.createInstance(instanceId);
                if (inst) { window[ccVar] = inst; }
            }
        });
    });

}(jQuery));
