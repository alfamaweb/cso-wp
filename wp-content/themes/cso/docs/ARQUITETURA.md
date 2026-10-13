# Arquitetura

O v3 tem uma regra que organiza todo o resto: **cada arquivo tem uma
responsabilidade, e `functions.php` não é um deles.** Na v2, o `functions.php`
misturava setup de tema, enqueue, helpers, endpoints AJAX, SMTP e templates de
e-mail em 1.000 a 1.500 linhas — e foi por isso que os seis projetos
divergiram: mexer em qualquer coisa significava mexer naquele arquivo.

## Fluxo de carga

```
functions.php
  ├─ define AW_VERSION, AW_DIR, AW_URI, AW_IMG, AW_CSS, AW_JS
  │  (+ aliases THEME_URI, IMG_URI, CSS_URI, JS_URI, para portar templates da v2)
  │
  ├─ inc/helpers.php        ← sem hooks; só funções puras
  ├─ inc/environment.php    ← aw_env(); tudo abaixo pode consultá-lo
  ├─ inc/setup.php          ← after_setup_theme
  ├─ inc/assets.php         ← wp_enqueue_scripts
  ├─ inc/post-types.php     ← init
  ├─ inc/acf.php            ← acf/init
  ├─ inc/pagination.php     ← sem hooks
  ├─ inc/scaffolding.php    ← transition_post_status
  │
  ├─ inc/Services/…         ← classes; nenhum hook no arquivo
  ├─ inc/Ajax/…             ← classe + ::init() chamado no fim
  └─ inc/Forms/…            ← classes + handlers

  AW_Ajax_Controller::init();
  AW_Mailer::init();
  AW_Form_Dispatcher::init();
```

A ordem importa em um ponto só: `helpers.php` e `environment.php` primeiro,
porque tudo depois deles usa `aw_field()` e `aw_is_dev()`.

## As três camadas de uma listagem

Esta é a parte que mais valeu extrair, porque é onde cada projeto da v2
escreveu a própria versão.

```
   Front (app.js)                Controller                   Service
   ─────────────────             ────────────────             ──────────────────
   form[data-aw-filtro]
     debounce 400 ms
     AbortController      ──▶   AW_Ajax_Controller
     skeletons                    · check_ajax_referer
                                  · resolve a ação      ──▶   AW_Query_Service
                                  · sanitize()                  · args()
                                                                · query()  ← cache
                                  ◀── HTML + paginação          · render() ← template part
     substitui #aw-grid    ◀───    wp_send_json_success
```

Três decisões dentro disso:

**O servidor devolve HTML, não JSON de dados.** É a convenção da casa desde a
v2 e faz sentido aqui: o card já existe como template part e é renderizado com
`ob_start()` dentro do `AW_Query_Service::render()`. O ganho é que o **mesmo
arquivo** produz o card na primeira carga (server-side, bom para SEO e para o
primeiro paint) e nas seguintes (AJAX). Na v2, o handler AJAX tinha o markup do
card duplicado dentro do `functions.php`, e os dois iam divergindo.

**O service nunca lê `$_POST`.** Quem lê é o controller, e só pelos campos que
declarou. `AW_Query_Service::sanitize()` recebe o array cru e uma lista de
taxonomias aceitas; qualquer chave fora dessa lista é descartada. Isso fecha a
porta para um parâmetro inesperado virar `tax_query`.

**O cache guarda IDs, não objetos.** `query()` monta um hash MD5 dos args e
grava em transient apenas os IDs, o total e o número de páginas — depois
reidrata com `post__in`. O transient fica pequeno e o cache de objetos do
WordPress continua no comando. E, ao contrário da v2, o cache é invalidado em
`save_post`, `deleted_post` e `edited_term`: o editor publica e vê a mudança.
Em desenvolvimento o cache é ignorado por completo.

## O dispatcher de formulários

Um `<form>`, um id, um handler. O dispatcher faz o trabalho repetido:

```
<form action="admin-post.php" method="post" data-aw-form-ajax>
  AW_Form_Dispatcher::campos_ocultos('contato')
    → action, aw_form, aw_redirect, nonce, honeypot, slot do reCAPTCHA
```

```
AW_Form_Dispatcher::executar()
  1. resolve o formulário pelo id           → não registrado? para aqui
  2. wp_verify_nonce                        → falhou? status 'expirado'
  3. honeypot preenchido                    → responde 'success' (não avisa o bot)
  4. coletar()  — só os campos declarados, com sanitize por tipo de nome
  5. obrigatórios                           → faltando? status 'obrigatorio'
  6. AW_Recaptcha::verificar()              → falhou? status 'recaptcha'
  7. processar_anexos()                     → extensão e teto de 10 MB
  8. chama o handler do formulário          → bool | WP_Error
  9. limpa os temporários
```

O mesmo `executar()` serve os dois transportes: `admin-post.php` redireciona com
`?aw_form={status}`, e `admin-ajax.php` devolve JSON. É o que permite o
formulário funcionar **sem JavaScript** e ficar melhor com ele — na v2 o
feedback dependia de um `<script>` inline com SweetAlert2 e jQuery.

Criar um formulário novo é um arquivo em `inc/Forms/handlers/`: registro,
campos e corpo do e-mail. Nada de segurança se repete.

## Assets: ordem por dependência, não por prioridade

```
aw-base       style.css da raiz — tokens + reset + base
  └─ aw-tailwind    assets/css/tailwind.css — utilitários compilados
       └─ aw-global      assets/css/style.css — componentes autorais
            └─ aw-css-{slug}   assets/css/{slug}.css — o contexto atual
```

Na v2 a ordem era resolvida com `add_action('wp_enqueue_scripts', …, 100)` para
"o Tailwind entrar por último". Aqui a cascata é declarada no array de
dependências de cada `wp_enqueue_style()`, que é o mecanismo que o WordPress
oferece para isso. Efeito colateral bom: o WordPress passa a poder concatenar e
reordenar com segurança.

`aw_slugs_de_css()` decide quais contextos pedem CSS (página, post type, home,
blog, archive, busca, 404) e `aw_enqueue_css_se_existir()` só enfileira o que
existe no disco. Versão de cada arquivo é o `filemtime` — cache-busting sem
ninguém editar número de versão.

Bibliotecas de terceiros são **registradas** sempre e **enfileiradas** só onde
fazem falta: por contexto em `aw_libs_do_contexto()`, pelo filtro
`aw_libs_automaticas` (um projeto ajusta sem editar o arquivo) ou por
`aw_enqueue_lib('swiper')` chamado de dentro de um template.

## Componentes com contrato

Todo `template-part` abre com um docblock declarando os `@param` que aceita em
`$args`, os defaults e a origem dos campos ACF. O corpo trata dado ausente com
placeholder visível (`[ imagem do hero — custom field ]`) em vez de quebrar o
layout. Foi o padrão que o tema `enkan` alcançou na v2 e é o único jeito de um
componente ser reutilizável sem que quem o chama precise ler o código inteiro.

```php
get_template_part('template-parts/hero', null, array(
    'title'    => get_the_title(),
    'text'     => $localizacao,
    'bg_image' => get_the_post_thumbnail_url($post_id, 'aw-hero'),
));
```

## Ambiente

`aw_env()` resolve, nesta ordem: a constante `AW_ENV`, o `WP_ENVIRONMENT_TYPE`
nativo, e por último a heurística de hostname da v2 (`dev.`, `staging.`,
`.local`, `localhost`).

Fora de produção: `blog_public` é filtrado em `pre_option_blog_public` (leitura,
não escrita), `wp_robots` recebe `noindex`/`nofollow`, a admin bar ganha selo e
fica vermelha, e o cache de listagem é ignorado. Nada disso grava no banco.

## Estrutura é código, conteúdo é dado

A divisão que resolve o "modelagem fora do repositório" da v2:

| O quê | Onde vive | Por quê |
| --- | --- | --- |
| Post types, taxonomias | `inc/post-types.php`, em PHP | É estrutura: precisa viajar no git e existir em qualquer ambiente |
| Grupos de campo do cliente | UI do ACF → `acf-json/`, commitado | O designer/editor mexe; a sincronia do ACF cuida do resto |
| Opções do Site, grupo Hero | `inc/acf.php`, `acf_add_local_field_group()` | É boilerplate do tema, igual em todo projeto — não deve ser editável |
| Conteúdo | banco | É conteúdo |

## Onde estender sem editar o tema

Filtros pensados para isso:

| Filtro | Serve para |
| --- | --- |
| `aw_libs_automaticas` | mudar em que contexto Swiper/Fancybox entram |
| `aw_query_args` | ajustar os args da WP_Query de qualquer listagem |
| `aw_ajax_acoes` | registrar uma ação AJAX nova sem tocar no controller |
| `aw_taxonomias_cascata` | liberar outra taxonomia para o efeito cascata |
| `aw_scaffolding_post_types` | incluir ou remover post types do scaffolding |
| `aw_form_destinatario` | trocar o destinatário dos formulários |
| `aw_email_cor` | cor da faixa do e-mail |
