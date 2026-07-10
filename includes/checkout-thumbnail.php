<?php
/**
 * Prepends a product thumbnail to each line item on the checkout review-order
 * table. This checkout is rendered through an Elementor Pro template that wraps
 * WooCommerce's *default* review-order.php, which only ever had two columns
 * (product name and subtotal) — unlike the normal cart, which has its own
 * dedicated thumbnail column. There's no missing image to restore here; this
 * adds a column that never existed on this specific checkout.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_cart_item_name', 'wi_checkout_prepend_thumbnail', 10, 3 );

/**
 * Prepends the product's thumbnail markup to the cart item name.
 *
 * `woocommerce_cart_item_name` fires on the cart page, the mini-cart, order
 * emails, and the checkout review-order table alike, so this must only act on
 * the checkout. The AJAX branch is required too: WooCommerce re-renders the
 * whole review-order table via `update_order_review` every time shipping or
 * the address changes, and that AJAX request does NOT satisfy is_checkout()
 * — without this guard, the thumbnail would vanish after the first shipping
 * recalculation. Anywhere else (cart, mini-cart, emails) is left untouched,
 * since those already have their own image handling (or intentionally none).
 *
 * @param string $name         Cart item name/link markup.
 * @param array  $cart_item    Cart item data, includes the WC_Product in 'data'.
 * @param string $cart_item_key Cart item key.
 * @return string
 */
function wi_checkout_prepend_thumbnail( $name, $cart_item, $cart_item_key ) {
	$is_checkout_ajax_update = defined( 'DOING_AJAX' ) && DOING_AJAX
		&& isset( $_POST['action'] ) && 'update_order_review' === $_POST['action'];

	if ( ! ( is_checkout() || $is_checkout_ajax_update ) ) {
		return $name;
	}

	$product = $cart_item['data'];

	if ( ! $product instanceof WC_Product ) {
		return $name;
	}

	$thumbnail = $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'wi-checkout-thumb' ) );

	return $thumbnail . $name;
}
