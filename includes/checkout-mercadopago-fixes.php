<?php
/**
 * Patches the official woocommerce-mercadopago plugin (validated against
 * v8.8.1) to fill in a payment-risk field the plugin's own code omits.
 *
 * `AbstractPaymentTransaction::setPayerTransaction()` builds the root-level
 * `payer` object sent to Mercado Pago's Payments API — it sets email,
 * first/last name and address, but never sets `payer->phone`, even though
 * the exact same `OrderBilling::getPhone()` helper it could reuse is already
 * called elsewhere in the plugin (for `additional_info.payer.phone`, which
 * *is* populated). Confirmed via a real production payment log
 * (transaction 168160711391, 2026-07-16): `additional_info.payer.phone` had
 * the number, but the root `payer.phone` was empty. Mercado Pago's own
 * documentation lists a complete payer profile — phone included — as one of
 * the signals that improves fraud-risk scoring, so this is a real gap, not
 * cosmetic.
 *
 * There is no WordPress hook anywhere in the plugin's Transactions/ folder
 * (confirmed by grep — zero `apply_filters`/`do_action` in that whole
 * directory) to inject this from outside, so the fix has to edit the vendor
 * file directly. The plugin also has `auto_update_plugins` enabled for
 * itself on this install, so the patch must survive an update — hence the
 * same idempotent-patch-with-safety-net shape already used for the CPF and
 * melidata fixes (see the mu-plugin `mp-antifraud-signal-patch.php` on the
 * VPS for those — this file intentionally does not touch that one; it only
 * adds the phone fix, in this plugin instead of a loose mu-plugin, since
 * this plugin is the proper, version-controlled home for WI's own
 * checkout customizations).
 */

defined( 'ABSPATH' ) || exit;

define( 'WI_MP_PHONE_FIX_TARGET_PLUGIN_VERSION', '8.8.1' );
define( 'WI_MP_PHONE_FIX_MARKER', '// PATCH: root-payer-phone (wi-checkout-customizations)' );
define( 'WI_MP_PHONE_FIX_FILE', WP_CONTENT_DIR . '/plugins/woocommerce-mercadopago/src/Transactions/AbstractPaymentTransaction.php' );

/**
 * Pure function: takes the file content as a string, returns the patched
 * string if the exact target snippet is found exactly once, the unchanged
 * string if the marker is already present (idempotent no-op), or false if
 * the target snippet isn't found (plugin updated and code changed enough to
 * break the assumption) — never applies a partial/approximate patch.
 */
function wi_mp_phone_fix_patch( string $content ) {
	if ( strpos( $content, WI_MP_PHONE_FIX_MARKER ) !== false ) {
		return $content;
	}

	$search = "        \$payer             = \$this->transaction->payer;\n"
		. "        \$payer->email      = \$this->mercadopago->orderBilling->getEmail(\$this->order);\n"
		. "        \$payer->first_name = \$this->mercadopago->orderBilling->getFirstName(\$this->order);\n"
		. "        \$payer->last_name  = \$this->mercadopago->orderBilling->getLastName(\$this->order);\n";

	$first_pos = strpos( $content, $search );
	if ( false === $first_pos ) {
		return false;
	}
	if ( strpos( $content, $search, $first_pos + 1 ) !== false ) {
		// Unexpected duplicate occurrence — safer to skip than risk patching the wrong spot.
		return false;
	}

	$replace = $search
		. "        " . WI_MP_PHONE_FIX_MARKER . "\n"
		. "        \$payer->phone->number = \$this->mercadopago->orderBilling->getPhone(\$this->order);\n";

	return substr_replace( $content, $replace, $first_pos, strlen( $search ) );
}

/**
 * Lints a file with `php -l`. Returns false (never "assume it's fine") if
 * shell_exec is unavailable or the output doesn't confirm clean syntax.
 */
function wi_mp_phone_fix_lint( string $path ): bool {
	if ( ! function_exists( 'shell_exec' ) ) {
		error_log( '[wi-checkout-customizations] shell_exec indisponível — não foi possível confirmar sintaxe do patch de telefone.' );
		return false;
	}

	$escaped = escapeshellarg( $path );
	$output  = @shell_exec( "php -l {$escaped} 2>&1" );

	if ( null === $output ) {
		error_log( '[wi-checkout-customizations] php -l não retornou saída ao validar o patch de telefone.' );
		return false;
	}

	return strpos( $output, 'No syntax errors detected' ) !== false;
}

/**
 * Applies the patch. Reads the file, checks the idempotency marker, patches
 * in memory, backs up the original (once), writes, lints, and rolls back
 * automatically if the lint fails. Uses a transient as a per-request-cheap
 * cache so this isn't re-reading the file on every single page load.
 */
function wi_mp_phone_fix_apply(): void {
	$transient_key = 'wi_mp_phone_fix_ok';
	if ( get_transient( $transient_key ) ) {
		return;
	}

	$path = WI_MP_PHONE_FIX_FILE;

	if ( ! is_readable( $path ) ) {
		error_log( "[wi-checkout-customizations] Arquivo alvo do patch de telefone não encontrado/legível: {$path}" );
		return;
	}

	$original = file_get_contents( $path );
	if ( false === $original ) {
		error_log( "[wi-checkout-customizations] Falha ao ler arquivo pro patch de telefone: {$path}" );
		return;
	}

	if ( strpos( $original, WI_MP_PHONE_FIX_MARKER ) !== false ) {
		set_transient( $transient_key, 1, HOUR_IN_SECONDS );
		return;
	}

	if ( ! is_writable( $path ) ) {
		error_log( "[wi-checkout-customizations] Arquivo alvo do patch de telefone não é gravável: {$path}" );
		return;
	}

	$patched = wi_mp_phone_fix_patch( $original );

	if ( false === $patched || $patched === $original ) {
		error_log(
			"[wi-checkout-customizations] Trecho alvo não encontrado em {$path} — patch de telefone NÃO aplicado. "
			. 'O plugin pode ter sido atualizado além da versão validada (' . WI_MP_PHONE_FIX_TARGET_PLUGIN_VERSION . '). '
			. 'Verificação manual necessária.'
		);
		return;
	}

	$backup_path = $path . '.orig-wi-phone-fix';
	if ( ! file_exists( $backup_path ) ) {
		file_put_contents( $backup_path, $original );
	}

	if ( false === file_put_contents( $path, $patched ) ) {
		error_log( "[wi-checkout-customizations] Falha ao gravar o patch de telefone em {$path}." );
		return;
	}

	if ( function_exists( 'opcache_invalidate' ) ) {
		@opcache_invalidate( $path, true );
	}

	if ( ! wi_mp_phone_fix_lint( $path ) ) {
		file_put_contents( $path, $original );
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true );
		}
		error_log( "[wi-checkout-customizations] php -l falhou depois do patch de telefone em {$path}. Revertido automaticamente." );
		return;
	}

	set_transient( $transient_key, 1, HOUR_IN_SECONDS );
	error_log( "[wi-checkout-customizations] Patch de telefone (root payer.phone) aplicado com sucesso em {$path}." );
}
add_action( 'plugins_loaded', 'wi_mp_phone_fix_apply', 999 );

/**
 * Safety net: reapplies the patch automatically if woocommerce-mercadopago
 * updates (auto-update is enabled for it on this install). Same reasoning
 * as the CPF/melidata mu-plugin's Step 3 — see that file's docblock for the
 * full explanation of the two `$hook_extra` shapes WordPress uses here.
 */
function wi_mp_phone_fix_maybe_reapply_after_update( $upgrader, array $hook_extra ): void {
	if ( ! isset( $hook_extra['action'], $hook_extra['type'] )
		|| 'update' !== $hook_extra['action']
		|| 'plugin' !== $hook_extra['type']
	) {
		return;
	}

	$target_plugin   = 'woocommerce-mercadopago/woocommerce-mercadopago.php';
	$updated_plugins = array();

	if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
		$updated_plugins = $hook_extra['plugins'];
	} elseif ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
		$updated_plugins = array( $hook_extra['plugin'] );
	}

	if ( ! in_array( $target_plugin, $updated_plugins, true ) ) {
		return;
	}

	delete_transient( 'wi_mp_phone_fix_ok' );
	wi_mp_phone_fix_apply();
}
add_action( 'upgrader_process_complete', 'wi_mp_phone_fix_maybe_reapply_after_update', 10, 2 );
