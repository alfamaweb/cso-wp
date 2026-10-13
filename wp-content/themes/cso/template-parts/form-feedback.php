<?php
/**
 * Feedback de envio para o fluxo sem JS (POST clássico → ?aw_form=<status>).
 *
 * DÍVIDA RESOLVIDA (v2): o feedback vinha de um <script> inline injetado em
 * wp_footer, dependia de SweetAlert2 carregado em toda página e de jQuery para
 * escutar os eventos do CF7. Aqui é um bloco HTML que funciona sem JS; quando
 * há JS, app.js o transforma em toast e limpa a query string.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$status = isset($_GET['aw_form']) ? sanitize_key(wp_unslash($_GET['aw_form'])) : '';

if (!$status) {
	return;
}

$feedback = AW_Form_Dispatcher::feedback($status);

if (!$feedback) {
	return;
}
?>
<div class="aw-toast aw-toast--<?php echo esc_attr($feedback['tipo']); ?>"
	role="alert" data-aw-toast tabindex="-1">
	<div class="aw-toast-corpo">
		<strong><?php echo esc_html($feedback['titulo']); ?></strong>
		<p><?php echo esc_html($feedback['texto']); ?></p>
	</div>
	<button type="button" class="aw-toast-fechar" data-aw-toast-fechar>
		<span class="sr-only"><?php esc_html_e('Fechar aviso', 'alfama-web'); ?></span>
		<span aria-hidden="true">&times;</span>
	</button>
</div>
