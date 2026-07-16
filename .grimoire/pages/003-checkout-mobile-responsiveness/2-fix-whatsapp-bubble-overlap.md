# Step 2 — Evitar que o balão do WhatsApp sobreponha o formulário

## Context
Segundo passo da página 003 (ver `SPEC.md`). Investigação já feita (não
repita): o balão flutuante "Fale Conosco" é o widget "Contact Buttons" do
Elementor Pro — `<div class="e-contact-buttons e-contact-buttons-var-9
has-h-alignment-end has-v-alignment-middle has-platform-whatsapp">`. Ele é
`position: fixed`, com `top` calculado dinamicamente como metade da altura
da viewport (`top: 1200px` numa viewport de 2400px de altura) mais um
`transform: translateY(-24px)` — ou seja, fica **sempre centralizado
verticalmente na tela**, não ancorado no canto inferior como é comum pra
esse tipo de botão. Por isso ele sobrepõe qualquer campo do formulário que
caia no meio da tela conforme o usuário rola o checkout.

Esse widget **não faz parte do template deste plugin** (não está em
`templates/checkout-elementor-data.json` sob o widget `bd9deff`) — é uma
configuração global do Elementor Pro (provavelmente Theme Builder/popup),
renderizada em toda página do site, fora do nosso controle de código. Mas,
seguindo o mesmo padrão já usado no `custom_css` do nosso widget pra
"ancestrais do tema" (`body`, `.wd-content-area`, etc. — CSS sem o prefixo
`selector`, que só carrega quando a página do checkout usa nosso widget, mas
aplica a qualquer elemento da página, não só dentro do widget), dá pra
sobrescrever a posição desse balão só na página de checkout, sem tocar na
configuração global dele em nenhum outro lugar do site.

## Goal
Em `templates/checkout-elementor-data.json`, no `custom_css` do widget
`bd9deff`, adicionar uma regra **sem o prefixo `selector`** (ancestro do
tema/site-wide, seguindo o padrão já documentado no topo do bloco mobile
para `body`/`.wd-content-area`), forçando o balão a ficar ancorado embaixo
em vez de centralizado:
```css
.e-contact-buttons {
	top: auto !important;
	bottom: 20px !important;
	transform: none !important;
}
```
Adicione um comentário curto explicando que isso é intencional (ancestro de
fora do widget, escopado só à página de checkout porque esse CSS só carrega
ali) — igual ao estilo dos comentários já existentes nesse arquivo.

Decida, ao implementar, se essa regra deve ficar dentro do bloco
`@media (max-width: 768.98px)` (só mobile, já que a sobreposição foi
observada em viewport mobile) ou fora dele (se o mesmo problema também
ocorrer em desktop — verifique isso durante a checklist abaixo antes de
decidir o escopo).

## Non-goals
- Não mexe na configuração global do widget Contact Buttons (isso é uma
  config do Elementor em outro lugar do site, fora do escopo deste plugin) —
  só sobrescreve a posição via CSS, só na página de checkout.
- Não mexe nas regras do Step 1 (Número/Complemento) nem mexe ainda no
  `#main-content` (Step 3).

## Verificação manual (checklist)
Use Chrome headless via protocolo DevTools com `Emulation.setDeviceMetricsOverride`
(não `--window-size` de linha de comando — confirmado pouco confiável nesta
investigação).

1. Verifique primeiro se a sobreposição também ocorre em viewport desktop
   (ex. 1280px) antes de decidir se a regra fica só no bloco mobile ou não.
2. Aplique a mudança, sincronize pro ambiente de teste, adicione produto ao
   carrinho, carregue o checkout em viewport mobile (375px) e role a página
   do topo ao fim do formulário, medindo a posição do `.e-contact-buttons`
   (`getBoundingClientRect()`) em pelo menos 3 pontos de rolagem diferentes
   (topo, meio do formulário, próximo ao método de pagamento).
3. Confirme que o balão nunca sobrepõe (via checagem de interseção de
   retângulos) nenhum campo do formulário (`#billing_number_field`,
   `#billing_address_2_field`, e os demais campos de "Seus dados") em
   nenhum desses pontos de rolagem.
4. Confirme visualmente (screenshot via `Page.captureScreenshot`) que o
   balão continua clicável/visível (não sumiu, só mudou de posição) e que
   ele não sai da tela (ex. não fica com `bottom` negativo escondido atrás
   do rodapé fixo de navegação mobile, se houver um).
5. Confirme que o balão continua funcionando normalmente em outras páginas
   do site (não teste extensivamente, só confirme que a regra realmente só
   carrega na página de checkout, olhando se o CSS enfileirado é específico
   dessa página).

## Commit
`fix: anchor WhatsApp contact button to bottom on checkout to avoid overlapping form fields`
