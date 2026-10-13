<?php
/**
 * Template Name: Contato
 *
 * Contato — fiel ao frame "contato" do Figma (1600px, conteúdo 1200px):
 *
 *   1. hero-section   450px, imagem + overlay, "CSO Atendimento" e botão
 *                     "Entre em contato" (rola até o formulário)
 *   2. canais         fundo #1E2530, 3 cards 384×200 (telefones, e-mail,
 *                     endereço) com ícone, linha e rótulo + valor
 *   3. formulário     fundo #F8F9FA, card de 1200px com o formulário em duas
 *                     colunas (template-parts/form-contato.php)
 *
 * Textos por ACF (prefixo contato_, acf-json/group_cso_contato.json) com
 * fallback para o layout. Sem cards cadastrados, os canais saem de Opções do
 * Site › Contato (telefone, e-mail e endereço), que é a mesma fonte do rodapé.
 * Estilos: assets/css/contato.css + assets/css/style.css (.pagina-cso,
 * .form-contato, .campo, .aceite).
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

$pid = get_the_ID();

/**
 * URL de um campo ACF de imagem (array, ID ou URL), com fallback.
 */
$img = static function ($valor, $tamanho = 'large', $fallback = '') {
	if (is_array($valor) && !empty($valor['ID'])) {
		return wp_get_attachment_image_url($valor['ID'], $tamanho) ?: $valor['url'];
	}

	if (is_numeric($valor)) {
		return wp_get_attachment_image_url((int) $valor, $tamanho) ?: $fallback;
	}

	return $valor ?: $fallback;
};

/* ---------------------------------------------------------------------------
 * 1. Hero
 * ------------------------------------------------------------------------ */
$hero_titulo = aw_field('contato_hero_titulo', $pid, 'CSO Atendimento');
$hero_texto  = aw_field('contato_hero_texto', $pid, '');
$hero_botao  = aw_field('contato_hero_botao', $pid, '');
$hero_botao  = is_array($hero_botao) && !empty($hero_botao['url'])
	? array('url' => $hero_botao['url'], 'rotulo' => $hero_botao['title'] ?: __('Entre em contato', 'alfama-web'))
	: array('url' => '#formulario', 'rotulo' => __('Entre em contato', 'alfama-web'));
$hero_imagem = $img(aw_field('contato_hero_imagem', $pid, ''), 'aw-hero', get_the_post_thumbnail_url($pid, 'aw-hero') ?: '');

/* ---------------------------------------------------------------------------
 * 2. Canais (repeater contato_canais: tipo, titulo, itens[rotulo, valor])
 * ------------------------------------------------------------------------ */
$canais = aw_field('contato_canais', $pid, array());
$canais = is_array($canais) ? array_values(array_filter($canais, static function ($canal) {
	return is_array($canal) && !empty($canal['titulo']);
})) : array();

if (!$canais) {
	$canais = array(
		array('tipo' => 'telefone', 'titulo' => __('Nossos Telefones', 'alfama-web'), 'itens' => array(
			array('rotulo' => __('Atendimento e Vendas', 'alfama-web'), 'valor' => aw_field('telefone', 'option', '(75) 3623-2546')),
		)),
		array('tipo' => 'email', 'titulo' => __('Email', 'alfama-web'), 'itens' => array(
			array('rotulo' => __('Corporativo', 'alfama-web'), 'valor' => aw_field('email_contato', 'option', 'cso@csoengenharia.com')),
		)),
		array('tipo' => 'endereco', 'titulo' => __('Localização', 'alfama-web'), 'itens' => array(
			array('rotulo' => __('Endereço', 'alfama-web'), 'valor' => aw_field('endereco', 'option', "Rua da Concórdia, 313 - Queimadinha,\nFeira de Santana - BA, 44050-132")),
		)),
	);
}

$icones = array('telefone' => 'telefone', 'email' => 'mail', 'endereco' => 'pin');

/**
 * Link de cada linha conforme o tipo do card.
 */
$link_canal = static function ($tipo, $valor) {
	$valor = trim((string) $valor);

	if ('telefone' === $tipo) {
		$digitos = preg_replace('/[^0-9+]/', '', $valor);
		return $digitos ? 'tel:' . $digitos : '';
	}

	if ('email' === $tipo) {
		return is_email($valor) ? 'mailto:' . $valor : '';
	}

	if ('endereco' === $tipo) {
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(preg_replace('/\s+/', ' ', $valor));
	}

	return '';
};
?>
<main id="conteudo" class="pagina-cso contato">

	<!-- 1. Hero ============================================================ -->
	<section class="hero hero--interna" aria-labelledby="hero-titulo">
		<div class="hero__midia" aria-hidden="true">
			<?php if ($hero_imagem) : ?>
				<img class="hero__video" src="<?php echo esc_url($hero_imagem); ?>" alt="" fetchpriority="high">
			<?php endif; ?>
		</div>

		<div class="container hero__inner">
			<div class="hero__conteudo">
				<div class="hero__textos">
					<h1 class="hero__titulo" id="hero-titulo"><?php echo nl2br(esc_html($hero_titulo)); ?></h1>
					<?php if ($hero_texto) : ?>
						<p class="hero__subtitulo hero__subtitulo--largo"><?php echo esc_html($hero_texto); ?></p>
					<?php endif; ?>
				</div>

				<div class="hero__ctas">
					<a href="<?php echo esc_url($hero_botao['url']); ?>" class="btn btn--primario btn--lg"><?php echo esc_html($hero_botao['rotulo']); ?></a>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Canais de atendimento =========================================== -->
	<section class="secao secao--canais" aria-labelledby="canais-titulo">
		<div class="container">
			<header class="secao__cabecalho">
				<p class="kicker"><?php echo esc_html(aw_field('contato_canais_kicker', $pid, __('Central de relacionamento', 'alfama-web'))); ?></p>
				<h2 class="titulo-secao titulo-secao--claro" id="canais-titulo"><?php echo esc_html(aw_field('contato_canais_titulo', $pid, __('Principais contatos', 'alfama-web'))); ?></h2>
			</header>

			<ul class="canais">
				<?php foreach (array_slice($canais, 0, 3) as $canal) :
					$tipo  = $canal['tipo'] ?? 'telefone';
					$itens = isset($canal['itens']) && is_array($canal['itens']) ? $canal['itens'] : array();
					?>
					<li class="canal">
						<div class="canal__cabecalho">
							<?php echo aw_icone($icones[$tipo] ?? 'telefone', array('class' => 'canal__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<h3 class="canal__titulo"><?php echo esc_html($canal['titulo']); ?></h3>
						</div>

						<?php if ($itens) : ?>
							<ul class="canal__itens">
								<?php foreach ($itens as $item) :
									if (empty($item['valor'])) {
										continue;
									}

									$href = $link_canal($tipo, $item['valor']);
									?>
									<li>
										<?php if (!empty($item['rotulo'])) : ?>
											<strong><?php echo esc_html($item['rotulo']); ?>:</strong>
										<?php endif; ?>
										<?php if ($href) : ?>
											<a href="<?php echo esc_url($href); ?>"<?php echo 'endereco' === $tipo ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo nl2br(esc_html($item['valor'])); ?></a>
										<?php else : ?>
											<?php echo nl2br(esc_html($item['valor'])); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<!-- 3. Formulário ======================================================= -->
	<section class="secao secao--formulario" id="formulario" aria-labelledby="formulario-titulo">
		<div class="container">
			<header class="secao__cabecalho">
				<p class="kicker"><?php echo esc_html(aw_field('contato_form_kicker', $pid, __('Envie sua mensagem', 'alfama-web'))); ?></p>
				<h2 class="titulo-secao" id="formulario-titulo"><?php echo esc_html(aw_field('contato_form_titulo', $pid, __('Nossa equipe responderá o mais breve possível', 'alfama-web'))); ?></h2>
			</header>

			<?php get_template_part('template-parts/form-contato', null, array(
				'titulo' => aw_field('contato_form_subtitulo', $pid, __('Preencha as informações abaixo', 'alfama-web')),
			)); ?>
		</div>
	</section>

</main>
<?php
get_footer();
