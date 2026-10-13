<?php
/**
 * Registro e enfileiramento de CSS e JS.
 *
 * DÍVIDAS RESOLVIDAS (v2):
 *  - libs não entram mais como <link>/<script> literais no header.php/footer.php:
 *    tudo passa por wp_enqueue_*, então dependências e ordem são explícitas;
 *  - jQuery 1.11.1 (2014) hardcoded no header saiu. O tema é vanilla JS; se um
 *    plugin precisar de jQuery, o WordPress carrega a própria versão dele;
 *  - Bootstrap saiu por completo (232 KB de CSS carregados em toda página);
 *  - Swiper, Fancybox e SweetAlert2 só entram onde são usados, via
 *    aw_enqueue_lib() ou pelo filtro 'aw_libs_automaticas';
 *  - todo asset local é versionado por filemtime — cache-busting sem editar
 *    número de versão à mão.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Versões fixadas das bibliotecas de terceiros. Atualizar aqui, num só lugar.
 */
const AW_LIB_VERSIONS = array(
	'swiper'      => '12.0.2',
	'fancybox'    => '5.0.36',
	'sweetalert2' => '11.14.5',
);

/**
 * Versão de um asset local a partir do mtime do arquivo.
 *
 * @param string $relativo Caminho relativo à raiz do tema, ex. '/assets/css/style.css'.
 * @return string|false
 */
function aw_asset_version($relativo)
{
	$path = AW_DIR . $relativo;

	return file_exists($path) ? (string) filemtime($path) : AW_VERSION;
}

/**
 * Registra tudo e enfileira o que é global.
 *
 * Ordem da cascata, garantida por dependência (não por prioridade de hook):
 *   aw-base  →  aw-tailwind  →  aw-global  →  aw-{contexto}
 *
 * @return void
 */
add_action('wp_enqueue_scripts', 'aw_registrar_assets');
function aw_registrar_assets()
{
	/* --------------------------------------------------------------------
	 * Bibliotecas de terceiros — registradas, não enfileiradas.
	 * ----------------------------------------------------------------- */
	$v = AW_LIB_VERSIONS;

	wp_register_style('swiper', "https://cdn.jsdelivr.net/npm/swiper@{$v['swiper']}/swiper-bundle.min.css", array(), $v['swiper']);
	wp_register_script('swiper', "https://cdn.jsdelivr.net/npm/swiper@{$v['swiper']}/swiper-bundle.min.js", array(), $v['swiper'], true);

	wp_register_style('fancybox', "https://cdn.jsdelivr.net/npm/@fancyapps/ui@{$v['fancybox']}/dist/fancybox/fancybox.css", array(), $v['fancybox']);
	wp_register_script('fancybox', "https://cdn.jsdelivr.net/npm/@fancyapps/ui@{$v['fancybox']}/dist/fancybox/fancybox.umd.js", array(), $v['fancybox'], true);

	wp_register_style('sweetalert2', "https://cdn.jsdelivr.net/npm/sweetalert2@{$v['sweetalert2']}/dist/sweetalert2.min.css", array(), $v['sweetalert2']);
	wp_register_script('sweetalert2', "https://cdn.jsdelivr.net/npm/sweetalert2@{$v['sweetalert2']}/dist/sweetalert2.all.min.js", array(), $v['sweetalert2'], true);

	/* --------------------------------------------------------------------
	 * CSS do tema.
	 * ----------------------------------------------------------------- */

	// Webfonts do layout (Sora + Instrument Sans). display=swap evita texto invisível.
	wp_enqueue_style('aw-fonts', 'https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Sora:wght@600;700&display=swap', array(), null);

	// style.css da raiz: cabeçalho do tema + reset + tokens de design.
	wp_enqueue_style('aw-base', get_stylesheet_uri(), array('aw-fonts'), aw_asset_version('/style.css'));

	// Utilitários do Tailwind, compilados por `npm run build`.
	if (file_exists(AW_DIR . '/assets/css/tailwind.css')) {
		wp_enqueue_style('aw-tailwind', AW_CSS . 'tailwind.css', array('aw-base'), aw_asset_version('/assets/css/tailwind.css'));
	} elseif (aw_is_dev() && current_user_can('manage_options')) {
		wp_add_inline_style('aw-base', '/* aw: assets/css/tailwind.css não existe — rode `npm run build` no tema. */');
	}

	// CSS autoral global.
	$dep_global = wp_style_is('aw-tailwind', 'registered') ? array('aw-tailwind') : array('aw-base');
	wp_enqueue_style('aw-global', AW_CSS . 'style.css', $dep_global, aw_asset_version('/assets/css/style.css'));

	// CSS do contexto atual (um arquivo por página / por post type).
	foreach (aw_slugs_de_css() as $slug) {
		aw_enqueue_css_se_existir($slug);
	}

	/* --------------------------------------------------------------------
	 * JS do tema.
	 * ----------------------------------------------------------------- */
	wp_enqueue_script('aw-app', AW_JS . 'app.js', array(), aw_asset_version('/assets/js/app.js'), true);

	wp_localize_script('aw-app', 'AW', array(
		'ajaxUrl'  => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce(AW_Ajax_Controller::NONCE),
		'homeUrl'  => home_url('/'),
		'i18n'     => array(
			'erroGenerico' => __('Não foi possível concluir agora. Tente novamente em instantes.', 'alfama-web'),
			'semResultado' => __('Nenhum resultado encontrado.', 'alfama-web'),
		),
	));

	/* --------------------------------------------------------------------
	 * Libs automáticas por contexto. Um projeto ajusta pelo filtro em vez de
	 * editar este arquivo.
	 * ----------------------------------------------------------------- */
	$automaticas = apply_filters('aw_libs_automaticas', aw_libs_do_contexto());

	// Libs pedidas por templates antes de wp_head() (ver aw_enqueue_lib()).
	$automaticas = array_merge((array) $automaticas, $GLOBALS['aw_libs_pendentes'] ?? array());

	foreach ((array) $automaticas as $lib) {
		aw_enqueue_lib($lib);
	}
}

/**
 * Slugs de CSS que o contexto atual pede, na ordem em que devem carregar.
 *
 * Mantém a convenção da v2 — page-{slug}.php ↔ assets/css/{slug}.css — e
 * acrescenta os contextos de arquivo, busca e erro.
 *
 * @return string[]
 */
function aw_slugs_de_css()
{
	$slugs = array();

	if (is_front_page()) {
		$slugs[] = 'home';
	}

	if (is_home() && !is_front_page()) {
		$slugs[] = 'blog';
	}

	if (is_page()) {
		$slugs[] = aw_slug_pagina(get_queried_object_id());
	}

	if (is_singular()) {
		$slugs[] = get_post_type();
	}

	if (is_post_type_archive() || is_tax() || is_category() || is_tag() || is_date() || is_author()) {
		$slugs[] = 'archive';

		if (is_post_type_archive()) {
			$slugs[] = get_query_var('post_type') . '-archive';
		}
	}

	if (is_search()) {
		$slugs[] = 'search';
	}

	if (is_404()) {
		$slugs[] = '404';
	}

	return array_values(array_unique(array_filter($slugs)));
}

/**
 * Enfileira assets/css/{slug}.css se o arquivo existir.
 *
 * @param string $slug
 * @return bool Se enfileirou.
 */
function aw_enqueue_css_se_existir($slug)
{
	$slug     = sanitize_file_name($slug);
	$relativo = '/assets/css/' . $slug . '.css';

	if (!$slug || !file_exists(AW_DIR . $relativo)) {
		return false;
	}

	$handle = 'aw-css-' . $slug;

	if (!wp_style_is($handle, 'enqueued')) {
		wp_enqueue_style($handle, AW_CSS . $slug . '.css', array('aw-global'), aw_asset_version($relativo));
	}

	return true;
}

/**
 * Bibliotecas que cada contexto costuma precisar.
 *
 * @return string[]
 */
function aw_libs_do_contexto()
{
	$libs = array();

	if (is_front_page()) {
		$libs[] = 'swiper';
	}

	if (is_singular('empreendimento')) {
		$libs[] = 'swiper';
		$libs[] = 'fancybox';
	}

	return $libs;
}

/**
 * Enfileira uma lib registrada (CSS + JS) pelo mesmo handle.
 *
 * Pode ser chamada de dentro de um template, antes ou depois do get_header():
 *  - antes: o pedido fica pendente e entra junto com as libs do contexto,
 *    com o CSS no <head>;
 *  - depois: enfileira na hora e o WordPress imprime no rodapé.
 *
 * @param string $lib 'swiper' | 'fancybox' | 'sweetalert2'
 * @return void
 */
function aw_enqueue_lib($lib)
{
	if (!array_key_exists($lib, AW_LIB_VERSIONS)) {
		return;
	}

	// Chamada num template antes do get_header(): as libs só são registradas
	// em wp_enqueue_scripts (dentro de wp_head()), então aqui ainda não
	// existem. Guarda o pedido; aw_registrar_assets() enfileira ao registrar.
	if (!did_action('wp_enqueue_scripts')) {
		$GLOBALS['aw_libs_pendentes'][] = $lib;
		return;
	}

	if (wp_style_is($lib, 'registered')) {
		wp_enqueue_style($lib);
	}

	if (wp_script_is($lib, 'registered')) {
		wp_enqueue_script($lib);
	}
}

/**
 * Preconnect com o Google Fonts, para as webfonts não atrasarem o primeiro paint.
 *
 * @param array  $urls
 * @param string $tipo
 * @return array
 */
add_filter('wp_resource_hints', 'aw_preconnect_fontes', 10, 2);
function aw_preconnect_fontes($urls, $tipo)
{
	if ('preconnect' === $tipo) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array('href' => 'https://fonts.gstatic.com', 'crossorigin');
	}

	return $urls;
}

/**
 * CSS do editor de blocos, para o conteúdo no admin lembrar o front.
 *
 * @return void
 */
add_action('after_setup_theme', 'aw_editor_style');
function aw_editor_style()
{
	add_theme_support('editor-styles');

	if (file_exists(AW_DIR . '/assets/css/editor.css')) {
		add_editor_style('assets/css/editor.css');
	}
}

/**
 * Transição entre páginas — opt-in e complemento do CSS de style.css.
 *
 * 1. <style> inline com @view-transition { navigation: auto }. Precisa estar
 *    no <head> como estilo inline: dentro de assets/css/style.css o Chrome
 *    não enxergava o opt-in na página nova a tempo e abortava a transição
 *    ("ViewTransition opt-in disabled").
 * 2. Script que roda antes da primeira pintura: se a transição nativa não
 *    aconteceu (Firefox, primeira visita, transição pulada), marca o <html>
 *    com .aw-entrada para o <main> entrar com fade. Assim a animação aparece
 *    em qualquer navegador, sem animar duas vezes.
 */
add_action('wp_head', 'aw_transicao_paginas', 1);
function aw_transicao_paginas()
{
	if (is_admin()) {
		return;
	}

	echo '<style id="aw-transicao-opt-in">@view-transition{navigation:auto}</style>' . "\n";

	$js = "(function(h){if('onpagereveal' in window){addEventListener('pagereveal',function(e){if(!e.viewTransition){h.classList.add('aw-entrada')}},{once:true})}else{h.classList.add('aw-entrada')}})(document.documentElement);";

	wp_print_inline_script_tag($js, array('id' => 'aw-transicao-paginas'));
}
