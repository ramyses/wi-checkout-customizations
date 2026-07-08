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

## O que este plugin **não** inclui

O layout visual do checkout (cores, cards, grid desktop/mobile, textos das seções) foi
feito inteiramente dentro do **Elementor Pro**, usando o widget nativo
`woocommerce-checkout-page`, e vive como dado de página (`_elementor_data`) no banco —
não é código de plugin. Para levar esse design para outro site:

1. Na página de checkout, abra o Elementor e use **"Salvar como Modelo"** (ou exporte o
   template via **Templates → Salvar Tudo Como Modelo**).
2. No site de destino, importe esse modelo em **Templates → Importar Modelos** e
   aplique-o na página de checkout.

## Instalação

1. Copie a pasta `wi-checkout-customizations` inteira para `wp-content/plugins/`.
2. Ative o plugin em **Plugins** no wp-admin.
3. Confirme que o WooCommerce e o Elementor Pro (com o widget de checkout configurado)
   já estão ativos — este plugin só ajusta o comportamento do checkout, não o substitui.

## Requisitos

- WordPress + WooCommerce
- Elementor Pro com o checkout montado via widget `woocommerce-checkout-page`
- jQuery (já vem com o WordPress/WooCommerce)
