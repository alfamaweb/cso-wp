<?php
/**
 * Faixa de chamada para ação, com campos em Opções do Site.
 *
 * @param array $args {
 *     @type string $titulo
 *     @type string $texto
 *     @type string $botao_label
 *     @type string $botao_url
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'titulo'      => aw_field('cta_titulo', 'option', __('Vamos conversar?', 'alfama-web')),
	'texto'       => aw_field('cta_texto', 'option', ''),
	'botao_label' => aw_field('cta_botao_label', 'option', __('Fale com a gente', 'alfama-web')),
	'botao_url'   => aw_field('cta_botao_url', 'option', ''),
));

$url = $args['botao_url'];

if (!$url) {
	$pagina = get_page_by_path('contato');
	$url    = $pagina ? get_permalink($pagina) : home_url('/contato');
}
?>
<section class="section-cta">
	<div class="container">
		<div class="cta-inner">
			<div>
				<h2 class="text-2xl font-bold lg:text-3xl"><?php echo esc_html($args['titulo']); ?></h2>
				<?php if ($args['texto']) : ?>
					<p class="mt-2 max-w-prose"><?php echo esc_html($args['texto']); ?></p>
				<?php endif; ?>
			</div>

			<a href="<?php echo esc_url($url); ?>" class="btn btn--claro shrink-0">
				<?php echo esc_html($args['botao_label']); ?>
			</a>
		</div>
	</div>
</section>
