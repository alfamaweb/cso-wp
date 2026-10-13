<?php
/**
 * Molde de página. É este arquivo que o scaffolding copia para page-{slug}.php.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="conteudo">
	<?php get_template_part('template-parts/hero'); ?>

	<section class="py-12 lg:py-20">
		<div class="container">
			<div class="grid grid-cols-12">
				<div class="col-span-12 lg:col-span-8">
					<div class="aw-prose">
						<?php
						while (have_posts()) {
							the_post();
							the_content();
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
