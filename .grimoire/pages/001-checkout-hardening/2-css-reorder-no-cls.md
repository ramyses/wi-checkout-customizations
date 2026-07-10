# Step 2 — Eliminar o CLS: reorder do checkout via CSS em vez de JS

> **REVERTIDO (commit `1107b69`, revertendo `76d84bf`).** Depois de executado e
> commitado, uma verificação visual local revelou que
> `templates/checkout-elementor-data.json` já carrega, no `custom_css` do
> próprio widget (id `bd9deff`), um comentário histórico explícito: uma
> tentativa anterior já usou `display:contents` (exatamente esta abordagem) e
> quebrou o widget de preço/desconto do Mercado Pago nas caixas de pagamento,
> que depende de layout de caixa normal para se medir. Por isso o design
> vigente reparenta os blocos via JS de propósito, não por descuido. Este step
> foi revertido para restaurar o reorder via JS; o CLS que ele tentava
> resolver continua sendo uma limitação conhecida e não resolvida. Ver memória
> `elementor-template-custom-css-history` para o achado completo. Uma correção
> real do CLS exigiria reestruturar o JSON do template para que os blocos já
> nasçam como filhos diretos de `.e-checkout__container` (não reparentar nem
> achatar em runtime) — não tentado aqui.

## Context
Segundo passo da página 001 (ver `SPEC.md`). O `reorderCheckout()` atual em
`assets/js/wi-checkout.js` move os blocos do checkout no DOM depois do
`DOMContentLoaded` — ou seja, depois do primeiro paint — causando layout shift
(CLS). Este step substitui essa movimentação por CSS puro, sem JS.

**Investigação já feita (não repita) — estrutura real confirmada lendo o código
fonte do Elementor Pro instalado localmente em
`c:\xampp\htdocs\public_html\wp-content\plugins\elementor-pro\modules\woocommerce\widgets\checkout.php`
(método `woocommerce_checkout_before_customer_details` e seguintes) e o CSS
compilado em
`c:\xampp\htdocs\public_html\wp-content\plugins\elementor-pro\assets\css\widget-woocommerce-checkout-page.min.css`:**

```
<div class="e-checkout__container">                              <!-- display:grid; grid-template-columns:auto (layout "one-column" já ativo neste site) -->
  <div class="e-checkout__column e-checkout__column-start">       <!-- "Informações": login form + #customer_details (billing/account fields) -->
    ...
  </div>
  <div class="e-checkout__column e-checkout__column-end">
    <div class="e-checkout__column-inner e-sticky-right-column">
      <div class="e-checkout__order_review">...</div>             <!-- "Produtos" -->
      <div class="e-coupon-box">...</div>                          <!-- "Cupom" (condicional: só quando needs_payment()) -->
      <div class="e-checkout__order_review-2">...</div>            <!-- "Pagamento" -->
    </div>
  </div>
</div>
```

`.e-checkout__container` é `display:grid`. `.e-checkout__column-end` e
`.e-checkout__column-inner` **não têm nenhuma regra CSS própria** (nem
background, nem padding, nem border) neste stylesheet — são wrappers
estruturais puros. `.e-sticky-right-column` só ganha `position:sticky` com o
modificador `--active`, que só é aplicado quando `checkout_layout=two-column`
(confirmado em `checkout.php`, control `sticky_right_column`) — irrelevante
aqui, pois este site usa `checkout_layout=one-column`.

Isso significa: `reorderCheckout()` hoje não é só uma reordenação — ele
**reparenta** `.e-checkout__order_review`, `.e-coupon-box` e
`.e-checkout__order_review-2` para fora de `.e-checkout__column-inner`/
`.e-checkout__column-end`, direto para dentro de `.e-checkout__container`
(porque `appendChild` em um nó já existente o move, não duplica). CSS puro não
reparenta nós, mas `display: contents` no wrapper produz o mesmo efeito
visual: remove a *box* do wrapper e deixa os filhos participarem do grid do
avô como se fossem filhos diretos — exatamente o que precisamos, sem JS e sem
reflow pós-paint.

## Goals
1. **Novo arquivo `assets/css/wi-checkout-reorder.css`:**
   ```css
   /* Achata os wrappers estruturais do Elementor Pro (sem estilo próprio) para
      que Produtos / Cupom / Pagamento participem do grid de
      .e-checkout__container como itens diretos, junto de Informações — sem
      precisar reparentar nós via JS (evita layout shift pós-paint). */
   .e-checkout__container .e-checkout__column-end,
   .e-checkout__container .e-checkout__column-inner {
   	display: contents;
   }

   .e-checkout__container .e-checkout__order_review   { order: 1; } /* Produtos */
   .e-checkout__container .e-checkout__column-start   { order: 2; } /* Informações */
   .e-checkout__container .e-coupon-box               { order: 3; } /* Cupom (condicional) */
   .e-checkout__container .e-checkout__order_review-2 { order: 4; } /* Pagamento */
   ```
2. **Enfileirar o novo CSS** em `includes/checkout-reorder.php`, junto dos
   demais assets, com o mesmo padrão de guarda de `file_exists()` +
   `filemtime()` do Step 1 (não duplique lógica — siga o mesmo formato já usado
   ali para `wi-checkout-thumb.css`).
3. **Remover a reordenação posicional de `reorderCheckout()`** em
   `assets/js/wi-checkout.js`: apague as chamadas
   `container.appendChild( produtos )`, `container.appendChild( informacoes )`,
   `container.appendChild( couponBox )` (com seu `if`) e
   `container.appendChild( pagamento )`. **Mantenha** o bloco que move
   `.woocommerce-account-fields` para dentro de `.woocommerce-billing-fields`
   (isso é reestruturação real de hierarquia — "Criar uma conta?" precisa virar
   filho do card de billing —, não apenas ordem visual, então continua em JS).
   Depois de remover as linhas de `appendChild` de posição, as variáveis
   `container`, `produtos`, `pagamento`, `couponBox` só existem para o guard
   inicial (`if ( ! container || ! informacoes || ... )`) — ajuste esse guard
   e a função para não referenciar mais variáveis que ficaram sem uso, sem
   remover a checagem de existência de `informacoes` (ainda necessária para o
   bloco de account fields).

## Non-goals (deste step)
- Não mexer no resumo de pagamento (`updateSummary()`) nem no colapso de frete
  (`setupShippingCollapse()`) — isso é Step 3 / já está fora de escopo.
- Não alterar `templates/checkout-elementor-data.json` — a mudança é só CSS.
- Não remover ou alterar `.e-coupon-box` quando ausente (o CSS de `order` não
  precisa de guarda condicional; grid simplesmente ignora o item ausente).

## Verificação manual (checklist)
Use o ambiente XAMPP local (`c:\xampp\htdocs\public_html`, ver Step 1 para como
subir Apache/MySQL e sincronizar os arquivos alterados para lá).
1. Copie `assets/css/wi-checkout-reorder.css`, `includes/checkout-reorder.php`
   e `assets/js/wi-checkout.js` atualizados para o clone em
   `c:\xampp\htdocs\public_html\wp-content\plugins\wi-checkout-customizations\`.
2. Visite a página de checkout (com produtos no carrinho, para que Produtos,
   Informações, Cupom e Pagamento apareçam todos). Confirme visualmente a
   ordem: Produtos → Informações → Cupom → Pagamento — igual ao comportamento
   atual em produção.
3. Abra o DevTools do navegador (Elements/Inspector) e confirme:
   - `.e-checkout__column-end` e `.e-checkout__column-inner` aparecem no DOM
     mas **sem caixa própria** (o inspector deve mostrar os filhos deles
     alinhados como se fossem irmãos de `.e-checkout__column-start` dentro do
     grid — no Chrome/Firefox, elementos com `display:contents` aparecem sem
     highlight de box ao passar o mouse).
   - Na aba Network/Performance (ou só observando visualmente o carregamento),
     confirme que os blocos **não pulam de posição** depois do carregamento —
     a ordem já vem certa no primeiro paint (diferente do comportamento atual
     antes desta mudança).
4. Teste com o carrinho vazio de frete configurado e mude o CEP/endereço para
   disparar `updated_checkout` via AJAX — confirme que a ordem se mantém
   correta após o recálculo (o WooCommerce re-renderiza `#customer_details`,
   `.woocommerce-checkout-review-order-table` etc. via AJAX; como a ordem
   agora é CSS pura, isso deve continuar funcionando sem nenhum código JS
   rodando de novo).
5. Confirme que "Criar uma conta?" continua aparecendo dentro do card de "Seus
   dados" (comportamento herdado do JS que não foi removido).
6. Confirme, com o Fluid Checkout ativo (ver `includes/compat-fluid-checkout.php`,
   não tocado neste step), que o checkout via `[wi_checkout]` continua
   renderizando normalmente — este step não deve interagir com aquele
   comportamento, mas é um teste de regressão barato de fazer junto.
7. Rode o checkout completo pelo menos uma vez (Pix ou Cartão via Mercado
   Pago) neste ambiente local, se as credenciais de sandbox/teste do Mercado
   Pago estiverem configuradas; caso não estejam configuradas localmente, isso
   fica marcado como pendente para o usuário confirmar depois em produção/
   staging real (não bloqueia este step).

## Commit
Ao final, um commit único cobrindo este step:
`perf: replace JS checkout reorder with CSS order to eliminate layout shift`
