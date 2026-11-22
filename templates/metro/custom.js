/* ══════════════════════════════════════════════════════════════
   PIZZATIER — Metro Template — custom.js

   Architecture:
   - Reuses the NightPie PizzaStack engine (available via PizzaTierNP)
     for the actual layer rendering. Metro only manages its own UI:
     orb visibility, tray chips, section nav active state, scroll tracking.
   - MT.createInstance(instanceId) → scoped instance
   - window.MT_{instanceId} exposed for onclick= handlers in PHP
   - window.PizzaTierAPI remains the canonical external API
   ══════════════════════════════════════════════════════════════ */

(function ($) {
    'use strict';

    /* ════════════════════════════════════════════════════════
       PIZZA STACK — forward to NightPie's engine if available,
       otherwise use a lightweight fallback.
       ════════════════════════════════════════════════════════ */
    var PizzaStack = (window.PizzaTierNP && window.PizzaTierNP._PizzaStack)
        ? window.PizzaTierNP._PizzaStack
        : {
            /* Minimal fallback — just swap src on existing <img> or append */
            getStage: function ($root) {
                var $stage = $root.find('.np-pizza-stage');
                if (!$stage.length) {
                    $stage = $('<div class="np-pizza-stage" style="position:relative;width:100%;height:100%;"></div>');
                    $root.find('.mt-hero__canvas, .mt-tray__pizza-thumb, .mt-orb__pizza').first().append($stage);
                }
                return $stage;
            },
            /** Live (not fading-out) layer div for an id. */
            findLayer: function ($stage, layerId) {
                if (!$stage || !$stage.length) { return $(); }
                return $stage.find('.np-layer-div').filter(function () {
                    return this.getAttribute('data-layer-id') === layerId && !$(this).hasClass('is-removing');
                });
            },
            setLayer: function ($stage, layerId, src, zIndex, cls, coverage) {
                if (!$stage || !$stage.length) { return; }
                /* Skip nodes that are fading out: updating one in place left the
                   new image inside a div that was deleted 200ms later. */
                var $el = this.findLayer($stage, layerId);
                if (!src) { this.removeLayer($stage, layerId); return; }
                if (!$el.length) {
                    $el = $('<div class="np-layer-div" style="position:absolute;inset:0;"></div>').attr('data-layer-id', layerId);
                    if (cls) { $el.addClass(cls); }
                    $stage.append($el);
                }
                $el.css('z-index', zIndex);
                /* Update the existing <img> in place (rebuilding it wiped the
                   coverage clip-path) and always apply this layer's coverage. */
                var $img = $el.find('img');
                if (!$img.length) {
                    $img = $('<img alt="" style="width:100%;height:100%;object-fit:cover;opacity:0;" />');
                    $img.on('load', function () { $(this).css({ transition: 'opacity 0.3s', opacity: 1 }); });
                    $el.append($img);
                }
                if ($img.attr('src') !== src) { $img.attr('src', src); }
                if ($img[0].complete && $img[0].naturalWidth) { $img.css({ opacity: 1 }); }
                $img.css('clip-path', this.getCoverageClip(coverage || 'whole'));
            },
            removeLayer: function ($stage, layerId) {
                this.findLayer($stage, layerId).addClass('is-removing').fadeOut(200, function () { $(this).remove(); });
            },
            getCoverageClip: function (fraction) {
                var clips = {
                    'whole':                'none',
                    'half-left':            'polygon(0 0,50% 0,50% 100%,0 100%)',
                    'half-right':           'polygon(50% 0,100% 0,100% 100%,50% 100%)',
                    'quarter-top-left':     'polygon(0 0,50% 0,50% 50%,0 50%)',
                    'quarter-top-right':    'polygon(50% 0,100% 0,100% 50%,50% 50%)',
                    'quarter-bottom-left':  'polygon(0 50%,50% 50%,50% 100%,0 100%)',
                    'quarter-bottom-right': 'polygon(50% 50%,100% 50%,100% 100%,50% 100%)'
                };
                return clips[fraction] || 'none';
            }
        };

    /* ════════════════════════════════════════════════════════
       INSTANCE FACTORY
       ════════════════════════════════════════════════════════ */
    function createMTInstance(instanceId) {

        var $root    = $('#' + instanceId);
        var $find    = function (sel) { return $root.find(sel); };

        /* State: mirrors NightPie state shape so PizzaTierAPI works */
        var state = {
            crust:    null,  // { slug, title, thumb, layerImg }
            sauce:    null,
            cheese:   null,
            drizzle:  null,
            cut:      null,
            toppings: {}     // slug → { slug, title, thumb, layerImg, zindex, coverage }
        };

        /* Step mode state */
        var stepMode    = false;
        var stepIndex   = 0;
        var stepOrder   = [];

        /* Coverage modal state */
        var covModalSlug = null;

        /* Stage references */
        var $heroCanvas    = $find('#' + instanceId + '-canvas');
        var $sidebarCanvas = $find('#' + instanceId + '-sidebar-canvas');
        var $orbPizza      = $find('#' + instanceId + '-orb-pizza');
        var $trayThumb     = $find('#' + instanceId + '-tray-pizza');
        var $orbCountEl    = $find('#' + instanceId + '-orb-count');
        var $countEl       = $find('#' + instanceId + '-count');
        var $trayChips     = $find('#' + instanceId + '-tray-chips');
        var $orb           = $find('#' + instanceId + '-orb');
        var $hero          = $find('#' + instanceId + '-hero');
        var $sectionNav    = $find('#' + instanceId + '-section-nav');
        var $toppingBadge  = $find('#' + instanceId + '-topping-badge');
        var $toppingSearch = $find('#' + instanceId + '-topping-search');
        var $modeToggle    = $find('#' + instanceId + '-mode-toggle');
        var $stepNav       = $find('#' + instanceId + '-step-nav');
        var $stepPrev      = $find('#' + instanceId + '-step-prev');
        var $stepNext      = $find('#' + instanceId + '-step-next');
        var $stepCurrent   = $find('#' + instanceId + '-step-indicator .mt-step-nav__current');
        var $covModal      = $find('#' + instanceId + '-cov-modal');

        /* Layer z-index map */
        var zMap = { crust: 100, sauce: 150, cheese: 200, drizzle: 900, cut: 950 };

        /* ── Helpers ─────────────────────────────────────── */

        function getActiveCanvas() {
            /* Side-by-side mode: hero is display:none, sidebar canvas is the live one.
               Use jQuery's is(':visible') — works even before layout settles. */
            if ($sidebarCanvas.length && $sidebarCanvas.closest('.mt-sidebar').is(':visible')) {
                return $sidebarCanvas;
            }
            return $heroCanvas;
        }

        function getHeroStage() {
            return PizzaStack.getStage(getActiveCanvas());
        }

        function mirrorStage($target, $heroStage) {
            if (!$target.length || !$heroStage.length) { return; }
            var html = $heroStage.html();
            $target.html('<div class="np-pizza-stage" style="position:relative;width:100%;height:100%;">' + html + '</div>');
        }

        function _toppingCount() {
            return Object.keys(state.toppings).length;
        }

        function _updateCounters() {
            var n = _toppingCount();
            $countEl.text(n);
            $orbCountEl.text(n);
            /* Also sync sidebar count which has no ID — use class selector scoped to root */
            $find('.mt-sidebar__count').text(n);
        }

        function _updateToppingBadge() {
            var n = _toppingCount();
            $toppingBadge.text(n).attr('data-count', n);
        }

        function _syncMiniPizzas() {
            var $hs = getHeroStage();
            setTimeout(function () {
                mirrorStage($orbPizza, $hs);
                mirrorStage($trayThumb, $hs);

                /* Keep the inactive canvas in sync with the active one so
                   switching layout modes shows the current state immediately */
                var $active = getActiveCanvas();
                var $other  = $active.is($heroCanvas) ? $sidebarCanvas : $heroCanvas;
                if ($other.length) {
                    var $otherStage = $other.find('.np-pizza-stage');
                    if (!$otherStage.length) {
                        $other.empty().append(
                            $('<div class="np-pizza-stage-wrap"></div>').append(
                                $('<div class="np-pizza-stage"></div>')
                            )
                        );
                        $otherStage = $other.find('.np-pizza-stage');
                    }
                    $otherStage.html($hs.html());
                }
            }, 50);
        }

        /* Single-quoted JS string inside a double-quoted HTML attribute. */
        function _jsq(str) {
            return _esc(String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/[\r\n\u2028\u2029]/g, ''));
        }

        function _esc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        /* ── Card lookup: by slug, else by matching layer image URL ── */
        function _findCard(layerType, slug, url) {
            var $all  = $find('.mt-card[data-layer="' + layerType + '"]');
            var $card = $();
            if (slug) {
                $card = $all.filter(function () { return this.getAttribute('data-slug') === String(slug); });
            }
            if (!$card.length && url) {
                $card = $all.filter(function () { return this.getAttribute('data-layer-img') === url; });
            }
            return $card.first();
        }

        /* Own card for a remove call; tray chips are not inside a card. */
        function _cardFor(layerType, slug, triggerEl) {
            var $card = triggerEl ? $(triggerEl).closest('.mt-card') : $();
            if (!$card.length) { $card = _findCard(layerType, slug, ''); }
            return $card;
        }

        /* ── Apply layer offsets from shortcode data attr ── */
        function _applyLayerOffsets() {
            try {
                var offsets = JSON.parse($root.attr('data-layer-offsets') || '{}');
                var $stage  = getHeroStage();
                if (!$stage.length) { return; }
                var $wrap = $stage.closest('.np-pizza-stage-wrap').parent();
                /* Set CSS custom properties on the hero canvas so scoped rules pick them up */
                $.each(offsets, function (type, pct) {
                    if (pct > 0) {
                        $root[0].style.setProperty('--mt-offset-' + type, pct + '%');
                    }
                });
            } catch (e) { /* ignore */ }
        }

        /* ── Tray chips ──────────────────────────────────── */
        function _renderChips() {
            var chips = '';
            var mtVarAttr = $root.data('mt-var') || ('MT_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));

            var baseTypes = ['crust', 'sauce', 'cheese', 'drizzle', 'cut'];

            baseTypes.forEach(function (lt) {
                var sel = state[lt];
                if (!sel) { return; }
                var imgHtml = sel.thumb
                    ? '<img src="' + _esc(sel.thumb) + '" alt="' + _esc(sel.title) + '">'
                    : '';
                var removeJS = "window['" + _jsq(mtVarAttr) + "']&&window['" + _jsq(mtVarAttr) + "'].removeBase('" + _jsq(lt) + "','" + _jsq(sel.slug) + "',this)";
                chips += '<div class="mt-chip" data-layer="' + lt + '" data-slug="' + _esc(sel.slug) + '">'
                       + imgHtml
                       + '<span>' + _esc(sel.title) + '</span>'
                       + '<button class="mt-chip__remove" onclick="' + removeJS + '" title="Remove"><i class="fa fa-times"></i></button>'
                       + '</div>';
            });

            $.each(state.toppings, function (slug, t) {
                var imgHtml = t.thumb ? '<img src="' + _esc(t.thumb) + '" alt="' + _esc(t.title) + '">' : '';
                var removeJS = "window['" + _jsq(mtVarAttr) + "']&&window['" + _jsq(mtVarAttr) + "'].removeTopping('pizzatier-topping-" + _jsq(slug) + "','" + _jsq(slug) + "',this)";
                var covLabel = t.coverage ? ' <small>(' + _esc(t.coverage.replace('quarter-','Q').replace('half-left','L½').replace('half-right','R½')) + ')</small>' : '';
                chips += '<div class="mt-chip" data-layer="toppings" data-slug="' + _esc(slug) + '">'
                       + imgHtml
                       + '<span>' + _esc(t.title) + covLabel + '</span>'
                       + '<button class="mt-chip__remove" onclick="' + removeJS + '" title="Remove"><i class="fa fa-times"></i></button>'
                       + '</div>';
            });

            if (chips) {
                $trayChips.html(chips);
            } else {
                $trayChips.html('<span class="mt-tray__empty">Start selecting below ↓</span>');
            }
        }

        function _showToast(msg) {
            var settings = window.pizzatierSettings || {};
            if ( (settings.toastStyle || '') === 'none' ) { return; }
            var dur = parseInt(settings.toastDuration, 10) || 2800;
            var $t = $('<div class="mt-toast"></div>').text(msg);
            $root.append($t);
            setTimeout(function () { $t.addClass('is-visible'); }, 10);
            setTimeout(function () { $t.removeClass('is-visible'); setTimeout(function () { $t.remove(); }, 300); }, dur);
        }

        /* ── Card state helpers ──────────────────────────── */
        function _markCard($card, selected) {
            if (selected) {
                $card.addClass('is-selected');
                $card.find('.mt-card__btn--add').hide();
                $card.find('.mt-card__btn--remove').show();
                /* For toppings: show coverage button too */
                if ($card.hasClass('mt-card--topping')) {
                    $card.find('.mt-card__btn--coverage').show();
                }
                /* Add checkmark badge if not already present */
                if (!$card.find('.mt-card__check').length) {
                    $card.find('.mt-card__img-wrap').append('<span class="mt-card__check" aria-hidden="true"><i class="fa fa-check"></i></span>');
                }
            } else {
                $card.removeClass('is-selected');
                $card.find('.mt-card__btn--add').show();
                $card.find('.mt-card__btn--remove').hide();
                $card.find('.mt-card__btn--coverage').hide();
                $card.find('.mt-card__check').remove();
            }
        }

        function _deselectAllInSection($section) {
            $section.find('.mt-card.is-selected').each(function () {
                _markCard($(this), false);
            });
        }

        /* ── Coverage modal ──────────────────────────────── */
        function _openCoverageModal(slug, title, thumb) {
            covModalSlug = slug;
            $covModal.find('.mt-cov-modal__name').text(title);
            var $img = $covModal.find('.mt-cov-modal__thumb');
            if (thumb) { $img.attr('src', thumb).show(); } else { $img.hide(); }

            /* Mark current active fraction */
            var currentCov = (state.toppings[slug] && state.toppings[slug].coverage) || 'whole';
            $covModal.find('.mt-cov-modal__btn').removeClass('is-active')
                     .filter('[data-fraction="' + currentCov + '"]').addClass('is-active');

            $covModal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('mt-modal-open');
        }

        function _closeCoverageModal() {
            $covModal.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('mt-modal-open');
            covModalSlug = null;
        }

        /* ── Step mode ───────────────────────────────────── */
        function _buildStepOrder() {
            stepOrder = [];
            $root.find('.mt-section[data-section]').each(function () {
                stepOrder.push($(this).data('section'));
            });
        }

        function _goToStep(idx) {
            if (!stepMode || !stepOrder.length) { return; }
            stepIndex = Math.max(0, Math.min(stepOrder.length - 1, idx));

            $root.find('.mt-section').removeClass('is-step-active');
            var sectionId = instanceId + '-section-' + stepOrder[stepIndex];
            $('#' + sectionId).addClass('is-step-active');

            $stepCurrent.text(stepIndex + 1);
            $stepPrev.prop('disabled', stepIndex === 0);
            $stepNext.prop('disabled', stepIndex === stepOrder.length - 1);

            /* Update section nav active state */
            $sectionNav.find('.mt-section-nav__item').removeClass('is-active')
                       .filter('[data-section="' + stepOrder[stepIndex] + '"]').addClass('is-active');
        }

        function _enableStepMode() {
            stepMode = true;
            $root.addClass('is-step-mode');
            $modeToggle.attr('aria-pressed', 'true')
                       .attr('title', 'Switch to scroll mode');
            $modeToggle.find('.mt-mode-toggle__icon-step').hide();
            $modeToggle.find('.mt-mode-toggle__icon-scroll').show();
            $modeToggle.find('.mt-mode-toggle__label').text('Scroll mode');
            $stepNav.attr('aria-hidden', 'false');
            _buildStepOrder();
            _goToStep(0);
        }

        function _disableStepMode() {
            stepMode = false;
            $root.removeClass('is-step-mode');
            $modeToggle.attr('aria-pressed', 'false')
                       .attr('title', 'Switch to step-by-step mode');
            $modeToggle.find('.mt-mode-toggle__icon-step').show();
            $modeToggle.find('.mt-mode-toggle__icon-scroll').hide();
            $modeToggle.find('.mt-mode-toggle__label').text('Step mode');
            $stepNav.attr('aria-hidden', 'true');
            $root.find('.mt-section').removeClass('is-step-active');
        }

        /* ════════════════════════════════════════════════════
           PUBLIC API (mirrors NightPie for PHP onclick= compat)
           ════════════════════════════════════════════════════ */
        var instance = {

            init: function () {
                instance._initStage();
                instance._applyLayerOffsets();
                instance._initDefaultLayers();
                instance._initCardClicks();
                instance._initOrb();
                instance._initSectionNav();
                instance._initToppingSearch();
                instance._initModeToggle();
                instance._initCoverageModal();
                instance._initCollapsibleSections();
            },

            /* ── Locate / create the pizza stage ── */
            _initStage: function () {
                /* Ensure both hero and sidebar canvases have a stage wrapper */
                var canvases = [$heroCanvas, $sidebarCanvas];
                canvases.forEach(function ($canvas) {
                    if (!$canvas.length) { return; }
                    if (!$canvas.find('.np-pizza-stage').length) {
                        $canvas.empty().append(
                            $('<div class="np-pizza-stage-wrap"></div>').append(
                                $('<div class="np-pizza-stage"></div>')
                            )
                        );
                    }
                });
            },

            /* ── Apply layer offsets from data attr ── */
            _applyLayerOffsets: function () {
                _applyLayerOffsets();
            },

            /* ── Load default layers from data-* attrs on .np-pizza-stage-wrap ── */
            _initDefaultLayers: function () {
                var $stage = getHeroStage();
                if (!$stage.length) { return; }
                var $wrap  = $stage.closest('.np-pizza-stage-wrap');
                if (!$wrap.length) { return; }

                /* Every base type (drizzle / cut included) goes through the same
                   path so the default is in state (priced) and its card selected.
                   crust/sauce/cheese fall back to the first card when no default. */
                var applyBase = function (layerType, useFirstFallback) {
                    var url   = $wrap.attr('data-default-' + layerType) || '';
                    var slug  = $wrap.attr('data-default-' + layerType + '-slug') || '';
                    var $card = _findCard(layerType, slug, url);

                    if (!url && useFirstFallback) {
                        $card = $find('.mt-card[data-layer="' + layerType + '"]').first();
                        url   = $card.length ? ($card.attr('data-layer-img') || '') : '';
                    }
                    if (!url) { return; }

                    if ($card.length) { slug = $card.attr('data-slug') || slug; }
                    PizzaStack.setLayer($stage, 'layer-' + layerType, url, zMap[layerType] || 200, 'nl-' + layerType);
                    state[layerType] = {
                        slug: slug,
                        title: ($card.length && $card.attr('data-title')) || slug,
                        layerImg: url,
                        thumb: $card.length ? ($card.attr('data-thumb') || '') : ''
                    };
                    if (layerType === 'crust') { $heroCanvas.addClass('mt-has-crust'); }
                    if ($card.length) { _markCard($card, true); }
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
                            var $tc = _findCard('toppings', t.slug, t.layerImg);
                            PizzaStack.setLayer($stage, 'layer-topping-' + t.slug, t.layerImg, t.zindex || 400, 'nl-topping', t.coverage || 'whole');
                            state.toppings[t.slug] = {
                                slug: t.slug,
                                title: ($tc.length && $tc.attr('data-title')) || t.slug,
                                layerImg: t.layerImg,
                                zindex: t.zindex || 400, coverage: t.coverage || 'whole',
                                thumb: $tc.length ? ($tc.attr('data-thumb') || '') : ''
                            };
                            _markCard($tc, true);
                        });
                    }
                } catch (e) { /* malformed JSON — skip */ }

                _updateCounters();
                _updateToppingBadge();
                _renderChips();
                _syncMiniPizzas();
            },

            /* Base swap (crust / sauce / cheese / drizzle / cut) */
            swapBase: function (layerType, slug, title, layerImg, triggerEl) {
                var $card  = $(triggerEl).closest('.mt-card');
                var $stage = getHeroStage();
                var stateKey = (layerType === 'cut') ? 'cut' : layerType;
                var thumb  = $card.data('thumb') || '';

                var $section = $card.closest('.mt-section');
                _deselectAllInSection($section);

                state[stateKey] = { slug: slug, title: title, thumb: thumb, layerImg: layerImg };

                PizzaStack.setLayer($stage, 'layer-' + layerType, layerImg, zMap[layerType] || 200, 'mt-' + layerType);

                // Toggle dashed ring: hide when crust is selected
                if (layerType === 'crust') { $heroCanvas.addClass('mt-has-crust'); }

                _markCard($card, true);
                _renderChips();
                _syncMiniPizzas();
            },

            /* Remove a base layer */
            removeBase: function (layerType, slug, triggerEl) {
                /* Tray chips call this with a trigger that is not inside a card. */
                var $card  = _cardFor(layerType, slug, triggerEl);
                var $stage = getHeroStage();
                var stateKey = (layerType === 'cut') ? 'cut' : layerType;

                state[stateKey] = null;
                PizzaStack.removeLayer($stage, 'layer-' + layerType);

                // Toggle dashed ring: show when crust is removed
                if (layerType === 'crust') { $heroCanvas.removeClass('mt-has-crust'); }

                _markCard($card, false);
                _renderChips();
                _syncMiniPizzas();
            },

            /* Add a topping */
            addTopping: function (zindex, slug, layerImg, title, layerId, cssId, triggerEl) {
                var maxT = parseInt($root.data('max-toppings')) || 99;
                if (_toppingCount() >= maxT) {
                    var settings = window.pizzatierSettings || {};
                    var maxMsg = settings.textMaxToppings || ('Max ' + maxT + ' toppings reached.');
                    _showToast(maxMsg);
                    return;
                }
                var $card  = $(triggerEl).closest('.mt-card');
                var $stage = getHeroStage();
                var thumb  = $card.data('thumb') || '';

                state.toppings[slug] = { slug: slug, title: title, thumb: thumb, layerImg: layerImg, zindex: zindex, coverage: 'whole' };

                PizzaStack.setLayer($stage, 'layer-topping-' + slug, layerImg, zindex, 'mt-topping', 'whole');

                _markCard($card, true);

                _updateCounters();
                _updateToppingBadge();
                _renderChips();
                _syncMiniPizzas();

                /* Auto-open coverage modal after adding */
                _openCoverageModal(slug, title, thumb);
            },

            /* Remove a topping */
            removeTopping: function (layerId, slug, triggerEl) {
                /* Tray chips call this with a trigger that is not inside a card. */
                var $card  = _cardFor('toppings', slug, triggerEl);
                var $stage = getHeroStage();

                delete state.toppings[slug];
                PizzaStack.removeLayer($stage, 'layer-topping-' + slug);

                _markCard($card, false);

                _updateCounters();
                _updateToppingBadge();
                _renderChips();
                _syncMiniPizzas();
            },

            /* Open coverage modal (also callable from PHP onclick) */
            openCoverageModal: function (slug, title, thumb) {
                _openCoverageModal(slug, title, thumb);
            },

            /* Set coverage for a topping */
            setCoverage: function (slug, fraction) {
                if (!state.toppings[slug]) { return; }
                if (['whole','half-left','half-right','quarter-top-left','quarter-top-right','quarter-bottom-left','quarter-bottom-right'].indexOf(fraction) === -1) { return; }
                state.toppings[slug].coverage = fraction;

                var $stage = getHeroStage();
                var $layer = PizzaStack.findLayer
                    ? PizzaStack.findLayer($stage, 'layer-topping-' + slug)
                    : $stage.find('[data-layer-id="layer-topping-' + slug + '"]');
                $layer.find('img').css('clip-path', PizzaStack.getCoverageClip(fraction));

                $covModal.find('.mt-cov-modal__btn').removeClass('is-active')
                         .filter('[data-fraction="' + fraction + '"]').addClass('is-active');

                _renderChips();
                _syncMiniPizzas();
            },

            /* Reset everything */
            resetAll: function () {
                state = { crust: null, sauce: null, cheese: null, drizzle: null, cut: null, toppings: {} };
                $root.find('.mt-card').each(function () { _markCard($(this), false); });
                _closeCoverageModal();
                _updateCounters();
                _updateToppingBadge();
                _renderChips();
                /* Clear both hero and sidebar stages */
                [$heroCanvas, $sidebarCanvas].forEach(function ($c) {
                    if ($c.length) { $c.find('.np-pizza-stage .np-layer-div').stop(true).remove(); }
                });
                $heroCanvas.removeClass('mt-has-crust');
                mirrorStage($orbPizza,   PizzaStack.getStage($heroCanvas));
                mirrorStage($trayThumb,  PizzaStack.getStage($heroCanvas));
            },

            /* State read / write (for PizzaTierAPI compat) */
            getState: function () { return $.extend(true, {}, state); },
            /* Accepts the shape frontend-builder sends for default layers and
               cart-edit restore: { crust:{slug}, …, toppings:{slug:{slug,coverage}} }.
               layerImg / title / thumb are resolved from the matching card when
               missing, and coverage is applied. (No global ClearPizza() here —
               that wiped every builder on the page.) */
            setState: function (newState) {
                instance.resetAll();
                var $stage = getHeroStage();

                if (newState && typeof newState === 'object') {
                    ['crust', 'sauce', 'cheese', 'drizzle', 'cut'].forEach(function (lt) {
                        var sel = newState[lt];
                        if (!sel) { return; }
                        if (typeof sel !== 'object') { sel = { slug: String(sel) }; }
                        var $card = _findCard(lt, sel.slug, sel.layerImg);
                        var img   = sel.layerImg || ($card.length ? ($card.attr('data-layer-img') || '') : '');
                        if (!img) { return; }
                        var slug  = sel.slug || ($card.length ? ($card.attr('data-slug') || '') : '');
                        state[lt] = {
                            slug:     slug,
                            title:    sel.title || ($card.length && $card.attr('data-title')) || slug,
                            thumb:    sel.thumb || ($card.length ? ($card.attr('data-thumb') || '') : ''),
                            layerImg: img
                        };
                        PizzaStack.setLayer($stage, 'layer-' + lt, img, zMap[lt], 'mt-' + lt);
                        if (lt === 'crust') { $heroCanvas.addClass('mt-has-crust'); }
                        _markCard($card, true);
                    });

                    if (newState.toppings && typeof newState.toppings === 'object') {
                        $.each(newState.toppings, function (key, t) {
                            if (!t || typeof t !== 'object') { t = { slug: String(t || key) }; }
                            var slug = t.slug || String(key);
                            var $card = _findCard('toppings', slug, t.layerImg);
                            var img   = t.layerImg || ($card.length ? ($card.attr('data-layer-img') || '') : '');
                            if (!img) { return; }
                            var cov   = ['whole','half-left','half-right','quarter-top-left','quarter-top-right','quarter-bottom-left','quarter-bottom-right'].indexOf(t.coverage) !== -1 ? t.coverage : 'whole';
                            var z     = t.zindex || ($card.length ? parseInt($card.attr('data-zindex'), 10) : 0) || 400;
                            state.toppings[slug] = {
                                slug:     slug,
                                title:    t.title || ($card.length && $card.attr('data-title')) || slug,
                                thumb:    t.thumb || ($card.length ? ($card.attr('data-thumb') || '') : ''),
                                layerImg: img,
                                zindex:   z,
                                coverage: cov
                            };
                            PizzaStack.setLayer($stage, 'layer-topping-' + slug, img, z, 'mt-topping', cov);
                            $stage.find('[data-layer-id="layer-topping-' + slug + '"] img')
                                  .css('clip-path', PizzaStack.getCoverageClip(cov));
                            _markCard($card, true);
                        });
                    }
                }
                _updateCounters();
                _updateToppingBadge();
                _renderChips();
                _syncMiniPizzas();
            },

            /* ── Private: IntersectionObserver for orb ── */
            _initOrb: function () {
                if (!window.IntersectionObserver) { return; }
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            $orb.removeClass('is-visible').attr('aria-hidden', 'true');
                        } else {
                            $orb.addClass('is-visible').attr('aria-hidden', 'false');
                            _syncMiniPizzas();
                        }
                    });
                }, { threshold: 0.2 });
                if ($hero.length) { observer.observe($hero[0]); }
            },

            /* ── Private: scroll-spy for section nav ── */
            _initSectionNav: function () {
                var $sections = $root.find('.mt-section[data-section]');
                if (!$sections.length || !window.IntersectionObserver) { return; }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var tab = $(entry.target).data('section');
                        var $navItem = $sectionNav.find('[data-section="' + tab + '"]');
                        if (entry.isIntersecting) {
                            $sectionNav.find('.mt-section-nav__item').removeClass('is-active');
                            $navItem.addClass('is-active');
                        }
                    });
                }, { threshold: 0.35, rootMargin: '-' + ($sectionNav.outerHeight() || 52) + 'px 0px 0px 0px' });

                $sections.each(function () { observer.observe(this); });

                $sectionNav.find('.mt-section-nav__item').on('click', function (e) {
                    e.preventDefault();
                    var sectionId = $(this).attr('href');
                    var $target   = $(sectionId);
                    if ($target.length) {
                        var navH = $sectionNav.outerHeight() || 52;
                        var top  = $target.offset().top - navH - 8;
                        $('html, body').animate({ scrollTop: top }, 400);
                    }
                });
            },

            /* ── Private: topping search filter ── */
            _initToppingSearch: function () {
                if (!$toppingSearch.length) { return; }
                $toppingSearch.on('input', function () {
                    var q = $(this).val().toLowerCase().trim();
                    $root.find('#' + instanceId + '-section-toppings .mt-card').each(function () {
                        var name = ($(this).data('title') || '').toLowerCase();
                        $(this).toggleClass('is-hidden', !!(q && name.indexOf(q) === -1));
                    });
                });
            },

            /* ── Private: step mode toggle ── */
            _initModeToggle: function () {
                if (!$modeToggle.length) { return; }
                $modeToggle.on('click', function () {
                    if (stepMode) { _disableStepMode(); } else { _enableStepMode(); }
                });
                $stepPrev.on('click', function () { _goToStep(stepIndex - 1); });
                $stepNext.on('click', function () { _goToStep(stepIndex + 1); });
            },

            /* ── Private: card click-to-toggle ── */
            _initCardClicks: function () {
                /* Delegate a single click handler on the root — no onclick= attrs needed */
                $root.on('click.mt-card', '.mt-card', function (e) {
                    /* Ignore clicks on the card's own controls (add / remove /
                       coverage buttons carry their own onclick handlers — acting
                       here too toggled the topping on and straight back off). */
                    if ($(e.target).closest('button, a, input, select, label, .mt-card__btn, .mt-card__btn--coverage').length) { return; }

                    var $card      = $(this);
                    var layerType  = $card.data('layer');
                    var slug       = $card.data('slug')      || '';
                    var title      = $card.data('title')     || slug;
                    var layerImg   = $card.data('layer-img') || '';
                    var isSelected = $card.hasClass('is-selected');

                    if (layerType === 'toppings') {
                        if (isSelected) {
                            instance.removeTopping('pizzatier-topping-' + slug, slug, this);
                        } else {
                            var zindex = parseInt($card.data('zindex')) || 400;
                            instance.addTopping(zindex, slug, layerImg, title, 'pizzatier-topping-' + slug, 'pizzatier-topping-' + slug, this);
                        }
                    } else {
                        if (isSelected) {
                            instance.removeBase(layerType, slug, this);
                        } else {
                            instance.swapBase(layerType, slug, title, layerImg, this);
                        }
                    }
                });
            },

            /* ── Private: collapsible sections (scroll mode only) ── */
            _initCollapsibleSections: function () {
                // Wrap each section's non-header content in .mt-section__body
                $find('.mt-section').each(function () {
                    var $sec = $(this);
                    // Add collapse icon to header if not already present
                    var $hdr = $sec.find('.mt-section__header').first();
                    if ($hdr.length && !$hdr.find('.mt-section__collapse-icon').length) {
                        $hdr.append('<span class="mt-section__collapse-icon" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>');
                    }
                    // Wrap non-header children in .mt-section__body if not already done
                    if (!$sec.find('.mt-section__body').length) {
                        var $nonHeader = $sec.children().not('.mt-section__header');
                        if ($nonHeader.length) {
                            $nonHeader.wrapAll('<div class="mt-section__body"></div>');
                        }
                    }
                });

                // Bind click to toggle collapse (scroll mode only)
                $root.on('click.mt-collapse', '.mt-section__header', function (e) {
                    // Ignore clicks on inner buttons/links
                    if ($(e.target).closest('button, a, input, select').length) { return; }
                    // Don't collapse in step mode
                    if ($root.hasClass('is-step-mode')) { return; }
                    var $sec = $(this).closest('.mt-section');
                    $sec.toggleClass('is-collapsed');
                });
            },

            /* ── Private: coverage modal ── */
            _initCoverageModal: function () {
                if (!$covModal.length) { return; }

                /* Coverage option buttons */
                $covModal.find('.mt-cov-modal__btn').on('click', function () {
                    var fraction = $(this).data('fraction');
                    if (covModalSlug && fraction) {
                        instance.setCoverage(covModalSlug, fraction);
                    }
                });

                /* Done button */
                $covModal.find('.mt-cov-modal__done').on('click', function () {
                    _closeCoverageModal();
                });

                /* Close button */
                $covModal.find('.mt-cov-modal__close').on('click', function () {
                    _closeCoverageModal();
                });

                /* Backdrop click closes */
                $covModal.find('.mt-cov-modal__backdrop').on('click', function () {
                    _closeCoverageModal();
                });

                /* Escape key closes */
                $(document).on('keydown.mt-modal-' + instanceId, function (e) {
                    if (e.key === 'Escape' && $covModal.hasClass('is-open')) {
                        _closeCoverageModal();
                    }
                });
            }
        };

        return instance;
    }

    /* ════════════════════════════════════════════════════════
       GLOBAL MT FACTORY
       ════════════════════════════════════════════════════════ */
    var MT = {
        _instances: {},

        createInstance: function (instanceId) {
            var inst = createMTInstance(instanceId);
            MT._instances[instanceId] = inst;
            return inst;
        },

        getInstance: function (instanceId) {
            return MT._instances[instanceId] || null;
        }
    };

    /* ════════════════════════════════════════════════════════
       AUTO-INIT all .mt-root elements
       ════════════════════════════════════════════════════════ */
    $(document).ready(function () {
        $('.mt-root').each(function () {
            var instanceId = $(this).data('instance') || $(this).attr('id');
            if (!instanceId) { return; }

            var mtVar = $(this).data('mt-var') || ('MT_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));
            var inst  = MT.createInstance(instanceId);
            inst.init();

            window[mtVar] = inst;
        });

        /* Backward compat single-instance */
        var allIds = Object.keys(MT._instances);
        if (allIds.length === 1) {
            window.MT = MT._instances[allIds[0]];
        } else {
            window.MT = MT;
        }
    });

    window.PizzaTierMT = MT;

    /* PizzaTierAPI — standard surface consumed by PizzaTier. setState is what
       frontend-builder's default layers and cart-edit restore call. */
    MT.API = {
        getState: function (instanceId) {
            var inst = MT.getInstance(instanceId);
            return inst ? inst.getState() : null;
        },
        setState: function (instanceId, newState) {
            var inst = MT.getInstance(instanceId);
            if (inst && newState && typeof newState === 'object') { inst.setState(newState); }
        },
        getAllInstances: function () {
            return Object.keys(MT._instances);
        }
    };
    if (window.PizzaTierAPI && typeof window.PizzaTierAPI.register === 'function') {
        window.PizzaTierAPI.register(MT.API);
    } else {
        window.PizzaTierAPI = window.PizzaTierAPI || MT.API;
    }

    /* ════════════════════════════════════════════════════════
       LEGACY GLOBALS (D62 compat — same as NightPie)
       ════════════════════════════════════════════════════════ */
    window.SwapBasePizzaTier = window.SwapBasePizzaTier || function (divId, title, imgSrc) {
        var layerType = divId.replace('pizzatier-base-layer-', '');
        var $stage = $('.np-pizza-stage').first();
        if ($stage.length) {
            var zMap = { crust: 100, sauce: 150, cheese: 200 };
            PizzaStack.setLayer($stage, 'layer-' + layerType, imgSrc, zMap[layerType] || 200);
        }
    };
    window.AddPizzaTier = window.AddPizzaTier || function (zindex, slug, imgSrc) {
        var $stage = $('.np-pizza-stage').first();
        if ($stage.length) { PizzaStack.setLayer($stage, 'layer-topping-' + slug, imgSrc, zindex, 'mt-topping', 'whole'); }
    };
    window.RemovePizzaTier = window.RemovePizzaTier || function (layerId, title, slug) {
        var $stage = $('.np-pizza-stage').first();
        if ($stage.length) { PizzaStack.removeLayer($stage, 'layer-topping-' + slug); }
    };
    window.ClearPizza = window.ClearPizza || function () {
        $('.np-pizza-stage').each(function () {
            $(this).find('.np-layer-div').fadeOut(300, function () { $(this).remove(); });
        });
    };

})(jQuery);
