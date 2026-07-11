# Scripts que não podem ser atrasados/combinados fora de ordem pelo LiteSpeed Cache

Lista de referência para configurar **LiteSpeed Cache → Otimização de Página → Configurações de JS** (campos "Excluir JS Combinado", "Excluir Adiamento de JS" e "Incluir Atraso de Execução de JS"). Levantada em 08/07/2026 checando o código-fonte dos plugins e as configurações atuais do LiteSpeed no banco. **Atualizado em 10/07/2026** para incluir o script de Device ID do Mercado Pago (ver seção abaixo).

## Estado atual (já configurado, confirmado no banco em 10/07/2026)
Já excluídos de **Combinar JS** (`litespeed.conf.optm-js_exc`):
- `jquery.min.js`, `jquery.js`
- `woocommerce_params` (dados localizados que o checkout.js do WooCommerce precisa pra funcionar)
- `order-attribution`
- `wi-checkout.js` (script próprio de reordenação dos blocos do checkout)

Já excluídos de **Adiar JS** (`litespeed.conf.optm-js_defer_exc`):
- `jquery.min.js`, `jquery.js`, `gtm.js`, `analytics.js`

Os scripts `mp-*`/SDK do Mercado Pago listados na seção abaixo **ainda não estão** em nenhuma dessas duas listas de exclusão (nem `optm-js_exc` nem `optm-js_defer_exc`) — só o script de Device ID (`session.min.js`) foi adicionado a ambas, no ambiente local de teste, como parte do hardening desta página. Replicar em produção via wp-admin continua pendente (ver seção "Device ID" abaixo).

**Atraso de Execução de JS** (`litespeed.conf.optm-js_delay`) está **desativado hoje** (`optm-js_delay_inc` vazio) — ou seja, esse recurso não está atrasando nenhum script no momento. Se for ativado no futuro, os itens abaixo (incluindo o Device ID) precisam entrar na lista de exclusão dele também.

## Scripts do Mercado Pago que precisam ser adicionados às exclusões (checkout)
Carregados só na página de checkout (`is_checkout()`), essenciais pro Pix/Cartão/Boleto funcionarem:

**Device ID (fingerprint antifraude) — já protegido no ambiente local, replicar em produção:**
- `session.min.js` (handle `wc_mercadopago_security_session`), enfileirado pelo plugin oficial `woocommerce-mercadopago`. É o script que injeta `https://www.mercadopago.com/v2/security.js` e popula `MP_DEVICE_SESSION_ID`, o Device ID que o Mercado Pago usa para reduzir recusa/fraude em toda venda (ver `.grimoire/pages/002-checkout-antifraude-mercadopago/SPEC.md`). Se o LiteSpeed combinar ou atrasar esse script, o Device ID pode parar de ser coletado silenciosamente — sem erro visível, só um sinal antifraude a menos.
- `https://www.mercadopago.com/v2/security.js` — script externo injetado pelo `session.min.js` acima; mesma justificativa, nunca deve ser combinado/adiado.
- **Confirmado em 10/07/2026** no ambiente local (`c:\xampp\htdocs\public_html`): adicionado `session.min.js` a `litespeed.conf.optm-js_exc` e a `litespeed.conf.optm-js_defer_exc` via `update_option()` (bootstrap `wp-load.php`, mesmo mecanismo usado na investigação). Após a mudança, a página de checkout local continua servindo `wc_mercadopago_security_session-js` como arquivo próprio (não combinado), sem novos erros/warnings em `wp-content/debug.log`. **Em produção, essa mesma exclusão ainda precisa ser aplicada manualmente no wp-admin** (LiteSpeed Cache → Otimização de Página → Configurações de JS) — não está versionada neste repositório.

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
