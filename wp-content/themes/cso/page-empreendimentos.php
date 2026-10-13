<?php
/**
 * Template Name: Empreendimentos
 *
 * Empreendimentos — fiel ao frame "empreendimentos" do Figma (1600px,
 * conteúdo 1200px):
 *
 *   1. hero-section   450px, imagem + overlay: empreendimento em destaque
 *                     (título, texto e "Quero saber mais")
 *   2. listagem       kicker + título, abas por status (Todos, Lançamentos,
 *                     Em Obras, 100% Vendido), grade 3×3 de cards
 *                     (template-parts/card-empreendimento.php) e paginação
 *                     em círculos
 *
 * Filtro e paginação usam o fluxo do tema: primeira carga no servidor via
 * AW_Query_Service e as seguintes por AJAX (ação aw_filtrar_empreendimentos,
 * form [data-aw-filtro] em assets/js/app.js). O card é o mesmo nos dois.
 * A página aceita ?status={slug} (links da home e do menu) e /page/N/.
 *
 * Hero (ACF, prefixo emp_): emp_hero_destaque (post object/ID de um
 * empreendimento) define título, texto, imagem e link; emp_hero_titulo,
 * emp_hero_texto, emp_hero_imagem e emp_hero_botao sobrepõem cada item.
 * Sem destaque escolhido, usa o lançamento mais recente.
 * Estilos: assets/css/empreendimentos.css + assets/css/style.css
 * (.pagina-cso .hero, .emp-card, .abas-filtro, .paginacao-circulos).
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
 * 1. Hero — empreendimento em destaque
 * ------------------------------------------------------------------------ */
$destaque = aw_field('emp_hero_destaque', $pid, 0);
$destaque = $destaque instanceof WP_Post ? $destaque->ID : (int) $destaque;

if (!$destaque || 'empreendimento' !== get_post_type($destaque)) {
	$recentes = get_posts(array(
		'post_type'      => 'empreendimento',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => taxonomy_exists('status_obra') ? array(array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'taxonomy' => 'status_obra',
			'field'    => 'slug',
			'terms'    => 'lancamento',
		)) : array(),
	));
	$destaque = $recentes ? (int) $recentes[0] : 0;
}

$hero_titulo = aw_field('emp_hero_titulo', $pid, $destaque ? get_the_title($destaque) : 'Bless Houses');
$hero_texto  = aw_field('emp_hero_texto', $pid, $destaque && has_excerpt($destaque)
	? get_the_excerpt($destaque)
	: 'Casas em condomínio fechado no Papagaio, com a solidez da engenharia CSO, lazer completo e a sofisticação que a sua família merece.');
$hero_botao  = aw_field('emp_hero_botao', $pid, '');
$hero_botao  = is_array($hero_botao) && !empty($hero_botao['url'])
	? array('url' => $hero_botao['url'], 'rotulo' => $hero_botao['title'] ?: __('Quero saber mais', 'alfama-web'), 'alvo' => $hero_botao['target'] ?? '')
	: array('url' => $destaque ? get_permalink($destaque) : '#listagem', 'rotulo' => __('Quero saber mais', 'alfama-web'), 'alvo' => '');
$hero_imagem = $img(
	aw_field('emp_hero_imagem', $pid, ''),
	'aw-hero',
	($destaque ? get_the_post_thumbnail_url($destaque, 'aw-hero') : '') ?: (get_the_post_thumbnail_url($pid, 'aw-hero') ?: '')
);

/* ---------------------------------------------------------------------------
 * 2. Listagem
 * ------------------------------------------------------------------------ */
$filtros = array(
	''           => __('Todos', 'alfama-web'),
	'lancamento' => __('Lançamentos', 'alfama-web'),
	'em-obras'   => __('Em Obras', 'alfama-web'),
	'esgotado'   => __('100% Vendido', 'alfama-web'),
);

$status_atual = isset($_GET['status']) ? sanitize_title(wp_unslash($_GET['status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

if (!array_key_exists($status_atual, $filtros)) {
	$status_atual = '';
}

$pagina_atual = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

// Mesma configuração de AW_Ajax_Controller::filtrar_empreendimentos(), para a
// primeira carga e as respostas AJAX devolverem exatamente a mesma coisa.
$listagem = new AW_Query_Service(
	'empreendimento',
	array(
		'paged' => $pagina_atual,
		'tax'   => $status_atual ? array('status_obra' => array($status_atual)) : array(),
	),
	array('por_pagina' => 9)
);
?>
<main id="conteudo" class="pagina-cso empreendimentos">

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
					<a href="<?php echo esc_url($hero_botao['url']); ?>" class="btn btn--primario btn--lg"<?php echo $hero_botao['alvo'] ? ' target="' . esc_attr($hero_botao['alvo']) . '" rel="noopener"' : ''; ?>>
						<?php echo esc_html($hero_botao['rotulo']); ?>
					</a>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Listagem ======================================================== -->
	<section class="secao secao--emp-lista" id="listagem" aria-labelledby="listagem-titulo">
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha emp-lista__cabecalho">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('emp_kicker', $pid, __('Empreendimentos', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao" id="listagem-titulo"><?php echo esc_html(aw_field('emp_titulo', $pid, __('As melhores opções para você', 'alfama-web'))); ?></h2>
				</div>

				<form class="abas-filtro" data-aw-filtro data-action="aw_filtrar_empreendimentos"
					data-target="#emp-grid" data-pagination="#emp-paginacao"
					aria-label="<?php esc_attr_e('Filtrar empreendimentos por status', 'alfama-web'); ?>">
					<input type="hidden" name="sem_fallback" value="1">
					<?php foreach ($filtros as $slug => $rotulo) : ?>
						<label class="abas-filtro__item">
							<input type="radio" name="status_obra" value="<?php echo esc_attr($slug); ?>" class="sr-only" <?php checked($slug, $status_atual); ?>>
							<span><?php echo esc_html($rotulo); ?></span>
						</label>
					<?php endforeach; ?>
				</form>
			</header>

			<div class="emp-grid" id="emp-grid" aria-live="polite">
				<?php echo $listagem->render('template-parts/card-empreendimento', aw_empreendimentos_vazio_html()); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>

			<div class="emp-lista__paginacao paginacao-circulos" id="emp-paginacao">
				<?php echo $listagem->paginacao(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
