/* PizzaTier Settings — admin JS */
/* eslint-disable no-var */

/* PizzaTier Settings */
/* eslint-disable no-var */

	(function(){
		// Bail early if the calculator widget isn't rendered on this page.
		// (Lives on the Pricing page in v1.6.0+; the Settings page no longer
		// renders it.)
		if ( ! document.getElementById( 'pztc-calc-base' ) ) { return; }

		function getVal(id) {
			var el = document.getElementById(id);
			return el ? ( parseFloat(el.value) || 0 ) : 0;
		}
		function getIntVal(id) {
			var el = document.getElementById(id);
			return el ? ( parseInt(el.value, 10) || 0 ) : 0;
		}

		// Read saved settings from PHP-printed JSON
		var SETTINGS = (window.pizzatier_commerceSettings && window.pizzatier_commerceSettings.config) || {};

		function calcTotal() {
			var base  = getVal('pztc-calc-base');
			var cell  = getVal('pztc-calc-cell');
			var count = getIntVal('pztc-calc-count');
			var mode  = SETTINGS.mode;
			var addOn = 0;

			if ( mode === 'bundle' ) {
				addOn = 0;
			} else if ( mode === 'flat_per_size' ) {
				addOn = cell; // flat = one cell regardless of count
			} else if ( mode === 'highest_wins' ) {
				addOn = count > 0 ? cell : 0; // highest = one cell
			} else if ( mode === 'tiered_by_count' ) {
				addOn = count > 0 ? cell : 0; // tier cell * 1
			} else {
				// addon_per_layer / free_first_n
				var paid = Math.max(0, count - (SETTINGS.freeCount || 0));
				var pricedCell = cell;
				if ( SETTINGS.minToppingPrice !== null ) pricedCell = Math.max(pricedCell, SETTINGS.minToppingPrice);
				if ( SETTINGS.maxToppingPrice !== null ) pricedCell = Math.min(pricedCell, SETTINGS.maxToppingPrice);
				addOn = paid * pricedCell;
			}

			// Bulk discount
			if ( SETTINGS.discThreshold > 0 && SETTINGS.discPercent > 0 && count >= SETTINGS.discThreshold ) {
				var disc = addOn * (SETTINGS.discPercent / 100);
				if ( SETTINGS.discMax !== null ) disc = Math.min(disc, SETTINGS.discMax);
				addOn = Math.max(0, addOn - disc);
			}

			var total = base + addOn;

			// Rounding
			var r = SETTINGS.rounding;
			if ( r === 'up' )        total = Math.ceil(total * 100) / 100;
			else if ( r === 'nearest5' ) total = Math.round(total / 0.05) * 0.05;
			else if ( r === 'nearest25') total = Math.round(total / 0.25) * 0.25;
			else if ( r === 'nearest50') total = Math.round(total / 0.50) * 0.50;
			else if ( r === 'nearest1' ) total = Math.round(total);
			else total = Math.round(total * 100) / 100;

			document.getElementById('pztc-calc-total').textContent = SETTINGS.currency + total.toFixed(2);
		}

		['pztc-calc-base','pztc-calc-cell','pztc-calc-count'].forEach(function(id){
			var el = document.getElementById(id);
			if (el) el.addEventListener('input', calcTotal);
		});
		calcTotal();
	})();


	(function(){
		var grid  = document.getElementById('pztc-pricing-mode-grid');
		var input = document.getElementById('pizzatier_commerce_pricing_mode_input');
		if (!grid || !input) return;
		grid.querySelectorAll('.pztc-mode-card').forEach(function(card){
			card.style.setProperty('--mc', card.dataset.color || '#ff6b35');
			card.addEventListener('click', function(){
				grid.querySelectorAll('.pztc-mode-card').forEach(function(c){
					c.classList.remove('pztc-mode-card--selected');
					c.setAttribute('aria-pressed','false');
				});
				card.classList.add('pztc-mode-card--selected');
				card.setAttribute('aria-pressed','true');
				input.value = card.dataset.mode;
			});
			card.addEventListener('keydown', function(e){
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
			});
		});
	})();


