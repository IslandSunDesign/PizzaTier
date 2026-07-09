/* PizzaTier Settings Page — admin UI interactions */
/* eslint-disable no-var */

/* ── Simple / Advanced description mode ──────────────────────
   Wired up immediately (not inside DOMContentLoaded) so it's
   resilient to third-party admin scripts (e.g. SCF/ACF) that
   may throw during the ready phase and abort later bindings.
   Uses delegated events on document so it works even if the
   DOM isn't parsed yet at script-run time. */
(function() {
	var modeKey = 'pset_user_mode';

	function applyMode(mode) {
		var wrap = document.querySelector('.pset-wrap');
		if (!wrap) { return; }
		if (mode === 'simple') {
			wrap.classList.add('pset-mode-simple');
		} else {
			wrap.classList.remove('pset-mode-simple');
		}
		var btns = document.querySelectorAll('.pset-mode-btn');
		for (var i = 0; i < btns.length; i++) {
			var b = btns[i];
			var isActive = b.getAttribute('data-pset-mode') === mode;
			if (isActive) { b.classList.add('pset-mode-btn--active'); }
			else          { b.classList.remove('pset-mode-btn--active'); }
			b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
		}
	}

	function init() {
		var stored = 'advanced';
		try { stored = localStorage.getItem(modeKey) || 'advanced'; } catch(e) {}
		applyMode(stored);
	}

	/* Delegated click — works regardless of when the buttons render
	   or whether another script errored before this ran. */
	document.addEventListener('click', function(e) {
		var target = e.target;
		while (target && target !== document) {
			if (target.classList && target.classList.contains('pset-mode-btn')) {
				var mode = target.getAttribute('data-pset-mode');
				if (mode === 'simple' || mode === 'advanced') {
					e.preventDefault();
					e.stopPropagation();
					applyMode(mode);
					try { localStorage.setItem(modeKey, mode); } catch(err) {}
				}
				return;
			}
			target = target.parentNode;
		}
	}, true); /* capture phase — beats other delegated listeners */

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());

document.addEventListener('DOMContentLoaded', function() {
	/** Escape a string for safe injection into innerHTML. */
	function escHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	var storageKey = 'pset_collapsed_sections';
	var collapsed = {};
	try { collapsed = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch(e) {}

	document.querySelectorAll('.pset-card__head--collapsible').forEach(function(head) {
		var slug = head.getAttribute('data-pset-toggle');
		var btn  = head.querySelector('.pset-collapse-btn');
		var body = document.getElementById('pset-body-' + slug);
		if (!btn || !body) return;

		if (collapsed[slug]) {
			body.classList.add('pset-card__body--collapsed');
			btn.setAttribute('aria-expanded', 'false');
		}

		btn.addEventListener('click', function() {
			var isOpen = btn.getAttribute('aria-expanded') === 'true';
			if (isOpen) {
				body.classList.add('pset-card__body--collapsed');
				btn.setAttribute('aria-expanded', 'false');
				collapsed[slug] = true;
			} else {
				body.classList.remove('pset-card__body--collapsed');
				btn.setAttribute('aria-expanded', 'true');
				delete collapsed[slug];
			}
			try { localStorage.setItem(storageKey, JSON.stringify(collapsed)); } catch(e) {}
		});
	});

	// Color revert buttons
	document.querySelectorAll('.pset-color-revert').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var def    = btn.getAttribute('data-default');
			var target = btn.getAttribute('data-target');
			var input  = document.getElementById(target);
			if (input && def) {
				input.value = def;
				input.dispatchEvent(new Event('input'));
				input.dispatchEvent(new Event('change'));
			}
		});
	});

	// ── Layer picker modal ────────────────────────────────────────
	var modal       = document.getElementById('pset-layer-modal');
	var modalTitle  = document.getElementById('pset-modal-title');
	var modalGrid   = document.getElementById('pset-modal-grid');
	var modalSearch = document.getElementById('pset-modal-search');
	var modalClear  = modal ? modal.querySelector('.pset-modal__clear') : null;
	var modalClose  = modal ? modal.querySelector('.pset-modal__close') : null;
	var modalBack   = modal ? modal.querySelector('.pset-modal__backdrop') : null;
	var activeField = null;  // the .pset-layer-picker-field currently being edited

	function buildModalGrid(items, currentSlug, searchVal) {
		modalGrid.innerHTML = '';
		var q = (searchVal || '').toLowerCase().trim();
		var filtered = items.filter(function(it) { return !q || it.title.toLowerCase().indexOf(q) !== -1; });
		if (!filtered.length) {
			modalGrid.innerHTML = '<p class="pset-modal__empty">No items found.</p>';
			return;
		}
		filtered.forEach(function(item) {
			var div = document.createElement('div');
			div.className = 'pset-modal__item' + (item.slug === currentSlug ? ' pset-modal__item--active' : '');
			div.dataset.slug = item.slug;
			var imgHtml = item.thumb
				? '<img src="' + escHtml(item.thumb) + '" alt="' + escHtml(item.title) + '" loading="lazy">'
				: '<span class="pset-modal__item__no-img"><span class="dashicons dashicons-format-image"></span></span>';
			div.innerHTML = imgHtml + '<span class="pset-modal__item__name">' + escHtml(item.title) + '</span>';
			div.addEventListener('click', function() {
				if (!activeField) return;
				applyLayerChoice(activeField, item);
				closeModal();
			});
			modalGrid.appendChild(div);
		});
	}

	function openModal(field) {
		if (!modal) return;
		activeField = field;
		var label   = field.getAttribute('data-picker-label') || 'Choose a layer';
		var items   = JSON.parse(field.getAttribute('data-picker-items') || '[]');
		var current = field.querySelector('input[type=hidden]').value;
		modalTitle.textContent = 'Choose: ' + label;
		modalSearch.value = '';
		buildModalGrid(items, current, '');
		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';
		setTimeout(function() { modalSearch.focus(); }, 50);
	}

	function closeModal() {
		if (!modal) return;
		modal.style.display = 'none';
		document.body.style.overflow = '';
		activeField = null;
	}

	function applyLayerChoice(field, item) {
		var hidden  = field.querySelector('input[type=hidden]');
		var trigger = field.querySelector('.pset-layer-trigger');
		if (!hidden || !trigger) return;
		hidden.value = item.slug;
		var thumbHtml = item.thumb
			? '<span class="pset-layer-trigger__thumb"><img src="' + escHtml(item.thumb) + '" alt="' + escHtml(item.title) + '"></span>'
			: '<span class="pset-layer-trigger__placeholder dashicons dashicons-format-image"></span>';
		trigger.innerHTML = thumbHtml
			+ '<span class="pset-layer-trigger__name">' + escHtml(item.title) + '</span>'
			+ '<span class="pset-layer-trigger__edit dashicons dashicons-edit"></span>';
		trigger.classList.add('pset-layer-trigger--has-value');
	}

	function clearLayerChoice(field) {
		var hidden  = field.querySelector('input[type=hidden]');
		var trigger = field.querySelector('.pset-layer-trigger');
		if (!hidden || !trigger) return;
		hidden.value = '';
		trigger.innerHTML = '<span class="pset-layer-trigger__placeholder dashicons dashicons-plus-alt2"></span>'
			+ '<span class="pset-layer-trigger__name pset-hint">None selected</span>'
			+ '<span class="pset-layer-trigger__edit dashicons dashicons-edit"></span>';
		trigger.classList.remove('pset-layer-trigger--has-value');
	}

	// Wire up trigger buttons
	document.querySelectorAll('.pset-layer-picker-field').forEach(function(field) {
		field.querySelector('.pset-layer-trigger').addEventListener('click', function() {
			openModal(field);
		});
	});

	if (modalClose) modalClose.addEventListener('click', closeModal);
	if (modalBack)  modalBack.addEventListener('click', closeModal);

	if (modalSearch) {
		modalSearch.addEventListener('input', function() {
			if (!activeField) return;
			var items   = JSON.parse(activeField.getAttribute('data-picker-items') || '[]');
			var current = activeField.querySelector('input[type=hidden]').value;
			buildModalGrid(items, current, modalSearch.value);
		});
	}

	if (modalClear) {
		modalClear.addEventListener('click', function() {
			if (activeField) clearLayerChoice(activeField);
			closeModal();
		});
	}

	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && modal && modal.style.display !== 'none') closeModal();
	});

	// ── Quick-jump pills ─────────────────────────────────────────
	(function() {
		var pills = document.querySelectorAll('.pset-quickjump__pill');
		pills.forEach(function(pill) {
			pill.addEventListener('click', function(e) {
				var slug   = pill.getAttribute('data-section');
				var body   = document.getElementById('pset-body-' + slug);
				var toggle = document.querySelector('[data-pset-toggle="' + slug + '"]');
				// Expand the section if collapsed
				if (body && body.classList.contains('pset-card__body--collapsed')) {
					body.classList.remove('pset-card__body--collapsed');
					var btn = toggle ? toggle.querySelector('.pset-collapse-btn') : null;
					if (btn) btn.setAttribute('aria-expanded', 'true');
					try {
						var c = JSON.parse(localStorage.getItem('pset_collapsed_sections') || '{}');
						delete c[slug];
						localStorage.setItem('pset_collapsed_sections', JSON.stringify(c));
					} catch(err) {}
				}
				pills.forEach(function(p) { p.classList.remove('pset-quickjump__pill--active'); });
				pill.classList.add('pset-quickjump__pill--active');
			});
		});

		// Highlight pill when section scrolls into view — accounts for sticky nav height
		if ('IntersectionObserver' in window) {
			var navEl = document.querySelector('.pset-quickjump');
			var navH  = navEl ? navEl.offsetHeight : 60;
			var topMargin = '-' + (navH + 12) + 'px';
			var io = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (!entry.isIntersecting) return;
					var id = entry.target.id.replace('pset-body-', '');
					pills.forEach(function(p) {
						p.classList.toggle('pset-quickjump__pill--active', p.getAttribute('data-section') === id);
					});
					// Scroll active pill into view within the nav row if it wraps
					var activePill = document.querySelector('.pset-quickjump__pill--active');
					if (activePill) { activePill.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
				});
			}, { rootMargin: topMargin + ' 0px -55% 0px', threshold: 0 });
			document.querySelectorAll('[id^="pset-body-"]').forEach(function(el) { io.observe(el); });
		}
	})();

	(function() {
		var fileInput  = document.getElementById('pset-import-file');
		var importBtn  = document.getElementById('pset-import-btn');
		if (!fileInput || !importBtn) return;
		fileInput.addEventListener('change', function() {
			importBtn.disabled = !fileInput.files.length;
			if (fileInput.files.length) {
				fileInput.closest('label').querySelector('.dashicons').className = 'dashicons dashicons-yes-alt';
			}
		});
	})();

	// ── Spacing slider ↔ text sync ──────────────────────────
	document.querySelectorAll('.pset-spacing-range').forEach(function(range) {
		var textId  = range.getAttribute('data-target');
		var labelId = range.getAttribute('data-label');
		var text    = document.getElementById(textId);
		var label   = document.getElementById(labelId);
		if (!text) return;
		range.addEventListener('input', function() {
			text.value = range.value + 'px';
			if (label) label.textContent = '(' + range.value + 'px)';
		});
		text.addEventListener('input', function() {
			var num = parseInt(text.value, 10);
			if (!isNaN(num) && num >= 0 && num <= parseInt(range.max, 10)) {
				range.value = num;
				if (label) label.textContent = '(' + num + 'px)';
			}
		});
		text.addEventListener('blur', function() {
			if (text.value && !/[a-z%]/i.test(text.value)) {
				text.value = text.value + 'px';
			}
		});
	});

	// ── Portion checkboxes — visual toggle ───────────────────
	document.querySelectorAll('.pset-portion-box').forEach(function(box) {
		var cb = box.querySelector('input[type=checkbox]');
		if (!cb || cb.disabled) return;
		box.addEventListener('click', function() {
			cb.checked = !cb.checked;
			box.classList.toggle('pset-portion-box--on', cb.checked);
		});
	});

	// ── Logo WP Media Picker ─────────────────────────────────
	(function() {
		var selectBtn = document.getElementById('pset-logo-select-btn');
		var removeBtn = document.getElementById('pset-logo-remove-btn');
		var urlInput  = document.getElementById('pset-logo-url-input');
		var preview   = document.getElementById('pset-logo-preview');
		if (!selectBtn || !urlInput || !preview || typeof wp === 'undefined' || !wp.media) return;
		var frame;
		selectBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: 'Select or Upload Logo', button: { text: 'Use this image' }, multiple: false, library: { type: 'image' } });
			frame.on('select', function() {
				var att = frame.state().get('selection').first().toJSON();
				urlInput.value = att.url;
				preview.innerHTML = '<img src="' + escHtml(att.url) + '" alt="Logo" style="max-height:60px;max-width:200px;border-radius:4px;border:1px solid #e0e3e7;">';
				selectBtn.innerHTML = '<span class="dashicons dashicons-upload"></span> Change Logo';
				if (removeBtn) removeBtn.style.display = '';
			});
			frame.open();
		});
		if (removeBtn) {
			removeBtn.addEventListener('click', function(e) {
				e.preventDefault();
				urlInput.value = '';
				preview.innerHTML = '<span class="pset-logo-picker__placeholder"><span class="dashicons dashicons-format-image"></span> No logo selected</span>';
				selectBtn.innerHTML = '<span class="dashicons dashicons-upload"></span> Select / Upload Logo';
				removeBtn.style.display = 'none';
			});
		}
	})();

});
