<?php
/**
 * Empreendimento — fiel ao frame "empreendimento - interna" do Figma (1600px,
 * conteúdo 1200px):
 *
 *   1. hero-section      600px, imagem + overlay; selo de status e nome à
 *                        esquerda, card com vendas + formulário à direita
 *   2. diferenciais      carrossel de cards 282×80 com gradiente
 *   3. galeria           carrossel de ponta a ponta (slides 587×500, centrado)
 *   4. vídeos            um vídeo por vez (1200×500) com setas
 *   5. plantas           lista de tipologias que troca a imagem ao lado
 *   6. andamento da obra imagem + etapas com selo pelo percentual
 *   7. localização       texto, botões Google Maps / Waze e mapa
 *
 * Conteúdo: grupo ACF "Empreendimento" (acf-json/group_cso_empreendimento.json).
 * Os títulos caem no texto do layout quando vazios; as seções que dependem de
 * conteúdo próprio (diferenciais, galeria, vídeos, plantas, obra, localização)
 * somem quando não há o que mostrar — não faz sentido inventar plantas ou
 * etapas para um empreendimento real.
 *
 * Estilos: assets/css/empreendimento.css + assets/css/style.css (.pagina-cso).
 * Swiper e Fancybox entram automaticamente (aw_libs_do_contexto).
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
	the_post();

	$post_id  = get_the_ID();
	$nome     = get_the_title();
	$esgotado = aw_esta_esgotado($post_id);

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

	/**
	 * Só as linhas de um repeater que têm o subcampo obrigatório preenchido.
	 */
	$linhas = static function ($valor, $chave) {
		return is_array($valor) ? array_values(array_filter($valor, static function ($linha) use ($chave) {
			return is_array($linha) && !empty($linha[$chave]);
		})) : array();
	};

	/* -----------------------------------------------------------------------
	 * 1. Hero + card
	 * -------------------------------------------------------------------- */
	$termos      = get_the_terms($post_id, 'status_obra');
	$termo       = ($termos && !is_wp_error($termos)) ? $termos[0] : null;
	$status_slug = $esgotado ? AW_STATUS_ESGOTADO : ($termo ? $termo->slug : '');
	$badge_mod   = in_array($status_slug, array('lancamento', 'esgotado'), true) ? $status_slug : 'padrao';
	$local       = aw_field('localizacao_texto', $post_id, '');
	$vendido     = $esgotado ? 100 : min(100, max(0, (int) aw_field('percentual_vendido', $post_id, 0)));
	$hero_imagem = $img(aw_field('hero_imagem', $post_id, ''), 'aw-hero', get_the_post_thumbnail_url($post_id, 'aw-hero') ?: '');

	/* -----------------------------------------------------------------------
	 * 2–7. Seções
	 * -------------------------------------------------------------------- */
	$diferenciais = $linhas(aw_field('diferenciais', $post_id, array()), 'titulo');
	$galeria      = array_values(array_filter((array) aw_field('galeria', $post_id, array()), static function ($i) {
		return is_array($i) && !empty($i['url']);
	}));

	$videos = array();
	foreach ($linhas(aw_field('videos', $post_id, array()), 'url') as $linha) {
		$dados = aw_video_data($linha['url']);
		$capa  = $img($linha['capa'] ?? '', 'aw-hero', $dados['thumb'] ?? '');

		$videos[] = array('url' => $linha['url'], 'capa' => $capa ?: AW_IMG . 'placeholder.svg');
	}

	$plantas = $linhas(aw_field('plantas', $post_id, array()), 'nome');
	$etapas  = $linhas(aw_field('obra_etapas', $post_id, array()), 'etapa');

	$atualizacao = aw_field('obra_atualizacao', $post_id, '');
	$atualizacao = $atualizacao ? date_i18n('F Y', strtotime($atualizacao)) : '';

	$endereco = trim((string) aw_field('local_endereco', $post_id, ''));
	$mapa_img = $img(aw_field('mapa_imagem', $post_id, ''), 'large', '');
	?>
	<main id="conteudo" class="pagina-cso empreendimento-interna">

		<!-- 1. Hero + card ===================================================== -->
		<section class="hero hero--empreendimento" aria-labelledby="hero-titulo">
			<div class="hero__midia" aria-hidden="true">
				<?php if ($hero_imagem) : ?>
					<img class="hero__video" src="<?php echo esc_url($hero_imagem); ?>" alt="" fetchpriority="high">
				<?php endif; ?>
			</div>

			<div class="container hero__inner emp-hero">
				<div class="emp-hero__textos">
					<?php if ($termo) : ?>
						<span class="emp-badge emp-badge--<?php echo esc_attr($badge_mod); ?>"><?php echo esc_html($termo->name); ?></span>
					<?php endif; ?>
					<h1 class="hero__titulo emp-hero__titulo" id="hero-titulo"><?php echo esc_html($nome); ?></h1>
					<?php if (has_excerpt($post_id)) : ?>
						<?php /* Só no mobile (Figma empreendimento-interna-mobile): o desktop mostra apenas o nome. */ ?>
						<p class="hero__subtitulo emp-hero__subtitulo"><?php echo esc_html(get_the_excerpt($post_id)); ?></p>
					<?php endif; ?>
				</div>

				<aside class="emp-ficha<?php echo $esgotado ? '' : ' emp-ficha--com-form'; ?>" aria-label="<?php esc_attr_e('Vendas e contato', 'alfama-web'); ?>">
					<?php if (!$esgotado) : ?>
						<?php /* Cabeçalho do card no mobile (no lugar de nome, local e vendas, que o hero já mostra). */ ?>
						<div class="emp-ficha__contato">
							<p class="emp-ficha__contato-titulo"><?php esc_html_e('Fale Conosco', 'alfama-web'); ?></p>
							<p class="emp-ficha__contato-texto"><?php esc_html_e('Preencha seus dados para receber tabelas e condições', 'alfama-web'); ?></p>
						</div>
					<?php endif; ?>

					<div class="emp-ficha__cabecalho">
						<p class="emp-ficha__nome"><?php echo esc_html($nome); ?></p>
						<?php if ($local) : ?>
							<p class="emp-ficha__local"><?php echo esc_html($local); ?></p>
						<?php endif; ?>
					</div>

					<div class="emp-ficha__vendas">
						<div class="emp-ficha__barra<?php echo 100 === $vendido ? ' is-completa' : ''; ?>" role="progressbar"
							aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $vendido; ?>"
							aria-label="<?php esc_attr_e('Unidades vendidas', 'alfama-web'); ?>">
							<span style="--progresso: <?php echo (int) $vendido; ?>%"></span>
						</div>
						<p class="emp-ficha__percentual">
							<?php
							/* translators: %d: percentual vendido. */
							printf(esc_html__('%d%% vendido', 'alfama-web'), (int) $vendido);
							?>
						</p>
					</div>

					<?php if ($esgotado) : ?>
						<p class="emp-ficha__titulo"><?php esc_html_e('Todas as unidades foram vendidas', 'alfama-web'); ?></p>
						<a href="<?php echo esc_url(aw_url_empreendimentos()); ?>" class="btn btn--primario emp-ficha__enviar"><?php esc_html_e('Ver outros empreendimentos', 'alfama-web'); ?></a>
					<?php else : ?>
						<?php get_template_part('template-parts/form-interesse', null, array('empreendimento' => $nome)); ?>
					<?php endif; ?>
				</aside>
			</div>
		</section>

		<!-- 2. Diferenciais ===================================================== -->
		<?php if ($diferenciais) : ?>
			<section class="secao secao--diferenciais" aria-labelledby="diferenciais-titulo">
				<div class="container">
					<header class="secao__cabecalho secao__cabecalho--linha">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Diferenciais', 'alfama-web'); ?></p>
							<h2 class="titulo-secao" id="diferenciais-titulo">
								<?php
								/* translators: %s: nome do empreendimento. */
								echo esc_html(aw_field('diferenciais_titulo', $post_id, sprintf(__('Por que escolher o %s', 'alfama-web'), $nome)));
								?>
							</h2>
						</div>

						<div class="setas">
							<button type="button" class="setas__btn diferenciais-anterior" aria-label="<?php esc_attr_e('Diferenciais anteriores', 'alfama-web'); ?>">
								<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</button>
							<button type="button" class="setas__btn diferenciais-proximo" aria-label="<?php esc_attr_e('Próximos diferenciais', 'alfama-web'); ?>">
								<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</button>
						</div>
					</header>

					<?php /* Mobile (até 767px): sem Swiper, 4 diferenciais por vez com as setas de baixo (iniciarPaginasMobile em app.js). */ ?>
					<div class="swiper diferenciais" id="diferenciais-lista" data-aw-paginas="4" data-aw-swiper-min="768" data-aw-swiper='{"slidesPerView":1.3,"spaceBetween":16,"watchOverflow":true,"navigation":{"prevEl":".diferenciais-anterior","nextEl":".diferenciais-proximo"},"breakpoints":{"576":{"slidesPerView":2,"spaceBetween":24},"992":{"slidesPerView":3,"spaceBetween":24},"1200":{"slidesPerView":4,"spaceBetween":24}}}'>
						<ul class="swiper-wrapper">
							<?php foreach ($diferenciais as $diferencial) :
								$icone = $img($diferencial['icone'] ?? '', 'thumbnail', '');
								?>
								<li class="swiper-slide diferencial">
									<?php if ($icone) : ?>
										<img class="diferencial__icone" src="<?php echo esc_url($icone); ?>" alt="" width="40" height="40" loading="lazy">
									<?php endif; ?>
									<span class="diferencial__titulo"><?php echo esc_html($diferencial['titulo']); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="setas setas--sm setas--mobile" data-aw-paginas-de="#diferenciais-lista" hidden>
						<button type="button" class="setas__btn" data-aw-paginas-anterior aria-label="<?php esc_attr_e('Diferenciais anteriores', 'alfama-web'); ?>">
							<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</button>
						<button type="button" class="setas__btn" data-aw-paginas-proxima aria-label="<?php esc_attr_e('Próximos diferenciais', 'alfama-web'); ?>">
							<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</button>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- 3. Galeria ========================================================== -->
		<?php if ($galeria) : ?>
			<section class="secao secao--galeria" aria-labelledby="galeria-titulo">
				<div class="container">
					<header class="secao__cabecalho secao__cabecalho--centro">
						<p class="kicker"><?php esc_html_e('Galeria de imagens', 'alfama-web'); ?></p>
						<h2 class="titulo-secao" id="galeria-titulo"><?php echo esc_html(aw_field('galeria_titulo', $post_id, __('Áreas Comuns e Externas', 'alfama-web'))); ?></h2>
					</header>
				</div>

				<div class="swiper galeria" data-aw-swiper='{"slidesPerView":"auto","spaceBetween":16,"centeredSlides":true,"loop":<?php echo count($galeria) > 3 ? 'true' : 'false'; ?>,"pagination":{"el":".galeria__paginacao","clickable":true},"breakpoints":{"768":{"spaceBetween":24}}}'>
					<div class="swiper-wrapper">
						<?php foreach ($galeria as $foto) : ?>
							<div class="swiper-slide galeria__slide">
								<a href="<?php echo esc_url($foto['url']); ?>" data-fancybox="galeria" data-caption="<?php echo esc_attr($foto['caption'] ?? ''); ?>">
									<img src="<?php echo esc_url($foto['sizes']['large'] ?? $foto['url']); ?>" alt="<?php echo esc_attr($foto['alt'] ?? ''); ?>" width="587" height="500" loading="lazy" decoding="async">
								</a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="galeria__paginacao" aria-label="<?php esc_attr_e('Navegar pela galeria', 'alfama-web'); ?>"></div>
			</section>
		<?php endif; ?>

		<!-- 4. Vídeos =========================================================== -->
		<?php if ($videos) : ?>
			<section class="secao secao--videos" aria-labelledby="videos-titulo">
				<div class="container">
					<header class="secao__cabecalho secao__cabecalho--linha">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Nossos vídeos', 'alfama-web'); ?></p>
							<h2 class="titulo-secao titulo-secao--claro" id="videos-titulo"><?php echo esc_html(aw_field('videos_titulo', $post_id, __('Seu futuro começa aqui!', 'alfama-web'))); ?></h2>
						</div>

						<?php if (count($videos) > 1) : ?>
							<div class="setas">
								<button type="button" class="setas__btn videos-anterior" aria-label="<?php esc_attr_e('Vídeo anterior', 'alfama-web'); ?>">
									<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
								<button type="button" class="setas__btn videos-proximo" aria-label="<?php esc_attr_e('Próximo vídeo', 'alfama-web'); ?>">
									<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
							</div>
						<?php endif; ?>
					</header>

					<div class="swiper videos" data-aw-swiper='{"slidesPerView":1,"spaceBetween":24,"navigation":{"prevEl":".videos-anterior","nextEl":".videos-proximo"}}'>
						<div class="swiper-wrapper">
							<?php foreach ($videos as $i => $video) : ?>
								<div class="swiper-slide">
									<a class="video-capa" href="<?php echo esc_url($video['url']); ?>" data-fancybox="videos">
										<img src="<?php echo esc_url($video['capa']); ?>" alt="" width="1200" height="500" loading="lazy" decoding="async">
										<span class="video-capa__play" aria-hidden="true"><?php echo aw_icone('play'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
										<span class="sr-only">
											<?php
											/* translators: %d: número do vídeo. */
											printf(esc_html__('Assistir ao vídeo %d', 'alfama-web'), (int) $i + 1);
											?>
										</span>
									</a>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- 5. Plantas ========================================================== -->
		<?php if ($plantas) : ?>
			<section class="secao secao--plantas" aria-labelledby="plantas-titulo" data-aw-plantas>
				<div class="container linha-midia">
					<div class="linha-midia__texto">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Plantas', 'alfama-web'); ?></p>
							<h2 class="titulo-secao titulo-secao--bloco" id="plantas-titulo"><?php echo esc_html(aw_field('plantas_titulo', $post_id, __('Escolha a melhor opção de planta para a sua família', 'alfama-web'))); ?></h2>
						</div>

						<ul class="plantas__lista" role="tablist" aria-label="<?php esc_attr_e('Tipologias', 'alfama-web'); ?>">
							<?php foreach ($plantas as $i => $planta) : ?>
								<li role="presentation">
									<button type="button" class="plantas__item<?php echo 0 === $i ? ' is-ativa' : ''; ?>" role="tab"
										id="planta-aba-<?php echo (int) $i; ?>" aria-controls="planta-<?php echo (int) $i; ?>"
										aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-aw-planta="<?php echo (int) $i; ?>">
										<?php echo esc_html($planta['nome']); ?>
										<?php if (!empty($planta['area'])) : ?>
											— <?php echo esc_html($planta['area']); ?>
										<?php endif; ?>
									</button>
								</li>
							<?php endforeach; ?>
						</ul>

						<?php if (count($plantas) > 1) : ?>
							<div class="setas setas--sm">
								<button type="button" class="setas__btn" data-aw-planta-passo="-1" aria-label="<?php esc_attr_e('Planta anterior', 'alfama-web'); ?>">
									<?php echo aw_icone('chevron-left'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
								<button type="button" class="setas__btn" data-aw-planta-passo="1" aria-label="<?php esc_attr_e('Próxima planta', 'alfama-web'); ?>">
									<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
							</div>
						<?php endif; ?>
					</div>

					<div class="linha-midia__visual plantas__imagens">
						<?php foreach ($plantas as $i => $planta) :
							$planta_img = $img($planta['imagem'] ?? '', 'large', AW_IMG . 'placeholder.svg');
							?>
							<a class="plantas__imagem" id="planta-<?php echo (int) $i; ?>" role="tabpanel" aria-labelledby="planta-aba-<?php echo (int) $i; ?>"
								href="<?php echo esc_url($img($planta['imagem'] ?? '', 'full', $planta_img)); ?>" data-fancybox="plantas"
								<?php echo 0 === $i ? '' : 'hidden'; ?>>
								<img src="<?php echo esc_url($planta_img); ?>" alt="<?php echo esc_attr(sprintf(__('Planta %s', 'alfama-web'), $planta['nome'])); ?>" width="694" height="460" loading="lazy" decoding="async">
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- 6. Andamento da obra ================================================ -->
		<?php if ($etapas) :
			$obra_img = $img(aw_field('obra_imagem', $post_id, ''), 'large', AW_IMG . 'placeholder.svg');
			?>
			<section class="secao secao--obra" aria-labelledby="obra-titulo">
				<div class="container linha-midia linha-midia--invertida">
					<div class="linha-midia__visual">
						<img src="<?php echo esc_url($obra_img); ?>" alt="" width="694" height="460" loading="lazy" decoding="async">
					</div>

					<div class="linha-midia__texto obra">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Andamento da obra', 'alfama-web'); ?></p>
							<h2 class="titulo-secao titulo-secao--bloco" id="obra-titulo"><?php echo esc_html(aw_field('obra_titulo', $post_id, __('Acompanhe a evolução do seu futuro lar', 'alfama-web'))); ?></h2>
						</div>

						<ul class="obra__etapas">
							<?php foreach ($etapas as $etapa) :
								$pct = min(100, max(0, (int) ($etapa['percentual'] ?? 0)));

								if (100 === $pct) {
									$estado = array('concluida', __('Concluído', 'alfama-web'));
								} elseif ($pct > 0) {
									$estado = array('andamento', __('Em andamento', 'alfama-web'));
								} else {
									$estado = array('proxima', __('Próxima etapa', 'alfama-web'));
								}
								?>
								<li class="obra__etapa obra__etapa--<?php echo esc_attr($estado[0]); ?>">
									<span class="obra__selo"><?php echo esc_html($estado[1]); ?></span>
									<span class="obra__nome"><?php echo esc_html($etapa['etapa']); ?> — <?php echo (int) $pct; ?>%</span>
								</li>
							<?php endforeach; ?>
						</ul>

						<?php if ($atualizacao) : ?>
							<p class="obra__atualizacao">
								<?php
								/* translators: %s: mês e ano. */
								printf(esc_html__('Última atualização: %s', 'alfama-web'), esc_html(ucfirst($atualizacao)));
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<!-- 7. Localização ====================================================== -->
		<?php if ($endereco) :
			$texto = aw_field('local_texto', $post_id, '');
			$busca = rawurlencode($endereco);
			?>
			<section class="secao secao--localizacao" aria-labelledby="local-titulo">
				<div class="container linha-midia linha-midia--mapa">
					<div class="linha-midia__texto">
						<div class="secao__titulos">
							<p class="kicker"><?php esc_html_e('Localização privilegiada', 'alfama-web'); ?></p>
							<h2 class="titulo-secao titulo-secao--bloco" id="local-titulo"><?php echo esc_html(aw_field('local_titulo', $post_id, __('Tudo que você precisa em até 100m² de casa', 'alfama-web'))); ?></h2>
						</div>

						<?php if ($texto) : ?>
							<div class="local__texto"><?php echo wp_kses_post(wpautop($texto)); ?></div>
						<?php endif; ?>

						<div class="local__acoes">
							<a href="<?php echo esc_url('https://www.google.com/maps/search/?api=1&query=' . $busca); ?>" class="btn btn--contorno btn--lg" target="_blank" rel="noopener noreferrer">
								<?php echo aw_icone('navegar', array('class' => 'btn__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php esc_html_e('Abrir no Google Maps', 'alfama-web'); ?></span>
							</a>
							<a href="<?php echo esc_url('https://waze.com/ul?q=' . $busca . '&navigate=yes'); ?>" class="btn btn--contorno btn--lg" target="_blank" rel="noopener noreferrer">
								<?php echo aw_icone('mapa', array('class' => 'btn__icone')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php esc_html_e('Abrir no Waze', 'alfama-web'); ?></span>
							</a>
						</div>
					</div>

					<div class="linha-midia__visual local__mapa">
						<?php if ($mapa_img) : ?>
							<img src="<?php echo esc_url($mapa_img); ?>" alt="<?php echo esc_attr(sprintf(__('Mapa: %s', 'alfama-web'), $endereco)); ?>" width="694" height="366" loading="lazy" decoding="async">
						<?php else : ?>
							<iframe src="<?php echo esc_url('https://maps.google.com/maps?q=' . $busca . '&z=15&output=embed'); ?>"
								title="<?php echo esc_attr(sprintf(__('Mapa: %s', 'alfama-web'), $endereco)); ?>"
								loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
						<?php endif; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

	</main>
	<?php
endwhile;

get_footer();
