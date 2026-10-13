<?php
/**
 * Resultados de busca.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

$termo = get_search_query();
?>
<main id="conteudo">
	<?php
	get_template_part('template-parts/hero', null, array(
		'title' => sprintf(
			/* translators: %s: termo buscado. */
			__('Resultados para “%s”', 'alfama-web'),
			$termo
		),
	));
	?>

	<section class="py-12 lg:py-20">
		<div class="container">
			<?php if (have_posts()) : ?>
				<p class="aw-meta">
					<?php
					printf(
						esc_html(_n('%s resultado encontrado.', '%s resultados encontrados.', (int) $GLOBALS['wp_query']->found_posts, 'alfama-web')),
						esc_html(number_format_i18n($GLOBALS['wp_query']->found_posts))
					);
					?>
				</p>

				<ul class="aw-resultados mt-6">
					<?php while (have_posts()) : the_post(); ?>
						<li>
							<a href="<?php the_permalink(); ?>">
								<h2><?php the_title(); ?></h2>
								<p><?php echo esc_html(aw_resumo()); ?></p>
							</a>
						</li>
					<?php endwhile; ?>
				</ul>

				<?php echo aw_paginacao_html(max(1, get_query_var('paged')), $GLOBALS['wp_query']->max_num_pages); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<p class="aw-vazio">
					<?php esc_html_e('Nenhum resultado. Tente outras palavras.', 'alfama-web'); ?>
				</p>
				<?php get_search_form(); ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
