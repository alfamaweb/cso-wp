<?php
/**
 * Alfama WEB v3 — bootstrap do tema.
 *
 * Este arquivo NÃO contém lógica. Ele define as constantes de caminho e
 * carrega os módulos de inc/. Toda regra vive em um módulo próprio, para que
 * functions.php nunca volte a virar um arquivo de 1.500 linhas.
 *
 * @package AlfamaWeb
 */

defined('ABSPATH') || exit;

/* -------------------------------------------------------------------------
 * Constantes de caminho (convenção Alfama, mantida desde a v2)
 * ---------------------------------------------------------------------- */
define('AW_VERSION', '3.0.0');
define('AW_DIR', get_template_directory());
define('AW_URI', get_template_directory_uri());
define('AW_IMG', AW_URI . '/assets/img/');
define('AW_CSS', AW_URI . '/assets/css/');
define('AW_JS',  AW_URI . '/assets/js/');

/** Aliases dos nomes usados na v2, para portar templates antigos sem reescrever. */
define('THEME_URI', AW_URI);
define('IMG_URI', AW_IMG);
define('CSS_URI', AW_CSS);
define('JS_URI',  AW_JS);

/* -------------------------------------------------------------------------
 * Módulos — a ordem importa: helpers e ambiente antes de quem os consome.
 * ---------------------------------------------------------------------- */
require_once AW_DIR . '/inc/helpers.php';
require_once AW_DIR . '/inc/environment.php';
require_once AW_DIR . '/inc/setup.php';
require_once AW_DIR . '/inc/assets.php';
require_once AW_DIR . '/inc/post-types.php';
require_once AW_DIR . '/inc/acf.php';
require_once AW_DIR . '/inc/pagination.php';
require_once AW_DIR . '/inc/scaffolding.php';
require_once AW_DIR . '/inc/indices-economicos.php';

// Importação única do conteúdo do Figma (pode ser removida depois de rodar).
if (is_admin() && file_exists(AW_DIR . '/inc/conteudo-inicial.php')) {
	require_once AW_DIR . '/inc/conteudo-inicial.php';
}

require_once AW_DIR . '/inc/Services/class-aw-query-service.php';
require_once AW_DIR . '/inc/Ajax/class-aw-ajax-controller.php';
require_once AW_DIR . '/inc/Forms/class-aw-mailer.php';
require_once AW_DIR . '/inc/Forms/class-aw-recaptcha.php';
require_once AW_DIR . '/inc/Forms/email-template.php';
require_once AW_DIR . '/inc/Forms/class-aw-form-dispatcher.php';
require_once AW_DIR . '/inc/Forms/handlers/contato.php';

AW_Ajax_Controller::init();
AW_Mailer::init();
AW_Form_Dispatcher::init();
