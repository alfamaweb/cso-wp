<?php
/**
 * Arquivos: categoria, tag, taxonomia, data e post type archive.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="conteudo">
	<?php
	get_template_part('template-parts/hero', null, array(
		'title' => wp_strip_all_tags(get_the_archive_title()),
		'text'  => wp_strip_all_tags(get_the_archive_description()),
	));
	?>

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
				<p class="aw-vazio"><?php esc_html_e('Nada publicado aqui ainda.', 'alfama-web'); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
