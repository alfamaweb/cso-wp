<?php
/**
 * Política de privacidade — fiel ao frame "política de privacidade" do Figma
 * (1600px): sem hero; fundo areia, coluna de 996px centrada com selo, título
 * (Sora 600 36/130%), texto justificado e botão "Baixar PDF".
 *
 * O texto é o conteúdo da própria página (editor do WordPress). Também é
 * usado pela página escolhida em Configurações › Privacidade, qualquer que
 * seja o slug dela (aw_slug_pagina() + filtro page_template em inc/setup.php).
 * Campos ACF: politica_kicker, politica_titulo, politica_pdf
 * (acf-json/group_cso_politica.json).
 * Estilos: assets/css/politica-de-privacidade.css.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
	the_post();

	$pid    = get_the_ID();
	$titulo = aw_field('politica_titulo', $pid, get_the_title());
	$pdf    = aw_field('politica_pdf', $pid, '');
	$pdf    = is_array($pdf) ? ($pdf['url'] ?? '') : (is_numeric($pdf) ? wp_get_attachment_url((int) $pdf) : $pdf);
	?>
	<main id="conteudo" class="pagina-cso politica">
		<article class="secao secao--politica" aria-labelledby="politica-titulo">
			<div class="container">
				<div class="politica__coluna">
					<header class="politica__cabecalho">
						<span class="politica__selo"><?php echo esc_html(aw_field('politica_kicker', $pid, __('Política de privacidade', 'alfama-web'))); ?></span>
						<h1 class="politica__titulo" id="politica-titulo"><?php echo esc_html($titulo); ?></h1>
					</header>

					<div class="politica__conteudo">
						<?php the_content(); ?>
					</div>

					<?php if ($pdf) : ?>
						<a href="<?php echo esc_url($pdf); ?>" class="btn btn--primario politica__pdf" target="_blank" rel="noopener" download>
							<?php esc_html_e('Baixar PDF', 'alfama-web'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
