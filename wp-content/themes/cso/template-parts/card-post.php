<?php
/**
 * Card de post do blog (Figma: "blog-card") — 384×516, padding 24, raio 16.
 *
 * Usado na home, no blog (page-blog.php), nos arquivos e nas respostas AJAX
 * de aw_filtrar_posts — é o mesmo arquivo em todos os caminhos.
 *
 * @param array $args {
 *     @type int   $post_id ID do post. Default: post atual do loop.
 *     @type array $dados   Alternativa ao post_id, para conteúdo de exemplo:
 *                          ['titulo', 'link', 'imagem', 'categoria', 'resumo',
 *                          'data' (texto), 'data_iso'].
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$dados = $args['dados'] ?? null;

if (!$dados) {
	$post_id = (int) ($args['post_id'] ?? get_the_ID());

	if (!$post_id) {
		return;
	}

	$categorias = get_the_category($post_id);

	$dados = array(
		'titulo'    => get_the_title($post_id),
		'link'      => get_permalink($post_id),
		'imagem'    => aw_thumb($post_id, 'aw-card'),
		'categoria' => $categorias ? $categorias[0]->name : '',
		'resumo'    => aw_resumo($post_id, 150),
		// "15 Jan, 2026" — aparece só no mobile da lista do blog e dos
		// relacionados (Figma blog-mobile); no desktop o card não tem data.
		'data'      => get_the_date('d', $post_id) . ' ' . ucfirst(get_the_date('M', $post_id)) . ', ' . get_the_date('Y', $post_id),
		'data_iso'  => get_the_date('c', $post_id),
	);
}

$dados = wp_parse_args($dados, array(
	'titulo'    => '',
	'link'      => '#',
	'imagem'    => AW_IMG . 'placeholder.svg',
	'categoria' => '',
	'resumo'    => '',
	'data'      => '',
	'data_iso'  => '',
));
?>
<article class="post-card">
	<a href="<?php echo esc_url($dados['link']); ?>" class="post-card__midia" tabindex="-1" aria-hidden="true">
		<img src="<?php echo esc_url($dados['imagem']); ?>" alt="" width="336" height="220" loading="lazy" decoding="async">
	</a>

	<?php if ($dados['categoria'] || $dados['data']) : ?>
		<div class="post-card__meta">
			<?php if ($dados['categoria']) : ?>
				<span class="post-card__categoria"><?php echo esc_html($dados['categoria']); ?></span>
			<?php endif; ?>
			<?php if ($dados['data']) : ?>
				<time class="post-card__data" datetime="<?php echo esc_attr($dados['data_iso']); ?>"><?php echo esc_html($dados['data']); ?></time>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h3 class="post-card__titulo"><a href="<?php echo esc_url($dados['link']); ?>"><?php echo esc_html($dados['titulo']); ?></a></h3>

	<?php if ($dados['resumo']) : ?>
		<p class="post-card__resumo"><?php echo esc_html($dados['resumo']); ?></p>
	<?php endif; ?>

	<a href="<?php echo esc_url($dados['link']); ?>" class="link-seta" aria-label="<?php echo esc_attr(sprintf(__('Leia mais: %s', 'alfama-web'), $dados['titulo'])); ?>">
		<?php esc_html_e('Leia mais', 'alfama-web'); ?>
		<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</a>
</article>
