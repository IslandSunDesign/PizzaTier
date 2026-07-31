/**
 * PizzaTier — Cart Submission
 *
 * Intercepts all Add to Cart button clicks on pizza product pages, reads
 * the current builder state from PizzaTierBuilder.getState(), verifies
 * the price server-side via the REST endpoint, and submits to WooCommerce
 * via the pizzatier_commerce_add_to_cart AJAX action.
 *
 * Also handles the standard WooCommerce "Add to cart" form button so that
 * submitting the product page form goes through our flow instead of the
 * default WC form handler.
 *
 * Multi-instance support: when a page has multiple pizza builders, each
 * button resolves its config and builder instance by walking up the DOM
 * to the nearest .pztc-builder-section[data-pztc-instance] ancestor.
 * This means clicks on builder-1's Add to Cart button always submit
 * builder-1's product, even if builder-2 is also present on the page.
 *
 * Depends on:
 *   - window.pizzatier_commerceFrontend  (localised by FrontendEmbed::enqueue_assets)
 *   - window.PizzaTierBuilderInstances  (exposed by frontend-builder.js)
 *   - jQuery (for WC compatibility)
 *
 * Button selectors handled:
 *   .pztc-add-to-cart-btn     — rendered by CartIntegration::render_cart_button
 *   .single_add_to_cart_button  — standard WC product page button
 */

/* global pizzatier_commerceFrontend, pizzatier_commerceFrontendInstances, PizzaTierBuilderInstances, jQuery, wc_add_to_cart_params */

( function ( $ ) {
	'use strict';

	// Base config (last registered instance) — used as fallback and for the
	// single-instance case. Per-click resolution is done in getCfgForButton().
	const BASE_CFG  = window.pizzatier_commerceFrontend || {};
	const BASE_AJAX = BASE_CFG.ajaxUrl || ( typeof ajaxurl !== 'undefined' ? ajaxurl : '' );

	// -------------------------------------------------------------------------
	// Config resolution helpers (multi-instance)
	// -------------------------------------------------------------------------

	/**
	 * Resolve the config object and matching builder instance for a clicked button.
	 *
	 * Walks up the DOM from $btn to find the nearest
	 * .pztc-builder-section[data-pztc-instance] wrapper, then maps that
	 * index back to the pizzatier_commerceFrontendInstances array and
	 * PizzaTierBuilderInstances array.
	 *
	 * Falls back to the base (last-registered) config and builder when the
	 * DOM structure is not present (e.g. the standard WC button outside a
	 * builder section, or a single-instance page).
	 *
	 * @param {jQuery} $btn  The clicked button element.
	 * @returns {{ cfg: object, builder: object|undefined, ajaxUrl: string }}
	 */
	function getCfgForButton( $btn ) {
		const instances = window.PizzaTierBuilderInstances || [];
		const configs   = window.pizzatier_commerceFrontendInstances || [];

		// Resolve instance index: check button's own data-pztc-instance first,
		// then walk up to the nearest builder section wrapper.
		let instanceIdx = parseInt( $btn.data( 'pztc-instance' ) || $btn.data( 'instance' ), 10 ) || 0;

		if ( ! instanceIdx ) {
			const $section = $btn.closest( '.pztc-builder-section[data-pztc-instance]' );
			if ( $section.length ) {
				instanceIdx = parseInt( $section.data( 'pztc-instance' ), 10 ) || 0;
			}
		}

		if ( instanceIdx ) {
			// Find the matching builder API by instanceIdx
			const builder = instances.find( function ( b ) {
				return b && b.instanceIdx === instanceIdx;
			} );

			// configs array is 0-based; instanceIdx is 1-based
			const cfg = configs[ instanceIdx - 1 ] || BASE_CFG;

			return {
				cfg:     cfg,
				builder: builder,
				ajaxUrl: cfg.ajaxUrl || BASE_AJAX,
			};
		}

		// No instance resolved — fall back to global defaults
		return {
			cfg:     BASE_CFG,
			builder: instances[0] || window.PizzaTierBuilder,
			ajaxUrl: BASE_AJAX,
		};
	}

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	let isSubmitting = false;

	// -------------------------------------------------------------------------
	// Initialise on DOM ready
	// -------------------------------------------------------------------------

	$( function () {
		bindAddToCartButtons();
		preventWcFormDefaultSubmit();
	} );

	// =========================================================================
	// Button binding
	// =========================================================================

	/**
	 * Bind click handlers to all add-to-cart buttons on the page.
	 * Uses delegated events so buttons added dynamically (e.g. inside the
	 * PizzaTier builder iframe) are also caught.
	 */
	function bindAddToCartButtons() {
		// In-builder button (rendered by CartIntegration::render_cart_button)
		// AND the checkout bar "Order Now" / "Add to Cart" button (.pztc-bar-row__btn).
		$( document ).on( 'click', '.pztc-add-to-cart-btn, .pztc-bar-row__btn', function ( e ) {
			e.preventDefault();
			e.stopImmediatePropagation();
			handleAddToCart( $( this ) );
		} );

		// Standard WC product page "Add to cart" button.
		$( document ).on( 'click', '.single_add_to_cart_button', function ( e ) {
			if ( ! BASE_CFG.productId ) {
				// Not a pizza product — let WC handle it normally.
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			handleAddToCart( $( this ) );
		} );
	}

	/**
	 * Prevent the WC single product form from submitting via its default
	 * mechanism on pizza product pages. The button click handler above
	 * takes over the submission flow.
	 */
	function preventWcFormDefaultSubmit() {
		if ( ! BASE_CFG.productId ) return;

		$( document ).on( 'submit', 'form.cart', function ( e ) {
			e.preventDefault();
		} );
	}

	// =========================================================================
	// Core add-to-cart flow
	// =========================================================================

	/**
	 * Main handler — called for any add-to-cart button click.
	 *
	 * @param {jQuery} $btn  The clicked button element.
	 */
	function handleAddToCart( $btn ) {
		if ( isSubmitting ) return;

		// ── Resolve per-instance config and builder ───────────────────────
		const { cfg, builder, ajaxUrl } = getCfgForButton( $btn );
		const I18N = cfg.i18n || BASE_CFG.i18n || {};

		// ── Read builder state ────────────────────────────────────────────
		if ( ! builder || typeof builder.getState !== 'function' ) {
			showError( $btn, I18N.builderNotReady || 'The pizza builder is not ready yet. Please wait a moment.' );
			return;
		}

		let state = builder.getState();

		// ── Validate state ────────────────────────────────────────────────
		if ( ! state.size ) {
			showError( $btn, I18N.selectSize || 'Please select a pizza size.' );
			return;
		}

		// Block submission if size wasn't explicitly chosen (multiple sizes available).
		if ( state.sizeChosen === false ) {
			showError( $btn, I18N.selectSize || 'Please select a pizza size.' );
			var $sizeSel = $btn.closest( '.pztc-builder-section' ).find( '.pztc-size-selector--visible' );
			if ( $sizeSel.length ) {
				$sizeSel[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
			return;
		}

		// Note: layers may be empty for a plain pizza (just base price).
		// Only block if total is null (unconfigured price grid).
		if ( state.total === null ) {
			showError( $btn, I18N.priceUnavailable || 'A price could not be calculated for your selection. Please contact the store.' );
			return;
		}

		// ── UI — loading state ────────────────────────────────────────────
		setSubmitting( true, $btn, I18N );
		clearMessages( $btn );

		// Ensure productId is set — fall back to data-product-id on the button
		if ( ! state.productId ) {
			state = Object.assign( {}, state, {
				productId: parseInt( $btn.data( 'product-id' ) || cfg.productId || 0, 10 )
			} );
		}

		// ── Step 1: REST price verification ──────────────────────────────
		verifyPrice( state, cfg, function ( verifyOk, verifyData, verifyError ) {
			if ( ! verifyOk ) {
				setSubmitting( false, $btn, I18N );
				showError( $btn, verifyError || I18N.addToCartError || 'Could not verify price. Please try again.' );
				return;
			}

			// ── Step 2: AJAX add to cart ──────────────────────────────────
			submitToCart( state, verifyData, $btn, cfg, ajaxUrl, function ( cartOk, cartData, cartError ) {
				setSubmitting( false, $btn, I18N );

				if ( ! cartOk ) {
					showError( $btn, cartError || I18N.addToCartError || 'Could not add to cart. Please try again.' );
					return;
				}

				// ── Step 3: Success UI ────────────────────────────────────
				onCartSuccess( cartData, $btn, cfg, I18N );
			} );
		} );
	}

	// =========================================================================
	// Step 1 — REST price verification
	// =========================================================================

	/**
	 * Call the REST endpoint to verify the price server-side.
	 *
	 * @param {object}   state     PizzaTierBuilder.getState() result.
	 * @param {object}   cfg       Per-instance config object.
	 * @param {function} callback  fn( ok: bool, data: object|null, errorMsg: string|null )
	 */
	function verifyPrice( state, cfg, callback ) {
		const I18N    = cfg.i18n || BASE_CFG.i18n || {};
		const restUrl = ( cfg.restUrl || BASE_CFG.restUrl || '' ) + 'calculate-price';

		$.ajax( {
			url:         restUrl,
			type:        'POST',
			contentType: 'application/json',
			data:        JSON.stringify( {
				product_id: state.productId,
				size:       state.size,
				layers:     state.layers.map( function ( l ) {
					return {
						layerId:      l.layerId,
						fraction:     l.fraction     || 'Whole',
						portion:      l.portion      || '',
						portionLabel: l.portionLabel || '',
						layerType:    l.layerType    || '',
						layerPostId:  l.layerPostId  || 0
					};
				} ),
			} ),
			beforeSend: function ( xhr ) {
				// WordPress REST API nonce header for authentication.
				xhr.setRequestHeader( 'X-WP-Nonce', cfg.restNonce || BASE_CFG.restNonce || '' );
			},
		} )
		.done( function ( response ) {
			// WP REST success responses are the data object directly
			// (not wrapped in { success, data }).
			if ( response && typeof response.total === 'number' ) {
				callback( true, response, null );
			} else {
				callback( false, null, I18N.addToCartError || 'Unexpected response from server.' );
			}
		} )
		.fail( function ( xhr ) {
			const body   = xhr.responseJSON || {};
			const msg    = body.message || I18N.addToCartError || 'Price verification failed.';
			callback( false, null, msg );
		} );
	}

	// =========================================================================
	// Step 2 — AJAX add to cart
	// =========================================================================

	/**
	 * Submit the pizza configuration to the WC cart via admin-ajax.
	 *
	 * @param {object}   state       Builder state.
	 * @param {object}   priceData   Verified price data from the REST endpoint.
	 * @param {jQuery}   $btn        The clicked button (for context).
	 * @param {object}   cfg         Per-instance config object.
	 * @param {string}   ajaxUrl     Admin-ajax URL for this instance.
	 * @param {function} callback    fn( ok: bool, data: object|null, errorMsg: string|null )
	 */
	function submitToCart( state, priceData, $btn, cfg, ajaxUrl, callback ) {
		const I18N       = cfg.i18n || BASE_CFG.i18n || {};
		const cartNonce  = cfg.cartNonce || BASE_CFG.cartNonce || '';

		$.post( ajaxUrl, {
			action:     'pizzatier_commerce_add_to_cart',
			nonce:      cartNonce,
			product_id: state.productId,
			size:       state.size,
			order_note: resolveOrderNote( $btn, state ),
			quantity:   resolveQuantity( $btn ),
			// layerName is for display only — server always recalculates price from the grid.
			layers:     JSON.stringify( state.layers.map( function ( l ) {
				return {
					layerId:      l.layerId,
					layerName:    l.layerName    || '',
					fraction:     l.fraction     || 'Whole',
					portion:      l.portion      || '',
					portionLabel: l.portionLabel || '',
					layerType:    l.layerType    || '',
					layerPostId:  l.layerPostId  || 0
				};
			} ) ),
		} )
		.done( function ( response ) {
			if ( response && response.success ) {
				callback( true, response.data, null );
			} else {
				const msg = ( response && response.data && response.data.message )
					|| I18N.addToCartError
					|| 'Could not add to cart.';
				callback( false, null, msg );
			}
		} )
		.fail( function () {
			callback( false, null, I18N.addToCartError || 'Server error. Please try again.' );
		} );
	}

	// =========================================================================
	// Step 3 — Success
	// =========================================================================

	/**
	 * Handle a successful cart submission.
	 *
	 * Updates the mini-cart count, shows a success notice, and — if configured
	 * to redirect — sends the user to the cart page.
	 *
	 * @param {object} data   Response data from CartIntegration::handle_add_to_cart.
	 * @param {jQuery} $btn   The clicked button.
	 * @param {object} cfg    Per-instance config object.
	 * @param {object} I18N   i18n strings.
	 */
	function onCartSuccess( data, $btn, cfg, I18N ) {
		// Update mini-cart count via WC's own fragment refresh mechanism.
		$( document.body ).trigger( 'wc_fragment_refresh' );
		$( document.body ).trigger( 'added_to_cart', [ {}, '', null ] );

		// Show inline success notice.
		showSuccess(
			$btn,
			( I18N.addedToCart || 'Added to cart!' ) +
			( data.total_formatted
				? ' ' + ( data.currency_symbol || '' ) + data.total_formatted
				: '' )
		);

		// A server-supplied redirect wins over every client-side rule: it means
		// the store's order route has already decided where this pizza goes
		// (the straight-to-checkout route sends the customer past the cart).
		if ( data.redirect ) {
			setTimeout( function () {
				window.location.href = data.redirect;
			}, 600 );
			return;
		}

		// Redirect to cart if configured to do so.
		if ( shouldRedirectToCart( cfg ) && data.cart_url ) {
			setTimeout( function () {
				window.location.href = data.cart_url;
			}, 800 );
		}
	}

	// =========================================================================
	// UI helpers
	// =========================================================================

	/**
	 * Put the button and page into loading/submitting state.
	 *
	 * @param {boolean} submitting
	 * @param {jQuery}  $btn
	 * @param {object}  I18N
	 */
	function setSubmitting( submitting, $btn, I18N ) {
		isSubmitting = submitting;

		if ( submitting ) {
			$btn
				.addClass( 'pztc-btn--loading' )
				.prop( 'disabled', true )
				.data( 'original-text', $btn.text() )
				.text( I18N.addingToCart || 'Adding…' );
		} else {
			$btn
				.removeClass( 'pztc-btn--loading' )
				.prop( 'disabled', false )
				.text( $btn.data( 'original-text' ) || ( I18N.addToCart || 'Add to Cart' ) );
		}
	}

	/**
	 * Show an error notice near the add-to-cart button.
	 *
	 * @param {jQuery} $btn
	 * @param {string} message
	 */
	function showError( $btn, message ) {
		getOrCreateNotice( $btn, 'error' ).text( message ).show();
	}

	/**
	 * Show a success notice near the add-to-cart button.
	 *
	 * @param {jQuery} $btn
	 * @param {string} message
	 */
	function showSuccess( $btn, message ) {
		getOrCreateNotice( $btn, 'success' ).text( message ).show();
		// Auto-dismiss after 5 seconds.
		setTimeout( function () { clearMessages( $btn ); }, 5000 );
	}

	/**
	 * Remove all cart notices within the same builder section as $btn.
	 *
	 * @param {jQuery} $btn
	 */
	function clearMessages( $btn ) {
		const $section = $btn.closest( '.pztc-builder-section' );
		const $scope   = $section.length ? $section : $( document );
		$scope.find( '.pztc-cart-notice' ).hide().text( '' );
	}

	/**
	 * Get or create the cart notice element of the given type, scoped to the
	 * builder section containing $btn so multi-instance pages don't cross-contaminate.
	 *
	 * @param {jQuery} $btn   The clicked button.
	 * @param {string} type   'error' | 'success'
	 * @returns {jQuery}
	 */
	function getOrCreateNotice( $btn, type ) {
		// Find the containing builder section; fall back to document scope.
		const $section    = $btn.closest( '.pztc-builder-section' );
		const instanceIdx = $section.length ? ( $section.data( 'pztc-instance' ) || '' ) : '';
		const id          = 'pztc-cart-notice-' + type + ( instanceIdx ? '-' + instanceIdx : '' );

		let $el = $( '#' + id );

		if ( $el.length === 0 ) {
			$el = $( '<div>', {
				id:    id,
				class: 'pztc-cart-notice pztc-cart-notice--' + type,
				role:  'alert',
			} ).hide();

			// Insert after the scoped price bar, or before the button, or at the
			// end of the builder section — whichever exists first.
			const idSuffix = instanceIdx ? '-' + instanceIdx : '';
			const $anchor  = $( '#pztc-price-bar' + idSuffix ).length
				? $( '#pztc-price-bar' + idSuffix )
				: $btn.parent();

			if ( $anchor.length ) {
				$anchor.after( $el );
			} else if ( $section.length ) {
				$section.append( $el );
			} else {
				$( '#pztc-builder-section' + idSuffix ).append( $el );
			}
		}

		return $el;
	}

	/**
	 * Determine whether to redirect to the cart after a successful add.
	 *
	 * Priority: PizzaTier setting → WooCommerce redirect setting.
	 *
	 * @param {object} cfg  Per-instance config object.
	 * @returns {boolean}
	 */
	function shouldRedirectToCart( cfg ) {
		// PizzaTier setting takes precedence when explicitly set.
		if ( cfg.redirectAfterAdd === true ) {
			return true;
		}
		if ( cfg.redirectAfterAdd === false ) {
			return false;
		}
		// Fall back to WooCommerce's own redirect setting.
		if ( typeof wc_add_to_cart_params !== 'undefined' ) {
			return '1' === wc_add_to_cart_params.cart_redirect_after_add;
		}
		return false;
	}

	/**
	 * Resolve the order note for submission.
	 *
	 * Priority:
	 *  1. The checkout-bar's own .pztc-order-note-input textarea (per-instance,
	 *     rendered inside the builder template's checkout-bar.php).
	 *  2. The standalone .pztc-order-note__input rendered by FrontendEmbed
	 *     (the product-page level note field).
	 *  3. state.orderNote if present (legacy / JS-set value).
	 *
	 * @param {jQuery} $btn   The clicked button.
	 * @param {object} state  Builder state object.
	 * @returns {string}
	 */
	function resolveOrderNote( $btn, state ) {
		// 1. Look for the bar-level notes textarea scoped to this instance.
		var instanceId = $btn.data( 'instance' ) || '';
		// Try data-pztc-instance on button, then walk up to builder section.
		var rawIdxN = $btn.data( 'pztc-instance' ) || $btn.data( 'instance' ) || '';
		if ( ! instanceId && rawIdxN ) instanceId = 'pztc-' + rawIdxN;
		if ( ! instanceId ) {
			var $secN = $btn.closest( '.pztc-builder-section[data-pztc-instance]' );
			if ( $secN.length ) {
				instanceId = 'pztc-' + $secN.data( 'pztc-instance' );
			}
		}
		if ( instanceId ) {
			var $barNote = $( '.pztc-order-note-input[data-instance="' + instanceId + '"]' );
			if ( $barNote.length ) {
				return $.trim( $barNote.val() );
			}
		}

		// 2. Standalone note field (FrontendEmbed's render_order_note_field).
		var $section = $btn.closest( '.pztc-builder-section' );
		if ( $section.length ) {
			var $standaloneNote = $section.find( '.pztc-order-note__input' );
			if ( $standaloneNote.length ) {
				return $.trim( $standaloneNote.val() );
			}
		}

		// 3. Fall back to builder state (e.g. set programmatically).
		return state.orderNote || '';
	}

	/**
	 * Resolve the quantity for submission from the checkout-bar's stepper.
	 *
	 * Reads the .pztc-qty-value element for this instance. Defaults to 1.
	 *
	 * @param {jQuery} $btn  The clicked button.
	 * @returns {number}
	 */
	function resolveQuantity( $btn ) {
		// Try data-pztc-instance or data-instance attribute on the button first.
		var rawIdx = $btn.data( 'pztc-instance' ) || $btn.data( 'instance' ) || '';
		var instanceId = rawIdx ? 'pztc-' + rawIdx : '';

		// If not set, derive it from the closest builder-section wrapper.
		if ( ! instanceId ) {
			var $sec = $btn.closest( '.pztc-builder-section[data-pztc-instance]' );
			if ( $sec.length ) {
				instanceId = 'pztc-' + $sec.data( 'pztc-instance' );
			}
		}

		if ( ! instanceId ) return 1;

		var $display = $( '#pztc-qty-' + instanceId );
		if ( ! $display.length ) return 1;

		// cart.js reads dataset.qty (set by checkout-bar.js) or falls back to textContent.
		var qty = parseInt( $display.data( 'qty' ) || $display.text(), 10 );
		return isNaN( qty ) || qty < 1 ? 1 : Math.min( qty, 99 );
	}

} )( jQuery );
