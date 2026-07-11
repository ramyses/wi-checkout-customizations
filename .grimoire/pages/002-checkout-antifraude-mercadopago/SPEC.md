# 002 — Checkout alinhado às exigências antifraude do Mercado Pago

## Context
A loja vende categorias de produto com histórico de alerta de fraude nos bancos
(eletrônicos de alto valor — iPhone, PS5, drones —, e especialmente TV Box/IPTV,
categoria associada a fraude com cartão no Brasil). O pedido é revisar os campos
do checkout para que os dados enviados ao Mercado Pago (nosso adquirente/banco)
atendam ao que a documentação deles pede para reduzir recusa/fraude, já que
recusas incorretas e chargebacks pioram o histórico da conta junto aos bancos.

Investigação feita antes deste SPEC (via docs oficiais do Mercado Pago —
WooCommerce e Checkout API — e inspeção direta do código instalado localmente):

- **Device ID (fingerprint) já funciona.** O plugin oficial
  `woocommerce-mercadopago` já enfileira `wc_mercadopago_security_session`
  (`session.min.js`), que injeta `https://www.mercadopago.com/v2/security.js` e
  popula `MP_DEVICE_SESSION_ID` automaticamente. Confirmado presente e
  carregando no ambiente local de teste. **Não precisa de trabalho aqui.**
- **Endereço de entrega = cobrança já é forçado** (nosso próprio CSS já esconde
  "entregar em endereço diferente"), o que já ajuda a evitar divergência de
  endereço (um fator de recusa).
- **`date_of_birth` não existe na API de pagamento do Mercado Pago** (confirmado
  na referência oficial: `payer`/`additional_info.payer` só têm `first_name`,
  `last_name`, `email`, `identification`, `phone`, `address`) — descartado,
  não faz parte deste SPEC.
- **Gap real #1 — script de Device ID sem proteção no LiteSpeed.**
  `docs/litespeed-js-nao-atrasar.md` lista os scripts do Mercado Pago que
  precisam ficar fora de "Combinar JS"/"Atraso de Execução" no LiteSpeed Cache,
  mas **não inclui `session.min.js`** (o script que gera o Device ID). Se
  "Atraso de Execução de JS" for ativado no LiteSpeed no futuro (hoje está
  vazio/inativo, mas é uma configuração que pode mudar), o Device ID pode parar
  de ser coletado silenciosamente — sem erro visível, só um sinal antifraude a
  menos em toda venda.
- **Gap real #2 — campo de Nome da Empresa some no mobile para Pessoa
  Jurídica.** O plugin `woocommerce-extra-checkout-fields-for-brazil` alterna
  os campos de PF/PJ via jQuery `.show()`/`.hide()` (estilo inline, sem
  `!important`) no `#billing_persontype`. Nosso `custom_css` do template
  Elementor (dentro de `templates/checkout-elementor-data.json`, bloco
  `@media (max-width: 768.98px)`) esconde `#billing_company_field` com
  `display:none !important` — que **vence** o `.show()` inline do plugin.
  Resultado: no mobile, um comprador que seleciona "Pessoa Jurídica" consegue
  preencher o CNPJ (não afetado pela nossa regra), mas **não consegue** informar
  o Nome da Empresa, porque o campo continua escondido. No desktop essa regra
  não existe (está só dentro do media query mobile), então lá não há o
  problema. Isso reduz a completude dos dados de uma compra PJ — exatamente o
  tipo de dado incompleto que a doc do Mercado Pago pede pra evitar.
- **Recomendação geral da doc do Mercado Pago:** enviar "o máximo de dados
  possível" sobre comprador e produto — isso é preenchido automaticamente pelo
  plugin oficial a partir dos dados do pedido/cliente do WooCommerce, então a
  parte que está no nosso controle é garantir que o formulário de checkout
  colete esses dados de forma completa (sem campos essenciais faltando ou
  escondidos por engano) e validada (formato correto).

## Goals
- Adicionar `session.min.js`/`wc_mercadopago_security_session` (e o próprio
  `security.js` que ele carrega) à lista de scripts protegidos em
  `docs/litespeed-js-nao-atrasar.md`, e confirmar/ajustar a configuração real
  do LiteSpeed Cache no site (exclusão de "Combinar JS Externo" e de "Atraso de
  Execução de JS") para que esse script nunca seja combinado ou atrasado.
- Corrigir a regra CSS em `templates/checkout-elementor-data.json` que esconde
  `#billing_company_field` no mobile com `!important`, para que o campo de Nome
  da Empresa apareça corretamente quando "Pessoa Jurídica" for selecionado,
  tanto no mobile quanto no desktop.
- Revisar e confirmar que todos os campos de PF (Nome, Sobrenome, CPF, Celular,
  Endereço completo, E-mail) e de PJ (+ Nome da Empresa, CNPJ) estão marcados
  como obrigatórios (`required`) no formulário e com validação de formato
  (CPF/CNPJ com dígito verificador, telefone com DDD, CEP no formato correto) —
  usando o que o `woocommerce-extra-checkout-fields-for-brazil` já oferece,
  confirmando que nada no nosso plugin ou CSS desativa essa validação.
- Testar de ponta a ponta o fluxo de Pessoa Jurídica no checkout (desktop e
  mobile) no ambiente local, confirmando que CNPJ + Nome da Empresa chegam
  completos até a finalização do pedido.
- Documentar, num lugar visível (README ou `docs/`), que o Device ID já está
  coberto e não precisa ser implementado — para que ninguém tente reimplementar
  isso no futuro achando que está faltando.

## Non-goals
- Não mexer na lógica de criação de pagamento do Mercado Pago em si
  (`additional_info`, `payer`, etc.) — isso é código do plugin oficial
  `woocommerce-mercadopago`, não deste plugin. Nosso escopo é garantir que os
  dados que chegam até esse plugin (via campos do checkout) estejam completos.
- Não adicionar campo de data de nascimento (confirmado que o Mercado Pago não
  usa isso na API de pagamento).
- Não habilitar/configurar o produto "Antifraude Plus" do Mercado Pago — é uma
  configuração de conta/contrato comercial, fora do escopo de código. Fica só
  como recomendação nas Referências.
- Não alterar o layout visual do checkout além do necessário para mostrar o
  campo de Nome da Empresa corretamente (sem redesenhar cards/cores).
- Não mexer em `includes/checkout-i18n.php` nem `includes/compat-fluid-checkout.php`.

## Scope
- `docs/litespeed-js-nao-atrasar.md` — adicionar `session.min.js`/Device ID à
  lista de scripts protegidos.
- Configuração do LiteSpeed Cache no site (via wp-admin, não é arquivo do
  plugin) — confirmar exclusões de Combinar JS/Atraso de Execução para o script
  de Device ID.
- `templates/checkout-elementor-data.json` — ajustar a regra `custom_css` do
  widget `woocommerce-checkout-page` (bloco mobile) que esconde
  `#billing_company_field`.
- Nenhuma mudança esperada em `includes/*.php` ou `assets/js/wi-checkout.js`,
  a não ser que a investigação do Step de execução encontre algo adicional
  relacionado a validação de campos que dependa desses arquivos.

## Acceptance criteria
- `docs/litespeed-js-nao-atrasar.md` lista explicitamente o script de Device ID
  do Mercado Pago (`session.min.js`) entre os que não podem ser combinados/
  atrasados, com a mesma justificativa dos outros scripts mp-*.
- No wp-admin, a configuração do LiteSpeed Cache confirma esse script fora de
  "Combinar JS Externo" e de "Atraso de Execução de JS" (se este último
  continuar vazio/inativo, documentar que precisa ser adicionado quando for
  ativado — não bloqueia esta página).
- No ambiente local (mobile e desktop), selecionar "Pessoa Jurídica" no
  checkout mostra tanto o campo de CNPJ quanto o campo de Nome da Empresa.
- Finalizar um pedido de teste como Pessoa Jurídica (CNPJ + Nome da Empresa
  preenchidos) e confirmar, no pedido criado no WooCommerce (wp-admin), que
  ambos os dados foram salvos corretamente.
- Nenhuma regressão no fluxo de Pessoa Física (continua funcionando como hoje).
- Device ID (`MP_DEVICE_SESSION_ID`) continua sendo gerado normalmente após
  qualquer mudança desta página (checagem de não-regressão, já que mexemos na
  documentação/config que protege esse script).

## Constraints
- Sem framework de testes automatizados — validação via checklist manual no
  ambiente XAMPP local (`c:\xampp\htdocs\public_html`), igual à página 001.
- Mudanças em `templates/checkout-elementor-data.json` devem ser feitas com
  cuidado: esse arquivo tem um `custom_css` extenso e já documentado com
  decisões anteriores (ver página 001 e a memória
  `elementor-template-custom-css-history`) — ler o `custom_css` inteiro antes
  de editar, não sobrescrever states relacionados a outras correções já feitas
  (ex: a regra sobre `display:contents`/Mercado Pago).
- Qualquer mudança na configuração do LiteSpeed Cache é feita no wp-admin do
  site real (não em código versionado) — a página só pode *documentar* e
  *recomendar* essa configuração; aplicá-la em produção é uma ação manual do
  usuário fora do escopo de código.
- Não desativar nem reconfigurar o plugin `woocommerce-mercadopago` ou
  `woocommerce-extra-checkout-fields-for-brazil` além do necessário para testar.

## Open questions
- Confirmar durante o plano se "Atraso de Execução de JS" do LiteSpeed está
  ativo em produção hoje (o doc de 08/07/2026 dizia que estava vazio/inativo,
  mas isso pode ter mudado) — isso muda a urgência de proteger o script de
  Device ID contra esse recurso especificamente.
- Confirmar se existe alguma razão de negócio para o campo de Nome da Empresa
  ter sido escondido no mobile (talvez intencional para simplificar o
  formulário mobile) antes de simplesmente reabilitá-lo — se for intencional,
  a solução pode ser reabilitar só quando Pessoa Jurídica for selecionada, via
  JS/CSS mais específico, em vez de simplesmente remover a regra.

## References
- Mercado Pago — recomendações de aprovação de pagamento (WooCommerce):
  https://www.mercadopago.com.br/developers/pt/docs/woocommerce/how-tos/improve-payment-approval/recommendations
- Mercado Pago — referência da API de criação de pagamento (campos `payer`):
  https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api-payments/create-payment/post
- Mercado Pago — Antifraude Plus (produto de conta, não código):
  https://www.mercadopago.com.br/developers/pt/docs/shopify/how-tos/antifraude-plus
- `docs/litespeed-js-nao-atrasar.md`, `templates/checkout-elementor-data.json`
  (campo `custom_css` do widget `bd9deff`).
- Página 001 (`checkout-hardening`) e memória
  `elementor-template-custom-css-history` — contexto sobre o `custom_css` do
  template e por que `display:contents` foi revertido ali.
- `wp-content/plugins/woocommerce-mercadopago/src/Gateways/CustomGateway.php`
  (linha ~183, registro do `wc_mercadopago_security_session`) e
  `wp-content/plugins/woocommerce-extra-checkout-fields-for-brazil/assets/js/frontend/frontend.js`
  (linha ~137, toggle PF/PJ) — no ambiente local de teste.
