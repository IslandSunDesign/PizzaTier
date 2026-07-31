/* ══════════════════════════════════════════════════════════════════════
   PIZZATIER — Colorbox Template — custom.js
   
   Architecture:
   - CB.createInstance(instanceId) → scoped builder instance
   - Each instance maintains its own state and updates its own pizza stack
   - Pizza stack = absolutely-positioned <div> layers inside .cb-pizza-stage
   - window.CB_{instanceId} exposed for global access (legacy + Pro hooks)
   - window.PizzaTierAPI = public API for external plugins
   ══════════════════════════════════════════════════════════════════════ */

(function ($) {
    'use strict';

    /* ════════════════════════════════════════════════════════════════
       PIZZA STACK RENDERER
       Maintains a visual layer stack inside .cb-pizza-stage.
       Each layer is a <div> with an <img> absolutely filling it.
       Layers are identified by data-layer-id attributes.
       ════════════════════════════════════════════════════════════════ */

    var PizzaStack = {

        /**
         * Get (or lazily create) the pizza stage inside a root element.
         * The stage is a square relative-positioned container that
         * all layer divs are absolutely positioned inside.
         */
        getStage: function ($root) {
            var $stage = $root.find('.cb-pizza-stage');
            if (!$stage.length) {
                // Wrap any existing initial HTML inside a stage
                var $canvas = $root.find('.cb-pizza-sticky__canvas, .cb-pizza-stage-wrap').first();
                if (!$canvas.length) { return $(); }
                $stage = $('<div class="cb-pizza-stage"></div>');
                $canvas.append($stage);
            }
            return $stage;
        },

        /**
         * Set or update a named layer in the stack.
         * @param {jQuery}  $stage    The pizza stage element
         * @param {string}  layerId   Unique ID for this layer (e.g. "layer-crust")
         * @param {string}  src       Image URL — empty string removes the layer
         * @param {number}  zIndex    CSS z-index
         * @param {string}  [cls]     Optional extra CSS class
         * @param {string}  [coverage] 'whole'|'half-left'|'half-right'|'quarter-*'
         */
        /**
         * Layer animation engine.
         * Reads the animation mode from the closest [data-layer-anim] ancestor
         * (set on .cb-root by PHP from shortcode/global setting).
         *
         * Modes: fade | scale-in | slide-up | flip-in | drop-in | instant
         */
        _animateLayerIn: function ($img, $stage, mode) {
            var $root = $stage.closest('[data-layer-anim-speed]');
            var dur = $root.length ? (parseInt($root.data('layer-anim-speed'), 10) || 320) : 320;

            /* Reset any in-progress animation */
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
                                opacity: 1,
                                transform: 'scale(1)'
                            });
                        });
                        break;

                    case 'slide-up':
                        $img.css({ transform: 'translateY(22%)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)',
                                opacity: 1,
                                transform: 'translateY(0)'
                            });
                        });
                        break;

                    case 'flip-in':
                        $img.css({ transform: 'rotateY(90deg) scale(0.8)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + (dur + 80) + 'ms ease, transform ' + (dur + 80) + 'ms cubic-bezier(0.34,1.2,0.64,1)',
                                opacity: 1,
                                transform: 'rotateY(0deg) scale(1)'
                            });
                        });
                        break;

                    case 'drop-in':
                        $img.css({ transform: 'translateY(-30%) scale(1.12)', opacity: 0 });
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity ' + dur + 'ms ease, transform ' + dur + 'ms cubic-bezier(0.22,1,0.36,1)',
                                opacity: 1,
                                transform: 'translateY(0) scale(1)'
                            });
                        });
                        break;

                    default: /* 'fade' — original behaviour */
                        requestAnimationFrame(function () {
                            $img.css({
                                transition: 'opacity 0.3s ease',
                                opacity: 1
                            });
                        });
                        break;
                }
            };

            /* If image is already loaded, animate immediately; else wait for load */
            if ($img[0].complete && $img[0].naturalWidth) {
                applyAnim();
            } else {
                $img.on('load.pzanim', function () {
                    $(this).off('load.pzanim');
                    applyAnim();
                });
                /* Fallback: also fire on rAF so even cached images animate */
                requestAnimationFrame(applyAnim);
            }
        },

        setLayer: function ($stage, layerId, src, zIndex, cls, coverage, animMode) {
            if (!$stage.length) { return; }

            /* Resolve animation mode: explicit arg → data attr on root → 'fade' */
            if (!animMode) {
                var $root = $stage.closest('[data-layer-anim]');
                animMode = $root.length ? $root.data('layer-anim') : 'fade';
            }

            var $existing = $stage.find('[data-layer-id="' + layerId + '"]');

            if (!src) {
                $existing.fadeOut(200, function () { $(this).remove(); });
                return;
            }

            var clipStyle = PizzaStack.getCoverageClip(coverage);

            if ($existing.length) {
                /* Update existing layer in-place */
                $existing.css('z-index', zIndex);
                $existing.find('img').attr('src', src).css('clip-path', clipStyle);
            } else {
                /* Create new layer div */
                var $layer = $('<div class="cb-layer-div" data-layer-id="' + layerId + '"></div>').css({
                    position: 'absolute',
                    inset: 0,
                    'z-index': zIndex,
                    'pointer-events': 'none',
                    overflow: 'hidden'
                });
                if (cls) { $layer.addClass(cls); }

                var $img = $('<img loading="lazy" decoding="async">').css({
                    position: 'absolute',
                    inset: 0,
                    width: '100%',
                    height: '100%',
                    'object-fit': 'contain',
                    'clip-path': clipStyle,
                    opacity: 0
                }).attr('src', src).attr('alt', layerId);

                $layer.append($img);
                $stage.append($layer);

                /* Mark canvas as having layers (hides the dashed placeholder ring) */
                $stage.closest('.cb-pizza-sticky__canvas').addClass('cb-has-layers');

                /* Run the chosen animation */
                PizzaStack._animateLayerIn($img, $stage, animMode);
            }
        },

        removeLayer: function ($stage, layerId) {
            PizzaStack.setLayer($stage, layerId, '', 0);
        },

        /**
         * Apply pizza shape to a canvas element.
         * shape: 'round' | 'square' | 'rectangle' | 'custom'
         * customRatio: CSS aspect-ratio value e.g. '4/3'
         * customRadius: CSS border-radius value e.g. '12px'
         */
        applyShape: function ($canvas, shape, customRatio, customRadius) {
            switch (shape) {
                case 'square':
                    $canvas.css({
                        'border-radius': '8px',
                        'aspect-ratio':  '1 / 1'
                    });
                    break;
                case 'rectangle':
                    $canvas.css({
                        'border-radius': '12px',
                        'aspect-ratio':  customRatio || '4 / 3'
                    });
                    break;
                case 'custom':
                    $canvas.css({
                        'border-radius': customRadius || '0px',
                        'aspect-ratio':  customRatio  || '1 / 1'
                    });
                    break;
                default: /* 'round' */
                    $canvas.css({
                        'border-radius': '50%',
                        'aspect-ratio':  '1 / 1'
                    });
                    break;
            }
        },

        /** Convert coverage name to CSS clip-path */
        getCoverageClip: function (coverage) {
            switch (coverage) {
                case 'half-left':
                    return 'polygon(0 0, 50% 0, 50% 100%, 0 100%)';
                case 'half-right':
                    return 'polygon(50% 0, 100% 0, 100% 100%, 50% 100%)';
                case 'quarter-top-left':
                    return 'polygon(0 0, 50% 0, 50% 50%, 0 50%)';
                case 'quarter-top-right':
                    return 'polygon(50% 0, 100% 0, 100% 50%, 50% 50%)';
                case 'quarter-bottom-left':
                    return 'polygon(0 50%, 50% 50%, 50% 100%, 0 100%)';
                case 'quarter-bottom-right':
                    return 'polygon(50% 50%, 100% 50%, 100% 100%, 50% 100%)';
                default: // 'whole' or undefined
                    return 'none';
            }
        }
    };

    /* ════════════════════════════════════════════════════════════════
       COVERAGE LABELS / SWATCH CLASSES
       Mirror the PHP maps in pztp-containers-menu.php so the chip can be
       updated client-side. CSS swatches: --whole, --left, --right, --q1..q4.
       ════════════════════════════════════════════════════════════════ */
    var _COV_ICON = {
        'whole': 'whole', 'half-left': 'left', 'half-right': 'right',
        'quarter-top-left': 'q1', 'quarter-top-right': 'q2',
        'quarter-bottom-left': 'q3', 'quarter-bottom-right': 'q4'
    };
    var _COV_LABEL = {
        'whole': 'Whole', 'half-left': 'Left Half', 'half-right': 'Right Half',
        'quarter-top-left': 'Top Left', 'quarter-top-right': 'Top Right',
        'quarter-bottom-left': 'Bottom Left', 'quarter-bottom-right': 'Bottom Right'
    };
    function CB_COV_ICON(f)  { return _COV_ICON[f]  || 'whole'; }
    function CB_COV_LABEL(f) { return _COV_LABEL[f] || 'Whole'; }

    /* ════════════════════════════════════════════════════════════════
       INSTANCE FACTORY
       ════════════════════════════════════════════════════════════════ */

    function createCBInstance(instanceId) {

        var $root      = $('#' + instanceId + ', [data-instance="' + instanceId + '"]').first();
        var maxTopping = parseInt($root.data('max-toppings')) || 99;
        /* Animation mode read once at instance creation — data attr set by PHP */
        var animMode   = $root.data('layer-anim') || 'fade';

        /* ── Per-instance state ── */
        var state = {
            crust:    null,   // { slug, title, thumb, layerImg }
            sauce:    null,
            cheese:   null,
            drizzle:  null,
            cut:      null,
            toppings: {}      // slug → { slug, title, thumb, layerImg, coverage, zindex }
        };

        /* ── Lazy stage reference ── */
        var _$stage = null;

        /* ── Coverage modal: slug currently being edited ── */
        var activeCoverageSlug = null;
        function getStage() {
            if (!_$stage || !_$stage.length) {
                _$stage = PizzaStack.getStage($root);
            }
            return _$stage;
        }

        /* ── Scoped helpers ── */
        var $find = function (sel) { return $root.find(sel); };

        var instance = {

            /* ── Initialise ── */
            init: function () {
                var settings = window.pizzatierSettings || {};

                // Debug mode
                if ( settings.debugMode === 'yes' ) {
                    window.console && console.info('[PizzaTier] Builder init:', instanceId, settings);
                }

                this._initStage();
                this._bindTabs();
                this._bindMobileToggle();
                this._bindCoverageModal();
                this._applyStartOverLabel();
                this.goTab('crust');
                this._updateCounter();
                this._initDefaultLayers();

                // Step-by-step mode: hide all tabs except the first
                if ( settings.stepByStep === 'yes' ) {
                    this._initStepMode();
                }
            },

            /* ── Create / locate the pizza stage ── */
            _initStage: function () {
                var $canvas = $find('#' + instanceId + '-canvas');
                if (!$canvas.length) { return; }

                // PHP now renders .cb-pizza-stage-wrap > .cb-pizza-stage (empty)
                // If for some reason it's missing, create it.
                if (!$canvas.find('.cb-pizza-stage').length) {
                    $canvas.empty().append(
                        $('<div class="cb-pizza-stage-wrap"></div>').append(
                            $('<div class="cb-pizza-stage"></div>')
                        )
                    );
                }
                _$stage = $canvas.find('.cb-pizza-stage');

                /* ── Apply pizza shape from data-pizza-shape on root ── */
                var shape = $root.data('pizza-shape') || 'round';
                var customRatio  = $root.data('pizza-aspect')  || '1';
                var customRadius = $root.data('pizza-radius')  || '0px';
                PizzaStack.applyShape($canvas, shape, customRatio, customRadius);
            },

            /* ── Load default layers from data-* attrs on the stage-wrap ── */
            _initDefaultLayers: function () {
                var $wrap = getStage().closest('.cb-pizza-stage-wrap');

                // Helper: apply a base layer if URL exists, else fall back to first card
                var applyBase = function (layerType, urlAttr, slugAttr, zIndex, cssClass) {
                    var url  = $wrap.data(urlAttr)  || '';
                    var slug = $wrap.data(slugAttr) || '';

                    if (url) {
                        PizzaStack.setLayer(getStage(), 'layer-' + layerType, url, zIndex, cssClass);
                        state[layerType] = { slug: slug, title: slug, layerImg: url, thumb: '' };
                        // Highlight the matching card as selected
                        if (slug) {
                            $find('.cb-card[data-layer="' + layerType + '"][data-slug="' + slug + '"]')
                                .addClass('cb-card--selected');
                        }
                    } else {
                        // No default set — auto-apply the first available card silently
                        var $first = $find('.cb-card[data-layer="' + layerType + '"]').first();
                        if ($first.length) {
                            var fSlug = $first.data('slug') || '';
                            var fImg  = $first.data('layer-img') || '';
                            var fThumb= $first.data('thumb') || '';
                            if (fImg) {
                                PizzaStack.setLayer(getStage(), 'layer-' + layerType, fImg, zIndex, cssClass);
                                state[layerType] = { slug: fSlug, title: fSlug, layerImg: fImg, thumb: fThumb };
                                $first.addClass('cb-card--selected');
                            }
                        }
                    }
                };

                // Apply base layers in order
                applyBase('crust',  'default-crust',  'default-crust-slug',  100, 'nl-crust');
                applyBase('sauce',  'default-sauce',  'default-sauce-slug',  200, 'nl-sauce');
                applyBase('cheese', 'default-cheese', 'default-cheese-slug', 300, 'nl-cheese');

                // Default drizzle / cut
                var drUrl = $wrap.data('default-drizzle') || '';
                if (drUrl) {
                    PizzaStack.setLayer(getStage(), 'layer-drizzle', drUrl, 900, 'nl-drizzle');
                }
                var cuUrl = $wrap.data('default-cut') || '';
                if (cuUrl) {
                    PizzaStack.setLayer(getStage(), 'layer-cut', cuUrl, 950, 'nl-cut');
                }

                // Default toppings (from shortcode attr, JSON encoded by PHP)
                try {
                    var defaultTops = JSON.parse($wrap.attr('data-default-toppings') || '[]');
                    if (Array.isArray(defaultTops)) {
                        defaultTops.forEach(function (t) {
                            PizzaStack.setLayer(getStage(), 'layer-topping-' + t.slug, t.layerImg, t.zindex || 400, 'nl-topping', t.coverage || 'whole');
                            state.toppings[t.slug] = { slug: t.slug, title: t.slug, layerImg: t.layerImg, zindex: t.zindex || 400, coverage: t.coverage || 'whole' };
                        });
                        _$root.find('#' + instanceId + '-count').text(Object.keys(state.toppings).length);
                    }
                } catch (e) { /* malformed JSON — ignore */ }
            },

            /* ── Tab switching ── */
            goTab: function (tabName) {
                $find('.cb-tab').each(function () {
                    var t = $(this).data('tab');
                    $(this)
                        .toggleClass('active', t === tabName)
                        .attr('aria-selected', t === tabName ? 'true' : 'false');
                });

                $find('.cb-panel').each(function () {
                    var p = this.id.replace(instanceId + '-panel-', '');
                    $(this).toggleClass('active', p === tabName);
                });

                var order = ['crust','sauce','cheese','toppings','drizzle','slicing','yourpizza'];
                var idx   = order.indexOf(tabName);
                $find('.cb-progress__dot').each(function () {
                    var s  = $(this).data('step');
                    var si = order.indexOf(s);
                    $(this)
                        .toggleClass('active', s === tabName)
                        .toggleClass('done',   si < idx);
                });

                if (tabName === 'yourpizza') { instance._renderSummary(); }

                // Scroll tabnav
                var $activeTab = $find('.cb-tab[data-tab="' + tabName + '"]');
                if ($activeTab.length) {
                    var nav = $find('.cb-tabnav')[0];
                    if (nav) { nav.scrollTo({ left: $activeTab[0].offsetLeft - 20, behavior: 'smooth' }); }
                }
            },

            /* ── Swap exclusive base layer (crust/sauce/cheese/drizzle/cut) ── */
            swapBase: function (layerType, slug, title, layerImg, triggerEl) {
                // Deselect all cards of this type
                $find('.cb-card[data-layer="' + layerType + '"]').each(function () {
                    $(this).removeClass('cb-card--selected');
                    $(this).find('.cb-btn--add').show();
                    $(this).find('.cb-btn--remove').hide();
                });

                // Select clicked card
                var $card = $(triggerEl).closest('.cb-card');
                var thumb = $card.data('thumb') || '';
                $card.addClass('cb-card--selected');
                $card.find('.cb-btn--add').hide();
                $card.find('.cb-btn--remove').show();

                // Update state
                state[layerType] = { slug: slug, title: title, thumb: thumb, layerImg: layerImg };

                // ── Update pizza stack ──
                var zMap = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };
                var zIndex = zMap[layerType] || 500;
                PizzaStack.setLayer(getStage(), 'layer-' + layerType, layerImg, zIndex, 'nl-' + layerType);

                // Mark tab done
                var tabForLayer = (layerType === 'cut') ? 'slicing' : layerType;
                $find('.cb-tab[data-tab="' + tabForLayer + '"]').addClass('cb-tab--done');

                instance._flyTo($card.find('.cb-card__thumb'));
                instance._updateSummaryRow(layerType);
            },

            /* ── Remove exclusive base layer ── */
            removeBase: function (layerType, slug, triggerEl) {
                var $card = $(triggerEl).closest('.cb-card');
                $card.removeClass('cb-card--selected');
                $card.find('.cb-btn--add').show();
                $card.find('.cb-btn--remove').hide();

                state[layerType] = null;

                // Remove from pizza stack
                PizzaStack.removeLayer(getStage(), 'layer-' + layerType);

                instance._updateSummaryRow(layerType);
            },

            /* ── Add topping ── */
            addTopping: function (zindex, slug, layerImg, title, cssId, menuId, triggerEl) {
                var settings = window.pizzatierSettings || {};
                var currentCount = Object.keys(state.toppings).length;
                if (currentCount >= maxTopping) {
                    var maxMsg = settings.textMaxToppings || ('Maximum ' + maxTopping + ' toppings reached!');
                    instance._showToast(maxMsg);
                    return;
                }

                var $card = $(triggerEl).closest('.cb-card');
                var thumb = $card.data('thumb') || '';
                $card.addClass('cb-card--selected');
                $card.find('.cb-btn--add').hide();
                $card.find('.cb-btn--remove').show();
                $card.find('.cb-coverage').show();
                instance._updateCoverageChip(slug, 'whole');

                state.toppings[slug] = {
                    slug: slug, title: title, thumb: thumb,
                    layerImg: layerImg, coverage: 'whole', zindex: zindex
                };

                // ── Add to pizza stack ──
                PizzaStack.setLayer(getStage(), 'layer-topping-' + slug, layerImg, zindex, 'nl-topping', 'whole');

                $find('.cb-tab[data-tab="toppings"]').addClass('cb-tab--done');
                instance._flyTo($card.find('.cb-card__thumb'));
                instance._updateCounter();
                instance._updateSummaryRow('toppings');

                // Toast: "added" message
                var settings = window.pizzatierSettings || {};
                var addedMsg = settings.textAdded || '';
                if ( addedMsg ) { instance._showToast(addedMsg.replace('{item}', title)); }

                // Count badge
                if ( settings.toppingShowBadge === 'yes' ) { instance._updateBadge($card, 1); }

                // Debug
                if ( settings.debugMode === 'yes' ) { window.console && console.log('[PizzaTier] addTopping:', slug, state.toppings); }
            },

            /* ── Remove topping ── */
            removeTopping: function (layerId, slug, triggerEl) {
                var $card = $(triggerEl).closest('.cb-card');
                $card.removeClass('cb-card--selected');
                $card.find('.cb-btn--add').show();
                $card.find('.cb-btn--remove').hide();
                $card.find('.cb-coverage').hide();
                instance._updateCoverageChip(slug, 'whole');

                delete state.toppings[slug];

                // Remove from pizza stack
                PizzaStack.removeLayer(getStage(), 'layer-topping-' + slug);

                instance._updateCounter();
                instance._updateSummaryRow('toppings');

                // Toast: "removed" message
                var settings = window.pizzatierSettings || {};
                var removedMsg = settings.textRemoved || '';
                if ( removedMsg ) { instance._showToast(removedMsg); }

                // Clear badge
                if ( settings.toppingShowBadge === 'yes' ) { instance._updateBadge($card, 0); }

                // Debug
                if ( settings.debugMode === 'yes' ) { window.console && console.log('[PizzaTier] removeTopping:', slug); }
            },

            /* ── Close the coverage modal on Escape ── */
            _bindCoverageModal: function () {
                $(document).on('keydown.cbcov-' + instanceId, function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        if ($find('.cb-cov-modal').hasClass('cb-cov-modal--open')) {
                            instance.closeCoverage();
                        }
                    }
                });
            },

            /* ── Native Add to Cart bar ──────────────────────────────────
               (removed) The base plugin's Add to Cart / checkout bar is
               provided by PizzaTier via the pizzatier_builder_action_bar
               hook in pztp-containers-menu.php, so the template renders no
               native bar of its own. ── */

            /* ── Set coverage (clip-path on topping layer) ── */
            setCoverage: function (slug, fraction, triggerEl) {
                if (!state.toppings[slug]) { return; }
                state.toppings[slug].coverage = fraction;

                // Update clip-path on the pizza stack layer
                var $layer = getStage().find('[data-layer-id="layer-topping-' + slug + '"]');
                if ($layer.length) {
                    $layer.find('img').css('clip-path', PizzaStack.getCoverageClip(fraction));
                }

                instance._updateCoverageChip(slug, fraction);
                instance._updateSummaryRow('toppings');
            },

            /* ── Coverage chip helpers ── */
            _updateCoverageChip: function (slug, fraction) {
                var $chip = $find('.cb-card--topping[data-slug="' + slug + '"] .cb-coverage__current');
                if (!$chip.length) { return; }
                $chip.attr('data-fraction', fraction);
                $chip.find('.cb-cov-ico')
                    .attr('class', 'cb-cov-ico cb-cov-ico--' + CB_COV_ICON(fraction));
                $chip.find('.cb-coverage__current-label').text(CB_COV_LABEL(fraction));
            },

            /* ── Coverage modal: open / close / choose ── */
            openCoverage: function (slug) {
                if (!state.toppings[slug]) { return; }
                activeCoverageSlug = slug;
                var current = state.toppings[slug].coverage || 'whole';

                var $modal = $find('.cb-cov-modal');
                if (!$modal.length) { return; }
                // Mark the current option active
                $modal.find('.cb-cov-opt').removeClass('active').each(function () {
                    if ($(this).data('fraction') === current) { $(this).addClass('active'); }
                });
                $modal.addClass('cb-cov-modal--open').attr('aria-hidden', 'false');
            },

            closeCoverage: function () {
                activeCoverageSlug = null;
                $find('.cb-cov-modal').removeClass('cb-cov-modal--open').attr('aria-hidden', 'true');
            },

            chooseCoverage: function (fraction) {
                if (activeCoverageSlug) {
                    instance.setCoverage(activeCoverageSlug, fraction);
                }
                instance.closeCoverage();
            },

            /* ── Reset all layers ── */
            resetAll: function () {
                state = { crust: null, sauce: null, cheese: null, drizzle: null, cut: null, toppings: {} };

                $find('.cb-card').removeClass('cb-card--selected');
                $find('.cb-btn--add').show();
                $find('.cb-btn--remove').hide();
                $find('.cb-coverage').hide();
                $find('.cb-coverage__current').each(function () {
                    var s = $(this).data('slug');
                    if (s) { instance._updateCoverageChip(s, 'whole'); }
                });
                instance.closeCoverage();
                $find('.cb-tab').removeClass('cb-tab--done');

                // Clear pizza stage
                getStage().find('.cb-layer-div').fadeOut(300, function () { $(this).remove(); });

                instance._updateCounter();
                instance._renderSummary();
                instance.goTab('crust');
                instance._initDefaultLayers();
            },

            /* ── Public: get current pizza state ── */
            getState: function () {
                return JSON.parse(JSON.stringify(state)); // deep clone
            },

            /* ── Public: set state programmatically (Pizza API) ── */
            setState: function (newState) {
                instance.resetAll();
                if (newState.crust)   { instance._applyBaseFromState('crust',   newState.crust); }
                if (newState.sauce)   { instance._applyBaseFromState('sauce',   newState.sauce); }
                if (newState.cheese)  { instance._applyBaseFromState('cheese',  newState.cheese); }
                if (newState.drizzle) { instance._applyBaseFromState('drizzle', newState.drizzle); }
                if (newState.cut)     { instance._applyBaseFromState('cut',     newState.cut); }
                if (newState.toppings) {
                    $.each(newState.toppings, function (slug, t) {
                        var $card = $find('.cb-card[data-layer="toppings"][data-slug="' + slug + '"]');
                        if ($card.length) {
                            $card.find('.cb-btn--add').trigger('click');
                            if (t.coverage && t.coverage !== 'whole') {
                                instance.setCoverage(slug, t.coverage);
                            }
                        } else {
                            // Headless add (no UI card)
                            state.toppings[slug] = t;
                            PizzaStack.setLayer(getStage(), 'layer-topping-' + slug, t.layerImg, t.zindex || 400, 'nl-topping', t.coverage || 'whole');
                        }
                    });
                }
            },

            _applyBaseFromState: function (layerType, layerData) {
                var $card = $find('.cb-card[data-layer="' + layerType + '"][data-slug="' + layerData.slug + '"]');
                if ($card.length) {
                    $card.find('.cb-btn--add').trigger('click');
                } else {
                    state[layerType] = layerData;
                    var zMap = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };
                    PizzaStack.setLayer(getStage(), 'layer-' + layerType, layerData.layerImg, zMap[layerType] || 500, 'nl-' + layerType);
                }
            },

            /* ── Private helpers ── */
            _bindTabs: function () {
                $root.on('click', '.cb-tab', function () {
                    instance.goTab($(this).data('tab'));
                });
            },

            _bindMobileToggle: function () {
                $root.on('click', '#' + instanceId + '-mobile-toggle', function () {
                    var $exp = $('#' + instanceId + '-mobile-expanded');
                    var open = $exp.hasClass('open');
                    $exp.toggleClass('open', !open).attr('aria-hidden', open ? 'false' : 'true');
                    $(this).find('.fa').toggleClass('fa-chevron-down', open).toggleClass('fa-chevron-up', !open);
                });
            },

            _updateCounter: function () {
                var count = Object.keys(state.toppings).length;
                $find('#' + instanceId + '-count').text(count);
                $find('.cb-topping-counter').css('border-color', count >= maxTopping ? 'var(--cb-accent)' : '');
            },

            _flyTo: function ($thumbEl) {
                if (!$thumbEl || !$thumbEl.length) { return; }
                var $target = $find('#' + instanceId + '-canvas, .cb-pizza-sticky__canvas').first();
                if (!$target.length) { return; }

                var srcRect = $thumbEl[0].getBoundingClientRect();
                var dstRect = $target[0].getBoundingClientRect();
                if (!srcRect.width || !dstRect.width) { return; }

                var $clone = $('<div class="cb-fly-clone"></div>').css({
                    top: srcRect.top, left: srcRect.left,
                    width: srcRect.width, height: srcRect.height
                });
                if ($thumbEl.is('img')) {
                    $clone.append($('<img>').attr('src', $thumbEl.attr('src')).css({ width:'100%',height:'100%','object-fit':'cover' }));
                }
                $find('#' + instanceId + '-fly-container, #cb-fly-container').first().append($clone);

                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var destX = dstRect.left + dstRect.width / 2 - srcRect.width / 2;
                        var destY = dstRect.top  + dstRect.height / 2 - srcRect.height / 2;
                        $clone.css({
                            transition: 'top 0.65s cubic-bezier(0.4,0,0.2,1),left 0.65s cubic-bezier(0.4,0,0.2,1),transform 0.65s cubic-bezier(0.4,0,0.2,1),opacity 0.65s ease',
                            top: destY, left: destX, transform: 'scale(0.18)', opacity: '0'
                        });
                        setTimeout(function () { $clone.remove(); }, 700);
                    });
                });
            },

            _showToast: function (msg) {
                var settings = window.pizzatierSettings || {};
                var style    = settings.toastStyle || 'bottom-right';
                if ( style === 'none' ) { return; }
                var dur = parseInt( settings.toastDuration, 10 ) || 3000;
                var $toast = $('<div class="cb-toast cb-toast--style-' + style + '"></div>').text(msg);
                $root.append($toast);
                setTimeout(function () { $toast.addClass('cb-toast--visible'); }, 10);
                setTimeout(function () { $toast.removeClass('cb-toast--visible'); setTimeout(function () { $toast.remove(); }, 400); }, dur);
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
                        $val.closest('.cb-yourpizza__row').addClass('has-selection');
                    } else {
                        $val.html('<span class="cb-yp-none">— none selected —</span>');
                        $val.closest('.cb-yourpizza__row').removeClass('has-selection');
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
                    $tVal.closest('.cb-yourpizza__row').addClass('has-selection');
                } else {
                    $tVal.html('<span class="cb-yp-none">— none added —</span>');
                    $tVal.closest('.cb-yourpizza__row').removeClass('has-selection');
                }
            },

            _selBubble: function (thumb, title, coverage, toppingSlug, layerType, slug) {
                var npVar   = $root.data('cb-var');
                var imgHtml = thumb ? '<img src="' + instance._esc(thumb) + '" alt="' + instance._esc(title) + '" />' : '';
                var covHtml = coverage ? '<span class="cb-yp-coverage"> · ' + coverage.replace('quarter-','Q').replace('half-','½') + '</span>' : '';
                var remHtml = '';
                if (layerType === 'toppings' && toppingSlug) {
                    remHtml = '<button class="cb-yp-remove" onclick="' + npVar + '.removeTopping(\'pizzatier-topping-' + toppingSlug + '\',\'' + toppingSlug + '\',this);" title="Remove"><i class="fa fa-times"></i></button>';
                } else if (layerType && slug) {
                    remHtml = '<button class="cb-yp-remove" onclick="' + npVar + '.removeBase(\'' + layerType + '\',\'' + slug + '\',this);" title="Remove"><i class="fa fa-times"></i></button>';
                }
                return '<div class="cb-yp-bubble">' + imgHtml + '<span class="cb-yp-name">' + instance._esc(title) + covHtml + '</span>' + remHtml + '</div>';
            },

            _esc: function (str) {
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            },

            /* ── Topping count badge ── */
            _updateBadge: function ($card, count) {
                var $badge = $card.find('.cb-card__badge');
                if ( !$badge.length ) {
                    $badge = $('<span class="cb-card__badge"></span>');
                    $card.css('position', 'relative').append($badge);
                }
                if ( count > 0 ) {
                    $badge.text(count).addClass('cb-card__badge--visible');
                } else {
                    $badge.removeClass('cb-card__badge--visible').text('');
                }
            },

            /* ── Apply start-over button label from settings ── */
            _applyStartOverLabel: function () {
                var settings = window.pizzatierSettings || {};
                var label = settings.startOverLabel || '';
                if ( !label ) { return; }
                $find('.cb-btn--ghost').each(function () {
                    var $btn = $(this);
                    if ( $btn.text().indexOf('Reset') !== -1 || $btn.attr('onclick') && $btn.attr('onclick').indexOf('resetAll') !== -1 ) {
                        $btn.find('.fa').next().length
                            ? $btn.find('.fa').next().text(' ' + label)
                            : $btn.html($btn.html().replace(/Reset|Start Over/, label));
                    }
                });
            },

            /* ── Step-by-step mode: reveal one tab at a time ── */
            _initStepMode: function () {
                var settings  = window.pizzatierSettings || {};
                var autoAdv   = settings.autoAdvance === 'yes';
                var $tabs     = $find('.cb-tab');
                var tabCount  = $tabs.length;
                if ( tabCount < 2 ) { return; }

                // Hide all tabs after the first
                $tabs.each(function (i) {
                    if ( i > 0 ) { $(this).addClass('cb-tab--hidden-step'); }
                });

                // Listen for selections and reveal next tab
                $root.on('click.stepmode', '.cb-btn--add', function () {
                    var $activeTabs = $find('.cb-tab:not(.cb-tab--hidden-step)');
                    var lastVisible = $activeTabs.last();
                    var $next = lastVisible.next('.cb-tab.cb-tab--hidden-step');
                    if ( $next.length ) {
                        $next.removeClass('cb-tab--hidden-step');
                        if ( autoAdv ) {
                            setTimeout(function () { instance.goTab($next.data('tab')); }, 120);
                        }
                    }
                });
            }
        };

        return instance;
    }

    /* ════════════════════════════════════════════════════════════════
       GLOBAL CB FACTORY + PIZZA API
       ════════════════════════════════════════════════════════════════ */

    var CB = {

        _instances: {},

        createInstance: function (instanceId) {
            var inst = createCBInstance(instanceId);
            CB._instances[instanceId] = inst;
            return inst;
        },

        getInstance: function (instanceId) {
            return CB._instances[instanceId] || null;
        },

        /**
         * PizzaTier Pizza API — callable by other plugins.
         *
         * Usage:
         *   // Set a full pizza state on a builder:
         *   window.PizzaTierAPI.setState('pizza-1', {
         *     crust:   { slug: 'thin-crust',  layerImg: '...', title: 'Thin Crust' },
         *     sauce:   { slug: 'tomato',       layerImg: '...', title: 'Tomato' },
         *     toppings: {
         *       'pepperoni': { slug: 'pepperoni', layerImg: '...', title: 'Pepperoni', zindex: 400, coverage: 'whole' }
         *     }
         *   });
         *
         *   // Get current state:
         *   var state = window.PizzaTierAPI.getState('pizza-1');
         *
         *   // Render a static pizza stack into any element:
         *   window.PizzaTierAPI.renderStatic('#my-div', pizzaStateObj);
         */
        API: {
            getState: function (instanceId) {
                var inst = CB.getInstance(instanceId);
                return inst ? inst.getState() : null;
            },
            setState: function (instanceId, newState) {
                var inst = CB.getInstance(instanceId);
                if (inst) { inst.setState(newState); }
            },
            getAllInstances: function () {
                return Object.keys(CB._instances);
            },

            /**
             * renderPizza — fetch a rendered pizza stack from the REST API.
             *
             * Usage:
             *   window.PizzaTierAPI.renderPizza({
             *     crust: 'thin-crust',
             *     sauce: 'classic-tomato',
             *     toppings: ['pepperoni','mushrooms']
             *   }).then(function(html) {
             *     document.getElementById('my-pizza').innerHTML = html;
             *   });
             *
             * @param {Object}  layers  { crust, sauce, cheese, toppings[], drizzle, cut, preset }
             * @returns {Promise<string>} Resolves to rendered HTML string
             */
            renderPizza: function (layers) {
                var apiBase = (window.wpApiSettings && window.wpApiSettings.root)
                    ? window.wpApiSettings.root
                    : '/wp-json/';
                var nonce = (window.wpApiSettings && window.wpApiSettings.nonce)
                    ? window.wpApiSettings.nonce : '';

                return fetch(apiBase + 'pizzatier/v1/render', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify(layers || {})
                }).then(function (r) { return r.json(); })
                  .then(function (d) { return d.html || ''; });
            },

            /**
             * getLayerUrl — get the layer image URL for a type + slug.
             *
             * Usage:
             *   window.PizzaTierAPI.getLayerUrl('crust', 'thin-crust')
             *     .then(function(url) { myImg.src = url; });
             */
            getLayerUrl: function (type, slug) {
                var apiBase = (window.wpApiSettings && window.wpApiSettings.root)
                    ? window.wpApiSettings.root : '/wp-json/';
                return fetch(apiBase + 'pizzatier/v1/layer-url?type=' + encodeURIComponent(type) + '&slug=' + encodeURIComponent(slug))
                    .then(function (r) { return r.json(); })
                    .then(function (d) { return d.url || ''; });
            },

            /**
             * renderStatic — render a pizza state into any container element (client-side).
             *
             * Usage:
             *   window.PizzaTierAPI.renderStatic('#my-div', {
             *     crust:   { layerImg: '...' },
             *     sauce:   { layerImg: '...' },
             *     toppings: { pepperoni: { layerImg: '...', zindex: 400, coverage: 'whole' } }
             *   });
             */
            renderStatic: function (selectorOrEl, pizzaState) {
                var $container = $(selectorOrEl);
                if (!$container.length) { return; }

                $container.empty().css({ position: 'relative', overflow: 'hidden' });
                var $stage = $('<div class="cb-pizza-stage"></div>');
                $container.append($stage);

                var zMap = { crust: 100, sauce: 200, cheese: 300, drizzle: 900, cut: 950 };
                var baseTypes = ['crust','sauce','cheese','drizzle','cut'];
                baseTypes.forEach(function (lt) {
                    var layer = pizzaState[lt];
                    if (layer && layer.layerImg) {
                        PizzaStack.setLayer($stage, 'layer-' + lt, layer.layerImg, zMap[lt], 'nl-' + lt);
                    }
                });

                if (pizzaState.toppings) {
                    $.each(pizzaState.toppings, function (slug, t) {
                        if (t && t.layerImg) {
                            PizzaStack.setLayer($stage, 'layer-topping-' + slug, t.layerImg, t.zindex || 400, 'nl-topping', t.coverage);
                        }
                    });
                }
                return $stage;
            }
        }
    };

    /* ════════════════════════════════════════════════════════════════
       AUTO-INITIALISE ALL .cb-root ELEMENTS ON PAGE LOAD
       ════════════════════════════════════════════════════════════════ */

    $(document).ready(function () {
        $('.cb-root').each(function () {
            var instanceId = $(this).data('instance') || $(this).attr('id');
            if (!instanceId) { return; }

            var npVar = $(this).data('cb-var') || ('CB_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));
            var inst  = CB.createInstance(instanceId);
            inst.init();

            // Expose instance globally for onclick= handlers in PHP-rendered HTML
            window[npVar] = inst;
        });

        // Backward compat: single-instance sites that use window.CB directly
        var allIds = CB.API.getAllInstances();
        if (allIds.length === 1) {
            window.CB = CB._instances[allIds[0]];
        } else {
            window.CB = CB; // expose the factory
        }
    });

    /* ════════════════════════════════════════════════════════════════
       EXPOSE GLOBAL API
       ════════════════════════════════════════════════════════════════ */
    window.PizzaTierAPI = CB.API;
    window.PizzaTierNP  = CB; // Factory always available

    /* ════════════════════════════════════════════════════════════════
       LEGACY GLOBAL FUNCTIONS (D62 compatibility)
       Called by older template code — now delegate to the stack API
       ════════════════════════════════════════════════════════════════ */
    window.SwapBasePizzaTier = function (divId, title, imgSrc) {
        // divId format: pizzatier-base-layer-{type}
        var layerType = divId.replace('pizzatier-base-layer-', '');
        var $stage = $('.cb-pizza-stage').first();
        if (!$stage.length) { return; }
        var zMap = { crust: 100, sauce: 200, cheese: 300 };
        PizzaStack.setLayer($stage, 'layer-' + layerType, imgSrc, zMap[layerType] || 200);
    };

    window.AddPizzaTier = function (zindex, slug, imgSrc, title, cssId) {
        var $stage = $('.cb-pizza-stage').first();
        if ($stage.length) {
            PizzaStack.setLayer($stage, 'layer-topping-' + slug, imgSrc, zindex, 'nl-topping', 'whole');
        }
    };

    window.RemovePizzaTier = function (layerId, title, slug) {
        var $stage = $('.cb-pizza-stage').first();
        if ($stage.length) {
            PizzaStack.removeLayer($stage, 'layer-topping-' + slug);
        }
    };

    window.SetToppingCoverage = function (fraction, divId) {
        // divId: pizzatier-topping-{slug}
        var slug   = divId.replace('pizzatier-topping-', '');
        var $stage = $('.cb-pizza-stage').first();
        if (!$stage.length) { return; }
        var $layer = $stage.find('[data-layer-id="layer-topping-' + slug + '"]');
        if ($layer.length) {
            $layer.find('img').css('clip-path', PizzaStack.getCoverageClip(fraction));
        }
    };

    window.ClearPizza = function () {
        $('.cb-pizza-stage').each(function () {
            $(this).find('.cb-layer-div').fadeOut(300, function () { $(this).remove(); });
        });
    };

})(jQuery);
