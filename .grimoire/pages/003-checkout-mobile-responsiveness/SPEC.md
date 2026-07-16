# 003 — Responsividade do checkout em smartphone

## Context
Pedido para melhorar a responsividade do checkout com foco em smartphone.
Antes de escrever este SPEC, foi feita uma investigação direta na produção
(`https://webimportbrasil.com.br/finalizar-compra/`, com carrinho real, via
Chrome headless controlado pelo protocolo DevTools com viewport mobile
forçado corretamente em 375px — o `--window-size` da linha de comando do
Chrome se mostrou pouco confiável, gerando medições de viewport erradas em
tentativas anteriores nesta mesma sessão).

Achados confirmados (com números reais, não suposição):

1. **Não há overflow horizontal real hoje.** Uma tentativa anterior (fora
   deste pipeline) de zerar o `padding-inline` do `#main-content` no mobile
   foi revertida por ter "dado ruim", mas o motivo exato nunca foi
   diagnosticado. Com o viewport mobile corretamente medido, `scrollWidth`
   bate exatamente com `innerWidth` (375 = 375) — ou seja, hoje não existe
   overflow horizontal no checkout. O problema que motivou aquele revert
   pode ter sido outra coisa (efeito colateral não relacionado a overflow),
   ou pode ter sido um falso positivo de teste, igual ao que aconteceu nesta
   investigação antes de corrigir a medição do viewport.
2. **Número/Complemento: desalinhamento real, medido.** As duas caixas
   externas (`#billing_number_field`, `#billing_address_2_field`) têm a
   mesma altura (84px — o `flex: stretch` do wrapper funciona). Mas o input
   de "Complemento" está 18px mais para baixo que o de "Número"
   (`numInput` top=1498/bottom=1540; `compInput` top=1516/bottom=1558,
   ambos com 42px de altura). O label de "Complemento"
   (`label.screen-reader-text` dentro de `#billing_address_2_field`) mede
   **altura zero** em produção, com uma posição à esquerda (`left`) bem
   distante do início do campo — indício de que a regra de CSS já existente
   no `custom_css` do template (que deveria tornar esse label visível,
   `display:block !important` etc.) **não está de fato surtindo efeito em
   produção**, apesar de já existir no JSON. Causa raiz ainda não confirmada
   (ver Open Questions).
3. **Achado novo: o balão flutuante "Fale Conosco" (WhatsApp) sobrepõe
   campos do formulário no mobile**, dependendo da posição de rolagem —
   chegou a cobrir completamente o campo "Número" durante o teste. Esse
   widget não é deste plugin (é de outro plugin/tema), mas atrapalha o
   preenchimento do checkout em telas pequenas.

## Goals
- Corrigir o desalinhamento entre "Número" e "Complemento": investigar por
  que a regra que deveria desconder o label de "Complemento" não está
  surtindo efeito em produção (cache do CSS gerado pelo Elementor para esse
  post? a classe `screen-reader-text` mudou numa atualização do WooCommerce?
  outra coisa?), e então aplicar a correção real — não só uma
  correção de sintoma (como forçar altura/posição via `align-self` sem
  entender a causa).
- Investigar e, se possível, corrigir a sobreposição do balão do WhatsApp
  sobre os campos do checkout no mobile — via CSS do nosso `custom_css`
  (ex.: afastar/reposicionar o balão, ou garantir margem/z-index adequado),
  já que não temos acesso ao código do plugin do WhatsApp.
- Reinvestigar com cuidado o caso do `#main-content padding-inline`: como
  não foi possível reproduzir overflow real agora, determinar se essa
  mudança é segura de reaplicar, e só reaplicar com verificação visual
  completa (antes/depois, em viewport mobile corretamente medido) — não
  repetir o mesmo fix às cegas.
- Fazer uma auditoria mais ampla do checkout em viewport mobile (375–428px):
  revisar espaçamentos, botões, cards (Produtos/Cupom/Pagamento), imagens de
  produto, caixa de frete, e qualquer outro elemento visível, procurando
  problemas de responsividade ainda não mapeados.

## Non-goals
- Não mexer no layout desktop.
- Não alterar o código do plugin/tema responsável pelo balão do WhatsApp —
  só o nosso próprio CSS, se houver como mitigar a partir daqui.
- Não é um redesign visual do checkout — é correção de responsividade sobre
  o design já existente (cores, cards, tipografia já definidos nas páginas
  001/002 e ajustes pontuais anteriores permanecem como estão).
- Não mexer em `includes/checkout-i18n.php`, `includes/compat-fluid-checkout.php`,
  nem na lógica de gateways de pagamento.

## Scope
- `templates/checkout-elementor-data.json` (custom_css do widget `bd9deff`)
  — principal superfície de mudança.
- `includes/checkout-reorder.php` — só se algum novo asset precisar ser
  enfileirado (não esperado, mas possível).
- Nenhuma mudança esperada em `assets/js/wi-checkout.js`.

## Acceptance criteria
- Testado em produção real (ou staging, se disponível) com viewport mobile
  **corretamente forçado** (via `Emulation.setDeviceMetricsOverride` do
  protocolo DevTools, não via flag de linha de comando `--window-size`, que
  se mostrou pouco confiável nesta investigação) em pelo menos 375px e
  414px de largura:
  - "Número" e "Complemento" alinhados na mesma linha de base (inputs
    começando na mesma posição vertical, não só as caixas externas).
  - O balão do WhatsApp não cobre nenhum campo do formulário durante o
    preenchimento normal do checkout (scroll do topo ao fim do formulário).
  - Nenhum elemento do checkout ultrapassa a largura do viewport
    (`document.documentElement.scrollWidth` igual a `window.innerWidth`,
    exceto pelo marquee/ticker do topo do site, que é intencionalmente mais
    largo que a tela — isso não é um bug, não deve ser "corrigido").
  - Se o fix do `#main-content padding-inline` for reaplicado, documentar
    explicitamente o que foi verificado antes/depois (prints ou medições) e
    o que se concluiu sobre o problema original.
- Nenhuma regressão no fluxo Pessoa Física/Jurídica (página 002) nem no
  reorder/hardening já existentes (página 001).

## Constraints
- Sem framework de testes automatizado — validação manual, com medições via
  protocolo DevTools (não screenshots feitos só com flags de CLI do Chrome,
  que já geraram um falso positivo nesta própria investigação).
- `templates/checkout-elementor-data.json` tem um `custom_css` extenso com
  decisões documentadas de páginas anteriores (ver página 001 e a memória
  `elementor-template-custom-css-history`) — ler o `custom_css` inteiro
  antes de editar, não sobrescrever regras de outras correções (em
  particular, nunca usar `display:contents` nos wrappers do
  `.e-checkout__container` — já comprovadamente quebra o widget do Mercado
  Pago).
- Não tentar login no wp-admin nem mexer em `wp_users`/credenciais, em
  nenhum ambiente (local ou produção) — restrição permanente do projeto.
- Não usar `taskkill`/`pkill` por nome de imagem genérico (ex.: `chrome.exe`)
  — matar processo por PID específico, nunca em massa.
- Testar preferencialmente contra produção real (`webimportbrasil.com.br`)
  usando ações normais de visitante (adicionar produto ao carrinho, navegar)
  — sem finalizar nenhum pedido de teste de verdade, sem deixar dados de
  teste "sujos" no banco de produção.

## Open questions
- Por que o label de "Complemento" mede altura zero em produção mesmo com a
  regra CSS já existente pra desescondê-lo? Hipóteses a checar no plano:
  cache de CSS do Elementor desatualizado para esse post específico, mudança
  de classe/estrutura numa versão mais nova do
  `woocommerce-extra-checkout-fields-for-brazil` ou do WooCommerce, ou algo
  na regra CSS que não está com a especificidade/seletor corretos.
- O balão do WhatsApp tem um seletor CSS estável e identificável (classe,
  id) que dá pra mirar com segurança, ou ele muda de posição/estrutura
  dependendo do plugin/versão? Precisa inspecionar o elemento real antes de
  propor a correção.
- O que de fato "deu ruim" com o fix antigo do `#main-content
  padding-inline`? Não foi possível reproduzir um problema agora — pode ter
  sido resolvido por outra mudança no meio tempo, pode ter sido um teste
  falho (como o desta própria investigação antes da correção do viewport),
  ou pode haver um cenário específico (outro navegador, outra largura,
  outro estado de carrinho) que ainda reproduz o problema.

## References
- `templates/checkout-elementor-data.json` (custom_css do widget `bd9deff`).
- Página 001 (`checkout-hardening`) e memória
  `elementor-template-custom-css-history` — histórico do `custom_css` e por
  que `display:contents` foi revertido.
- Investigação desta sessão: medições feitas via Chrome DevTools Protocol
  (`Emulation.setDeviceMetricsOverride`, `Runtime.evaluate`,
  `Page.captureScreenshot`) diretamente contra
  `https://webimportbrasil.com.br/finalizar-compra/`.
