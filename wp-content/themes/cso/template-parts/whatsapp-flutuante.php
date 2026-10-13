<?php
/**
 * Botão flutuante de WhatsApp.
 *
 * Só é impresso quando há número cadastrado em Opções do Site — na v2 o link
 * ficava hardcoded no footer e virava um href vazio quando o campo faltava.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$url = aw_whatsapp_url('api', sprintf(
	/* translators: %s: nome do site. */
	__('Olá! Vim pelo site da %s e gostaria de mais informações.', 'alfama-web'),
	get_bloginfo('name')
));

if (!$url) {
	return;
}
?>
<a class="aw-whatsapp" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
	<span class="sr-only"><?php esc_html_e('Falar no WhatsApp', 'alfama-web'); ?></span>
	<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
		<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.06c-.25.69-1.45 1.32-2 1.37-.55.05-1.06.24-3.57-.99-2.51-1.23-4.05-3.9-4.18-4.08-.13-.19-.99-1.35-.95-2.55.04-1.2.68-1.77.92-2.02.24-.25.52-.31.7-.31.18 0 .37 0 .53.01.17.01.4-.06.62.48.22.54.75 1.87.82 2 .06.14.11.3.02.48-.09.19-.17.3-.33.48-.16.18-.35.4-.5.54-.16.14-.33.3-.15.6.18.31.8 1.36 1.72 2.19 1.18 1.07 2.19 1.38 2.5 1.53.31.15.5.13.68-.07.19-.2.79-.9 1-1.21.21-.31.42-.25.7-.15.28.1 1.79.86 2.1 1.02.31.15.51.23.59.36.07.13.07.79-.18 1.48Z"/>
	</svg>
</a>
