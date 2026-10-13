<?php
/**
 * Integração com o Advanced Custom Fields PRO.
 *
 * DÍVIDAS RESOLVIDAS (v2):
 *  - sincronia acf-json/ ligada, com save e load points no tema: grupo de campo
 *    editado na UI vira arquivo versionado, e o próximo clone do repositório já
 *    nasce com a modelagem;
 *  - todos os grupos de campos ficam em acf-json/ (um arquivo por página,
 *    post type e Opções do Site), editáveis em ACF > Grupos de campos e
 *    versionados com o tema; aqui fica só a página de opções e a regra de
 *    localização por slug (aw_pagina);
 *  - aviso claro no admin quando o ACF está ausente, em vez de fatal error.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Onde o ACF grava os JSON dos grupos editados na UI.
 */
add_filter('acf/settings/save_json', 'aw_acf_save_json');
function aw_acf_save_json($path)
{
	$dir = AW_DIR . '/acf-json';

	return is_dir($dir) ? $dir : $path;
}

/**
 * De onde o ACF carrega os JSON versionados.
 *
 * @param array $paths
 * @return array
 */
add_filter('acf/settings/load_json', 'aw_acf_load_json');
function aw_acf_load_json($paths)
{
	unset($paths[0]);
	$paths[] = AW_DIR . '/acf-json';

	return $paths;
}

/**
 * Página "Opções do Site" — header, footer, redes, contatos e integrações.
 *
 * @return void
 */
add_action('acf/init', 'aw_acf_options_page');
function aw_acf_options_page()
{
	if (!function_exists('acf_add_options_page')) {
		return;
	}

	acf_add_options_page(array(
		'page_title'  => __('Opções do Site', 'alfama-web'),
		'menu_title'  => __('Opções do Site', 'alfama-web'),
		'menu_slug'   => 'aw-opcoes',
		'capability'  => 'edit_theme_options',
		'position'    => '58.9',
		'icon_url'    => 'dashicons-admin-settings',
		'redirect'    => false,
		'update_button' => __('Salvar opções', 'alfama-web'),
	));
}

/**
 * Páginas com layout próprio (page-{slug}.php) e grupo ACF em acf-json/.
 *
 * Nelas o editor de conteúdo não é usado: o bloco/clássico é desligado e o
 * grupo "Hero da página" (genérico) não aparece.
 *
 * @return string[]
 */
function aw_paginas_com_layout()
{
	return (array) apply_filters('aw_paginas_com_layout', array('home', 'quem-somos', 'blog', 'empreendimentos', 'contato'));
}

/**
 * Regra de localização "Página do tema (slug)".
 *
 * Os grupos das páginas (acf-json/group_cso_*.json) são ligados pelo slug, e
 * não pelo ID: o slug é o mesmo em todos os ambientes (local, homologação,
 * produção) e é o que o WordPress usa para escolher page-{slug}.php.
 *
 * @return void
 */
add_action('acf/init', 'aw_acf_registrar_localizacao');
function aw_acf_registrar_localizacao()
{
	if (!class_exists('ACF_Location') || !function_exists('acf_register_location_type')) {
		return;
	}

	if (!class_exists('AW_ACF_Location_Pagina')) {
		class AW_ACF_Location_Pagina extends ACF_Location // phpcs:ignore Generic.Files.OneObjectStructurePerFile
		{
			public function initialize()
			{
				$this->name        = 'aw_pagina';
				$this->label       = __('Página do tema (slug)', 'alfama-web');
				$this->category    = 'page';
				$this->object_type = 'post';
			}

			public function match($rule, $screen, $field_group)
			{
				$post_id = (int) ($screen['post_id'] ?? 0);
				$post    = $post_id ? get_post($post_id) : null;

				if (!$post || 'page' !== $post->post_type) {
					return false;
				}

				return $this->compare_to_rule(aw_slug_pagina($post->ID), $rule);
			}

			public function get_values($rule)
			{
				$valores = array();

				foreach (glob(get_template_directory() . '/page-*.php') as $arquivo) {
					$slug           = substr(basename($arquivo, '.php'), 5);
					$valores[$slug] = $slug . ' (page-' . $slug . '.php)';
				}

				foreach (get_pages(array('post_status' => 'publish,draft,private')) as $pagina) {
					if (!isset($valores[$pagina->post_name])) {
						$valores[$pagina->post_name] = $pagina->post_name . ' — ' . $pagina->post_title;
					}
				}

				ksort($valores);

				return $valores;
			}
		}
	}

	acf_register_location_type('AW_ACF_Location_Pagina');
}

/**
 * Páginas com layout próprio abrem no editor clássico, só com os campos ACF
 * (o hide_on_screen dos grupos esconde o conteúdo — no editor de blocos ele
 * não tem efeito).
 *
 * @param bool    $usar
 * @param WP_Post $post
 * @return bool
 */
add_filter('use_block_editor_for_post', 'aw_editor_paginas_com_layout', 10, 2);
function aw_editor_paginas_com_layout($usar, $post)
{
	if ($post && 'page' === $post->post_type && in_array(aw_slug_pagina($post->ID), aw_paginas_com_layout(), true)) {
		return false;
	}

	return $usar;
}

/**
 * Aviso no admin quando o ACF PRO não está ativo.
 *
 * O tema degrada em vez de quebrar: aw_field() devolve os fallbacks e o site
 * continua navegável, mas o alerta deixa a causa explícita.
 *
 * @return void
 */
add_action('admin_notices', 'aw_aviso_acf_ausente');
function aw_aviso_acf_ausente()
{
	if (function_exists('get_field') || !current_user_can('activate_plugins')) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
		esc_html__('Alfama WEB v3:', 'alfama-web'),
		esc_html__('o Advanced Custom Fields PRO não está ativo. O tema continua funcionando com valores de fallback, mas nenhum conteúdo editável será exibido.', 'alfama-web')
	);
}
