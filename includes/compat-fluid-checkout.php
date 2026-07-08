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
