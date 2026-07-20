/**
 * Popup de lembrete de abandono no checkout.
 *
 * - Gatilho "começou a preencher": qualquer input/change em algum campo
 *   dentro de #customer_details (ou form.checkout, se o primeiro não
 *   existir) conta.
 * - Gatilho desktop: exit-intent clássico — mouseout no document com
 *   e.clientY <= 0 e e.relatedTarget === null (mouse saindo pelo topo da
 *   viewport).
 * - Gatilho mobile: interceptação do botão/gesto de voltar via History API.
 *   No carregamento, empurra um estado fantasma (history.pushState). No
 *   popstate, se o cliente já interagiu e o popup ainda não apareceu nesta
 *   sessão, mostra o popup e reempurra o estado fantasma pra não deixar sair
 *   ainda. "Sair mesmo assim" navega de fato: liga uma flag interna que
 *   deixa o próximo popstate passar direto (sem reinterceptar) e chama
 *   history.back().
 * - No máximo uma vez por sessão (sessionStorage, chave própria do plugin).
 * - Sem captura de dado nenhum: não lê nem envia valores de campo nenhum,
 *   só observa que ALGUM campo recebeu interação.
 * - Dois eventos anônimos no dataLayer do GTM, sem PII:
 *     checkout_abandon_popup_shown
 *     checkout_abandon_popup_click  (com { action: 'continuar' | 'sair' })
 */
(function () {
	'use strict';

	var SESSION_KEY = 'wiCheckoutAbandonPopupShown';

	var hasInteracted = false;
	var allowNextPopstate = false;
	var popupEl = null;

	function alreadyShownThisSession() {
		try {
			return sessionStorage.getItem( SESSION_KEY ) === '1';
		} catch ( e ) {
			// sessionStorage indisponível (ex.: modo privado em alguns navegadores) —
			// trata como "ainda não mostrado" e segue sem persistir; pior caso é o
			// popup poder reaparecer, o que é aceitável e não quebra nada.
			return false;
		}
	}

	function markShownThisSession() {
		try {
			sessionStorage.setItem( SESSION_KEY, '1' );
		} catch ( e ) {
			// Ver comentário acima.
		}
	}

	function pushToDataLayer( data ) {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( data );
	}

	function getPopupEl() {
		if ( ! popupEl ) {
			popupEl = document.getElementById( 'wi-checkout-abandon-popup' );
		}
		return popupEl;
	}

	function showPopup() {
		if ( alreadyShownThisSession() ) {
			return;
		}

		var el = getPopupEl();
		if ( ! el ) {
			return;
		}

		el.classList.add( 'wi-abandon-popup--visible' );
		el.setAttribute( 'aria-hidden', 'false' );
		markShownThisSession();
		pushToDataLayer( { event: 'checkout_abandon_popup_shown' } );
	}

	function hidePopup() {
		var el = getPopupEl();
		if ( ! el ) {
			return;
		}

		el.classList.remove( 'wi-abandon-popup--visible' );
		el.setAttribute( 'aria-hidden', 'true' );
	}

	function handleContinueClick() {
		pushToDataLayer( { event: 'checkout_abandon_popup_click', action: 'continuar' } );
		hidePopup();
	}

	function handleExitClick() {
		pushToDataLayer( { event: 'checkout_abandon_popup_click', action: 'sair' } );
		hidePopup();
		allowNextPopstate = true;
		try {
			history.back();
		} catch ( e ) {
			// Sem History API funcional — nada a fazer, o popup já foi fechado.
		}
	}

	function onFormInteraction() {
		hasInteracted = true;
	}

	function bindFormListeners() {
		var container = document.querySelector( '#customer_details' ) || document.querySelector( 'form.checkout' );
		if ( ! container ) {
			return;
		}

		// Captura (terceiro argumento true) pra pegar interação em qualquer campo
		// dentro do container, inclusive os que o WooCommerce recria via AJAX
		// (ex.: recalcular frete), sem precisar reatachar listeners depois.
		container.addEventListener( 'input', onFormInteraction, true );
		container.addEventListener( 'change', onFormInteraction, true );
	}

	function bindDesktopExitIntent() {
		document.addEventListener( 'mouseout', function ( e ) {
			if ( ! hasInteracted || alreadyShownThisSession() ) {
				return;
			}

			if ( e.clientY <= 0 && e.relatedTarget === null ) {
				showPopup();
			}
		} );
	}

	function bindMobileBackButton() {
		try {
			history.pushState( null, '', location.href );
		} catch ( e ) {
			// History API indisponível — sem interceptação de voltar; o
			// exit-intent de desktop continua funcionando normalmente.
			return;
		}

		window.addEventListener( 'popstate', function () {
			if ( allowNextPopstate ) {
				allowNextPopstate = false;
				return;
			}

			if ( hasInteracted && ! alreadyShownThisSession() ) {
				showPopup();
				try {
					history.pushState( null, '', location.href );
				} catch ( e ) {
					// Sem repush possível — deixa seguir sem bloquear de novo.
				}
			}
		} );
	}

	function bindPopupButtons() {
		var el = getPopupEl();
		if ( ! el ) {
			return;
		}

		var continueBtn = el.querySelector( '[data-wi-abandon-continue]' );
		var exitBtn = el.querySelector( '[data-wi-abandon-exit]' );
		var closeBtn = el.querySelector( '[data-wi-abandon-close]' );

		if ( continueBtn ) {
			continueBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleContinueClick();
			} );
		}

		if ( exitBtn ) {
			exitBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleExitClick();
			} );
		}

		if ( closeBtn ) {
			// Botão de fechar (X) tem o mesmo comportamento do botão secundário
			// (deixa sair), conforme decidido no spec.
			closeBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				handleExitClick();
			} );
		}
	}

	function init() {
		if ( ! getPopupEl() ) {
			return;
		}

		bindFormListeners();
		bindPopupButtons();
		bindDesktopExitIntent();
		bindMobileBackButton();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
