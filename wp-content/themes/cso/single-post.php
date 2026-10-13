<?php
/**
 * Notícia (post único) — fiel ao frame "blog - interna" do Figma (1600px,
 * conteúdo 1200px):
 *
 *   1. Artigo (fundo branco, padding 72, gap 40)
 *      - topo: categoria + data, título e texto de abertura (584px) ao lado da
 *        imagem destacada (484×327, raio 16)
 *      - barra de compartilhamento (79px, borda dourada) à direita, fixa ao rolar
 *      - corpo do texto em 1098px (largura do conteúdo menos a barra)
 *   2. "Você também pode se interessar" — 3 posts (template-parts/card-post.php)
 *
 * Texto de abertura: o resumo manual do post, se houver; senão, o primeiro
 * parágrafo do conteúdo (retirado do corpo para não repetir).
 * Estilos: assets/css/post.css + assets/css/style.css (.post-card, .blog-grid).
 * Molde copiado pelo scaffolding para single-{cpt}.php.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
	the_post();

	$post_id    = get_the_ID();
	$categorias = get_the_category($post_id);
	$categoria  = $categorias ? $categorias[0] : null;
	$imagem     = get_the_post_thumbnail_url($post_id, 'large') ?: AW_IMG . 'placeholder.svg';

	// Corpo já processado pelos filtros do WordPress (blocos, shortcodes, embeds).
	$corpo    = apply_filters('the_content', get_the_content());
	$corpo    = str_replace(']]>', ']]&gt;', $corpo);
	$abertura = '';

	if (has_excerpt($post_id)) {
		$abertura = wpautop(esc_html(get_the_excerpt($post_id)));
	} elseif (preg_match('#^\s*(<p\b[^>]*>.*?</p>)#s', $corpo, $m)) {
		$abertura = $m[1];
		$corpo    = substr($corpo, strlen($m[0]));
	}

	// Relacionados: mesma categoria primeiro; completa com os mais recentes.
	$relacionados = get_posts(array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'post__not_in'        => array($post_id),
		'category__in'        => $categoria ? array($categoria->term_id) : array(),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	));

	if (count($relacionados) < 3) {
		$relacionados = array_merge($relacionados, get_posts(array(
			'post_type'           => 'post',
			'posts_per_page'      => 3 - count($relacionados),
			'post__not_in'        => array_merge(array($post_id), wp_list_pluck($relacionados, 'ID')),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)));
	}
	?>
	<main id="conteudo" class="pagina-cso noticia">

		<!-- 1. Artigo ======================================================= -->
		<article class="secao secao--noticia" aria-labelledby="noticia-titulo">
			<div class="container noticia__grade">

				<div class="noticia__principal">
					<header class="noticia__topo">
						<div class="noticia__intro">
							<div class="noticia__titulos">
								<p class="noticia__meta">
									<?php if ($categoria) : ?>
										<a class="noticia__categoria" href="<?php echo esc_url(add_query_arg('categoria', $categoria->slug, aw_url_blog())); ?>"><?php echo esc_html($categoria->name); ?></a>
									<?php endif; ?>
									<time class="noticia__data" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j \d\e F \d\e Y')); ?></time>
								</p>
								<h1 class="noticia__titulo" id="noticia-titulo"><?php the_title(); ?></h1>
							</div>

							<?php if ($abertura) : ?>
								<div class="noticia__abertura"><?php echo wp_kses_post($abertura); ?></div>
							<?php endif; ?>
						</div>

						<figure class="noticia__imagem">
							<img src="<?php echo esc_url($imagem); ?>" alt="<?php echo esc_attr(get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true)); ?>" width="484" height="327" fetchpriority="high">
						</figure>
					</header>

					<?php if (trim(wp_strip_all_tags($corpo, true)) || false !== strpos($corpo, '<img')) : ?>
						<div class="noticia__conteudo">
							<?php echo $corpo; // phpcs:ignore WordPress.Security.EscapeOutput -- saída de the_content. ?>
						</div>
					<?php endif; ?>

					<?php
					wp_link_pages(array(
						'before' => '<nav class="noticia__paginas" aria-label="' . esc_attr__('Páginas da notícia', 'alfama-web') . '">',
						'after'  => '</nav>',
					));
					?>
				</div>

				<aside class="noticia__compartilhar" aria-label="<?php esc_attr_e('Compartilhar', 'alfama-web'); ?>">
					<p class="compartilhar__rotulo" aria-hidden="true"><?php esc_html_e('Compartilhar matéria', 'alfama-web'); ?></p>
					<ul class="compartilhar">
						<?php foreach (aw_links_compartilhar($post_id) as $link) : ?>
							<li>
								<a href="<?php echo esc_url($link['url']); ?>" class="compartilhar__link compartilhar__link--<?php echo esc_attr($link['rede']); ?>"
									<?php echo 'mail' === $link['rede'] ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>>
									<span class="sr-only"><?php echo esc_html($link['rotulo']); ?></span>
									<?php echo aw_icone($link['rede']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</aside>
			</div>
		</article>

		<!-- 2. Relacionados ================================================= -->
		<?php if ($relacionados) : ?>
			<section class="secao secao--relacionados" aria-labelledby="relacionados-titulo">
				<div class="container">
					<header class="secao__cabecalho secao__cabecalho--linha">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Blog', 'alfama-web'); ?></p>
							<h2 class="titulo-secao" id="relacionados-titulo"><?php esc_html_e('Você também pode se interessar', 'alfama-web'); ?></h2>
						</div>
						<a href="<?php echo esc_url(aw_url_blog()); ?>" class="btn btn--contorno btn--lg"><?php esc_html_e('Ver todas as notícias', 'alfama-web'); ?></a>
					</header>

					<div class="blog-grid">
						<?php foreach ($relacionados as $relacionado) : ?>
							<?php get_template_part('template-parts/card-post', null, array('post_id' => $relacionado->ID)); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

	</main>
	<?php
endwhile;

get_footer();
