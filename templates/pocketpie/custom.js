/* ══════════════════════════════════════════════════════════════════════
   PIZZATIER — PocketPie Template — custom.js

   Architecture:
   - PP.createInstance(instanceId) → scoped builder instance
   - Each layout mode (corner-quad, layer-deck, slide-drawer, stack-panel)
     has its own init/open/close helpers within the instance.
   - Pizza stack reuses the same PizzaStack renderer from NightPie (shared
     via window.PizzaStack if loaded, or a local fallback defined below).
   - window.PP_{instanceId} exposed for onclick= handlers in PHP HTML.
   - window.PizzaTierAPI forwarded for Pro plugin hooks.
   ══════════════════════════════════════════════════════════════════════ */

(function ($) {
    'use strict';

    /* ════════════════════════════════════════════════════════════════
       PIZZA STACK RENDERER (self-contained fallback — works standalone
       even when NightPie's custom.js is NOT loaded on the same page)
       ════════════════════════════════════════════════════════════════ */

    var PizzaStack = window.PizzaStack || {

        getStage: function ($root) {
            var $stage = $root.find('.pp-pizza-stage');
            if (!$stage.length) {
                var $wrap = $root.find('.pp-pizza-stage-wrap, .np-pizza-stage-wrap, .pp-ld-pizza-zone .pp-pizza-stage-wrap, .pp-sp-pizza-mini').first();
                if (!$wrap.length) { $wrap = $root; }
                $stage = $('<div class="pp-pizza-stage"></div>');
                $wrap.append($stage);
            }
            return $stage;
        },

        getCoverageClip: function (fraction) {
            var clips = {
                'whole':               'inset(0 0 0 0)',
                'half-left':           'inset(0 50% 0 0)',
                'half-right':          'inset(0 0 0 50%)',
                'quarter-top-left':    'inset(0 50% 50% 0)',
                'quarter-top-right':   'inset(0 0 50% 50%)',
                'quarter-bottom-left': 'inset(50% 50% 0 0)',
                'quarter-bottom-right':'inset(50% 0 0 50%)'
            };
            return clips[fraction] || clips['whole'];
        },

        setLayer: function ($stage, layerId, src, zIndex, cls, coverage) {
            if (!src) { this.removeLayer($stage, layerId); return; }
            var $layer = $stage.find('[data-layer-id="' + layerId + '"]');
            if (!$layer.length) {
                $layer = $('<div class="pp-layer-div"></div>');
                $layer.attr('data-layer-id', layerId);
                $stage.append($layer);
            }
            $layer.css({ position:'absolute', top:0, left:0, width:'100%', height:'100%', zIndex: zIndex });
            if (cls) { $layer.addClass(cls); }

            var $img = $layer.find('img');
            if (!$img.length) {
                $img = $('<img style="width:100%;height:100%;object-fit:cover;display:block;" />');
                $layer.append($img);
                $img.css({ opacity: 0 });
                $img.on('load', function () {
                    $img.animate({ opacity: 1 }, 260);
                });
            } else {
                $img.css('opacity', 0).animate({ opacity: 1 }, 260);
            }
            $img.attr('src', src).attr('alt', layerId);
            if (coverage) {
                $img.css('clip-path', this.getCoverageClip(coverage));
            }
        },

        removeLayer: function ($stage, layerId) {
            $stage.find('[data-layer-id="' + layerId + '"]').fadeOut(200, function () { $(this).remove(); });
        }
    };

    /* ════════════════════════════════════════════════════════════════
       STATE OBJECT per instance
       ════════════════════════════════════════════════════════════════ */

    function makeState() {
        return {
            crust:    null,
            sauce:    null,
            cheese:   null,
            drizzle:  null,
            cut:      null,
            toppings: {}
        };
    }

    /* ════════════════════════════════════════════════════════════════
       PP FACTORY
       ════════════════════════════════════════════════════════════════ */

    var PP = {
        _instances: {},

        createInstance: function (instanceId) {
            if (PP._instances[instanceId]) { return PP._instances[instanceId]; }

            var inst = {
                id:      instanceId,
                $root:   null,
                layout:  'corner-quad',
                state:   makeState(),
                maxTop:  99,

                /* ── init ─────────────────────────────── */
                init: function () {
                    var self = this;
                    self.$root  = $('#' + instanceId);
                    self.layout = self.$root.data('layout') || 'corner-quad';
                    self.maxTop = parseInt(self.$root.data('max-toppings'), 10) || 99;

                    // Ensure the pizza canvas has a stage div
                    var $canvas = self.$root.find('#' + instanceId + '-canvas').first();
                    if ($canvas.length && !$canvas.find('.pp-pizza-stage').length) {
                        $canvas.css({ position:'relative', overflow:'hidden' });
                        $canvas.append('<div class="pp-pizza-stage"></div>');
                    }

                    // Chip hover: show selected-layer image in layer-deck preview
                    if (self.layout === 'layer-deck') {
                        self.$root.on('mouseenter', '.pp-chip', function () {
                            var thumb = $(this).data('thumb');
                            var $img  = self.$root.find('#' + instanceId + '-ld-preview-img-tag');
                            var $empty= self.$root.find('#' + instanceId + '-ld-preview-img-empty');
                            if (thumb && $img.length) {
                                $img.attr('src', thumb).show();
                                $empty.hide();
                            }
                        });
                    }

                    // Close modal on overlay click — handled via onclick in PHP
                    // Swipe-down to close drawers / sheets
                    self._initSwipeClose();
                },

                /* ── swipe to close overlays ──────────── */
                _initSwipeClose: function () {
                    var self    = this;
                    var touchY  = null;
                    var $sheet  = self.$root.find('.pp-sd-drawer, .pp-sp-sheet');

                    // Per-layout opt-out via template settings (rendered as
                    // data attributes on .pp-root by pztp-containers-menu.php)
                    var sdSwipe = (self.$root.attr('data-swipe-close-sd') || 'yes') !== 'no';
                    var spSwipe = (self.$root.attr('data-swipe-close-sp') || 'yes') !== 'no';
                    if (self.layout === 'slide-drawer' && !sdSwipe) { return; }
                    if (self.layout === 'stack-panel'  && !spSwipe) { return; }

                    $sheet.on('touchstart', function (e) {
                        touchY = e.originalEvent.touches[0].clientY;
                    });
                    $sheet.on('touchmove', function (e) {
                        if (touchY === null) { return; }
                        var dy = e.originalEvent.touches[0].clientY - touchY;
                        if (dy > 60) {
                            touchY = null;
                            if (self.layout === 'slide-drawer') { self.sdClose(instanceId); }
                            if (self.layout === 'stack-panel')  { self.spClose(instanceId); }
                        }
                    });
                },

                /* ═══════════════════════════════════════
                   PIZZA LAYER HELPERS
                   ═══════════════════════════════════════ */

                _getStage: function () {
                    return PizzaStack.getStage(this.$root.find('#' + instanceId + '-canvas'));
                },

                /* Exclusive base layer (crust / sauce / cheese / drizzle / cut) */
                swapBase: function (layerType, slug, title, imgSrc, triggerEl) {
                    var self   = this;
                    var $stage = self._getStage();
                    var zMap   = { crust:100, sauce:150, cheese:200, drizzle:900, cut:950 };
                    var z      = zMap[layerType] || 200;

                    // Toggle: if already selected, remove it
                    var existing = self.state[layerType];
                    if (existing && existing.slug === slug) {
                        self.removeBase(layerType, slug, triggerEl);
                        return;
                    }

                    self.state[layerType] = { slug: slug, title: title, layerImg: imgSrc };
                    PizzaStack.setLayer($stage, 'layer-' + layerType, imgSrc, z, 'pp-l-' + layerType);

                    // UI feedback on chips
                    self.$root.find('.pp-chip[data-layer="' + layerType + '"]').each(function () {
                        var $c = $(this);
                        var isThis = ($c.data('slug') === slug);
                        $c.toggleClass('pp-chip--selected', isThis);
                        $c.find('.pp-chip__add-btn').toggle(!isThis);
                        $c.find('.pp-chip__remove-btn').toggle(isThis);
                        $c.find('.pp-chip__check').toggle(isThis);
                    });

                    self._updateSummary(layerType, title);
                    self._updateBadge(layerType, title);
                    self._updateLayerDeckSel(layerType, title);
                    self._updateDot(layerType, true);

                    // Layer-deck preview image: show the selected layer image large
                    if (self.layout === 'layer-deck') {
                        var $img   = self.$root.find('#' + instanceId + '-ld-preview-img-tag');
                        var $empty = self.$root.find('#' + instanceId + '-ld-preview-img-empty');
                        if ($img.length && imgSrc) {
                            $img.attr('src', imgSrc).show();
                            $empty.hide();
                        }
                    }
                },

                removeBase: function (layerType, slug, triggerEl) {
                    var self = this;
                    if (!self.state[layerType] || self.state[layerType].slug !== slug) { return; }
                    var $stage = self._getStage();
                    PizzaStack.removeLayer($stage, 'layer-' + layerType);
                    self.state[layerType] = null;

                    self.$root.find('.pp-chip[data-layer="' + layerType + '"][data-slug="' + slug + '"]').each(function () {
                        $(this).removeClass('pp-chip--selected');
                        $(this).find('.pp-chip__add-btn').show();
                        $(this).find('.pp-chip__remove-btn').hide();
                        $(this).find('.pp-chip__check').hide();
                    });

                    self._updateSummary(layerType, null);
                    self._updateBadge(layerType, null);
                    self._updateLayerDeckSel(layerType, null);
                    self._updateDot(layerType, false);
                },

                /* Toppings — multi-select */
                addTopping: function (zindex, slug, imgSrc, title, layerId, cssId, triggerEl) {
                    var self = this;
                    if (Object.keys(self.state.toppings).length >= self.maxTop) {
                        self._flashMax();
                        return;
                    }
                    var $stage = self._getStage();
                    self.state.toppings[slug] = { slug:slug, title:title, layerImg:imgSrc, zindex:zindex, coverage:'whole' };
                    PizzaStack.setLayer($stage, 'layer-topping-' + slug, imgSrc, zindex, 'pp-l-topping', 'whole');

                    var $chip = self.$root.find('.pp-chip[data-layer="toppings"][data-slug="' + slug + '"]');
                    $chip.addClass('pp-chip--selected');
                    $chip.find('.pp-chip__add-btn').hide();
                    $chip.find('.pp-chip__remove-btn').show();
                    $chip.find('.pp-chip__check').show();
                    $chip.find('.pp-coverage').show();

                    self._updateToppingCount();
                    self._updateDot('toppings', true);
                },

                removeTopping: function (layerId, slug, triggerEl) {
                    var self   = this;
                    var $stage = self._getStage();
                    delete self.state.toppings[slug];
                    PizzaStack.removeLayer($stage, 'layer-topping-' + slug);

                    var $chip = self.$root.find('.pp-chip[data-layer="toppings"][data-slug="' + slug + '"]');
                    $chip.removeClass('pp-chip--selected');
                    $chip.find('.pp-chip__add-btn').show();
                    $chip.find('.pp-chip__remove-btn').hide();
                    $chip.find('.pp-chip__check').hide();
                    $chip.find('.pp-coverage').hide();

                    self._updateToppingCount();
                    self._updateDot('toppings', Object.keys(self.state.toppings).length > 0);
                },

                setCoverage: function (slug, fraction, triggerEl) {
                    var self   = this;
                    var $stage = self._getStage();
                    if (!self.state.toppings[slug]) { return; }
                    self.state.toppings[slug].coverage = fraction;
                    var $layer = $stage.find('[data-layer-id="layer-topping-' + slug + '"] img');
                    if ($layer.length) {
                        $layer.css('clip-path', PizzaStack.getCoverageClip(fraction));
                    }
                    // Update active state on coverage buttons
                    var $chip = self.$root.find('.pp-chip[data-layer="toppings"][data-slug="' + slug + '"]');
                    $chip.find('.pp-cov-btn').removeClass('pp-cov-btn--active');
                    $chip.find('.pp-cov-btn[data-fraction="' + fraction + '"]').addClass('pp-cov-btn--active');
                },

                resetAll: function () {
                    var self   = this;
                    self.state = makeState();
                    self.$root.find('.pp-chip').each(function () {
                        $(this).removeClass('pp-chip--selected');
                        $(this).find('.pp-chip__add-btn').show();
                        $(this).find('.pp-chip__remove-btn').hide();
                        $(this).find('.pp-chip__check').hide();
                        $(this).find('.pp-coverage').hide();
                    });
                    self.$root.find('.pp-cq-trigger__badge').text('');
                    self.$root.find('.pp-sd-pill__dot, .pp-sp-step__dot').removeClass('pp-dot--active');
                    self.$root.find('.pp-ld-deck-thumb__sel').text('');
                    self._updateToppingCount();
                    self._updateAllSummaryRows();
                },

                /* ── UI helper: topping counter ───────── */
                _updateToppingCount: function () {
                    var self = this;
                    var n    = Object.keys(self.state.toppings).length;
                    self.$root.find('#' + instanceId + '-cq-count, #' + instanceId + '-ld-count, #' + instanceId + '-sd-count, #' + instanceId + '-sp-count, #' + instanceId + '-modal-count').text(n);
                    self._updateLayerDeckSel('toppings', n > 0 ? n + ' selected' : null);
                },

                _flashMax: function () {
                    var self = this;
                    self.$root.find('#' + instanceId + '-cq-count, #' + instanceId + '-ld-count, #' + instanceId + '-sd-count, #' + instanceId + '-sp-count').addClass('pp-count--flash');
                    setTimeout(function () {
                        self.$root.find('.pp-count--flash').removeClass('pp-count--flash');
                    }, 600);
                },

                /* ── Summary rows (modal) ─────────────── */
                _updateSummary: function (key, title) {
                    var $val = this.$root.find('#' + instanceId + '-modal-yp-' + key + '-val');
                    if (!$val.length) { return; }
                    if (title) {
                        $val.html('<span class="pp-yp-set">' + $('<span>').text(title).html() + '</span>');
                        $val.removeClass('pp-summary-row__val--empty');
                    } else {
                        $val.html('— <em>none</em> —').addClass('pp-summary-row__val--empty');
                    }
                },

                _updateAllSummaryRows: function () {
                    var self = this;
                    ['crust','sauce','cheese','drizzle','cut'].forEach(function (k) {
                        var v = self.state[k];
                        self._updateSummary(k, v ? v.title : null);
                    });
                    // toppings row
                    var tops = Object.values(self.state.toppings).map(function (t) { return t.title; }).join(', ');
                    self._updateSummary('toppings', tops || null);
                },

                /* ── Corner-quad badge ────────────────── */
                _updateBadge: function (layerType, title) {
                    var self = this;
                    // Find which corner this tab is in
                    self.$root.find('.pp-cq-corner[data-tab="' + layerType + '"] .pp-cq-trigger__badge').text(title ? '✓' : '');
                },

                /* ── Layer-deck strip selected label ──── */
                _updateLayerDeckSel: function (layerType, title) {
                    var self = this;
                    self.$root.find('#' + instanceId + '-ld-sel-' + layerType).text(title ? title : '');
                },

                /* ── Progress dot ─────────────────────── */
                _updateDot: function (tab, active) {
                    var self = this;
                    self.$root.find('[data-step="' + tab + '"]').toggleClass('pp-dot--active', !!active);
                    self.$root.find('#' + instanceId + '-sd-dot-' + tab).toggleClass('pp-dot--active', !!active);
                    self.$root.find('#' + instanceId + '-sp-step-dot-' + tab).toggleClass('pp-dot--active', !!active);
                },

                /* ═══════════════════════════════════════
                   LAYOUT 1: CORNER QUAD
                   ═══════════════════════════════════════ */

                /* Corner-quad corners now open the shared modal. cqToggle is kept
                   as a backward-compatible alias: it resolves the corner's
                   category and opens that tab in the modal. */
                cqToggle: function (iid, corner) {
                    var $corner = this.$root.find('.pp-cq-corner--' + corner);
                    var tab = $corner.attr('data-tab');
                    if (tab) { this.openModal(iid, tab); }
                },

                /* ═══════════════════════════════════════
                   LAYOUT 2: LAYER DECK
                   ═══════════════════════════════════════ */

                ldSelect: function (iid, tab) {
                    var self    = this;
                    var $expand = self.$root.find('#' + iid + '-ld-expand');
                    var $title  = self.$root.find('#' + iid + '-ld-expand-title');
                    var $prevImg= self.$root.find('#' + iid + '-ld-preview-img-tag');
                    var $empty  = self.$root.find('#' + iid + '-ld-preview-img-empty');

                    // Hide all panels
                    self.$root.find('[id^="' + iid + '-ld-chips-"]').hide();

                    // Deactivate all thumbs
                    self.$root.find('.pp-ld-deck-thumb').removeClass('pp-ld-deck-thumb--active');
                    self.$root.find('#' + iid + '-ld-thumb-' + tab).addClass('pp-ld-deck-thumb--active');

                    // Show correct chips panel
                    self.$root.find('#' + iid + '-ld-chips-' + tab).show();

                    // Update title
                    var labelMap = {
                        crust:'Crust', sauce:'Sauce', cheese:'Cheese',
                        toppings:'Toppings', drizzle:'Drizzle', slicing:'Slicing'
                    };
                    $title.text(labelMap[tab] || tab);

                    // Reset preview to empty or show current selection
                    var current = (tab !== 'toppings') ? self.state[tab] : null;
                    if (current && current.layerImg) {
                        $prevImg.attr('src', current.layerImg).show();
                        $empty.hide();
                    } else {
                        $prevImg.attr('src','').hide();
                        $empty.show();
                    }

                    // Show/slide expand
                    $expand.addClass('pp-ld-expand--open').attr('aria-hidden', 'false');
                },

                ldClose: function (iid) {
                    this.$root.find('#' + iid + '-ld-expand').removeClass('pp-ld-expand--open').attr('aria-hidden', 'true');
                    this.$root.find('.pp-ld-deck-thumb').removeClass('pp-ld-deck-thumb--active');
                },

                /* ═══════════════════════════════════════
                   LAYOUT 3: SLIDE DRAWER
                   ═══════════════════════════════════════ */

                sdOpen: function (iid, tab) {
                    var self    = this;
                    var $drawer = self.$root.find('#' + iid + '-sd-drawer');
                    var $title  = self.$root.find('#' + iid + '-sd-drawer-title');

                    // Hide all panels
                    self.$root.find('[id^="' + iid + '-sd-panel-"]').hide();
                    self.$root.find('#' + iid + '-sd-panel-' + tab).show();

                    var labelMap = {
                        crust:'Crust', sauce:'Sauce', cheese:'Cheese',
                        toppings:'Toppings', drizzle:'Drizzle', slicing:'Slicing'
                    };
                    $title.text(labelMap[tab] || tab);

                    // Mark active pill
                    self.$root.find('.pp-sd-pill').removeClass('pp-sd-pill--active');
                    self.$root.find('#' + iid + '-sd-pill-' + tab).addClass('pp-sd-pill--active');

                    $drawer.addClass('pp-sd-drawer--open').attr('aria-hidden', 'false');
                },

                sdClose: function (iid) {
                    this.$root.find('#' + iid + '-sd-drawer').removeClass('pp-sd-drawer--open').attr('aria-hidden', 'true');
                    this.$root.find('.pp-sd-pill').removeClass('pp-sd-pill--active');
                },

                /* ═══════════════════════════════════════
                   LAYOUT 4: STACK PANEL
                   ═══════════════════════════════════════ */

                spOpen: function (iid, tab) {
                    var self   = this;
                    var $sheet = self.$root.find('#' + iid + '-sp-sheet');
                    var $title = self.$root.find('#' + iid + '-sp-sheet-title');
                    var $label = self.$root.find('#' + iid + '-sp-label');

                    self.$root.find('[id^="' + iid + '-sp-panel-"]').hide();
                    self.$root.find('#' + iid + '-sp-panel-' + tab).show();

                    var labelMap = {
                        crust:'Crust', sauce:'Sauce', cheese:'Cheese',
                        toppings:'Toppings', drizzle:'Drizzle', slicing:'Slicing'
                    };
                    $title.text(labelMap[tab] || tab);
                    $label.text('Choose ' + (labelMap[tab] || tab));

                    self.$root.find('.pp-sp-step').removeClass('pp-sp-step--active');
                    self.$root.find('.pp-sp-step[data-tab="' + tab + '"]').addClass('pp-sp-step--active');

                    $sheet.addClass('pp-sp-sheet--open').attr('aria-hidden', 'false');
                },

                spClose: function (iid) {
                    this.$root.find('#' + iid + '-sp-sheet').removeClass('pp-sp-sheet--open').attr('aria-hidden', 'true');
                    this.$root.find('.pp-sp-step').removeClass('pp-sp-step--active');
                    this.$root.find('#' + iid + '-sp-label').text('');
                },

                /* ═══════════════════════════════════════
                   SHARED: MODAL
                   ═══════════════════════════════════════ */

                openModal: function (iid, tab) {
                    var self    = this;
                    var $overlay= self.$root.find('#' + iid + '-modal-overlay');
                    var $modal  = self.$root.find('#' + iid + '-modal');
                    var $title  = self.$root.find('#' + iid + '-modal-title');
                    var $body   = self.$root.find('#' + iid + '-modal-body');
                    var $summ   = self.$root.find('#' + iid + '-modal-summary');

                    // Hide all tab panels in modal
                    self.$root.find('[id^="' + iid + '-modal-panel-"]').hide();
                    $summ.hide();
                    $body.empty();

                    if (tab === 'yourpizza') {
                        $title.text($title.attr('data-default') || 'Your Pizza');
                        self._updateAllSummaryRows();
                        $summ.show();
                    } else {
                        var labelMap = {
                            size:'Size', crust:'Crust', sauce:'Sauce', cheese:'Cheese',
                            toppings:'Toppings', drizzle:'Drizzle', slicing:'Slicing'
                        };
                        // Prefer the trigger's own label (honours custom Size label) when available.
                        var $trigger = self.$root.find('.pp-cq-corner[data-tab="' + tab + '"] .pp-cq-trigger__label, .pp-cq-overflow-btn[onclick*="\'' + tab + '\'"] span:last-child').first();
                        var lbl = ($trigger.length ? $.trim($trigger.text()) : '') || labelMap[tab] || tab;
                        $title.text(lbl);
                        // If this panel exists in modal, show it
                        var $panel = self.$root.find('#' + iid + '-modal-panel-' + tab);
                        if ($panel.length) {
                            $panel.show();
                        }
                    }

                    $overlay.addClass('pp-modal-overlay--open').attr('aria-hidden','false');
                    $modal.addClass('pp-modal--open').attr('aria-hidden','false');
                    $('body').addClass('pp-modal-active');
                },

                closeModal: function (iid) {
                    var self = this;
                    self.$root.find('#' + iid + '-modal-overlay').removeClass('pp-modal-overlay--open').attr('aria-hidden','true');
                    self.$root.find('#' + iid + '-modal').removeClass('pp-modal--open').attr('aria-hidden','true');
                    $('body').removeClass('pp-modal-active');
                },

                /* ═══════════════════════════════════════
                   getState / setState (Pro hooks)
                   ═══════════════════════════════════════ */
                getState: function () { return this.state; },
                setState: function (newState) {
                    var self = this;
                    // Apply crust/sauce/cheese/drizzle/cut
                    ['crust','sauce','cheese','drizzle','cut'].forEach(function (k) {
                        if (newState[k]) {
                            var slug = newState[k].slug || '';
                            var img  = newState[k].layerImg || self.$root.find('.pp-chip[data-layer="' + k + '"][data-slug="' + slug + '"]').data('layer-img') || '';
                            self.swapBase(k, slug, newState[k].title || slug, img, null);
                        }
                    });
                    // Apply toppings
                    if (newState.toppings) {
                        $.each(newState.toppings, function (slug, t) {
                            // Resolve layerImg from DOM chip if not supplied (e.g. Pro default-layer path)
                            var img = t.layerImg || self.$root.find('.pp-chip[data-layer="toppings"][data-slug="' + slug + '"]').data('layer-img') || '';
                            var title = t.title || slug;
                            self.addTopping(t.zindex||400, slug, img, title, 'pizzatier-topping-'+slug, 'pizzatier-topping-'+slug, null);
                            if (t.coverage) { self.setCoverage(slug, t.coverage, null); }
                        });
                    }
                }
            };

            PP._instances[instanceId] = inst;
            return inst;
        },

        getInstance: function (instanceId) {
            return PP._instances[instanceId] || null;
        }
    };

    /* ════════════════════════════════════════════════════════════════
       AUTO-INIT ALL .pp-root ELEMENTS
       ════════════════════════════════════════════════════════════════ */

    $(document).ready(function () {
        $('.pp-root').each(function () {
            var instanceId = $(this).data('instance') || $(this).attr('id');
            if (!instanceId) { return; }

            var ppVar = $(this).data('pp-var') || ('PP_' + instanceId.replace(/[^a-zA-Z0-9_]/g, '_'));
            var inst  = PP.createInstance(instanceId);
            inst.init();

            // Expose globally for onclick= handlers in PHP HTML
            window[ppVar] = inst;
        });

        // Backward compat for single-instance pages
        var allIds = Object.keys(PP._instances);
        if (allIds.length === 1) { window.PP = PP._instances[allIds[0]]; }
        else                     { window.PP = PP; }
    });

    /* ════════════════════════════════════════════════════════════════
       EXPOSE GLOBAL API (PizzaTierPro compatibility)
       ════════════════════════════════════════════════════════════════ */
    window.PizzaTierPP  = PP;
    window.PizzaTierAPI = window.PizzaTierAPI || {
        getState:       function (id) { var i = PP.getInstance(id); return i ? i.getState() : null; },
        setState:       function (id, s) { var i = PP.getInstance(id); if (i) { i.setState(s); } },
        getAllInstances: function () { return Object.keys(PP._instances); }
    };

    /* ════════════════════════════════════════════════════════════════
       LEGACY GLOBAL FUNCTIONS (D62 compatibility)
       ════════════════════════════════════════════════════════════════ */
    window.SwapBasePizzaTier = function (divId, title, imgSrc) {
        var layerType = divId.replace('pizzatier-base-layer-', '');
        var $stage = $('.pp-pizza-stage').first();
        if (!$stage.length) { return; }
        var zMap = { crust: 100, sauce: 150, cheese: 200 };
        PizzaStack.setLayer($stage, 'layer-' + layerType, imgSrc, zMap[layerType] || 200);
    };

    window.AddPizzaTier = function (zindex, slug, imgSrc, title, cssId) {
        var $stage = $('.pp-pizza-stage').first();
        if ($stage.length) { PizzaStack.setLayer($stage, 'layer-topping-' + slug, imgSrc, zindex, 'pp-l-topping', 'whole'); }
    };

    window.RemovePizzaTier = function (layerId, title, slug) {
        var $stage = $('.pp-pizza-stage').first();
        if ($stage.length) { PizzaStack.removeLayer($stage, 'layer-topping-' + slug); }
    };

    window.SetToppingCoverage = function (fraction, divId) {
        var slug   = divId.replace('pizzatier-topping-', '');
        var $stage = $('.pp-pizza-stage').first();
        if (!$stage.length) { return; }
        var $layer = $stage.find('[data-layer-id="layer-topping-' + slug + '"]');
        if ($layer.length) { $layer.find('img').css('clip-path', PizzaStack.getCoverageClip(fraction)); }
    };

    window.ClearPizza = function () {
        $('.pp-pizza-stage').each(function () {
            $(this).find('.pp-layer-div').fadeOut(300, function () { $(this).remove(); });
        });
    };

})(jQuery);
