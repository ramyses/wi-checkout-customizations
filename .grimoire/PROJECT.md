# WI Checkout Customizations

## Purpose
Plugin WordPress sob medida que ajusta o checkout WooCommerce + Elementor Pro da
Web Import Brasil sem substituir nenhum desses sistemas: reordena os blocos do
checkout (Produtos → Informações → Cupom → Pagamento), adiciona um resumo de
valores por forma de pagamento, colapsa a lista de fretes numa caixa expansível,
corrige traduções pt-BR que faltam nesta instalação específica, torna o layout
visual do checkout (montado no Elementor Pro) portável via shortcode `[wi_checkout]`,
adiciona thumbnail de produto na tabela de revisão do pedido, e mantém
compatibilidade com o plugin "Fluid Checkout for WooCommerce" (que por padrão
substitui a página inteira de checkout pelo seu próprio layout multi-etapas).

## Audience
Uso exclusivo do site de produção da Web Import Brasil. Não é pensado para reuso
genérico em outras instalações/clientes — as correções (traduções, compat com
Fluid Checkout, etc.) são amarradas a peculiaridades desta instalação específica
(versões de `.po`/`.mo`, plugins ativos, layout Elementor específico).

## Tech Stack
- **PHP** (plugin WordPress padrão, sem build step, sem Composer/dependências
  externas declaradas) — ponto de entrada `wi-checkout-customizations.php`.
- **JavaScript (jQuery)** — `assets/js/wi-checkout.js`, enfileirado apenas na
  página de checkout (nunca em `/pedido-recebido/`), especificamente como arquivo
  externo (não inline) para poder ser excluído do "Combinar JS" do LiteSpeed Cache.
- **CSS** — `assets/css/wi-checkout-thumb.css` (estilo da thumbnail no checkout).
- Depende em runtime de **WordPress + WooCommerce + Elementor Pro** (widget
  `woocommerce-checkout-page`) já ativos e configurados; opcionalmente reage à
  presença do plugin **Fluid Checkout for WooCommerce**.
- Sem testes automatizados, sem CI/CD configurado.

## Repository Layout
- `wi-checkout-customizations.php` — bootstrap do plugin; define constantes
  `WI_CHECKOUT_DIR`/`WI_CHECKOUT_URL`, dá `require_once` em cada módulo de
  `includes/`, registra o hook de ativação que cria o template Elementor.
- `includes/checkout-reorder.php` — enfileira JS/CSS só na página de checkout.
- `includes/checkout-i18n.php` — filtro `gettext` escopado ao checkout, corrige
  strings pt-BR que não traduzem nesta instalação.
- `includes/checkout-shortcode.php` — cria/atualiza o template interno do
  Elementor (`elementor_library`) a partir do JSON bundled e expõe o shortcode
  `[wi_checkout]`.
- `includes/checkout-thumbnail.php` — adiciona thumbnail do produto à tabela de
  revisão do pedido no checkout (inclusive no recálculo AJAX de frete/endereço).
- `includes/compat-fluid-checkout.php` — desliga a substituição de template do
  Fluid Checkout apenas na página de checkout (filtro documentado +
  fallback via `template_include`), sem desativar o plugin.
- `assets/js/wi-checkout.js` — reordenação de blocos no DOM, resumo de valores,
  colapso da caixa de fretes.
- `assets/css/wi-checkout-thumb.css` — estilo da thumbnail de produto.
- `templates/checkout-elementor-data.json` (+ `-meta.json`, `-page-settings.json`)
  — export do design do checkout no Elementor Pro, copiado para um template
  interno na ativação do plugin.
- `docs/litespeed-js-nao-atrasar.md` — referência de configuração do LiteSpeed
  Cache (quais scripts do checkout — SDK Mercado Pago, script de reordenação —
  não podem ser combinados/adiados sem quebrar o checkout).

## Key Conventions / Constraints
- Cada correção é amarrada a uma causa raiz específica desta instalação (ex.:
  `elementor-pro-pt_BR.mo` ausente, LiteSpeed combinando JS fora de ordem,
  Fluid Checkout sobrescrevendo o template) — os comentários no topo de cada
  arquivo em `includes/` documentam o porquê, não repita isso ao editar.
- Hooks que rodam em toda página (`gettext`, `template_include`,
  `woocommerce_cart_item_name`) são sempre guardados por `is_checkout()` (ou,
  quando a checagem via `is_checkout()` não é confiável — ex. dentro do próprio
  filtro `gettext` ou durante o AJAX `update_order_review` — por um gate
  equivalente) para não vazar efeito para outras páginas.
- `[wi_checkout]` reproduz apenas o layout visual; a lógica real de checkout do
  WooCommerce só roda na página que o WooCommerce tem configurada como
  "Checkout" em WooCommerce → Configurações → Avançado → Páginas.
- Não desativar o Fluid Checkout como estratégia de compatibilidade — ele
  permanece ativo em produção (usado em outras páginas, como o carrinho); a
  compatibilidade é feita via filtro seletivo, escopado ao checkout.

## Current Status
Em produção ativa no site da Web Import Brasil. Evolui via fixes pontuais de
compatibilidade conforme problemas aparecem (ex.: a série de commits recentes
resolvendo conflitos com o Fluid Checkout). Deploy é manual: a pasta do plugin é
copiada/enviada diretamente para `wp-content/plugins/` no servidor de produção
(sem pipeline automático); o repositório GitHub
(`ramyses/wi-checkout-customizations`) é usado para versionamento, não para
deploy automatizado.

## Notes
