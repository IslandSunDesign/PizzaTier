/* ══════════════════════════════════════════════════════════════════════
   PIZZATIER — Fornaia Template — custom.js

   Architecture mirrors NightPie (NP) but uses the RP namespace:
   - RP.createInstance(instanceId) → scoped builder instance
   - Each instance maintains its own state and updates its own pizza stack
   - Pizza stack = absolutely-positioned <div> layers inside .np-pizza-stage
     (shared layer IDs with NightPie for PizzaTierAPI compatibility)
   - window.RP_{instanceId} exposed for global access
   - window.PizzaTierAPI = public API (same surface as NightPie)
   ══════════════════════════════════════════════════════════════════════ */

(function ($) {
    'use strict';

    /* ════════════════════════════════════════════════════════════════
       PIZZA STACK RENDERER  (re-used verbatim from NightPie)
       ════════════════════════════════════════════════════════════════ */

    var PizzaStack = {

        getStage: function ($root) {
            var $stage = $root.find('.np-pizza-stage');
            if (!$stage.length) {
                var $canvas = $root.find('.rp-pizza-canvas, .np-pizza-stage-wrap').first();
                if (!$canvas.length) { return $(); }
                $stage = $('<div class="np-pizza-stage"></div>');
                $canvas.append($stage);
            }
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
                            $img.css({ transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.34,1.56,0.64,1)', opacity: 1, transform: 'scale(1)' });
                        });
                        break;
                    case 'slide-up':
                        $img.css({ transform: 'translateY(22%)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({ transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)', opacity: 1, transform: 'translateY(0)' });
                        });
                        break;
                    case 'flip-in':
                        $img.css({ transform: 'rotateY(90deg) scale(0.8)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({ transition: 'opacity ' + (dur + 80) + 'ms ease, transform ' + (dur + 80) + 'ms cubic-bezier(0.34,1.2,0.64,1)', opacity: 1, transform: 'rotateY(0deg) scale(1)' });
                        });
                        break;
                    case 'drop-in':
                        $img.css({ transform: 'translateY(-30%) scale(1.12)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({ transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)', opacity: 1, transform: 'translateY(0) scale(1)' });
                        });
                        break;
                    default: /* 'fade' */
                        requestAnimationFrame(function () {
                            $img.css({ transition: 'opacity 0.3s ease', opacity: 1 });
                        });
                        break;
                }
            };

            if ($img[0].complete && $img[0].naturalWidth) {
                applyAnim();
            } else {
                $img.on('load.pzanim', function () { $(this).off('load.pzanim'); applyAnim(); });
                requestAnimationFrame(applyAnim);
            }
        },

        setLayer: function ($stage, layerId, src, zIndex, cls, coverage, animMode) {
            if (!$stage.length) { return; }

            if (!animMode) {
                var $root = $stage.closest('[data-layer-anim]');
                animMode = $root.length ? $root.data('layer-anim') : 'fade';
            }

            /* Ignore nodes that are fading out — updating one in place would
               leave the new image inside a div that is about to be deleted
               (blank pizza after Reset / remove-then-re-add). */
            var $existing = PizzaStack.findLayer($stage, layerId);

            if (!src) {
                $existing.addClass('is-removing').fadeOut(200, function () { $(this).remove(); });
                return;
            }

            var clipStyle = PizzaStack.getCoverageClip(coverage);

            if ($existing.length) {
                $existing.css('z-index', zIndex);
                $existing.find('img').attr('src', src).css('clip-path', clipStyle);
            } else {
                var $layer = $('<div class="np-layer-div"></div>').attr('data-layer-id', layerId).css({
                    position: 'absolute', inset: 0, 'z-index': zIndex, 'pointer-events': 'none', overflow: 'hidden'
                });
                if (cls) { $layer.addClass(cls); }

                var $img = $('<img loading="lazy" decoding="async">').css({
                    position: 'absolute', inset: 0, width: '100%', height: '100%',
                    'object-fit': 'contain', 'clip-path': clipStyle, opacity: 0
                }).attr('src', src).attr('alt', layerId);

                $layer.append($img);
                $stage.append($layer);
                $stage.closest('.rp-pizza-canvas').addClass('rp-has-layers');
                PizzaStack._animateLayerIn($img, $stage, animMode);
            }
        },

        removeLayer: function ($stage, layerId) {
            PizzaStack.setLayer($stage, layerId, '', 0);
        },

        /** Live (not fading-out) layer div for an id. */
        findLayer: function ($stage, layerId) {
            if (!$stage || !$stage.length) { return $(); }
            return $stage.find('.np-layer-div').filter(function () {
                return this.getAttribute('data-layer-id') === layerId && !$(this).hasClass('is-removing');
            });
        },

        /** Remove every layer immediately (no fade, so re-applied layers survive). */
        clear: function ($stage) {
            if ($stage && $stage.length) { $stage.find('.np-layer-div').stop(true).remove(); }
        },

        applyShape: function ($canvas, shape, customRatio, customRadius) {
            switch (shape) {
                case 'square':
                    $canvas.css({ 'border-radius': '8px', 'aspect-ratio': '1 / 1' });
                    break;
                case 'rectangle':
                    $canvas.css({ 'border-radius': '12px', 'aspect-ratio': customRatio || '4 / 3' });
                    break;
                case 'custom':
                    $canvas.css({ 'border-radius': customRadius || '0px', 'aspect-ratio': customRatio || '1 / 1' });
                    break;
                default: /* 'round' */
                    $canvas.css({ 'border-radius': '50%', 'aspect-ratio': '1 / 1' });
                    break;
            }
        },

        getCoverageClip: function (coverage) {
            switch (coverage) {
                case 'half-left':           return 'polygon(0 0, 50% 0, 50% 100%, 0 100%)';
                case 'half-right':          return 'polygon(50% 0, 100% 0, 100% 100%, 50% 100%)';
                case 'quarter-top-left':    return 'polygon(0 0, 50% 0, 50% 50%, 0 50%)';
                case 'quarter-top-right':   return 'polygon(50% 0, 100% 0, 100% 50%, 50% 50%)';
                case 'quarter-bottom-left': return 'polygon(0 50%, 50% 50%, 50% 100%, 0 100%)';
                case 'quarter-bottom-right':return 'polygon(50% 50%, 100% 50%, 100% 100%, 50% 100%)';
                default: return 'none';
            }
        }
    };

    /* ════════════════════════════════════════════════════════════════
       INSTANCE FACTORY
       ════════════════════════════════════════════════════════════════ */

    function createRPInstance(instanceId) {

        var $root      = $('#' + instanceId + ', [data-instance="' + instanceId + '"]').first();
        var maxTopping = parseInt($root.data('max-toppings')) || 99;
        var animMode   = $root.data('layer-anim') || 'fade';

        var state = {
            crust:    null,
            sauce:    null,
            cheese:   null,
            drizzle:  null,
            cut:      null,
            toppings: {}
        };

        var _$stage = null;
        function getStage() {
            if (!_$stage || !_$stage.length) {
                _$stage = PizzaStack.getStage($root);
            }
            return _$stage;
        }

        var $find = function (sel) { return $root.find(sel); };

        var instance = {

            init: function () {
                this._initStage();
                this._bindTabs();
                this.goTab(this._firstTab());
                this._updateCounter();
                this._initDefaultLayers();
            },

            _initStage: function () {
                var $canvas = $find('#' + instanceId + '-canvas');
                if (!$canvas.length) { return; }

                if (!$canvas.find('.np-pizza-stage').length) {
                    $canvas.empty().append(
                        $('<div class="np-pizza-stage-wrap"></div>').append(
                            $('<div class="np-pizza-stage"></div>')
                        )
                    );
                }
                _$stage = $canvas.find('.np-pizza-stage');

                var shape        = $root.data('pizza-shape')  || 'round';
                var customRatio  = $root.data('pizza-aspect') || '1';
                var customRadius = $root.data('pizza-radius') || '0px';
                PizzaStack.applyShape($canvas, shape, customRatio, customRadius);
            },

            _initDefaultLayers: function () {
                var $wrap = getStage().closest('.np-pizza-stage-wrap');
                var zMap  = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };

                // Every base type (drizzle / cut included) goes through the same
                // path so the default is in state (priced) and its card selected.
                // crust/sauce/cheese fall back to the first card when no default.
                var applyBase = function (layerType, useFirstFallback) {
                    var url   = $wrap.attr('data-default-' + layerType) || '';
                    var slug  = $wrap.attr('data-default-' + layerType + '-slug') || '';
                    var $card = instance._findCard(layerType, slug, url);

                    if (!url && useFirstFallback) {
                        $card = $find('.rp-card[data-layer="' + layerType + '"]').first();
                        url   = $card.length ? ($card.attr('data-layer-img') || '') : '';
                    }
                    if (!url) { return; }

                    if ($card.length) { slug = $card.attr('data-slug') || slug; }
                    var title = ($card.length && $card.attr('data-title')) || slug;
                    var thumb = $card.length ? ($card.attr('data-thumb') || '') : '';

                    PizzaStack.setLayer(getStage(), 'layer-' + layerType, url, zMap[layerType], 'nl-' + layerType);
                    state[layerType] = { slug: slug, title: title, layerImg: url, thumb: thumb };
                    if ($card.length) { instance._markCard($card, true); }
                };

                applyBase('crust',   true);
                applyBase('sauce',   true);
                applyBase('cheese',  true);
                applyBase('drizzle', false);
                applyBase('cut',     false);

                try {
                    var defaultTops = JSON.parse($wrap.attr('data-default-toppings') || '[]');
                    if (Array.isArray(defaultTops)) {
                        defaultTops.forEach(function (t) {
                            if (!t || !t.slug || !t.layerImg) { return; }
                            var cov   = t.coverage || 'whole';
                            var $card = instance._findCard('toppings', t.slug, t.layerImg);
                            PizzaStack.setLayer(getStage(), 'layer-topping-' + t.slug, t.layerImg, t.zindex || 400, 'nl-topping', cov);
                            state.toppings[t.slug] = {
                                slug: t.slug,
                                title: ($card.length && $card.attr('data-title')) || t.slug,
                                thumb: $card.length ? ($card.attr('data-thumb') || '') : '',
                                layerImg: t.layerImg, zindex: t.zindex || 400, coverage: cov
                            };
                            if ($card.length) {
                                instance._markCard($card, true);
                                $card.find('.rp-cov-btn').removeClass('active')
                                     .filter('[data-fraction="' + cov + '"]').addClass('active');
                            }
                        });
                        instance._updateCounter();
                    }
                } catch (e) { /* ignore */ }
            },

            /* ── Card lookup: by slug, else by matching layer image URL ── */
            _findCard: function (layerType, slug, url) {
                var $all  = $find('.rp-card[data-layer="' + layerType + '"]');
                var $card = $();
                if (slug) {
                    $card = $all.filter(function () { return this.getAttribute('data-slug') === String(slug); });
                }
                if (!$card.length && url) {
                    $card = $all.filter(function () { return this.getAttribute('data-layer-img') === url; });
                }
                return $card.first();
            },

            /* ── Card lookup for a remove call (own card, else by slug) ── */
            _cardFor: function (layerType, slug, triggerEl) {
                var $card = triggerEl ? $(triggerEl).closest('.rp-card') : $();
                if (!$card.length) { $card = instance._findCard(layerType, slug, ''); }
                return $card;
            },

            /* ── Selected / unselected card UI ── */
            _markCard: function ($card, selected) {
                if (!$card || !$card.length) { return; }
                $card.toggleClass('rp-card--selected', !!selected);
                $card.find('.rp-btn--add').toggle(!selected);
                $card.find('.rp-btn--remove').toggle(!!selected);
                if ($card.find('.rp-coverage').length) { $card.find('.rp-coverage').toggle(!!selected); }
            },

            /* ── First tab actually rendered (crust may be hidden) ── */
            _firstTab: function () {
                var t = $find('.rp-step').first().data('tab');
                if (!t) {
                    var $p = $find('.rp-panel').first();
                    t = $p.length ? String($p.attr('id') || '').replace(instanceId + '-panel-', '') : '';
                }
                return t || 'crust';
            },

            /* ── Tab switching ── */
            goTab: function (tabName) {
                $find('.rp-step').each(function () {
                    var t = $(this).data('tab');
                    $(this)
                        .toggleClass('active', t === tabName)
                        .attr('aria-selected', t === tabName ? 'true' : 'false');
                });

                $find('.rp-panel').each(function () {
                    var p = this.id.replace(instanceId + '-panel-', '');
                    $(this).toggleClass('active', p === tabName);
                });

                if (tabName === 'yourpizza') { instance._renderSummary(); }

                // Scroll step nav to active
                var $activeStep = $find('.rp-step[data-tab="' + tabName + '"]');
                if ($activeStep.length) {
                    var nav = $find('.rp-stepnav')[0];
                    if (nav) { nav.scrollTo({ left: $activeStep[0].offsetLeft - 16, behavior: 'smooth' }); }
                }
            },

            /* ── Swap exclusive base layer ── */
            swapBase: function (layerType, slug, title, layerImg, triggerEl) {
                $find('.rp-card[data-layer="' + layerType + '"]').each(function () {
                    $(this).removeClass('rp-card--selected');
                    $(this).find('.rp-btn--add').show();
                    $(this).find('.rp-btn--remove').hide();
                });

                var $card = $(triggerEl).closest('.rp-card');
                var thumb = $card.data('thumb') || '';
                $card.addClass('rp-card--selected');
                $card.find('.rp-btn--add').hide();
                $card.find('.rp-btn--remove').show();

                state[layerType] = { slug: slug, title: title, thumb: thumb, layerImg: layerImg };

                var zMap = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };
                PizzaStack.setLayer(getStage(), 'layer-' + layerType, layerImg, zMap[layerType] || 500, 'nl-' + layerType);

                var tabForLayer = (layerType === 'cut') ? 'slicing' : layerType;
                $find('.rp-step[data-tab="' + tabForLayer + '"]').addClass('rp-step--done');

                instance._flyTo($card.find('.rp-card__thumb'));
                instance._updateSummaryRow(layerType);
            },

            /* ── Remove exclusive base layer ── */
            removeBase: function (layerType, slug, triggerEl) {
                /* From the summary panel the trigger is not inside a card. */
                var $card = instance._cardFor(layerType, slug, triggerEl);
                $card.removeClass('rp-card--selected');
                $card.find('.rp-btn--add').show();
                $card.find('.rp-btn--remove').hide();

                state[layerType] = null;
                PizzaStack.removeLayer(getStage(), 'layer-' + layerType);
                instance._updateSummaryRow(layerType);
            },

            /* ── Add topping ── */
            addTopping: function (zindex, slug, layerImg, title, cssId, menuId, triggerEl) {
                var currentCount = Object.keys(state.toppings).length;
                if (currentCount >= maxTopping) {
                    var settings = window.pizzatierSettings || {};
                    var maxMsg = settings.textMaxToppings || ('Maximum ' + maxTopping + ' toppings reached!');
                    instance._showToast(maxMsg);
                    return;
                }

                var $card = $(triggerEl).closest('.rp-card');
                var thumb = $card.data('thumb') || '';
                $card.addClass('rp-card--selected');
                $card.find('.rp-btn--add').hide();
                $card.find('.rp-btn--remove').show();
                $card.find('.rp-coverage').show();
                $card.find('.rp-cov-btn[data-fraction="whole"]').addClass('active');
                state.toppings[slug] = { slug: slug, title: title, thumb: thumb, layerImg: layerImg, coverage: 'whole', zindex: zindex };
                PizzaStack.setLayer(getStage(), 'layer-topping-' + slug, layerImg, zindex, 'nl-topping', 'whole');

                $find('.rp-step[data-tab="toppings"]').addClass('rp-step--done');
                instance._flyTo($card.find('.rp-card__thumb'));
                instance._updateCounter();
                instance._updateSummaryRow('toppings');
            },

            /* ── Remove topping ── */
            removeTopping: function (layerId, slug, triggerEl) {
                /* From the summary panel the trigger is not inside a card. */
                var $card = instance._cardFor('toppings', slug, triggerEl);
                $card.removeClass('rp-card--selected');
                $card.find('.rp-btn--add').show();
                $card.find('.rp-btn--remove').hide();
                $card.find('.rp-coverage').hide();
                $card.find('.rp-cov-btn').removeClass('active');

                delete state.toppings[slug];
                PizzaStack.removeLayer(getStage(), 'layer-topping-' + slug);
                instance._updateCounter();
                instance._updateSummaryRow('toppings');
            },

            /* ── Set coverage ── */
            setCoverage: function (slug, fraction, triggerEl) {
                if (!state.toppings[slug]) { return; }
                if (['whole','half-left','half-right','quarter-top-left','quarter-top-right','quarter-bottom-left','quarter-bottom-right'].indexOf(fraction) === -1) { return; }
                state.toppings[slug].coverage = fraction;

                var $card = instance._cardFor('toppings', slug, triggerEl);
                $card.find('.rp-cov-btn').removeClass('active')
                     .filter('[data-fraction="' + fraction + '"]').addClass('active');

                var $layer = PizzaStack.findLayer(getStage(), 'layer-topping-' + slug);
                if ($layer.length) {
                    $layer.find('img').css('clip-path', PizzaStack.getCoverageClip(fraction));
                }
                instance._updateSummaryRow('toppings');
            },

            /* ── Reset all ── */
            resetAll: function () {
                state = { crust: null, sauce: null, cheese: null, drizzle: null, cut: null, toppings: {} };

                $find('.rp-card').removeClass('rp-card--selected');
                $find('.rp-btn--add').show();
                $find('.rp-btn--remove').hide();
                $find('.rp-coverage').hide();
                $find('.rp-cov-btn').removeClass('active');
                $find('.rp-step').removeClass('rp-step--done');

                // Clear only THIS builder's stage, immediately: a fade-then-remove
                // here deleted the layers _initDefaultLayers / setState re-apply
                // straight after (they update the fading div in place).
                PizzaStack.clear(getStage());

                instance._updateCounter();
                instance._renderSummary();
                instance.goTab(instance._firstTab());
                instance._initDefaultLayers();
            },

            /* ── Public API surface ── */
            getState: function () { return JSON.parse(JSON.stringify(state)); },

            setState: function (newState) {
                instance.resetAll();
                if (newState.crust)    { instance._applyBaseFromState('crust',   newState.crust); }
                if (newState.sauce)    { instance._applyBaseFromState('sauce',   newState.sauce); }
                if (newState.cheese)   { instance._applyBaseFromState('cheese',  newState.cheese); }
                if (newState.drizzle)  { instance._applyBaseFromState('drizzle', newState.drizzle); }
                if (newState.cut)      { instance._applyBaseFromState('cut',     newState.cut); }
                if (newState.toppings) {
                    $.each(newState.toppings, function (slug, t) {
                        var $card = $find('.rp-card[data-layer="toppings"][data-slug="' + slug + '"]');
                        if ($card.length) {
                            $card.find('.rp-btn--add').trigger('click');
                            /* Restore the saved coverage (cart edit / Pro defaults). */
                            if (t && t.coverage && t.coverage !== 'whole') {
                                instance.setCoverage(slug, t.coverage);
                            }
                        } else {
                            /* Coverage is rendered from state: reject unknown values. */
                            if (t && ['whole','half-left','half-right','quarter-top-left','quarter-top-right','quarter-bottom-left','quarter-bottom-right'].indexOf(t.coverage) === -1) { t.coverage = 'whole'; }
                            state.toppings[slug] = t;
                            PizzaStack.setLayer(getStage(), 'layer-topping-' + slug, t.layerImg, t.zindex || 400, 'nl-topping', t.coverage || 'whole');
                        }
                    });
                }
            },

            _applyBaseFromState: function (layerType, layerData) {
                var $card = $find('.rp-card[data-layer="' + layerType + '"][data-slug="' + layerData.slug + '"]');
                if ($card.length) {
                    $card.find('.rp-btn--add').trigger('click');
                } else {
                    state[layerType] = layerData;
                    var zMap = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };
                    PizzaStack.setLayer(getStage(), 'layer-' + layerType, layerData.layerImg, zMap[layerType] || 500, 'nl-' + layerType);
                }
            },

            /* ── Private helpers ── */
            _bindTabs: function () {
                $root.on('click', '.rp-step', function () {
                    instance.goTab($(this).data('tab'));
                });
            },

            _updateCounter: function () {
                var count = Object.keys(state.toppings).length;
                $find('#' + instanceId + '-count').text(count);
                $find('.rp-topping-counter').css('border-color', count >= maxTopping ? 'var(--rp-accent)' : '');
            },

            _flyTo: function ($thumbEl) {
                if (!$thumbEl || !$thumbEl.length) { return; }
                var $target = $find('#' + instanceId + '-canvas, .rp-pizza-canvas').first();
                if (!$target.length) { return; }

                var srcRect = $thumbEl[0].getBoundingClientRect();
                var dstRect = $target[0].getBoundingClientRect();
                if (!srcRect.width || !dstRect.width) { return; }

                var $clone = $('<div class="rp-fly-clone"></div>').css({
                    top: srcRect.top, left: srcRect.left, width: srcRect.width, height: srcRect.height
                });
                if ($thumbEl.is('img')) {
                    $clone.append($('<img>').attr('src', $thumbEl.attr('src')).css({ width: '100%', height: '100%', 'object-fit': 'cover' }));
                }
                $find('#' + instanceId + '-fly-container').first().append($clone);

                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var destX = dstRect.left + dstRect.width  / 2 - srcRect.width  / 2;
                        var destY = dstRect.top  + dstRect.height / 2 - srcRect.height / 2;
                        $clone.css({
                            transition: 'top 0.65s cubic-bezier(0.4,0,0.2,1), left 0.65s cubic-bezier(0.4,0,0.2,1), transform 0.65s cubic-bezier(0.4,0,0.2,1), opacity 0.65s ease',
                            top: destY, left: destX, transform: 'scale(0.18)', opacity: '0'
                        });
                        setTimeout(function () { $clone.remove(); }, 700);
                    });
                });
            },

            _showToast: function (msg) {
                var settings = window.pizzatierSettings || {};
                if ((settings.toastStyle || '') === 'none') { return; }
                var dur = parseInt(settings.toastDuration, 10) || 3000;
                var $toast = $('<div class="rp-toast"></div>').text(msg);
                $root.append($toast);
                setTimeout(function () { $toast.addClass('rp-toast--visible'); }, 10);
                setTimeout(function () {
                    $toast.removeClass('rp-toast--visible');
                    setTimeout(function () { $toast.remove(); }, 400);
                }, dur);
            },

            _updateSummaryRow: function () {
                if ($find('#' + instanceId + '-panel-yourpizza').hasClass('active')) {
                    instance._renderSummary();
                }
            },

            _renderSummary: function () {
                var layerMap = { crust: 'crust', sauce: 'sauce', cheese: 'cheese', drizzle: 'drizzle', cut: 'slicing' };
                $.each(layerMap, function (lt, ypKey) {
                    var $val = $find('#' + instanceId + '-yp-' + ypKey + '-val');
                    var sel  = state[lt];
                    if (sel) {
                        $val.html(instance._selBubble(sel.thumb, sel.title, null, null, lt, sel.slug));
                        $val.closest('.rp-yourpizza__row').addClass('has-selection');
                    } else {
                        $val.html('<span class="rp-yp-none">— none selected —</span>');
                        $val.closest('.rp-yourpizza__row').removeClass('has-selection');
                    }
                });

                var $tVal = $find('#' + instanceId + '-yp-toppings-val');
                var tKeys = Object.keys(state.toppings);
                if (tKeys.length) {
                    var html = '';
                    $.each(tKeys, function (i, slug) {
                        var t = state.toppings[slug];
                        html += instance._selBubble(t.thumb, t.title, t.coverage, slug, 'toppings', slug);
                    });
                    $tVal.html(html);
                    $tVal.closest('.rp-yourpizza__row').addClass('has-selection');
                } else {
                    $tVal.html('<span class="rp-yp-none">— none added —</span>');
                    $tVal.closest('.rp-yourpizza__row').removeClass('has-selection');
                }
            },

            _selBubble: function (thumb, title, coverage, toppingSlug, layerType, slug) {
                var rpVar  = $root.data('rp-var');
                var imgHtml = thumb ? '<img src="' + instance._esc(thumb) + '" alt="' + instance._esc(title) + '" />' : '';
                var covHtml = coverage ? '<span class="rp-yp-coverage"> · ' + instance._esc(coverage.replace('quarter-', 'Q').replace('half-', '½')) + '</span>' : '';
                var remHtml = '';
                if (layerType === 'toppings' && toppingSlug) {
                    remHtml = '<button class="rp-yp-remove" onclick="' + instance._jsq(rpVar) + '.removeTopping(\'pizzatier-topping-' + instance._jsq(toppingSlug) + '\',\'' + instance._jsq(toppingSlug) + '\',this);" title="Remove"><i class="fa fa-times"></i></button>';
                } else if (layerType && slug) {
                    remHtml = '<button class="rp-yp-remove" onclick="' + instance._jsq(rpVar) + '.removeBase(\'' + instance._jsq(layerType) + '\',\'' + instance._jsq(slug) + '\',this);" title="Remove"><i class="fa fa-times"></i></button>';
                }
                return '<div class="rp-yp-bubble">' + imgHtml + '<span class="rp-yp-name">' + instance._esc(title) + covHtml + '</span>' + remHtml + '</div>';
            },

            /* Escape a value for a single-quoted JS string inside a double-quoted
               HTML attribute (onclick="…('VALUE')"): JS-escape first, then HTML-escape. */
            _jsq: function (str) {
                return instance._esc(String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/[\r\n\u2028\u2029]/g, ''));
            },

            _esc: function (str) {
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
        };

        return instance;
    }

    /* ════════════════════════════════════════════════════════════════
       GLOBAL RP FACTORY
       ════════════════════════════════════════════════════════════════ */

    var RP = {
        _instances: {},

        createInstance: function (instanceId) {
            var inst = createRPInstance(instanceId);
            RP._instances[instanceId] = inst;
            return inst;
        },

        getInstance: function (instanceId) {
            return RP._instances[instanceId] || null;
        }
    };

    /* ════════════════════════════════════════════════════════════════
       AUTO-INITIALISE ALL .rp-root ELEMENTS ON PAGE LOAD
       ════════════════════════════════════════════════════════════════ */

    $(document).ready(function () {
        $('.rp-root').each(function () {
            var instanceId = $(this).data('instance') || $(this).attr('id');
            if (!instanceId) { return; }

            var rpVar = $(this).data('rp-var') || ('RP_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));
            var inst  = RP.createInstance(instanceId);
            inst.init();

            // Expose globally for onclick= handlers in PHP-rendered HTML
            window[rpVar] = inst;
        });

        // Backward compat: single-instance sites
        var allIds = Object.keys(RP._instances);
        if (allIds.length === 1) {
            window.RP = RP._instances[allIds[0]];
        } else {
            window.RP = RP;
        }
    });

    /* ════════════════════════════════════════════════════════════════
       EXPOSE GLOBAL API (same surface as NightPie for Pro compat)
       ════════════════════════════════════════════════════════════════ */
    window.PizzaTierRP = RP;

    /* PizzaTierAPI — standard surface consumed by PizzaTier. setState is what
       frontend-builder's default layers and cart-edit restore call. */
    RP.API = {
        getState: function (instanceId) {
            var inst = RP.getInstance(instanceId);
            return inst ? inst.getState() : null;
        },
        setState: function (instanceId, newState) {
            var inst = RP.getInstance(instanceId);
            if (inst && newState && typeof newState === 'object') { inst.setState(newState); }
        },
        getAllInstances: function () {
            return Object.keys(RP._instances);
        }
    };
    if (window.PizzaTierAPI && typeof window.PizzaTierAPI.register === 'function') {
        window.PizzaTierAPI.register(RP.API);
    } else {
        window.PizzaTierAPI = window.PizzaTierAPI || RP.API;
    }

    /* Legacy global functions — delegate to stack API */
    window.ClearPizza = window.ClearPizza || function () {
        $('.np-pizza-stage').each(function () {
            $(this).find('.np-layer-div').fadeOut(300, function () { $(this).remove(); });
        });
    };

})(jQuery);
