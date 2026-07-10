# 001 — Checkout Hardening (robustez, segurança e performance)

## Context
Auditoria do código atual do plugin (PHP + JS) pedida para deixar o checkout mais
robusto e alinhado a boas práticas de performance e segurança. A auditoria
(Fase 1 deste SPEC) encontrou pontos concretos em três frentes — robustez,
segurança e performance — que devem ser corrigidos num único esforço, priorizados
por risco/impacto. Nenhum desses pontos é uma falha crítica em produção hoje; são
lacunas de hardening num plugin que já funciona.

## Goals
- Eliminar os warnings/erros PHP possíveis por falta de checagem de existência de
  arquivo antes de `filemtime()` (`includes/checkout-reorder.php`).
- Tratar falhas de `file_get_contents()`/`wp_insert_post()` em
  `wi_checkout_create_template()` (`includes/checkout-shortcode.php`) de forma
  explícita, sem gravar dado inválido silenciosamente.
- Mover a criação do template Elementor (`wi_checkout_create_template()`) para
  rodar **apenas** no hook de ativação do plugin. O shortcode `[wi_checkout]`
  nunca mais cria o template em runtime a partir de uma request pública; se o
  template estiver ausente, registra um aviso (admin notice e/ou `error_log`) e
  retorna string vazia.
- Diferenciar Elementor free de Elementor Pro no shortcode: checar
  `class_exists('\ElementorPro\Plugin')` além de `did_action('elementor/loaded')`,
  e falhar de forma explícita (aviso no admin) quando só o Elementor free estiver
  ativo, em vez de tentar renderizar um widget Pro-only silenciosamente.
- Declarar as dependências do plugin (WooCommerce, Elementor Pro) no cabeçalho via
  `Requires Plugins` (suportado desde WP 6.5), para que o WordPress avise/impeça a
  ativação sem os pré-requisitos, em vez de falhar em runtime sem explicação.
- Eliminar o Cumulative Layout Shift (CLS) causado pelo reorder do checkout:
  substituir a movimentação de nós via JS (`assets/js/wi-checkout.js`,
  `reorderCheckout()`) por reordenação via CSS (`order` num container flex/grid),
  escopada às classes do Elementor (`.e-checkout__container` e filhos) através de
  um novo arquivo CSS enfileirado pelo plugin. O JS deixa de mover os blocos no
  DOM; mantém apenas os ajustes que não são puramente posicionais (ex.: mover
  `.woocommerce-account-fields` para dentro do card de billing, que é uma
  reestruturação de hierarquia DOM, não apenas de ordem visual).
- Trocar a montagem de `innerHTML` por concatenação de string no resumo de
  pagamento (`updateSummary()` em `wi-checkout.js`) por uma construção que não
  interpola texto lido do DOM diretamente em HTML (defesa em profundidade, mesmo
  com a fonte atual sendo confiável).
- Documentar, num único lugar (comentário de topo ou seção do `PROJECT.md` via
  `grimoire-note` depois), a convenção de escaping esperada para código novo neste
  plugin.

## Non-goals
- Não reescrever a lógica de negócio do checkout em si (fluxo de pedido, cálculo
  de frete/impostos) — isso é responsabilidade do WooCommerce/Mercado Pago.
- Não adicionar um pipeline de CI/CD nem processo de deploy automatizado — fora
  de escopo desta página (ver `PROJECT.md`, deploy continua manual).
- Não adicionar suporte a múltiplas instalações/clientes — o plugin continua sob
  medida para a Web Import Brasil.
- Não migrar o template do checkout para fora do Elementor Pro, nem alterar o
  design visual (cores, textos, grid) além do necessário para a reordenação via
  CSS.
- Não introduzir um framework de testes automatizados novo neste SPEC — pode ser
  proposto como página separada se o usuário quiser depois.

## Scope
- `wi-checkout-customizations.php` — cabeçalho (`Requires Plugins`), possível
  ajuste no hook de ativação para chamar `wi_checkout_create_template()`.
- `includes/checkout-reorder.php` — guarda de `file_exists()` antes de
  `filemtime()`; enfileiramento do novo CSS de reordenação.
- `includes/checkout-shortcode.php` — tratamento de falhas em
  `wi_checkout_create_template()`; remoção da chamada de criação a partir do
  shortcode; checagem de Elementor Pro; admin notice quando o template estiver
  ausente ou o Pro não estiver ativo.
- `assets/js/wi-checkout.js` — remoção da reordenação posicional via JS (mantendo
  só a reestruturação de `.woocommerce-account-fields`); hardening de
  `updateSummary()`.
- Novo arquivo `assets/css/wi-checkout-reorder.css` (ou nome equivalente) — regras
  `order`/`display:flex|grid` para `.e-checkout__container` e filhos.
- `templates/checkout-elementor-data.json` — não deve precisar mudar (o CSS de
  reorder atua sobre classes que o Elementor já gera); confirmar durante o plano
  se algum ajuste de estrutura for necessário.

## Acceptance criteria
- Com qualquer um dos dois arquivos de asset (`wi-checkout.js`,
  `wi-checkout-thumb.css`) ausente, a página de checkout carrega sem warning/erro
  PHP relacionado a `filemtime()`.
- Ativar o plugin sem WooCommerce e/ou Elementor Pro ativos resulta em um aviso
  claro no admin (via `Requires Plugins` e/ou admin notice), não em fatal error.
- Visitar uma página com `[wi_checkout]` quando o template interno não existe
  **não** grava nenhum post/post meta no banco; a página mostra vazio e um
  administrador vê um aviso indicando que precisa reativar o plugin ou rodar a
  criação do template manualmente.
- Ativar (ou reativar) o plugin recria o template do zero corretamente, igual ao
  comportamento atual.
- Com apenas Elementor free ativo (sem Pro), o shortcode não tenta renderizar o
  widget Pro-only; mostra um aviso claro no admin em vez de saída quebrada/vazia
  silenciosa.
- Na página de checkout, os blocos Produtos → Informações → Cupom → Pagamento
  aparecem na ordem correta **sem** reflow visível via JavaScript após o primeiro
  paint — a ordem final vem de CSS, carregado junto com o restante do CSS da
  página (sem FOUC perceptível maior que o já existente hoje por causa do
  Elementor).
- `.woocommerce-account-fields` continua sendo movido para dentro do card de
  "Seus dados" (isso é reestruturação de DOM, mantido em JS).
- O resumo de pagamento (`#wi-payment-summary`) continua atualizando
  corretamente em `updated_checkout`, sem regressão visual, e sem usar
  concatenação direta de string para montar o `innerHTML` a partir do texto lido
  do DOM.
- Nenhuma das mudanças altera o comportamento hoje documentado em
  `includes/checkout-i18n.php` e `includes/compat-fluid-checkout.php` (fora de
  escopo, não tocados).
- Teste manual em produção (ou staging, se existir) confirma: checkout completo
  funciona (Pix/Cartão/Boleto via Mercado Pago), sem quebra visual, com Fluid
  Checkout ativo.

## Constraints
- Sem framework de testes automatizados no repositório — a validação desta
  página é manual (ver critérios de aceite), seguindo o que já é prática no
  projeto (`PROJECT.md` → Current Status).
- Não desativar o Fluid Checkout como estratégia de compatibilidade (constraint
  já registrada em `PROJECT.md`).
- Manter compatibilidade com o LiteSpeed Cache "Combinar JS": o novo CSS de
  reorder deve poder ser enfileirado como arquivo externo (não inline), e o JS
  restante continua sendo carregado como está hoje (ver
  `docs/litespeed-js-nao-atrasar.md`).
- `Requires Plugins` no cabeçalho requer WordPress 6.5+; produção confirmada em
  WordPress 7.0.1, portanto o header pode ser usado. Manter os
  `function_exists`/`class_exists` em runtime como segunda camada de guarda,
  não substituí-los pelo header.
- Mudanças devem ser incrementais e seguras para deploy manual (cópia de pasta
  para `wp-content/plugins/`), sem exigir passo de build.

## Open questions
- Confirmar a estrutura real do grid/flex gerado pelo Elementor para
  `.e-checkout__container` (via inspeção no navegador/DevTools) antes de escrever
  as regras de `order` — os seletores exatos podem exigir ajuste fino que só
  aparece ao testar no admin/editor Elementor.
- Definir o mecanismo exato do admin notice (dashboard notice padrão do WP vs.
  log simples) — deixado para o plano decidir com base no que já existe no
  projeto (hoje nenhum).

## References
- `includes/checkout-reorder.php`, `includes/checkout-shortcode.php`,
  `includes/checkout-thumbnail.php`, `includes/compat-fluid-checkout.php`,
  `assets/js/wi-checkout.js`, `assets/css/wi-checkout-thumb.css`.
- `.grimoire/PROJECT.md` — contexto de produto, stack e constraints já
  registradas.
- `docs/litespeed-js-nao-atrasar.md` — constraint de cache/JS a respeitar.
