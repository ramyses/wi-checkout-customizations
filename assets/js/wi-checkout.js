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

	// Requires an account (existing login, or "Criar uma conta?" checked) to pay by
	// credit card (Mercado Pago Checkout Transparente). This is UX only — the real
	// gate is server-side (includes/checkout-require-account-for-card.php), which
	// rejects the order even if this script never runs. Pix and every other
	// payment method are untouched.
	//
	// "Logged in" is inferred from the DOM rather than a localized flag: WooCommerce's
	// own checkout template only renders `.woocommerce-account-fields` (the "Criar uma
	// conta?" block) for guests, so its absence means the customer already has a
	// session. Switching between Pix and Cartão de Crédito doesn't need any special
	// handling to preserve the checkbox state — `.woocommerce-account-fields` lives in
	// the billing fields, not inside the payment-methods list that WooCommerce
	// re-renders via AJAX, so it's never recreated by an `updated_checkout` refresh.

	var CARD_GATEWAY_ID = 'woo-mercado-pago-custom';
	var NOTICE_ID = 'wi-require-account-notice';
	var NOTICE_TEXT = 'Para pagar no cartão de crédito é necessário ter uma conta. '
		+ 'Faça login ou marque "Criar uma conta?" acima para continuar, ou escolha Pix para comprar sem conta.';

	function ensureNoticeEl() {
		var existing = document.getElementById( NOTICE_ID );
		if ( existing ) {
			return existing;
		}
		var paymentMethods = document.querySelector( '#payment ul.payment_methods' );
		if ( ! paymentMethods ) {
			return null;
		}
		var el = document.createElement( 'div' );
		el.id = NOTICE_ID;
		el.setAttribute( 'role', 'alert' );
		el.style.cssText = 'display:none;margin-top:12px;padding:10px 12px;'
			+ 'background:#FFF4E5;border:1px solid #F5C177;border-radius:8px;'
			+ 'color:#7A4A00;font-size:13px;line-height:1.5;';
		el.textContent = NOTICE_TEXT;
		paymentMethods.insertAdjacentElement( 'afterend', el );
		return el;
	}

	function isGuestWithoutAccountCheckbox() {
		var accountFields = document.querySelector( '.woocommerce-account-fields' );
		if ( ! accountFields ) {
			// No account-fields block in the DOM at all = already logged in.
			return false;
		}
		var createAccount = document.getElementById( 'createaccount' );
		return ! createAccount || ! createAccount.checked;
	}

	function updateCardAccountGate() {
		var cardRadio = document.getElementById( 'payment_method_' + CARD_GATEWAY_ID );
		var notice = ensureNoticeEl();
		var placeOrder = document.getElementById( 'place_order' );

		var mustBlock = !! ( cardRadio && cardRadio.checked && isGuestWithoutAccountCheckbox() );

		if ( notice ) {
			notice.style.display = mustBlock ? 'block' : 'none';
		}
		if ( placeOrder ) {
			placeOrder.disabled = mustBlock;
		}
	}

	function init() {
		updateCardAccountGate();

		var paymentMethods = document.querySelector( '#payment ul.payment_methods' );
		if ( paymentMethods ) {
			paymentMethods.addEventListener( 'change', updateCardAccountGate );
		}

		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.id === 'createaccount' ) {
				updateCardAccountGate();
			}
		} );

		if ( window.jQuery ) {
			jQuery( document.body ).on( 'updated_checkout', updateCardAccountGate );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
