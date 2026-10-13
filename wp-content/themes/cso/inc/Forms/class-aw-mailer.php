<?php
/**
 * Transporte de e-mail: SMTP a partir de constantes definidas FORA do tema.
 *
 * DÍVIDA RESOLVIDA (v2): senha de app SMTP e chaves de reCAPTCHA estavam como
 * define() literais dentro do functions.php de um tema versionado em git.
 * Aqui o tema não carrega nenhum segredo: se as constantes não existirem em
 * wp-config.php, o SMTP simplesmente não é configurado e o admin recebe um
 * aviso. Nada de credencial de exemplo, nem placeholder que vire produção.
 *
 * Constantes esperadas em wp-config.php (ver docs/SEGURANCA.md):
 *   AW_SMTP_HOST, AW_SMTP_PORT, AW_SMTP_USER, AW_SMTP_PASS,
 *   AW_SMTP_SECURE ('tls' | 'ssl'), AW_MAIL_FROM, AW_MAIL_FROM_NAME
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

class AW_Mailer
{
	/**
	 * @return void
	 */
	public static function init()
	{
		add_action('phpmailer_init', array(__CLASS__, 'configurar'));
		add_filter('wp_mail_from', array(__CLASS__, 'from'));
		add_filter('wp_mail_from_name', array(__CLASS__, 'from_name'));
		add_action('admin_notices', array(__CLASS__, 'aviso_sem_smtp'));
		add_action('wp_mail_failed', array(__CLASS__, 'registrar_falha'));
	}

	/**
	 * @return bool Se há credenciais de SMTP disponíveis.
	 */
	public static function configurado()
	{
		return defined('AW_SMTP_HOST') && AW_SMTP_HOST
			&& defined('AW_SMTP_USER') && AW_SMTP_USER
			&& defined('AW_SMTP_PASS') && AW_SMTP_PASS;
	}

	/**
	 * Aplica as credenciais no PHPMailer do WordPress.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
	 * @return void
	 */
	public static function configurar($phpmailer)
	{
		if (!self::configurado()) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host        = AW_SMTP_HOST;
		$phpmailer->SMTPAuth    = true;
		$phpmailer->Username    = AW_SMTP_USER;
		$phpmailer->Password    = AW_SMTP_PASS;
		$phpmailer->Port        = defined('AW_SMTP_PORT') ? (int) AW_SMTP_PORT : 587;
		$phpmailer->SMTPSecure  = defined('AW_SMTP_SECURE') ? AW_SMTP_SECURE : 'tls';
		$phpmailer->CharSet     = 'UTF-8';
		$phpmailer->Encoding    = 'base64';

		$phpmailer->setFrom(self::from(), self::from_name(), false);
	}

	/**
	 * @param string $email
	 * @return string
	 */
	public static function from($email = '')
	{
		if (defined('AW_MAIL_FROM') && AW_MAIL_FROM) {
			return AW_MAIL_FROM;
		}

		if (defined('AW_SMTP_USER') && AW_SMTP_USER) {
			return AW_SMTP_USER;
		}

		return $email ?: get_option('admin_email');
	}

	/**
	 * @param string $nome
	 * @return string
	 */
	public static function from_name($nome = '')
	{
		if (defined('AW_MAIL_FROM_NAME') && AW_MAIL_FROM_NAME) {
			return AW_MAIL_FROM_NAME;
		}

		return $nome ?: get_bloginfo('name');
	}

	/**
	 * Destinatário padrão dos formulários do site.
	 *
	 * @return string
	 */
	public static function destinatario()
	{
		$email = aw_field('email_contato', 'option', '');
		$email = is_email($email) ? $email : get_option('admin_email');

		return (string) apply_filters('aw_form_destinatario', $email);
	}

	/**
	 * Envia um e-mail HTML.
	 *
	 * @param string   $para
	 * @param string   $assunto
	 * @param string   $corpo_html
	 * @param string[] $anexos
	 * @param string   $responder_para Endereço para Reply-To, opcional.
	 * @return bool
	 */
	public static function enviar($para, $assunto, $corpo_html, $anexos = array(), $responder_para = '')
	{
		$headers = array('Content-Type: text/html; charset=UTF-8');

		if ($responder_para && is_email($responder_para)) {
			$headers[] = 'Reply-To: ' . $responder_para;
		}

		return wp_mail($para, $assunto, $corpo_html, $headers, $anexos);
	}

	/**
	 * @param WP_Error $erro
	 * @return void
	 */
	public static function registrar_falha($erro)
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('[aw] falha no envio de e-mail: ' . $erro->get_error_message());
		}
	}

	/**
	 * @return void
	 */
	public static function aviso_sem_smtp()
	{
		if (self::configurado() || !current_user_can('manage_options')) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__('Alfama WEB v3 — SMTP não configurado.', 'alfama-web'),
			esc_html__('Os formulários usarão o mail() do servidor, que costuma cair em spam. Defina AW_SMTP_HOST, AW_SMTP_USER e AW_SMTP_PASS no wp-config.php (ver docs/SEGURANCA.md).', 'alfama-web')
		);
	}
}
