<?php
/**
 * Hides the native "Criar uma conta?" checkbox/username/password block on
 * checkout, and creates a customer account automatically in the background
 * for every guest order — using the e-mail already entered, with an
 * auto-generated password sent by WooCommerce's native "new account" e-mail.
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
			// Account already exists — just attach the order, no new account,
			// no error, no duplicate-e-mail notice.
			$order->set_customer_id( $existing_user_id );
			$order->save();
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

		$order->set_customer_id( $new_user_id );
		$order->save();
	} catch ( \Throwable $e ) {
		error_log( 'wi_auto_create_account_from_order: exception for order ' . $order_id . ' — ' . $e->getMessage() );
	}
}
add_action( 'woocommerce_checkout_order_processed', 'wi_auto_create_account_from_order', 20 );
