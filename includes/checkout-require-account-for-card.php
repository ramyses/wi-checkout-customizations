<?php
/**
 * Requires an account (existing login, or "create an account" checked
 * during checkout) to pay by credit card (Mercado Pago Checkout Transparente,
 * gateway id `woo-mercado-pago-custom`). Pix and every other gateway are
 * unaffected.
 *
 * Why: production data (Page 011 SPEC, session 2026-07-17) showed guest
 * credit-card orders above ~R$3.000 are almost always rejected by Mercado
 * Pago's antifraud engine (`cc_rejected_high_risk`), even after the CPF fix
 * (Page 009) — the dominant signal appears to be account/identity history,
 * not CPF presence. Requiring an account for card payments is a UX-level
 * mitigation for that, decided by the store owner rather than something
 * Mercado Pago's API lets us configure directly.
 *
 * This is server-side enforcement — the real gate. `assets/js/wi-checkout.js`
 * has the matching client-side UX (disables the place-order button with an
 * inline notice), but that's convenience only; a request that skips the JS
 * entirely (or has it disabled) still has to pass through here.
 */

defined( 'ABSPATH' ) || exit;

define( 'WI_REQUIRE_ACCOUNT_CARD_GATEWAY_ID', 'woo-mercado-pago-custom' );
define( 'WI_REQUIRE_ACCOUNT_CARD_MESSAGE', 'Para pagar no cartão de crédito é necessário ter uma conta. Faça login ou marque "Criar uma conta?" acima para continuar, ou escolha Pix para comprar sem conta.' );

/**
 * Hooked on `woocommerce_after_checkout_validation`, which runs after
 * WooCommerce's own field validation and gives access to both the parsed
 * `$data` array (includes `payment_method`) and the `$errors` object to add
 * to — no manual `$_POST` parsing needed.
 */
function wi_require_account_for_card_validate( array $data, \WP_Error $errors ): void {
	if ( ( $data['payment_method'] ?? '' ) !== WI_REQUIRE_ACCOUNT_CARD_GATEWAY_ID ) {
		return;
	}

	if ( is_user_logged_in() ) {
		return;
	}

	if ( ! empty( $_POST['createaccount'] ) ) {
		return;
	}

	$errors->add( 'validation', WI_REQUIRE_ACCOUNT_CARD_MESSAGE );
}
add_action( 'woocommerce_after_checkout_validation', 'wi_require_account_for_card_validate', 10, 2 );
