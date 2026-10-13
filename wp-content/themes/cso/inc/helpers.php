<?php
/**
 * Helpers globais do tema. Sem hooks — só funções puras usadas em template.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/**
 * Lê um campo do ACF com fallback, sem quebrar se o plugin estiver inativo.
 *
 * Substitui o acesso direto a get_field() nos templates: permite montar o
 * layout antes de os campos existirem e evita fatal error num site sem ACF.
 *
 * @param string          $key      Nome do campo.
 * @param int|string|null $post_id  ID do post, 'option' para a página de opções.
 * @param mixed           $fallback Valor devolvido quando o campo está vazio.
 * @return mixed
 */
function aw_field($key, $post_id = null, $fallback = '')
{
	if (!function_exists('get_field')) {
		return $fallback;
	}

	$value = get_field($key, $post_id);

	return ($value === null || $value === '' || $value === array()) ? $fallback : $value;
}

/**
 * Mesmo contrato de aw_field(), para subcampos dentro de have_rows().
 */
function aw_sub_field($key, $fallback = '')
{
	if (!function_exists('get_sub_field')) {
		return $fallback;
	}

	$value = get_sub_field($key);

	return ($value === null || $value === '' || $value === array()) ? $fallback : $value;
}

/**
 * Imprime um SVG de assets/img/ inline, para permitir currentColor no CSS.
 *
 * Só lê de dentro de assets/img/ e recusa qualquer caminho que escape da
 * pasta (proteção contra path traversal caso o nome venha de campo editável).
 *
 * @param string $arquivo Nome do arquivo, ex. 'icone-whatsapp.svg'.
 * @param array  $attr    Atributos extras aplicados à tag <svg>, ex. ['class' => 'h-5 w-5'].
 * @return void
 */
function aw_svg($arquivo, $attr = array())
{
	$base = realpath(AW_DIR . '/assets/img');
	$path = realpath(AW_DIR . '/assets/img/' . ltrim($arquivo, '/'));

	if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
		return;
	}

	$svg = file_get_contents($path);

	if (!$svg) {
		return;
	}

	if ($attr) {
		$extra = '';
		foreach ($attr as $nome => $valor) {
			$extra .= sprintf(' %s="%s"', esc_attr($nome), esc_attr($valor));
		}
		$svg = preg_replace('/<svg\b/', '<svg' . $extra, $svg, 1);
	}

	echo wp_kses($svg, aw_svg_allowed_tags()); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Tags e atributos permitidos ao imprimir SVG inline.
 *
 * @return array
 */
function aw_svg_allowed_tags()
{
	$attrs = array(
		'class' => true, 'id' => true, 'style' => true, 'fill' => true, 'stroke' => true,
		'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
		'viewbox' => true, 'width' => true, 'height' => true, 'xmlns' => true,
		'aria-hidden' => true, 'aria-label' => true, 'role' => true, 'focusable' => true,
		'd' => true, 'x' => true, 'y' => true, 'x1' => true, 'y1' => true, 'x2' => true,
		'y2' => true, 'cx' => true, 'cy' => true, 'r' => true, 'rx' => true, 'ry' => true,
		'points' => true, 'transform' => true, 'opacity' => true, 'fill-rule' => true,
		'clip-rule' => true, 'offset' => true, 'stop-color' => true, 'gradientunits' => true,
	);

	return array(
		'svg' => $attrs, 'g' => $attrs, 'path' => $attrs, 'circle' => $attrs,
		'rect' => $attrs, 'line' => $attrs, 'polyline' => $attrs, 'polygon' => $attrs,
		'ellipse' => $attrs, 'defs' => $attrs, 'lineargradient' => $attrs,
		'radialgradient' => $attrs, 'stop' => $attrs, 'clippath' => $attrs, 'title' => $attrs,
	);
}

/**
 * Monta a URL de WhatsApp a partir do número cadastrado nas Opções do Site.
 *
 * @param string $formato 'link' (wa.me) ou 'api' (api.whatsapp.com, para o botão flutuante).
 * @param string $texto   Mensagem pré-preenchida, opcional.
 * @return string URL, ou string vazia se não houver número cadastrado.
 */
function aw_whatsapp_url($formato = 'link', $texto = '')
{
	$numero = preg_replace('/\D/', '', (string) aw_field('whatsapp_numero', 'option', ''));

	if (!$numero) {
		return '';
	}

	if ('api' === $formato) {
		return add_query_arg(
			array('phone' => $numero, 'text' => $texto ?: null),
			'https://api.whatsapp.com/send/'
		);
	}

	return 'https://wa.me/' . $numero . ($texto ? '?text=' . rawurlencode($texto) : '');
}

/**
 * Resumo curto e seguro do conteúdo, para cards.
 *
 * @param int|null $post_id
 * @param int      $limite Número de caracteres.
 * @return string
 */
function aw_resumo($post_id = null, $limite = 160)
{
	$post_id = $post_id ?: get_the_ID();
	$texto   = has_excerpt($post_id)
		? get_the_excerpt($post_id)
		: wp_strip_all_tags(get_post_field('post_content', $post_id));

	$texto = trim(preg_replace('/\s+/', ' ', $texto));

	return mb_strimwidth($texto, 0, $limite, '…');
}

/**
 * URL da imagem destacada com fallback para o placeholder do tema.
 *
 * @param int|null $post_id
 * @param string   $tamanho
 * @return string
 */
function aw_thumb($post_id = null, $tamanho = 'aw-card')
{
	$post_id = $post_id ?: get_the_ID();
	$url     = get_the_post_thumbnail_url($post_id, $tamanho);

	return $url ?: AW_IMG . 'placeholder.svg';
}

/**
 * Extrai provedor, ID, URL de embed e thumbnail de um link de YouTube ou Vimeo.
 *
 * Herdado do helper get_acf_oembed_data() da v2, agora sem depender do ACF e
 * com o resultado cacheado (a consulta à API pública do Vimeo é remota).
 *
 * @param string $url Link do vídeo, ou o iframe devolvido por um campo oEmbed.
 * @return array{provedor:string,id:string,embed:string,thumb:string}|null
 */
function aw_video_data($url)
{
	if (!$url) {
		return null;
	}

	// Campo oEmbed do ACF devolve o <iframe> inteiro; extrai o src.
	if (false !== strpos($url, '<iframe')) {
		preg_match('/src="([^"]+)"/', $url, $m);
		$url = $m[1] ?? '';
	}

	if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
		return array(
			'provedor' => 'youtube',
			'id'       => $m[1],
			'embed'    => 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0',
			'thumb'    => 'https://i.ytimg.com/vi/' . $m[1] . '/maxresdefault.jpg',
		);
	}

	if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
		$id    = $m[1];
		$chave = 'aw_vimeo_' . $id;
		$thumb = get_transient($chave);

		if (false === $thumb) {
			$thumb    = '';
			$resposta = wp_remote_get('https://vimeo.com/api/oembed.json?url=' . rawurlencode('https://vimeo.com/' . $id), array('timeout' => 5));

			if (!is_wp_error($resposta)) {
				$dados = json_decode(wp_remote_retrieve_body($resposta), true);
				$thumb = $dados['thumbnail_url'] ?? '';
			}

			set_transient($chave, $thumb, DAY_IN_SECONDS);
		}

		return array(
			'provedor' => 'vimeo',
			'id'       => $id,
			'embed'    => 'https://player.vimeo.com/video/' . $id . '?autoplay=1',
			'thumb'    => $thumb,
		);
	}

	return null;
}

/**
 * Ícones de interface desenhados inline (stroke/fill em currentColor).
 *
 * Os ícones do layout (Figma) são de traço simples; mantê-los aqui evita um
 * arquivo por ícone em assets/img/ e permite colorir tudo por CSS.
 *
 * @param string $nome  instagram | facebook | x | threads | mail | user | chevron-down |
 *                      chevron-left | chevron-right | quote | heart | comment | whatsapp | menu | close
 * @param array  $attr  Atributos extras da tag <svg>, ex. ['class' => 'icone'].
 * @return string SVG pronto para echo, ou string vazia se o ícone não existir.
 */
function aw_icone($nome, $attr = array())
{
	$icones = array(
		'instagram'     => array('0 0 24 24', '<rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8"/><circle cx="17.4" cy="6.6" r="1.1" fill="currentColor"/>'),
		'facebook'      => array('0 0 24 24', '<path d="M13.5 21v-7.6h2.6l.4-3h-3V8.5c0-.9.25-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.3H7.9v3h2.6V21h3Z" fill="currentColor"/>'),
		'user'          => array('0 0 24 24', '<circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 20c.8-3.6 3.9-5.5 7.5-5.5s6.7 1.9 7.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'),
		'chevron-down'  => array('0 0 24 24', '<path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
		'chevron-left'  => array('0 0 24 24', '<path d="m15 6-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
		'chevron-right' => array('0 0 24 24', '<path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
		'quote'         => array('0 0 32 32', '<path d="M5 25v-7.5C5 11.6 8 8 13 7l1 2.4c-2.8.9-4.3 3-4.5 5.6H13V25H5Zm13 0v-7.5C18 11.6 21 8 26 7l1 2.4c-2.8.9-4.3 3-4.5 5.6H26V25h-8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>'),
		'heart'         => array('0 0 24 24', '<path d="M12 20.3s-7.8-4.6-9.2-9.4C1.9 7.8 3.8 4.5 7.1 4.5c2 0 3.4 1.1 4.9 2.9 1.5-1.8 2.9-2.9 4.9-2.9 3.3 0 5.2 3.3 4.3 6.4-1.4 4.8-9.2 9.4-9.2 9.4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>'),
		'comment'       => array('0 0 24 24', '<path d="M20.5 11.6a8.4 8.4 0 0 1-12.3 7.5L3.5 20.5l1.4-4.5a8.4 8.4 0 1 1 15.6-4.4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>'),
		'whatsapp'      => array('0 0 24 24', '<path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.06c-.25.69-1.45 1.32-2 1.37-.55.05-1.06.24-3.57-.99-2.51-1.23-4.05-3.9-4.18-4.08-.13-.19-.99-1.35-.95-2.55.04-1.2.68-1.77.92-2.02.24-.25.52-.31.7-.31.18 0 .37 0 .53.01.17.01.4-.06.62.48.22.54.75 1.87.82 2 .06.14.11.3.02.48-.09.19-.17.3-.33.48-.16.18-.35.4-.5.54-.16.14-.33.3-.15.6.18.31.8 1.36 1.72 2.19 1.18 1.07 2.19 1.38 2.5 1.53.31.15.5.13.68-.07.19-.2.79-.9 1-1.21.21-.31.42-.25.7-.15.28.1 1.79.86 2.1 1.02.31.15.51.23.59.36.07.13.07.79-.18 1.48Z" fill="currentColor"/>'),
		'x'             => array('0 0 24 24', '<path d="M4 4l6.6 8.8L4.3 20h1.9l5.3-6.1 4.6 6.1H20l-7-9.3L18.9 4H17l-4.9 5.6L8 4H4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>'),
		'mail'          => array('0 0 24 24', '<rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'),
		'threads'       => array('0 0 24 24', '<path d="M16.6 11.2c-.4-2.6-2-3.9-4.5-3.9-2.9 0-4.6 2-4.6 4.9 0 3.2 1.9 5.1 4.8 5.1 2.6 0 4.4-1.6 4.4-3.8 0-2-1.6-3.1-3.8-3.1-1.8 0-3.1.9-3.1 2.3 0 1.2 1 2 2.4 2 2.3 0 3.4-1.7 3.4-4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M19.5 7.5A8.5 8.5 0 1 0 20.5 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'),
		'telefone'      => array('0 0 24 24', '<path d="M8.6 3.5H5.8A1.8 1.8 0 0 0 4 5.4C4.4 13.6 10.4 19.6 18.6 20a1.8 1.8 0 0 0 1.9-1.8v-2.8a1 1 0 0 0-.8-1l-3.4-.8a1 1 0 0 0-1 .3l-1.5 1.6a12.5 12.5 0 0 1-5.3-5.3l1.6-1.5a1 1 0 0 0 .3-1l-.8-3.4a1 1 0 0 0-1-.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14.5 3.5a6 6 0 0 1 6 6M14.5 7a2.5 2.5 0 0 1 2.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'),
		'pin'           => array('0 0 24 24', '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.6"/>'),
		'play'          => array('0 0 32 32', '<path d="M10.5 6.8v18.4a1.2 1.2 0 0 0 1.8 1l14.5-9.2a1.2 1.2 0 0 0 0-2L12.3 5.8a1.2 1.2 0 0 0-1.8 1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>'),
		'navegar'       => array('0 0 24 24', '<path d="M20.5 3.5 3.5 10.6l7.1 2.8 2.8 7.1 7.1-17Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'),
		'mapa'          => array('0 0 24 24', '<path d="M9 4 3.5 6v14L9 18l6 2 5.5-2V4L15 6 9 4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 4v14M15 6v14" stroke="currentColor" stroke-width="1.5"/>'),
		'menu'          => array('0 0 24 24', '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'),
		'close'         => array('0 0 24 24', '<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'),
	);

	if (!isset($icones[$nome])) {
		return '';
	}

	$attr = wp_parse_args($attr, array('aria-hidden' => 'true', 'focusable' => 'false'));
	$extra = '';

	foreach ($attr as $chave => $valor) {
		$extra .= sprintf(' %s="%s"', esc_attr($chave), esc_attr($valor));
	}

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" fill="none"%s>%s</svg>',
		$icones[$nome][0],
		$extra,
		$icones[$nome][1]
	);
}

/**
 * Redes sociais do site, com ícone resolvido pelo nome da rede.
 *
 * Lê o repeater "redes_sociais" das Opções do Site. Sem cadastro, devolve
 * Instagram e Facebook do layout, para o header/rodapé não ficarem vazios
 * enquanto o conteúdo não é preenchido.
 *
 * @return array<int, array{nome:string,url:string,icone:string}>
 */
function aw_redes_sociais()
{
	$redes = aw_field('redes_sociais', 'option', array());
	$lista = array();

	if (is_array($redes)) {
		foreach ($redes as $rede) {
			if (empty($rede['url'])) {
				continue;
			}

			$nome  = (string) ($rede['nome'] ?? '');
			$chave = sanitize_title($nome);

			$lista[] = array(
				'nome'  => $nome,
				'url'   => $rede['url'],
				'icone' => in_array($chave, array('instagram', 'facebook'), true) ? $chave : '',
				'img'   => $rede['icone']['url'] ?? '',
			);
		}
	}

	if (!$lista) {
		$lista = array(
			array('nome' => 'Instagram', 'url' => 'https://www.instagram.com/' . aw_instagram_usuario() . '/', 'icone' => 'instagram', 'img' => ''),
			array('nome' => 'Facebook', 'url' => '#', 'icone' => 'facebook', 'img' => ''),
		);
	}

	return $lista;
}

/**
 * Usuário do Instagram (sem @), das Opções do Site.
 *
 * @return string
 */
function aw_instagram_usuario()
{
	return ltrim((string) aw_field('instagram_usuario', 'option', 'csoengenharia'), '@');
}

/**
 * Imprime a lista de redes sociais no formato do layout.
 *
 * @param string $classe Classe do <ul> (modificador de contexto: header, rodapé…).
 * @return void
 */
function aw_redes_html($classe = 'redes')
{
	$redes = aw_redes_sociais();

	if (!$redes) {
		return;
	}

	echo '<ul class="' . esc_attr($classe) . '">';

	foreach ($redes as $rede) {
		echo '<li><a href="' . esc_url($rede['url']) . '" target="_blank" rel="noopener noreferrer">';
		echo '<span class="sr-only">' . esc_html($rede['nome']) . '</span>';

		if ($rede['icone']) {
			echo aw_icone($rede['icone']); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ($rede['img']) {
			echo '<img src="' . esc_url($rede['img']) . '" alt="" width="20" height="20" loading="lazy">';
		}

		echo '</a></li>';
	}

	echo '</ul>';
}

/**
 * Logo do site: imagem das Opções do Site, custom logo do WP ou o SVG do tema.
 *
 * O SVG do tema usa currentColor, então a mesma marca serve no header (azul)
 * e no rodapé (branca) — a cor vem do CSS do contexto.
 *
 * @param string $campo Campo ACF de opções a consultar ('logo' ou 'logo_rodape').
 * @return void
 */
function aw_logo($campo = 'logo')
{
	$logo = aw_field($campo, 'option', '');

	if (is_array($logo) && !empty($logo['url'])) {
		printf(
			'<img src="%s" alt="%s" width="%d" height="%d">',
			esc_url($logo['url']),
			esc_attr($logo['alt'] ?: get_bloginfo('name')),
			(int) ($logo['width'] ?? 176),
			(int) ($logo['height'] ?? 85)
		);
		return;
	}

	if ('logo' === $campo && has_custom_logo()) {
		$id = get_theme_mod('custom_logo');
		echo wp_get_attachment_image($id, 'full', false, array('alt' => get_bloginfo('name')));
		return;
	}

	aw_svg('logo-cso.svg', array('aria-label' => get_bloginfo('name')));
}

/**
 * URL de uma página pelo slug, com fallback para /{slug}/ quando ela ainda
 * não existe — os menus estáticos do layout funcionam antes do conteúdo.
 *
 * @param string $slug
 * @return string
 */
function aw_url_pagina($slug)
{
	$pagina = get_page_by_path($slug);

	return $pagina ? get_permalink($pagina) : home_url('/' . trim($slug, '/') . '/');
}

/**
 * URL da listagem de empreendimentos, opcionalmente filtrada por status.
 *
 * @param string $status Slug do termo de status_obra.
 * @return string
 */
function aw_url_empreendimentos($status = '')
{
	$url = aw_url_pagina('empreendimentos');

	return $status ? add_query_arg('status', $status, $url) : $url;
}

/**
 * Mensagem de listagem vazia de empreendimentos (SSR e AJAX).
 *
 * @return string
 */
function aw_empreendimentos_vazio_html()
{
	return '<p class="aw-vazio emp-grid__vazio">' . esc_html__('Nenhum empreendimento nesta categoria no momento.', 'alfama-web') . '</p>';
}

/**
 * URL do blog (página de posts ou /blog/).
 *
 * @return string
 */
function aw_url_blog()
{
	$id = (int) get_option('page_for_posts');

	return $id ? get_permalink($id) : home_url('/blog/');
}

/**
 * Imprime um menu estático com a mesma marcação do wp_nav_menu().
 *
 * Usado como fallback_cb enquanto o menu não é montado em Aparência > Menus,
 * para que o CSS escrito para .menu-item/.sub-menu valha nos dois casos.
 *
 * @param array  $itens  Lista de ['rotulo' => '', 'url' => '', 'filhos' => []].
 * @param string $classe Classe do <ul> raiz.
 * @return void
 */
function aw_menu_estatico(array $itens, $classe = 'menu')
{
	echo '<ul class="' . esc_attr($classe) . '">';

	foreach ($itens as $item) {
		$filhos = $item['filhos'] ?? array();
		$li     = 'menu-item' . ($filhos ? ' menu-item-has-children' : '');

		echo '<li class="' . esc_attr($li) . '"><a href="' . esc_url($item['url']) . '">' . esc_html($item['rotulo']) . '</a>';

		if ($filhos) {
			echo '<ul class="sub-menu">';
			foreach ($filhos as $filho) {
				echo '<li class="menu-item"><a href="' . esc_url($filho['url']) . '">' . esc_html($filho['rotulo']) . '</a></li>';
			}
			echo '</ul>';
		}

		echo '</li>';
	}

	echo '</ul>';
}

/**
 * Itens do submenu "Empreendimentos" — header e rodapé usam os mesmos.
 *
 * @return array
 */
function aw_itens_empreendimentos()
{
	return array(
		array('rotulo' => __('Todos', 'alfama-web'), 'url' => aw_url_empreendimentos()),
		array('rotulo' => __('Lançamentos', 'alfama-web'), 'url' => aw_url_empreendimentos('lancamento')),
		array('rotulo' => __('Em Obras', 'alfama-web'), 'url' => aw_url_empreendimentos('em-obras')),
		array('rotulo' => __('Obras Públicas', 'alfama-web'), 'url' => aw_url_empreendimentos('obras-publicas')),
		array('rotulo' => __('Portfólio', 'alfama-web'), 'url' => aw_url_empreendimentos('esgotado')),
	);
}

/**
 * Fallback do menu principal (theme_location "primary").
 *
 * @param array $args Argumentos do wp_nav_menu().
 * @return void
 */
function aw_fallback_menu_principal($args = array())
{
	$itens = array(
		array('rotulo' => __('A empresa', 'alfama-web'), 'url' => aw_url_pagina('quem-somos')),
		array('rotulo' => __('Empreendimentos', 'alfama-web'), 'url' => aw_url_empreendimentos(), 'filhos' => array(
			aw_itens_empreendimentos()[0],
			aw_itens_empreendimentos()[2],
			aw_itens_empreendimentos()[1],
			aw_itens_empreendimentos()[3],
			aw_itens_empreendimentos()[4],
		)),
		array('rotulo' => __('Simulador', 'alfama-web'), 'url' => aw_url_pagina('simulador')),
		array('rotulo' => __('Blog', 'alfama-web'), 'url' => aw_url_blog()),
		array('rotulo' => __('Fale Conosco', 'alfama-web'), 'url' => aw_url_pagina('contato')),
	);

	aw_menu_estatico($itens, $args['menu_class'] ?? 'menu');
}

/**
 * Fallback do menu "Navegação" do rodapé (theme_location "rodape").
 *
 * @param array $args
 * @return void
 */
function aw_fallback_menu_rodape($args = array())
{
	aw_menu_estatico(array(
		array('rotulo' => __('A Empresa', 'alfama-web'), 'url' => aw_url_pagina('quem-somos')),
		array('rotulo' => __('Simulador', 'alfama-web'), 'url' => aw_url_pagina('simulador')),
		array('rotulo' => __('Portal do Cliente', 'alfama-web'), 'url' => aw_field('portal_cliente_url', 'option', '#')),
		array('rotulo' => __('Fale Conosco', 'alfama-web'), 'url' => aw_url_pagina('contato')),
		array('rotulo' => __('Blog', 'alfama-web'), 'url' => aw_url_blog()),
	), $args['menu_class'] ?? 'menu');
}

/**
 * Fallback do menu "Empreendimentos" do rodapé (theme_location "rodape-empreendimentos").
 *
 * @param array $args
 * @return void
 */
function aw_fallback_menu_rodape_empreendimentos($args = array())
{
	aw_menu_estatico(aw_itens_empreendimentos(), $args['menu_class'] ?? 'menu');
}

/**
 * Links de compartilhamento de um post (barra lateral da notícia).
 *
 * Instagram não aceita compartilhamento por URL; o ícone leva ao perfil da CSO.
 *
 * @param int|null $post_id
 * @return array<int, array{rede:string,rotulo:string,url:string}>
 */
function aw_links_compartilhar($post_id = null)
{
	$post_id = $post_id ?: get_the_ID();
	$url     = get_permalink($post_id);
	$titulo  = wp_strip_all_tags(get_the_title($post_id));
	$texto   = $titulo . ' ' . $url;

	return array(
		array('rede' => 'whatsapp', 'rotulo' => __('Compartilhar no WhatsApp', 'alfama-web'), 'url' => 'https://api.whatsapp.com/send?text=' . rawurlencode($texto)),
		array('rede' => 'instagram', 'rotulo' => __('Instagram da CSO Engenharia', 'alfama-web'), 'url' => 'https://www.instagram.com/' . aw_instagram_usuario() . '/'),
		array('rede' => 'facebook', 'rotulo' => __('Compartilhar no Facebook', 'alfama-web'), 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url)),
		array('rede' => 'x', 'rotulo' => __('Compartilhar no X', 'alfama-web'), 'url' => 'https://twitter.com/intent/tweet?url=' . rawurlencode($url) . '&text=' . rawurlencode($titulo)),
		array('rede' => 'mail', 'rotulo' => __('Enviar por e-mail', 'alfama-web'), 'url' => 'mailto:?subject=' . rawurlencode($titulo) . '&body=' . rawurlencode($url)),
		array('rede' => 'threads', 'rotulo' => __('Compartilhar no Threads', 'alfama-web'), 'url' => 'https://www.threads.net/intent/post?text=' . rawurlencode($texto)),
	);
}

/**
 * Slug "do tema" de uma página: o post_name, exceto a página de privacidade
 * escolhida em Configurações › Privacidade, que é sempre
 * 'politica-de-privacidade' — qualquer que seja o slug dela no banco. Assim
 * template, CSS e grupo ACF da política funcionam mesmo se o WordPress criou
 * a página com outro endereço.
 *
 * @param int $page_id
 * @return string
 */
function aw_slug_pagina($page_id)
{
	$page_id = (int) $page_id;

	if ($page_id && $page_id === (int) get_option('wp_page_for_privacy_policy')) {
		return 'politica-de-privacidade';
	}

	return (string) get_post_field('post_name', $page_id);
}

