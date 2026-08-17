<?php
/**
 * Offers an alternative-instalment ("link de parcelamento direto com o banco")
 * message under the Mercado Pago credit-card option, plus a variant for the
 * "payment not approved" screen.
 *
 * WHY THIS EXISTS — and what it is NOT: card approval on this store's Mercado
 * Pago account sits around 41%, and the cause is the account's own risk
 * classification, not anything on the site (measured: ticket size drives the
 * rejection, not identity or account history). So this is **mitigation, not a
 * fix** — it catches the customer whose card is about to fail, or already
 * failed, instead of losing the sale. It does not raise the approval rate by
 * a single point.
 *
 * WHILE UNDER REVIEW this renders ONLY on the internal preview page (see
 * WI_PARCEL_PREVIEW_SLUG). The live checkout is deliberately untouched: the
 * gate is checked before any output, so `/finalizar-compra/` renders
 * byte-for-byte as it did before this file existed. Flipping it on later means
 * relaxing `wi_parcel_should_render()` — nothing else.
 *
 * WHICH HOOK, AND WHY NOT THE OBVIOUS ONE: the natural choice,
 * `woocommerce_gateway_description`, does not work here. Mercado Pago's
 * CustomGateway overrides `payment_fields()` to render its own template and
 * never calls `get_description()`, so WooCommerce only uses the filtered
 * description as a boolean ("should I draw the payment_box at all?") and
 * throws the string away. Measured on production 2026-08-17: the filter did
 * fire (description 64 -> 86 chars) yet the marker was absent from all 11.133
 * bytes of `payment_fields()` output. What does fire, inside the payment box
 * and immediately after the card form, is `woocommerce_after_template_part`
 * with $template_name === '/public/checkouts/custom-checkout.php' — Mercado
 * Pago renders through `wc_get_template()`, which emits that action. Matching
 * on that template name also keeps the block off every other gateway: Pix is
 * a separate plugin with a separate template, so it cannot pick this up.
 *
 * WHY THE PREVIEW PAGE CANNOT TAKE MONEY: `[wi_checkout]` renders the real
 * Elementor checkout widget, and the real checkout form posts to
 * `wc-ajax=checkout`, which processes payment regardless of which page the
 * form was drawn on. A page-based guard is useless there — during that AJAX
 * request `is_page()` is false. So the block keys off two request-scoped
 * signals that can never be present on a genuine purchase: a hidden field
 * planted in the preview page's form, and the referring page's path.
 *
 * @package wi-checkout-customizations
 */

defined( 'ABSPATH' ) || exit;

/** Slug of the internal preview page. Chosen so `$request_uri` contains
 * `/checkout/`, which is what the nginx FastCGI bypass rule matches — see
 * wi_parcel_preview_page_id() for the full reasoning. */
define( 'WI_PARCEL_PREVIEW_SLUG', 'checkout' );

/** Option holding the preview page ID, so a slug rename cannot silently
 * detach the guards from the page they protect. */
define( 'WI_PARCEL_PREVIEW_OPTION', 'wi_parcel_preview_page_id' );

/** Mercado Pago's card-form template, as reported by `wc_get_template()`. */
define( 'WI_PARCEL_MP_TEMPLATE', '/public/checkouts/custom-checkout.php' );

/** Name of the hidden field that marks a submission as coming from the preview. */
define( 'WI_PARCEL_PREVIEW_FIELD', 'wi_parcel_preview_submission' );

define( 'WI_PARCEL_EMAIL', 'vendas@webimportbrasil.com.br' );

/**
 * Resolves the preview page ID: stored option first, slug lookup as fallback.
 *
 * @return int Page ID, or 0 when the preview page does not exist.
 */
function wi_parcel_preview_page_id(): int {
	static $cached = null;

	if ( null !== $cached ) {
		return $cached;
	}

	$id = (int) get_option( WI_PARCEL_PREVIEW_OPTION );

	if ( $id && get_post( $id ) ) {
		$cached = $id;
		return $cached;
	}

	$page   = get_page_by_path( WI_PARCEL_PREVIEW_SLUG );
	$cached = $page ? (int) $page->ID : 0;

	return $cached;
}

/**
 * True while rendering the preview page itself (not during checkout AJAX).
 */
function wi_parcel_is_preview_page(): bool {
	$id = wi_parcel_preview_page_id();

	return $id && is_page( $id );
}

/**
 * Gate for rendering the block. Kept as one function so switching the feature
 * on for the live checkout later is a single, reviewable edit.
 */
function wi_parcel_should_render(): bool {
	return wi_parcel_is_preview_page();
}

/**
 * True when the current request is a checkout submission that originated from
 * the preview page. Deliberately narrow: both signals are request-scoped and
 * neither can appear on a genuine purchase made from `/finalizar-compra/`, so
 * this can never block a real sale.
 */
function wi_parcel_is_preview_submission(): bool {
	// Primary signal: the hidden field planted in the preview page's form.
	// Presence alone is what matters; the value is never trusted or echoed.
	if ( isset( $_POST[ WI_PARCEL_PREVIEW_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return true;
	}

	// Secondary signal: the AJAX request's referring page. Same-origin requests
	// send the full path under every mainstream Referrer-Policy, and a real
	// customer's referer is the live checkout, never the preview slug.
	if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
		return false;
	}

	$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
	$path    = (string) wp_parse_url( $referer, PHP_URL_PATH );

	return '' !== $path && trim( $path, '/' ) === WI_PARCEL_PREVIEW_SLUG;
}

/* -------------------------------------------------------------------------
 * 1. Nothing on the preview page may ever charge anyone.
 * ---------------------------------------------------------------------- */

/**
 * Plants the hidden marker field inside the checkout form on the preview page.
 *
 * Hooked to three different points that all sit inside `<form name="checkout">`
 * because the exact template structure depends on the Elementor Pro checkout
 * widget; a static guard keeps it to one emission per request. Whichever fires
 * first wins, and if the theme ever drops one hook the others still cover it.
 */
function wi_parcel_render_preview_marker(): void {
	static $done = false;

	if ( $done || ! wi_parcel_is_preview_page() ) {
		return;
	}

	$done = true;

	echo '<input type="hidden" name="' . esc_attr( WI_PARCEL_PREVIEW_FIELD ) . '" value="1" />';
}
add_action( 'woocommerce_after_checkout_billing_form', 'wi_parcel_render_preview_marker', 5 );
add_action( 'woocommerce_checkout_before_order_review', 'wi_parcel_render_preview_marker', 5 );
add_action( 'woocommerce_review_order_before_submit', 'wi_parcel_render_preview_marker', 5 );

/**
 * Aborts any checkout submission coming from the preview page.
 *
 * `woocommerce_checkout_process` runs at class-wc-checkout.php:1378, before the
 * `0 === wc_notice_count( 'error' )` gate at line 1409 that guards order
 * creation and payment. Adding an error notice here means no order is created
 * and no gateway is ever called.
 */
function wi_parcel_block_preview_submission(): void {
	if ( ! wi_parcel_is_preview_submission() ) {
		return;
	}

	wc_add_notice(
		__( 'Esta é a página interna de pré-visualização do checkout. Nenhum pedido pode ser finalizado por aqui e ninguém é cobrado. Para comprar de verdade, use a página de finalização de compra da loja.', 'wi-checkout-customizations' ),
		'error'
	);
}
add_action( 'woocommerce_checkout_process', 'wi_parcel_block_preview_submission' );

/* -------------------------------------------------------------------------
 * 2. The preview page must never be cached or indexed.
 * ---------------------------------------------------------------------- */

/**
 * Keeps the preview page out of every page cache and out of search results.
 *
 * The nginx FastCGI cache is handled by the URL, not from here: that config
 * sets `fastcgi_ignore_headers Cache-Control Expires Set-Cookie`, so no header
 * PHP sends can opt a page out. Its bypass rule matches `$request_uri` against
 * `/(carrinho|finalizar-compra|minha-conta|cart|checkout|my-account)/`, which
 * needs a slash on BOTH sides — `/checkout-preview/` would NOT have matched and
 * would have been cached and shared between visitors, exactly the leak that hit
 * `/finalizar-compra/` on 12/08/2026. Hence the slug `checkout`: `/checkout/`
 * matches the rule literally. DONOTCACHEPAGE below covers WP Rocket, which has
 * no such rule for this page (it only auto-excludes WooCommerce's own pages).
 */
function wi_parcel_protect_preview_page(): void {
	if ( ! wi_parcel_is_preview_page() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	nocache_headers();
}
add_action( 'template_redirect', 'wi_parcel_protect_preview_page', 1 );

/**
 * noindex/nofollow for the preview page. Set through both the core filter and
 * Yoast's, since Yoast owns the robots tag on this install and would otherwise
 * overwrite the core value. The page also carries
 * `_yoast_wpseo_meta-robots-noindex`, which is what keeps it out of the sitemap.
 */
function wi_parcel_noindex_preview( $robots ) {
	if ( ! wi_parcel_is_preview_page() ) {
		return $robots;
	}

	if ( is_array( $robots ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );

		return $robots;
	}

	return 'noindex, nofollow';
}
add_filter( 'wp_robots', 'wi_parcel_noindex_preview', 99 );
add_filter( 'wpseo_robots', 'wi_parcel_noindex_preview', 99 );

/**
 * Keeps the block's own classes out of WP Rocket's unused-CSS removal.
 *
 * Additive only — this can preserve extra CSS, never strip any. Page 036 lost
 * the "Comprar agora" button for days because RUCSS dropped a button rule, so
 * the block's styles are safelisted before they can ever reach a cached page.
 */
function wi_parcel_rucss_safelist( $safelist ) {
	if ( ! is_array( $safelist ) ) {
		return $safelist;
	}

	return array_merge(
		$safelist,
		array(
			'.wi-parcel',
			'.wi-parcel__title',
			'.wi-parcel__text',
			'.wi-parcel__button',
			'.wi-parcel__fallback',
			'.wi-parcel__email',
			'.wi-parcel-preview',
			'.wi-parcel-preview__label',
		)
	);
}
add_filter( 'rocket_rucss_safelist', 'wi_parcel_rucss_safelist' );

/* -------------------------------------------------------------------------
 * 3. The block itself.
 * ---------------------------------------------------------------------- */

/**
 * Builds the `mailto:` link.
 *
 * Both subject and body go through `rawurlencode()`: without it the accented
 * words and the line breaks break the link — mail clients stop reading at the
 * first raw newline, so the body arrives truncated or empty.
 *
 * @return string Escaped href.
 */
function wi_parcel_mailto_href(): string {
	$subject = __( 'Solicitação de link de parcelamento', 'wi-checkout-customizations' );

	$lines = array(
		__( 'Olá! Gostaria de receber um link de parcelamento para concluir minha compra.', 'wi-checkout-customizations' ),
		'',
	);

	$total = wi_parcel_cart_total_text();

	if ( '' !== $total ) {
		/* translators: %s: cart total, already formatted as currency. */
		$lines[] = sprintf( __( 'Valor do meu carrinho: %s', 'wi-checkout-customizations' ), $total );
		$lines[] = '';
	}

	$lines[] = __( 'Meu nome:', 'wi-checkout-customizations' );
	$lines[] = __( 'Meu telefone / WhatsApp:', 'wi-checkout-customizations' );

	$href = 'mailto:' . WI_PARCEL_EMAIL
		. '?subject=' . rawurlencode( $subject )
		. '&body=' . rawurlencode( implode( "\r\n", $lines ) );

	return esc_url( $href );
}

/**
 * Cart total as plain text ("R$ 1.234,56"), or '' when there is no cart.
 *
 * `WC()->cart->get_total()` returns markup with a non-breaking space entity;
 * both the tags and the entity have to go, or the mail body shows raw HTML.
 */
function wi_parcel_cart_total_text(): string {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}

	$total = html_entity_decode( wp_strip_all_tags( WC()->cart->get_total() ), ENT_QUOTES, 'UTF-8' );
	$total = trim( str_replace( "\xc2\xa0", ' ', $total ) );

	return $total;
}

/**
 * Inline stylesheet, emitted once per request alongside the first block.
 *
 * Inline rather than an enqueued file so the block cannot render unstyled if a
 * JS/CSS combiner reorders assets, and prefixed `wi-parcel-` throughout so it
 * cannot collide with Woodmart or the Elementor checkout widget.
 */
function wi_parcel_styles(): string {
	static $done = false;

	if ( $done ) {
		return '';
	}

	$done = true;

	return '<style id="wi-parcel-css">
.wi-parcel{border:1px solid #d6d9e0;border-left:4px solid #0f6fd1;border-radius:6px;background:#f6f9fd;padding:16px 18px;margin:14px 0;font-size:14px;line-height:1.5;color:#22262e}
.wi-parcel__title{font-weight:700;font-size:15px;margin:0 0 6px;color:#12161d}
.wi-parcel__text{margin:0 0 12px}
.wi-parcel__button{display:inline-block;background:#0f6fd1;color:#fff !important;text-decoration:none !important;font-weight:600;padding:11px 20px;border-radius:5px;border:0;cursor:pointer;font-size:14px;line-height:1.2}
.wi-parcel__button:hover,.wi-parcel__button:focus{background:#0b559f;color:#fff !important}
.wi-parcel__fallback{margin:10px 0 0;font-size:13px;color:#4a515c}
.wi-parcel__email{font-weight:600;color:#0f6fd1;white-space:nowrap}
.wi-parcel-preview{border:2px dashed #b8863b;background:#fffaf0;border-radius:6px;padding:12px 14px;margin:14px 0}
.wi-parcel-preview__label{display:block;font-weight:700;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#8a6224;margin:0 0 8px}
</style>';
}

/**
 * Renders one variant of the block.
 *
 * @param string $variant 'payment' under the card form, 'declined' for the
 *                        payment-not-approved screen.
 * @return string
 */
function wi_parcel_render_block( string $variant = 'payment' ): string {
	$is_declined = ( 'declined' === $variant );

	$title = $is_declined
		? __( 'Seu pagamento não foi aprovado — ainda dá para concluir.', 'wi-checkout-customizations' )
		: __( 'Precisa de mais prazo ou o cartão não passou?', 'wi-checkout-customizations' );

	$text = __( 'Enviamos um link de parcelamento direto com o banco, com mais opções de parcelas. É só pedir.', 'wi-checkout-customizations' );

	$html  = wi_parcel_styles();
	$html .= '<div class="wi-parcel wi-parcel--' . esc_attr( $variant ) . '">';
	$html .= '<p class="wi-parcel__title">' . esc_html( $title ) . '</p>';
	$html .= '<p class="wi-parcel__text">' . esc_html( $text ) . '</p>';

	if ( $is_declined ) {
		// Chatwoot only here. On the payment form it stays off on purpose — the
		// widget has a history of interfering with the Mercado Pago card fields,
		// which is why wi-chatwoot-widget bails out on the checkout. By this
		// screen the payment has already failed, so that risk is gone.
		// Single-quoted on purpose: in a double-quoted PHP string `$chatwoot`
		// would be read as a variable and interpolated away to nothing.
		$html .= '<button type="button" class="wi-parcel__button" onclick="' .
			esc_attr( 'if(window.$chatwoot){window.$chatwoot.toggle(\'open\');}' ) .
			'">' . esc_html__( 'Falar com a gente agora', 'wi-checkout-customizations' ) . '</button>';
	} else {
		$html .= '<a class="wi-parcel__button" href="' . wi_parcel_mailto_href() . '">' .
			esc_html__( 'Solicitar link de parcelamento', 'wi-checkout-customizations' ) . '</a>';
	}

	// The address in plain text, not only behind the button: on desktop, a
	// visitor with no mail client configured clicks a `mailto:` and simply
	// nothing happens, with no error — around 20% of this store's traffic.
	$html .= '<p class="wi-parcel__fallback">' .
		esc_html__( 'ou escreva para', 'wi-checkout-customizations' ) . ' ' .
		'<span class="wi-parcel__email">' . esc_html( WI_PARCEL_EMAIL ) . '</span></p>';

	$html .= '</div>';

	return $html;
}

/**
 * Prints the block right after Mercado Pago's card form, inside the payment box.
 *
 * @param string $template_name Template being rendered, as passed by `wc_get_template()`.
 */
function wi_parcel_after_mp_card_form( $template_name ): void {
	if ( WI_PARCEL_MP_TEMPLATE !== $template_name ) {
		return;
	}

	if ( ! wi_parcel_should_render() ) {
		return;
	}

	echo wi_parcel_preview_showcase(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
}
add_action( 'woocommerce_after_template_part', 'wi_parcel_after_mp_card_form', 10, 1 );

/**
 * Both variants side by side, each labelled, for review on the preview page.
 *
 * Once approved and switched on for the live checkout, only the 'payment'
 * variant belongs under the card form; the labelled showcase is preview-only.
 */
function wi_parcel_preview_showcase(): string {
	$html  = '<div class="wi-parcel-preview">';
	$html .= '<span class="wi-parcel-preview__label">' .
		esc_html__( 'Variante 1 — no checkout, sob Cartão de Crédito', 'wi-checkout-customizations' ) . '</span>';
	$html .= wi_parcel_render_block( 'payment' );
	$html .= '</div>';

	$html .= '<div class="wi-parcel-preview">';
	$html .= '<span class="wi-parcel-preview__label">' .
		esc_html__( 'Variante 2 — página de pedido não aprovado', 'wi-checkout-customizations' ) . '</span>';
	$html .= wi_parcel_render_block( 'declined' );
	$html .= '</div>';

	return $html;
}
