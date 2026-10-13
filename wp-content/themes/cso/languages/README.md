# languages

Text domain do tema: `alfama-web`.

Todas as strings de interface já passam por `__()` / `esc_html_e()`. Para gerar
o `.pot`:

```bash
wp i18n make-pot . languages/alfama-web.pot --domain=alfama-web
```

A v2 arrastava o `twentysixteen.pot` do tema-pai em todos os projetos, sem
nunca usá-lo. Aqui a pasta começa vazia de propósito: gere o `.pot` só se o
projeto realmente for traduzido.
