<?php
/**
 * Since Page 012 turned off guest checkout, a returning customer who types
 * an e-mail that already has an account gets WooCommerce's default hard
 * error ("An account is already registered with your email address. Please
 * log in.") only after clicking Place Order — a dead end that throws away
 * the whole form. This adds an inline login right at the e-mail field
 * instead: check on blur whether the e-mail matches an account, and if so,
 * reveal a password field that logs the customer in over AJAX without
 * leaving checkout.
 *
 * Security notes:
 * - Both endpoints are nonce-protected (`wi_checkout_email_login` action).
 * - The email-exists check reveals account existence by e-mail, same as
 *   WooCommerce's own native checkout registration error already does
 *   today — not a new information leak, just surfaced earlier/nicer.
 * - Login goes through `wp_signon()`, WordPress's own authentication path
 *   (respects password hashing, account lockout plugins, etc. — Wordfence
 *   is already active on this install and applies its own brute-force
 *   protection at that same layer).
 * - No password is ever logged or stored outside the signon call.
 */

defined( 'ABSPATH' ) || exit;

function wi_checkout_email_exists_ajax(): void {
	check_ajax_referer( 'wi_checkout_email_login', 'nonce' );

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_success( [ 'exists' => false ] );
	}

	wp_send_json_success( [ 'exists' => (bool) email_exists( $email ) ] );
}
add_action( 'wp_ajax_wi_checkout_email_exists', 'wi_checkout_email_exists_ajax' );
add_action( 'wp_ajax_nopriv_wi_checkout_email_exists', 'wi_checkout_email_exists_ajax' );

function wi_checkout_email_login_ajax(): void {
	check_ajax_referer( 'wi_checkout_email_login', 'nonce' );

	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

	if ( ! is_email( $email ) || '' === $password ) {
		wp_send_json_error( [ 'message' => 'Preencha e-mail e senha.' ] );
	}

	$user = wp_signon( [
		'user_login'    => $email,
		'user_password' => $password,
		'remember'      => true,
	], is_ssl() );

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( [ 'message' => 'E-mail ou senha incorretos.' ] );
	}

	wp_set_current_user( $user->ID );
	wp_send_json_success();
}
add_action( 'wp_ajax_wi_checkout_email_login', 'wi_checkout_email_login_ajax' );
add_action( 'wp_ajax_nopriv_wi_checkout_email_login', 'wi_checkout_email_login_ajax' );

function wi_checkout_email_login_localize(): void {
	if ( ! is_checkout() || is_user_logged_in() ) {
		return;
	}

	wp_localize_script( 'wi-checkout', 'wiCheckoutEmailLogin', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'wi_checkout_email_login' ),
	] );
}
add_action( 'wp_enqueue_scripts', 'wi_checkout_email_login_localize', 20 );
