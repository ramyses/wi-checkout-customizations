(function () {
	'use strict';

	function reorderCheckout() {
		var container   = document.querySelector( '.e-checkout__container' );
		var informacoes = document.querySelector( '.e-checkout__column-start' );
		var produtos    = document.querySelector( '.e-checkout__order_review' );
		var pagamento   = document.querySelector( '.e-checkout__order_review-2' );
		var couponBox   = document.querySelector( '.e-coupon-box' );

		if ( ! container || ! informacoes || ! produtos || ! pagamento ) {
			return;
		}

		// "Criar uma conta?" (.woocommerce-account-fields) sits right after the billing
		// fields but OUTSIDE the "Seus dados" white card — move it inside so it stops
		// floating on the plain background.
		var billingFields = informacoes.querySelector( '.woocommerce-billing-fields' );
		var accountFields = informacoes.querySelector( '.woocommerce-account-fields' );
		if ( billingFields && accountFields ) {
			billingFields.appendChild( accountFields );
		}

		container.appendChild( produtos );
		container.appendChild( informacoes );
		// The coupon box lives nested inside "Produtos" by default (right after the
		// totals table) — pulled out here into its own block, positioned right before
		// "Pagamento" instead of squeezed inside the products card.
		if ( couponBox ) {
			container.appendChild( couponBox );
		}
		container.appendChild( pagamento );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', reorderCheckout );
	} else {
		reorderCheckout();
	}
})();

(function () {
	'use strict';

	// Simple value summary (Valor + Frete + Taxa/Desconto if any = Total) shown right
	// under the payment methods list. Re-reads WooCommerce's own totals table, which
	// already updates via AJAX ("updated_checkout") whenever the payment method
	// changes — no new calculation is invented here, just a compact readout of
	// numbers WooCommerce already computed.
	function cellText( selector ) {
		var el = document.querySelector( selector );
		return el ? el.textContent.trim() : null;
	}

	function ensureSummaryEl() {
		var existing = document.getElementById( 'wi-payment-summary' );
		if ( existing ) {
			return existing;
		}
		var paymentMethods = document.querySelector( '#payment ul.payment_methods' );
		if ( ! paymentMethods ) {
			return null;
		}
		var el = document.createElement( 'div' );
		el.id = 'wi-payment-summary';
		el.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;'
			+ 'margin-top:12px;padding:10px 12px;background:#F1EFFA;border-radius:8px;'
			+ 'font-size:12.5px;color:#221E32;';
		paymentMethods.insertAdjacentElement( 'afterend', el );
		return el;
	}

	// Builds one "<span>Label: <strong>value</strong></span>" block via DOM APIs
	// (no innerHTML/string concatenation) so text read from the totals table can
	// never be interpreted as markup, even though the source is trusted today.
	function appendSummaryPart( el, label, value, color ) {
		var span = document.createElement( 'span' );
		if ( color ) {
			span.style.color = color;
		}
		span.appendChild( document.createTextNode( label + ': ' ) );
		var strong = document.createElement( 'strong' );
		strong.textContent = value;
		span.appendChild( strong );
		el.appendChild( span );
	}

	function updateSummary() {
		var el = ensureSummaryEl();
		if ( ! el ) {
			return;
		}

		var subtotal = cellText( '.cart-subtotal td' );
		var shipping = cellText( '.woocommerce-shipping-totals.shipping td' );
		var fee      = cellText( 'tr.fee td' );
		var total    = cellText( '.order-total td' );

		while ( el.firstChild ) {
			el.removeChild( el.firstChild );
		}

		if ( subtotal ) { appendSummaryPart( el, 'Valor', subtotal ); }
		if ( shipping ) { appendSummaryPart( el, 'Frete', shipping ); }
		if ( fee )      { appendSummaryPart( el, 'Taxa/Desconto', fee ); }
		if ( total )    { appendSummaryPart( el, 'Total', total, '#6D3CF2' ); }
	}

	function init() {
		updateSummary();
		if ( window.jQuery ) {
			jQuery( document.body ).on( 'updated_checkout', updateSummary );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();

(function () {
	'use strict';

	// Collapses the shipping method list (ul#shipping_method) down to a single
	// clickable summary row showing only the currently selected option; clicking
	// it expands the full radio list so another method can be chosen. WooCommerce
	// re-renders this list from scratch on every "updated_checkout" (e.g. after a
	// CEP lookup changes what methods are available), so setup re-runs each time
	// rather than relying on a one-time init flag on a node that may not exist anymore.

	function labelFor( input ) {
		var label = input.parentNode.querySelector( 'label[for="' + input.id + '"]' );
		return label ? label.innerHTML : '';
	}

	function setupShippingCollapse() {
		var list = document.getElementById( 'shipping_method' );
		if ( ! list || list.dataset.wiCollapse === 'ready' ) {
			return;
		}
		list.dataset.wiCollapse = 'ready';

		var wrapper = document.createElement( 'div' );
		wrapper.className = 'wi-shipping-box';
		list.parentNode.insertBefore( wrapper, list );
		wrapper.appendChild( list );

		var summary = document.createElement( 'div' );
		summary.className = 'wi-shipping-summary';
		wrapper.insertBefore( summary, list );

		function collapse() {
			var checked = list.querySelector( 'input.shipping_method:checked' );
			summary.innerHTML = ( checked ? labelFor( checked ) : 'Selecione a forma de entrega' )
				+ '<span class="wi-shipping-chevron">&#8250;</span>';
			summary.style.display = 'flex';
			list.style.display = 'none';
		}

		function expand() {
			summary.style.display = 'none';
			list.style.display = 'block';
		}

		summary.addEventListener( 'click', expand );
		list.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.classList.contains( 'shipping_method' ) ) {
				collapse();
			}
		} );

		collapse();
	}

	function init() {
		setupShippingCollapse();
		if ( window.jQuery ) {
			jQuery( document.body ).on( 'updated_checkout', setupShippingCollapse );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();

(function () {
	'use strict';

	// Guest checkout is off (Page 012), so an e-mail that already has an account
	// would otherwise only surface WooCommerce's generic error after clicking
	// Place Order, throwing away the whole form. This checks the e-mail on blur
	// and, if it matches an account, reveals a password field right there —
	// logging in over AJAX (wi_checkout_email_login, includes/checkout-existing-email-login.php)
	// without leaving checkout. wiCheckoutEmailLogin (ajaxUrl/nonce) is only
	// localized for logged-out visitors on the checkout page, so this script
	// is a silent no-op for anyone already logged in.

	if ( typeof window.wiCheckoutEmailLogin === 'undefined' ) {
		return;
	}

	var CONFIG = window.wiCheckoutEmailLogin;
	var BOX_ID = 'wi-existing-email-login';
	var lastCheckedEmail = '';

	function ajaxPost( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', CONFIG.nonce );
		for ( var key in data ) {
			if ( Object.prototype.hasOwnProperty.call( data, key ) ) {
				body.append( key, data[ key ] );
			}
		}
		return fetch( CONFIG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } );
	}

	function removeBox() {
		var existing = document.getElementById( BOX_ID );
		if ( existing ) {
			existing.parentNode.removeChild( existing );
		}
	}

	function showLoginBox( email, emailField ) {
		removeBox();

		var box = document.createElement( 'div' );
		box.id = BOX_ID;
		box.style.cssText = 'margin:8px 0 16px;padding:12px;background:#FFFFFF;'
			+ 'border:1px solid #C7DBFF;border-radius:8px;';

		var label = document.createElement( 'p' );
		label.style.cssText = 'margin:0 0 8px;font-size:13.5px;color:#1F3A66;';
		label.textContent = 'Já existe uma conta com este e-mail. Digite sua senha para continuar:';
		box.appendChild( label );

		var row = document.createElement( 'div' );
		row.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;align-items:center;';

		var passwordInput = document.createElement( 'input' );
		passwordInput.type = 'password';
		passwordInput.placeholder = 'Sua senha';
		passwordInput.autocomplete = 'current-password';
		passwordInput.style.cssText = 'flex:1;min-width:160px;padding:8px 10px;'
			+ 'border:1px solid #C7DBFF;border-radius:6px;';
		row.appendChild( passwordInput );

		var loginButton = document.createElement( 'button' );
		loginButton.type = 'button';
		loginButton.textContent = 'Entrar';
		loginButton.style.cssText = 'padding:8px 16px;background:#1F3A66;color:#fff;'
			+ 'border:none;border-radius:6px;cursor:pointer;';
		row.appendChild( loginButton );

		box.appendChild( row );

		var errorMsg = document.createElement ( 'p' );
		errorMsg.style.cssText = 'margin:8px 0 0;font-size:12.5px;color:#B3261E;display:none;';
		box.appendChild( errorMsg );

		function attemptLogin() {
			errorMsg.style.display = 'none';
			loginButton.disabled = true;
			loginButton.textContent = 'Entrando...';

			ajaxPost( 'wi_checkout_email_login', { email: email, password: passwordInput.value } )
				.then( function ( res ) {
					if ( res && res.success ) {
						if ( window.jQuery ) {
							jQuery( document.body ).trigger( 'update_checkout' );
						}
						location.reload();
						return;
					}
					loginButton.disabled = false;
					loginButton.textContent = 'Entrar';
					errorMsg.textContent = ( res && res.data && res.data.message ) || 'Não foi possível entrar.';
					errorMsg.style.display = 'block';
				} )
				.catch( function () {
					loginButton.disabled = false;
					loginButton.textContent = 'Entrar';
					errorMsg.textContent = 'Erro de conexão. Tente novamente.';
					errorMsg.style.display = 'block';
				} );
		}

		loginButton.addEventListener( 'click', attemptLogin );
		passwordInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				attemptLogin();
			}
		} );

		emailField.insertAdjacentElement( 'afterend', box );
		passwordInput.focus();
	}

	function checkEmail( emailField ) {
		var email = emailField.value.trim();

		if ( email === lastCheckedEmail ) {
			return;
		}
		lastCheckedEmail = email;

		if ( ! email || email.indexOf( '@' ) === -1 ) {
			removeBox();
			return;
		}

		ajaxPost( 'wi_checkout_email_exists', { email: email } )
			.then( function ( res ) {
				if ( emailField.value.trim() !== email ) {
					return; // user kept typing; a newer check will follow
				}
				if ( res && res.success && res.data && res.data.exists ) {
					showLoginBox( email, emailField );
				} else {
					removeBox();
				}
			} )
			.catch( function () {
				// Silent fail — this is a UX nicety, not a security gate.
			} );
	}

	function bind() {
		var emailField = document.getElementById( 'billing_email' );
		if ( ! emailField || emailField.dataset.wiEmailLoginBound === 'yes' ) {
			return;
		}
		emailField.dataset.wiEmailLoginBound = 'yes';
		emailField.addEventListener( 'blur', function () {
			checkEmail( emailField );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
	if ( window.jQuery ) {
		jQuery( document.body ).on( 'updated_checkout', bind );
	}
})();
