# Step 1 — Proteger o script de Device ID do Mercado Pago no LiteSpeed

## Context
Primeiro passo da página 002 (ver `SPEC.md`). O plugin oficial
`woocommerce-mercadopago` já enfileira `wc_mercadopago_security_session`
(arquivo `session.min.js`), que por sua vez injeta
`https://www.mercadopago.com/v2/security.js` e popula `MP_DEVICE_SESSION_ID`
— o Device ID que o Mercado Pago usa para reduzir recusa/fraude. Isso **já
funciona hoje** (confirmado via inspeção do ambiente local). O problema é que
`docs/litespeed-js-nao-atrasar.md` — a referência do projeto para configurar o
LiteSpeed Cache sem quebrar scripts do checkout — não lista esse script entre
os protegidos.

**Investigação já feita (não repita):** consultei a configuração real do
LiteSpeed no ambiente local via `wp-load.php` (`get_option('litespeed.conf.*')`)
e confirmei:
- `optm-js_delay` (Atraso de Execução de JS) = `false` — **desativado hoje**.
  Não é um risco urgente agora, mas pode ser ativado no futuro.
- `optm-js_delay_inc` = `[]` — lista vazia, nada sendo atrasado por esse
  recurso.
- `optm-js_exc` (exclusão de "Combinar JS Externo") já inclui
  `jquery.min.js`, `jquery.js`, `woocommerce_params`, `order-attribution`,
  `wi-checkout.js` — **mas não inclui `session.min.js`** nem nenhum script
  `mp-*`/SDK do Mercado Pago.
- `optm-js_defer_exc` (exclusão de "Adiar JS") só tem `jquery.js`,
  `jquery.min.js`, `gtm.js`, `analytics.js` — o script de Device ID também não
  está aqui. Na prática, hoje ele já roda com o atributo `defer`, e isso não
  quebra nada porque o próprio `session.min.js` já espera o evento `load` da
  janela antes de agir — mas fica sem essa garantia documentada/formalizada.
- Confirmado via dump da página de checkout local: o script
  (`wc_mercadopago_security_session-js`) é servido como arquivo próprio,
  individual (não combinado com outros), e `security.js`/`MP_DEVICE_SESSION_ID`
  funcionam corretamente hoje. **Não há quebra ativa agora** — isso é hardening
  preventivo, não um bug em produção.

## Goals
1. **Atualizar `docs/litespeed-js-nao-atrasar.md`:**
   - Adicionar `session.min.js` (handle `wc_mercadopago_security_session`) e o
     próprio `https://www.mercadopago.com/v2/security.js` à lista de "Scripts
     do Mercado Pago que precisam ser adicionados às exclusões", com uma nota
     explicando que esse é o script de Device ID (fingerprint) usado pelo
     Mercado Pago para prevenção de fraude, junto com os já listados
     (`mp-checkout-*`, `mp-custom-*`, SDK).
   - Atualizar a seção "Estado atual (já configurado, confirmado no banco)"
     para refletir a configuração real encontrada agora (`wi-checkout.js` já
     está em `optm-js_exc`; os scripts `mp-*`/SDK/Device ID ainda não estão em
     nenhuma lista de exclusão — nem `optm-js_exc` nem `optm-js_defer_exc`).
     Mantenha a data desta atualização visível (ex.: "Atualizado em <data de
     hoje>") do mesmo jeito que o documento já registra a data da checagem
     anterior (08/07/2026).
   - Deixar claro que `optm-js_delay` está desativado hoje, mas que se for
     ativado no futuro, o Device ID (junto com os scripts mp-* já listados)
     precisa entrar na exclusão desse recurso também.
2. **Aplicar a proteção no ambiente local de teste** (não em produção — isso é
   só para verificar que a mudança funciona antes de recomendar ao usuário
   replicar em produção via wp-admin):
   - Usando a mesma abordagem de bootstrap read-only via `wp-load.php` já
     usada na investigação (nunca acesse `wp_users` nem credenciais — só as
     opções do LiteSpeed), adicione `session.min.js` (ou o handle
     `wc_mercadopago_security_session`) à opção
     `litespeed.conf.optm-js_exc` e a `litespeed.conf.optm-js_defer_exc`,
     usando `update_option()` do WordPress (não escreva SQL bruto na tabela).
   - Confirme, via novo dump da página de checkout local (headless Chrome ou
     curl), que o script continua carregando e que `security.js`/
     `MP_DEVICE_SESSION_ID` continuam funcionando normalmente após a mudança.
3. **Não altere nada em produção.** A mudança de configuração no site real é
   uma ação manual do usuário no wp-admin — o resultado deste step é a
   documentação atualizada + a confirmação de que funciona no ambiente local.

## Non-goals (deste step)
- Não mexer em `templates/checkout-elementor-data.json` (Step 2).
- Não mexer em nenhum arquivo `.php`/`.js` do próprio plugin
  `wi-checkout-customizations` além da documentação em `docs/`.
- Não configurar o LiteSpeed Cache em produção.

## Verificação manual (checklist)
1. Ler o `docs/litespeed-js-nao-atrasar.md` atualizado e confirmar que está
   claro, consistente com o resto do documento, e com a data de atualização
   registrada.
2. No ambiente local, confirmar (via `get_option()`, mesmo mecanismo de
   bootstrap read-only) que `session.min.js` (ou o handle correspondente)
   agora aparece em `litespeed.conf.optm-js_exc` e
   `litespeed.conf.optm-js_defer_exc`.
3. Visitar a página de checkout local e confirmar, via dump do HTML, que
   `wc_mercadopago_security_session-js`/`security.js` continuam presentes e
   que nada quebrou (nenhum novo warning em `wp-content/debug.log`).

## Commit
Ao final, um commit único cobrindo este step (só o arquivo de documentação —
a config do LiteSpeed local não é versionada neste repositório):
`docs: protect Mercado Pago device ID script in LiteSpeed exclusion list`
