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

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('ademoveis_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Nome do site. Corrige automaticamente a grafia antiga "ADemóveis" de config.php já instalados.
define('SITE_NOME', in_array(APP_NAME, ['ADemóveis', 'ADemoveis', 'ADEMÓVEIS', ''], true) ? 'Ademóveis' : APP_NAME);

/**
 * Endereço do site. Se APP_URL estiver vazio no config.php, é detectado automaticamente
 * (funciona em localhost, na Hostinger e em qualquer pasta). Também ignora um APP_URL
 * de "localhost" quando o site está rodando em outro domínio.
 */
function detectar_base_url(): string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) return rtrim(APP_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') == 443;
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

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
