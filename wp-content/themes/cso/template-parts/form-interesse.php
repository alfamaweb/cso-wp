<?php
/**
 * Formulário de interesse do card do empreendimento (Figma: hero da
 * "empreendimento - interna" — "Preencha seus dados").
 *
 * Usa o formulário "contato" do dispatcher (inc/Forms/handlers/contato.php):
 * nome, telefone, e-mail, aceite e o empreendimento em campo oculto. Envio por
 * AJAX, com nonce, honeypot e reCAPTCHA de AW_Form_Dispatcher::campos_ocultos().
 * Estilos: assets/css/empreendimento.css (.emp-ficha) + style.css (.campo, .aceite).
 *
 * @param array $args {
 *     @type string $empreendimento Nome do empreendimento (vai no e-mail).
 *     @type string $titulo         Default: 'Preencha seus dados'.
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'empreendimento' => '',
	'titulo'         => __('Preencha seus dados', 'alfama-web'),
));

AW_Recaptcha::enqueue();

$politica = get_privacy_policy_url() ?: aw_url_pagina('politica-de-privacidade');
$id_base  = wp_unique_id('interesse-');
?>
<form class="emp-ficha__form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-aw-form-ajax>
	<?php AW_Form_Dispatcher::campos_ocultos('contato'); ?>

	<?php if ($args['empreendimento']) : ?>
		<input type="hidden" name="empreendimento" value="<?php echo esc_attr($args['empreendimento']); ?>">
		<input type="hidden" name="assunto" value="<?php echo esc_attr(sprintf(__('Interesse: %s', 'alfama-web'), $args['empreendimento'])); ?>">
	<?php endif; ?>

	<p class="emp-ficha__titulo"><?php echo esc_html($args['titulo']); ?></p>

	<div class="emp-ficha__campos">
		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-nome"><?php esc_html_e('Nome completo', 'alfama-web'); ?></label>
			<input type="text" id="<?php echo esc_attr($id_base); ?>-nome" name="nome" placeholder="<?php esc_attr_e('Seu nome', 'alfama-web'); ?>" autocomplete="name" required>
		</div>

		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-telefone"><?php esc_html_e('Telefone', 'alfama-web'); ?></label>
			<input type="tel" id="<?php echo esc_attr($id_base); ?>-telefone" name="telefone" placeholder="<?php esc_attr_e('(75) 99999-9999', 'alfama-web'); ?>" autocomplete="tel" inputmode="tel" data-aw-mask="telefone" required>
		</div>

		<div class="campo">
			<label for="<?php echo esc_attr($id_base); ?>-email"><?php esc_html_e('E-mail', 'alfama-web'); ?></label>
			<input type="email" id="<?php echo esc_attr($id_base); ?>-email" name="email" placeholder="<?php esc_attr_e('Seu e-mail', 'alfama-web'); ?>" autocomplete="email" required>
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
	</div>

	<button type="submit" class="btn btn--primario emp-ficha__enviar" data-aw-form-submit>
		<span data-aw-form-label><?php esc_html_e('Enviar formulário de contato', 'alfama-web'); ?></span>
		<span class="aw-spinner" hidden aria-hidden="true"></span>
	</button>

	<p class="aw-form-status" role="status" aria-live="polite" hidden></p>
</form>
