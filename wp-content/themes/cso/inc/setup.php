<?php
/**
 * Suporte do tema, menus, tamanhos de imagem e limpeza do <head>.
 *
 * DÍVIDA RESOLVIDA (v2): nada aqui herda do Twenty Sixteen. Foram removidos
 * inc/back-compat.php, inc/customizer.php, inc/template-tags.php, genericons/,
 * css/ie*.css, js/html5.js e languages/twentysixteen.pot — junto com o
 * twentysixteen_scripts() que enfileirava Google Fonts, Genericons e três
 * folhas condicionais de IE em toda página.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

add_action('after_setup_theme', 'aw_setup');
function aw_setup()
{
	load_theme_textdomain('alfama-web', AW_DIR . '/languages');

	add_theme_support('title-tag');
	add_theme_support('automatic-feed-links');
	add_theme_support('post-thumbnails');
	add_theme_support('responsive-embeds');
	add_theme_support('custom-logo', array(
		'height'      => 120,
		'width'       => 400,
		'flex-height' => true,
		'flex-width'  => true,
	));
	add_theme_support('html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
	));

	register_nav_menus(array(
		'primary'   => __('Menu principal', 'alfama-web'),
		'rodape'    => __('Menu do rodapé — Navegação', 'alfama-web'),
		'rodape-empreendimentos' => __('Menu do rodapé — Empreendimentos', 'alfama-web'),
		'legal'     => __('Links legais (rodapé)', 'alfama-web'),
	));

	// Tamanhos usados pelos componentes do tema — evita servir 'full' em card.
	add_image_size('aw-card', 720, 480, true);
	add_image_size('aw-hero', 1920, 900, true);
	add_image_size('aw-hero-mobile', 828, 1104, true);
}

/**
 * Largura de conteúdo para embeds e imagens no editor.
 *
 * @return void
 */
add_action('after_setup_theme', 'aw_content_width', 0);
function aw_content_width()
{
	$GLOBALS['content_width'] = 1140;
}

/**
 * Limpa o <head> de recursos que o tema não usa.
 *
 * Nada aqui é "hardening" agressivo: só a remoção de saídas que nenhum
 * template do tema consome e que custam requests ou expõem versão.
 *
 * @return void
 */
add_action('init', 'aw_limpar_head');
function aw_limpar_head()
{
	remove_action('wp_head', 'wp_generator');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wp_shortlink_wp_head');
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');
}

/**
 * Remove a versão do WordPress das URLs de assets do core.
 *
 * @param string $src
 * @return string
 */
add_filter('style_loader_src', 'aw_remover_versao_wp', 10, 1);
add_filter('script_loader_src', 'aw_remover_versao_wp', 10, 1);
function aw_remover_versao_wp($src)
{
	if ($src && strpos($src, 'ver=' . get_bloginfo('version')) !== false) {
		$src = remove_query_arg('ver', $src);
	}

	return $src;
}

/**
 * Classes utilitárias no <body>: ambiente e slug da página.
 *
 * Permite escrever CSS por página sem depender de ID gerado pelo banco.
 *
 * @param array $classes
 * @return array
 */
add_filter('body_class', 'aw_body_class');
function aw_body_class($classes)
{
	if (is_page() || is_singular()) {
		$slug = get_post_field('post_name', get_queried_object_id());
		if ($slug) {
			$classes[] = 'pagina-' . sanitize_html_class($slug);
		}
	}

	if (is_singular()) {
		$classes[] = 'tipo-' . sanitize_html_class(get_post_type());
	}

	if (aw_is_dev()) {
		$classes[] = 'aw-env-' . sanitize_html_class(aw_env());
	}

	return $classes;
}

/**
 * Resumo automático mais curto e com reticências em vez de "[...]".
 */
add_filter('excerpt_length', function () {
	return 28;
}, 999);

add_filter('excerpt_more', function () {
	return '…';
});

/**
 * A página de privacidade (Configurações › Privacidade) usa
 * page-politica-de-privacidade.php mesmo que o slug dela seja outro.
 *
 * @param string $template
 * @return string
 */
add_filter('page_template', 'aw_template_politica');
function aw_template_politica($template)
{
	if ('politica-de-privacidade' !== aw_slug_pagina(get_queried_object_id())) {
		return $template;
	}

	return locate_template('page-politica-de-privacidade.php') ?: $template;
}

