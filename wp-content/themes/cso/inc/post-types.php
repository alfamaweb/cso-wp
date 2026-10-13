<?php
/**
 * CPTs e taxonomias do tema, registrados em PHP.
 *
 * DÍVIDA RESOLVIDA (v2): a modelagem vivia só no banco, criada pela UI do ACF,
 * e nenhum dos seis temas tinha acf-json/ de sincronia — deploy e onboarding
 * dependiam de dump de banco. Aqui o que é estrutura (post types, taxonomias)
 * é código versionado; o que é conteúdo editável (grupos de campos) continua
 * na UI do ACF, mas com sincronia acf-json/ ligada (ver inc/acf.php).
 *
 * O caso de referência é "empreendimento", o objeto de domínio mais recorrente
 * do estúdio. Em um projeto de outro ramo, renomeie ou remova este arquivo.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

add_action('init', 'aw_registrar_post_types');
function aw_registrar_post_types()
{
	register_post_type('empreendimento', array(
		'labels' => array(
			'name'               => __('Empreendimentos', 'alfama-web'),
			'singular_name'      => __('Empreendimento', 'alfama-web'),
			'add_new_item'       => __('Adicionar empreendimento', 'alfama-web'),
			'edit_item'          => __('Editar empreendimento', 'alfama-web'),
			'search_items'       => __('Buscar empreendimentos', 'alfama-web'),
			'not_found'          => __('Nenhum empreendimento encontrado.', 'alfama-web'),
			'menu_name'          => __('Empreendimentos', 'alfama-web'),
		),
		'public'        => true,
		'has_archive'   => false, // A listagem é a página /empreendimentos, com filtro.
		'rewrite'       => array('slug' => 'empreendimentos', 'with_front' => false),
		'menu_icon'     => 'dashicons-building',
		'menu_position' => 20,
		'supports'      => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes'),
		'show_in_rest'  => true,
		'taxonomies'    => array('localizacao', 'status_obra'),
	));
}

add_action('init', 'aw_registrar_taxonomias');
function aw_registrar_taxonomias()
{
	// Hierárquica de propósito: cidade como termo pai, bairro como filho.
	register_taxonomy('localizacao', array('empreendimento'), array(
		'labels' => array(
			'name'          => __('Localizações', 'alfama-web'),
			'singular_name' => __('Localização', 'alfama-web'),
			'menu_name'     => __('Localizações', 'alfama-web'),
		),
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array('slug' => 'localizacao'),
	));

	register_taxonomy('status_obra', array('empreendimento'), array(
		'labels' => array(
			'name'          => __('Status da obra', 'alfama-web'),
			'singular_name' => __('Status da obra', 'alfama-web'),
			'menu_name'     => __('Status da obra', 'alfama-web'),
		),
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array('slug' => 'status'),
	));
}

/**
 * Termos fixos de status, criados uma única vez.
 *
 * DÍVIDA RESOLVIDA (v2): enkan_seed_termos_atuacao() rodava em wp_loaded a
 * cada requisição, chamando term_exists() para cada termo. Aqui o seed roda
 * uma vez e grava uma flag em option.
 *
 * @return void
 */
add_action('admin_init', 'aw_seed_termos');
function aw_seed_termos()
{
	$versao_seed = '1';

	if (get_option('aw_seed_termos') === $versao_seed) {
		return;
	}

	$termos = array(
		'status_obra' => array(
			'lancamento'    => __('Lançamento', 'alfama-web'),
			'em-obras'      => __('Em obras', 'alfama-web'),
			'pronto'        => __('Pronto para morar', 'alfama-web'),
			'esgotado'      => __('100% vendido', 'alfama-web'),
		),
	);

	foreach ($termos as $taxonomia => $lista) {
		if (!taxonomy_exists($taxonomia)) {
			continue;
		}

		foreach ($lista as $slug => $nome) {
			if (!term_exists($slug, $taxonomia)) {
				wp_insert_term($nome, $taxonomia, array('slug' => $slug));
			}
		}
	}

	update_option('aw_seed_termos', $versao_seed, false);
}

/**
 * Slug do termo que marca um item esgotado.
 *
 * Centralizado porque a regra aparece em três lugares: no card (troca o preço
 * pelo selo), no filtro (pode ser filtrado) e nos selects de formulário (é
 * excluído, para não gerar lead de item indisponível).
 */
const AW_STATUS_ESGOTADO = 'esgotado';

/**
 * @param int|null $post_id
 * @return bool
 */
function aw_esta_esgotado($post_id = null)
{
	$post_id = $post_id ?: get_the_ID();

	return has_term(AW_STATUS_ESGOTADO, 'status_obra', $post_id);
}
