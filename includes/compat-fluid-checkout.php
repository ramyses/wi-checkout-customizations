<?php
/**
 * Compatibility with the "Fluid Checkout for WooCommerce" plugin.
 *
 * Fluid Checkout replaces the entire checkout page output with its own
 * multi-step layout, which overrides our Elementor `[wi_checkout]` design.
 * Since Fluid Checkout stays active in production (it's also used elsewhere,
 * e.g. the cart page), we don't deactivate it — instead we use the filter
 * Fluid Checkout itself documents for page-builder compatibility to skip
 * just its checkout-page template override, only on the checkout page.
 * No-op if Fluid Checkout isn't installed.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'fc_enable_checkout_page_template', function ( $is_enabled ) {
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return false;
	}

	return $is_enabled;
}, 100 );

/**
 * Belt-and-suspenders fallback: some Fluid Checkout versions/editions still
 * pick their own checkout template even when the filter above says not to
 * (the exact cooperation logic has changed across releases). This runs after
 * every other `template_include` filter (including Fluid Checkout's own, at
 * priority 100) and forces the theme's normal page template back whenever
 * the resolved template is specifically Fluid Checkout's checkout template —
 * so `[wi_checkout]` renders through the normal WordPress content loop again.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $template;
	}

	$is_fluid_checkout_template = false !== strpos( str_replace( '\\', '/', $template ), 'checkout/page-checkout.php' );

	if ( ! $is_fluid_checkout_template ) {
		return $template;
	}

	$fallback = locate_template( array( 'page.php', 'singular.php', 'index.php' ) );

	return $fallback ? $fallback : $template;
}, PHP_INT_MAX );
