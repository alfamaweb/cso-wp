# Alfama WEB v3

Starter theme WordPress da Alfamaweb. Sucessor do **Alfama WEB v2** (o
boilerplate internamente chamado *None Plate*), reescrito para manter as
convenções que funcionavam e eliminar as dívidas técnicas que se replicavam a
cada novo projeto por cópia do tema anterior.

- **Text domain:** `alfama-web`
- **Prefixo:** `aw_` / `AW_`
- **Requisitos:** WordPress 6.4+, PHP 8.0+, Advanced Custom Fields PRO
- **Build de CSS:** Tailwind v4 via `@tailwindcss/cli` (sem bundler, sem PostCSS)

---

## Começando um projeto

```bash
# 1. Copie o tema e renomeie a pasta com o nome do projeto
cp -r alfama-web-v3 /caminho/do/site/wp-content/themes/cliente

# 2. Instale as dependências de build e compile o CSS
cd /caminho/do/site/wp-content/themes/cliente
npm install
npm run build      # ou `npm run dev` para watch durante o desenvolvimento

# 3. Ative o tema e siga docs/CHECKLIST-NOVO-PROJETO.md
```

O `Theme Name` em `style.css` pode ser trocado pelo nome do cliente; **mantenha
o text domain `alfama-web`** para não invalidar as strings já traduzidas.

---

## Estrutura

```
functions.php            Só define constantes e carrega inc/. Sem lógica.
style.css                Cabeçalho do tema + tokens de design + reset + base.
src/input.css            Entrada do Tailwind (breakpoints e container do Bootstrap).

inc/
├── helpers.php           aw_field, aw_svg, aw_whatsapp_url, aw_resumo, aw_video_data
├── environment.php       aw_env(), noindex fora de produção, selo na admin bar
├── setup.php             theme supports, menus, tamanhos de imagem, limpeza do <head>
├── assets.php            registro e enqueue de CSS/JS, com dependências explícitas
├── post-types.php        CPT e taxonomias em código (caso de referência: empreendimento)
├── acf.php               sincronia acf-json, página de opções, grupos boilerplate
├── pagination.php        aw_paginacao_html(), usada no SSR e no AJAX
├── scaffolding.php       geração de template/CSS ao publicar — só em desenvolvimento
├── Services/             AW_Query_Service — args, cache e render das listagens
├── Ajax/                 AW_Ajax_Controller — ponto único, nonce obrigatório
└── Forms/                AW_Mailer, AW_Recaptcha, AW_Form_Dispatcher, handlers/

template-parts/          Componentes com contrato $args documentado no topo
assets/css/              style.css global + um arquivo por página/contexto
assets/js/app.js         Único JS autoral, vanilla, sem jQuery
acf-json/                Grupos de campo versionados (sincronia do ACF)
docs/                    Arquitetura, checklist de projeto novo e segurança
```

---

## As convenções que continuam da v2

Estas são deliberadas: a equipe já pensa assim, e mudá-las não traria ganho.

| Convenção | Onde |
| --- | --- |
| Constantes de caminho no topo do `functions.php` | `AW_URI`, `AW_IMG`, `AW_CSS`, `AW_JS` (com aliases `THEME_URI`, `IMG_URI`… para portar templates da v2) |
| Um CSS por página: `page-{slug}.php` ↔ `assets/css/{slug}.css` | `inc/assets.php` |
| Geração automática de template e CSS ao publicar | `inc/scaffolding.php` |
| Staging desindexado por convenção de domínio | `inc/environment.php` |
| ACF como camada de conteúdo, com página "Opções do Site" | `inc/acf.php` |
| AJAX devolvendo HTML pronto, renderizado pelo mesmo template part do SSR | `AW_Query_Service::render()` |
| Formulários autorais com SMTP próprio, um handler por formulário | `inc/Forms/` |
| Grade e breakpoints do Bootstrap dentro do Tailwind | `src/input.css` |
| Português no domínio, inglês na infraestrutura | todo o código |

---

## O que mudou, e por quê

| v2 | v3 | Motivo |
| --- | --- | --- |
| Fork do Twenty Sixteen (`inc/back-compat.php`, `customizer.php`, genericons, `css/ie*.css`, `js/html5.js`, `twentysixteen.pot`) | Nada disso existe | Eram ~400 KB de assets e 40 KB de PHP carregados sem uso em toda página |
| Bootstrap 5 completo (232 KB de CSS) | Só Tailwind v4, com a grade do Bootstrap reproduzida em `@theme` | A grade era o que a equipe usava; o resto do framework, não |
| jQuery 1.11.1 (2014) via `<script>` no header | Sem jQuery; `app.js` em vanilla ES2018 | Versão de 10 anos, fora do sistema de dependências do WP, duplicada por qualquer plugin |
| Libs como `<link>`/`<script>` literais em `header.php`/`footer.php`, comentadas quando não usadas | Registradas em `inc/assets.php`, enfileiradas por contexto ou por `aw_enqueue_lib()` | Ordem, versão e dependência sob controle; nada carregado à toa |
| `functions.php` de 1.000 a 1.500 linhas | `functions.php` só com `define` e `require`; um módulo por responsabilidade em `inc/` | O monolito era a causa da divergência entre projetos |
| Endpoints `wp_ajax_nopriv_*` lendo `$_POST` sem nonce | `AW_Ajax_Controller` valida nonce antes de despachar, sempre | Endpoint público sem verificação é convite a abuso |
| `echo json_encode(...); wp_die();` | `wp_send_json_success()` / `wp_send_json_error()` | Content-type e código de status corretos |
| SMTP e reCAPTCHA com senha e secret em `define()` no tema versionado | Constantes lidas de `wp-config.php`; sem elas, degrada e avisa | Segredo em git é incidente, não configuração |
| `update_option('blog_public', …)` em `init`, a cada request | Filtro `pre_option_blog_public` + `wp_robots` | Mesmo efeito, zero escrita no banco |
| Scaffolding rodando em produção, exigindo tema gravável | Só fora de produção, com checagem de permissão e desligável por constante | Tema gravável em produção é superfície de ataque |
| Cache de listagem de 5 min sem invalidação | `AW_Query_Service` invalida em `save_post`, `deleted_post` e `edited_term` | O editor publicava e não via a mudança |
| Modelagem só no banco (nenhum `acf-json/`) | CPTs em PHP + sincronia `acf-json/` ligada | Clonar o repositório passa a ser suficiente |
| CPT registrado pela UI do ACF | `register_post_type()` em `inc/post-types.php` | Estrutura é código; ver `acf-json/README.md` se preferir o caminho antigo |
| Seed de termos rodando em `wp_loaded` a cada request | Uma vez, com flag em option | Dezenas de `term_exists()` por request |
| Feedback de formulário por `<script>` inline + SweetAlert2 em toda página | Bloco HTML que funciona sem JS, virando toast quando há JS | Menos JS, e funciona com JS desabilitado |
| Paginação remontada à mão em cada tema | `aw_paginacao_html()`, uma implementação | Era a função que mais divergia entre projetos |
| Sem `.gitignore` — `node_modules` versionado | `.gitignore` e `.editorconfig` | Repositório de 200 MB por projeto |
| Overlay `.loading` bloqueando o primeiro paint | Removido | Atrasava o conteúdo e deixava a tela branca se o JS falhasse |
| Sem skip link, sem `aria-*` no menu, sem `prefers-reduced-motion` | Todos presentes | Acessibilidade básica |

---

## Caso de referência incluído

Para não deixar cada projeto reinventar o filtro e o formulário — que é
exatamente onde os seis temas da v2 divergiram — o tema vem com um caso
completo, de ponta a ponta:

- **CPT `empreendimento`** com as taxonomias `localizacao` (hierárquica, para a
  cascata cidade → bairro) e `status_obra`;
- **`page-empreendimentos.php`** — busca com debounce de 400 ms, filtros por
  taxonomia, cascata, paginação assíncrona, skeletons e fallback de resultado
  zero. Primeira carga server-side, o resto por AJAX;
- **`single-empreendimento.php`** — ficha, diferenciais, galeria com Fancybox,
  vídeo por oEmbed e formulário de interesse na sidebar;
- **`template-parts/card-empreendimento.php`** — o mesmo card no SSR e no AJAX;
- **`inc/Forms/handlers/contato.php`** — formulário completo em ~70 linhas,
  porque nonce, honeypot, reCAPTCHA, anexos, redirect e feedback são do
  dispatcher.

Em um projeto de outro ramo, remova `inc/post-types.php`, os três arquivos de
`empreendimento` e o `card-empreendimento.php` — ou renomeie tudo para o objeto
de domínio do cliente. Nada mais depende deles.

---

## Como adicionar um formulário

1. Copie `inc/Forms/handlers/contato.php` para `inc/Forms/handlers/{nome}.php`.
2. Troque o id no `AW_Form_Dispatcher::registrar()` e declare `campos` e
   `obrigatorios`.
3. Escreva o corpo do e-mail com `aw_email_shell()` + `aw_email_tabela()`.
4. Adicione o `require_once` no `functions.php`.
5. No template, `AW_Form_Dispatcher::campos_ocultos('{nome}')` dentro do `<form>`.

Segurança, anexos, redirect e feedback vêm de graça.

## Como adicionar uma listagem filtrável

1. Registre a ação em `AW_Ajax_Controller::acoes()` (ou pelo filtro
   `aw_ajax_acoes`, sem editar a classe).
2. No handler, use `AW_Query_Service::sanitize()` e instancie o service.
3. No template, monte o `<form data-aw-filtro>` com `data-action`, `data-target`
   e `data-pagination` — o `app.js` cuida do resto sem uma linha de JS nova.

---

## Documentação

- [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md) — como as camadas se encaixam e por quê
- [`docs/CHECKLIST-NOVO-PROJETO.md`](docs/CHECKLIST-NOVO-PROJETO.md) — do clone ao go-live
- [`docs/SEGURANCA.md`](docs/SEGURANCA.md) — constantes do `wp-config.php` e o que nunca versionar
