<?php
/**
 * Popup de lembrete quando o cliente demonstra intenção de sair do checkout
 * depois de já ter começado a preencher o formulário. Gatilho desktop:
 * exit-intent clássico (mouse saindo pelo topo da viewport). Gatilho mobile:
 * interceptação do botão/gesto de voltar via History API (não existe
 * exit-intent de mouse em touch). Sem incentivo/desconto e sem captura de
 * nenhum dado pessoal — só o lembrete e dois eventos anônimos no dataLayer
 * do GTM (checkout_abandon_popup_shown / checkout_abandon_popup_click).
 *
 * Guest checkout continua ativo (Page 012/reversão de hoje) — este popup não
 * exige login nem empurra criação de conta, é só reforço de conversão.
 *
 * Ver .grimoire/pages/018-popup-abandono-checkout/ pro histórico completo
 * da decisão (inspirado no popup de saída do Temu, sem o incentivo de frete
 * grátis).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enfileira o JS/CSS só na página de checkout (exclui a thank-you page).
 * Carregado como asset real enfileirado (não inline) pelo mesmo motivo já
 * documentado em checkout-reorder.php: scripts inline correm risco de cair
 * no Combine JS do LiteSpeed junto com scripts de outros plugins.
 */
add_action( 'wp_enqueue_scripts', function () {
	$woocommerce_loaded = function_exists( 'is_checkout' ) && function_exists( 'is_order_received_page' );

	if ( ! $woocommerce_loaded || ! is_checkout() || is_order_received_page() ) {
		return;
	}

	$script_path = WI_CHECKOUT_DIR . 'assets/js/checkout-abandonment-popup.js';
	$style_path  = WI_CHECKOUT_DIR . 'assets/css/checkout-abandonment-popup.css';

	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'wi-checkout-abandonment-popup',
			WI_CHECKOUT_URL . 'assets/js/checkout-abandonment-popup.js',
			array(),
			filemtime( $script_path ),
			true
		);
	} else {
		error_log( 'WI Checkout: asset ausente: ' . $script_path );
	}

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'wi-checkout-abandonment-popup',
			WI_CHECKOUT_URL . 'assets/css/checkout-abandonment-popup.css',
			array(),
			filemtime( $style_path )
		);
	} else {
		error_log( 'WI Checkout: asset ausente: ' . $style_path );
	}
} );

/**
 * Imprime o markup do popup (escondido por padrão via CSS, display: none)
 * no rodapé da página de checkout. Fica deliberadamente isolado de qualquer
 * componente de popup do Woodmart — este projeto já tem um histórico
 * documentado de incompatibilidade entre o Combine CSS/JS do LiteSpeed e o
 * Woodmart, então este markup/CSS não reaproveita nada do tema.
 */
add_action( 'wp_footer', function () {
	$woocommerce_loaded = function_exists( 'is_checkout' ) && function_exists( 'is_order_received_page' );

	if ( ! $woocommerce_loaded || ! is_checkout() || is_order_received_page() ) {
		return;
	}
	?>
	<div id="wi-checkout-abandon-popup" class="wi-abandon-popup" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="wi-abandon-popup-title">
		<div class="wi-abandon-popup__overlay"></div>
		<div class="wi-abandon-popup__box">
			<button type="button" class="wi-abandon-popup__close" data-wi-abandon-close aria-label="Fechar">&times;</button>
			<h2 id="wi-abandon-popup-title" class="wi-abandon-popup__title">Você ainda não finalizou seu pedido</h2>
			<p class="wi-abandon-popup__body">Percebemos que você começou a preencher seus dados. Quer continuar de onde parou?</p>
			<div class="wi-abandon-popup__actions">
				<button type="button" class="wi-abandon-popup__btn wi-abandon-popup__btn--primary" data-wi-abandon-continue>Continuar preenchendo</button>
				<button type="button" class="wi-abandon-popup__btn wi-abandon-popup__btn--secondary" data-wi-abandon-exit>Sair mesmo assim</button>
			</div>
		</div>
	</div>
	<?php
} );
