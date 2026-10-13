# Changelog

## 3.0.0 — 2026-09-02

Primeira versão. Reescrita do boilerplate **Alfama WEB v2** ("None Plate"), a
partir do levantamento dos seis projetos em `espelho-tema/` (cassind,
condominial, enkan, kraft, stanza, nova-bairros).

### Mantido da v2

Constantes de caminho, um CSS por página, scaffolding na publicação,
desindexação de staging por convenção de domínio, ACF como camada de conteúdo
com página de Opções do Site, AJAX devolvendo HTML pronto, formulários autorais
com SMTP próprio, grade e breakpoints do Bootstrap dentro do Tailwind,
português no domínio e inglês na infraestrutura.

### Removido

- Toda a herança do Twenty Sixteen: `inc/back-compat.php`, `inc/customizer.php`,
  `inc/template-tags.php`, `genericons/`, `css/ie.css`, `css/ie7.css`,
  `css/ie8.css`, `js/html5.js`, `languages/twentysixteen.pot` e o corpo de
  `twentysixteen_scripts()`
- Bootstrap 5 (o CSS de 232 KB e o JS de 80 KB)
- jQuery 1.11.1
- WOW.js e `animate.min.css`
- Overlay `.loading`, que atrasava o primeiro paint

### Corrigido

- Nonce obrigatório em todo endpoint AJAX, inclusive `nopriv`
- Nenhuma credencial no tema; SMTP e reCAPTCHA por constantes de `wp-config.php`
- `blog_public` filtrado na leitura, sem escrita em option a cada request
- Scaffolding restrito a ambiente de desenvolvimento, com checagem de permissão
  e desligável por `AW_SCAFFOLDING`
- Cache de listagem invalidado em `save_post`, `deleted_post` e `edited_term`
- Seed de termos executado uma vez, com flag em option
- Modelagem versionada: CPTs em PHP e sincronia `acf-json/` ligada
- Uploads validados por extensão real, com teto de 10 MB e limpeza dos temporários
- Redirect pós-envio passando por `wp_validate_redirect()`

### Adicionado

- `functions.php` como bootstrap, com um módulo por responsabilidade em `inc/`
- `AW_Query_Service`, `AW_Ajax_Controller`, `AW_Form_Dispatcher`, `AW_Mailer`,
  `AW_Recaptcha`
- `aw_paginacao_html()` — uma implementação de paginação, usada no SSR e no AJAX
- `app.js` em vanilla ES2018: menu, swiper por data-attribute, filtro com
  debounce e `AbortController`, cascata de taxonomia, formulário por AJAX,
  máscara de telefone e toast
- Acessibilidade: skip link, `aria-*` no menu, foco visível,
  `prefers-reduced-motion`, `aria-live` no contador de resultados
- `.gitignore`, `.editorconfig`, `docs/` e caso de referência completo
