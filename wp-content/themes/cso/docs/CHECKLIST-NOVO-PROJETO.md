# Checklist de projeto novo

Do clone ao go-live. Marque conforme avança — é o roteiro que a v2 tinha só na
cabeça de quem já havia feito antes (e que sobrou como o comentário
`<!-- INSERIR AQUI CONFORME CHECKLIST -->` esquecido no `header.php`).

## 1. Ambiente

- [ ] Site criado no Local WP, tema copiado para `wp-content/themes/{cliente}`
- [ ] `npm install` e `npm run build` rodados no diretório do tema
- [ ] `Theme Name` trocado em `style.css` (text domain segue `alfama-web`)
- [ ] `WP_ENVIRONMENT_TYPE` definido no `wp-config.php`
- [ ] Repositório git iniciado — confira que `node_modules/` está ignorado

## 2. Plugins

Kit padrão:

- [ ] Advanced Custom Fields **PRO** (obrigatório)
- [ ] Classic Editor
- [ ] SVG Support
- [ ] Post Types Order (se o cliente for ordenar CPT à mão)
- [ ] UpdraftPlus (provisionamento, não desenvolvimento)
- [ ] Duplicador de posts com ACF (`duplikate`), se o fluxo pedir

Por demanda: plugin de SEO, cache da hospedagem, consentimento de cookies,
integração de CRM.

## 3. Identidade visual

- [ ] Tokens da seção 1 do `style.css` preenchidos com as cores do cliente
- [ ] Webfont em `assets/fonts/` e `@font-face` em `assets/css/fonts.css`
- [ ] `--aw-fonte` e `--aw-fonte-titulo` apontando para a webfont
- [ ] Logo em `assets/img/` (SVG) e enviado em **Personalizar → Identidade do site**
- [ ] Kit de favicon em `assets/img/meta/` (ver o README de lá)
- [ ] `thumbnail.jpg` 1200×630 para Open Graph

## 4. Modelagem

- [ ] `inc/post-types.php` ajustado — renomeie o CPT de referência ou remova o arquivo
- [ ] Grupos de campo criados na UI do ACF (os `.json` aparecem em `acf-json/`; **commitá-los**)
- [ ] Página "Opções do Site" preenchida: WhatsApp, telefone, e-mail, endereço, redes
- [ ] Menus criados e atribuídos: `primary`, `rodape`, `legal`

## 5. Páginas

- [ ] Home criada e definida em **Leitura → Página inicial**
- [ ] Página de contato criada com o slug `contato`
- [ ] Página `politica-de-privacidade` criada (o formulário linka para ela)
- [ ] Demais páginas publicadas — o scaffolding gera `page-{slug}.php` e o CSS
- [ ] Templates órfãos removidos (o aviso do scaffolding os lista)

## 6. Formulários

- [ ] `AW_SMTP_*` no `wp-config.php` e **um envio de teste real** feito
- [ ] `AW_RECAPTCHA_*` no `wp-config.php`, se o cliente tiver conta
- [ ] `email_contato` preenchido em Opções do Site
- [ ] Handler próprio criado para cada formulário além do contato
- [ ] Envio testado com JS desabilitado (o fluxo sem JS precisa funcionar)

## 7. Antes de subir

- [ ] `npm run build` rodado e `assets/css/tailwind.css` commitado
- [ ] Nenhum `error_log`, `var_dump` ou `console.log` sobrando
- [ ] Nenhuma credencial no código (`git grep -iE "senha|password|secret|token"`)
- [ ] Lighthouse ≥ 90 em Performance e Acessibilidade na home e numa interna
- [ ] Navegação por teclado: skip link, foco visível, menu mobile fechando no Esc
- [ ] 404, busca e paginação testados
- [ ] Imagens grandes otimizadas (nenhum JPG de 1 MB em `assets/img/`)

## 8. Go-live

- [ ] `WP_ENVIRONMENT_TYPE` = `production` no servidor
- [ ] Selo da admin bar mostrando "✅ Produção — indexado"
- [ ] **Leitura → Visibilidade** confirmando que o site é indexável
- [ ] `AW_SCAFFOLDING` desligado, ou tema sem permissão de escrita
- [ ] Backup configurado e primeira execução verificada
- [ ] Sitemap enviado ao Search Console
