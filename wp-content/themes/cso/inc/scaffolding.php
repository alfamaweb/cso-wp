<?php
/**
 * Geração automática de template e CSS ao publicar — só em desenvolvimento.
 *
 * DÍVIDAS RESOLVIDAS (v2): a rotina rodava em qualquer ambiente, exigindo que
 * o diretório do tema fosse gravável pelo PHP em produção, e deixava arquivos
 * órfãos quando o slug mudava. Aqui ela:
 *   - só roda fora de produção (aw_is_dev());
 *   - só roda se o diretório for gravável e o usuário puder editar arquivos;
 *   - pode ser desligada de vez com define('AW_SCAFFOLDING', false);
 *   - lista os órfãos no mesmo aviso, para serem removidos à mão.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * @return bool Se o scaffolding pode rodar agora.
 */
function aw_scaffolding_ativo()
{
	if (defined('AW_SCAFFOLDING')) {
		return (bool) AW_SCAFFOLDING;
	}

	return aw_is_dev()
		&& is_writable(AW_DIR)
		&& is_writable(AW_DIR . '/assets/css')
		&& current_user_can('edit_posts');
}

/**
 * Post types que participam do scaffolding.
 *
 * @return string[]
 */
function aw_scaffolding_post_types()
{
	return apply_filters('aw_scaffolding_post_types', array('page', 'post', 'empreendimento'));
}

add_action('transition_post_status', 'aw_scaffolding_ao_publicar', 10, 3);
/**
 * @param string  $novo
 * @param string  $antigo
 * @param WP_Post $post
 * @return void
 */
function aw_scaffolding_ao_publicar($novo, $antigo, $post)
{
	if ('publish' !== $novo || 'publish' === $antigo) {
		return;
	}

	if (!in_array($post->post_type, aw_scaffolding_post_types(), true) || !aw_scaffolding_ativo()) {
		return;
	}

	$criados = array();

	if ('page' === $post->post_type) {
		$slug = $post->post_name;

		$criados = array_merge($criados, aw_criar_css($slug, sprintf('Página: %s', $post->post_title)));
		$criados = array_merge($criados, aw_criar_template('page-' . $slug . '.php', 'page.php'));
	} else {
		$tipo = $post->post_type;

		$criados = array_merge($criados, aw_criar_css($tipo, sprintf('Post type: %s', $tipo)));
		$criados = array_merge($criados, aw_criar_template('single-' . $tipo . '.php', 'single.php'));
	}

	if ($criados) {
		set_transient('aw_scaffolding_aviso_' . get_current_user_id(), $criados, 2 * MINUTE_IN_SECONDS);
	}
}

/**
 * Cria assets/css/{slug}.css vazio, se ainda não existir.
 *
 * @param string $slug
 * @param string $descricao Comentário de cabeçalho.
 * @return string[] Caminhos relativos criados.
 */
function aw_criar_css($slug, $descricao)
{
	$slug = sanitize_file_name($slug);

	if (!$slug) {
		return array();
	}

	$relativo = 'assets/css/' . $slug . '.css';
	$path     = AW_DIR . '/' . $relativo;

	if (file_exists($path)) {
		return array();
	}

	$conteudo = sprintf("/* %s — %s */\n", $descricao, gmdate('Y-m-d'));

	return false !== file_put_contents($path, $conteudo) ? array($relativo) : array();
}

/**
 * Copia um template base para um novo nome, se ainda não existir.
 *
 * @param string $novo Nome do arquivo de destino, ex. 'page-sobre.php'.
 * @param string $base Nome do molde, ex. 'page.php'.
 * @return string[] Caminhos relativos criados.
 */
function aw_criar_template($novo, $base)
{
	$novo = sanitize_file_name($novo);
	$path = AW_DIR . '/' . $novo;

	if (!$novo || file_exists($path) || !file_exists(AW_DIR . '/' . $base)) {
		return array();
	}

	return copy(AW_DIR . '/' . $base, $path) ? array($novo) : array();
}

add_action('admin_notices', 'aw_scaffolding_aviso');
/**
 * Mostra o que foi criado e, no mesmo aviso, os templates órfãos.
 *
 * @return void
 */
function aw_scaffolding_aviso()
{
	$chave   = 'aw_scaffolding_aviso_' . get_current_user_id();
	$criados = get_transient($chave);

	if (!$criados) {
		return;
	}

	delete_transient($chave);

	echo '<div class="notice notice-success is-dismissible"><p><strong>'
		. esc_html__('Alfama WEB v3 — arquivos criados:', 'alfama-web') . '</strong></p><ul style="margin-left:1.4em;list-style:disc">';

	foreach ((array) $criados as $arquivo) {
		echo '<li><code>' . esc_html($arquivo) . '</code></li>';
	}

	echo '</ul>';

	$orfaos = aw_templates_orfaos();

	if ($orfaos) {
		echo '<p><strong>' . esc_html__('Templates sem página correspondente (candidatos a remoção):', 'alfama-web')
			. '</strong> <code>' . esc_html(implode('</code>, <code>', $orfaos)) . '</code></p>';
	}

	echo '</div>';
}

/**
 * Templates page-{slug}.php cujo slug não existe mais como página publicada.
 *
 * @return string[]
 */
function aw_templates_orfaos()
{
	$arquivos = glob(AW_DIR . '/page-*.php') ?: array();
	$orfaos   = array();

	foreach ($arquivos as $arquivo) {
		$slug = substr(basename($arquivo, '.php'), 5);

		if (!$slug) {
			continue;
		}

		$pagina = get_page_by_path($slug);

		if (!$pagina || 'publish' !== $pagina->post_status) {
			$orfaos[] = basename($arquivo);
		}
	}

	return $orfaos;
}
