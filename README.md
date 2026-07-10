# WI Checkout Customizations

Plugin para o checkout WooCommerce + Elementor Pro da Web Import Brasil. Reordena os
blocos do checkout (Produtos / Informações / Pagamento), mostra um resumo de valores
por forma de pagamento, transforma a lista de fretes numa caixa expansível e corrige
traduções pt-BR que faltam nesta instalação.

## O que este plugin faz

- **`includes/checkout-reorder.php`** — enfileira o `assets/js/wi-checkout.js` só na
  página de checkout (nunca em `/pedido-recebido/`).
- **`assets/js/wi-checkout.js`** — três comportamentos independentes:
  1. Reordena os blocos do checkout no DOM (Produtos → Informações → Cupom → Pagamento).
  2. Cria/atualiza um resumo de valores (Valor + Frete + Taxa/Desconto = Total) abaixo
     da lista de formas de pagamento, sincronizado com o evento `updated_checkout` do
     WooCommerce.
  3. Colapsa a lista de fretes numa caixa que mostra só a opção selecionada, expandindo
     ao clicar.
- **`includes/checkout-i18n.php`** — filtro `gettext` escopado à página de checkout que
  corrige strings que não traduzem por falta do arquivo `elementor-pro-pt_BR.mo` nesta
  instalação (ou incompatibilidade de versão do `.po`/`.mo` do WooCommerce).

## Design do checkout (layout Elementor) via shortcode

O layout visual do checkout (cores, cards, grid desktop/mobile, textos das seções,
widget `woocommerce-checkout-page` do Elementor Pro) vai junto com o plugin: um export
do design fica em `templates/checkout-elementor-data.json`.

Na ativação do plugin (`register_activation_hook`), esse JSON é copiado para um
template interno do Elementor (`elementor_library`), e o shortcode **`[wi_checkout]`**
passa a renderizar esse template onde for colocado.

**Para aplicar em uma página:** edite a página que o WooCommerce usa como Checkout
(**WooCommerce → Configurações → Avançado → Páginas**) e defina o conteúdo dela como
`[wi_checkout]` — pode ser em um bloco de Shortcode do Gutenberg, no editor clássico,
ou no widget "Shortcode" do próprio Elementor.

> **Atenção:** o shortcode reproduz o layout visual em qualquer página, mas a lógica de
> checkout do WooCommerce (criar pedido, redirecionar para "pedido recebido", sessão)
> só funciona de verdade na página que o WooCommerce tem configurada como a página de
> Checkout. Colocar `[wi_checkout]` em outra página só mostra o design, sem processar
> pagamento de verdade.

Se o template interno precisar ser recriado manualmente (ex.: depois de editar o JSON
à mão), chame `wi_checkout_create_template()` uma vez (via um snippet ou WP-CLI
`wp eval`).

## Compatibilidade com o plugin "Fluid Checkout"

Se o site tiver o plugin **Fluid Checkout for WooCommerce** ativo, ele substitui a
página inteira de checkout pelo layout multi-etapas próprio dele (a tela "Passo 1 de
4"), por cima de qualquer coisa que esteja no conteúdo da página — inclusive o
`[wi_checkout]`.

Em vez de desativar o Fluid Checkout (ele também pode afetar outras páginas, como o
carrinho), **`includes/compat-fluid-checkout.php`** usa o filtro que o próprio Fluid
Checkout documenta para compatibilidade com page builders
(`fc_enable_checkout_page_template`) para desligar só a substituição da página de
checkout, mantendo o resto do plugin funcionando normalmente. Não faz nada se o Fluid
Checkout não estiver instalado.

## Instalação

1. Copie a pasta `wi-checkout-customizations` inteira para `wp-content/plugins/`.
2. Ative o plugin em **Plugins** no wp-admin.
3. Confirme que o WooCommerce e o Elementor Pro já estão ativos — este plugin só
   ajusta o comportamento do checkout, não os substitui.
4. Defina o conteúdo da página de Checkout como `[wi_checkout]` (veja acima).

## Requisitos

- WordPress + WooCommerce
- Elementor Pro com o checkout montado via widget `woocommerce-checkout-page`
- jQuery (já vem com o WordPress/WooCommerce)

## Convenções de segurança para código novo

- PHP: use `esc_html()`/`esc_attr()`/`esc_url()`/`wp_kses()` em qualquer
  saída dinâmica que não seja uma constante fixa do próprio código.
- JS: prefira `textContent`/`createElement` a `innerHTML` sempre que o
  conteúdo vier do DOM ou de qualquer fonte que não seja um literal fixo no
  próprio script.
