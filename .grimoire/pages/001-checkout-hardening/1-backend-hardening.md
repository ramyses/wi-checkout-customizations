# Step 1 — Backend hardening & security (dependências, template, admin notices)

## Context
Primeiro passo da página 001 (ver `SPEC.md`). Este step cobre só a parte PHP de
robustez e segurança: guardas de filesystem, tratamento de falha na criação do
template Elementor, remoção da escrita em banco disparada por visitante anônimo,
diferenciação Elementor free/Pro, e declaração de dependências no cabeçalho do
plugin. Não mexe em CSS/JS de reorder (isso é o Step 2) nem no resumo de
pagamento (Step 3).

Este projeto não tem framework de testes automatizados — não há Red/Green/Refactor
aqui. Em vez disso, siga a checklist de verificação manual no final deste step,
usando o ambiente WordPress local via XAMPP.

## Ambiente de teste local (XAMPP)
Existe uma cópia funcional do WordPress em `c:\xampp\htdocs\public_html`, com este
mesmo plugin instalado em
`c:\xampp\htdocs\public_html\wp-content\plugins\wi-checkout-customizations`
(clone git separado do repositório de trabalho). WooCommerce, Elementor, Elementor
Pro e Fluid Checkout já estão instalados nessa cópia (confirmado em
`wp-content/plugins/`).

Para testar suas mudanças:
1. Suba Apache e MySQL: `C:\xampp\apache_start.bat` e `C:\xampp\mysql_start.bat`
   (ou verifique se já estão rodando antes de iniciar de novo).
2. Depois de editar os arquivos no repositório de trabalho
   (`c:\xampp\htdocs\PLUGINS\wi-checkout-customizations`), copie os arquivos PHP
   alterados para
   `c:\xampp\htdocs\public_html\wp-content\plugins\wi-checkout-customizations\`
   (sobrescrevendo), para refletir a mudança no ambiente rodando. Não faça commit
   nesse segundo clone — ele é só ambiente de teste.
3. Descubra a URL da página de checkout (ex.: acessando
   `http://localhost/public_html/wp-admin/admin.php?page=wc-settings&tab=advanced`
   logado como admin, ou inspecionando `wp_options` via
   `C:\xampp\mysql\bin\mysql.exe` se preferir linha de comando).
4. Depois de qualquer mudança PHP, confirme que não há fatal error/warning novo:
   ative `WP_DEBUG`/`WP_DEBUG_LOG` no `wp-config.php` dessa cópia local (se ainda
   não estiver ativo) e cheque `wp-content/debug.log` após visitar a página de
   checkout e o wp-admin.

## Goals
1. **Guarda de filesystem em `includes/checkout-reorder.php`.** Antes de cada
   `filemtime()` (para `wi-checkout.js` e `wi-checkout-thumb.css`), verifique
   `file_exists()`. Se o arquivo não existir, pule o `wp_enqueue_script`/
   `wp_enqueue_style` daquele asset específico (não quebre o restante da função) e
   registre `error_log( 'WI Checkout: asset ausente: ' . <caminho> )`.
2. **Tratamento de falha em `wi_checkout_create_template()`
   (`includes/checkout-shortcode.php`).**
   - Se `file_get_contents( $json_path )` retornar `false`, logue com `error_log`
     e retorne `0` imediatamente — não chame `wp_slash( false )`.
   - Se `wp_insert_post()` retornar `WP_Error` ou `0`/`false` (já há um check —
     mantenha, só confirme que também loga via `error_log` com
     `is_wp_error( $post_id ) ? $post_id->get_error_message() : 'erro desconhecido'`
     antes do `return 0`).
3. **Template só é criado na ativação.** Em `includes/checkout-shortcode.php`,
   dentro do callback do shortcode `wi_checkout`, **remova** a chamada
   `$template_id = wi_checkout_create_template();` que roda quando
   `$template_id` está vazio/inválido. Nesse caso, o shortcode deve apenas
   retornar `''` (sem criar nada). `register_activation_hook` em
   `wi-checkout-customizations.php` continua sendo o único lugar que chama
   `wi_checkout_create_template()` — não precisa mudar isso, já está correto.
4. **Admin notice: template ausente.** Adicione, em
   `includes/checkout-shortcode.php`, uma função hookada em `admin_notices` que:
   - Só roda para usuários com capability `manage_options`.
   - Verifica se `get_option( 'wi_checkout_template_post_id' )` existe e aponta
     para um post válido (`get_post()` não nulo). Se não, imprime um notice
     (classe `notice notice-error`) do tipo: "WI Checkout Customizations: o
     template interno do checkout não foi encontrado. Desative e reative o
     plugin para recriá-lo."
5. **Diferenciar Elementor free de Elementor Pro.** No callback do shortcode,
   troque o guard atual (`! did_action('elementor/loaded') ||
   ! class_exists('\Elementor\Plugin')`) por uma checagem que também falha
   quando `! class_exists('\ElementorPro\Plugin')`, retornando `''`. Adicione um
   segundo admin notice (mesma função ou uma nova, mesma capability
   `manage_options`) avisando: "WI Checkout Customizations: este plugin exige o
   Elementor Pro ativo (o layout do checkout usa o widget
   `woocommerce-checkout-page`, exclusivo do Pro)." — mostrado só quando
   `did_action('elementor/loaded')` for true mas `\ElementorPro\Plugin` não
   existir (evita notice duplicado/confuso quando nem o Elementor free está
   ativo, cenário já coberto pelo aviso de template ausente).
6. **Declarar dependências no cabeçalho do plugin.** Em
   `wi-checkout-customizations.php`, adicione ao bloco de cabeçalho (WordPress
   7.0.1 em produção suporta `Requires Plugins`, disponível desde WP 6.5):
   ```
   * Requires Plugins: woocommerce, elementor-pro
   ```
   Os slugs corretos (confirmados na instalação local) são as pastas
   `woocommerce/woocommerce.php` → slug `woocommerce`, e
   `elementor-pro/elementor-pro.php` → slug `elementor-pro`. Mantenha todas as
   checagens `function_exists`/`class_exists`/`did_action` já existentes em
   runtime — o header é uma camada adicional (evita ativação sem os plugins),
   não substitui as guardas em runtime.

## Non-goals (deste step)
- Não mexer em `assets/js/wi-checkout.js`, `assets/css/`, ou em
  `includes/checkout-i18n.php` / `includes/compat-fluid-checkout.php`.
- Não adicionar testes automatizados.

## Verificação manual (checklist)
Rodar no ambiente XAMPP local (ver seção acima):
1. Com os dois arquivos de asset temporariamente renomeados (simulando ausência),
   confirmar em `wp-content/debug.log` que aparece só a mensagem de log nova, sem
   PHP warning/fatal. Renomear de volta depois.
2. Com o plugin ativo e o template presente (estado normal), visitar uma página
   com `[wi_checkout]` e confirmar que o layout renderiza normalmente (nenhuma
   regressão).
3. Simular template ausente: apagar a opção `wi_checkout_template_post_id`
   (via `wp_options` no phpMyAdmin/mysql client) ou apontar para um post_id
   inexistente. Visitar a página com `[wi_checkout]`: deve renderizar vazio, sem
   criar nenhum post novo (confirmar em **Elementor → Meus Templates** que
   nenhum "WI Checkout Layout" novo foi criado). Entrar no wp-admin e confirmar
   que o notice de template ausente aparece.
4. Desativar e reativar o plugin (com a opção ainda incorreta): confirmar que o
   template é recriado corretamente (comportamento igual ao atual) e o notice
   some.
5. Se possível, desativar temporariamente o Elementor Pro (mantendo o Elementor
   free ativo) e confirmar que aparece o notice de "exige Elementor Pro" e o
   shortcode não tenta renderizar nada quebrado. Reativar o Elementor Pro depois.
6. Confirmar que o cabeçalho do plugin (`Plugin Name`, `Requires Plugins`, etc.)
   aparece corretamente em **Plugins** no wp-admin, sem erro de sintaxe no
   cabeçalho.

## Commit
Ao final, um commit único cobrindo este step:
`fix: harden checkout template creation, file guards and dependency checks`
