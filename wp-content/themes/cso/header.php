<?php
/**
 * Cabeçalho do site — layout CSO (Figma: frames "top-bar" e "main-header").
 *
 *  - top-bar: faixa escura de 40px com os índices econômicos (IGP-M, INCC);
 *  - main-header: 90px, logo à esquerda; menu, redes e "Portal do Cliente" à direita;
 *  - abaixo de 1200px o menu vira painel mobile (mesmo JS do tema: data-aw-menu-*).
 *
 * Conteúdo editável (Opções do Site, ACF), sempre com fallback para o texto
 * do layout:
 *  - indices_economicos (repeater: nome, valor, referencia)
 *  - portal_cliente_url (url)
 *  - logo (imagem) — sem ela, usa assets/img/logo-cso.svg
 *
 * DÍVIDA RESOLVIDA (v2): nenhuma biblioteca entra aqui como <link> ou <script>
 * literal — tudo passa por wp_enqueue_* em inc/assets.php.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$indices = aw_field('indices_economicos', 'option', array());

if (!is_array($indices) || !$indices) {
	$indices = array(
		array('nome' => 'IGP-M', 'valor' => '-0,73%', 'referencia' => '2026'),
		array('nome' => 'INCC', 'valor' => '+0,34%', 'referencia' => '2026'),
	);
}

$portal_url = aw_field('portal_cliente_url', 'option', '#');

$menu_args = array(
	'theme_location' => 'primary',
	'container'      => false,
	'depth'          => 2,
	'fallback_cb'    => 'aw_fallback_menu_principal',
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0D1424">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="aw-skip-link" href="#conteudo"><?php esc_html_e('Pular para o conteúdo', 'alfama-web'); ?></a>

<header class="site-header" id="topo">

	<div class="topbar">
		<div class="container topbar__inner">
			<ul class="topbar__indices" aria-label="<?php esc_attr_e('Índices econômicos', 'alfama-web'); ?>">
				<?php foreach ($indices as $indice) :
					if (empty($indice['nome'])) {
						continue;
					}
					?>
					<li class="topbar__indice">
						<span class="topbar__nome"><?php echo esc_html($indice['nome']); ?>:</span>
						<strong class="topbar__valor"><?php echo esc_html($indice['valor'] ?? ''); ?></strong>
						<?php if (!empty($indice['referencia'])) : ?>
							<span class="topbar__ref"><?php echo esc_html($indice['referencia']); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="header-main">
		<div class="container header-main__inner">

			<a href="<?php echo esc_url(home_url('/')); ?>" class="header-main__logo" rel="home">
				<?php aw_logo('logo'); ?>
			</a>

			<div class="header-main__acoes">
				<nav class="nav-principal" aria-label="<?php esc_attr_e('Menu principal', 'alfama-web'); ?>">
					<?php wp_nav_menu($menu_args + array('menu_class' => 'nav-principal__menu')); ?>
				</nav>

				<?php aw_redes_html('redes redes--header'); ?>

				<a href="<?php echo esc_url($portal_url); ?>" class="btn btn--contorno btn--portal"<?php echo '#' !== $portal_url ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo aw_icone('user', array('class' => 'btn__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span><?php esc_html_e('Portal do Cliente', 'alfama-web'); ?></span>
				</a>
			</div>

			<button type="button" class="header-main__toggle" data-aw-menu-toggle
				aria-expanded="false" aria-controls="menu-mobile">
				<span class="sr-only"><?php esc_html_e('Abrir menu', 'alfama-web'); ?></span>
				<?php echo aw_icone('menu'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
		</div>
	</div>

	<div class="menu-mobile" id="menu-mobile" hidden>
		<div class="menu-mobile__topo">
			<a href="<?php echo esc_url(home_url('/')); ?>" class="menu-mobile__logo" rel="home">
				<?php aw_logo('logo'); ?>
			</a>
			<button type="button" class="menu-mobile__fechar" data-aw-menu-close>
				<span class="sr-only"><?php esc_html_e('Fechar menu', 'alfama-web'); ?></span>
				<?php echo aw_icone('close'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</button>
		</div>

		<nav class="menu-mobile__nav" aria-label="<?php esc_attr_e('Menu principal (mobile)', 'alfama-web'); ?>">
			<?php wp_nav_menu($menu_args + array('menu_class' => 'menu-mobile__menu')); ?>
		</nav>

		<div class="menu-mobile__rodape">
			<a href="<?php echo esc_url($portal_url); ?>" class="btn btn--contorno btn--portal">
				<?php echo aw_icone('user', array('class' => 'btn__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php esc_html_e('Portal do Cliente', 'alfama-web'); ?></span>
			</a>
			<?php aw_redes_html('redes redes--header'); ?>
		</div>
	</div>
</header>
