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
            // senha trocada em outro lugar: esta sessão deixa de valer
            if (!isset($_SESSION['sv'])) $_SESSION['sv'] = versao_sessao($u);
            if (hash_equals((string) $_SESSION['sv'], versao_sessao($u))) {
                $cache = $u;
                $_SESSION['admin'] = $u['tipo'] === 'admin';
            } else {
                unset($_SESSION['uid'], $_SESSION['sv']);
            }
        } else {
            unset($_SESSION['uid'], $_SESSION['sv']);
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
    $_SESSION['ultimo_uso'] = time();
    $u = one('SELECT id, tipo, senha_hash FROM usuarios WHERE id = ?', [$id]);
    $_SESSION['sv'] = $u ? versao_sessao($u) : '';
    $_SESSION['admin'] = ($u['tipo'] ?? '') === 'admin';
    unset($_SESSION['csrf']); // novo token de formulário a cada login
    q('UPDATE usuarios SET ultimo_login = ? WHERE id = ?', [agora(), $id]);
    registrar_evento('login_ok', '', $id);
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
    pagina_privada();
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

/** Atualiza a sessão atual depois que o próprio usuário troca a senha (as outras sessões caem). */
function renovar_sessao_apos_senha(int $id): void
{
    session_regenerate_id(true);
    $u = one('SELECT id, senha_hash FROM usuarios WHERE id = ?', [$id]);
    if ($u) $_SESSION['sv'] = versao_sessao($u);
}

// Limite de tentativas de login (gravado no banco: não adianta apagar cookies ou trocar de navegador)
const LOGIN_MAX_POR_EMAIL = 5;   // erros seguidos na mesma conta
const LOGIN_MAX_POR_IP = 20;     // erros do mesmo endereço de internet
const LOGIN_JANELA = 900;        // 15 minutos

function login_bloqueado(string $email): bool
{
    return limite_excedido('login_falha', 'email:' . $email, LOGIN_MAX_POR_EMAIL, LOGIN_JANELA)
        || limite_excedido('login_falha_ip', 'ip:' . ip_cliente(), LOGIN_MAX_POR_IP, LOGIN_JANELA);
}

function registrar_falha_login(string $email, ?int $uid = null): void
{
    registrar_evento('login_falha', 'email:' . $email, $uid, 'E-mail: ' . $email);
    registrar_evento('login_falha_ip', 'ip:' . ip_cliente(), $uid);
}

function limpar_falhas_login(string $email): void
{
    zerar_eventos('login_falha', 'email:' . $email);
}
