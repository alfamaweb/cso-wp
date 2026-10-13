<?php
/**
 * Página não encontrada.
 *
 * Bloco centralizado: código "404" em destaque, título, texto e botão para a
 * home. Estilos: assets/css/404.css.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();
?>
<main id="conteudo" class="pagina-cso pagina-404">
	<section class="erro-404" aria-labelledby="erro-404-titulo">
		<div class="container erro-404__inner">
			<p class="erro-404__codigo" aria-hidden="true">404</p>
			<h1 class="erro-404__titulo" id="erro-404-titulo"><?php esc_html_e('Página não encontrada', 'alfama-web'); ?></h1>
			<p class="erro-404__texto">
				<?php esc_html_e('O endereço acessado não existe ou foi movido. Volte para a página inicial para continuar navegando.', 'alfama-web'); ?>
			</p>

			<a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn--primario btn--lg"><?php esc_html_e('Ir para a home', 'alfama-web'); ?></a>
		</div>
	</section>
</main>
<?php
get_footer();
