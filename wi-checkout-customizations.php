<?php
/**
 * Plugin Name: WI Checkout Customizations
 * Description: Reordena os blocos do checkout (Produtos/Informações/Pagamento), adiciona um resumo de valores por forma de pagamento, agrupa o frete em uma caixa expansível e corrige traduções pt-BR ausentes. Feito sob medida para o checkout Elementor Pro + WooCommerce da Web Import Brasil.
 * Version: 1.0.0
 * Author: Web Import Brasil
 * Text Domain: wi-checkout-customizations
 */

defined( 'ABSPATH' ) || exit;

define( 'WI_CHECKOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WI_CHECKOUT_URL', plugin_dir_url( __FILE__ ) );

require_once WI_CHECKOUT_DIR . 'includes/checkout-reorder.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-i18n.php';
require_once WI_CHECKOUT_DIR . 'includes/checkout-shortcode.php';

register_activation_hook( __FILE__, 'wi_checkout_create_template' );
