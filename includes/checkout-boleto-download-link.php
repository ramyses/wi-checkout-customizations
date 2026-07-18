<?php
/**
 * Shows a "Baixar boleto" button directly on the order-received page for
 * Banco Inter boleto orders (Page 013), not just in the e-mail notification.
 *
 * The wc-banco-inter plugin's own `thankyou_page()` (index.php) only echoes
 * the configured "Instructions" text on this page — the actual PDF link is
 * built only in `email_instructions()`, sent by e-mail. Reusing that same
 * PDF path convention here (`tmp/boleto-{order_id}.pdf`, confirmed by
 * reading the plugin's source) rather than guessing it independently.
 */

defined( 'ABSPATH' ) || exit;

function wi_render_boleto_download_link( $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'interboleto' !== $order->get_payment_method() ) {
		return;
	}

	$pdf_path = WP_CONTENT_DIR . '/plugins/wc-banco-inter/tmp/boleto-' . $order_id . '.pdf';
	if ( ! file_exists( $pdf_path ) ) {
		return;
	}

	$pdf_url = content_url( '/plugins/wc-banco-inter/tmp/boleto-' . $order_id . '.pdf' );
	?>
	<p style="margin:16px 0;">
		<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"
			style="font-weight:700;color:#fff;font-size:larger;padding:15px;background-color:#1F3A66;display:block;text-decoration:none;max-width:50%;text-align:center;border-radius:8px;">
			<?php esc_html_e( 'Baixar boleto', 'wi-checkout-customizations' ); ?>
		</a>
	</p>
	<?php
}
add_action( 'woocommerce_thankyou_interboleto', 'wi_render_boleto_download_link', 20 );
