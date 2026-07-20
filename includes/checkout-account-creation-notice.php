<?php
/**
 * Shows a notice on checkout explaining that an account is created
 * automatically with the e-mail entered below, and the password arrives by
 * e-mail — since Page 012 turned off guest checkout entirely
 * (`woocommerce_enable_guest_checkout` = no), every checkout now creates an
 * account, so this isn't conditional on any checkbox; it's always shown to
 * a logged-out visitor.
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
