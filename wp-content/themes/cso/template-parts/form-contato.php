<?php
/**
 * Formulário de contato (Figma: "contato" — card "Preencha as informações
 * abaixo"): nome, telefone, e-mail e mensagem em duas colunas, aceite e botão.
 *
 * Todos os campos de segurança vêm de AW_Form_Dispatcher::campos_ocultos():
 * nonce, id do formulário, URL de retorno, honeypot e slot do reCAPTCHA. O
 * handler é inc/Forms/handlers/contato.php. Envio por AJAX (data-aw-form-ajax).
 * Estilos: assets/css/style.css (.form-contato, .campo, .aceite).
 *
 * @param array $args {
 *     @type string $titulo         Título do card. Default: 'Preencha as informações abaixo'.
 *     @type string $empreendimento Preenche o campo oculto de interesse.
 *     @type string $botao          Rótulo do botão. Default: 'Enviar mensagem'.
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'titulo'         => __('Preencha as informações abaixo', 'alfama-web'),
	'empreendimento' => '',
	'botao'          => __('Enviar mensagem', 'alfama-web'),
));

AW_Recaptcha::enqueue();

$politica = get_privacy_policy_url() ?: aw_url_pagina('politica-de-privacidade');
$id_base  = wp_unique_id('contato-');
?>
<form class="form-contato" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-aw-form-ajax>
	<?php AW_Form_Dispatcher::campos_ocultos('contato'); ?>

	<?php if ($args['empreendimento']) : ?>
		<input type="hidden" name="empreendimento" value="<?php echo esc_attr($args['empreendimento']); ?>">
	<?php endif; ?>

	<?php if ($args['titulo']) : ?>
		<h3 class="form-contato__titulo"><?php echo esc_html($args['titulo']); ?></h3>
	<?php endif; ?>

	<div class="form-contato__grade">
		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-nome"><?php esc_html_e('Nome completo', 'alfama-web'); ?></label>
			<input type="text" id="<?php echo esc_attr($id_base); ?>-nome" name="nome" placeholder="<?php esc_attr_e('Seu nome', 'alfama-web'); ?>" autocomplete="name" required>
		</div>

		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-telefone"><?php esc_html_e('Telefone', 'alfama-web'); ?></label>
			<input type="tel" id="<?php echo esc_attr($id_base); ?>-telefone" name="telefone" placeholder="<?php esc_attr_e('Seu número', 'alfama-web'); ?>" autocomplete="tel" inputmode="tel" data-aw-mask="telefone" required>
		</div>

		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-email"><?php esc_html_e('E-mail', 'alfama-web'); ?></label>
			<input type="email" id="<?php echo esc_attr($id_base); ?>-email" name="email" placeholder="<?php esc_attr_e('Seu e-mail', 'alfama-web'); ?>" autocomplete="email" required>
		</div>

		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-mensagem"><?php esc_html_e('Mensagem', 'alfama-web'); ?></label>
			<textarea id="<?php echo esc_attr($id_base); ?>-mensagem" name="mensagem" rows="1" placeholder="<?php esc_attr_e('Digite aqui', 'alfama-web'); ?>"></textarea>
		</div>
	</div>

	<div class="aceite">
		<input type="checkbox" id="<?php echo esc_attr($id_base); ?>-lgpd" name="aceite_lgpd" value="1" required>
		<label for="<?php echo esc_attr($id_base); ?>-lgpd">
			<?php
			printf(
				/* translators: %s: link "termos e condições". */
				esc_html__('Aceito os %s', 'alfama-web'),
				sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($politica), esc_html__('termos e condições', 'alfama-web'))
			);
			?>
		</label>
	</div>

	<div class="form-contato__rodape">
		<button type="submit" class="btn btn--primario form-contato__enviar" data-aw-form-submit>
			<span data-aw-form-label><?php echo esc_html($args['botao']); ?></span>
			<span class="aw-spinner" hidden aria-hidden="true"></span>
		</button>

		<p class="aw-form-status" role="status" aria-live="polite" hidden></p>
	</div>
</form>
