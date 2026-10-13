<?php
/**
 * "Shell" visual dos e-mails de formulário.
 *
 * Uma casca só, reaproveitada por todos os formulários do site — é o que dá ao
 * time comercial um layout uniforme entre projetos. Herdado do padrão
 * enkan_email_shell() / _linha() / _tabela_campos() da v2, agora com escape
 * em todos os valores.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Envolve o conteúdo na casca do e-mail.
 *
 * @param string $titulo   Título exibido no topo.
 * @param string $conteudo HTML interno (tipicamente aw_email_tabela()).
 * @return string
 */
function aw_email_shell($titulo, $conteudo)
{
	$site  = get_bloginfo('name');
	$cor   = apply_filters('aw_email_cor', '#1f2933');
	$data  = wp_date('d/m/Y \à\s H:i');

	return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
		. '<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ec;">'
		. '<tr><td style="background:' . esc_attr($cor) . ';padding:20px 24px;">'
		. '<p style="margin:0;color:#ffffff;font-size:12px;letter-spacing:.08em;text-transform:uppercase;">' . esc_html($site) . '</p>'
		. '<h1 style="margin:6px 0 0;color:#ffffff;font-size:20px;font-weight:700;">' . esc_html($titulo) . '</h1>'
		. '</td></tr>'
		. '<tr><td style="padding:24px;">' . $conteudo . '</td></tr>'
		. '<tr><td style="padding:16px 24px;border-top:1px solid #e3e6ec;color:#626b7b;font-size:12px;">'
		. esc_html(sprintf(__('Enviado pelo site em %s.', 'alfama-web'), $data))
		. '</td></tr></table></body></html>';
}

/**
 * Tabela de rótulo e valor, com linhas zebradas.
 *
 * @param array<string,mixed> $campos Rótulo => valor. Valores vazios são omitidos.
 * @return string
 */
function aw_email_tabela(array $campos)
{
	$linhas = '';
	$i      = 0;

	foreach ($campos as $rotulo => $valor) {
		if (is_array($valor)) {
			$valor = implode(', ', array_filter(array_map('strval', $valor)));
		}

		$valor = trim((string) $valor);

		if ('' === $valor) {
			continue;
		}

		$fundo   = ($i % 2 === 0) ? '#fafbfc' : '#ffffff';
		$linhas .= '<tr style="background:' . $fundo . ';">'
			. '<th align="left" style="padding:10px 12px;border-bottom:1px solid #eef0f4;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#626b7b;width:38%;vertical-align:top;">'
			. esc_html($rotulo) . '</th>'
			. '<td style="padding:10px 12px;border-bottom:1px solid #eef0f4;font-size:14px;color:#1f2933;">'
			. nl2br(esc_html($valor)) . '</td></tr>';

		$i++;
	}

	if (!$linhas) {
		return '<p style="margin:0;color:#626b7b;">' . esc_html__('Nenhum campo preenchido.', 'alfama-web') . '</p>';
	}

	return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $linhas . '</table>';
}

/**
 * Título de seção dentro do corpo do e-mail.
 *
 * @param string $texto
 * @return string
 */
function aw_email_secao($texto)
{
	return '<h2 style="margin:20px 0 8px;font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#626b7b;">'
		. esc_html($texto) . '</h2>';
}
