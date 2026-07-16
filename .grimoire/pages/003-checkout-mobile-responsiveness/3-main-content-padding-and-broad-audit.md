# Step 3 — Reavaliar o padding do #main-content e auditoria ampla mobile

## Context
Terceiro e último passo da página 003 (ver `SPEC.md`). Uma tentativa
anterior (fora deste pipeline) de zerar o `padding-inline` do `#main-content`
no mobile foi revertida por ter "dado ruim", mas o motivo exato nunca foi
diagnosticado, e o usuário não lembra o que quebrou especificamente.
Investigação já feita nesta página (não repita): com o viewport mobile
medido corretamente (375px reais via `Emulation.setDeviceMetricsOverride`,
não via flag `--window-size`), **não foi possível reproduzir overflow
horizontal** — `document.documentElement.scrollWidth` bate exatamente com
`window.innerWidth` no estado atual (com o padding de 15px ainda presente).
Isso não prova que reaplicar o fix é seguro — só que o problema original,
seja lá qual for, não é (ou não é mais) um overflow horizontal simples.

## Goals

### Parte A — Reavaliar o #main-content padding-inline
1. Reaplique **temporariamente** (sem commitar ainda) a regra já conhecida:
   ```css
   #main-content {
   	padding-inline: 0 !important;
   }
   ```
   dentro do bloco mobile do `custom_css` (mesmo texto do fix revertido
   anteriormente — consulte o histórico do git, commit
   `ffaae7a` e seu revert `8e4d7dc`, se quiser ver o diff exato de antes).
2. Compare visualmente ANTES e DEPOIS (screenshots via
   `Page.captureScreenshot`, viewport mobile 375px real) do checkout
   completo, do topo ao fim do formulário e da seção de pagamento. Preste
   atenção especial a: espaçamento lateral dos cards, se algum elemento
   passa a tocar a borda da tela de forma que pareça quebrado (não só
   "mais colado"), se a caixa de frete/cupom/pagamento mudam de largura de
   forma inesperada, e se surge QUALQUER overflow horizontal novo
   (`scrollWidth` vs `innerWidth`) que não existia antes.
3. **Se nada quebrar visivelmente** e o `scrollWidth` continuar igual ao
   `innerWidth`: mantenha a mudança (ela remove uma faixa de espaço vazio
   nas laterais que não parece ter função visual), documente no commit que
   foi reavaliada com verificação antes/depois, e inclua uma nota curta no
   `custom_css` (comentário) explicando que essa regra já foi tentada,
   revertida por um problema não diagnosticado, e reaplicada após nova
   verificação nesta página não encontrar regressão.
4. **Se algo quebrar de forma clara**: reverta essa parte específica (não
   afeta os Steps 1/2, que são independentes), descreva no relatório final
   exatamente o que quebrou (com screenshot), e deixe a página 003 concluída
   sem essa parte — não é bloqueante para os outros dois fixes.

### Parte B — Auditoria ampla de responsividade mobile
Com o checkout carregado em viewport mobile (375px e, se der tempo, também
414px), percorra visualmente do topo ao fim (incluindo a seção de
pagamento com Pix/Cartão/Boleto) e reporte qualquer problema de
responsividade **novo**, não mapeado nos Steps 1/2/Parte A — por exemplo:
texto cortado, botões maiores que a tela, imagens de produto distorcidas,
espaçamento inconsistente entre cards, elementos sobrepostos. Para cada
problema encontrado:
- Se for pequeno e claramente seguro de corrigir com CSS pontual (seguindo
  os mesmos padrões já usados no `custom_css`), corrija.
- Se for maior, ambíguo, ou exigir decisão de design, **não corrija
  sozinho** — apenas descreva no relatório final para o usuário decidir se
  quer abrir uma página nova pra isso.

## Non-goals
- Não mexe nas regras dos Steps 1 e 2 (Número/Complemento, balão do
  WhatsApp) — já devem estar corretas quando este step rodar.
- Não faz redesign visual — só corrige problemas de responsividade
  pontuais, mantendo cores/tipografia/estrutura já definidas.
- Não mexe em `includes/checkout-i18n.php`, `includes/compat-fluid-checkout.php`,
  nem em lógica de gateway de pagamento.

## Verificação manual (checklist)
Use Chrome headless via protocolo DevTools com `Emulation.setDeviceMetricsOverride`
(375px e 414px, `deviceScaleFactor:1`, `mobile:true`).

1. Teste contra produção real (`webimportbrasil.com.br`) com uma ação normal
   de visitante (adicionar produto ao carrinho) — sem finalizar nenhum
   pedido de teste, sem tentar login no wp-admin, sem mexer em
   `wp_users`/credenciais.
2. Para a Parte A: capture screenshot antes de aplicar a mudança, aplique,
   capture depois, e compare lado a lado antes de decidir manter ou
   reverter.
3. Para a Parte B: percorra o checkout inteiro (Produtos → Seus dados →
   Cupom → Pagamento) em 375px e 414px, documentando qualquer achado com
   screenshot.
4. Ao final, confirme mais uma vez que Steps 1 e 2 continuam corretos
   (Número/Complemento alinhados, balão do WhatsApp não sobrepõe nada) —
   checagem de regressão rápida antes de finalizar.

## Commit
Um commit para a Parte A (se mantida):
`fix: remove theme container padding on checkout for mobile (re-verified)`

Um commit separado para qualquer correção pontual da Parte B, com mensagem
descrevendo o problema específico corrigido. Se a Parte B não encontrar
nada que precise de correção imediata (só achados para reportar), não
precisa de commit para essa parte — só o relatório final.
