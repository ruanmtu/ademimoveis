<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

$cfg = ROOT_PATH . '/config/config.php';
if (!is_file($cfg)) {
    http_response_code(500);
    exit('Configuração não encontrada. Copie config/config.exemplo.php para config/config.php e preencha seus dados.');
}
require $cfg;

date_default_timezone_set(TIMEZONE);
mb_internal_encoding('UTF-8');

/** Sai da conta depois de tanto tempo sem usar o site (segundos). */
const SESSAO_INATIVA_MAX = 4 * 3600;
const SESSAO_INATIVA_ADMIN = 2 * 3600;

require __DIR__ . '/seguranca.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // erro inesperado: registra no log do servidor e mostra uma página simples, sem detalhes técnicos
    set_exception_handler(function (Throwable $ex): void {
        error_log('[Ademimoveis] ' . get_class($ex) . ': ' . $ex->getMessage() . ' em ' . $ex->getFile() . ':' . $ex->getLine());
        if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
        echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Algo deu errado</title><body style="font-family:system-ui,sans-serif;background:#f5f3f0;color:#221a15;display:grid;place-items:center;min-height:90vh;margin:0;padding:20px">'
           . '<div style="max-width:440px;text-align:center"><h1 style="font-size:1.5rem">Algo deu errado por aqui</h1>'
           . '<p style="color:#6b5f57">Não foi possível concluir esta ação. Tente de novo em alguns instantes. Se continuar, fale com o nosso atendimento.</p>'
           . '<p><a href="/" style="color:#9d4a12;font-weight:700">Voltar para o início</a></p></div></body></html>';
    });
}

if (PHP_SAPI !== 'cli') {
    forcar_https();
    cabecalhos_seguranca();
}

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string) (SESSAO_INATIVA_MAX));
    session_name('ademoveis_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => conexao_https(),
    ]);
    session_start();
}

define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Nome do site. Corrige automaticamente grafias antigas ("ADemóveis", "Ademóveis") de config.php já instalados.
define('SITE_NOME', in_array(APP_NAME, ['ADemóveis', 'ADemoveis', 'ADEMÓVEIS', 'Ademóveis', 'Ademoveis', ''], true) ? 'Ademimóveis' : APP_NAME);

/**
 * Endereço do site. Se APP_URL estiver vazio no config.php, é detectado automaticamente
 * (funciona em localhost, na Hostinger e em qualquer pasta). Também ignora um APP_URL
 * de "localhost" quando o site está rodando em outro domínio.
 */
function detectar_base_url(): string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) return rtrim(APP_URL, '/');
    $https = conexao_https();
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST']);
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $raiz = realpath(ROOT_PATH) ?: ROOT_PATH;
    $pasta = ($docRoot !== '' && str_starts_with($raiz, $docRoot)) ? str_replace('\\', '/', substr($raiz, strlen($docRoot))) : '';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($pasta, '/');
}

$cfgUrl = rtrim((string) APP_URL, '/');
$cfgHost = (string) parse_url($cfgUrl, PHP_URL_HOST);
$reqHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
$usarDetectado = $cfgUrl === ''
    || (in_array($cfgHost, ['localhost', '127.0.0.1'], true) && $reqHost !== '' && !in_array($reqHost, ['localhost', '127.0.0.1'], true));
define('BASE_URL', $usarDetectado ? detectar_base_url() : $cfgUrl);

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/planos.php';
require __DIR__ . '/documentos.php';

// Sessão parada por muito tempo: sai da conta (menos tempo para administradores)
if (PHP_SAPI !== 'cli' && !empty($_SESSION['uid'])) {
    $limite = ($_SESSION['admin'] ?? false) ? SESSAO_INATIVA_ADMIN : SESSAO_INATIVA_MAX;
    if (isset($_SESSION['ultimo_uso']) && time() - (int) $_SESSION['ultimo_uso'] > $limite) {
        sair();
        session_start();
        flash('aviso', 'Por segurança, você saiu da conta depois de um tempo sem uso. Entre de novo para continuar.');
    } else {
        $_SESSION['ultimo_uso'] = time();
    }
}
