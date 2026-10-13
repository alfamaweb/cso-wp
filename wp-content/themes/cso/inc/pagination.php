<?php
/**
 * Paginação em botões, reaproveitada pelo SSR e pelas respostas AJAX.
 *
 * DÍVIDA RESOLVIDA (v2): cada tema remontava a janela de páginas à mão, com
 * regras ligeiramente diferentes (janela fixa de 4 no cassind, nb_pagination_range
 * no nova-bairros). Aqui existe uma implementação só, usada nos dois caminhos.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Janela de páginas com elipses.
 *
 * Ex.: aw_janela_paginas(7, 20) → [1, '…', 6, 7, 8, '…', 20]
 *
 * @param int $atual
 * @param int $total
 * @param int $borda  Quantas páginas manter nas pontas.
 * @param int $volta  Quantas páginas manter de cada lado da atual.
 * @return array<int|string>
 */
function aw_janela_paginas($atual, $total, $borda = 1, $volta = 1)
{
	$atual = max(1, (int) $atual);
	$total = max(1, (int) $total);

	$paginas = array();

	for ($i = 1; $i <= $total; $i++) {
		$nas_pontas   = $i <= $borda || $i > $total - $borda;
		$perto_atual  = abs($i - $atual) <= $volta;

		if ($nas_pontas || $perto_atual) {
			$paginas[] = $i;
		} elseif (end($paginas) !== '…') {
			$paginas[] = '…';
		}
	}

	return $paginas;
}

/**
 * Devolve o HTML da paginação como <button data-page>, para consumo por JS.
 *
 * @param int    $atual
 * @param int    $total
 * @param string $rotulo Rótulo acessível do <nav>.
 * @return string HTML, ou string vazia quando há uma página só.
 */
function aw_paginacao_html($atual, $total, $rotulo = '')
{
	$atual = max(1, (int) $atual);
	$total = max(1, (int) $total);

	if ($total < 2) {
		return '';
	}

	$rotulo = $rotulo ?: __('Paginação', 'alfama-web');

	ob_start();
	?>
	<nav class="aw-paginacao" aria-label="<?php echo esc_attr($rotulo); ?>">
		<button type="button" class="aw-pg aw-pg--prev" data-page="<?php echo esc_attr(max(1, $atual - 1)); ?>"
			<?php disabled(1, $atual); ?>>
			<?php esc_html_e('Anterior', 'alfama-web'); ?>
		</button>

		<?php foreach (aw_janela_paginas($atual, $total) as $pagina) : ?>
			<?php if ('…' === $pagina) : ?>
				<span class="aw-pg-elipse" aria-hidden="true">…</span>
			<?php else : ?>
				<button type="button"
					class="aw-pg aw-pg--num<?php echo $pagina === $atual ? ' is-ativa' : ''; ?>"
					data-page="<?php echo esc_attr($pagina); ?>"
					<?php echo $pagina === $atual ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html($pagina); ?>
				</button>
			<?php endif; ?>
		<?php endforeach; ?>

		<button type="button" class="aw-pg aw-pg--next" data-page="<?php echo esc_attr(min($total, $atual + 1)); ?>"
			<?php disabled($total, $atual); ?>>
			<?php esc_html_e('Próximo', 'alfama-web'); ?>
		</button>
	</nav>
	<?php
	return trim(ob_get_clean());
}
