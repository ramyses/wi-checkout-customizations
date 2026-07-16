# Step 1 — Alinhar Número e Complemento pela base

## Context
Primeiro passo da página 003 (ver `SPEC.md`). Investigação já feita (não
repita): o label de "Número" ("Número *") é curto e ocupa 1 linha; o label de
"Complemento" ("Apartamento, suíte, unidade, etc. (opcional)") é bem mais
longo e quebra em 2 linhas dentro da coluna estreita (~131px). Isso NÃO é um
bug de CSS quebrado/escondido — os dois labels renderizam normalmente
(`display:block`, visíveis) — é só que um é naturalmente mais alto que o
outro. As duas caixas externas (`#billing_number_field`,
`#billing_address_2_field`) já ficam com a mesma altura (84px, o
`flex:stretch` do wrapper funciona), mas como o conteúdo não é alinhado pela
base, o input de "Complemento" fica 18px mais baixo que o de "Número"
(confirmado via medição: `numInput` top=1498/bottom=1540,
`compInput` top=1516/bottom=1558 — mesma altura de 42px cada, offset de 18px).

## Goal
Em `templates/checkout-elementor-data.json`, no `custom_css` do widget
`bd9deff`, adicionar (na seção de pares de campos, perto de onde já existem
regras parecidas pra Tipo de Pessoa/CPF e Cidade/Estado):
```css
selector #billing_number_field,
selector #billing_address_2_field {
	align-self: flex-end;
}
```
Isso alinha as duas caixas pela base dentro da linha flex, garantindo que os
dois inputs fiquem na mesma posição vertical, independente de quantas linhas
o label de cada um ocupar.

**Importante:** este arquivo é uma string JSON — não edite o texto bruto do
JSON com find/replace ingênuo. Escreva um script Node que faça `JSON.parse`,
localize o widget `bd9deff` na árvore `elements`, edite só a string
`settings.custom_css` (inserindo a nova regra no ponto certo), e
`JSON.stringify` de volta preservando o resto do arquivo. Depois de editar,
rode `git diff --stat` pra confirmar que só essa parte mudou.

## Non-goals
- Não mexe em nenhuma outra regra do `custom_css` (balão do WhatsApp e
  `#main-content` são os próximos steps).
- Não mexe em `#billing_persontype_field`/`#billing_cpf_field` nem
  `#billing_city_field`/`#billing_state_field` (outros pares, já funcionam).

## Verificação manual (checklist)
Use Chrome headless controlado via protocolo DevTools (CDP), **não** a flag
`--window-size` da linha de comando sozinha — nesta mesma investigação ela se
mostrou pouco confiável (relatou innerWidth de ~504px quando o pedido era
375px). Use `Emulation.setDeviceMetricsOverride` com `width:375,
deviceScaleFactor:1, mobile:true` pra garantir um viewport mobile real, e
`Runtime.evaluate` pra medir posições via `getBoundingClientRect()`.

1. Sincronize o JSON editado pro ambiente de teste (local XAMPP ou, com
   cautela, direto contra produção real via `curl`/Chrome headless, sempre
   como uma ação normal de visitante — adicionar produto ao carrinho, nunca
   finalizar pedido de teste).
2. Adicione um produto ao carrinho, carregue o checkout, e meça
   `#billing_number` e `#billing_address_2` via
   `getBoundingClientRect().top`. Confirme que os dois **começam na mesma
   posição vertical** (diferença de 0px, ou no máximo 1-2px de arredondamento).
3. Confirme visualmente (screenshot via `Page.captureScreenshot` no CDP, não
   via `--screenshot` de linha de comando) que os dois inputs aparecem
   alinhados lado a lado na mesma altura.
4. Confirme que nada mudou no par Tipo de Pessoa/CPF nem Cidade/Estado
   (ainda alinhados como antes).

## Commit
`fix: align Número and Complemento inputs to the same baseline on mobile`
