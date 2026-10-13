<?php
/**
 * Hero das páginas internas.
 *
 * Contrato explícito: os valores vêm de $args, com fallback para o grupo ACF
 * "hero" da página atual e, por último, para o título da página. Campo vazio
 * gera placeholder visível em vez de layout quebrado.
 *
 * @param array $args {
 *     @type string $title           Título (H1). Default: título da página.
 *     @type string $text            Texto de apoio. Default: campo hero.texto.
 *     @type string $bg_image        URL da imagem desktop. Default: hero.desktop ou thumbnail.
 *     @type string $bg_image_mobile URL da imagem mobile. Default: hero.mobile.
 *     @type string $eyebrow         Rótulo pequeno acima do H1. Default: vazio.
 *     @type bool   $breadcrumb      Exibir breadcrumb de um nível. Default: false.
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'title'           => '',
	'text'            => '',
	'bg_image'        => '',
	'bg_image_mobile' => '',
	'eyebrow'         => '',
	'breadcrumb'      => false,
));

$hero = aw_field('hero', get_the_ID(), array());

$titulo  = $args['title'] ?: ($hero['titulo'] ?? '') ?: get_the_title();
$texto   = $args['text'] ?: ($hero['texto'] ?? '');
$desktop = $args['bg_image'] ?: ($hero['desktop']['url'] ?? '') ?: get_the_post_thumbnail_url(get_the_ID(), 'aw-hero');
$mobile  = $args['bg_image_mobile'] ?: ($hero['mobile']['url'] ?? '');
$alt     = $hero['desktop']['alt'] ?? '';
?>
<section class="hero-interna" aria-labelledby="hero-titulo">
	<div class="hero-media" aria-hidden="<?php echo $desktop ? 'true' : 'false'; ?>">
		<?php if ($desktop && $mobile) : ?>
			<picture>
				<source media="(max-width: 767px)" srcset="<?php echo esc_url($mobile); ?>">
				<img src="<?php echo esc_url($desktop); ?>" alt="<?php echo esc_attr($alt); ?>" loading="eager" fetchpriority="high">
			</picture>
		<?php elseif ($desktop || $mobile) : ?>
			<img src="<?php echo esc_url($desktop ?: $mobile); ?>" alt="<?php echo esc_attr($alt); ?>" loading="eager" fetchpriority="high">
		<?php else : ?>
			<div class="aw-placeholder">[ <?php esc_html_e('imagem do hero — custom field', 'alfama-web'); ?> ]</div>
		<?php endif; ?>
		<span class="hero-overlay"></span>
	</div>

	<div class="hero-conteudo">
		<div class="container">
			<div class="grid grid-cols-12">
				<div class="col-span-12 md:col-span-9 xl:col-span-7">
					<?php if ($args['breadcrumb']) : ?>
						<nav class="aw-breadcrumb aw-breadcrumb--claro" aria-label="<?php esc_attr_e('Você está aqui', 'alfama-web'); ?>">
							<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Início', 'alfama-web'); ?></a>
							<span aria-hidden="true">/</span>
							<span><?php echo esc_html($titulo); ?></span>
						</nav>
					<?php endif; ?>

					<?php if ($args['eyebrow']) : ?>
						<p class="hero-eyebrow"><?php echo esc_html($args['eyebrow']); ?></p>
					<?php endif; ?>

					<h1 id="hero-titulo" class="hero-titulo"><?php echo esc_html($titulo); ?></h1>

					<?php if ($texto) : ?>
						<p class="hero-texto"><?php echo esc_html($texto); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>
