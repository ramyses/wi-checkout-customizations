<?php
/**
 * Plugin Name: WI Checkout Customizations
 * Description: Reordena os blocos do checkout (Produtos/Informações/Pagamento), adiciona um resumo de valores por forma de pagamento, agrupa o frete em uma caixa expansível e corrige traduções pt-BR ausentes. Feito sob medida para o checkout Elementor Pro + WooCommerce da Web Import Brasil.
 * Version: 1.10.0
 * Author: Web Import Brasil
 * Text Domain: wi-checkout-customizations
 * Requires Plugins: woocommerce, elementor-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'WI_CHECKOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WI_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );

require_once WI_CHECKOUT_DIR . 'includes/checkout-reorder.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-google-login.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-i18n.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-shortcode.php';
require_once WI_CHECKOUT_DIR . 'includes/compat-fluid-checkout.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-thumbnail.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-mercadopago-fixes.php';
// Reativado 2026-07-29 (Page 030-adjacent quick fix): conta criada
// automaticamente em segundo plano, sem tornar login/conta obrigatorio —
// guest checkout continua livre (woocommerce_enable_guest_checkout=yes,
// intocado). Ver checkout-account-auto-creation.php pra logica completa.
require_once WI_CHECKOUT_DIR . 'includes/checkout-account-creation-notice.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-account-auto-creation.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-boleto-download-link.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-abandonment-popup.php';
// Em revisao (2026-08-17): mensagem de link de parcelamento sob o Cartao de
// Credito. Renderiza SO na pagina interna de pre-visualizacao; o checkout real
// nao muda ate a aprovacao. Ver o cabecalho do arquivo pro porque do gancho.
require_once WI_CHECKOUT_DIR . 'includes/checkout-parcelamento-alternativo.php';

register_activation_hook( __FILE__, 'wi_checkout_create_template' );
