# Ícones e metadados

Coloque aqui o kit de favicon do projeto, gerado a partir do logo do cliente:

```
favicon.ico
favicon-32x32.png
favicon-16x16.png
apple-touch-icon.png        (180x180)
android-chrome-192x192.png
android-chrome-512x512.png
site.webmanifest
thumbnail.jpg               (1200x630, para Open Graph)
```

O favicon é registrado pelo WordPress em **Aparência → Personalizar → Identidade
do site**, não por `<link>` no `header.php` — foi assim que a v2 acabou com o
comentário `<!-- INSERIR AQUI CONFORME CHECKLIST -->` esquecido em produção.

As tags de Open Graph ficam a cargo do plugin de SEO do projeto (Rank Math ou
Yoast), não do tema.
