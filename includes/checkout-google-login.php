<?php
/**
 * Adds Woodmart's "Google" login button to the checkout's own login form.
 *
 * Google login is configured and working (goo_app_id/goo_app_secret filled
 * in the Woodmart theme options) and already shows up in the header's login
 * popup, drawn by woodmart_login_form() in
 * themes/woodmart/inc/integrations/woocommerce/template-tags.php. It does
 * NOT show up on the checkout, because Elementor Pro's checkout widget
 * doesn't reuse that function or WooCommerce's own
 * `global/form-login.php` template — it renders a fully custom login form
 * (render_woocommerce_checkout_login_form() in
 * elementor-pro/modules/woocommerce/widgets/checkout.php, after explicitly
 * doing `remove_action( 'woocommerce_before_checkout_form',
 * 'woocommerce_checkout_login_form' )`) with no woocommerce_login_form_*
 * hooks fired inside it at all. So there is no PHP hook to attach to on the
 * checkout's login form — the button is inserted client-side instead, in
 * assets/js/wi-checkout.js.
 *
 * What stays in PHP is everything that shouldn't be reimplemented in JS:
 * confirming Google login is actually configured (same gate Woodmart's own
 * button uses), and building the login URL with the exact query args
 * Woodmart's button uses — `social_auth=google&is_checkout=1`. That second
 * arg isn't cosmetic: woodmart-core/inc/auth.php only saves the "return to
 * checkout after login" cookie when it's present; without it the customer
 * would land on "Minha Conta" after authenticating, cart out of sight.
 *
 * Do not edit the theme or woodmart-core to fix this instead — both get
 * updated and the change would be lost (theme also has no active license).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	$woocommerce_loaded = function_exists( 'is_checkout' ) && function_exists( 'is_order_received_page' );

	if ( ! $woocommerce_loaded || ! is_checkout() || is_order_received_page() || is_user_logged_in() ) {
		return;
	}

	// Same gate the theme itself checks before drawing the button
	// (woodmart_login_form(), template-tags.php) — keeps this dormant if the
	// social-login add-on isn't active or Google isn't configured.
	if ( ! class_exists( 'WOODMART_Auth', false ) || ! function_exists( 'woodmart_get_opt' ) ) {
		return;
	}

	$goo_app_id     = woodmart_get_opt( 'goo_app_id' );
	$goo_app_secret = woodmart_get_opt( 'goo_app_secret' );

	if ( empty( $goo_app_id ) || empty( $goo_app_secret ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$social_url  = add_query_arg( array( 'social_auth' => 'google' ), wc_get_page_permalink( 'myaccount' ) );
	$social_url .= '&is_checkout=1';

	// Reuses the theme's own inline stylesheet for .wd-social-login /
	// .login-goo-link instead of adding new CSS, so it can't be swept away
	// by "Remove Unused CSS" the way a bespoke rule was before — see
	// docs/litespeed-js-nao-atrasar.md.
	if ( function_exists( 'woodmart_enqueue_inline_style' ) ) {
		woodmart_enqueue_inline_style( 'woo-opt-social-login' );
	}

	add_action(
		'wp_footer',
		function () use ( $social_url ) {
			// A JSON data island, not a text/javascript <script> — JS/CSS
			// "combine & optimize" plugins (WP Rocket, LiteSpeed) only sweep
			// up script tags they recognize as JS, so this is left alone.
			// assets/js/wi-checkout.js reads it and builds the button.
			?>
			<script type="application/json" id="wi-checkout-google-login-data"><?php echo wp_json_encode( array( 'url' => esc_url_raw( $social_url ) ) ); ?></script>
			<?php
		}
	);
}, 20 );
