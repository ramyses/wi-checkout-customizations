<?php
/**
 * Shows a notice on checkout explaining that an account is created
 * automatically with the e-mail entered below, and the password arrives by
 * e-mail. Guest checkout stays fully open (`woocommerce_enable_guest_
 * checkout` = yes, untouched) — the account is created silently in the
 * background after the order is placed (see checkout-account-auto-
 * creation.php), not as a requirement to check out. This notice replaces
 * the native "Criar uma conta?" checkbox, which is hidden by that same file.
 */

defined( 'ABSPATH' ) || exit;

function wi_render_account_creation_notice(): void {
	if ( is_user_logged_in() ) {
		return;
	}
	?>
	<div class="wi-account-creation-notice" role="note" style="margin:0 0 16px;padding:10px 12px;background:#EEF4FF;border:1px solid #C7DBFF;border-radius:8px;color:#1F3A66;font-size:13.5px;line-height:1.5;">
		Uma conta será criada com o e-mail abaixo. A senha chega por e-mail.
	</div>
	<?php
}
add_action( 'woocommerce_before_checkout_billing_form', 'wi_render_account_creation_notice' );
