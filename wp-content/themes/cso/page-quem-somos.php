<?php
/**
 * Quem Somos — fiel ao frame "quem somos" do Figma (1600px, conteúdo 1200px):
 *
 *   1. hero-section         imagem + overlay #0D1424 55%, 450px (.hero--interna)
 *   2. quem-somos-section   indicadores + texto institucional com selo "+35"
 *   3. secao-historia       "História e Evolução da Marca" — carrossel de marcos
 *   4. secao-institucional  Responsabilidade social + Política de qualidade (linhas alternadas)
 *   5. secao-instagram      template-parts/cso-instagram.php
 *
 * Conteúdo por campos ACF da própria página (prefixo qs_), sempre com fallback
 * para o texto do layout. Estilos: assets/css/quem-somos.css (exclusivos) e
 * assets/css/style.css (.pagina-cso: hero, empresa, indicadores, setas, Instagram).
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

// Carrossel da história. Enfileirado antes do get_header() para o CSS ir no <head>.
aw_enqueue_lib('swiper');

get_header();

$pid         = get_the_ID();
$placeholder = AW_IMG . 'placeholder.svg';

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
$hero_titulo = aw_field('qs_hero_titulo', $pid, 'Conheça a CSO Engenharia');
$hero_texto  = aw_field('qs_hero_texto', $pid, 'Desde 1989, unindo engenharia de precisão e a sofisticação que sua vida merece. Nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues.');
$hero_botao  = aw_field('qs_hero_botao', $pid, '');
$hero_botao  = is_array($hero_botao) && !empty($hero_botao['url'])
	? array('url' => $hero_botao['url'], 'rotulo' => $hero_botao['title'] ?: __('Ver Empreendimentos', 'alfama-web'))
	: array('url' => aw_url_empreendimentos(), 'rotulo' => __('Ver Empreendimentos', 'alfama-web'));
$hero_imagem = $img(aw_field('qs_hero_imagem', $pid, ''), 'aw-hero', get_the_post_thumbnail_url($pid, 'aw-hero') ?: '');

/* ---------------------------------------------------------------------------
 * 2. Institucional + indicadores
 * ------------------------------------------------------------------------ */
$emp_kicker = aw_field('qs_empresa_kicker', $pid, __('A empresa', 'alfama-web'));
$emp_titulo = aw_field('qs_empresa_titulo', $pid, "Nossos verbos preferidos\nsão desenhar e otimizar.");
$emp_texto  = aw_field('qs_empresa_texto', $pid, '<p>Somos referência em construção civil de alto padrão, entregando precisão estrutural e solidez absoluta em cada metro quadrado. Cada uma de nossas obras é planejada detalhadamente, mantendo o mesmo cuidado artesanal e engenharia rigorosa para garantir a sofisticação e segurança de quem vai viver ali.</p><p>Desde 1989, nossos verbos preferidos são construir, sonhar e otimizar. Ao longo dessa trajetória, nos tornamos referência na construção civil no interior da Bahia, com mais de 5.000 unidades entregues. Essa história, edificada com CREDIBILIDADE, SOLIDEZ e OBJETIVIDADE, continua agora com você.</p>');
$emp_imagem = $img(aw_field('qs_empresa_imagem', $pid, ''), 'large', $placeholder);
$emp_anos   = aw_field('qs_empresa_anos', $pid, '+35');
$emp_anos_r = aw_field('qs_empresa_anos_rotulo', $pid, __('Anos de história', 'alfama-web'));

/* ---------------------------------------------------------------------------
 * 3. História da marca (repeater qs_historia: logo, periodo, texto)
 * ------------------------------------------------------------------------ */
$historia = aw_field('qs_historia', $pid, array());
if (!is_array($historia) || !$historia) {
	$historia = array(
		array('logo' => '', 'periodo' => '1989 à 1992', 'texto' => "Feira de Santana, década de 80; em 24 de julho de 1989 nasce a Construtora Souza Oliveira.\n\nCom sede na Av. Maria Quitéria, a empresa ainda dava seus primeiros passos.\n\nEm 1991, passa a se chamar CSO Engenharia Ltda. e a sede é transferida para a Av. Sampaio."),
		array('logo' => '', 'periodo' => '1992 à 2008', 'texto' => "Os cubos foram proposta de uma gráfica que foi contratada na época para confeccionar a papelaria da empresa, desde então os cubos azuis passaram a ser marca registrada da CSO.\n\nSempre em evolução, a empresa cresceu em mercado e tamanho, as salas alugadas na Av. Sampaio já não eram o suficiente. Em 1997 veio a mudança para o prédio na Rua da Concórdia."),
		array('logo' => '', 'periodo' => '2008 à 2013', 'texto' => "Uma nova perspectiva é sempre bem vinda. Esse é um dos segredos para se chegar tão longe. A empresa que até pouco tempo tinha seu foco em obras públicas, já firmava sua marca no competitivo mercado imobiliário.\n\nAcompanhando as mudanças, a logomarca ganhou um aspecto mais sólido e firme, valores que foram cruciais para enfrentar os novos e constantes desafios."),
	);
}

/* ---------------------------------------------------------------------------
 * 4. Blocos institucionais (repeater qs_blocos: imagem, kicker, titulo, texto)
 *    Linhas alternadas: 1ª imagem à esquerda (482×500), 2ª imagem à direita (694×400).
 * ------------------------------------------------------------------------ */
$blocos = aw_field('qs_blocos', $pid, array());
if (!is_array($blocos) || !$blocos) {
	$blocos = array(
		array(
			'imagem' => '',
			'kicker' => 'Responsabilidade social',
			'titulo' => 'Garantimos cumprir nosso papel como uma empresa cidadã',
			'texto'  => '<p>Visando contribuir para a construção de um mundo melhor, a CSO Engenharia busca sempre cumprir seu papel de empresa cidadã, realizando junto com o SESI, desde 2011, o projeto Cidadão Solidário, que tem como objetivos específicos proporcionar para 100 crianças e adolescentes momentos de formação em cidadania e dos fundamentos do esporte; oferecer cursos de orientação profissional e uso consciente do orçamento doméstico para 50 adultos; articular parcerias entre SESI, empresa e comunidade. Apoiar as ações educativas da Escola API; e disseminar internamente os valores e ações sociais da empresa. O local do Projeto foi escolhido pela CSO Engenharia, por estar localizado no mesmo bairro da empresa (Queimadinha), bairro este que apresenta um alto índice de vulnerabilidade social. O Projeto está sendo realizado na Escola Estadual de Proteção à Infância (API), sediada nas instalações da Associação de Proteção à Infância.</p><p>A CSO também estimula a participação de seus colaboradores em atividades de cunho social, em trabalhos voluntários e outras atividades que tenham como propósito a melhoria da qualidade de vida da comunidade.</p>',
		),
		array(
			'imagem' => '',
			'kicker' => 'Política de qualidade',
			'titulo' => 'A melhor experiência e atendimento para os nossos clientes',
			'texto'  => '<p>A CSO Engenharia tem como compromisso entregar produtos com qualidade, nos prazos pactuados, promovendo a melhoria contínua de seus processos e o atendimento de todos os requisitos legais, normativos e entre outros aplicáveis, visando à satisfação de seus clientes e a sustentabilidade.</p>',
		),
	);
}
?>
<main id="conteudo" class="pagina-cso quem-somos">

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
						<p class="hero__subtitulo"><?php echo esc_html($hero_texto); ?></p>
					<?php endif; ?>
				</div>

				<div class="hero__ctas">
					<a href="<?php echo esc_url($hero_botao['url']); ?>" class="btn btn--primario btn--lg"><?php echo esc_html($hero_botao['rotulo']); ?></a>
				</div>
			</div>
		</div>
	</section>

	<!-- 2. Indicadores + institucional ===================================== -->
	<section class="secao secao--empresa" id="a-empresa" aria-labelledby="empresa-titulo">
		<div class="container">
			<?php get_template_part('template-parts/cso-indicadores', null, array(
				'kicker' => aw_field('qs_indicadores_kicker', $pid, __('Números de destaque', 'alfama-web')),
				'titulo' => aw_field('qs_indicadores_titulo', $pid, __('Indicadores e Relevância', 'alfama-web')),
				'itens'  => aw_field('qs_indicadores', $pid, array()),
			)); ?>

			<div class="empresa empresa--institucional">
				<div class="empresa__texto">
					<div class="empresa__titulos">
						<p class="kicker"><?php echo esc_html($emp_kicker); ?></p>
						<h2 class="titulo-secao titulo-secao--empresa" id="empresa-titulo"><?php echo nl2br(esc_html($emp_titulo)); ?></h2>
					</div>

					<div class="empresa__descricao"><?php echo wp_kses_post(wpautop($emp_texto)); ?></div>
				</div>

				<div class="empresa__visual">
					<img class="empresa__imagem" src="<?php echo esc_url($emp_imagem); ?>" alt="" width="587" height="500" loading="lazy" decoding="async">

					<div class="empresa__selo">
						<strong class="empresa__selo-numero"><?php echo esc_html($emp_anos); ?></strong>
						<span class="empresa__selo-rotulo"><?php echo esc_html($emp_anos_r); ?></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- 3. História e evolução da marca ==================================== -->
	<section class="secao secao--historia" aria-labelledby="historia-titulo">
		<div class="container">
			<header class="secao__cabecalho secao__cabecalho--linha">
				<div class="secao__titulos">
					<p class="kicker"><?php echo esc_html(aw_field('qs_historia_kicker', $pid, __('Nossa história', 'alfama-web'))); ?></p>
					<h2 class="titulo-secao titulo-secao--claro" id="historia-titulo"><?php echo esc_html(aw_field('qs_historia_titulo', $pid, __('História e Evolução da Marca', 'alfama-web'))); ?></h2>
				</div>

				<div class="setas">
					<button type="button" class="setas__btn historia-anterior" aria-label="<?php esc_attr_e('Período anterior', 'alfama-web'); ?>">
						<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
					<button type="button" class="setas__btn historia-proximo" aria-label="<?php esc_attr_e('Próximo período', 'alfama-web'); ?>">
						<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				</div>
			</header>

			<?php /* Mobile (até 767px): sem Swiper, 2 marcos por vez com as setas de baixo (iniciarPaginasMobile em app.js). */ ?>
			<div class="swiper historia" id="historia-lista" data-aw-paginas="2" data-aw-swiper-min="768" data-aw-swiper='{"slidesPerView":1,"spaceBetween":24,"navigation":{"prevEl":".historia-anterior","nextEl":".historia-proximo"},"breakpoints":{"768":{"slidesPerView":2},"992":{"slidesPerView":3}}}'>
				<div class="swiper-wrapper">
					<?php foreach ($historia as $marco) :
						$logo = $img($marco['logo'] ?? '', 'thumbnail', '');
						?>
						<div class="swiper-slide">
							<article class="marco">
								<?php if ($logo) : ?>
									<img class="marco__logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(sprintf(__('Logomarca da CSO em %s', 'alfama-web'), $marco['periodo'] ?? '')); ?>" width="60" height="60" loading="lazy">
								<?php else : ?>
									<span class="marco__logo marco__logo--vazio" aria-hidden="true"><?php aw_svg('logo-cso.svg'); ?></span>
								<?php endif; ?>

								<h3 class="marco__periodo"><?php echo esc_html($marco['periodo'] ?? ''); ?></h3>
								<div class="marco__texto"><?php echo wp_kses_post(wpautop($marco['texto'] ?? '')); ?></div>
							</article>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="setas setas--mobile" data-aw-paginas-de="#historia-lista" hidden>
				<button type="button" class="setas__btn" data-aw-paginas-anterior aria-label="<?php esc_attr_e('Períodos anteriores', 'alfama-web'); ?>">
					<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
				<button type="button" class="setas__btn" data-aw-paginas-proxima aria-label="<?php esc_attr_e('Próximos períodos', 'alfama-web'); ?>">
					<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>
	</section>

	<!-- 4. Responsabilidade social + Política de qualidade ================= -->
	<section class="secao secao--institucional" aria-label="<?php esc_attr_e('Compromissos da CSO', 'alfama-web'); ?>">
		<div class="container institucional">
			<?php foreach ($blocos as $i => $bloco) :
				$bloco_img = $img($bloco['imagem'] ?? '', 'large', $placeholder);
				$id_bloco  = 'institucional-' . ($i + 1);
				?>
				<div class="institucional__linha institucional__linha--<?php echo 0 === $i % 2 ? 'imagem-esquerda' : 'imagem-direita'; ?>">
					<div class="institucional__texto">
						<div class="institucional__titulos">
							<p class="kicker"><?php echo esc_html($bloco['kicker'] ?? ''); ?></p>
							<h2 class="titulo-secao titulo-secao--empresa" id="<?php echo esc_attr($id_bloco); ?>"><?php echo esc_html($bloco['titulo'] ?? ''); ?></h2>
						</div>
						<div class="institucional__descricao"><?php echo wp_kses_post(wpautop($bloco['texto'] ?? '')); ?></div>
					</div>

					<img class="institucional__imagem" src="<?php echo esc_url($bloco_img); ?>" alt="" loading="lazy" decoding="async">
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<!-- 5. Instagram ======================================================= -->
	<?php get_template_part('template-parts/cso-instagram', null, array(
		'kicker' => aw_field('qs_instagram_kicker', $pid, __('Instagram', 'alfama-web')),
		'titulo' => aw_field('qs_instagram_titulo', $pid, __('Acompanhe nossas novidades', 'alfama-web')),
		'posts'  => aw_field('qs_instagram', $pid, array()),
	)); ?>

</main>
<?php
get_footer();
