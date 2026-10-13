<?php
/**
 * Seção Instagram (Figma: "secao-instagram") — 4 posts 282×282 + botão.
 *
 * Usada na home e em Quem Somos. Os posts vêm de $args['posts'] (repeater
 * home_instagram / qs_instagram de cada página: imagem, link, curtidas,
 * comentarios) e, sem cadastro, de placeholders com os números do layout.
 *
 * @param array $args {
 *     @type string $kicker Rótulo acima do título. Default: 'Instagram'.
 *     @type string $titulo Título. Default: 'Acompanhe nossas novidades'.
 *     @type array  $posts  Lista de posts no formato acima.
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'kicker' => __('Instagram', 'alfama-web'),
	'titulo' => __('Acompanhe nossas novidades', 'alfama-web'),
	'posts'  => array(),
));

$usuario     = aw_instagram_usuario();
$perfil      = 'https://www.instagram.com/' . $usuario . '/';
$placeholder = AW_IMG . 'placeholder.svg';
$posts       = $args['posts'];

if (!is_array($posts) || !$posts) {
	$posts = array(
		array('imagem' => '', 'link' => '', 'curtidas' => '248', 'comentarios' => '18'),
		array('imagem' => '', 'link' => '', 'curtidas' => '194', 'comentarios' => '12'),
		array('imagem' => '', 'link' => '', 'curtidas' => '312', 'comentarios' => '29'),
		array('imagem' => '', 'link' => '', 'curtidas' => '420', 'comentarios' => '45'),
	);
}

/**
 * URL de um campo de imagem ACF (array, ID ou URL).
 */
$url_imagem = static function ($valor) use ($placeholder) {
	if (is_array($valor) && !empty($valor['ID'])) {
		return wp_get_attachment_image_url($valor['ID'], 'medium_large') ?: $valor['url'];
	}

	if (is_numeric($valor)) {
		return wp_get_attachment_image_url((int) $valor, 'medium_large') ?: $placeholder;
	}

	return $valor ?: $placeholder;
};

$id_titulo = wp_unique_id('instagram-titulo-');
?>
<section class="secao secao--instagram" aria-labelledby="<?php echo esc_attr($id_titulo); ?>">
	<div class="container">
		<header class="secao__cabecalho secao__cabecalho--centro">
			<p class="kicker"><?php echo esc_html($args['kicker']); ?></p>
			<h2 class="titulo-secao" id="<?php echo esc_attr($id_titulo); ?>"><?php echo esc_html($args['titulo']); ?></h2>
			<a href="<?php echo esc_url($perfil); ?>" class="instagram__handle" target="_blank" rel="noopener noreferrer">@<?php echo esc_html($usuario); ?></a>
		</header>

		<ul class="instagram-grid">
			<?php foreach (array_slice($posts, 0, 4) as $post_insta) : ?>
				<li>
					<a href="<?php echo esc_url(($post_insta['link'] ?? '') ?: $perfil); ?>" class="insta-post" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url($url_imagem($post_insta['imagem'] ?? '')); ?>" alt="" width="282" height="282" loading="lazy" decoding="async">
						<span class="insta-post__info">
							<span class="insta-post__stat">
								<?php echo aw_icone('heart'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span class="sr-only"><?php esc_html_e('Curtidas:', 'alfama-web'); ?></span>
								<?php echo esc_html($post_insta['curtidas'] ?? ''); ?>
							</span>
							<span class="insta-post__stat">
								<?php echo aw_icone('comment'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span class="sr-only"><?php esc_html_e('Comentários:', 'alfama-web'); ?></span>
								<?php echo esc_html($post_insta['comentarios'] ?? ''); ?>
							</span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="secao__acoes secao__acoes--instagram">
			<a href="<?php echo esc_url($perfil); ?>" class="btn btn--contorno" target="_blank" rel="noopener noreferrer">
				<?php echo aw_icone('instagram', array('class' => 'btn__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php esc_html_e('Siga nosso Instagram', 'alfama-web'); ?></span>
			</a>
		</div>
	</div>
</section>
