<?php
declare(strict_types=1);

function usuario(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $u = one('SELECT * FROM usuarios WHERE id = ?', [(int) $_SESSION['uid']]);
        if ($u && $u['status'] === 'ativo') {
            $cache = $u;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $cache;
}

function logado(): bool { return usuario() !== null; }

function e_admin(): bool { return (usuario()['tipo'] ?? '') === 'admin'; }

function e_anunciante(): bool { return (usuario()['tipo'] ?? '') === 'anunciante'; }

function entrar(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    q('UPDATE usuarios SET ultimo_login = ? WHERE id = ?', [agora(), $id]);
}

function sair(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function exigir_login(): array
{
    $u = usuario();
    if (!$u) {
        $_SESSION['voltar_para'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('aviso', 'Entre na sua conta para continuar.');
        redirect('login.php');
    }
    return $u;
}

function exigir_tipo(string ...$tipos): array
{
    $u = exigir_login();
    if (!in_array($u['tipo'], $tipos, true)) {
        http_response_code(403);
        flash('erro', 'Você não tem permissão para acessar esta página.');
        redirect('index.php');
    }
    return $u;
}

function destino_pos_login(array $u): string
{
    $voltar = $_SESSION['voltar_para'] ?? '';
    unset($_SESSION['voltar_para']);
    // só aceita caminhos internos
    $base = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
    if ($voltar && str_starts_with($voltar, '/') && !str_starts_with($voltar, '//')) {
        $rel = $base && str_starts_with($voltar, $base) ? substr($voltar, strlen($base)) : $voltar;
        return ltrim($rel, '/');
    }
    return match ($u['tipo']) {
        'admin'      => 'admin/index.php',
        'anunciante' => 'painel/index.php',
        default      => 'index.php',
    };
}

function mensagens_nao_lidas(int $uid): int
{
    return (int) val('SELECT COUNT(*) FROM mensagens m JOIN conversas c ON c.id = m.conversa_id
                      WHERE m.lida = 0 AND m.remetente_id <> ? AND (c.interessado_id = ? OR c.anunciante_id = ?)',
                     [$uid, $uid, $uid]);
}

// Proteção simples contra tentativa de senha em massa (por sessão + IP)
function login_bloqueado(): bool
{
    $chave = 'login_falhas_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $f = $_SESSION[$chave] ?? ['n' => 0, 't' => 0];
    if ($f['n'] >= 5 && time() - $f['t'] < 600) return true;
    if (time() - $f['t'] >= 600) $_SESSION[$chave] = ['n' => 0, 't' => time()];
    return false;
}

function registrar_falha_login(): void
{
    $chave = 'login_falhas_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $f = $_SESSION[$chave] ?? ['n' => 0, 't' => time()];
    $_SESSION[$chave] = ['n' => $f['n'] + 1, 't' => time()];
}
