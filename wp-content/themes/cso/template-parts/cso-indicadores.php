<?php
/**
 * Indicadores (Figma: "indicators-wrapper") — 4 cards 282×155 com gradiente.
 *
 * Usado na home e em Quem Somos. Os números vêm de $args['itens'] (repeater
 * home_indicadores / qs_indicadores de cada página) e, sem cadastro, do layout.
 *
 * @param array $args {
 *     @type string $kicker Rótulo acima do título. Default: 'Desempenho'.
 *     @type string $titulo Título. Default: 'Indicadores e Relevância'.
 *     @type array  $itens  Lista de ['numero' => '', 'rotulo' => ''].
 * }
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? array(), array(
	'kicker' => __('Desempenho', 'alfama-web'),
	'titulo' => __('Indicadores e Relevância', 'alfama-web'),
	'itens'  => array(),
));

$itens = $args['itens'];

if (!is_array($itens) || !$itens) {
	$itens = array(
		array('numero' => '37', 'rotulo' => 'Anos de mercado'),
		array('numero' => '+400', 'rotulo' => 'Obras públicas'),
		array('numero' => '+5.000', 'rotulo' => 'Unidades habitacionais'),
		array('numero' => '+150', 'rotulo' => 'Escolas construídas'),
	);
}
?>
<div class="indicadores">
	<header class="indicadores__cabecalho">
		<p class="kicker"><?php echo esc_html($args['kicker']); ?></p>
		<h2 class="titulo-secao titulo-secao--indicadores"><?php echo esc_html($args['titulo']); ?></h2>
	</header>

	<ul class="indicadores__lista">
		<?php foreach ($itens as $indicador) : ?>
			<li class="indicador">
				<strong class="indicador__numero"><?php echo esc_html($indicador['numero'] ?? ''); ?></strong>
				<span class="indicador__rotulo"><?php echo esc_html($indicador['rotulo'] ?? ''); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
