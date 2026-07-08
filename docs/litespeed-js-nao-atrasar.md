# Scripts que não podem ser atrasados/combinados fora de ordem pelo LiteSpeed Cache

Lista de referência para configurar **LiteSpeed Cache → Otimização de Página → Configurações de JS** (campos "Excluir JS Combinado", "Excluir Adiamento de JS" e "Incluir Atraso de Execução de JS"). Levantada em 08/07/2026 checando o código-fonte dos plugins e as configurações atuais do LiteSpeed no banco.

## Estado atual (já configurado, confirmado no banco)
Já excluídos de **Combinar JS** (`litespeed.conf.optm-js_exc`):
- `jquery.min.js`, `jquery.js`
- `woocommerce_params` (dados localizados que o checkout.js do WooCommerce precisa pra funcionar)
- `order-attribution`

Já excluídos de **Adiar JS** (`litespeed.conf.optm-js_defer_exc`):
- `jquery.min.js`, `jquery.js`, `gtm.js`, `analytics.js`

**Atraso de Execução de JS** (`litespeed.conf.optm-js_delay_inc`) está **vazio hoje** — ou seja, esse recurso não está atrasando nenhum script no momento. Se for ativado no futuro, os itens abaixo precisam entrar na lista de exclusão dele também.

## Scripts do Mercado Pago que precisam ser adicionados às exclusões (checkout)
Carregados só na página de checkout (`is_checkout()`), essenciais pro Pix/Cartão/Boleto funcionarem:

**Comuns a todos os métodos:**
- `mp-checkout-error-dispatcher.js`
- `mp-checkout-fields-dispatcher.js`
- `mp-checkout-session-data-register.js`
- `mp-plugins-components.js`
- `mp-checkout-update.js`
- `mp-checkout-metrics.js`

**Cartão de Crédito:**
- SDK oficial: `sdk.mercadopago.com/js/v2` (script externo, domínio da Mercado Pago — nunca deve ser combinado/adiado)
- `card-form.js`
- `mp-custom-elements.js`
- `mp-custom-checkout.js`
- `mp-custom-page.js`

**Boleto:**
- `mp-ticket-page.js`, `mp-ticket-elements.js`, `mp-ticket-checkout.js`

**Pix:** usa só o SDK acima (o polling de confirmação do Pix é na página de "pedido recebido", não no checkout).

## Script próprio adicionado por nós
- O script que reordena os 3 blocos (Produtos/Informações/Pagamento) — vem do mu-plugin `wp-content/mu-plugins/wi-checkout-reorder.php`, injetado inline via `wp_footer`. Hoje ele é pego pelo "Combinar JS Externo+Inline" (`js_comb_ext_inl=1`) e funciona normal dentro do bundle, mas se o "Atraso de Execução" for ativado no futuro, esse também precisa ser excluído (ele precisa rodar assim que a página carrega, não só após alguma interação do usuário).

## Por que isso importa
O SDK do Mercado Pago e os scripts `mp-checkout-*`/`mp-custom-*` precisam carregar e executar **na ordem certa e sem atraso** pra desenhar o QR Code do Pix, o formulário de cartão e o boleto corretamente. Se o LiteSpeed atrasar ou combinar esses arquivos fora de ordem, o card de pagamento pode ficar em branco ou quebrado — foi exatamente esse tipo de problema que já vimos antes nesse checkout (o script de reordenação sumindo da página por causa da combinação de JS).
