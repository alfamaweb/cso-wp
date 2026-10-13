<?php
/**
 * Detecção de ambiente e desindexação de staging.
 *
 * DÍVIDA RESOLVIDA (v2): disable_search_engine_indexing() rodava em 'init' e
 * chamava update_option('blog_public', …) em TODA requisição, escrevendo no
 * banco sem necessidade. Aqui o valor é filtrado na leitura
 * (pre_option_blog_public) — zero escrita, e o efeito é o mesmo.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Ambiente atual: 'production', 'staging', 'development' ou 'local'.
 *
 * Fonte de verdade, em ordem: a constante AW_ENV (wp-config.php), o
 * WP_ENVIRONMENT_TYPE nativo do WordPress e, por último, a heurística de
 * hostname que a v2 usava (prefixo "dev.").
 *
 * @return string
 */
function aw_env()
{
	static $env = null;

	if (null !== $env) {
		return $env;
	}

	if (defined('AW_ENV')) {
		$env = (string) AW_ENV;
		return $env;
	}

	// Definido por WP_ENVIRONMENT_TYPE em wp-config.php; 'production' é o default do core.
	$nativo = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

	if ('production' !== $nativo) {
		$env = $nativo;
		return $env;
	}

	$host = wp_parse_url(home_url(), PHP_URL_HOST);
	$host = is_string($host) ? strtolower($host) : '';

	if ($host === 'localhost' || substr($host, -6) === '.local' || substr($host, -5) === '.test') {
		$env = 'local';
	} elseif (strpos($host, 'dev.') === 0 || strpos($host, 'staging.') === 0 || strpos($host, 'homolog.') === 0) {
		$env = 'staging';
	} else {
		$env = 'production';
	}

	return $env;
}

/**
 * @return bool Verdadeiro em qualquer ambiente que não seja produção.
 */
function aw_is_dev()
{
	return 'production' !== aw_env();
}

/**
 * Força blog_public = 0 fora de produção, sem tocar no banco.
 *
 * @param mixed $pre Valor curto-circuitado da option.
 * @return mixed
 */
add_filter('pre_option_blog_public', 'aw_filtrar_blog_public');
function aw_filtrar_blog_public($pre)
{
	return aw_is_dev() ? '0' : $pre;
}

/**
 * Reforça o noindex nos headers de robots fora de produção.
 *
 * @param array $robots
 * @return array
 */
add_filter('wp_robots', 'aw_robots_staging');
function aw_robots_staging($robots)
{
	if (aw_is_dev()) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}

	return $robots;
}

/**
 * Selo de ambiente na barra de administração — mantém o indicador visual da v2.
 *
 * @param WP_Admin_Bar $bar
 * @return void
 */
add_action('admin_bar_menu', 'aw_selo_ambiente', 100);
function aw_selo_ambiente($bar)
{
	$env = aw_env();

	$rotulos = array(
		'production'  => '✅ Produção — indexado',
		'staging'     => '🚫 Staging — não indexado',
		'development' => '🚫 Desenvolvimento — não indexado',
		'local'       => '🚫 Local — não indexado',
	);

	$bar->add_node(array(
		'id'    => 'aw-ambiente',
		'title' => $rotulos[$env] ?? ('🚫 ' . $env),
		'meta'  => array('title' => sprintf('Ambiente detectado: %s', $env)),
	));
}

/**
 * Barra de admin colorida fora de produção, para nunca confundir as abas.
 *
 * @return void
 */
add_action('admin_bar_init', 'aw_estilo_barra_ambiente');
function aw_estilo_barra_ambiente()
{
	if (!aw_is_dev() || !is_admin_bar_showing()) {
		return;
	}

	$css = '#wpadminbar { background: #7a1f1a !important; }'
		. '#wp-admin-bar-aw-ambiente > .ab-item { font-weight: 700; }';

	wp_register_style('aw-admin-bar-env', false, array(), AW_VERSION);
	wp_enqueue_style('aw-admin-bar-env');
	wp_add_inline_style('aw-admin-bar-env', $css);
}
