<?php
/**
 * Blog — fiel ao frame "blog" do Figma (1600px, conteúdo 1200px):
 *
 *   1. hero-section   imagem + overlay #0D1424 55%, 450px (.hero--interna)
 *   2. secao-blog     kicker + título, abas por categoria, grade 3×3 de
 *                     cards (template-parts/card-post.php) e paginação numérica
 *
 * Filtro e paginação usam o fluxo do tema: primeira carga no servidor via
 * AW_Query_Service e as seguintes por AJAX (ação aw_filtrar_posts, form
 * [data-aw-filtro] tratado em assets/js/app.js). O card é o mesmo nos dois.
 *
 * A página aceita ?categoria={slug} e /page/N/ para chegar já filtrada ou
 * paginada. Textos por ACF (prefixo blog_) com fallback para o layout.
 * Estilos: assets/css/blog.css + assets/css/style.css (.pagina-cso, .post-card).
 *
 * Importante: esta página NÃO deve ser a "Página de posts" em Configurações >
 * Leitura — nesse caso o WordPress usa index.php e ignora este template.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

$pid = get_the_ID();

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
$hero_titulo = aw_field('blog_hero_titulo', $pid, 'Notícias de Engenharia');
$hero_texto  = aw_field('blog_hero_texto', $pid, 'Acompanhe as novidades da CSO Engenharia, as tendências da construção civil e dicas para escolher o imóvel ideal para você e sua família.');
$hero_botao  = aw_field('blog_hero_botao', $pid, '');
$hero_botao  = is_array($hero_botao) && !empty($hero_botao['url'])
	? array('url' => $hero_botao['url'], 'rotulo' => $hero_botao['title'] ?: __('Ver Empreendimentos', 'alfama-web'))
	: array('url' => aw_url_empreendimentos(), 'rotulo' => __('Ver Empreendimentos', 'alfama-web'));
$hero_imagem = $img(aw_field('blog_hero_imagem', $pid, ''), 'aw-hero', get_the_post_thumbnail_url($pid, 'aw-hero') ?: '');

/* ---------------------------------------------------------------------------
 * 2. Listagem
 * ------------------------------------------------------------------------ */
$categorias = get_categories(array(
	'hide_empty' => true,
	'exclude'    => array((int) get_option('default_category')), // "Sem categoria"
	'orderby'    => 'count',
	'order'      => 'DESC',
	'number'     => (int) apply_filters('aw_blog_max_abas', 6),
));

$categoria_atual = isset($_GET['categoria']) ? sanitize_title(wp_unslash($_GET['categoria'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$slugs_validos   = wp_list_pluck($categorias, 'slug');

if (!in_array($categoria_atual, $slugs_validos, true)) {
	$categoria_atual = '';
}

$pagina_atual = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

// Mesma configuração de AW_Ajax_Controller::filtrar_posts(), para a primeira
// carga e as respostas AJAX devolverem exatamente a mesma coisa.
$posts_blog = new AW_Query_Service(
	'post',
	array(
		'paged' => $pagina_atual,
		'tax'   => $categoria_atual ? array('category' => array($categoria_atual)) : array(),
	),
	array(
		'por_pagina' => 9,
		'orderby'    => 'date',
		'order'      => 'DESC',
	)
);
?>
<main id="conteudo" class="pagina-cso blog">

	<!-- 1. Hero ============================================================ -->
	<section class="hero hero--interna" aria-labelledby="hero-titulo">
		<div class="hero__midia" aria-hidden="true">
			<?php if ($hero_imagem) : ?>
				<img class="hero__video" src="<?php echo esc_url($hero_imagem); ?>" alt="" fetchpriority="high">
			<?php endif; ?>
		</div>

		<div class="container hero__inner">
			<div class="hero__conteudo">
				<div class="hero__textos">
					<h1 class="hero__titulo" id="hero-titulo"><?php echo nl2br(esc_html($hero_titulo)); ?></h1>
					<?php if ($hero_texto) : ?>
						<p class="hero__subtitulo hero__subtitulo--largo"><?php echo esc_html($hero_texto); ?></p>
					<?php endif; ?>
				</div>

				<div class="hero__ctas">
					<a href="<?php echo esc_url($hero_botao['url']); ?>" class="btn btn--primario btn--lg"><?php echo esc_html($hero_botao['rotulo']); ?></a>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Listagem de notícias ============================================ -->
	<section class="secao secao--blog-lista" id="noticias" aria-labelledby="noticias-titulo">
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha blog-lista__cabecalho">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('blog_kicker', $pid, __('Blog', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao" id="noticias-titulo"><?php echo esc_html(aw_field('blog_titulo', $pid, __('Fique por dentro das notícias', 'alfama-web'))); ?></h2>
				</div>

				<?php if ($categorias) : ?>
					<form class="abas-filtro" data-aw-filtro data-action="aw_filtrar_posts"
						data-target="#blog-grid" data-pagination="#blog-paginacao"
						aria-label="<?php esc_attr_e('Filtrar notícias por categoria', 'alfama-web'); ?>">
						<label class="abas-filtro__item">
							<input type="radio" name="category" value="" class="sr-only" <?php checked('', $categoria_atual); ?>>
							<span><?php esc_html_e('Todos', 'alfama-web'); ?></span>
						</label>
						<?php foreach ($categorias as $categoria) : ?>
							<label class="abas-filtro__item">
								<input type="radio" name="category" value="<?php echo esc_attr($categoria->slug); ?>" class="sr-only" <?php checked($categoria->slug, $categoria_atual); ?>>
								<span><?php echo esc_html($categoria->name); ?></span>
							</label>
						<?php endforeach; ?>
					</form>
				<?php endif; ?>
			</header>

			<div class="blog-grid" id="blog-grid" aria-live="polite">
				<?php
				echo $posts_blog->render( // phpcs:ignore WordPress.Security.EscapeOutput
					'template-parts/card-post',
					'<p class="aw-vazio">' . esc_html__('Nenhuma notícia publicada ainda.', 'alfama-web') . '</p>'
				);
				?>
			</div>

			<div class="blog-lista__paginacao paginacao-circulos" id="blog-paginacao">
				<?php echo $posts_blog->paginacao(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
