<?php
/**
 * reCAPTCHA v3 — opcional e sem chave alguma no tema.
 *
 * Constantes esperadas em wp-config.php:
 *   AW_RECAPTCHA_SITE_KEY, AW_RECAPTCHA_SECRET_KEY
 *
 * Sem elas, o reCAPTCHA fica desligado e a proteção anti-spam recai sobre o
 * honeypot e o nonce — o formulário continua funcionando.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

class AW_Recaptcha
{
	/** Nota mínima aceita na resposta do Google. */
	const SCORE_MINIMO = 0.5;

	/** @return bool */
	public static function ativo()
	{
		return defined('AW_RECAPTCHA_SITE_KEY') && AW_RECAPTCHA_SITE_KEY
			&& defined('AW_RECAPTCHA_SECRET_KEY') && AW_RECAPTCHA_SECRET_KEY;
	}

	/** @return string */
	public static function site_key()
	{
		return self::ativo() ? (string) AW_RECAPTCHA_SITE_KEY : '';
	}

	/**
	 * Enfileira o script do Google apenas quando um formulário pede.
	 *
	 * Chame de dentro do template do formulário, antes de imprimir o <form>.
	 *
	 * @return void
	 */
	public static function enqueue()
	{
		if (!self::ativo()) {
			return;
		}

		wp_enqueue_script(
			'recaptcha',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode(self::site_key()),
			array(),
			null,
			true
		);

		wp_add_inline_script('aw-app', sprintf('window.AW_RECAPTCHA_KEY = %s;', wp_json_encode(self::site_key())), 'before');
	}

	/**
	 * Valida um token no servidor.
	 *
	 * @param string $token
	 * @param string $acao Ação esperada, para conferir o campo 'action' da resposta.
	 * @return bool Verdadeiro quando desligado (não bloqueia o envio) ou quando o token passa.
	 */
	public static function verificar($token, $acao = '')
	{
		if (!self::ativo()) {
			return true;
		}

		if (!$token) {
			return false;
		}

		$resposta = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => AW_RECAPTCHA_SECRET_KEY,
				'response' => $token,
				'remoteip' => self::ip(),
			),
		));

		if (is_wp_error($resposta)) {
			// Falha de rede não deve derrubar um lead legítimo: registra e libera.
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[aw] reCAPTCHA inacessível: ' . $resposta->get_error_message());
			}

			return true;
		}

		$dados = json_decode(wp_remote_retrieve_body($resposta), true);

		if (empty($dados['success'])) {
			return false;
		}

		if ($acao && !empty($dados['action']) && $dados['action'] !== $acao) {
			return false;
		}

		return isset($dados['score']) ? (float) $dados['score'] >= self::SCORE_MINIMO : true;
	}

	/**
	 * IP do visitante, sem confiar cegamente em headers de proxy.
	 *
	 * @return string
	 */
	protected static function ip()
	{
		$ip = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '';

		return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
	}
}
