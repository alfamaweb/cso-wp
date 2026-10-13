<?php
/**
 * Conteúdo inicial do Figma — importação única para os campos ACF.
 *
 * Roda uma vez, ao abrir o painel com um usuário administrador, e grava com
 * update_field() os textos e imagens do layout (Figma "CSO (Copy)") em:
 *  - Opções do Site; páginas Home, Quem Somos, Blog, Empreendimentos, Contato
 *    e Política de Privacidade;
 *  - 3 empreendimentos (Residential Up SIM com a página interna completa,
 *    Bless Houses e Vienna Future Residence);
 *  - 3 notícias (Arquitetura, Imóveis, Empresa).
 *
 * Regras:
 *  - nunca sobrescreve um campo já preenchido nem duplica posts (busca pelo
 *    slug) ou imagens (marca cada anexo com _aw_ci_arquivo);
 *  - as imagens saem de conteudo-inicial/ no tema e vão para a biblioteca de
 *    mídia; depois da importação a pasta pode ser apagada;
 *  - controlado por AW_CONTEUDO_VERSAO: subir o número roda de novo (só o
 *    que estiver vazio é preenchido).
 *
 * Os textos lorem ipsum do Figma foram copiados como estão, por decisão do
 * Igor. O vídeo de fundo da home e o link do vídeo do empreendimento não
 * existem no Figma como arquivo/URL e ficam para cadastrar no painel.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

const AW_CONTEUDO_VERSAO = 3; // 1 = conteúdo do Figma; 2 = vídeo do hero; 3 = vídeo do hero em loop contínuo

add_action('admin_init', 'aw_conteudo_inicial', 20);
function aw_conteudo_inicial()
{
	if (wp_doing_ajax() || !current_user_can('manage_options') || !function_exists('update_field')) {
		return;
	}

	$versao_atual = (int) get_option('aw_conteudo_versao', 0);

	if ($versao_atual >= AW_CONTEUDO_VERSAO) {
		return;
	}

	// Marca antes de rodar: se algo der erro fatal no meio, não entra em loop.
	update_option('aw_conteudo_versao', AW_CONTEUDO_VERSAO, false);

	if (function_exists('set_time_limit')) {
		@set_time_limit(300); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$GLOBALS['aw_ci_log'] = array('campos' => 0, 'imagens' => 0, 'posts' => 0, 'erros' => array());

	try {
		if ($versao_atual < 1) {
			aw_ci_importar();
		}

		if ($versao_atual < 2) {
			aw_ci_video_hero();
		}

		if ($versao_atual < 3) {
			aw_ci_video_hero(array(
				'home_hero_video'        => 'hero-loop-desktop.mp4',
				'home_hero_video_mobile' => 'hero-loop-mobile.mp4',
				'home_hero_imagem'       => 'hero-loop-poster.jpg',
			));
		}
	} catch (Throwable $e) {
		$GLOBALS['aw_ci_log']['erros'][] = $e->getMessage();
	}

	set_transient('aw_ci_resultado', $GLOBALS['aw_ci_log'], HOUR_IN_SECONDS);
}

/**
 * Aviso no painel com o resultado da importação.
 */
add_action('admin_notices', 'aw_ci_aviso');
function aw_ci_aviso()
{
	$log = get_transient('aw_ci_resultado');

	if (!$log || !current_user_can('manage_options')) {
		return;
	}

	delete_transient('aw_ci_resultado');

	printf(
		'<div class="notice %s is-dismissible"><p><strong>%s</strong> %s</p>%s</div>',
		$log['erros'] ? 'notice-warning' : 'notice-success',
		esc_html__('Conteúdo do Figma importado:', 'alfama-web'),
		esc_html(sprintf(
			/* translators: 1: campos, 2: imagens, 3: posts. */
			__('%1$d campos preenchidos, %2$d imagens na biblioteca de mídia e %3$d posts criados.', 'alfama-web'),
			$log['campos'],
			$log['imagens'],
			$log['posts']
		)),
		$log['erros'] ? '<ul><li>' . implode('</li><li>', array_map('esc_html', $log['erros'])) . '</li></ul>' : ''
	);
}

/**
 * Versão 2: vídeo do hero da home (arquivos otimizados a partir do
 * video-empreendimento.mp4 enviado pelo Igor).
 *
 *  - hero-video-desktop.mp4: 1600×900, H.264, sem áudio, ~3 MB;
 *  - hero-video-mobile.mp4: recorte 4:5 central, 640×800, ~1,2 MB;
 *  - hero-video-poster.jpg: primeiro quadro, 1920×1080 (capa / LCP).
 *
 * A capa substitui a imagem anterior (veio do Figma e não é um quadro deste
 * vídeo, o que daria um "salto" quando o vídeo começa).
 */
function aw_ci_video_hero(array $arquivos = array())
{
	/*
	 * Versão 3 (hero-loop-*): o último segundo do vídeo funde em 1s com o
	 * primeiro, então o fim emenda no começo sem corte. A duração caiu de
	 * 23,2s para 22,2s e a capa passou a ser o quadro de 1s (o novo início).
	 */
	$arquivos = $arquivos ?: array(
		'home_hero_video'        => 'hero-video-desktop.mp4',
		'home_hero_video_mobile' => 'hero-video-mobile.mp4',
		'home_hero_imagem'       => 'hero-video-poster.jpg',
	);

	$home = (int) get_option('page_on_front') ?: aw_ci_pagina('home');

	if (!$home) {
		$GLOBALS['aw_ci_log']['erros'][] = 'Página Home não encontrada para o vídeo do hero.';
		return;
	}

	$campos = array_map('aw_ci_img', $arquivos);

	foreach ($campos as $nome => $id) {
		$chave = aw_ci_chave($nome, $home);

		if (!$id || !$chave) {
			$GLOBALS['aw_ci_log']['erros'][] = sprintf('Não foi possível gravar %s.', $nome);
			continue;
		}

		update_field($chave, $id, $home);
		$GLOBALS['aw_ci_log']['campos']++;
	}
}

/* ===========================================================================
 * Utilitários
 * ======================================================================== */

/**
 * Importa (uma vez) uma imagem de conteudo-inicial/ para a biblioteca.
 *
 * @return int ID do anexo, 0 se falhar.
 */
function aw_ci_img($arquivo, $alt = '')
{
	static $cache = array();

	if (isset($cache[$arquivo])) {
		return $cache[$arquivo];
	}

	$existente = get_posts(array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_aw_ci_arquivo', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $arquivo, // phpcs:ignore WordPress.DB.SlowDBQuery
	));

	if ($existente) {
		return $cache[$arquivo] = (int) $existente[0];
	}

	$origem = AW_DIR . '/conteudo-inicial/' . $arquivo;

	if (!file_exists($origem)) {
		$GLOBALS['aw_ci_log']['erros'][] = 'Imagem não encontrada: ' . $arquivo;
		return $cache[$arquivo] = 0;
	}

	// media_handle_sideload move o arquivo: trabalha numa cópia temporária.
	$tmp = wp_tempnam($arquivo);
	copy($origem, $tmp);

	$id = media_handle_sideload(array('name' => 'cso-' . $arquivo, 'tmp_name' => $tmp), 0, $alt ?: null);

	if (is_wp_error($id)) {
		@unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$GLOBALS['aw_ci_log']['erros'][] = $arquivo . ': ' . $id->get_error_message();
		return $cache[$arquivo] = 0;
	}

	update_post_meta($id, '_aw_ci_arquivo', $arquivo);

	if ($alt) {
		update_post_meta($id, '_wp_attachment_image_alt', $alt);
	}

	$GLOBALS['aw_ci_log']['imagens']++;

	return $cache[$arquivo] = (int) $id;
}

/**
 * Chave ACF de um campo pelo nome, dentro dos grupos que valem para o post.
 */
function aw_ci_chave($nome, $post_id)
{
	static $mapa = array();

	$id_cache = (string) $post_id;

	if (!isset($mapa[$id_cache])) {
		$mapa[$id_cache] = array();
		$filtro = 'option' === $post_id
			? array('options_page' => 'aw-opcoes')
			: array('post_id' => $post_id, 'post_type' => get_post_type($post_id));

		foreach (acf_get_field_groups($filtro) as $grupo) {
			foreach (acf_get_fields($grupo) as $campo) {
				if (!empty($campo['name'])) {
					$mapa[$id_cache][$campo['name']] = $campo['key'];
				}
			}
		}
	}

	return $mapa[$id_cache][$nome] ?? '';
}

/**
 * Grava um campo só se ele ainda estiver vazio.
 */
function aw_ci_set($post_id, $nome, $valor)
{
	if (null === $valor || '' === $valor || array() === $valor) {
		return;
	}

	$chave = aw_ci_chave($nome, $post_id);

	if (!$chave) {
		$GLOBALS['aw_ci_log']['erros'][] = sprintf('Campo "%s" não encontrado em %s', $nome, $post_id);
		return;
	}

	$atual = get_field($chave, $post_id, false);

	if (null !== $atual && '' !== $atual && false !== $atual && array() !== $atual) {
		return;
	}

	update_field($chave, $valor, $post_id);
	$GLOBALS['aw_ci_log']['campos']++;
}

/**
 * Vários campos de uma vez.
 */
function aw_ci_campos($post_id, array $campos)
{
	foreach ($campos as $nome => $valor) {
		aw_ci_set($post_id, $nome, $valor);
	}
}

/**
 * ID de uma página do tema pelo slug (a política vem de Configurações › Privacidade).
 */
function aw_ci_pagina($slug)
{
	if ('politica-de-privacidade' === $slug && (int) get_option('wp_page_for_privacy_policy')) {
		return (int) get_option('wp_page_for_privacy_policy');
	}

	$pagina = get_page_by_path($slug);

	return $pagina ? (int) $pagina->ID : 0;
}

/**
 * Cria (uma vez) um post pelo slug.
 */
function aw_ci_post($tipo, $slug, array $dados)
{
	$existente = get_page_by_path($slug, OBJECT, $tipo);

	if ($existente) {
		return (int) $existente->ID;
	}

	$id = wp_insert_post(array_merge(array(
		'post_type'   => $tipo,
		'post_name'   => $slug,
		'post_status' => 'publish',
	), $dados), true);

	if (is_wp_error($id)) {
		$GLOBALS['aw_ci_log']['erros'][] = $slug . ': ' . $id->get_error_message();
		return 0;
	}

	$GLOBALS['aw_ci_log']['posts']++;

	return (int) $id;
}

function aw_ci_link($url, $titulo, $alvo = '')
{
	return array('url' => $url, 'title' => $titulo, 'target' => $alvo);
}

/* ===========================================================================
 * Conteúdo
 * ======================================================================== */

function aw_ci_importar()
{
	$lorem      = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';
	$lorem_p    = '<p>' . $lorem . ' ' . $lorem . '</p>';
	$endereco   = "Rua da Concórdia, 313 - Queimadinha,\nFeira de Santana - BA, 44050-132";
	$indicadores = array(
		array('numero' => '37', 'rotulo' => 'Anos de mercado'),
		array('numero' => '+400', 'rotulo' => 'Obras públicas'),
		array('numero' => '+5.000', 'rotulo' => 'Unidades habitacionais'),
		array('numero' => '+150', 'rotulo' => 'Escolas construídas'),
	);
	$numeros_insta = array(array('248', '18'), array('194', '12'), array('312', '29'), array('420', '45'));

	/* ------------------------------------------------------------------
	 * Opções do Site
	 * --------------------------------------------------------------- */
	aw_ci_campos('option', array(
		'indices_economicos' => array(
			array('nome' => 'IGP-M', 'valor' => '-0,73%', 'referencia' => '2026'),
			array('nome' => 'INCC', 'valor' => '+0,34%', 'referencia' => '2026'),
		),
		'telefone'           => '(75) 3623-2546',
		'email_contato'      => 'cso@csoengenharia.com',
		'endereco'           => $endereco,
		'instagram_usuario'  => 'csoengenharia',
	));

	/* ------------------------------------------------------------------
	 * Notícias (antes da home, para os cards já existirem)
	 * --------------------------------------------------------------- */
	$noticias = array(
		array('tendencias-de-arquitetura-sustentavel-para-2025', 'Tendências de Arquitetura Sustentável para 2025', 'Arquitetura', 'Descubra como a construção civil está evoluindo com materiais reciclados, eficiência energética e espaços mais saudáveis para morar e trabalhar.', 'blog-arquitetura.jpg', '2025-10-20 09:00:00'),
		array('como-escolher-o-imovel-ideal-para-sua-familia', 'Como Escolher o Imóvel Ideal para Sua Família', 'Imóveis', 'Entenda os principais critérios para encontrar o lar perfeito: localização, segurança, infraestrutura e tipologia adequada para o seu perfil.', 'blog-imoveis.jpg', '2025-10-15 09:00:00'),
		array('cso-engenharia-conquista-certificacao-de-qualidade', 'CSO Engenharia Conquista Certificação de Qualidade', 'Empresa', 'A CSO reforça seu compromisso com a excelência operacional, recebendo reconhecimento nacional.', 'blog-certificacao.jpg', '2025-10-12 09:00:00'),
	);

	foreach ($noticias as $n) {
		$termo = term_exists($n[2], 'category') ?: wp_insert_term($n[2], 'category');
		$cat   = is_array($termo) ? (int) $termo['term_id'] : (is_numeric($termo) ? (int) $termo : 0);

		$id = aw_ci_post('post', $n[0], array(
			'post_title'    => $n[1],
			'post_excerpt'  => $n[3],
			'post_content'  => str_repeat($lorem_p . "\n\n", 5),
			'post_date'     => $n[5],
			'post_category' => $cat ? array($cat) : array(),
		));

		if ($id && !has_post_thumbnail($id)) {
			$img = aw_ci_img($n[4], $n[1]);
			if ($img) {
				set_post_thumbnail($id, $img);
			}
		}
	}

	// O post de exemplo do WordPress empurraria uma notícia do Figma para fora
	// dos 3 cards da home: vai para a lixeira (pode ser restaurado).
	$ola = get_page_by_path('ola-mundo', OBJECT, 'post') ?: get_page_by_path('hello-world', OBJECT, 'post');
	if ($ola && 'publish' === $ola->post_status) {
		wp_trash_post($ola->ID);
	}

	/* ------------------------------------------------------------------
	 * Empreendimentos
	 * --------------------------------------------------------------- */
	$empreendimentos = array(
		'residential-up-sim' => array(
			'titulo' => 'Residential Up SIM', 'status' => 'em-obras', 'ordem' => 1, 'imagem' => 'emp-residential-up-sim.jpg',
			'local' => 'SIM, Feira de Santana', 'vendido' => 83,
			'resumo' => array('Tipologia: 2 e 3 quartos', 'Área: 58m² a 112m²', 'Vagas: 1 a 2', 'Entrega: Dez/2025'),
		),
		'bless-houses' => array(
			'titulo' => 'Bless Houses', 'status' => 'lancamento', 'ordem' => 2, 'imagem' => 'emp-bless-houses.jpg',
			'local' => 'Papagaio, Feira de Santana', 'vendido' => 72,
			'resumo' => array('Tipologia: 2 e 3 quartos', 'Área: 62m² a 98m²', 'Vagas: 1', 'Entrega: Jan/2026'),
		),
		'vienna-future-residence' => array(
			'titulo' => 'Vienna Future Residence', 'status' => 'esgotado', 'ordem' => 3, 'imagem' => 'emp-vienna-future-residence.jpg',
			'local' => 'Santa Mônica, Feira de Santana', 'vendido' => 100,
			'resumo' => array('Tipologia: 2 e 3 quartos', 'Área: 72m² a 120m²', 'Vagas: 1 a 2', 'Entrega: Entregue'),
		),
	);

	$ids_emp = array();

	foreach ($empreendimentos as $slug => $e) {
		$id = aw_ci_post('empreendimento', $slug, array(
			'post_title'   => $e['titulo'],
			'post_excerpt' => $lorem,
			'menu_order'   => $e['ordem'],
		));

		if (!$id) {
			continue;
		}

		$ids_emp[$slug] = $id;

		if (!has_term('', 'status_obra', $id)) {
			wp_set_object_terms($id, $e['status'], 'status_obra');
		}

		$img = aw_ci_img($e['imagem'], $e['titulo']);
		if ($img && !has_post_thumbnail($id)) {
			set_post_thumbnail($id, $img);
		}

		aw_ci_campos($id, array(
			'localizacao_texto'   => $e['local'],
			'percentual_vendido'  => $e['vendido'],
			'diferenciais_resumo' => array_map(static function ($t) {
				return array('titulo' => $t);
			}, $e['resumo']),
		));
	}

	// Página interna completa do frame "empreendimento - interna".
	if (!empty($ids_emp['residential-up-sim'])) {
		$up      = $ids_emp['residential-up-sim'];
		$galeria = aw_ci_img('home-empresa.jpg', 'Áreas comuns do Residential Up SIM');
		$planta  = aw_ci_img('emp-planta.jpg', 'Planta do Residential Up SIM');
		$icone   = aw_ci_img('icone-fitness.png', '');

		aw_ci_campos($up, array(
			'hero_imagem'         => aw_ci_img('emp-residential-up-sim.jpg', 'Residential Up SIM'),
			'diferenciais_titulo' => 'Por que escolher o Residential UP SIM',
			'diferenciais'        => array_fill(0, 4, array('icone' => $icone, 'titulo' => 'Área Fitness')),
			'galeria_titulo'      => 'Áreas Comuns e Externas',
			'galeria'             => $galeria ? array_fill(0, 4, $galeria) : array(),
			'videos_titulo'       => 'Seu futuro começa aqui!',
			// O Figma tem só a capa: o link do vídeo precisa ser cadastrado
			// para a seção aparecer.
			'videos'              => array(array('url' => '', 'capa' => aw_ci_img('emp-video-capa.jpg', 'Vídeo do Residential Up SIM'))),
			'plantas_titulo'      => "Escolha a melhor opção de planta para\na sua família",
			'plantas'             => array(
				array('nome' => 'Studio', 'area' => '28 m²', 'imagem' => $planta),
				array('nome' => '2 Quartos', 'area' => '58 m²', 'imagem' => $planta),
				array('nome' => '3 Quartos', 'area' => '78 m²', 'imagem' => $planta),
				array('nome' => 'Duplex', 'area' => '95 m²', 'imagem' => $planta),
				array('nome' => 'Cobertura', 'area' => '120 m²', 'imagem' => $planta),
			),
			'obra_titulo'         => 'Acompanhe a evolução do seu futuro lar',
			'obra_imagem'         => aw_ci_img('emp-obra.jpg', 'Obra do Residential Up SIM'),
			'obra_atualizacao'    => '20260801',
			'obra_etapas'         => array(
				array('etapa' => 'Fundação', 'percentual' => 100),
				array('etapa' => 'Estrutura', 'percentual' => 100),
				array('etapa' => 'Alvenaria', 'percentual' => 60),
				array('etapa' => 'Instalações', 'percentual' => 0),
				array('etapa' => 'Acabamento', 'percentual' => 0),
			),
			'local_titulo'        => 'Tudo que você precisa em até 100m² de casa',
			'local_texto'         => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
			'local_endereco'      => 'SIM, Feira de Santana - BA',
			'mapa_imagem'         => aw_ci_img('emp-mapa.jpg', 'Mapa da localização'),
		));
	}

	/* ------------------------------------------------------------------
	 * Home
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('home')) {
		$insta = array();
		foreach (array('instagram-1.jpg', 'instagram-2.jpg', 'instagram-3.jpg', 'instagram-4.jpg') as $i => $arq) {
			$insta[] = array('imagem' => aw_ci_img($arq, ''), 'link' => '', 'curtidas' => $numeros_insta[$i][0], 'comentarios' => $numeros_insta[$i][1]);
		}

		$depoimentos = array(
			array('texto' => 'Escolhi a CSO Engenharia para realizar meu sonho da casa própria e não poderia ter feito melhor escolha. O atendimento foi transparente do início ao fim e cada etapa da obra transmitiu confiança e profissionalismo.', 'nome' => 'Juliana & Roberto Silva', 'projeto' => 'Bless Houses • Papagaio'),
			array('texto' => 'A equipe da CSO Engenharia superou minhas expectativas. O projeto foi entregue dentro do prazo, com qualidade impecável e sempre respeitando minhas necessidades e ideias.', 'nome' => 'Carlos Eduardo Menezes', 'projeto' => 'Vienna Future Residence • Santa Mônica'),
			array('texto' => 'Senti segurança em cada detalhe, eles mostraram seriedade e compromisso, transformando o que parecia complicado em uma experiência tranquila e gratificante, obrigado CSO!', 'nome' => 'Dra. Mariana Costa', 'projeto' => 'Residential Up SIM • SIM'),
		);

		aw_ci_campos($pid, array(
			'home_hero_titulo'         => "Solidez que transforma\no horizonte baiano.",
			'home_hero_texto'          => 'Desde 1989, unindo engenharia de precisão e a sofisticação que sua vida merece. Nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues.',
			'home_hero_botao'          => aw_ci_link(aw_url_empreendimentos('lancamento'), 'Conhecer lançamentos'),
			'home_hero_imagem'         => aw_ci_img('home-hero.jpg', ''),
			'home_emp_kicker'          => 'Portfólio',
			'home_emp_titulo'          => 'Nossos Empreendimentos',
			'home_emp_botao'           => aw_ci_link(aw_url_empreendimentos(), 'Ver todos os empreendimentos'),
			'home_empresa_kicker'      => 'A empresa',
			'home_empresa_titulo'      => "Nossos verbos preferidos\nsão desenhar e otimizar.",
			'home_empresa_texto'       => 'Somos referência em construção civil de alto padrão, entregando precisão estrutural e solidez absoluta em cada metro quadrado. Cada uma de nossas obras é planejada detalhadamente, mantendo o mesmo cuidado artesanal e engenharia rigorosa para garantir a sofisticação e segurança de quem vai viver ali.',
			'home_empresa_botao'       => aw_ci_link(aw_url_pagina('quem-somos'), 'Leia mais sobre a empresa'),
			'home_empresa_imagem'      => aw_ci_img('home-empresa.jpg', 'Sede da CSO Engenharia'),
			'home_empresa_anos'        => '+35',
			'home_empresa_anos_rotulo' => 'Anos de história',
			'home_indicadores_kicker'  => 'Desempenho',
			'home_indicadores_titulo'  => 'Indicadores e Relevância',
			'home_indicadores'         => $indicadores,
			'home_depoimentos_kicker'  => 'Depoimentos',
			'home_depoimentos_titulo'  => 'O que dizem nossos clientes',
			'home_depoimentos'         => $depoimentos,
			'home_blog_kicker'         => 'Blog',
			'home_blog_titulo'         => 'Últimas Notícias',
			'home_blog_botao'          => aw_ci_link(aw_url_blog(), 'Ver todas as notícias'),
			'home_instagram_kicker'    => 'Instagram',
			'home_instagram_titulo'    => 'Acompanhe nossas novidades',
			'home_instagram'           => $insta,
		));
	}

	/* ------------------------------------------------------------------
	 * Quem Somos
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('quem-somos')) {
		$historia = array(
			array('logo' => '', 'periodo' => '1989 à 1992', 'texto' => "Feira de Santana, década de 80; em 24 de julho de 1989 nasce a Construtora Souza Oliveira.\n\nCom sede na Av. Maria Quitéria, a empresa ainda dava seus primeiros passos.\n\nEm 1991, passa a se chamar CSO Engenharia Ltda. e a sede é transferida para a Av. Sampaio."),
			array('logo' => '', 'periodo' => '1992 à 2008', 'texto' => "Os cubos foram proposta de uma gráfica que foi contratada na época para confeccionar a papelaria da empresa, desde então os cubos azuis passaram a ser marca registrada da CSO.\n\nSempre em evolução, a empresa cresceu em mercado e tamanho, as salas alugadas na Av. Sampaio já não eram o suficiente. Em 1997 veio a mudança para o prédio na Rua da Concórdia."),
			array('logo' => '', 'periodo' => '2008 à 2013', 'texto' => "Uma nova perspectiva é sempre bem vinda. Esse é um dos segredos para se chegar tão longe. A empresa que até pouco tempo tinha seu foco em obras públicas, já firmava sua marca no competitivo mercado imobiliário.\n\nAcompanhando as mudanças, a logomarca ganhou um aspecto mais sólido e firme, valores que foram cruciais para enfrentar os novos e constantes desafios."),
		);
		$logos = array('logo-1989.png', 'logo-1992.png', 'logo-2008.png');
		foreach ($historia as $i => $marco) {
			$historia[$i]['logo'] = aw_ci_img($logos[$i], 'Logomarca da CSO');
		}

		$blocos = array(
			array(
				'imagem' => '',
				'kicker' => 'Responsabilidade social',
				'titulo' => 'Garantimos cumprir nosso papel como uma empresa cidadã',
				'texto'  => '<p>Visando contribuir para a construção de um mundo melhor, a CSO Engenharia busca sempre cumprir seu papel de empresa cidadã, realizando junto com o SESI, desde 2011, o projeto Cidadão Solidário, que tem como objetivos específicos proporcionar para 100 crianças e adolescentes momentos de formação em cidadania e dos fundamentos do esporte; oferecer cursos de orientação profissional e uso consciente do orçamento doméstico para 50 adultos; articular parcerias entre SESI, empresa e comunidade. Apoiar as ações educativas da Escola API; e disseminar internamente os valores e ações sociais da empresa. O local do Projeto foi escolhido pela CSO Engenharia, por estar localizado no mesmo bairro da empresa (Queimadinha), bairro este que apresenta um alto índice de vulnerabilidade social. O Projeto está sendo realizado na Escola Estadual de Proteção à Infância (API), sediada nas instalações da Associação de Proteção à Infância.</p><p>A CSO também estimula a participação de seus colaboradores em atividades de cunho social, em trabalhos voluntários e outras atividades que tenham como propósito a melhoria da qualidade de vida da comunidade.</p>',
			),
			array(
				'imagem' => '',
				'kicker' => 'Política de qualidade',
				'titulo' => 'A melhor experiência e atendimento para os nossos clientes',
				'texto'  => '<p>A CSO Engenharia tem como compromisso entregar produtos com qualidade, nos prazos pactuados, promovendo a melhoria contínua de seus processos e o atendimento de todos os requisitos legais, normativos e entre outros aplicáveis, visando à satisfação de seus clientes e a sustentabilidade.</p>',
			),
		);
		$blocos[0]['imagem'] = aw_ci_img('qs-responsabilidade.jpg', 'Bairro da Queimadinha, Feira de Santana');
		$blocos[1]['imagem'] = aw_ci_img('qs-empresa.jpg', 'Vista aérea de Feira de Santana');

		$insta = array();
		foreach (array('instagram-1.jpg', 'instagram-2.jpg', 'instagram-3.jpg', 'instagram-5.jpg') as $i => $arq) {
			$insta[] = array('imagem' => aw_ci_img($arq, ''), 'link' => '', 'curtidas' => $numeros_insta[$i][0], 'comentarios' => $numeros_insta[$i][1]);
		}

		aw_ci_campos($pid, array(
			'qs_hero_titulo'           => 'Conheça a CSO Engenharia',
			'qs_hero_texto'            => 'Desde 1989, unindo engenharia de precisão e a sofisticação que sua vida merece. Nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues.',
			'qs_hero_botao'            => aw_ci_link(aw_url_empreendimentos(), 'Ver Empreendimentos'),
			'qs_hero_imagem'           => aw_ci_img('blog-arquitetura.jpg', ''),
			'qs_indicadores_kicker'    => 'Números de destaque',
			'qs_indicadores_titulo'    => 'Indicadores e Relevância',
			'qs_indicadores'           => $indicadores,
			'qs_empresa_kicker'        => 'A empresa',
			'qs_empresa_titulo'        => "Nossos verbos preferidos\nsão desenhar e otimizar.",
			'qs_empresa_texto'         => '<p>Somos referência em construção civil de alto padrão, entregando precisão estrutural e solidez absoluta em cada metro quadrado. Cada uma de nossas obras é planejada detalhadamente, mantendo o mesmo cuidado artesanal e engenharia rigorosa para garantir a sofisticação e segurança de quem vai viver ali.</p><p>Desde 1989, nossos verbos preferidos são construir, sonhar e otimizar. Ao longo dessa trajetória, nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues. Essa história, edificada com CREDIBILIDADE, SOLIDEZ e OBJETIVIDADE, continua agora com você.</p>',
			'qs_empresa_imagem'        => aw_ci_img('qs-empresa.jpg', 'Vista aérea de Feira de Santana'),
			'qs_empresa_anos'          => '+35',
			'qs_empresa_anos_rotulo'   => 'Anos de história',
			'qs_historia_kicker'       => 'Nossa história',
			'qs_historia_titulo'       => 'História e Evolução da Marca',
			'qs_historia'              => $historia,
			'qs_blocos'                => $blocos,
			'qs_instagram_kicker'      => 'Instagram',
			'qs_instagram_titulo'      => 'Acompanhe nossas novidades',
			'qs_instagram'             => $insta,
		));
	}

	/* ------------------------------------------------------------------
	 * Blog
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('blog')) {
		aw_ci_campos($pid, array(
			'blog_hero_titulo' => 'Notícias de Engenharia',
			'blog_hero_texto'  => $lorem,
			'blog_hero_botao'  => aw_ci_link(aw_url_empreendimentos(), 'Ver Empreendimentos'),
			'blog_hero_imagem' => aw_ci_img('blog-imoveis.jpg', ''),
			'blog_kicker'      => 'Blog',
			'blog_titulo'      => 'Fique por dentro das notícias',
		));
	}

	/* ------------------------------------------------------------------
	 * Empreendimentos (página)
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('empreendimentos')) {
		$bless = $ids_emp['bless-houses'] ?? 0;

		aw_ci_campos($pid, array(
			'emp_hero_destaque' => $bless,
			'emp_hero_titulo'   => 'Bless Houses',
			'emp_hero_texto'    => $lorem,
			'emp_hero_botao'    => $bless ? aw_ci_link(get_permalink($bless), 'Quero saber mais') : null,
			'emp_hero_imagem'   => aw_ci_img('emp-bless-houses.jpg', 'Bless Houses'),
			'emp_kicker'        => 'Empreendimentos',
			'emp_titulo'        => 'As melhores opções para você',
		));
	}

	/* ------------------------------------------------------------------
	 * Contato
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('contato')) {
		aw_ci_campos($pid, array(
			'contato_hero_titulo'    => 'CSO Atendimento',
			'contato_hero_botao'     => aw_ci_link('#formulario', 'Entre em contato'),
			'contato_hero_imagem'    => aw_ci_img('contato-hero.jpg', 'Equipe de atendimento da CSO Engenharia'),
			'contato_canais_kicker'  => 'Central de relacionamento',
			'contato_canais_titulo'  => 'Principais contatos',
			'contato_canais'         => array(
				array('tipo' => 'telefone', 'titulo' => 'Nossos Telefones', 'itens' => array(
					array('rotulo' => 'Atendimento e Vendas', 'valor' => '(75) 3623-2546'),
					array('rotulo' => 'Suporte', 'valor' => '(75) 3623-2546'),
				)),
				array('tipo' => 'email', 'titulo' => 'Email', 'itens' => array(
					array('rotulo' => 'Corporativo', 'valor' => 'cso@csoengenharia.com'),
					array('rotulo' => 'Dúvidas', 'valor' => 'cso2@csoengenharia.com'),
				)),
				array('tipo' => 'endereco', 'titulo' => 'Localização', 'itens' => array(
					array('rotulo' => 'Endereço', 'valor' => 'Rua da Concórdia, 313 - Queimadinha, Feira de Santana - BA, 44050-132'),
				)),
			),
			'contato_form_kicker'    => 'Envie sua mensagem',
			'contato_form_titulo'    => 'Nossa equipe responderá o mais breve possível',
			'contato_form_subtitulo' => 'Preencha as informações abaixo',
		));
	}

	/* ------------------------------------------------------------------
	 * Política de privacidade (texto do Figma no conteúdo da página)
	 * --------------------------------------------------------------- */
	if ($pid = aw_ci_pagina('politica-de-privacidade')) {
		aw_ci_campos($pid, array(
			'politica_kicker' => 'Política de privacidade',
			'politica_titulo' => 'Política de Privacidade e Segurança',
		));

		// O texto anterior fica nas revisões da página.
		wp_update_post(array(
			'ID'           => $pid,
			'post_content' => str_repeat($lorem_p . "\n\n", 5),
		));
	}
}
