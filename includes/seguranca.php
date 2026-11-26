<?php
/**
 * Segurança do site: HTTPS, cabeçalhos de proteção, limite de tentativas,
 * registro de eventos e regras de senha.
 */
declare(strict_types=1);

/** true quando a página chegou por HTTPS (direto ou atrás do proxy/CDN da hospedagem). */
function conexao_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function host_local(): bool
{
    $h = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    return $h === '' || $h === 'localhost' || $h === '127.0.0.1' || $h === '[::1]' || str_ends_with($h, '.test') || str_ends_with($h, '.localhost');
}

/** Redireciona para HTTPS (fora do computador local). Desligue com define('FORCAR_HTTPS', false) no config.php. */
function forcar_https(): void
{
    if (PHP_SAPI === 'cli' || (defined('FORCAR_HTTPS') && !FORCAR_HTTPS) || conexao_https() || host_local()) return;
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) $uri = '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

/** Cabeçalhos HTTP que protegem contra XSS, clickjacking, vazamento de dados e uso indevido do navegador. */
function cabecalhos_seguranca(): void
{
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: blob:",
        "connect-src 'self' https://viacep.com.br",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        // o pagamento sai do site para o Mercado Pago depois do envio do formulário
        "form-action 'self' https://*.mercadopago.com.br https://*.mercadopago.com https://*.mercadolivre.com.br https://*.mercadolibre.com",
        "frame-ancestors 'self'",
    ];
    if (conexao_https()) {
        $csp[] = 'upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

/** Página e cabeçalhos para áreas que não devem ficar em cache nem aparecer no Google. */
function pagina_privada(): void
{
    if (headers_sent()) return;
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}

function ip_cliente(): string
{
    // REMOTE_ADDR é o único valor que o visitante não consegue falsificar
    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// ---------------- Registro de eventos e limite de tentativas ----------------

/** Cria a tabela de eventos de segurança na primeira vez que for usada. */
function seg_tabela(): void
{
    static $pronta = false;
    if ($pronta) return;
    try {
        db()->query('SELECT 1 FROM seguranca_eventos LIMIT 1');
    } catch (PDOException) {
        if (DB_DRIVER === 'sqlite') {
            db()->exec('CREATE TABLE IF NOT EXISTS seguranca_eventos (id INTEGER PRIMARY KEY AUTOINCREMENT, acao VARCHAR(40) NOT NULL,
                chave CHAR(64) NOT NULL, ip VARCHAR(45) NULL, usuario_id INT NULL, detalhe VARCHAR(255) NULL, criado_em DATETIME NOT NULL)');
            db()->exec('CREATE INDEX IF NOT EXISTS idx_seg_chave ON seguranca_eventos (acao, chave, criado_em)');
        } else {
            db()->exec(file_get_contents(ROOT_PATH . '/database/atualizacao_05_seguranca.sql'));
        }
    }
    $pronta = true;
}

/** Grava um evento (login errado, conta criada, senha trocada...). $alvo é o que está sendo limitado: IP, e-mail ou id. */
function registrar_evento(string $acao, string $alvo = '', ?int $usuarioId = null, string $detalhe = ''): void
{
    try {
        seg_tabela();
        q('INSERT INTO seguranca_eventos (acao, chave, ip, usuario_id, detalhe, criado_em) VALUES (?,?,?,?,?,?)', [
            $acao, hash('sha256', mb_strtolower($alvo)), ip_cliente(), $usuarioId, mb_substr($detalhe, 0, 255) ?: null, agora(),
        ]);
        // limpeza ocasional: mantém 6 meses de histórico (prazo do Marco Civil da Internet, art. 15)
        if (random_int(1, 200) === 1) q('DELETE FROM seguranca_eventos WHERE criado_em < ?', [date('Y-m-d H:i:s', time() - 180 * 86400)]);
    } catch (Throwable $ex) {
        error_log('[Ademimoveis seguranca] ' . $ex->getMessage());
    }
}

/** Quantas vezes $acao aconteceu para $alvo nos últimos $segundos. */
function contar_eventos(string $acao, string $alvo, int $segundos): int
{
    try {
        seg_tabela();
        return (int) val('SELECT COUNT(*) FROM seguranca_eventos WHERE acao = ? AND chave = ? AND criado_em >= ?',
                         [$acao, hash('sha256', mb_strtolower($alvo)), date('Y-m-d H:i:s', time() - $segundos)]);
    } catch (Throwable $ex) {
        error_log('[Ademimoveis seguranca] ' . $ex->getMessage());
        return 0;
    }
}

function limite_excedido(string $acao, string $alvo, int $maximo, int $segundos): bool
{
    return contar_eventos($acao, $alvo, $segundos) >= $maximo;
}

/** Apaga as falhas registradas (ex.: depois de um login certo). */
function zerar_eventos(string $acao, string $alvo): void
{
    try {
        seg_tabela();
        q('DELETE FROM seguranca_eventos WHERE acao = ? AND chave = ?', [$acao, hash('sha256', mb_strtolower($alvo))]);
    } catch (Throwable $ex) {
        error_log('[Ademimoveis seguranca] ' . $ex->getMessage());
    }
}

// ---------------- Senhas ----------------

/** Retorna o motivo de a senha ser fraca, ou null se estiver boa. */
function problema_senha(string $senha, string $email = '', string $nome = ''): ?string
{
    if (strlen($senha) < 8) return 'Use pelo menos 8 caracteres.';
    if (strlen($senha) > 200) return 'Use no máximo 200 caracteres.';
    $min = mb_strtolower($senha);
    $comuns = ['12345678', '123456789', '1234567890', '87654321', '11111111', '00000000', '12341234', '11223344', 'abcd1234',
        'password', 'password1', 'senha123', 'senha1234', 'senhasenha', 'mudar123', 'qwerty123', 'qwertyui', 'asdfghjk', 'iloveyou',
        'admin123', 'admin1234', 'administrador', 'brasil123', 'imoveis123', 'ademimoveis', 'ademoveis', 'corretor123', 'flamengo',
        'corinthians', 'palmeiras', 'abc12345', 'a1b2c3d4', '123mudar', 'teste123', 'teste1234', '1q2w3e4r', '1qaz2wsx', 'aaaaaaaa'];
    if (in_array($min, $comuns, true) || preg_match('/^(.)\1+$/u', $senha)) {
        return 'Essa senha é muito comum e fácil de adivinhar. Escolha outra.';
    }
    $local = mb_strtolower(explode('@', $email)[0] ?? '');
    if ($local !== '' && mb_strlen($local) >= 4 && str_contains($min, $local)) return 'A senha não pode conter o seu e-mail.';
    $primeiro = mb_strtolower(explode(' ', trim($nome))[0] ?? '');
    if ($primeiro !== '' && mb_strlen($primeiro) >= 4 && $min === $primeiro . preg_replace('/\D/', '', $min) && $min !== $primeiro) {
        return 'Evite usar só o seu nome com números.';
    }
    return null;
}

/** Marca usada para derrubar outras sessões quando a senha muda. */
function versao_sessao(array $u): string
{
    return substr(hash('sha256', (string) $u['senha_hash'] . '|' . (int) $u['id']), 0, 24);
}
