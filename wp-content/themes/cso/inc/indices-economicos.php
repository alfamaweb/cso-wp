<?php
/**
 * Índices econômicos da top-bar (IGP-M e INCC) — atualização automática.
 *
 * Fonte: SGS do Banco Central. As duas séries são "variação % mensal":
 *   - 189 = IGP-M (FGV)
 *   - 192 = INCC (FGV)
 * O campo "valor" já vem em porcentagem ("0.22" = 0,22%) e o sinal vem no
 * próprio número: deflação aparece como "-0.73". O tema formata com sinal
 * explícito ("+0,22%" / "-0,73%") e vírgula decimal.
 *
 * Regra: a top-bar mostra sempre o valor do MÊS ANTERIOR ao atual (em outubro,
 * setembro). O WP-Cron roda uma vez por dia, mas só chama a API enquanto o mês
 * de referência ainda não foi gravado. Depois de gravado, fica parado até
 * virar o mês. Assim, um atraso na publicação ou uma queda da API se resolve
 * sozinho no dia seguinte, sem esperar mais um mês.
 *
 * Grava no repetidor indices_economicos das Opções do Site (nome, valor,
 * referencia), atualizando só as linhas IGP-M e INCC. Outras linhas ficam
 * intactas. Se uma série falhar, a outra é gravada, e a linha da série que
 * falhou mantém o valor anterior.
 *
 * Execução manual:
 *   - botão "Atualizar índices agora" em Opções do Site;
 *   - WP-CLI: wp aw-indices atualizar [--forcar]
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

const AW_INDICES_CRON   = 'aw_indices_economicos_cron';
const AW_INDICES_STATUS = 'aw_indices_status';

/**
 * Séries do SGS: nome exibido na top-bar => código da série.
 *
 * @return array<string,int>
 */
function aw_indices_series()
{
	return apply_filters('aw_indices_series', array(
		'IGP-M' => 189,
		'INCC'  => 192,
	));
}

/* ---------------------------------------------------------------------------
 * Agendamento
 * ------------------------------------------------------------------------ */
add_action('init', 'aw_indices_agendar');
function aw_indices_agendar()
{
	if (!wp_next_scheduled(AW_INDICES_CRON)) {
		// Primeira execução às 06:00 (horário do site) do dia seguinte.
		$amanha = new DateTimeImmutable('tomorrow 06:00', wp_timezone());
		wp_schedule_event($amanha->getTimestamp(), 'daily', AW_INDICES_CRON);
	}
}

add_action(AW_INDICES_CRON, 'aw_indices_atualizar');

add_action('switch_theme', 'aw_indices_desagendar');
function aw_indices_desagendar()
{
	wp_clear_scheduled_hook(AW_INDICES_CRON);
}

/* ---------------------------------------------------------------------------
 * Atualização
 * ------------------------------------------------------------------------ */

/**
 * Busca o mês anterior nas séries e grava no repetidor das Opções do Site.
 *
 * @param bool $forcar Ignora o "já atualizado neste mês".
 * @return array Status gravado em AW_INDICES_STATUS.
 */
function aw_indices_atualizar($forcar = false)
{
	$alvo   = aw_indices_mes_referencia();
	$status = get_option(AW_INDICES_STATUS, array());

	if (!$forcar && !empty($status['referencia']) && $status['referencia'] === $alvo->format('Y-m') && empty($status['erros'])) {
		return $status;
	}

	if (!function_exists('update_field')) {
		return aw_indices_salvar_status($alvo, array(), array('ACF' => 'ACF PRO não está ativo.'));
	}

	$novos = array();
	$erros = array();

	foreach (aw_indices_series() as $nome => $serie) {
		$valor = aw_indices_buscar_mes((int) $serie, $alvo);

		if (is_wp_error($valor)) {
			$erros[$nome] = $valor->get_error_message();
			continue;
		}

		$novos[$nome] = array(
			'valor'      => aw_indices_formatar($valor),
			'referencia' => $alvo->format('m/Y'),
		);
	}

	if ($novos) {
		aw_indices_gravar($novos);
	}

	return aw_indices_salvar_status($alvo, $novos, $erros);
}

/**
 * Primeiro dia do mês anterior, no fuso do site.
 */
function aw_indices_mes_referencia()
{
	return (new DateTimeImmutable('first day of last month', wp_timezone()))->setTime(0, 0);
}

/**
 * Valor (float, em %) de um mês numa série do SGS.
 *
 * Usa os últimos 12 meses: o mês anterior está sempre entre eles, e a
 * resposta é pequena.
 *
 * @return float|WP_Error
 */
function aw_indices_buscar_mes($serie, DateTimeImmutable $mes)
{
	$url = sprintf('https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados/ultimos/12?formato=json', $serie);

	// A API do BCB devolve 502/503 de vez em quando: até 3 tentativas.
	for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
		$resposta = wp_remote_get($url, array(
			'timeout' => 20,
			'headers' => array('Accept' => 'application/json'),
		));
		$codigo   = is_wp_error($resposta) ? 0 : (int) wp_remote_retrieve_response_code($resposta);

		if (200 === $codigo || ($codigo >= 400 && $codigo < 500)) {
			break;
		}

		if ($tentativa < 3) {
			sleep(2);
		}
	}

	if (is_wp_error($resposta)) {
		return $resposta;
	}

	if (200 !== $codigo) {
		return new WP_Error('aw_indices_http', sprintf('A API do Banco Central respondeu HTTP %d.', $codigo));
	}

	$dados = json_decode(wp_remote_retrieve_body($resposta), true);

	if (!is_array($dados)) {
		return new WP_Error('aw_indices_json', 'Resposta da API em formato inesperado.');
	}

	$procurado = $mes->format('d/m/Y'); // "01/09/2026"

	foreach ($dados as $linha) {
		if (($linha['data'] ?? '') === $procurado && isset($linha['valor']) && is_numeric($linha['valor'])) {
			return (float) $linha['valor'];
		}
	}

	return new WP_Error('aw_indices_sem_mes', sprintf('O mês %s ainda não foi publicado.', $mes->format('m/Y')));
}

/**
 * 0.22 → "+0,22%", -0.73 → "-0,73%", 0 → "0,00%".
 */
function aw_indices_formatar($valor)
{
	$valor = round((float) $valor, 2);
	$sinal = $valor > 0 ? '+' : ($valor < 0 ? '-' : '');

	return $sinal . number_format(abs($valor), 2, ',', '') . '%';
}

/**
 * Atualiza as linhas pelo nome (IGP-M, INCC) e acrescenta as que faltarem.
 *
 * @param array $novos nome => ['valor', 'referencia'].
 */
function aw_indices_gravar(array $novos)
{
	$linhas = get_field('indices_economicos', 'option');
	$linhas = is_array($linhas) ? $linhas : array();
	$feitos = array();

	foreach ($linhas as &$linha) {
		$chave = aw_indices_normalizar($linha['nome'] ?? '');

		foreach ($novos as $nome => $dados) {
			if (aw_indices_normalizar($nome) === $chave) {
				$linha['valor']      = $dados['valor'];
				$linha['referencia'] = $dados['referencia'];
				$feitos[$nome]       = true;
			}
		}
	}
	unset($linha);

	foreach ($novos as $nome => $dados) {
		if (empty($feitos[$nome])) {
			$linhas[] = array('nome' => $nome, 'valor' => $dados['valor'], 'referencia' => $dados['referencia']);
		}
	}

	// Grava pelas chaves dos subcampos (acf-json/group_aw_opcoes.json).
	$valor = array_map(static function ($linha) {
		return array(
			'field_cso_opcoes_nome'       => $linha['nome'] ?? '',
			'field_cso_opcoes_valor'      => $linha['valor'] ?? '',
			'field_cso_opcoes_referencia' => $linha['referencia'] ?? '',
		);
	}, array_values($linhas));

	update_field('field_cso_opcoes_indices_economicos', $valor, 'option');
}

/**
 * "IGP-M", "igpm", "IGP M" → "IGPM".
 */
function aw_indices_normalizar($nome)
{
	return strtoupper(preg_replace('/[^a-z0-9]/i', '', remove_accents((string) $nome)));
}

function aw_indices_salvar_status(DateTimeImmutable $alvo, array $novos, array $erros)
{
	// Mantém o que já foi gravado no mesmo mês (ex.: IGP-M ontem, INCC hoje).
	$anterior = get_option(AW_INDICES_STATUS, array());

	if (!empty($anterior['referencia']) && $anterior['referencia'] === $alvo->format('Y-m')) {
		$novos = array_merge((array) $anterior['valores'], $novos);
		$erros = array_diff_key($erros, $novos);
	}

	$status = array(
		'referencia'    => $alvo->format('Y-m'),
		'valores'       => $novos,
		'erros'         => $erros,
		'atualizado_em' => current_time('mysql'),
	);

	update_option(AW_INDICES_STATUS, $status, false);

	return $status;
}

/* ---------------------------------------------------------------------------
 * Opções do Site: status + botão "Atualizar índices agora"
 * ------------------------------------------------------------------------ */
add_action('admin_notices', 'aw_indices_aviso_admin');
function aw_indices_aviso_admin()
{
	if (empty($_GET['page']) || 'aw-opcoes' !== $_GET['page'] || !current_user_can('manage_options')) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$status = get_option(AW_INDICES_STATUS, array());
	$botao  = wp_nonce_url(admin_url('admin-post.php?action=aw_indices_atualizar'), 'aw_indices_atualizar');
	$classe = !empty($status['erros']) ? 'notice-warning' : 'notice-info';

	echo '<div class="notice ' . esc_attr($classe) . '"><p><strong>Índices da top-bar (IGP-M e INCC):</strong> atualizados automaticamente todo mês com o valor do mês anterior (Banco Central, SGS 189 e 192). Edições manuais nessas duas linhas são substituídas na próxima atualização.</p>';

	if ($status) {
		$valores = array();

		foreach ((array) $status['valores'] as $nome => $dados) {
			$valores[] = esc_html($nome . ' ' . $dados['valor'] . ' (' . $dados['referencia'] . ')');
		}

		echo '<p>Última verificação: ' . esc_html(mysql2date('d/m/Y H:i', $status['atualizado_em'])) . ($valores ? ' — ' . implode(', ', $valores) : '') . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput

		foreach ((array) $status['erros'] as $nome => $erro) {
			echo '<p>' . esc_html($nome . ': ' . $erro . ' Nova tentativa amanhã.') . '</p>';
		}
	}

	echo '<p><a class="button" href="' . esc_url($botao) . '">Atualizar índices agora</a></p></div>';
}

add_action('admin_post_aw_indices_atualizar', 'aw_indices_atualizar_manual');
function aw_indices_atualizar_manual()
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Sem permissão.', 'alfama-web'), 403);
	}

	check_admin_referer('aw_indices_atualizar');
	aw_indices_atualizar(true);

	wp_safe_redirect(admin_url('admin.php?page=aw-opcoes'));
	exit;
}

/* ---------------------------------------------------------------------------
 * WP-CLI: wp aw-indices atualizar [--forcar]
 * ------------------------------------------------------------------------ */
if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command('aw-indices atualizar', static function ($args, $assoc) {
		$status = aw_indices_atualizar(!empty($assoc['forcar']));

		foreach ((array) $status['valores'] as $nome => $dados) {
			WP_CLI::log($nome . ': ' . $dados['valor'] . ' / ' . $dados['referencia']);
		}

		foreach ((array) $status['erros'] as $nome => $erro) {
			WP_CLI::warning($nome . ': ' . $erro);
		}

		empty($status['erros']) ? WP_CLI::success('Índices atualizados.') : WP_CLI::halt(1);
	});
}
