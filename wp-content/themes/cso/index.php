<?php
/**
 * Fallback da hierarquia de templates.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="conteudo">
	<?php get_template_part('template-parts/hero', null, array('title' => get_the_archive_title() ?: get_bloginfo('name'))); ?>

	<section class="py-12 lg:py-20">
		<div class="container">
			<?php if (have_posts()) : ?>
				<div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
					<?php while (have_posts()) : the_post(); ?>
						<?php get_template_part('template-parts/card-post', null, array('post_id' => get_the_ID())); ?>
					<?php endwhile; ?>
				</div>

				<?php echo aw_paginacao_html(max(1, get_query_var('paged')), $GLOBALS['wp_query']->max_num_pages); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<p class="aw-vazio"><?php esc_html_e('Nenhum conteúdo publicado ainda.', 'alfama-web'); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
