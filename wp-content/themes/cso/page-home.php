<?php
/**
 * Template Name: Home
 *
 * Home CSO — fiel ao frame "home" do Figma (1600px, conteúdo em 1200px):
 *
 *   1. hero-section            vídeo/imagem + overlay #0D1424 55%, 600px
 *   2. empreendimentos-section cards com filtro por status (Todos/Lançamentos/Em Obras/100% Vendido)
 *   3. quem-somos-section      texto + imagem com selo "+35" e 4 indicadores
 *   4. secao-depoimentos       carrossel (Swiper) com setas
 *   5. secao-blog              3 últimos posts
 *   6. secao-instagram         4 posts (template-parts/cso-instagram.php)
 *
 * Todo texto vem de campos ACF da própria página (prefixo home_) e cai no
 * conteúdo do layout quando o campo está vazio — a página nunca quebra antes
 * de o conteúdo ser cadastrado. Estilos em assets/css/home.css (exclusivos) e
 * assets/css/style.css (.pagina-cso: hero, empresa, indicadores, Instagram).
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

$pid         = get_the_ID();
$placeholder = AW_IMG . 'placeholder.svg';

/**
 * Normaliza um campo ACF "link" (array) ou URL simples.
 */
$link = static function ($valor, $rotulo_padrao, $url_padrao) {
	if (is_array($valor) && !empty($valor['url'])) {
		return array(
			'url'    => $valor['url'],
			'rotulo' => $valor['title'] ?: $rotulo_padrao,
			'alvo'   => $valor['target'] ?? '',
		);
	}

	return array('url' => $valor ?: $url_padrao, 'rotulo' => $rotulo_padrao, 'alvo' => '');
};

/**
 * URL de um campo ACF de imagem (array, ID ou URL), com fallback.
 */
$img = static function ($valor, $tamanho = 'large', $fallback = '') {
	if (is_array($valor) && !empty($valor['ID'])) {
		return wp_get_attachment_image_url($valor['ID'], $tamanho) ?: $valor['url'];
	}

	if (is_numeric($valor)) {
		return wp_get_attachment_image_url((int) $valor, $tamanho) ?: $fallback;
	}

	return $valor ?: $fallback;
};

/* ---------------------------------------------------------------------------
 * 1. Hero
 * ------------------------------------------------------------------------ */
$hero_titulo = aw_field('home_hero_titulo', $pid, "Solidez que transforma\no horizonte baiano.");
$hero_texto  = aw_field('home_hero_texto', $pid, 'Desde 1989, unindo engenharia de precisão e a sofisticação que sua vida merece. Nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues.');
$hero_botao  = $link(aw_field('home_hero_botao', $pid, ''), __('Conhecer lançamentos', 'alfama-web'), aw_url_empreendimentos('lancamento'));
// Vídeo: arquivo próprio na biblioteca (MP4 H.264, sem áudio). A versão
// mobile é opcional (recorte vertical, mais leve); sem ela, o mobile usa a
// de desktop. Quem carrega e toca é iniciarVideoHero() em assets/js/app.js.
$arquivo_url = static function ($valor) {
	if (is_array($valor)) {
		return $valor['url'] ?? '';
	}

	return is_numeric($valor) ? (string) wp_get_attachment_url((int) $valor) : (string) $valor;
};

$hero_video        = $arquivo_url(aw_field('home_hero_video', $pid, ''));
$hero_video_mobile = $arquivo_url(aw_field('home_hero_video_mobile', $pid, ''));

// Capa: primeiro quadro do vídeo. É a imagem do LCP — sai como <picture>
// com fetchpriority=high e fica por baixo do vídeo até ele começar a tocar.
$hero_capa    = aw_field('home_hero_imagem', $pid, '');
$hero_capa_id = is_array($hero_capa) ? (int) ($hero_capa['ID'] ?? 0) : (is_numeric($hero_capa) ? (int) $hero_capa : 0);
$hero_imagem  = $img($hero_capa, 'aw-hero', '');
$hero_imagem_mobile = $hero_capa_id ? (wp_get_attachment_image_url($hero_capa_id, 'aw-hero-mobile') ?: $hero_imagem) : $hero_imagem;

/* ---------------------------------------------------------------------------
 * 2. Empreendimentos
 * ------------------------------------------------------------------------ */
$filtros = array(
	'todos'      => __('Todos', 'alfama-web'),
	'lancamento' => __('Lançamentos', 'alfama-web'),
	'em-obras'   => __('Em Obras', 'alfama-web'),
	'esgotado'   => __('100% Vendido', 'alfama-web'),
);

$empreendimentos = array();
$q_emp = new WP_Query(array(
	'post_type'      => 'empreendimento',
	'posts_per_page' => 9,
	'no_found_rows'  => true,
	'orderby'        => array('menu_order' => 'ASC', 'date' => 'DESC'),
));

foreach ($q_emp->posts as $emp) {
	$termos   = get_the_terms($emp->ID, 'status_obra');
	$termo    = ($termos && !is_wp_error($termos)) ? $termos[0] : null;
	$esgotado = aw_esta_esgotado($emp->ID);
	$difs     = aw_field('diferenciais_resumo', $emp->ID, array());

	$empreendimentos[] = array(
		'titulo'      => get_the_title($emp),
		'link'        => get_permalink($emp),
		'imagem'      => get_the_post_thumbnail_url($emp, 'large') ?: $placeholder,
		'local'       => aw_field('localizacao_texto', $emp->ID, ''),
		'status'      => $termo ? $termo->slug : '',
		'status_nome' => $termo ? $termo->name : '',
		'vendido'     => $esgotado ? 100 : min(100, max(0, (int) aw_field('percentual_vendido', $emp->ID, 0))),
		'detalhes'    => is_array($difs) ? array_filter(array_map(static function ($d) {
			return is_array($d) ? ($d['titulo'] ?? '') : (string) $d;
		}, $difs)) : array(),
	);
}
wp_reset_postdata();

// Conteúdo do layout enquanto não há empreendimentos publicados.
if (!$empreendimentos) {
	$empreendimentos = array(
		array('titulo' => 'Residential Up SIM', 'link' => aw_url_empreendimentos(), 'imagem' => $placeholder, 'local' => 'SIM, Feira de Santana', 'status' => 'em-obras', 'status_nome' => 'Em obras', 'vendido' => 83, 'detalhes' => array('Apartamentos de 2 e 3 quartos', 'Área de lazer completa', 'Varanda gourmet')),
		array('titulo' => 'Bless Houses', 'link' => aw_url_empreendimentos(), 'imagem' => $placeholder, 'local' => 'Papagaio, Feira de Santana', 'status' => 'lancamento', 'status_nome' => 'Lançamento', 'vendido' => 72, 'detalhes' => array('Casas em condomínio fechado', 'Piscina e paisagismo', 'Segurança 24h')),
		array('titulo' => 'Vienna Future Residence', 'link' => aw_url_empreendimentos(), 'imagem' => $placeholder, 'local' => 'Santa Mônica, Feira de Santana', 'status' => 'esgotado', 'status_nome' => '100% vendido', 'vendido' => 100, 'detalhes' => array('Duas torres residenciais', 'Rooftop com vista panorâmica', 'Entregue')),
	);
}

/* ---------------------------------------------------------------------------
 * 3. Quem somos + indicadores
 * ------------------------------------------------------------------------ */
$emp_kicker = aw_field('home_empresa_kicker', $pid, __('A empresa', 'alfama-web'));
$emp_titulo = aw_field('home_empresa_titulo', $pid, "Nossos verbos preferidos\nsão desenhar e otimizar.");
$emp_texto  = aw_field('home_empresa_texto', $pid, 'Somos referência em construção civil de alto padrão, entregando precisão estrutural e solidez absoluta em cada metro quadrado. Cada uma de nossas obras é planejada detalhadamente, mantendo o mesmo cuidado artesanal e engenharia rigorosa para garantir a sofisticação e segurança de quem vai viver ali.');
$emp_botao  = $link(aw_field('home_empresa_botao', $pid, ''), __('Leia mais sobre a empresa', 'alfama-web'), aw_url_pagina('quem-somos'));
$emp_imagem = $img(aw_field('home_empresa_imagem', $pid, ''), 'large', $placeholder);
$emp_anos   = aw_field('home_empresa_anos', $pid, '+35');
$emp_anos_r = aw_field('home_empresa_anos_rotulo', $pid, __('Anos de história', 'alfama-web'));


/* ---------------------------------------------------------------------------
 * 4. Depoimentos
 * ------------------------------------------------------------------------ */
$depoimentos = aw_field('home_depoimentos', $pid, array());
if (!is_array($depoimentos) || !$depoimentos) {
	$depoimentos = array(
		array('texto' => 'Escolhi a CSO Engenharia para realizar meu sonho da casa própria e não poderia ter feito melhor escolha. O atendimento foi transparente do início ao fim e cada etapa da obra transmitiu confiança e profissionalismo.', 'nome' => 'Juliana & Roberto Silva', 'projeto' => 'Bless Houses • Papagaio'),
		array('texto' => 'A equipe da CSO Engenharia superou minhas expectativas. O projeto foi entregue dentro do prazo, com qualidade impecável e sempre respeitando minhas necessidades e ideias.', 'nome' => 'Carlos Eduardo Menezes', 'projeto' => 'Vienna Future Residence • Santa Mônica'),
		array('texto' => 'Senti segurança em cada detalhe, eles mostraram seriedade e compromisso, transformando o que parecia complicado em uma experiência tranquila e gratificante, obrigado CSO!', 'nome' => 'Dra. Mariana Costa', 'projeto' => 'Residential Up SIM • SIM'),
	);
}

/* ---------------------------------------------------------------------------
 * 5. Blog
 * ------------------------------------------------------------------------ */
$posts_blog = array();
$q_blog = new WP_Query(array(
	'post_type'           => 'post',
	'posts_per_page'      => 3,
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
));

foreach ($q_blog->posts as $post_blog) {
	$cats = get_the_category($post_blog->ID);

	$posts_blog[] = array(
		'titulo'    => get_the_title($post_blog),
		'link'      => get_permalink($post_blog),
		'imagem'    => get_the_post_thumbnail_url($post_blog, 'aw-card') ?: $placeholder,
		'categoria' => $cats ? $cats[0]->name : '',
		'resumo'    => aw_resumo($post_blog->ID, 130),
	);
}
wp_reset_postdata();

if (!$posts_blog) {
	$posts_blog = array(
		array('titulo' => 'Tendências de Arquitetura Sustentável para 2025', 'link' => aw_url_blog(), 'imagem' => $placeholder, 'categoria' => 'Arquitetura', 'resumo' => 'Descubra como a construção civil está evoluindo com materiais reciclados, eficiência energética e espaços mais saudáveis para morar e trabalhar.'),
		array('titulo' => 'Como Escolher o Imóvel Ideal para Sua Família', 'link' => aw_url_blog(), 'imagem' => $placeholder, 'categoria' => 'Imóveis', 'resumo' => 'Entenda os principais critérios para encontrar o lar perfeito: localização, segurança, infraestrutura e tipologia adequada para o seu perfil.'),
		array('titulo' => 'CSO Engenharia Conquista Certificação de Qualidade', 'link' => aw_url_blog(), 'imagem' => $placeholder, 'categoria' => 'Empresa', 'resumo' => 'A CSO reforça seu compromisso com a excelência operacional, recebendo reconhecimento nacional.'),
	);
}

?>
<main id="conteudo" class="pagina-cso home">

	<!-- 1. Hero ============================================================ -->
	<section class="hero" aria-labelledby="hero-titulo">
		<div class="hero__midia" aria-hidden="true">
			<?php if ($hero_imagem) : ?>
				<picture>
					<?php if ($hero_imagem_mobile !== $hero_imagem) : ?>
						<source media="(max-width: 767.98px)" srcset="<?php echo esc_url($hero_imagem_mobile); ?>">
					<?php endif; ?>
					<img class="hero__video hero__capa" src="<?php echo esc_url($hero_imagem); ?>" alt="" fetchpriority="high" decoding="async">
				</picture>
			<?php endif; ?>

			<?php if ($hero_video || $hero_video_mobile) : ?>
				<?php /* Sem src e sem autoplay de propósito: o JS escolhe o arquivo e só baixa depois do load. */ ?>
				<video class="hero__video hero__player" muted loop playsinline preload="none" tabindex="-1"
					disablepictureinpicture disableremoteplayback
					data-aw-hero-video
					data-src-desktop="<?php echo esc_url($hero_video ?: $hero_video_mobile); ?>"
					data-src-mobile="<?php echo esc_url($hero_video_mobile ?: $hero_video); ?>"></video>
			<?php endif; ?>
		</div>

		<div class="container hero__inner">
			<div class="hero__conteudo">
				<div class="hero__textos">
					<h1 class="hero__titulo" id="hero-titulo"><?php echo nl2br(esc_html($hero_titulo)); ?></h1>
					<p class="hero__subtitulo"><?php echo esc_html($hero_texto); ?></p>
				</div>

				<div class="hero__ctas">
					<a href="<?php echo esc_url($hero_botao['url']); ?>" class="btn btn--primario btn--lg"<?php echo $hero_botao['alvo'] ? ' target="' . esc_attr($hero_botao['alvo']) . '"' : ''; ?>>
						<?php echo esc_html($hero_botao['rotulo']); ?>
					</a>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Empreendimentos ================================================= -->
	<section class="secao secao--empreendimentos" id="empreendimentos" aria-labelledby="emp-titulo" data-aw-abas>
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('home_emp_kicker', $pid, __('Portfólio', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao titulo-secao--emp" id="emp-titulo"><?php echo esc_html(aw_field('home_emp_titulo', $pid, __('Nossos Empreendimentos', 'alfama-web'))); ?></h2>
				</div>

				<div class="abas" role="tablist" aria-label="<?php esc_attr_e('Filtrar empreendimentos por status', 'alfama-web'); ?>">
					<?php $primeira = true; foreach ($filtros as $slug => $rotulo) : ?>
						<button type="button" class="abas__item<?php echo $primeira ? ' is-ativa' : ''; ?>" role="tab"
							aria-selected="<?php echo $primeira ? 'true' : 'false'; ?>" data-aw-aba="<?php echo esc_attr($slug); ?>">
							<?php echo esc_html($rotulo); ?>
						</button>
					<?php $primeira = false; endforeach; ?>
				</div>
			</header>

			<div class="emp-grid" data-aw-abas-alvo data-limite="3">
				<?php foreach ($empreendimentos as $i => $emp) : ?>
					<?php get_template_part('template-parts/card-empreendimento', null, array('dados' => $emp, 'oculto' => $i >= 3)); ?>
				<?php endforeach; ?>

				<p class="emp-grid__vazio" data-aw-abas-vazio hidden><?php esc_html_e('Nenhum empreendimento nesta categoria no momento.', 'alfama-web'); ?></p>
			</div>

			<?php $lista_botao = $link(aw_field('home_emp_botao', $pid, ''), __('Ver todos os empreendimentos', 'alfama-web'), aw_url_empreendimentos()); ?>
			<div class="secao__acoes">
				<a href="<?php echo esc_url($lista_botao['url']); ?>" class="btn btn--contorno"<?php echo $lista_botao['alvo'] ? ' target="' . esc_attr($lista_botao['alvo']) . '"' : ''; ?>><?php echo esc_html($lista_botao['rotulo']); ?></a>
			</div>
		</div>
	</section>

	<!-- 3. Quem somos ====================================================== -->
	<section class="secao secao--empresa" id="a-empresa" aria-labelledby="empresa-titulo">
		<div class="container">
			<div class="empresa">
				<div class="empresa__texto">
					<div class="empresa__titulos">
						<p class="kicker"><?php echo esc_html($emp_kicker); ?></p>
						<h2 class="titulo-secao titulo-secao--empresa" id="empresa-titulo"><?php echo nl2br(esc_html($emp_titulo)); ?></h2>
					</div>

					<div class="empresa__descricao"><?php echo wp_kses_post(wpautop($emp_texto)); ?></div>

					<hr class="empresa__divisor">

					<div>
						<a href="<?php echo esc_url($emp_botao['url']); ?>" class="btn btn--primario btn--lg"><?php echo esc_html($emp_botao['rotulo']); ?></a>
					</div>
				</div>

				<div class="empresa__visual">
					<img class="empresa__imagem" src="<?php echo esc_url($emp_imagem); ?>" alt="" width="587" height="500" loading="lazy" decoding="async">

					<div class="empresa__selo">
						<strong class="empresa__selo-numero"><?php echo esc_html($emp_anos); ?></strong>
						<span class="empresa__selo-rotulo"><?php echo esc_html($emp_anos_r); ?></span>
					</div>
				</div>
			</div>

			<?php get_template_part('template-parts/cso-indicadores', null, array(
				'kicker' => aw_field('home_indicadores_kicker', $pid, __('Desempenho', 'alfama-web')),
				'titulo' => aw_field('home_indicadores_titulo', $pid, __('Indicadores e Relevância', 'alfama-web')),
				'itens'  => aw_field('home_indicadores', $pid, array()),
			)); ?>
		</div>
	</section>

	<!-- 4. Depoimentos ===================================================== -->
	<section class="secao secao--depoimentos" aria-labelledby="depoimentos-titulo">
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('home_depoimentos_kicker', $pid, __('Depoimentos', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao titulo-secao--claro" id="depoimentos-titulo"><?php echo esc_html(aw_field('home_depoimentos_titulo', $pid, __('O que dizem nossos clientes', 'alfama-web'))); ?></h2>
				</div>

				<div class="setas">
					<button type="button" class="setas__btn depoimentos-anterior" aria-label="<?php esc_attr_e('Depoimento anterior', 'alfama-web'); ?>">
						<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
					<button type="button" class="setas__btn depoimentos-proximo" aria-label="<?php esc_attr_e('Próximo depoimento', 'alfama-web'); ?>">
						<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				</div>
			</header>

			<div class="swiper depoimentos" data-aw-swiper='{"slidesPerView":1,"spaceBetween":24,"navigation":{"prevEl":".depoimentos-anterior","nextEl":".depoimentos-proximo"},"pagination":{"el":".depoimentos-pontos","clickable":true},"breakpoints":{"768":{"slidesPerView":2},"992":{"slidesPerView":3}}}'>
				<div class="swiper-wrapper">
					<?php foreach ($depoimentos as $depoimento) : ?>
						<div class="swiper-slide">
							<figure class="depoimento">
								<div class="depoimento__topo">
									<?php echo aw_icone('quote', array('class' => 'depoimento__aspas')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<blockquote class="depoimento__texto"><p><?php echo esc_html($depoimento['texto'] ?? ''); ?></p></blockquote>
								</div>
								<figcaption class="depoimento__autor">
									<strong class="depoimento__nome"><?php echo esc_html($depoimento['nome'] ?? ''); ?></strong>
									<?php if (!empty($depoimento['projeto'])) : ?>
										<span class="depoimento__projeto"><?php echo esc_html($depoimento['projeto']); ?></span>
									<?php endif; ?>
								</figcaption>
							</figure>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="pontos pontos--mobile depoimentos-pontos"></div>
		</div>
	</section>

	<!-- 5. Blog ============================================================ -->
	<section class="secao secao--blog" aria-labelledby="blog-titulo">
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('home_blog_kicker', $pid, __('Blog', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao" id="blog-titulo"><?php echo esc_html(aw_field('home_blog_titulo', $pid, __('Últimas Notícias', 'alfama-web'))); ?></h2>
				</div>
				<?php $blog_botao = $link(aw_field('home_blog_botao', $pid, ''), __('Ver todas as notícias', 'alfama-web'), aw_url_blog()); ?>
				<a href="<?php echo esc_url($blog_botao['url']); ?>" class="btn btn--contorno btn--blog"<?php echo $blog_botao['alvo'] ? ' target="' . esc_attr($blog_botao['alvo']) . '"' : ''; ?>><?php echo esc_html($blog_botao['rotulo']); ?></a>
			</header>

			<div class="blog-grid" id="home-blog-grid">
				<?php foreach ($posts_blog as $post_blog) : ?>
					<?php get_template_part('template-parts/card-post', null, array('dados' => $post_blog)); ?>
				<?php endforeach; ?>
			</div>
			<?php /* Mobile: a grade vira carrossel por rolagem (home.css) e as bolinhas são montadas em app.js. */ ?>
			<div class="pontos pontos--mobile" data-aw-pontos="#home-blog-grid"></div>
		</div>
	</section>

	<!-- 6. Instagram ======================================================= -->
	<?php get_template_part('template-parts/cso-instagram', null, array(
		'kicker' => aw_field('home_instagram_kicker', $pid, __('Instagram', 'alfama-web')),
		'titulo' => aw_field('home_instagram_titulo', $pid, __('Acompanhe nossas novidades', 'alfama-web')),
		'posts'  => aw_field('home_instagram', $pid, array()),
	)); ?>

</main>
<?php
get_footer();
