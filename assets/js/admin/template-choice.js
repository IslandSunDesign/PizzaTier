( function () {
	'use strict';

	/* Wait for DOM — script is in footer but be defensive */
	function init() {

		/* ── Elements ─────────────────────────────────────────────── */
		var frame      = document.getElementById( 'ptc-preview-frame' );
		var loading    = document.getElementById( 'ptc-iframe-loading' );
		var label      = document.getElementById( 'ptc-preview-label' );
		var reloadBtn  = document.getElementById( 'ptc-preview-reload' );
		var modal      = document.getElementById( 'ptc-modal' );
		var modalName  = document.getElementById( 'ptc-modal-name' );
		var modalSlug  = document.getElementById( 'ptc-modal-slug' );
		var cancelBtn  = document.getElementById( 'ptc-modal-cancel' );
		var overlay    = document.getElementById( 'ptc-modal-overlay' );
		var editUrlBtn = document.getElementById( 'ptc-edit-preview-url' );
		var urlBar     = document.getElementById( 'ptc-preview-url-bar' );
		var cancelUrl  = document.getElementById( 'ptc-cancel-preview-url' );
		var items      = document.querySelectorAll( '.ptc-item' );

		/* If the split-pane layout isn't present, nothing to do */
		if ( ! frame ) { return; }

		/* ── Preview URL editor toggle ───────────────────────────── */
		if ( editUrlBtn && urlBar ) {
			editUrlBtn.addEventListener( 'click', function () {
				urlBar.style.display = urlBar.style.display === 'none' ? '' : 'none';
			} );
		}
		if ( cancelUrl && urlBar ) {
			cancelUrl.addEventListener( 'click', function () {
				urlBar.style.display = 'none';
			} );
		}

		/* ── Loading overlay helpers ─────────────────────────────── */
		var loadTimer = null;

		function showLoading() {
			if ( loading ) {
				loading.style.display   = 'flex';
				loading.style.opacity   = '1';
				loading.style.pointerEvents = 'auto';
			}
			frame.style.opacity = '0.25';
		}

		function hideLoading() {
			if ( loading ) {
				loading.style.opacity      = '0';
				loading.style.pointerEvents = 'none';
				/* Delay hiding so fade-out completes before display:none */
				setTimeout( function () {
					if ( loading.style.opacity === '0' ) {
						loading.style.display = 'none';
					}
				}, 250 );
			}
			frame.style.opacity = '1';
		}

		/* Hide spinner once iframe fires load */
		frame.addEventListener( 'load', function () {
			clearTimeout( loadTimer );
			setTimeout( hideLoading, 100 );
		} );
		frame.addEventListener( 'error', function () {
			clearTimeout( loadTimer );
			hideLoading();
		} );

		/* ── Load a URL into the preview iframe ──────────────────── */
		function loadPreview( url, templateName ) {
			showLoading();
			/* Safety timeout: if iframe never fires load, clear spinner anyway */
			clearTimeout( loadTimer );
			loadTimer = setTimeout( hideLoading, 10000 );

			/* Only update src if the URL actually changed */
			if ( frame.src !== url ) {
				frame.src = url;
			}

			if ( label ) {
				label.textContent = ( templateName || 'Template' ) + ' — Live Preview';
			}
		}

		/* ── Wire up template item rows ──────────────────────────── */
		// Preview button click — explicit, no hover
		document.querySelectorAll( '.ptc-preview-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				var url  = btn.getAttribute( 'data-preview-url' );
				var name = btn.getAttribute( 'data-name' );
				if ( ! url ) { return; }

				// Mark this item as previewing
				document.querySelectorAll( '.ptc-item' ).forEach( function ( el ) {
					el.classList.remove( 'ptc-item--previewing' );
				} );
				var item = btn.closest( '.ptc-item' );
				if ( item ) { item.classList.add( 'ptc-item--previewing' ); }

				loadPreview( url, name );
			} );
		} );

		/* ── Reload button ───────────────────────────────────────── */
		if ( reloadBtn ) {
			reloadBtn.addEventListener( 'click', function () {
				var src = frame.getAttribute( 'src' ) || '';
				if ( ! src ) { return; }
				showLoading();
				clearTimeout( loadTimer );
				loadTimer = setTimeout( hideLoading, 10000 );
				frame.src = '';
				setTimeout( function () { frame.src = src; }, 50 );
			} );
		}

		/* ── Activate modal ──────────────────────────────────────── */
		function openModal( name, slug ) {
			if ( modalName ) { modalName.textContent = name; }
			if ( modalSlug ) { modalSlug.value       = slug; }
			if ( modal )     { modal.style.display   = ''; }
			document.body.style.overflow = 'hidden';
		}

		function closeModal() {
			if ( modal ) { modal.style.display = 'none'; }
			document.body.style.overflow = '';
		}

		document.querySelectorAll( '.ptc-activate-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				openModal( btn.getAttribute( 'data-name' ), btn.getAttribute( 'data-slug' ) );
			} );
		} );

		if ( cancelBtn ) { cancelBtn.addEventListener( 'click', closeModal ); }
		if ( overlay )   { overlay.addEventListener(   'click', closeModal ); }

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) { closeModal(); }
		} );

		/* ── Trigger initial load of active template ─────────────── */
		/* The iframe already has src set in PHP, but call showLoading
		   so the spinner appears until it fires the load event */
		if ( frame.src && frame.src !== 'about:blank' && frame.src !== window.location.href ) {
			showLoading();
			clearTimeout( loadTimer );
			loadTimer = setTimeout( hideLoading, 10000 );
		}
	}

	/* Run after DOM is ready */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();

/* ── Color scheme chip handler (appended from TemplateChoice.php) ──────── */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {

		// Chip preset click handler
		document.querySelectorAll('.ptc-scheme-chip').forEach(function(chip) {
			chip.addEventListener('click', function() {
				var data;
				try { data = JSON.parse(chip.getAttribute('data-scheme')); } catch(e) { return; }
				if (Array.isArray(data)) {
					// Legacy metro positional array
					var legacyKeys = ['metro_setting_accent_color','metro_setting_background_color','metro_setting_card_bg_color'];
					data.forEach(function(hex, i) {
						var inp = document.getElementById('ptc-color-' + legacyKeys[i]);
						if (inp) { inp.value = hex; inp.dispatchEvent(new Event('change')); }
					});
				} else if (data && typeof data === 'object') {
					Object.keys(data).forEach(function(optKey) {
						var val = data[optKey];
						var colorInp = document.getElementById('ptc-color-' + optKey);
						if (colorInp) {
							colorInp.value = val;
							colorInp.dispatchEvent(new Event('change'));
						} else {
							var anyInp = document.querySelector('[name="' + optKey + '"]');
							if (anyInp) { anyInp.value = val; anyInp.dispatchEvent(new Event('change')); }
						}
					});
				}
				document.querySelectorAll('.ptc-scheme-chip').forEach(function(c) { c.classList.remove('ptc-scheme-chip--active'); });
				chip.classList.add('ptc-scheme-chip--active');
			});
		});

		// Color revert buttons
		document.querySelectorAll('.ptc-color-revert').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var def = btn.getAttribute('data-default');
				var tid = btn.getAttribute('data-target');
				var inp = document.getElementById(tid);
				if (inp && def) { inp.value = def; inp.dispatchEvent(new Event('change')); }
			});
		});

		// Image picker (uses the WordPress media frame; falls back to manual URL entry)
		function ptcSetImage(targetId, previewId, url) {
			var inp = document.getElementById(targetId);
			if (inp) { inp.value = url; inp.dispatchEvent(new Event('change')); }
			var prev = previewId ? document.getElementById(previewId) : null;
			if (prev) {
				var img = prev.querySelector('img');
				if (url) {
					if (img) { img.setAttribute('src', url); }
					prev.style.display = '';
				} else {
					if (img) { img.setAttribute('src', ''); }
					prev.style.display = 'none';
				}
			}
			var wrap = inp ? inp.closest('.ptc-image-wrap') : null;
			if (wrap) {
				var rm = wrap.querySelector('.ptc-image-remove');
				if (rm) { rm.style.display = url ? '' : 'none'; }
			}
		}

		document.querySelectorAll('.ptc-image-choose').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var targetId  = btn.getAttribute('data-target');
				var previewId = btn.getAttribute('data-preview');
				// Require the WP media library; if unavailable, focus the URL field.
				if (typeof wp === 'undefined' || !wp.media) {
					var manual = document.getElementById(targetId);
					if (manual) { manual.focus(); }
					return;
				}
				var frame = wp.media({
					title: 'Select or Upload Background Image',
					button: { text: 'Use this image' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function() {
					var att = frame.state().get('selection').first().toJSON();
					var url = (att.sizes && att.sizes.large) ? att.sizes.large.url : att.url;
					ptcSetImage(targetId, previewId, url || '');
				});
				frame.open();
			});
		});

		document.querySelectorAll('.ptc-image-remove').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				ptcSetImage(btn.getAttribute('data-target'), btn.getAttribute('data-preview'), '');
			});
		});

		// Keep preview in sync when a URL is typed/pasted directly
		document.querySelectorAll('.ptc-image__url').forEach(function(inp) {
			inp.addEventListener('change', function() {
				var wrap = inp.closest('.ptc-image-wrap');
				if (!wrap) { return; }
				var prev = wrap.querySelector('.ptc-image__preview');
				var rm   = wrap.querySelector('.ptc-image-remove');
				var val  = inp.value.trim();
				if (prev) {
					var img = prev.querySelector('img');
					if (val) { if (img) { img.setAttribute('src', val); } prev.style.display = ''; }
					else { prev.style.display = 'none'; }
				}
				if (rm) { rm.style.display = val ? '' : 'none'; }
			});
		});
	});

} )();
