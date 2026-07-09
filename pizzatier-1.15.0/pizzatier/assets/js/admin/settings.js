( function () {
	'use strict';

	// Shape preview
	var shapeSelect  = document.getElementById( 'pset-pizza-shape' );
	var aspectInput  = document.querySelector( '[name="pizzatier_setting_pizza_aspect"]' );
	var radiusInput  = document.querySelector( '[name="pizzatier_setting_pizza_radius"]' );
	var shapePreview = document.getElementById( 'pset-shape-preview' );

	function updateShapePreview() {
		if ( ! shapeSelect || ! shapePreview ) { return; }
		var shape  = shapeSelect.value;
		var aspect = ( aspectInput && aspectInput.value ) || '4 / 3';
		var radius = ( radiusInput && radiusInput.value ) || '8px';
		var w = 80, h = 80;

		if ( shape === 'round' ) {
			shapePreview.style.borderRadius = '50%';
			shapePreview.style.width        = w + 'px';
			shapePreview.style.height       = w + 'px';
		} else if ( shape === 'square' ) {
			shapePreview.style.borderRadius = '8px';
			shapePreview.style.width        = w + 'px';
			shapePreview.style.height       = w + 'px';
		} else if ( shape === 'rectangle' ) {
			var parts = aspect.replace( /\s/g, '' ).split( '/' );
			var ar    = parts.length === 2 ? parseFloat( parts[0] ) / parseFloat( parts[1] ) : 1.33;
			shapePreview.style.borderRadius = '12px';
			shapePreview.style.width        = ( h * ar ) + 'px';
			shapePreview.style.height       = h + 'px';
		} else if ( shape === 'custom' ) {
			shapePreview.style.borderRadius = radius;
			shapePreview.style.width        = w + 'px';
			shapePreview.style.height       = w + 'px';
		}
	}

	if ( shapeSelect )  { shapeSelect.addEventListener( 'change', updateShapePreview ); }
	if ( aspectInput )  { aspectInput.addEventListener( 'input',  updateShapePreview ); }
	if ( radiusInput )  { radiusInput.addEventListener( 'input',  updateShapePreview ); }
	updateShapePreview();

	// ── Export: detached form to avoid nested-form HTML restriction ──
	var $exportBtn = document.getElementById( 'pset-export-btn' );
	if ( $exportBtn ) {
		$exportBtn.addEventListener( 'click', function () {
			var actionUrl = $exportBtn.getAttribute( 'data-action-url' );
			var nonce     = $exportBtn.getAttribute( 'data-nonce' );
			var form = document.createElement( 'form' );
			form.method = 'post';
			form.action = actionUrl;
			form.style.display = 'none';

			var fAction = document.createElement( 'input' );
			fAction.type  = 'hidden';
			fAction.name  = 'action';
			fAction.value = 'pizzatier_export_settings';

			var fNonce = document.createElement( 'input' );
			fNonce.type  = 'hidden';
			fNonce.name  = '_wpnonce';
			fNonce.value = nonce;

			form.appendChild( fAction );
			form.appendChild( fNonce );
			document.body.appendChild( form );
			form.submit();
			setTimeout( function () { document.body.removeChild( form ); }, 2000 );
		} );
	}

} )();
