# acf-json

Sincronia local do Advanced Custom Fields.

`inc/acf.php` aponta os *save* e *load points* do ACF para esta pasta. Isso
significa que:

- todo grupo de campos criado ou editado na UI do ACF vira um arquivo `.json`
  aqui, que **entra no commit**;
- em outra máquina (ou em outro ambiente), o ACF mostra os grupos como
  "disponíveis para sincronizar" em **Custom Fields → Field Groups → Sync**;
- clonar o repositório passa a ser suficiente para ter a modelagem — não é mais
  preciso restaurar dump de banco.

## O que NÃO vem para cá

Post types e taxonomias são registrados em PHP, em `inc/post-types.php`. Foi
uma escolha: estrutura é código, conteúdo editável é dado. Se preferir criá-los
pela UI do ACF, os JSON também cairão nesta pasta — mas então mantenha uma
única fonte de verdade e remova o registro em PHP, para não haver conflito.

## Grupos do tema

| Arquivo | Onde aparece |
| --- | --- |
| `group_cso_home.json` | Página com slug `home` |
| `group_cso_quem_somos.json` | Página `quem-somos` |
| `group_cso_blog.json` | Página `blog` |
| `group_cso_empreendimentos.json` | Página `empreendimentos` |
| `group_cso_contato.json` | Página `contato` |
| `group_cso_politica.json` | Página `politica-de-privacidade` (ou a escolhida em Configurações › Privacidade) |
| `group_cso_empreendimento.json` | Post type Empreendimento |
| `group_aw_opcoes.json` | Opções do Site |
| `group_aw_hero.json` | Demais páginas (page.php, contato…) |

Os grupos de página usam a regra **Página do tema (slug)** (`aw_pagina`),
registrada em `inc/acf.php`: vale em qualquer ambiente, sem depender do ID.
Cada grupo é dividido em abas, uma por seção do layout. Campo vazio mostra o
conteúdo do Figma (fallback de `aw_field()`).

Os grupos já funcionam direto dos arquivos. Para editá-los na UI, use
**Sync** uma vez; depois disso cada alteração salva regrava o `.json`.
