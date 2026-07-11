# Step 2 — Corrigir campo de Nome da Empresa (Pessoa Jurídica) no mobile

## Context
Segundo e último passo da página 002 (ver `SPEC.md`). Confirmado com o usuário:
esconder o campo "Nome da Empresa" no mobile para Pessoa Jurídica **não foi
intencional** — corrigir para mostrar o campo em qualquer tamanho de tela
quando "Tipo de Pessoa" = Jurídica.

**Causa raiz (já investigada, não repita):** o plugin
`woocommerce-extra-checkout-fields-for-brazil` alterna os campos de PF/PJ via
jQuery `.show()`/`.hide()` (estilo inline, sem `!important`) no evento
`change` de `#billing_persontype`
(`wp-content/plugins/woocommerce-extra-checkout-fields-for-brazil/assets/js/frontend/frontend.js`,
por volta da linha 137). O `custom_css` do widget `woocommerce-checkout-page`
(id `bd9deff`) dentro de `templates/checkout-elementor-data.json`, no bloco
`@media (max-width: 768.98px)`, tem esta regra:
```css
selector #billing_company_field,
selector #billing_country_field,
selector .hostinger-reach-optin {
	display: none !important;
}
```
O `!important` vence o `.show()` inline do plugin — por isso o campo nunca
aparece no mobile, mesmo quando Jurídica é selecionada. `#billing_cnpj_field`
não está nessa regra, então o CNPJ em si já funciona; só o Nome da Empresa
está preso.

## Goals
1. Editar o `custom_css` do widget `bd9deff` dentro de
   `templates/checkout-elementor-data.json` (é uma string JSON — abra com
   cuidado, é um campo de texto longo dentro de `settings.custom_css`, não um
   arquivo `.css` solto) para remover `#billing_company_field` dessa regra
   `display:none !important`. Mantenha `#billing_country_field` e
   `.hostinger-reach-optin` escondidos (não fazem parte do escopo — o campo de
   país é fixo em "Brasil" em outro lugar, e o opt-in da Hostinger é
   cosmético). Ajuste o comentário acima da regra se necessário para não
   ficar desatualizado (ele já explica que só campos "opcionais ou já
   resolvidos em outro lugar" devem ficar nessa lista mobile-only — o Nome da
   Empresa deixa de se encaixar nisso).
2. **Não delete a regra inteira nem mexa nas outras entradas do `custom_css`**
   — é um campo grande com histórico de decisões documentado (ver página 001 e
   a memória `elementor-template-custom-css-history`). Leia o `custom_css`
   inteiro antes de editar, edite só a linha/seletor necessário.
3. Depois de editar o JSON, sincronize o arquivo para o ambiente de teste local
   (`c:\xampp\htdocs\public_html\wp-content\plugins\wi-checkout-customizations\templates\checkout-elementor-data.json`)
   e recrie o template Elementor a partir do JSON atualizado — chame
   `wi_checkout_create_template()` uma vez nesse ambiente (via script PHP com
   `wp-load.php`, do jeito já usado antes) para que a mudança do JSON realmente
   apareça no template interno já criado (lembre: desde a página 001, o
   shortcode não recria mais o template sozinho em runtime — só a ativação do
   plugin ou uma chamada manual a essa função faz isso).

## Non-goals (deste step)
- Não mexer em `docs/litespeed-js-nao-atrasar.md` (Step 1, já concluído).
- Não alterar nenhum outro campo, cor, ou layout do checkout.
- Não adicionar validação nova de CPF/CNPJ — só confirmar que a já existente
  (do plugin `woocommerce-extra-checkout-fields-for-brazil`) continua
  funcionando.

## Verificação manual (checklist)
No ambiente XAMPP local (mobile E desktop — use o DevTools do Chrome pra
emular viewport mobile, ou `--window-size` menor no headless):
1. No checkout, selecionar "Pessoa Jurídica" em "Tipo de Pessoa" e confirmar
   que os campos de CNPJ e Nome da Empresa aparecem os dois, tanto em viewport
   desktop (≥769px) quanto mobile (<769px).
2. Confirmar que "Pessoa Física" continua mostrando só CPF (sem Nome da
   Empresa/CNPJ), sem regressão, nas duas larguras.
3. Preencher um pedido de teste completo como Pessoa Jurídica (CNPJ válido de
   teste + Nome da Empresa) e finalizar até criar o pedido no WooCommerce.
4. No wp-admin, abrir o pedido criado e confirmar que o Nome da Empresa e o
   CNPJ foram salvos corretamente nos metadados do pedido (não use login
   real do usuário — se precisar de acesso ao wp-admin autenticado e não
   houver sessão disponível sem criar/alterar credenciais, verifique os dados
   direto na tabela de post meta via `wp-load.php`/`WC_Order::get_meta()` em
   vez de logar no admin).
5. Confirmar, via `wp-content/debug.log`, que nada quebrou (nenhum warning/
   notice novo).

## Commit
Ao final, um commit único cobrindo este step:
`fix: show company name field for legal-entity checkout on mobile`
