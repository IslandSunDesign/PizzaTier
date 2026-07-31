/* PizzaTier Layer Image Maker — admin JS */
/* eslint-disable no-var */
	(function(){
	'use strict';

	var cfg       = window.pizzatierLimConfig || {};
	var AJAX_URL  = cfg.ajaxUrl  || '';
	var NONCE     = cfg.nonce    || '';

	// ── State ─────────────────────────────────────────────────────────────
	var state = {
		imgEl:        null,   // original Image element
		rotation:     0,      // multiples of 90
		flipH:        false,
		flipV:        false,
		zoom:         1,
		panX:         0,
		panY:         0,
		cropX:        0.1,    // normalised 0..1 relative to displayed image
		cropY:        0.1,
		cropW:        0.8,
		cropH:        0.8,
		activeTool:   'crop',
		aspectW:      4,
		aspectH:      3,
		showGuide:    true,
		showThirds:   true,
		brightness:   0,
		contrast:     0,
		saturation:   0,
		hue:          0,
		blur:         0,
		sharpen:      0,
		opacity:      100,
		removeBg:     false,
		bgThreshold:  30,
		bgInvert:     false,
		undoStack:    [],
	};

	// ── DOM refs ─────────────────────────────────────────────────────────
	var $shell          = document.getElementById('plim-shell');
	var $dropZone       = document.getElementById('plim-drop-zone');
	var $fileInput      = document.getElementById('plim-file-input');
	var $mediaBtn       = document.getElementById('plim-media-btn');
	var $canvas         = document.getElementById('plim-canvas');
	var ctx             = $canvas ? $canvas.getContext('2d') : null;
	var $guideSvg       = document.getElementById('plim-guide-svg');
	var $emptyState     = document.getElementById('plim-empty-state');
	var $stage          = document.getElementById('plim-stage');
	var $previewStrip   = document.getElementById('plim-preview-strip');
	var $imgInfo        = document.getElementById('plim-img-info');
	var $zoomLevel      = document.getElementById('plim-zoom-level');
	var $outNote        = document.getElementById('plim-out-note');
	var $downloadBtn    = document.getElementById('plim-download-btn');
	var $sendMediaBtn   = document.getElementById('plim-send-media-btn');
	var $undoBtn        = document.getElementById('plim-undo-btn');
	var $aspectPreset   = document.getElementById('plim-aspect-preset');
	var $customRatioRow = document.getElementById('plim-custom-ratio-row');
	var $ratioW         = document.getElementById('plim-ratio-w');
	var $ratioH         = document.getElementById('plim-ratio-h');
	var $showGuide      = document.getElementById('plim-show-guide');
	var $showThirds     = document.getElementById('plim-show-thirds');
	var $removeBg       = document.getElementById('plim-remove-bg');
	var $bgRow          = document.getElementById('plim-bg-row');
	var $bgInvertRow    = document.getElementById('plim-bg-invert-row');
	var $bgInvert       = document.getElementById('plim-bg-invert');
	var $bgThresh       = document.getElementById('plim-bg-thresh');
	var $bgThreshVal    = document.getElementById('plim-bg-thresh-val');
	var $filenameInput  = document.getElementById('plim-filename');
	var $outSizeSelect  = document.getElementById('plim-out-size');

	// ── Init aspect from settings ─────────────────────────────────────────
	(function(){
		var asp = cfg.aspectRatio || '4/3';
		var parts = asp.split('/');
		if(parts.length === 2){
			var w = parseFloat(parts[0]), h = parseFloat(parts[1]);
			if(w && h){
				state.aspectW = w;
				state.aspectH = h;
				// Try to find matching preset
				var val = Math.round(w)+'/'+Math.round(h);
				var found = false;
				if($aspectPreset){
					Array.from($aspectPreset.options).forEach(function(opt){
						if(opt.value === val){ opt.selected = true; found = true; }
					});
				}
				if(!found && $aspectPreset){ $aspectPreset.value = 'custom'; }
				if($ratioW) $ratioW.value = w;
				if($ratioH) $ratioH.value = h;
				updateCropToAspect();
			}
		}
	})();

	// ── Image loading ─────────────────────────────────────────────────────
	function loadImageSrc(src){
		var img = new Image();
		img.onload = function(){
			state.imgEl = img;
			state.rotation = 0; state.flipH = false; state.flipV = false;
			state.zoom = 1; state.panX = 0; state.panY = 0;
			updateCropToAspect();
			showCanvas();
			fitToStage();
			render();
			updatePreviews();
			if($imgInfo) $imgInfo.textContent = img.naturalWidth + ' × ' + img.naturalHeight + ' px';
			if($downloadBtn) $downloadBtn.disabled = false;
			if($sendMediaBtn) $sendMediaBtn.disabled = false;
			if($previewStrip) $previewStrip.style.display = 'flex';
		};
		img.src = src;
	}

	// ── Drop zone ─────────────────────────────────────────────────────────
	if($dropZone){
		$dropZone.addEventListener('click', function(){ $fileInput && $fileInput.click(); });
		$dropZone.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); $fileInput && $fileInput.click(); }});
		$dropZone.addEventListener('dragover', function(e){ e.preventDefault(); $dropZone.classList.add('plim-drop-zone--over'); });
		$dropZone.addEventListener('dragleave', function(){ $dropZone.classList.remove('plim-drop-zone--over'); });
		$dropZone.addEventListener('drop', function(e){
			e.preventDefault(); $dropZone.classList.remove('plim-drop-zone--over');
			var file = e.dataTransfer.files[0];
			if(file && file.type.startsWith('image/')){ readFile(file); }
		});
	}
	if($fileInput){
		$fileInput.addEventListener('change', function(){
			if($fileInput.files[0]) readFile($fileInput.files[0]);
		});
	}

	function readFile(file){
		var reader = new FileReader();
		reader.onload = function(e){ loadImageSrc(e.target.result); };
		reader.readAsDataURL(file);
		// Seed filename from file name
		if($filenameInput){
			$filenameInput.value = file.name.replace(/\.[^.]+$/, '') || 'layer-image';
		}
	}

	// ── Media library picker ─────────────────────────────────────────────
	if($mediaBtn){
		$mediaBtn.addEventListener('click', function(){
			if(typeof wp === 'undefined' || !wp.media) return;
			var frame = wp.media({ title: 'Choose Layer Image', button: { text: 'Use Image' }, multiple: false, library: { type: 'image' } });
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				loadImageSrc(att.url);
				if($filenameInput) $filenameInput.value = (att.filename || 'layer-image').replace(/\.[^.]+$/,'');
			});
			frame.open();
		});
	}

	// ── Canvas display ────────────────────────────────────────────────────
	function showCanvas(){
		if($emptyState) $emptyState.style.display = 'none';
		if($canvas)     $canvas.style.display = 'block';
		if($guideSvg)   $guideSvg.style.display = 'block';
	}

	function fitToStage(){
		if(!state.imgEl || !$stage) return;
		var sw = $stage.clientWidth  || 600;
		var sh = $stage.clientHeight || 480;
		var iw = getRotatedW(), ih = getRotatedH();
		state.zoom = Math.min(1, (sw-40)/iw, (sh-40)/ih);
		state.panX = 0; state.panY = 0;
		updateZoomLabel();
	}

	function getRotatedW(){ var img=state.imgEl; if(!img) return 1; return (state.rotation%180===0)?img.naturalWidth:img.naturalHeight; }
	function getRotatedH(){ var img=state.imgEl; if(!img) return 1; return (state.rotation%180===0)?img.naturalHeight:img.naturalWidth; }

	// ── Main render ───────────────────────────────────────────────────────
	function render(){
		if(!state.imgEl || !ctx) return;
		var dw = getRotatedW(), dh = getRotatedH();

		// Size the canvas element to image natural (rotated) dims
		$canvas.width  = dw;
		$canvas.height = dh;

		ctx.save();
		ctx.clearRect(0,0,dw,dh);

		// Apply flip + rotation transforms
		ctx.translate(dw/2, dh/2);
		if(state.flipH) ctx.scale(-1,1);
		if(state.flipV) ctx.scale(1,-1);
		ctx.rotate(state.rotation * Math.PI / 180);
		ctx.drawImage(state.imgEl, -state.imgEl.naturalWidth/2, -state.imgEl.naturalHeight/2);
		ctx.restore();

		// Apply CSS filter for visual preview (non-destructive)
		var filterStr = buildFilterString();
		$canvas.style.filter = filterStr;
		$canvas.style.opacity = state.opacity / 100;

		// Apply zoom + pan via CSS transform
		$canvas.style.transform = 'scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)';
		$canvas.style.transformOrigin = 'center center';

		// Background removal: re-draw with pixel manipulation if active
		if(state.removeBg){ applyBgRemoval(); }

		drawGuide();
		updatePreviews();
	}

	function buildFilterString(){
		var f = '';
		if(state.brightness !== 0) f += 'brightness('+(1 + state.brightness/100)+') ';
		if(state.contrast   !== 0) f += 'contrast('  +(1 + state.contrast  /100)+') ';
		if(state.saturation !== 0) f += 'saturate('  +(1 + state.saturation/100)+') ';
		if(state.hue        !== 0) f += 'hue-rotate('+state.hue+'deg) ';
		if(state.blur       !== 0) f += 'blur('+state.blur+'px) ';
		return f.trim() || 'none';
	}

	// ── Background removal (pixel-level) ─────────────────────────────────
	function applyBgRemoval(){
		if(!ctx || !state.imgEl) return;
		var idata = ctx.getImageData(0,0,$canvas.width,$canvas.height);
		var d = idata.data;
		// Sample corner pixels to estimate background colour
		var samples = [
			[d[0],d[1],d[2]],
			[d[(($canvas.width-1)*4)], d[(($canvas.width-1)*4)+1], d[(($canvas.width-1)*4)+2]],
			[d[(($canvas.height-1)*$canvas.width)*4], d[(($canvas.height-1)*$canvas.width)*4+1], d[(($canvas.height-1)*$canvas.width)*4+2]],
		];
		var bgR = Math.round(samples.reduce(function(a,s){return a+s[0];},0)/samples.length);
		var bgG = Math.round(samples.reduce(function(a,s){return a+s[1];},0)/samples.length);
		var bgB = Math.round(samples.reduce(function(a,s){return a+s[2];},0)/samples.length);
		var thresh = state.bgThreshold;
		for(var i=0;i<d.length;i+=4){
			var dr=Math.abs(d[i]-bgR), dg=Math.abs(d[i+1]-bgG), db=Math.abs(d[i+2]-bgB);
			var dist = Math.sqrt(dr*dr+dg*dg+db*db);
			var isBg = dist < thresh;
			if(state.bgInvert) isBg = !isBg;
			if(isBg) d[i+3] = 0;
		}
		ctx.putImageData(idata,0,0);
	}

	// ── Guide overlay ─────────────────────────────────────────────────────
	function drawGuide(){
		if(!$guideSvg || !$canvas) return;
		var cw = $canvas.offsetWidth  || $canvas.width;
		var ch = $canvas.offsetHeight || $canvas.height;

		$guideSvg.setAttribute('viewBox','0 0 '+cw+' '+ch);
		$guideSvg.setAttribute('width',  cw);
		$guideSvg.setAttribute('height', ch);
		$guideSvg.innerHTML = '';

		if(!state.imgEl) return;

		// Crop rectangle in canvas-display coordinates
		var cx = state.cropX * cw;
		var cy = state.cropY * ch;
		var cwr = state.cropW * cw;
		var chr = state.cropH * ch;

		// Darken outside crop
		var mask = document.createElementNS('http://www.w3.org/2000/svg','path');
		mask.setAttribute('fill','rgba(0,0,0,0.48)');
		mask.setAttribute('fill-rule','evenodd');
		mask.setAttribute('d',
			'M 0 0 L '+cw+' 0 L '+cw+' '+ch+' L 0 '+ch+' Z '+
			'M '+cx+' '+cy+' L '+(cx+cwr)+' '+cy+' L '+(cx+cwr)+' '+(cy+chr)+' L '+cx+' '+(cy+chr)+' Z'
		);
		$guideSvg.appendChild(mask);

		if(state.showGuide){
			// Crop border
			var rect = document.createElementNS('http://www.w3.org/2000/svg','rect');
			rect.setAttribute('x', cx); rect.setAttribute('y', cy);
			rect.setAttribute('width', cwr); rect.setAttribute('height', chr);
			rect.setAttribute('fill', 'none');
			rect.setAttribute('stroke', '#ff6b35');
			rect.setAttribute('stroke-width', '2');
			$guideSvg.appendChild(rect);

			// Corner handles
			var hSize = 10;
			[[cx,cy],[cx+cwr,cy],[cx,cy+chr],[cx+cwr,cy+chr]].forEach(function(p){
				var h = document.createElementNS('http://www.w3.org/2000/svg','rect');
				h.setAttribute('x', p[0]-hSize/2); h.setAttribute('y', p[1]-hSize/2);
				h.setAttribute('width', hSize); h.setAttribute('height', hSize);
				h.setAttribute('fill','#ff6b35'); h.setAttribute('rx','2');
				h.setAttribute('data-handle','1');
				$guideSvg.appendChild(h);
			});

			// Simulated pizza circle guide
			var pr = Math.min(cwr,chr) * 0.44;
			var pcx = cx + cwr/2, pcy = cy + chr/2;
			var pizza = document.createElementNS('http://www.w3.org/2000/svg','ellipse');
			pizza.setAttribute('cx', pcx); pizza.setAttribute('cy', pcy);
			pizza.setAttribute('rx', pr*1.1); pizza.setAttribute('ry', pr);
			pizza.setAttribute('fill','none');
			pizza.setAttribute('stroke','rgba(255,200,0,0.55)');
			pizza.setAttribute('stroke-width','1.5');
			pizza.setAttribute('stroke-dasharray','6 4');
			$guideSvg.appendChild(pizza);

			// Label
			var lbl = document.createElementNS('http://www.w3.org/2000/svg','text');
			lbl.setAttribute('x', cx+4); lbl.setAttribute('y', cy-5);
			lbl.setAttribute('font-size','10'); lbl.setAttribute('fill','#ff6b35');
			lbl.setAttribute('font-family','sans-serif');
			lbl.textContent = Math.round(state.aspectW)+':'+Math.round(state.aspectH)+' crop';
			$guideSvg.appendChild(lbl);
		}

		if(state.showThirds){
			for(var i=1;i<3;i++){
				var lv = document.createElementNS('http://www.w3.org/2000/svg','line');
				lv.setAttribute('x1',cx+cwr*i/3); lv.setAttribute('y1',cy);
				lv.setAttribute('x2',cx+cwr*i/3); lv.setAttribute('y2',cy+chr);
				lv.setAttribute('stroke','rgba(255,255,255,0.25)'); lv.setAttribute('stroke-width','1');
				$guideSvg.appendChild(lv);
				var lh = document.createElementNS('http://www.w3.org/2000/svg','line');
				lh.setAttribute('x1',cx); lh.setAttribute('y1',cy+chr*i/3);
				lh.setAttribute('x2',cx+cwr); lh.setAttribute('y2',cy+chr*i/3);
				lh.setAttribute('stroke','rgba(255,255,255,0.25)'); lh.setAttribute('stroke-width','1');
				$guideSvg.appendChild(lh);
			}
		}
	}

	// ── Crop handle drag ──────────────────────────────────────────────────
	var dragState = null;
	if($guideSvg){
		$guideSvg.style.pointerEvents = 'auto';
		$guideSvg.addEventListener('mousedown', function(e){
			if(state.activeTool !== 'crop' || !state.imgEl) return;
			var cw = $canvas.offsetWidth  || 1;
			var ch = $canvas.offsetHeight || 1;
			var r  = $guideSvg.getBoundingClientRect();
			var mx = e.clientX - r.left, my = e.clientY - r.top;

			// Normalised pointer
			var nx = mx/cw, ny = my/ch;

			// Determine drag type: corner, edge, or move
			var cx=state.cropX,cy=state.cropY,cw2=state.cropW,ch2=state.cropH;
			var corners = [{x:cx,y:cy,dx:'l',dy:'t'},{x:cx+cw2,y:cy,dx:'r',dy:'t'},
			               {x:cx,y:cy+ch2,dx:'l',dy:'b'},{x:cx+cw2,y:cy+ch2,dx:'r',dy:'b'}];
			var tol = 0.04;
			var hit = null;
			corners.forEach(function(c){
				if(Math.abs(nx-c.x)<tol && Math.abs(ny-c.y)<tol) hit = c;
			});

			dragState = {
				type: hit ? 'corner' : 'move',
				corner: hit,
				startNx: nx, startNy: ny,
				origCx: state.cropX, origCy: state.cropY,
				origCw: state.cropW, origCh: state.cropH,
			};
			e.preventDefault();
		});
	}

	document.addEventListener('mousemove', function(e){
		if(!dragState || !$guideSvg || !state.imgEl) return;
		var cw = $canvas.offsetWidth || 1;
		var ch = $canvas.offsetHeight || 1;
		var r  = $guideSvg.getBoundingClientRect();
		var nx = (e.clientX - r.left)/cw;
		var ny = (e.clientY - r.top)/ch;
		var dx = nx - dragState.startNx;
		var dy = ny - dragState.startNy;

		if(dragState.type === 'move'){
			state.cropX = clamp(dragState.origCx + dx, 0, 1-state.cropW);
			state.cropY = clamp(dragState.origCy + dy, 0, 1-state.cropH);
		} else if(dragState.corner){
			var c = dragState.corner;
			var newX = dragState.origCx, newY = dragState.origCy;
			var newW = dragState.origCw, newH = dragState.origCh;
			if(c.dx==='r') newW = clamp(dragState.origCw+dx, 0.1, 1-newX);
			if(c.dx==='l'){ newX = clamp(dragState.origCx+dx, 0, dragState.origCx+dragState.origCw-0.1); newW = (dragState.origCx+dragState.origCw)-newX; }
			if(c.dy==='b') newH = clamp(dragState.origCh+dy, 0.05, 1-newY);
			if(c.dy==='t'){ newY = clamp(dragState.origCy+dy, 0, dragState.origCy+dragState.origCh-0.05); newH = (dragState.origCy+dragState.origCh)-newY; }
			// Maintain aspect ratio
			var asp = state.aspectW / state.aspectH;
			if(c.dx !== 'none'){
				var adjH = newW / asp;
				if(c.dy==='t') newY = (newY+newH) - adjH;
				newH = adjH;
			}
			state.cropX = newX; state.cropY = newY;
			state.cropW = newW; state.cropH = clamp(newH,0.05,1-newY);
		}
		drawGuide();
	});

	document.addEventListener('mouseup', function(){
		if(dragState){ dragState = null; updatePreviews(); }
	});

	// ── Pan tool ─────────────────────────────────────────────────────────
	var panDrag = null;
	if($canvas){
		$canvas.addEventListener('mousedown', function(e){
			if(state.activeTool !== 'move' || !state.imgEl) return;
			panDrag = { startX: e.clientX, startY: e.clientY, origPX: state.panX, origPY: state.panY };
			e.preventDefault();
		});
	}
	document.addEventListener('mousemove', function(e){
		if(!panDrag) return;
		state.panX = panDrag.origPX + (e.clientX - panDrag.startX);
		state.panY = panDrag.origPY + (e.clientY - panDrag.startY);
		if($canvas) $canvas.style.transform = 'scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)';
	});
	document.addEventListener('mouseup', function(){ panDrag = null; });

	// ── Zoom ─────────────────────────────────────────────────────────────
	if($stage){
		$stage.addEventListener('wheel', function(e){
			e.preventDefault();
			state.zoom = clamp(state.zoom * (e.deltaY < 0 ? 1.1 : 0.9), 0.1, 8);
			if($canvas) $canvas.style.transform = 'scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)';
			updateZoomLabel();
		}, {passive:false});
	}

	function btn(id, fn){ var el=document.getElementById(id); if(el) el.addEventListener('click',fn); }

	btn('plim-zoom-in',  function(){ state.zoom = clamp(state.zoom*1.2,0.1,8); if($canvas) $canvas.style.transform='scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)'; updateZoomLabel(); });
	btn('plim-zoom-out', function(){ state.zoom = clamp(state.zoom/1.2,0.1,8); if($canvas) $canvas.style.transform='scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)'; updateZoomLabel(); });
	btn('plim-zoom-fit', function(){ fitToStage(); if($canvas) $canvas.style.transform='scale('+state.zoom+') translate('+state.panX+'px,'+state.panY+'px)'; updateZoomLabel(); });

	function updateZoomLabel(){ if($zoomLevel) $zoomLevel.textContent = Math.round(state.zoom*100)+'%'; }

	// ── Rotate / flip ─────────────────────────────────────────────────────
	btn('plim-rotate-ccw', function(){ pushUndo(); state.rotation=(state.rotation-90+360)%360; render(); });
	btn('plim-rotate-cw',  function(){ pushUndo(); state.rotation=(state.rotation+90)%360; render(); });
	btn('plim-flip-h',     function(){ pushUndo(); state.flipH=!state.flipH; render(); });
	btn('plim-flip-v',     function(){ pushUndo(); state.flipV=!state.flipV; render(); });

	// ── Tool buttons ─────────────────────────────────────────────────────
	btn('plim-tool-crop', function(){
		state.activeTool='crop';
		document.getElementById('plim-tool-crop').classList.add('plim-tool-btn--active');
		document.getElementById('plim-tool-move').classList.remove('plim-tool-btn--active');
		if($canvas) $canvas.style.cursor='crosshair';
	});
	btn('plim-tool-move', function(){
		state.activeTool='move';
		document.getElementById('plim-tool-move').classList.add('plim-tool-btn--active');
		document.getElementById('plim-tool-crop').classList.remove('plim-tool-btn--active');
		if($canvas) $canvas.style.cursor='grab';
	});

	// ── Aspect ratio ─────────────────────────────────────────────────────
	if($aspectPreset){
		$aspectPreset.addEventListener('change', function(){
			if(this.value === 'custom'){
				if($customRatioRow) $customRatioRow.style.display='flex';
			} else {
				if($customRatioRow) $customRatioRow.style.display='none';
				var parts = this.value.split('/');
				state.aspectW = parseFloat(parts[0]);
				state.aspectH = parseFloat(parts[1]);
				updateCropToAspect();
				render();
			}
		});
	}

	[$ratioW,$ratioH].forEach(function(el){ if(el) el.addEventListener('input',function(){
		state.aspectW = parseFloat($ratioW.value)||1;
		state.aspectH = parseFloat($ratioH.value)||1;
		updateCropToAspect(); render();
	}); });

	function updateCropToAspect(){
		// Reset crop to centre with correct aspect
		var asp = state.aspectW / state.aspectH;
		var maxW = 0.85, maxH = 0.85;
		var w = maxW, h = maxW / asp;
		if(h > maxH){ h = maxH; w = maxH * asp; }
		state.cropW = Math.min(w, 1);
		state.cropH = Math.min(h, 1);
		state.cropX = (1-state.cropW)/2;
		state.cropY = (1-state.cropH)/2;
	}

	// ── Guide toggles ─────────────────────────────────────────────────────
	if($showGuide)  $showGuide.addEventListener('change',  function(){ state.showGuide=this.checked;  drawGuide(); });
	if($showThirds) $showThirds.addEventListener('change', function(){ state.showThirds=this.checked; drawGuide(); });

	// ── Adjustment sliders ────────────────────────────────────────────────
	var sliderMap = {
		'plim-brightness':'brightness','plim-contrast':'contrast','plim-saturation':'saturation',
		'plim-hue':'hue','plim-blur':'blur','plim-sharpen':'sharpen','plim-opacity':'opacity',
	};
	Object.keys(sliderMap).forEach(function(id){
		var el = document.getElementById(id);
		var key = sliderMap[id];
		if(el){
			el.addEventListener('input', function(){
				state[key] = parseFloat(this.value);
				var unit = this.getAttribute('data-unit') || '';
				var valEl = document.getElementById(id+'-val');
				if(valEl) valEl.textContent = this.value + unit;
				render();
			});
		}
	});

	// ── Reset adjustments ─────────────────────────────────────────────────
	btn('plim-reset-adj', function(){
		var defaults = {brightness:0,contrast:0,saturation:0,hue:0,blur:0,sharpen:0,opacity:100};
		Object.assign(state, defaults);
		Object.keys(sliderMap).forEach(function(id){
			var el = document.getElementById(id);
			var key = sliderMap[id];
			if(el){ el.value = defaults[key]; }
			var unit = el ? (el.getAttribute('data-unit')||'') : '';
			var valEl = document.getElementById(id+'-val');
			if(valEl) valEl.textContent = defaults[key] + unit;
		});
		render();
	});

	// ── Background removal ────────────────────────────────────────────────
	if($removeBg){
		$removeBg.addEventListener('change', function(){
			state.removeBg = this.checked;
			if($bgRow)       $bgRow.style.display       = this.checked ? 'block' : 'none';
			if($bgInvertRow) $bgInvertRow.style.display = this.checked ? 'flex'  : 'none';
			render();
		});
	}
	if($bgThresh){
		$bgThresh.addEventListener('input', function(){
			state.bgThreshold = parseInt(this.value);
			if($bgThreshVal) $bgThreshVal.textContent = this.value;
			render();
		});
	}
	if($bgInvert){
		$bgInvert.addEventListener('change', function(){ state.bgInvert = this.checked; render(); });
	}

	// ── Undo ─────────────────────────────────────────────────────────────
	function pushUndo(){
		state.undoStack.push({ rotation:state.rotation, flipH:state.flipH, flipV:state.flipV });
		if(state.undoStack.length > 30) state.undoStack.shift();
		if($undoBtn) $undoBtn.disabled = false;
	}
	btn('plim-undo-btn', function(){
		if(!state.undoStack.length) return;
		var prev = state.undoStack.pop();
		state.rotation = prev.rotation; state.flipH = prev.flipH; state.flipV = prev.flipV;
		if(!state.undoStack.length && $undoBtn) $undoBtn.disabled = true;
		render();
	});

	// ── Build output canvas ───────────────────────────────────────────────
	function buildOutputCanvas(){
		if(!state.imgEl) return null;
		var asp = state.aspectW / state.aspectH;
		var outSizeOpt = $outSizeSelect ? $outSizeSelect.value : '1024';
		var iw = getRotatedW(), ih = getRotatedH();
		// Final cropped pixel dims from source
		var srcX = state.cropX * iw;
		var srcY = state.cropY * ih;
		var srcW = state.cropW * iw;
		var srcH = state.cropH * ih;

		var outW, outH;
		if(outSizeOpt === 'original'){
			outW = Math.round(srcW); outH = Math.round(srcH);
		} else {
			var maxPx = parseInt(outSizeOpt);
			if(asp >= 1){ outW = maxPx; outH = Math.round(maxPx/asp); }
			else         { outH = maxPx; outW = Math.round(maxPx*asp); }
		}

		var oc = document.createElement('canvas');
		oc.width  = outW;
		oc.height = outH;
		var octx = oc.getContext('2d');

		// Apply filter
		octx.filter = buildFilterString();
		octx.globalAlpha = state.opacity / 100;

		// We need to draw the rotated+flipped image to a temp canvas first
		var tmp = document.createElement('canvas');
		tmp.width  = iw; tmp.height = ih;
		var tctx = tmp.getContext('2d');
		tctx.save();
		tctx.translate(iw/2, ih/2);
		if(state.flipH) tctx.scale(-1,1);
		if(state.flipV) tctx.scale(1,-1);
		tctx.rotate(state.rotation * Math.PI / 180);
		tctx.drawImage(state.imgEl, -state.imgEl.naturalWidth/2, -state.imgEl.naturalHeight/2);
		tctx.restore();

		octx.drawImage(tmp, srcX, srcY, srcW, srcH, 0, 0, outW, outH);

		if(state.removeBg){
			var idata = octx.getImageData(0,0,outW,outH);
			var d = idata.data;
			var corner = [d[0],d[1],d[2]];
			var thresh = state.bgThreshold;
			var bgR=corner[0],bgG=corner[1],bgB=corner[2];
			for(var i=0;i<d.length;i+=4){
				var dr=Math.abs(d[i]-bgR),dg=Math.abs(d[i+1]-bgG),db=Math.abs(d[i+2]-bgB);
				var dist=Math.sqrt(dr*dr+dg*dg+db*db);
				var isBg = dist < thresh;
				if(state.bgInvert) isBg = !isBg;
				if(isBg) d[i+3] = 0;
			}
			octx.putImageData(idata,0,0);
		}

		return oc;
	}

	// ── Previews ─────────────────────────────────────────────────────────
	function updatePreviews(){
		if(!state.imgEl) return;
		var oc = buildOutputCanvas();
		if(!oc) return;
		['dark','check','pizza','light'].forEach(function(id){
			var thumb = document.getElementById('plim-thumb-canvas-'+id);
			if(!thumb) return;
			var size = 110;
			thumb.width = size; thumb.height = size;
			var tc = thumb.getContext('2d');
			tc.clearRect(0,0,size,size);
			// Scale-to-fit
			var scale = Math.min(size/oc.width, size/oc.height);
			var dw = oc.width*scale, dh = oc.height*scale;
			tc.drawImage(oc, (size-dw)/2, (size-dh)/2, dw, dh);
		});
	}

	// ── Download ─────────────────────────────────────────────────────────
	btn('plim-download-btn', function(){
		var oc = buildOutputCanvas();
		if(!oc){ showNote('No image loaded.','error'); return; }
		var fname = ($filenameInput ? $filenameInput.value.trim() : '') || 'layer-image';
		if(!fname.endsWith('.png')) fname += '.png';
		oc.toBlob(function(blob){
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url; a.download = fname;
			document.body.appendChild(a); a.click();
			setTimeout(function(){ document.body.removeChild(a); URL.revokeObjectURL(url); }, 1000);
			showNote('Downloaded as '+fname,'success');
		}, 'image/png');
	});

	// ── Send to Media Library ─────────────────────────────────────────────
	btn('plim-send-media-btn', function(){
		var oc = buildOutputCanvas();
		if(!oc){ showNote('No image loaded.','error'); return; }
		var fname = ($filenameInput ? $filenameInput.value.trim() : '') || 'layer-image';
		if(!fname.endsWith('.png')) fname += '.png';
		showNote('Uploading…','');
		if($sendMediaBtn) $sendMediaBtn.disabled = true;

		oc.toBlob(function(blob){
			var reader = new FileReader();
			reader.onload = function(ev){
				var dataUrl = ev.target.result;
				var fd = new FormData();
				fd.append('action',   'pizzatier_upload_layer_image');
				fd.append('nonce',    NONCE);
				fd.append('data',     dataUrl);
				fd.append('filename', fname);
				fetch(AJAX_URL, { method:'POST', body:fd })
					.then(function(r){ return r.json(); })
					.then(function(d){
						if(d.success){
							showNote('✓ Added to Media Library (ID '+d.data.id+')', 'success');
						} else {
							showNote('Upload failed: '+(d.data||'unknown error'), 'error');
						}
					})
					.catch(function(){ showNote('Upload error.','error'); })
					.finally(function(){ if($sendMediaBtn) $sendMediaBtn.disabled=false; });
			};
			reader.readAsDataURL(blob);
		}, 'image/png');
	});

	function showNote(msg, cls){
		if(!$outNote) return;
		$outNote.textContent = msg;
		$outNote.className = 'plim-out-note' + (cls ? ' '+cls : '');
	}

	// ── Keyboard shortcuts ────────────────────────────────────────────────
	document.addEventListener('keydown', function(e){
		if(!state.imgEl) return;
		if(e.key === 'c' || e.key === 'C') document.getElementById('plim-tool-crop') && document.getElementById('plim-tool-crop').click();
		if(e.key === 'm' || e.key === 'M') document.getElementById('plim-tool-move') && document.getElementById('plim-tool-move').click();
		if(e.key === '+' || e.key === '=') document.getElementById('plim-zoom-in') && document.getElementById('plim-zoom-in').click();
		if(e.key === '-')                  document.getElementById('plim-zoom-out') && document.getElementById('plim-zoom-out').click();
		if(e.key === 'f' || e.key === 'F') document.getElementById('plim-zoom-fit') && document.getElementById('plim-zoom-fit').click();
		if((e.ctrlKey||e.metaKey) && e.key==='z'){ e.preventDefault(); document.getElementById('plim-undo-btn') && document.getElementById('plim-undo-btn').click(); }
	});

	// ── Helpers ───────────────────────────────────────────────────────────
	function clamp(v,mn,mx){ return Math.max(mn,Math.min(mx,v)); }

	})();
