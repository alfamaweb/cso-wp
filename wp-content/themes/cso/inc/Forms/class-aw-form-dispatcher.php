<?php
/**
 * Ponto único de entrada de todo formulário do tema.
 *
 * Generalização do dispatcher do tema enkan. Cada <form> envia um campo oculto
 * aw_form com o id do formulário; o dispatcher valida nonce, honeypot,
 * campos obrigatórios e reCAPTCHA, e só então chama o handler registrado.
 *
 * Aceita os dois transportes com o mesmo handler:
 *   - POST clássico para admin-post.php, com redirect e ?aw_form=<status>;
 *   - POST AJAX para admin-ajax.php, com resposta JSON.
 *
 * Para criar um formulário novo: registre-o com AW_Form_Dispatcher::registrar()
 * em um arquivo dentro de inc/Forms/handlers/ e inclua-o no functions.php.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

class AW_Form_Dispatcher
{
	const ACAO  = 'aw_form';
	const NONCE = 'aw_form_nonce';

	/** Teto total dos anexos de um envio. */
	const MAX_ANEXOS_BYTES = 10485760; // 10 MB

	/** @var array<string,array> */
	protected static $formularios = array();

	/**
	 * @return void
	 */
	public static function init()
	{
		add_action('admin_post_' . self::ACAO, array(__CLASS__, 'processar_post'));
		add_action('admin_post_nopriv_' . self::ACAO, array(__CLASS__, 'processar_post'));
		add_action('wp_ajax_' . self::ACAO, array(__CLASS__, 'processar_ajax'));
		add_action('wp_ajax_nopriv_' . self::ACAO, array(__CLASS__, 'processar_ajax'));
	}

	/**
	 * Registra um formulário.
	 *
	 * @param string $id     Id único, ex. 'contato'.
	 * @param array  $config {
	 *     @type callable $handler       Recebe (array $dados, array $anexos) e devolve bool|WP_Error.
	 *     @type array    $obrigatorios  Mapa campo => rótulo, usado na validação e na mensagem.
	 *     @type array    $campos        Mapa campo => rótulo de todos os campos aceitos.
	 *     @type bool     $anexos        Se aceita upload (campo 'anexos').
	 *     @type string[] $mime_aceitos  Extensões aceitas, ex. ['pdf','doc','docx'].
	 * }
	 * @return void
	 */
	public static function registrar($id, array $config)
	{
		self::$formularios[sanitize_key($id)] = wp_parse_args($config, array(
			'handler'      => null,
			'obrigatorios' => array(),
			'campos'       => array(),
			'anexos'       => false,
			'mime_aceitos' => array('pdf', 'doc', 'docx'),
		));
	}

	/**
	 * @param string $id
	 * @return array|null
	 */
	public static function get($id)
	{
		return self::$formularios[sanitize_key($id)] ?? null;
	}

	/**
	 * Campos ocultos obrigatórios de todo formulário do tema.
	 *
	 * @param string $id
	 * @return void
	 */
	public static function campos_ocultos($id)
	{
		$id = sanitize_key($id);

		wp_nonce_field(self::NONCE . '_' . $id, '_aw_nonce');
		printf('<input type="hidden" name="action" value="%s">', esc_attr(self::ACAO));
		printf('<input type="hidden" name="aw_form" value="%s">', esc_attr($id));
		printf('<input type="hidden" name="aw_redirect" value="%s">', esc_url(get_permalink() ?: home_url('/')));

		// Honeypot: bots preenchem, humanos não veem. Escondido por CSS em style.css.
		echo '<div class="aw-hp" aria-hidden="true">'
			. '<label>' . esc_html__('Não preencha este campo', 'alfama-web')
			. '<input type="text" name="aw_hp" tabindex="-1" autocomplete="off"></label></div>';

		printf('<input type="hidden" name="aw_recaptcha_token" value="" data-aw-recaptcha-action="%s">', esc_attr($id));
	}

	/**
	 * Fluxo comum de validação e execução.
	 *
	 * @return array{status:string,mensagem:string}
	 */
	protected static function executar()
	{
		$id     = isset($_POST['aw_form']) ? sanitize_key(wp_unslash($_POST['aw_form'])) : '';
		$config = self::get($id);

		if (!$config || !is_callable($config['handler'])) {
			return array('status' => 'invalido', 'mensagem' => __('Formulário não reconhecido.', 'alfama-web'));
		}

		$nonce = isset($_POST['_aw_nonce']) ? wp_unslash($_POST['_aw_nonce']) : '';

		if (!wp_verify_nonce($nonce, self::NONCE . '_' . $id)) {
			return array('status' => 'expirado', 'mensagem' => __('A página ficou aberta por muito tempo. Recarregue e tente novamente.', 'alfama-web'));
		}

		// Honeypot preenchido: responde "sucesso" para não dar pista ao bot.
		if (!empty($_POST['aw_hp'])) {
			return array('status' => 'success', 'mensagem' => __('Enviado.', 'alfama-web'));
		}

		$dados = self::coletar($config['campos']);

		$faltando = array();

		foreach ($config['obrigatorios'] as $campo => $rotulo) {
			if (empty($dados[$campo])) {
				$faltando[] = $rotulo;
			}
		}

		if ($faltando) {
			return array(
				'status'   => 'obrigatorio',
				'mensagem' => sprintf(
					/* translators: %s: lista de campos separados por vírgula. */
					__('Preencha os campos obrigatórios: %s.', 'alfama-web'),
					implode(', ', $faltando)
				),
			);
		}

		$token = isset($_POST['aw_recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['aw_recaptcha_token'])) : '';

		if (!AW_Recaptcha::verificar($token, $id)) {
			return array('status' => 'recaptcha', 'mensagem' => __('Não foi possível confirmar que você não é um robô. Recarregue a página e tente novamente.', 'alfama-web'));
		}

		$anexos = $config['anexos'] ? self::processar_anexos($config['mime_aceitos']) : array();

		if (is_wp_error($anexos)) {
			return array('status' => 'anexo', 'mensagem' => $anexos->get_error_message());
		}

		$resultado = call_user_func($config['handler'], $dados, $anexos);

		self::limpar_anexos($anexos);

		if (is_wp_error($resultado)) {
			return array('status' => 'erro', 'mensagem' => $resultado->get_error_message());
		}

		if (!$resultado) {
			return array('status' => 'erro', 'mensagem' => __('Não foi possível enviar seus dados agora. Tente novamente em alguns minutos.', 'alfama-web'));
		}

		return array('status' => 'success', 'mensagem' => __('Seus dados foram enviados. Em breve entraremos em contato.', 'alfama-web'));
	}

	/**
	 * POST clássico: executa e redireciona com o status na query string.
	 *
	 * @return void
	 */
	public static function processar_post()
	{
		$resultado = self::executar();

		$destino = isset($_POST['aw_redirect']) ? wp_unslash($_POST['aw_redirect']) : home_url('/');
		$destino = wp_validate_redirect($destino, home_url('/'));

		wp_safe_redirect(add_query_arg('aw_form', $resultado['status'], $destino) . '#aw-form');
		exit;
	}

	/**
	 * POST AJAX: executa e devolve JSON.
	 *
	 * @return void
	 */
	public static function processar_ajax()
	{
		$resultado = self::executar();

		if ('success' === $resultado['status']) {
			wp_send_json_success($resultado);
		}

		wp_send_json_error($resultado, 200);
	}

	/**
	 * Higieniza os campos declarados no registro do formulário.
	 *
	 * Campo não declarado é ignorado — o handler nunca recebe entrada arbitrária.
	 *
	 * @param array<string,string> $campos
	 * @return array<string,mixed>
	 */
	protected static function coletar(array $campos)
	{
		$dados = array();

		foreach ($campos as $campo => $rotulo) {
			$bruto = $_POST[$campo] ?? '';

			if (is_array($bruto)) {
				$dados[$campo] = array_map('sanitize_text_field', array_map('wp_unslash', $bruto));
				continue;
			}

			$bruto = wp_unslash($bruto);

			if (false !== strpos($campo, 'email')) {
				$dados[$campo] = sanitize_email($bruto);
			} elseif (false !== strpos($campo, 'mensagem') || false !== strpos($campo, 'observ')) {
				$dados[$campo] = sanitize_textarea_field($bruto);
			} elseif (false !== strpos($campo, 'url') || false !== strpos($campo, 'site')) {
				$dados[$campo] = esc_url_raw($bruto);
			} else {
				$dados[$campo] = sanitize_text_field($bruto);
			}
		}

		return $dados;
	}

	/**
	 * Move os uploads para um diretório temporário e devolve os caminhos.
	 *
	 * @param string[] $extensoes
	 * @return string[]|WP_Error
	 */
	protected static function processar_anexos(array $extensoes)
	{
		if (empty($_FILES['anexos']['name'])) {
			return array();
		}

		$nomes = (array) $_FILES['anexos']['name'];
		$tmps  = (array) $_FILES['anexos']['tmp_name'];
		$sizes = (array) $_FILES['anexos']['size'];

		$total   = 0;
		$anexos  = array();
		$destino = get_temp_dir() . 'aw-anexos-' . wp_generate_password(8, false) . '/';

		if (!wp_mkdir_p($destino)) {
			return new WP_Error('aw_anexo', __('Não foi possível processar os anexos.', 'alfama-web'));
		}

		foreach ($nomes as $i => $nome) {
			if (!$nome || empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) {
				continue;
			}

			$total += (int) $sizes[$i];

			if ($total > self::MAX_ANEXOS_BYTES) {
				self::limpar_anexos($anexos);
				return new WP_Error('aw_anexo', sprintf(
					/* translators: %s: tamanho legível, ex. "10 MB". */
					__('Os anexos somam mais de %s. Envie arquivos menores.', 'alfama-web'),
					size_format(self::MAX_ANEXOS_BYTES)
				));
			}

			$checagem = wp_check_filetype_and_ext($tmps[$i], $nome);
			$ext      = $checagem['ext'] ?: pathinfo($nome, PATHINFO_EXTENSION);

			if (!$ext || !in_array(strtolower($ext), array_map('strtolower', $extensoes), true)) {
				self::limpar_anexos($anexos);
				return new WP_Error('aw_anexo', sprintf(
					/* translators: %s: lista de extensões. */
					__('Formato de arquivo não aceito. Envie apenas: %s.', 'alfama-web'),
					implode(', ', $extensoes)
				));
			}

			$caminho = $destino . sanitize_file_name($nome);

			if (move_uploaded_file($tmps[$i], $caminho)) {
				$anexos[] = $caminho;
			}
		}

		return $anexos;
	}

	/**
	 * @param mixed $anexos
	 * @return void
	 */
	protected static function limpar_anexos($anexos)
	{
		if (!is_array($anexos)) {
			return;
		}

		$dirs = array();

		foreach ($anexos as $caminho) {
			if (is_file($caminho)) {
				$dirs[dirname($caminho)] = true;
				wp_delete_file($caminho);
			}
		}

		foreach (array_keys($dirs) as $dir) {
			@rmdir($dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}

	/**
	 * Mensagem correspondente a um status, para o feedback no front.
	 *
	 * @param string $status
	 * @return array{tipo:string,titulo:string,texto:string}|null
	 */
	public static function feedback($status)
	{
		$mapa = array(
			'success' => array(
				'tipo'   => 'success',
				'titulo' => __('Enviado com sucesso!', 'alfama-web'),
				'texto'  => __('Seus dados foram encaminhados à nossa equipe. Em breve entraremos em contato.', 'alfama-web'),
			),
			'obrigatorio' => array(
				'tipo'   => 'warning',
				'titulo' => __('Campos obrigatórios', 'alfama-web'),
				'texto'  => __('Preencha todos os campos obrigatórios antes de enviar.', 'alfama-web'),
			),
			'recaptcha' => array(
				'tipo'   => 'error',
				'titulo' => __('Verificação de segurança falhou', 'alfama-web'),
				'texto'  => __('Não foi possível confirmar que você não é um robô. Recarregue a página e tente novamente.', 'alfama-web'),
			),
			'expirado' => array(
				'tipo'   => 'warning',
				'titulo' => __('Sessão expirada', 'alfama-web'),
				'texto'  => __('A página ficou aberta por muito tempo. Recarregue e envie de novo.', 'alfama-web'),
			),
			'anexo' => array(
				'tipo'   => 'error',
				'titulo' => __('Problema no anexo', 'alfama-web'),
				'texto'  => __('Verifique o formato e o tamanho dos arquivos enviados.', 'alfama-web'),
			),
			'erro' => array(
				'tipo'   => 'error',
				'titulo' => __('Erro no envio', 'alfama-web'),
				'texto'  => __('Não foi possível enviar seus dados agora. Tente novamente em alguns minutos.', 'alfama-web'),
			),
			'invalido' => array(
				'tipo'   => 'error',
				'titulo' => __('Erro no envio', 'alfama-web'),
				'texto'  => __('O formulário não foi reconhecido. Recarregue a página.', 'alfama-web'),
			),
		);

		return $mapa[$status] ?? null;
	}
}
