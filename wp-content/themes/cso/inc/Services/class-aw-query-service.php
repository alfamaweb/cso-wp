<?php
/**
 * Camada de negócio das listagens filtráveis.
 *
 * Generalização do EmpreendimentoService do tema stanza: monta os args da
 * WP_Query a partir de parâmetros já higienizados, cacheia o resultado em
 * transient e renderiza o mesmo template part usado no SSR.
 *
 * O contrato é: quem chama entrega parâmetros limpos; o service não confia em
 * $_POST nem em $_GET e nunca os lê.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

class AW_Query_Service
{
	/** Tempo de vida do cache de consulta. */
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** Prefixo dos transients, usado também para invalidar em massa. */
	const CACHE_PREFIX = 'aw_q_';

	/** @var string */
	protected $post_type;

	/** @var array Configuração da listagem. */
	protected $config;

	/** @var array Parâmetros já higienizados. */
	protected $params;

	/** @var WP_Query|null */
	protected $query = null;

	/**
	 * @param string $post_type
	 * @param array  $params {
	 *     @type string $s          Busca textual.
	 *     @type int    $paged      Página atual.
	 *     @type array  $tax        Mapa taxonomia => array de slugs de termo.
	 *     @type array  $excluir_tax Mapa taxonomia => array de slugs a excluir.
	 * }
	 * @param array $config {
	 *     @type int    $por_pagina
	 *     @type string $orderby
	 *     @type string $order
	 *     @type string $meta_key   Usado quando orderby é 'meta_value_num'.
	 * }
	 */
	public function __construct($post_type, array $params = array(), array $config = array())
	{
		$this->post_type = $post_type;

		$this->params = wp_parse_args($params, array(
			's'           => '',
			'paged'       => 1,
			'tax'         => array(),
			'excluir_tax' => array(),
		));

		$this->config = wp_parse_args($config, array(
			'por_pagina' => 9,
			'orderby'    => 'menu_order date',
			'order'      => 'ASC',
			'meta_key'   => '',
		));
	}

	/**
	 * Higieniza um array cru de parâmetros (tipicamente $_POST) para o formato
	 * que o construtor espera.
	 *
	 * @param array    $cru
	 * @param string[] $taxonomias Taxonomias aceitas — qualquer outra chave é ignorada.
	 * @return array
	 */
	public static function sanitize(array $cru, array $taxonomias)
	{
		$params = array(
			's'     => isset($cru['s']) ? sanitize_text_field(wp_unslash($cru['s'])) : '',
			'paged' => isset($cru['paged']) ? max(1, absint($cru['paged'])) : 1,
			'tax'   => array(),
		);

		foreach ($taxonomias as $taxonomia) {
			if (empty($cru[$taxonomia])) {
				continue;
			}

			$valores = is_array($cru[$taxonomia]) ? $cru[$taxonomia] : array($cru[$taxonomia]);
			$valores = array_filter(array_map('sanitize_title', array_map('wp_unslash', $valores)));

			if ($valores) {
				$params['tax'][$taxonomia] = array_values(array_unique($valores));
			}
		}

		return $params;
	}

	/**
	 * Args da WP_Query.
	 *
	 * @return array
	 */
	public function args()
	{
		$args = array(
			'post_type'              => $this->post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $this->config['por_pagina'],
			'paged'                  => (int) $this->params['paged'],
			'orderby'                => $this->config['orderby'],
			'order'                  => $this->config['order'],
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ($this->config['meta_key']) {
			$args['meta_key'] = $this->config['meta_key'];
		}

		if ($this->params['s']) {
			$args['s'] = $this->params['s'];
		}

		$tax_query = array();

		foreach ($this->params['tax'] as $taxonomia => $termos) {
			$tax_query[] = array(
				'taxonomy' => $taxonomia,
				'field'    => 'slug',
				'terms'    => $termos,
				'operator' => 'IN',
			);
		}

		foreach ($this->params['excluir_tax'] as $taxonomia => $termos) {
			$tax_query[] = array(
				'taxonomy' => $taxonomia,
				'field'    => 'slug',
				'terms'    => $termos,
				'operator' => 'NOT IN',
			);
		}

		if (count($tax_query) > 1) {
			$tax_query['relation'] = 'AND';
		}

		if ($tax_query) {
			$args['tax_query'] = $tax_query;
		}

		/**
		 * Permite a um projeto ajustar a query sem editar o service.
		 *
		 * @param array             $args
		 * @param AW_Query_Service  $service
		 */
		return apply_filters('aw_query_args', $args, $this);
	}

	/**
	 * Executa a consulta, com cache dos IDs em transient.
	 *
	 * Cacheia IDs (não objetos WP_Post) e reidrata com 'post__in': mantém o
	 * transient pequeno e o cache de objetos do WordPress no comando.
	 *
	 * @return WP_Query
	 */
	public function query()
	{
		if ($this->query instanceof WP_Query) {
			return $this->query;
		}

		$args  = $this->args();
		$chave = self::CACHE_PREFIX . md5(wp_json_encode($args));
		$cache = aw_is_dev() ? false : get_transient($chave);

		if (is_array($cache) && isset($cache['ids'], $cache['total'], $cache['max_pages'])) {
			$this->query = new WP_Query(array(
				'post_type'           => $this->post_type,
				'post__in'            => $cache['ids'] ?: array(0),
				'orderby'             => 'post__in',
				'posts_per_page'      => count($cache['ids']) ?: 1,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			));

			$this->query->found_posts   = (int) $cache['total'];
			$this->query->max_num_pages = (int) $cache['max_pages'];

			return $this->query;
		}

		$this->query = new WP_Query($args);

		if (!aw_is_dev()) {
			set_transient($chave, array(
				'ids'       => wp_list_pluck($this->query->posts, 'ID'),
				'total'     => (int) $this->query->found_posts,
				'max_pages' => (int) $this->query->max_num_pages,
			), self::CACHE_TTL);
		}

		return $this->query;
	}

	/** @return int */
	public function total()
	{
		return (int) $this->query()->found_posts;
	}

	/** @return int */
	public function max_pages()
	{
		return max(1, (int) $this->query()->max_num_pages);
	}

	/** @return int */
	public function pagina()
	{
		return min((int) $this->params['paged'], $this->max_pages());
	}

	/**
	 * Renderiza a coleção com um template part, devolvendo o HTML.
	 *
	 * O MESMO template part é usado no SSR e na resposta AJAX — é o que evita
	 * o card divergir entre a primeira carga e as seguintes.
	 *
	 * @param string $part  Caminho relativo, ex. 'template-parts/card-empreendimento'.
	 * @param string $vazio HTML devolvido quando não há resultado.
	 * @return string
	 */
	public function render($part, $vazio = '')
	{
		$query = $this->query();

		if (!$query->have_posts()) {
			return $vazio ?: '<p class="aw-vazio">' . esc_html__('Nenhum resultado encontrado.', 'alfama-web') . '</p>';
		}

		ob_start();

		while ($query->have_posts()) {
			$query->the_post();
			get_template_part($part, null, array('post_id' => get_the_ID()));
		}

		wp_reset_postdata();

		return trim(ob_get_clean());
	}

	/**
	 * HTML da paginação desta consulta.
	 *
	 * @return string
	 */
	public function paginacao()
	{
		return aw_paginacao_html($this->pagina(), $this->max_pages());
	}

	/**
	 * Limpa todo o cache de consulta do tema.
	 *
	 * @return void
	 */
	public static function limpar_cache()
	{
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%',
				$wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%'
			)
		);

		wp_cache_flush();
	}
}

/**
 * Invalida o cache de listagem sempre que um conteúdo relevante muda.
 *
 * DÍVIDA RESOLVIDA (v2): o cache de 5 min do tema stanza não era invalidado na
 * publicação — o editor salvava e não via a mudança na listagem por até 5 min.
 */
add_action('save_post', 'aw_invalidar_cache_listagem', 10, 2);
add_action('deleted_post', 'aw_invalidar_cache_listagem');
add_action('edited_term', 'aw_invalidar_cache_listagem');
function aw_invalidar_cache_listagem($post_id = 0, $post = null)
{
	if ($post instanceof WP_Post && wp_is_post_revision($post_id)) {
		return;
	}

	AW_Query_Service::limpar_cache();
}
