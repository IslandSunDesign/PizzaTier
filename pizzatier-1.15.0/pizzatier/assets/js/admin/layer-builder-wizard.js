/* PizzaTier Layer Builder Wizard — admin JS */
/* eslint-disable no-var */
	(function($){
		'use strict';

		var nonce      = (window.pizzatierLBW && window.pizzatierLBW.nonce)      || '';
		var ajaxUrl    = (window.pizzatierLBW && window.pizzatierLBW.ajaxUrl)    || '';
		var limUrl     = (window.pizzatierLBW && window.pizzatierLBW.limUrl)     || '';
		var layerTypes = (window.pizzatierLBW && window.pizzatierLBW.layerTypes) || {};
		var i18n       = (window.pizzatierLBW && window.pizzatierLBW.i18n)       || {};

		// Provide English fallbacks if any localized string is missing
		function t(key, fallback) {
			return (i18n && typeof i18n[key] === 'string' && i18n[key].length) ? i18n[key] : fallback;
		}

		/* ── State ─────────────────────────────────────────── */
		var state = {
			step     : 1,
			typeSlug : '',
			typeLabel: '',
			typeCpt  : '',
			typeColor: '',
			typeExtra: [],
			name     : '',
			slug     : '',
			desc     : '',
			imageId  : 0,
			imageUrl : '',
			meta     : {}
		};

		/* ── Step navigation ───────────────────────────────── */
		function goStep(n) {
			state.step = n;
			$('.plbw-panel').hide();
			$('#plbw-panel-' + n).show();
			$('.plbw-step').removeClass('is-active is-done');
			for (var i = 1; i < n; i++) {
				$('.plbw-step[data-step="' + i + '"]').addClass('is-done');
			}
			$('.plbw-step[data-step="' + n + '"]').addClass('is-active');
			$('#plbw-progress').attr('aria-valuenow', n);
			$('html,body').animate({ scrollTop: 0 }, 200);

			if (n === 4) { buildReview(); }
		}

		/* ── Step 1: type selection ────────────────────────── */
		$(document).on('click', '.plbw-type-card', function() {
			$('.plbw-type-card').removeClass('is-selected');
			$(this).addClass('is-selected');
			state.typeSlug  = $(this).data('type');
			state.typeLabel = $(this).data('label');
			state.typeCpt   = $(this).data('cpt');
			state.typeColor = $(this).data('color');
			state.typeExtra = $(this).data('extra') || [];
			$('#plbw-step1-next').prop('disabled', false);
		});

		$('#plbw-step1-next').on('click', function() {
			if (!state.typeSlug) { return; }
			$('#plbw-step2-title').text(t('detailsFor', 'Details for your') + ' ' + state.typeLabel);
			showExtraFields(state.typeSlug);
			goStep(2);
		});

		function showExtraFields(typeSlug) {
			$('.plbw-extra').each(function(){
				var forTypes = ($(this).data('for') || '').split(' ');
				if (forTypes.indexOf(typeSlug) !== -1) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		}

		/* ── Step 2: details ───────────────────────────────── */
		$('#plbw-name').on('input', function(){
			state.name = $(this).val();
			if (!$('#plbw-slug').data('manual')) {
				var slug = state.name
					.toLowerCase()
					.replace(/[^a-z0-9\s\-]/g, '')
					.replace(/\s+/g, '-')
					.replace(/-+/g, '-')
					.substring(0, 60);
				$('#plbw-slug').val(slug);
				state.slug = slug;
			}
		});
		$('#plbw-slug').on('input', function(){
			$(this).data('manual', $(this).val() !== '');
			state.slug = $(this).val();
		});
		$('#plbw-description').on('input', function(){ state.desc = $(this).val(); });

		$('#plbw-step2-next').on('click', function(){
			state.name = $.trim($('#plbw-name').val());
			state.slug = $.trim($('#plbw-slug').val());
			state.desc = $.trim($('#plbw-description').val());
			if (!state.name) {
				$('#plbw-name').focus().closest('.plbw-field-row').addClass('plbw-field-error');
				return;
			}
			$('#plbw-name').closest('.plbw-field-row').removeClass('plbw-field-error');
			state.meta = {};
			if ($('#plbw-calories').val())   { state.meta.calories        = $('#plbw-calories').val(); }
			if ($('#plbw-thickness').val())  { state.meta.thickness       = $('#plbw-thickness').val(); }
			if ($('#plbw-diameter').val())   { state.meta.diameter_inches = $('#plbw-diameter').val(); }
			if ($('#plbw-spice').val())      { state.meta.spice_level     = $('#plbw-spice').val(); }
			if ($('#plbw-is-vegetarian').is(':checked')) { state.meta.is_vegetarian = '1'; }
			if ($('#plbw-is-vegan').is(':checked'))      { state.meta.is_vegan       = '1'; }
			if ($('#plbw-is-gf').is(':checked'))         { state.meta.is_gluten_free = '1'; }
			if ($('#plbw-is-dairyfree').is(':checked'))  { state.meta.is_dairy_free  = '1'; }
			state.meta.sort_order = $('#plbw-sort-order').val() || '0';
			goStep(3);
		});

		$(document).on('click', '.plbw-back-btn', function(){
			goStep( parseInt($(this).data('target'), 10) );
		});

		/* ── Step 3: image ─────────────────────────────────── */
		var mediaFrame = null;

		function openMedia(mode) {
			if (mediaFrame) { mediaFrame.off('select'); }
			mediaFrame = wp.media({
				title  : mode === 'upload' ? t('uploadLayerImage', 'Upload Layer Image') : t('chooseLayerImage', 'Choose Layer Image'),
				button : { text: t('useThisImage', 'Use this image') },
				library: mode === 'upload' ? { type: 'image', uploadedTo: null } : { type: 'image' },
				multiple: false
			});
			if (mode === 'upload') { mediaFrame.on('open', function(){ mediaFrame.state().get('selection').reset(); }); }
			mediaFrame.on('select', function(){
				var att = mediaFrame.state().get('selection').first().toJSON();
				setImage(att.id, att.url);
			});
			mediaFrame.open();
		}

		function setImage(id, url) {
			state.imageId  = id;
			state.imageUrl = url;
			$('#plbw-image-id').val(id);
			$('#plbw-image-url').val(url);
			$('#plbw-image-preview').html('<img src="' + escAttr(url) + '" alt="" style="max-width:100%;max-height:200px;border-radius:4px;">');
			$('#plbw-remove-image').show();
		}

		function noImagePlaceholder() {
			return '<span class="dashicons dashicons-format-image plbw-img-icon"></span><p>' + escHtml(t('noImageSelected', 'No image selected')) + '</p>';
		}

		$('#plbw-choose-image').on('click', function(){ openMedia('library'); });
		$('#plbw-upload-image').on('click', function(){ openMedia('upload'); });
		$('#plbw-remove-image').on('click', function(){
			state.imageId  = 0;
			state.imageUrl = '';
			$('#plbw-image-id').val('');
			$('#plbw-image-url').val('');
			$('#plbw-image-preview').html(noImagePlaceholder());
			$(this).hide();
		});
		$('#plbw-open-lim').on('click', function(){
			window.open(limUrl, '_blank');
		});
		$('#plbw-step3-next').on('click', function(){ goStep(4); });

		/* ── Step 4: review ────────────────────────────────── */
		function buildReview() {
			var typeInfo = layerTypes[state.typeSlug] || {};
			var slugVal  = state.slug || slugify(state.name);
			var html = '';
			html += '<div class="plbw-review-type" style="--plbw-accent:' + escAttr(state.typeColor) + '">';
			html += '<span class="plbw-review-emoji">' + escHtml(typeInfo.emoji || '') + '</span>';
			html += '<span class="plbw-review-type-label">' + escHtml(state.typeLabel) + '</span>';
			html += '</div>';

			html += '<table class="plbw-review-table">';
			html += reviewRow(escHtml(t('name', 'Name')), escHtml(state.name));
			html += reviewRow(escHtml(t('slug', 'Slug')), '<code>' + escHtml(slugVal) + '</code>');
			if (state.desc) {
				html += reviewRow(escHtml(t('description', 'Description')), escHtml(state.desc));
			}
			if (parseFloat(state.price) > 0) {
				html += reviewRow(escHtml(t('priceModifier', 'Price modifier')), escHtml(state.price));
			}
			if (state.meta.thickness)       { html += reviewRow(escHtml(t('thickness', 'Thickness')), escHtml(state.meta.thickness)); }
			if (state.meta.calories)        { html += reviewRow(escHtml(t('calories', 'Calories')),   escHtml(state.meta.calories)); }
			if (state.meta.diameter_inches) { html += reviewRow(escHtml(t('diameter', 'Diameter')),   escHtml(state.meta.diameter_inches) + '″'); }
			if (state.meta.spice_level)     { html += reviewRow(escHtml(t('spiceLevel', 'Spice level')), escHtml(state.meta.spice_level)); }

			var flags = [];
			if (state.meta.is_vegetarian) { flags.push(t('vegetarian', 'Vegetarian')); }
			if (state.meta.is_vegan)      { flags.push(t('vegan', 'Vegan')); }
			if (state.meta.is_gluten_free){ flags.push(t('glutenFree', 'Gluten-Free')); }
			if (state.meta.is_dairy_free) { flags.push(t('dairyFree', 'Dairy-Free')); }
			if (flags.length) {
				html += reviewRow(escHtml(t('dietary', 'Dietary')), escHtml(flags.join(', ')));
			}

			if (state.imageUrl) {
				html += reviewRow(escHtml(t('image', 'Image')), '<img src="' + escAttr(state.imageUrl) + '" style="max-height:80px;border-radius:4px;vertical-align:middle;">');
			} else {
				html += reviewRow(escHtml(t('image', 'Image')), '<em>' + escHtml(t('imageNoneCanAddLater', 'None (can be added later)')) + '</em>');
			}
			html += '</table>';

			$('#plbw-review-card').html(html);
		}
		function reviewRow(label, value) {
			return '<tr><th>' + label + '</th><td>' + value + '</td></tr>';
		}

		/* ── Save ──────────────────────────────────────────── */
		$('#plbw-save-btn').on('click', function(){
			var $btn = $(this);
			$btn.prop('disabled', true);
			$('#plbw-saving-overlay').show();

			var slugVal = state.slug || slugify(state.name);

			$.post(ajaxUrl, {
				action  : 'pizzatier_wizard_save_layer',
				nonce   : nonce,
				type    : state.typeSlug,
				cpt     : state.typeCpt,
				name    : state.name,
				slug    : slugVal,
				desc    : state.desc,
				image_id: state.imageId,
				meta    : JSON.stringify(state.meta)
			}, function(resp){
				$('#plbw-saving-overlay').hide();
				$btn.prop('disabled', false);

				if (resp.success) {
					showSuccess(resp.data);
				} else {
					alert(t('errorSavingLayer', 'Error saving layer:') + ' ' + (resp.data && resp.data.message ? resp.data.message : t('unknownError', 'Unknown error.')));
				}
			}).fail(function(){
				$('#plbw-saving-overlay').hide();
				$btn.prop('disabled', false);
				alert(t('networkError', 'Network error. Please try again.'));
			});
		});

		function showSuccess(data) {
			$('.plbw-panel').hide();
			var html = '';
			html += '<div class="plbw-success-check"><span class="dashicons dashicons-yes-alt"></span></div>';
			html += '<h2 class="plbw-success-title">' + escHtml(data.name) + ' ' + escHtml(t('wasSaved', 'was saved!')) + '</h2>';
			html += '<p>' + escHtml(t('successDesc', 'Your new layer has been created. Use the shortcode below to include it on any page.')) + '</p>';

			html += '<div class="plbw-shortcode-box">';
			html += '<code id="plbw-shortcode-output">' + escHtml(data.shortcode) + '</code>';
			html += '<button type="button" class="button plbw-copy-btn" data-clipboard="' + escAttr(data.shortcode) + '">';
			html += '<span class="dashicons dashicons-clipboard"></span> ' + escHtml(t('copy', 'Copy'));
			html += '</button>';
			html += '</div>';

			html += '<div class="plbw-success-actions">';
			html += '<a href="' + escAttr(data.edit_url || '') + '" class="button button-primary">';
			html += '<span class="dashicons dashicons-edit"></span> ' + escHtml(t('editLayer', 'Edit Layer')) + '</a> ';
			html += '<a href="' + escAttr(data.list_url || '') + '" class="button">';
			html += '<span class="dashicons dashicons-list-view"></span> ' + escHtml(t('all', 'All')) + ' ' + escHtml(state.typeLabel + 's') + '</a> ';
			html += '<button type="button" class="button" id="plbw-build-another">';
			html += '<span class="dashicons dashicons-plus-alt2"></span> ' + escHtml(t('buildAnotherLayer', 'Build Another Layer')) + '</button>';
			html += '</div>';

			$('#plbw-success-inner').html(html);
			$('#plbw-success-panel').show();
			$('html,body').animate({ scrollTop: 0 }, 200);

			$('.plbw-step').addClass('is-done').removeClass('is-active');
		}

		$(document).on('click', '#plbw-build-another', function(){
			state = { step:1, typeSlug:'', typeLabel:'', typeCpt:'', typeColor:'', typeExtra:[], name:'', slug:'', desc:'', imageId:0, imageUrl:'', meta:{} };
			$('.plbw-type-card').removeClass('is-selected');
			$('#plbw-name,#plbw-slug,#plbw-description').val('');
			$('#plbw-sort-order').val('0');
			$('#plbw-image-id,#plbw-image-url').val('');
			$('#plbw-image-preview').html(noImagePlaceholder());
			$('#plbw-remove-image').hide();
			$('#plbw-step1-next').prop('disabled', true);
			$('#plbw-slug').data('manual', false);
			$('.plbw-field-error').removeClass('plbw-field-error');
			$('#plbw-success-panel').hide();
			goStep(1);
		});

		$(document).on('click', '.plbw-copy-btn', function(){
			var text = $(this).data('clipboard');
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text);
			} else {
				var ta = document.createElement('textarea');
				ta.value = text; document.body.appendChild(ta);
				ta.select(); document.execCommand('copy');
				document.body.removeChild(ta);
			}
			$(this).text(t('copied', 'Copied!')).addClass('plbw-copied');
			var $btn = $(this);
			setTimeout(function(){ $btn.html('<span class="dashicons dashicons-clipboard"></span> ' + escHtml(t('copy', 'Copy'))).removeClass('plbw-copied'); }, 1800);
		});

		/* ── Helpers ───────────────────────────────────────── */
		function slugify(s) {
			return (s || '').toLowerCase().replace(/[^a-z0-9\s\-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-').substring(0,60);
		}
		function escHtml(s) {
			return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
		}
		function escAttr(s) { return escHtml(s); }

	})(jQuery);
