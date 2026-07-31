/* PizzaTier Layer Image Meta Box — admin JS */
/* eslint-disable no-var */
(function() {
	'use strict';

	// Boot all metabox instances on the page
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.pzlmb-wrap[data-post-id]').forEach(function(wrap) {
			initPzlMetaBox(wrap);
		});
	});

	function initPzlMetaBox($wrap) {
		if (!$wrap) return;

		// Derive slug from wrap id: 'pzlmb-wrap-{slug}'
		var slug = ($wrap.id || '').replace(/^pzlmb-wrap-/, '');

		var postId    = $wrap.dataset.postId;
		var fieldKey  = $wrap.dataset.fieldKey;
		var ajaxUrl   = $wrap.dataset.ajax;
		var nonce     = $wrap.dataset.nonce;
		var metaNonce = $wrap.dataset.metaNonce;
		var aspectStr = $wrap.dataset.aspect || '4/3';
		var aspectParts = aspectStr.split('/');
		var ASP_W = parseFloat(aspectParts[0]) || 4;
		var ASP_H = parseFloat(aspectParts[1]) || 3;

		// All elements scoped to this wrap (multiple metaboxes can exist on multi-edit screens)
		var $prompt   = $wrap.querySelector('.pzlmb-prompt');
		var $editor   = $wrap.querySelector('.pzlmb-editor');
		var $openBtn  = $wrap.querySelector('.pzlmb-open-btn');
		var $stage    = $wrap.querySelector('.pzlmb-stage');
		var $empty    = $wrap.querySelector('.pzlmb-canvas-empty');
		var $status   = $wrap.querySelector('.pzlmb-status');
		var $currImg  = $wrap.querySelector('.pzlmb-current-img');

		var $drop     = $wrap.querySelector('.pzlmb-drop-zone');
		var $fileIn   = $wrap.querySelector('.pzlmb-file-input');
		var $mediaBtn = $wrap.querySelector('.pzlmb-media-btn');
		var $canvas   = $wrap.querySelector('.pzlmb-canvas');
		var $guide    = $wrap.querySelector('.pzlmb-guide-svg');
		var $setBtn   = $wrap.querySelector('.pzlmb-set-btn');
		var $cancelBtn= $wrap.querySelector('.pzlmb-cancel-btn');
		var $resetAdj = $wrap.querySelector('.pzlmb-reset-adj');
		var $showGuide= $wrap.querySelector('.pzlmb-show-guide');
		var ctx       = $canvas ? $canvas.getContext('2d') : null;

		// ── State ────────────────────────────────────────────────────
		var st = {
			imgEl: null, rotation: 0, flipH: false, flipV: false,
			brightness: 0, contrast: 0, saturation: 0, opacity: 100,
			cropX: 0.08, cropY: 0.08, cropW: 0.84, cropH: 0.84,
			showGuide: true
		};

		function resetCrop(){
			var asp = ASP_W / ASP_H;
			var maxW = 0.88, maxH = 0.88;
			var w = maxW, h = maxW / asp;
			if (h > maxH) { h = maxH; w = maxH * asp; }
			st.cropW = Math.min(w, 1); st.cropH = Math.min(h, 1);
			st.cropX = (1 - st.cropW) / 2; st.cropY = (1 - st.cropH) / 2;
		}
		resetCrop();

		// ── Open / close ─────────────────────────────────────────────
		if ($openBtn) {
			$openBtn.addEventListener('click', function(){
				if ($prompt) $prompt.style.display = 'none';
				if ($editor) $editor.style.display = 'block';
			});
		}
		if ($cancelBtn) {
			$cancelBtn.addEventListener('click', function(){
				if ($editor) $editor.style.display = 'none';
				if ($prompt) $prompt.style.display = 'block';
			});
		}

		// ── Image loading ────────────────────────────────────────────
		function loadSrc(src){
			var img = new Image();
			img.onload = function(){
				st.imgEl = img;
				st.rotation = 0; st.flipH = false; st.flipV = false;
				resetCrop();
				showCanvas();
				render();
				if ($setBtn) $setBtn.disabled = false;
			};
			img.src = src;
		}

		function showCanvas(){
			if ($empty)  $empty.style.display  = 'none';
			if ($canvas) $canvas.style.display = 'block';
			if ($guide)  $guide.style.display  = 'block';
		}

		// ── Drop zone ────────────────────────────────────────────────
		if ($drop) {
			$drop.addEventListener('click', function(){ if ($fileIn) $fileIn.click(); });
			$drop.addEventListener('keydown', function(e){
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if ($fileIn) $fileIn.click(); }
			});
			$drop.addEventListener('dragover', function(e){ e.preventDefault(); $drop.classList.add('pzlmb-drop-zone--over'); });
			$drop.addEventListener('dragleave', function(){ $drop.classList.remove('pzlmb-drop-zone--over'); });
			$drop.addEventListener('drop', function(e){
				e.preventDefault();
				$drop.classList.remove('pzlmb-drop-zone--over');
				var f = e.dataTransfer.files[0];
				if (f && f.type.indexOf('image/') === 0) { readFile(f); }
			});
		}
		if ($fileIn) {
			$fileIn.addEventListener('change', function(){ if ($fileIn.files[0]) readFile($fileIn.files[0]); });
		}
		function readFile(f){
			var r = new FileReader();
			r.onload = function(e){ loadSrc(e.target.result); };
			r.readAsDataURL(f);
		}

		// ── Media library ────────────────────────────────────────────
		if ($mediaBtn) {
			$mediaBtn.addEventListener('click', function(){
				if (typeof wp === 'undefined' || !wp.media) return;
				var frame = wp.media({
					title  : 'Choose Image',
					button : { text: 'Use Image' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					loadSrc(att.url);
				});
				frame.open();
			});
		}

		// ── Render ───────────────────────────────────────────────────
		function getRotW(){ var img = st.imgEl; if (!img) return 1; return (st.rotation % 180 === 0) ? img.naturalWidth  : img.naturalHeight; }
		function getRotH(){ var img = st.imgEl; if (!img) return 1; return (st.rotation % 180 === 0) ? img.naturalHeight : img.naturalWidth;  }

		function render(){
			if (!st.imgEl || !ctx) return;
			var dw = getRotW(), dh = getRotH();
			$canvas.width = dw; $canvas.height = dh;
			ctx.save();
			ctx.clearRect(0, 0, dw, dh);
			ctx.translate(dw / 2, dh / 2);
			if (st.flipH) ctx.scale(-1, 1);
			if (st.flipV) ctx.scale(1, -1);
			ctx.rotate(st.rotation * Math.PI / 180);
			ctx.drawImage(st.imgEl, -st.imgEl.naturalWidth / 2, -st.imgEl.naturalHeight / 2);
			ctx.restore();
			var f = '';
			if (st.brightness !== 0) f += 'brightness(' + (1 + st.brightness / 100) + ') ';
			if (st.contrast   !== 0) f += 'contrast('   + (1 + st.contrast   / 100) + ') ';
			if (st.saturation !== 0) f += 'saturate('   + (1 + st.saturation / 100) + ') ';
			$canvas.style.filter  = f.trim() || 'none';
			$canvas.style.opacity = st.opacity / 100;
			drawGuide();
		}

		// ── Guide ────────────────────────────────────────────────────
		function drawGuide(){
			if (!$guide || !$canvas) return;
			var cw = $canvas.offsetWidth  || $canvas.width  || 1;
			var ch = $canvas.offsetHeight || $canvas.height || 1;
			$guide.setAttribute('viewBox', '0 0 ' + cw + ' ' + ch);
			$guide.setAttribute('width', cw);
			$guide.setAttribute('height', ch);
			$guide.innerHTML = '';
			if (!st.imgEl || !st.showGuide) return;
			var cx = st.cropX * cw, cy = st.cropY * ch, crw = st.cropW * cw, crh = st.cropH * ch;
			var mask = mkSVG('path');
			mask.setAttribute('fill', 'rgba(0,0,0,0.45)');
			mask.setAttribute('fill-rule', 'evenodd');
			mask.setAttribute('d', 'M 0 0 L ' + cw + ' 0 L ' + cw + ' ' + ch + ' L 0 ' + ch + ' Z M ' + cx + ' ' + cy + ' L ' + (cx + crw) + ' ' + cy + ' L ' + (cx + crw) + ' ' + (cy + crh) + ' L ' + cx + ' ' + (cy + crh) + ' Z');
			$guide.appendChild(mask);
			var rect = mkSVG('rect');
			rect.setAttribute('x', cx); rect.setAttribute('y', cy);
			rect.setAttribute('width', crw); rect.setAttribute('height', crh);
			rect.setAttribute('fill', 'none'); rect.setAttribute('stroke', '#ff6b35');
			rect.setAttribute('stroke-width', '1.5');
			$guide.appendChild(rect);
			var pr = Math.min(crw, crh) * 0.43;
			var ellipse = mkSVG('ellipse');
			ellipse.setAttribute('cx', cx + crw / 2); ellipse.setAttribute('cy', cy + crh / 2);
			ellipse.setAttribute('rx', pr * 1.1);     ellipse.setAttribute('ry', pr);
			ellipse.setAttribute('fill', 'none');
			ellipse.setAttribute('stroke', 'rgba(255,200,0,0.5)');
			ellipse.setAttribute('stroke-width', '1');
			ellipse.setAttribute('stroke-dasharray', '5 3');
			$guide.appendChild(ellipse);
			for (var i = 1; i < 3; i++) {
				var lv = mkSVG('line');
				lv.setAttribute('x1', cx + crw * i / 3); lv.setAttribute('y1', cy);
				lv.setAttribute('x2', cx + crw * i / 3); lv.setAttribute('y2', cy + crh);
				lv.setAttribute('stroke', 'rgba(255,255,255,0.2)'); lv.setAttribute('stroke-width', '1');
				$guide.appendChild(lv);
				var lh = mkSVG('line');
				lh.setAttribute('x1', cx); lh.setAttribute('y1', cy + crh * i / 3);
				lh.setAttribute('x2', cx + crw); lh.setAttribute('y2', cy + crh * i / 3);
				lh.setAttribute('stroke', 'rgba(255,255,255,0.2)'); lh.setAttribute('stroke-width', '1');
				$guide.appendChild(lh);
			}
		}
		function mkSVG(tag){ return document.createElementNS('http://www.w3.org/2000/svg', tag); }

		// ── Crop drag ────────────────────────────────────────────────
		var drag = null;
		if ($guide) {
			$guide.style.pointerEvents = 'auto';
			$guide.addEventListener('mousedown', function(e){
				if (!st.imgEl) return;
				var cw = $canvas.offsetWidth || 1, ch = $canvas.offsetHeight || 1;
				var r = $guide.getBoundingClientRect();
				var nx = (e.clientX - r.left) / cw, ny = (e.clientY - r.top) / ch;
				drag = { startNx: nx, startNy: ny, origX: st.cropX, origY: st.cropY, origW: st.cropW, origH: st.cropH };
				e.preventDefault();
			});
		}
		document.addEventListener('mousemove', function(e){
			if (!drag) return;
			var cw = $canvas.offsetWidth || 1, ch = $canvas.offsetHeight || 1;
			var r = $guide.getBoundingClientRect();
			var nx = (e.clientX - r.left) / cw, ny = (e.clientY - r.top) / ch;
			st.cropX = clamp(drag.origX + (nx - drag.startNx), 0, 1 - st.cropW);
			st.cropY = clamp(drag.origY + (ny - drag.startNy), 0, 1 - st.cropH);
			drawGuide();
		});
		document.addEventListener('mouseup', function(){ drag = null; });

		// ── Mini toolbar ─────────────────────────────────────────────
		function tbtn(cls, fn){ var el = $wrap.querySelector('.' + cls); if (el) el.addEventListener('click', fn); }
		tbtn('pzlmb-tbtn--rotate-ccw', function(){ st.rotation = (st.rotation - 90 + 360) % 360; render(); });
		tbtn('pzlmb-tbtn--rotate-cw',  function(){ st.rotation = (st.rotation + 90) % 360;        render(); });
		tbtn('pzlmb-tbtn--flip-h',     function(){ st.flipH = !st.flipH; render(); });
		tbtn('pzlmb-tbtn--flip-v',     function(){ st.flipV = !st.flipV; render(); });
		if ($showGuide) {
			$showGuide.addEventListener('change', function(){ st.showGuide = this.checked; drawGuide(); });
		}

		// ── Adjustment sliders ───────────────────────────────────────
		var adjKeys = ['brightness', 'contrast', 'saturation', 'opacity'];
		adjKeys.forEach(function(key){
			var el = $wrap.querySelector('.pzlmb-slider--' + key);
			var valEl = el ? el.parentNode.querySelector('.pzlmb-adj-val') : null;
			if (el) {
				el.addEventListener('input', function(){
					st[key] = parseFloat(this.value);
					if (valEl) valEl.textContent = this.value;
					render();
				});
			}
		});
		if ($resetAdj) {
			$resetAdj.addEventListener('click', function(){
				var defs = { brightness: 0, contrast: 0, saturation: 0, opacity: 100 };
				Object.assign(st, defs);
				adjKeys.forEach(function(key){
					var el = $wrap.querySelector('.pzlmb-slider--' + key);
					var valEl = el ? el.parentNode.querySelector('.pzlmb-adj-val') : null;
					if (el) el.value = defs[key];
					if (valEl) valEl.textContent = defs[key];
				});
				render();
			});
		}

		// ── Build output canvas and upload ───────────────────────────
		if ($setBtn) {
			$setBtn.addEventListener('click', function(){
				var oc = buildOutput();
				if (!oc) { showStatus('No image loaded.', 'err'); return; }
				$setBtn.disabled = true;
				showStatus('Uploading…', '');
				oc.toBlob(function(blob){
					var fr = new FileReader();
					fr.onload = function(ev){
						var fd = new FormData();
						fd.append('action',     'pizzatier_metabox_set_layer_image');
						fd.append('nonce',      nonce);
						fd.append('meta_nonce', metaNonce);
						fd.append('post_id',    postId);
						fd.append('field_key',  fieldKey);
						fd.append('filename',   fieldKey + '-' + postId + '.png');
						fd.append('data',       ev.target.result);
						fetch(ajaxUrl, { method: 'POST', body: fd })
							.then(function(r){ return r.json(); })
							.then(function(d){
								if (d.success) {
									showStatus('✓ Layer image set!', 'ok');
									if ($currImg) {
										$currImg.src = d.data.url;
									} else if ($prompt) {
										var emptyDiv = $prompt.querySelector('.pzlmb-empty');
										if (emptyDiv) {
											var newDiv = document.createElement('div');
											newDiv.className = 'pzlmb-current';
											var img = document.createElement('img');
											img.src = d.data.url;
											img.alt = 'Current layer image';
											img.className = 'pzlmb-current-img';
											var label = document.createElement('span');
											label.className = 'pzlmb-current-label';
											label.textContent = 'Current layer image';
											newDiv.appendChild(img);
											newDiv.appendChild(label);
											emptyDiv.parentNode.replaceChild(newDiv, emptyDiv);
											$currImg = img;
										}
									}
									if ($openBtn) {
										// "Edit / Replace" once image is set
										$openBtn.innerHTML = '<span class="dashicons dashicons-edit"></span> Edit / Replace Layer Image';
									}
									setTimeout(function(){
										if ($editor) $editor.style.display = 'none';
										if ($prompt) $prompt.style.display = 'block';
									}, 1200);
								} else {
									showStatus('Error: ' + (d.data || 'unknown'), 'err');
								}
							})
							.catch(function(){ showStatus('Upload failed.', 'err'); })
							.finally(function(){ $setBtn.disabled = false; });
					};
					fr.readAsDataURL(blob);
				}, 'image/png');
			});
		}

		function buildOutput(){
			if (!st.imgEl) return null;
			var asp = ASP_W / ASP_H;
			var iw = getRotW(), ih = getRotH();
			var srcX = st.cropX * iw, srcY = st.cropY * ih, srcW = st.cropW * iw, srcH = st.cropH * ih;
			var outW = 1024, outH = Math.round(1024 / asp);
			if (asp < 1) { outH = 1024; outW = Math.round(1024 * asp); }
			var oc = document.createElement('canvas'); oc.width = outW; oc.height = outH;
			var octx = oc.getContext('2d');
			var f = '';
			if (st.brightness !== 0) f += 'brightness(' + (1 + st.brightness / 100) + ') ';
			if (st.contrast   !== 0) f += 'contrast('   + (1 + st.contrast   / 100) + ') ';
			if (st.saturation !== 0) f += 'saturate('   + (1 + st.saturation / 100) + ') ';
			octx.filter = f.trim() || 'none';
			octx.globalAlpha = st.opacity / 100;
			var tmp = document.createElement('canvas'); tmp.width = iw; tmp.height = ih;
			var tc = tmp.getContext('2d');
			tc.translate(iw / 2, ih / 2);
			if (st.flipH) tc.scale(-1, 1);
			if (st.flipV) tc.scale(1, -1);
			tc.rotate(st.rotation * Math.PI / 180);
			tc.drawImage(st.imgEl, -st.imgEl.naturalWidth / 2, -st.imgEl.naturalHeight / 2);
			octx.drawImage(tmp, srcX, srcY, srcW, srcH, 0, 0, outW, outH);
			return oc;
		}

		function showStatus(msg, cls){
			if (!$status) return;
			$status.textContent = msg;
			$status.className = 'pzlmb-status' + (cls ? ' pzlmb-status--' + cls : '');
		}
		function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
	}
})();
