<?php
/**
 * Forces pt-BR translations for checkout strings that render in English despite
 * WPLANG being pt_BR — root cause: elementor-pro-pt_BR.mo doesn't exist anywhere
 * on this install (Elementor Pro's own translation file, separate from the free
 * Elementor plugin's), and a few WooCommerce core strings don't match the bundled
 * woocommerce-pt_BR.po/.mo (likely a version mismatch). Scoped to the checkout page
 * only, so it can't affect wp-admin or any other page's "Login"/"Password" etc.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the translation override, but only on the checkout page.
 *
 * `gettext` fires for every single translatable string on every page load,
 * site-wide — checking is_checkout() from *inside* that filter would run the
 * check on every one of those calls. Gating registration behind the `wp` hook
 * (by which point the main query, and therefore is_checkout(), is reliable)
 * means the filter callback below doesn't exist at all on any other page.
 */
add_action( 'wp', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	add_filter( 'gettext', 'wi_checkout_translate_string', 20, 2 );
} );

/**
 * Exact-match override map for strings that fail to translate on this install.
 *
 * @param string $translated Already-attempted translation.
 * @param string $original   Original (English) source string.
 * @return string
 */
function wi_checkout_translate_string( $translated, $original ) {
	static $map = array(
		'First Name'                            => 'Nome',
		'Last Name'                              => 'Sobrenome',
		'Phone'                                  => 'Celular',
		'Company Name'                           => 'Nome da Empresa',
		'Email Address'                          => 'Endereço de E-mail',
		'Password'                                => 'Senha',
		'Have a coupon?'                         => 'Tem um cupom de desconto?',
		'Click here to enter your coupon code'   => 'Clique aqui para inserir o código do cupom',
		'Enter your coupon code'                 => 'Insira o código do seu cupom',
		'Remember me'                             => 'Lembrar de mim',
		'Lost your password?'                    => 'Esqueceu sua senha?',
		'Login'                                   => 'Entrar',
		'Apply'                                   => 'Aplicar',
		'I have read and agree to the website'   => 'Eu li e concordo com os',
		'terms and conditions'                   => 'termos e condições',
		'Returning customer?'                    => 'Já é cliente?',
		'Click here to login'                    => 'Clique aqui para entrar',
		'Create an account?'                     => 'Criar uma conta?',
	);

	return $map[ $original ] ?? $translated;
}
