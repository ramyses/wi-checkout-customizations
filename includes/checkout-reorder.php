<?php
/**
 * Reorders the checkout blocks (Produtos / Informações / Pagamento) and shows a
 * payment summary (Valor + Frete + Taxa/Desconto = Total). Loaded as a real enqueued
 * file (not inline) specifically so it can be excluded from LiteSpeed Cache's
 * "Combine JS" feature — an inline version got swept into the same combined bundle
 * as dozens of other plugins' scripts, and a syntax error in one of THOSE broke
 * parsing for the whole bundle, silently killing ours too. See docs/litespeed-js-nao-atrasar.md.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
	$woocommerce_loaded = function_exists( 'is_checkout' ) && function_exists( 'is_order_received_page' );

	if ( ! $woocommerce_loaded || ! is_checkout() || is_order_received_page() ) {
		return;
	}

	$script_path = WI_CHECKOUT_DIR . 'assets/js/wi-checkout.js';

	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'wi-checkout',
			WI_CHECKOUT_URL . 'assets/js/wi-checkout.js',
			array( 'jquery' ),
			filemtime( $script_path ),
			true
		);
	} else {
		error_log( 'WI Checkout: asset ausente: ' . $script_path );
	}

	$style_path = WI_CHECKOUT_DIR . 'assets/css/wi-checkout-thumb.css';

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'wi-checkout-thumb',
			WI_CHECKOUT_URL . 'assets/css/wi-checkout-thumb.css',
			array(),
			filemtime( $style_path )
		);
	} else {
		error_log( 'WI Checkout: asset ausente: ' . $style_path );
	}
} );
