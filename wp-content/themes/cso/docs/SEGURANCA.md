# Segurança e configuração

## Nada de segredo no tema

O tema **não contém nenhuma credencial**, nem vazia como placeholder. Todas as
chaves vivem no `wp-config.php` do servidor, que não é versionado.

Se uma constante não existir, o recurso correspondente é desligado com
degradação limpa e um aviso no admin — nunca um fatal error e nunca um envio
silenciosamente quebrado.

## Constantes do `wp-config.php`

Cole antes da linha `/* That's all, stop editing! */`:

```php
/* Ambiente ------------------------------------------------------------- */
define( 'WP_ENVIRONMENT_TYPE', 'production' );  // production | staging | development | local
// define( 'AW_ENV', 'staging' );               // sobrepõe a detecção, se precisar

/* SMTP ----------------------------------------------------------------- */
define( 'AW_SMTP_HOST',      'smtp.exemplo.com' );
define( 'AW_SMTP_PORT',      587 );
define( 'AW_SMTP_SECURE',    'tls' );           // tls | ssl
define( 'AW_SMTP_USER',      'naoresponder@cliente.com.br' );
define( 'AW_SMTP_PASS',      '<<senha-de-app>>' );
define( 'AW_MAIL_FROM',      'naoresponder@cliente.com.br' );
define( 'AW_MAIL_FROM_NAME', 'Nome do Cliente' );

/* reCAPTCHA v3 (opcional) ---------------------------------------------- */
define( 'AW_RECAPTCHA_SITE_KEY',   '<<site-key>>' );
define( 'AW_RECAPTCHA_SECRET_KEY', '<<secret-key>>' );

/* Scaffolding ---------------------------------------------------------- */
// define( 'AW_SCAFFOLDING', false );           // desliga a geração de arquivos
```

Sem `AW_RECAPTCHA_*`, o formulário continua protegido por nonce e honeypot.
Sem `AW_SMTP_*`, o envio cai no `mail()` do servidor (e o admin avisa).

## Ao migrar um site da v2

O `functions.php` de temas da v2 pode conter senha de SMTP e a *secret key* do
reCAPTCHA como `define()` literais. Na migração:

1. **Rotacione as credenciais** antes de qualquer coisa — elas estão no
   histórico do git e devem ser consideradas comprometidas.
2. Mova as novas para o `wp-config.php` com os nomes `AW_*` acima.
3. Se o repositório for público ou compartilhado, reescreva o histórico
   (`git filter-repo`) ou trate o repositório antigo como queimado.

O mesmo vale para tokens de CRM embutidos em plugins (o `cv-listener` trazia
e-mail e token do CVCRM no código).

## Checagens que o tema faz por você

| Superfície | Proteção |
| --- | --- |
| Endpoints AJAX (inclusive `nopriv`) | `check_ajax_referer` antes de qualquer leitura de `$_POST`, em `AW_Ajax_Controller::despachar()` |
| Formulários | nonce por formulário, honeypot, reCAPTCHA v3 opcional, campos não declarados são descartados |
| Uploads | extensão conferida por `wp_check_filetype_and_ext()`, teto de 10 MB no total, arquivos temporários removidos após o envio |
| Redirect pós-envio | `wp_validate_redirect()` + `wp_safe_redirect()` |
| Saída | `esc_html`, `esc_attr`, `esc_url` em todo template; `wp_kses` no SVG inline e no HTML de campo rico |
| SVG inline | `aw_svg()` só lê de `assets/img/` e recusa caminho que escape da pasta |
| Escrita em arquivo | apenas o scaffolding, apenas fora de produção, com checagem de permissão |
| `<head>` | versão do WordPress, RSD, WLW e shortlink removidos |

## O que o tema deliberadamente **não** faz

Não é papel do tema, e tentar fazer aqui atrapalha o projeto:

- backup (UpdraftPlus), WAF ou hardening de login (plugin da hospedagem);
- cache de página (plugin ou CDN);
- SEO e Open Graph (Rank Math ou Yoast);
- consentimento de cookies e LGPD (plugin dedicado).
