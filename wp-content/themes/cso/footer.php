<?php
/**
 * Rodapé do site — layout CSO (Figma: frame "footer").
 *
 * Grade de 1200px: marca (383) · Navegação (181) · Empreendimentos (165) ·
 * Contato (279), com 64px entre colunas; linha e barra legal abaixo.
 *
 * Conteúdo editável (Opções do Site, ACF), com fallback para o layout:
 *  - logo_rodape (imagem) — sem ela, o SVG do tema em branco
 *  - endereco, telefone, email_contato, copyright
 *  - menus "rodape" e "rodape-empreendimentos" (Aparência > Menus)
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$endereco  = aw_field('endereco', 'option', "Rua da Concórdia, 313 - Queimadinha,\nFeira de Santana - BA, 44050-132");
$telefone  = aw_field('telefone', 'option', '(75) 3623-2546');
$email     = aw_field('email_contato', 'option', 'cso@csoengenharia.com');
$copyright = aw_field('copyright', 'option', sprintf(
	/* translators: 1: ano, 2: nome do site. */
	__('© %1$s %2$s. Todos os direitos reservados.', 'alfama-web'),
	wp_date('Y'),
	'CSO Engenharia'
));
$privacidade = get_privacy_policy_url() ?: aw_url_pagina('politica-de-privacidade');
?>
<footer class="site-footer">
	<div class="container">

		<div class="site-footer__main">

			<div class="site-footer__marca">
				<a href="<?php echo esc_url(home_url('/')); ?>" class="site-footer__logo" rel="home">
					<?php aw_logo('logo_rodape'); ?>
				</a>
				<?php aw_redes_html('redes redes--footer'); ?>
			</div>

			<div class="site-footer__col site-footer__col--nav">
				<h2 class="site-footer__titulo"><?php esc_html_e('Navegação', 'alfama-web'); ?></h2>
				<?php
				wp_nav_menu(array(
					'theme_location' => 'rodape',
					'container'      => false,
					'menu_class'     => 'site-footer__links',
					'depth'          => 1,
					'fallback_cb'    => 'aw_fallback_menu_rodape',
				));
				?>
			</div>

			<div class="site-footer__col site-footer__col--emp">
				<h2 class="site-footer__titulo"><?php esc_html_e('Empreendimentos', 'alfama-web'); ?></h2>
				<?php
				wp_nav_menu(array(
					'theme_location' => 'rodape-empreendimentos',
					'container'      => false,
					'menu_class'     => 'site-footer__links',
					'depth'          => 1,
					'fallback_cb'    => 'aw_fallback_menu_rodape_empreendimentos',
				));
				?>
			</div>

			<div class="site-footer__col site-footer__col--contato">
				<h2 class="site-footer__titulo"><?php esc_html_e('Contato', 'alfama-web'); ?></h2>

				<div class="site-footer__contatos">
					<?php if ($endereco) : ?>
						<div class="site-footer__contato">
							<h3 class="site-footer__rotulo"><?php esc_html_e('Endereço', 'alfama-web'); ?></h3>
							<address class="site-footer__texto"><?php echo nl2br(esc_html($endereco)); ?></address>
						</div>
					<?php endif; ?>

					<?php if ($telefone || $email) : ?>
						<div class="site-footer__contato">
							<h3 class="site-footer__rotulo"><?php esc_html_e('Telefone & E-mail', 'alfama-web'); ?></h3>
							<p class="site-footer__texto">
								<?php if ($telefone) : ?>
									<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $telefone)); ?>"><?php echo esc_html($telefone); ?></a><br>
								<?php endif; ?>
								<?php if ($email) : ?>
									<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
								<?php endif; ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="site-footer__bottom">
			<p class="site-footer__copy">
				<?php echo esc_html($copyright); ?>
				<a href="<?php echo esc_url($privacidade); ?>"><?php esc_html_e('Política de Privacidade', 'alfama-web'); ?></a>.
			</p>

			<a href="https://alfamaweb.com.br" target="_blank" rel="noopener noreferrer" class="site-footer__assinatura">
				<span class="sr-only"><?php esc_html_e('Desenvolvido pela Alfamaweb', 'alfama-web'); ?></span>
				<?php aw_svg('logo-alfamaweb.svg', array('aria-hidden' => 'true')); ?>
			</a>
		</div>
	</div>
</footer>

<?php get_template_part('template-parts/whatsapp-flutuante'); ?>
<?php get_template_part('template-parts/form-feedback'); ?>

<?php wp_footer(); ?>
</body>
</html>
