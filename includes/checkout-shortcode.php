<?php
/**
 * Exposes the checkout layout (the Elementor Pro `woocommerce-checkout-page` widget
 * plus all of its custom CSS) as a portable shortcode, `[wi_checkout]`. The design
 * itself ships bundled inside this plugin as templates/checkout-elementor-data.json
 * (an export of the widget tree) and gets copied, on activation, into an internal
 * Elementor Library template post — Elementor then renders that template on demand
 * wherever the shortcode is placed.
 *
 * This makes the page assigned as WooCommerce's checkout page (WooCommerce ->
 * Settings -> Advanced -> Page setup) portable across installs: instead of manually
 * rebuilding the widget tree in the Elementor editor, set that page's content to
 * just `[wi_checkout]` and activate this plugin.
 *
 * Note: placing this shortcode on a page OTHER than the WooCommerce checkout page
 * will render the same visual layout, but WooCommerce's own checkout logic
 * (order creation, redirects, session handling) only runs on the page WooCommerce
 * is configured to treat as "Checkout" — the shortcode does not change that.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates (or re-creates) the internal Elementor Library template from the bundled
 * JSON export and stores its post ID in an option for the shortcode to reuse.
 *
 * @return int Template post ID, or 0 on failure.
 */
function wi_checkout_create_template() {
	$json_path = WI_CHECKOUT_DIR . 'templates/checkout-elementor-data.json';

	if ( ! file_exists( $json_path ) ) {
		error_log( 'WI Checkout: JSON do template ausente: ' . $json_path );
		return 0;
	}

	$json_data = file_get_contents( $json_path );

	if ( false === $json_data ) {
		error_log( 'WI Checkout: falha ao ler o JSON do template: ' . $json_path );
		return 0;
	}

	$existing_id = (int) get_option( 'wi_checkout_template_post_id' );
	$post_id     = $existing_id && get_post( $existing_id ) ? $existing_id : 0;

	if ( ! $post_id ) {
		$post_id = wp_insert_post( array(
			'post_title'  => 'WI Checkout Layout',
			'post_type'   => 'elementor_library',
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			error_log( 'WI Checkout: falha ao criar o post do template: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'erro desconhecido' ) );
			return 0;
		}

		wp_set_object_terms( $post_id, 'page', 'elementor_library_type' );
		update_option( 'wi_checkout_template_post_id', $post_id );
	}

	update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );
	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_template_type', 'page' );

	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}

	return $post_id;
}

add_shortcode( 'wi_checkout', function () {
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
		return '';
	}

	$template_id = (int) get_option( 'wi_checkout_template_post_id' );

	if ( ! $template_id || ! get_post( $template_id ) ) {
		return '';
	}

	return \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id );
} );

/**
 * Admin notices covering the two ways the shortcode can silently render nothing:
 * the internal template is missing, or only Elementor free (not Pro) is active.
 * Both checks are scoped to `manage_options` so regular customers never see them.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$template_id = (int) get_option( 'wi_checkout_template_post_id' );

	if ( ! $template_id || ! get_post( $template_id ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'WI Checkout Customizations: o template interno do checkout não foi encontrado. Desative e reative o plugin para recriá-lo.', 'wi-checkout-customizations' ) .
			'</p></div>';
	}

	if ( did_action( 'elementor/loaded' ) && ! class_exists( '\ElementorPro\Plugin' ) ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'WI Checkout Customizations: este plugin exige o Elementor Pro ativo (o layout do checkout usa o widget woocommerce-checkout-page, exclusivo do Pro).', 'wi-checkout-customizations' ) .
			'</p></div>';
	}
} );
