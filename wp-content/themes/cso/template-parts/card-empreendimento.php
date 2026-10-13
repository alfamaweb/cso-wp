<?php
/**
 * Card de empreendimento (Figma: "emp-card") — 384×633, imagem 384×460,
 * selo de status, título, localização, barra de vendas e "Ver detalhes".
 *
 * Usado na home, na listagem (page-empreendimentos.php) e nas respostas AJAX
 * de aw_filtrar_empreendimentos — é o mesmo arquivo em todos os caminhos, o
 * que impede o card divergir entre a primeira carga e as seguintes.
 * Estilos: assets/css/style.css (.pagina-cso .emp-card).
 *
 * Campos ACF do empreendimento: localizacao_texto, percentual_vendido (0–100),
 * diferenciais_resumo (repeater "titulo" ou lista de textos). Esgotado
 * (status_obra = esgotado) sempre mostra 100%.
 *
 * @param array $args {
 *     @type int   $post_id ID do empreendimento. Default: post atual do loop.
 *     @type array $dados   Alternativa ao post_id, para conteúdo de exemplo:
 *                          ['titulo', 'link', 'imagem', 'local', 'status',
 *                          'status_nome', 'vendido', 'detalhes'].
 *     @type bool  $oculto  Renderiza com [hidden] (abas da home). Default false.
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$dados = $args['dados'] ?? null;

if (!$dados) {
	$post_id = (int) ($args['post_id'] ?? get_the_ID());

	if (!$post_id) {
		return;
	}

	$termos   = get_the_terms($post_id, 'status_obra');
	$termo    = ($termos && !is_wp_error($termos)) ? $termos[0] : null;
	$difs     = aw_field('diferenciais_resumo', $post_id, array());
	$esgotado = aw_esta_esgotado($post_id);

	$dados = array(
		'titulo'      => get_the_title($post_id),
		'link'        => get_permalink($post_id),
		'imagem'      => aw_thumb($post_id, 'large'),
		'local'       => aw_field('localizacao_texto', $post_id, ''),
		'status'      => $esgotado ? AW_STATUS_ESGOTADO : ($termo ? $termo->slug : ''),
		'status_nome' => $termo ? $termo->name : '',
		'vendido'     => $esgotado ? 100 : (int) aw_field('percentual_vendido', $post_id, 0),
		'detalhes'    => is_array($difs) ? array_filter(array_map(static function ($d) {
			return is_array($d) ? ($d['titulo'] ?? '') : (string) $d;
		}, $difs)) : array(),
	);
}

$dados = wp_parse_args($dados, array(
	'titulo'      => '',
	'link'        => '#',
	'imagem'      => AW_IMG . 'placeholder.svg',
	'local'       => '',
	'status'      => '',
	'status_nome' => '',
	'vendido'     => 0,
	'detalhes'    => array(),
));

$vendido   = min(100, max(0, (int) $dados['vendido']));
$badge_mod = in_array($dados['status'], array('lancamento', 'esgotado'), true) ? $dados['status'] : 'padrao';
?>
<article class="emp-card" data-status="<?php echo esc_attr($dados['status']); ?>"<?php echo !empty($args['oculto']) ? ' hidden' : ''; ?>>
	<a href="<?php echo esc_url($dados['link']); ?>" class="emp-card__midia" tabindex="-1" aria-hidden="true">
		<img src="<?php echo esc_url($dados['imagem']); ?>" alt="" width="384" height="460" loading="lazy" decoding="async">
		<?php if ($dados['status_nome']) : ?>
			<span class="emp-card__badge emp-card__badge--<?php echo esc_attr($badge_mod); ?>"><?php echo esc_html($dados['status_nome']); ?></span>
		<?php endif; ?>
	</a>

	<div class="emp-card__corpo">
		<div class="emp-card__cabecalho">
			<h3 class="emp-card__titulo"><a href="<?php echo esc_url($dados['link']); ?>"><?php echo esc_html($dados['titulo']); ?></a></h3>
			<?php if ($dados['local']) : ?>
				<p class="emp-card__local"><?php echo esc_html($dados['local']); ?></p>
			<?php endif; ?>
		</div>

		<div class="emp-card__vendas">
			<div class="emp-card__barra<?php echo 100 === $vendido ? ' is-completa' : ''; ?>" role="progressbar"
				aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $vendido; ?>"
				aria-label="<?php esc_attr_e('Unidades vendidas', 'alfama-web'); ?>">
				<span style="--progresso: <?php echo (int) $vendido; ?>%"></span>
			</div>
			<p class="emp-card__percentual">
				<?php
				/* translators: %d: percentual vendido. */
				printf(esc_html__('%d%% vendido', 'alfama-web'), (int) $vendido);
				?>
			</p>

			<details class="emp-card__detalhes">
				<summary class="emp-card__gatilho">
					<span><?php esc_html_e('Ver detalhes', 'alfama-web'); ?></span>
					<?php echo aw_icone('chevron-down', array('class' => 'emp-card__chevron')); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</summary>
				<div class="emp-card__painel">
					<?php if ($dados['detalhes']) : ?>
						<ul class="emp-card__lista">
							<?php foreach (array_slice((array) $dados['detalhes'], 0, 4) as $detalhe) : ?>
								<li><?php echo esc_html($detalhe); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<a href="<?php echo esc_url($dados['link']); ?>" class="link-seta">
						<?php esc_html_e('Ver empreendimento', 'alfama-web'); ?>
						<?php echo aw_icone('chevron-right'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</div>
			</details>
		</div>
	</div>
</article>
