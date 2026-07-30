<?php
/**
 * Hides the native "Criar uma conta?" checkbox/username/password block on
 * checkout, and creates a customer account automatically in the background
 * for every guest order — using the e-mail already entered, with an
 * auto-generated password sent by WooCommerce's native "new account" e-mail.
 * The new customer is logged in right away (same as core does for opt-in
 * account creation), so the thank-you page — and with it the Pix QR code —
 * renders normally instead of being gated behind a login form.
 *
 * Guest checkout stays fully open: `woocommerce_enable_guest_checkout` and
 * `woocommerce_enable_signup_and_login_from_checkout` are never touched
 * here. This is not the mandatory-account requirement that caused the 36h
 * zero-sales incident (Pages 011/012) — checkout completes exactly as
 * before for the customer, account creation just happens silently after,
 * instead of behind an optional checkbox.
 */

defined( 'ABSPATH' ) || exit;

// Hides the checkbox + username + password block. WooCommerce's
// form-billing.php wraps that whole block in a check against this filter
// (not woocommerce_checkout_fields), so this is the correct place to hook.
add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );

/**
 * Strips the "account" fieldset too, as defense-in-depth in case any
 * template renders it independently of is_registration_enabled().
 *
 * @param array $fields Checkout fields.
 * @return array
 */
function wi_remove_account_creation_fields( array $fields ): array {
	unset( $fields['account'] );
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'wi_remove_account_creation_fields' );

/**
 * Creates a customer account automatically after the order is placed, using
 * the billing e-mail. Never blocks or errors the checkout — any failure is
 * only logged, the order always completes normally.
 *
 * @param int $order_id
 */
function wi_auto_create_account_from_order( int $order_id ): void {
	try {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Already attached to a customer (logged-in purchase) — nothing to do.
		if ( $order->get_customer_id() ) {
			return;
		}

		$email = $order->get_billing_email();

		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$existing_user_id = email_exists( $email );

		if ( $existing_user_id ) {
			// Account already exists — leave this as a guest order, do NOT attach
			// it to that account.
			//
			// Attaching it would make WooCommerce treat the order as belonging to
			// a "known shopper", and WC_Shortcode_Checkout::order_received() then
			// refuses to render the thank-you page to anyone not logged in as that
			// customer: it prints "Please log in to your account to view this
			// order" and returns *before* the `woocommerce_thankyou` hook fires —
			// so the Pix QR code never renders and the sale is lost.
			//
			// Auto-logging the visitor in is not an option in this branch either:
			// anyone could type a stranger's e-mail at checkout and land inside
			// that person's account. WooCommerce core behaves the same way — it
			// never links a guest order to an existing account by e-mail alone.
			return;
		}

		// Empty password: WooCommerce auto-generates one and sends the
		// native "new account" e-mail (woocommerce_registration_generate_
		// password = yes on this site) — no password field on checkout.
		$new_user_id = wc_create_new_customer( $email, '', '', array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
		) );

		if ( is_wp_error( $new_user_id ) ) {
			error_log( 'wi_auto_create_account_from_order: failed for order ' . $order_id . ' — ' . $new_user_id->get_error_message() );
			return;
		}

		// Log the new customer in immediately, exactly like WooCommerce core does
		// when a shopper opts into "create an account" at checkout
		// (WC_Checkout::process_customer() -> wc_set_customer_auth_cookie()).
		//
		// This is load-bearing, not a nicety: without it the order carries a
		// customer_id while the visitor is still a guest, and order_received()
		// blocks the thank-you page behind a login form instead of running the
		// `woocommerce_thankyou` hook — which is precisely what stopped the Pix
		// QR code from ever reaching the customer.
		wc_set_customer_auth_cookie( $new_user_id );

		$order->set_customer_id( $new_user_id );
		$order->save();
	} catch ( \Throwable $e ) {
		error_log( 'wi_auto_create_account_from_order: exception for order ' . $order_id . ' — ' . $e->getMessage() );
	}
}
add_action( 'woocommerce_checkout_order_processed', 'wi_auto_create_account_from_order', 20 );
