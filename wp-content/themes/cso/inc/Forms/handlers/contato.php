<?php
/**
 * Formulário de contato — handler de referência.
 *
 * Copie este arquivo para criar um novo formulário: mude o id no registrar(),
 * declare os campos e escreva o corpo do e-mail. Nada mais precisa ser tocado
 * (nonce, honeypot, reCAPTCHA, anexos, redirect e feedback são do dispatcher).
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

add_action('init', 'aw_registrar_form_contato');
function aw_registrar_form_contato()
{
	AW_Form_Dispatcher::registrar('contato', array(
		'handler' => 'aw_handler_contato',
		'campos'  => array(
			'nome'          => __('Nome', 'alfama-web'),
			'email'         => __('E-mail', 'alfama-web'),
			'telefone'      => __('Telefone', 'alfama-web'),
			'assunto'       => __('Assunto', 'alfama-web'),
			'empreendimento' => __('Empreendimento de interesse', 'alfama-web'),
			'mensagem'      => __('Mensagem', 'alfama-web'),
			'aceite_lgpd'   => __('Aceite da política de privacidade', 'alfama-web'),
		),
		'obrigatorios' => array(
			'nome'        => __('Nome', 'alfama-web'),
			'email'       => __('E-mail', 'alfama-web'),
			'telefone'    => __('Telefone', 'alfama-web'),
			'aceite_lgpd' => __('Aceite da política de privacidade', 'alfama-web'),
		),
		'anexos' => false,
	));
}

/**
 * @param array    $dados  Campos já higienizados pelo dispatcher.
 * @param string[] $anexos Caminhos temporários (vazio neste formulário).
 * @return bool|WP_Error
 */
function aw_handler_contato($dados, $anexos)
{
	if (!is_email($dados['email'])) {
		return new WP_Error('aw_email', __('O e-mail informado não parece válido.', 'alfama-web'));
	}

	$corpo = aw_email_shell(
		__('Novo contato pelo site', 'alfama-web'),
		aw_email_secao(__('Dados do contato', 'alfama-web'))
		. aw_email_tabela(array(
			__('Nome', 'alfama-web')          => $dados['nome'],
			__('E-mail', 'alfama-web')        => $dados['email'],
			__('Telefone', 'alfama-web')      => $dados['telefone'],
			__('Assunto', 'alfama-web')       => $dados['assunto'],
			__('Empreendimento', 'alfama-web') => $dados['empreendimento'],
		))
		. aw_email_secao(__('Mensagem', 'alfama-web'))
		. aw_email_tabela(array(
			__('Mensagem', 'alfama-web') => $dados['mensagem'],
		))
	);

	$assunto = sprintf(
		/* translators: 1: nome do site, 2: nome de quem enviou. */
		__('[%1$s] Contato de %2$s', 'alfama-web'),
		get_bloginfo('name'),
		$dados['nome']
	);

	return AW_Mailer::enviar(
		AW_Mailer::destinatario(),
		$assunto,
		$corpo,
		$anexos,
		$dados['email']
	);
}
