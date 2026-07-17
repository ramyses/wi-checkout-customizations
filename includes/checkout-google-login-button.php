<?php
/**
 * Renders the Nextend Social Login "Continue with Google" button inside the
 * checkout's account-creation area.
 *
 * Why not just use Nextend's own WooCommerce settings tab: that integration
 * (billing form / login form / register form button placement) is gated
 * behind Nextend Social Login PRO — the free version installed on this site
 * only auto-injects into the plain WordPress login/register forms
 * (wp-login.php, My Account), not the WooCommerce checkout billing form.
 * The shortcode itself (`[nextend_social_login]`, registered via
 * `add_shortcode('nextend_social_login', ...)` in nextend-social-login.php)
 * is a public, documented free-tier feature though — calling it directly
 * from a hook is a legitimate use of the plugin's own extensibility point,
 * not a workaround of a paywall.
 *
 * Placement: `woocommerce_before_checkout_registration_form` fires right
 * before WooCommerce's own "Criar uma conta?" checkbox/fields block for
 * guest checkout — the button appears as an alternative right above it.
 */

defined( 'ABSPATH' ) || exit;

function wi_render_checkout_google_login_button(): void {
	if ( is_user_logged_in() || ! shortcode_exists( 'nextend_social_login' ) ) {
		return;
	}

	echo do_shortcode( '[nextend_social_login provider="google" style="fullwidth" heading="Ou entre com sua conta Google"]' );
}
add_action( 'woocommerce_before_checkout_registration_form', 'wi_render_checkout_google_login_button' );
