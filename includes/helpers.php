<?php
declare(strict_types=1);

// ---------------- Saída / URLs ----------------
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function upload_url(?string $arquivo, string $padrao = 'assets/img/sem-foto.svg'): string
{
    if (!$arquivo) {
        return url($padrao);
    }
    return url('uploads/' . $arquivo);
}

function redirect(string $path): never
{
    $dest = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $dest);
    exit;
}

function agora(): string { return date('Y-m-d H:i:s'); }
function hoje(): string  { return date('Y-m-d'); }

// ---------------- Entrada ----------------
function post(string $k, $padrao = '')
{
    $v = $_POST[$k] ?? $padrao;
    return is_string($v) ? trim($v) : $v;
}

function get(string $k, $padrao = '')
{
    $v = $_GET[$k] ?? $padrao;
    return is_string($v) ? trim($v) : $v;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function num_or_null($v): ?float
{
    $v = str_replace(['R$', ' '], '', (string) $v);
    if ($v === '') return null;
    // aceita 1.234,56 e 1234.56
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
        $v = str_replace('.', '', $v); // "350.000" = trezentos e cinquenta mil
    }
    return is_numeric($v) ? (float) $v : null;
}

// ---------------- Flash ----------------
function flash(string $tipo, string $msg): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------- CSRF ----------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes((string) ini_get('post_max_size'))) {
        flash('erro', 'O envio ficou grande demais para o servidor (limite de ' . (int) floor(ini_bytes((string) ini_get('post_max_size')) / 1048576)
            . ' MB por vez). Envie menos fotos de cada vez.');
        redirect(ltrim(parse_url($_SERVER['REQUEST_URI'] ?? 'index.php', PHP_URL_PATH) ?: 'index.php', '/'));
    }
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Sessão expirada. Volte, recarregue a página e tente novamente.');
    }
}

// ---------------- Formatação ----------------
function dinheiro($v): string
{
    return "R$\u{00A0}" . number_format((float) $v, 2, ',', '.'); // espaço que não quebra linha
}

function data_br(?string $d, bool $hora = false): string
{
    if (!$d) return '-';
    $t = strtotime($d);
    return $hora ? date('d/m/Y H:i', $t) : date('d/m/Y', $t);
}

function finalidades(): array
{
    return ['venda' => 'Venda', 'aluguel' => 'Aluguel', 'temporada' => 'Temporada'];
}

function tipos_imovel(): array
{
    return [
        'casa' => 'Casa', 'apartamento' => 'Apartamento', 'terreno' => 'Terreno',
        'comercial' => 'Comercial', 'rural' => 'Rural', 'outro' => 'Outro',
    ];
}

/** Comodidades que o anunciante pode marcar e o cliente pode filtrar: chave => [rótulo, ícone] */
function comodidades(): array
{
    return [
        'aceita_pets'     => ['Aceita pets', 'pets'],
        'piscina'         => ['Piscina', 'piscina'],
        'churrasqueira'   => ['Churrasqueira', 'churrasqueira'],
        'varanda'         => ['Varanda', 'varanda'],
        'quintal'         => ['Quintal', 'quintal'],
        'academia'        => ['Academia', 'academia'],
        'salao_festas'    => ['Salão de festas', 'festa'],
        'playground'      => ['Playground', 'playground'],
        'elevador'        => ['Elevador', 'elevador'],
        'portaria_24h'    => ['Portaria 24h', 'portaria'],
        'mobiliado'       => ['Mobiliado', 'sofa'],
        'ar_condicionado' => ['Ar-condicionado', 'ar'],
    ];
}

function comodidades_do_imovel(int $imovelId): array
{
    return array_column(all('SELECT comodidade FROM imovel_comodidades WHERE imovel_id = ?', [$imovelId]), 'comodidade');
}

/** Ícones de linha (SVG) usados na busca e nas comodidades. */
function icone(string $nome, int $tam = 22): string
{
    $p = [
        'busca'         => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'local'         => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'casa'          => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'predio'        => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M11 21v-3h2v3"/>',
        'cama'          => '<path d="M3 18V6M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="2"/>',
        'filtros'       => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'carro'         => '<path d="M3 13l2-5a2 2 0 0 1 1.9-1.4h10.2A2 2 0 0 1 19 8l2 5v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M3 13h18"/><circle cx="7.5" cy="15.5" r="1"/><circle cx="16.5" cy="15.5" r="1"/>',
        'terreno'       => '<path d="M3 20h18M5 20l4-9 3 5 2-3 5 7"/><circle cx="17" cy="6" r="2"/>',
        'loja'          => '<path d="M4 9h16l-1-5H5zM4 9v11h16V9M4 9c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3"/><path d="M10 20v-5h4v5"/>',
        'chave'         => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l2 2M15 8l2 2"/>',
        'dinheiro'      => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9v.01M18 15v.01"/>',
        'email'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'cadeado'       => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'olho'          => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'olho-fechado'  => '<path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.6 7 10 7a9.9 9.9 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'usuario'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'telefone'      => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'documento'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2M14 10h4M14 13h4"/>',
        'cracha'        => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 3v3h6V3"/><circle cx="12" cy="11" r="2.5"/><path d="M8 17c.7-1.5 2.2-2.3 4-2.3s3.3.8 4 2.3"/>',
        'verificado'    => '<path d="M12 2l2.4 2.1 3.2-.3.8 3.1 2.8 1.6-1.2 3 1.2 3-2.8 1.6-.8 3.1-3.2-.3L12 22l-2.4-2.1-3.2.3-.8-3.1-2.8-1.6 1.2-3-1.2-3 2.8-1.6.8-3.1 3.2.3z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'whatsapp'      => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.4z"/><path d="M9 8.5c0 3.6 2.9 6.5 6.5 6.5l1.2-1.5-2.1-1-1 .8a4.6 4.6 0 0 1-2.4-2.4l.8-1-1-2.1z"/>',
        'banho'         => '<path d="M4 12h16v3a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M6 12V5a2 2 0 0 1 3.5-1.3M8 20l-1 2M16 20l1 2"/>',
        'area'          => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/><path d="M9 9h6v6H9z"/>',
        'seta'          => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'pets'          => '<circle cx="5.5" cy="10" r="1.8"/><circle cx="9" cy="5.5" r="1.8"/><circle cx="15" cy="5.5" r="1.8"/><circle cx="18.5" cy="10" r="1.8"/><path d="M8 15.5c0-2.2 1.8-4 4-4s4 1.8 4 4c0 1.4 1.2 2.1 1.2 3.3a2.3 2.3 0 0 1-2.4 2.2c-1 0-1.8-.5-2.8-.5s-1.8.5-2.8.5a2.3 2.3 0 0 1-2.4-2.2c0-1.2 1.2-1.9 1.2-3.3z"/>',
        'piscina'       => '<path d="M2 17c2 0 2-1.3 4-1.3s2 1.3 4 1.3 2-1.3 4-1.3 2 1.3 4 1.3 2-1.3 4-1.3M2 21c2 0 2-1.3 4-1.3s2 1.3 4 1.3 2-1.3 4-1.3 2 1.3 4 1.3 2-1.3 4-1.3"/><path d="M8 14V5a2 2 0 0 1 4 0M15 14V5a2 2 0 0 1 4 0M8 8h7M8 11.5h7"/>',
        'churrasqueira' => '<path d="M4 10h16a8 8 0 0 1-16 0z"/><path d="m8 17-2 4M16 17l2 4M12 18v3M9 6.5c0-1 1-1.5 1-2.5M13 6.5c0-1 1-1.5 1-2.5"/>',
        'varanda'       => '<path d="M7 12V6a5 5 0 0 1 10 0v6"/><path d="M3 12h18M3 20h18M5 12v8M9 12v8M12 12v8M15 12v8M19 12v8"/>',
        'quintal'       => '<path d="M12 21v-5"/><path d="M12 16a5.5 5.5 0 0 1-5.5-5.5C6.5 7 9 3 12 3s5.5 4 5.5 7.5A5.5 5.5 0 0 1 12 16z"/><path d="M5 21h14"/>',
        'academia'      => '<path d="M6.5 7v10M17.5 7v10M3.5 9.5v5M20.5 9.5v5M6.5 12h11"/>',
        'festa'         => '<path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16.5c1.4 0 1.4 1 2.8 1s1.4-1 2.8-1 1.4 1 2.8 1 1.4-1 2.8-1 1.4 1 2.8 1M12 12V8.5M12 5.5v.01"/>',
        'playground'    => '<path d="M4 21V8l4-4 4 4v13M12 11l8 8v2M4 14h8M4 18h8"/>',
        'elevador'      => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="m9 10 3-3 3 3M9 14l3 3 3-3"/>',
        'portaria'      => '<path d="M12 3l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'sofa'          => '<path d="M5 11V8a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v5H3zM6 18v2M18 18v2"/>',
        'ar'            => '<path d="M12 2v20M4.2 6.5l15.6 11M19.8 6.5l-15.6 11M9 4l3 2 3-2M9 20l3-2 3 2"/>',
    ][$nome] ?? '';
    return '<svg class="icone" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function estados(): array
{
    return ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR',
            'PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
}

function status_assinatura_label(string $s): string
{
    return ['inativa' => 'Inativa', 'pendente' => 'Aguardando pagamento',
            'ativa' => 'Ativa', 'cancelada' => 'Cancelada'][$s] ?? $s;
}

/** Link do WhatsApp (wa.me) com mensagem pronta. Retorna null se o número não for válido. */
function link_whatsapp(?string $numero, string $mensagem = ''): ?string
{
    $d = preg_replace('/\D/', '', (string) $numero);
    if (strlen($d) === 10 || strlen($d) === 11) $d = '55' . $d;      // DDD + número
    if (!preg_match('/^55\d{10,11}$/', $d)) return null;
    return 'https://wa.me/' . $d . ($mensagem !== '' ? '?text=' . rawurlencode($mensagem) : '');
}

function estrelas(float $nota): string
{
    $cheias = (int) round($nota);
    return str_repeat('★', $cheias) . str_repeat('☆', 5 - $cheias);
}

// ---------------- Regras de visibilidade ----------------
/**
 * Condição SQL para o anunciante estar em dia (alias da tabela usuarios = $u).
 * 'ativa' sem data = liberado manualmente pelo admin.
 * 'cancelada' continua visível até o fim do período já pago.
 */
function sql_anunciante_em_dia(string $u = 'u'): string
{
    $h = hoje();
    return "($u.status = 'ativo' AND $u.tipo = 'anunciante' AND (
                ($u.assinatura_status = 'ativa' AND ($u.assinatura_ate IS NULL OR $u.assinatura_ate >= '$h'))
             OR ($u.assinatura_status = 'cancelada' AND $u.assinatura_ate >= '$h')))";
}

function sql_imovel_visivel(string $i = 'i', string $u = 'u'): string
{
    return "($i.status = 'ativo' AND " . sql_anunciante_em_dia($u) . ")";
}

function anunciante_em_dia(array $u): bool
{
    if ($u['tipo'] !== 'anunciante' || $u['status'] !== 'ativo') return false;
    $ate = $u['assinatura_ate'];
    if ($u['assinatura_status'] === 'ativa') return $ate === null || $ate >= hoje();
    if ($u['assinatura_status'] === 'cancelada') return $ate !== null && $ate >= hoje();
    return false;
}

function capa_imovel(int $imovelId): ?string
{
    return val('SELECT arquivo FROM imovel_fotos WHERE imovel_id = ? ORDER BY ordem, id LIMIT 1', [$imovelId]);
}

function media_avaliacoes(int $anuncianteId): array
{
    $r = one("SELECT COUNT(*) AS total, AVG(nota) AS media FROM avaliacoes
              WHERE anunciante_id = ? AND status = 'visivel'", [$anuncianteId]);
    return ['total' => (int) $r['total'], 'media' => round((float) $r['media'], 1)];
}

// ---------------- Paginação ----------------
function paginar(int $total, int $porPagina = 12): array
{
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($paginas, max(1, (int) get('pagina', 1)));
    return ['pagina' => $pagina, 'paginas' => $paginas, 'offset' => ($pagina - 1) * $porPagina, 'limite' => $porPagina];
}

function links_paginacao(array $p): string
{
    if ($p['paginas'] <= 1) return '';
    $params = $_GET;
    $html = '<nav class="paginacao">';
    for ($i = 1; $i <= $p['paginas']; $i++) {
        $params['pagina'] = $i;
        $cls = $i === $p['pagina'] ? ' class="atual"' : '';
        $html .= '<a' . $cls . ' href="?' . e(http_build_query($params)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

// ---------------- Upload de imagens ----------------
/** Converte valores do php.ini como "128M" ou "1G" em bytes. */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (float) $v;
    return (int) match (strtoupper(substr($v, -1))) { 'G' => $n * 1073741824, 'M' => $n * 1048576, 'K' => $n * 1024, default => $n };
}

/** Limite de tamanho por foto, em MB: pelo menos 25 MB (ou o do config.php, se for maior). */
function limite_foto_mb(): int
{
    return max(25, defined('UPLOAD_MAX_MB') ? (int) UPLOAD_MAX_MB : 0);
}

/** Limite que vale de fato: o menor entre o do site e o do servidor PHP (upload_max_filesize). */
function limite_foto_efetivo_mb(): int
{
    $servidor = (int) floor(ini_bytes((string) ini_get('upload_max_filesize')) / 1048576);
    return max(1, min(limite_foto_mb(), $servidor ?: limite_foto_mb()));
}

/**
 * Valida e re-salva a imagem (redimensiona, corrige a rotação de fotos de celular e converte
 * para JPEG), removendo qualquer conteúdo malicioso embutido. Retorna o caminho dentro de /uploads.
 */
function salvar_imagem(array $file, string $pasta, int $maxLado = 1600): string
{
    $erro = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Foto maior que o servidor aceita (' . limite_foto_efetivo_mb() . ' MB).');
    }
    if ($erro !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio da imagem.');
    }
    if ($file['size'] > limite_foto_mb() * 1024 * 1024) {
        throw new RuntimeException('Imagem maior que ' . limite_foto_mb() . ' MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $tiposOk = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
    if (!$info || !in_array($info[2], $tiposOk, true)) {
        throw new RuntimeException('Formato inválido. Envie JPG, PNG, WEBP ou GIF.');
    }
    [$w, $h] = [$info[0], $info[1]];
    if ($w * $h > 80_000_000) { // 80 megapixels: protege a memória do servidor
        throw new RuntimeException('Imagem com resolução grande demais (acima de 80 megapixels).');
    }
    // fotos grandes precisam de mais memória para serem abertas
    $necessario = (int) ($w * $h * 5 * 1.8) + 64 * 1048576;
    if (ini_bytes((string) ini_get('memory_limit')) < $necessario) {
        @ini_set('memory_limit', (string) max(512, (int) ceil($necessario / 1048576)) . 'M');
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }
    // rotação gravada pela câmera do celular (EXIF)
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro && ($rot = imagerotate($src, $giro, 0))) {
            imagedestroy($src);
            $src = $rot;
            [$w, $h] = [imagesx($src), imagesy($src)];
        }
    }
    $escala = min(1, $maxLado / max($w, $h));
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $sub = $pasta . '/' . date('Y/m');
    $dir = UPLOAD_PATH . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta de uploads.');
    }
    $nome = bin2hex(random_bytes(12)) . '.jpg';
    imageinterlace($dst, true); // JPEG progressivo: aparece mais rápido no celular
    imagejpeg($dst, $dir . '/' . $nome, 84);
    imagedestroy($dst);
    return $sub . '/' . $nome;
}

function apagar_upload(?string $rel): void
{
    if (!$rel || str_contains($rel, '..')) return;
    $f = UPLOAD_PATH . '/' . $rel;
    if (is_file($f)) @unlink($f);
}

/** Converte $_FILES['campo'][] (múltiplo) em lista de arquivos. */
function arquivos_multiplos(string $campo): array
{
    if (empty($_FILES[$campo]['name']) || !is_array($_FILES[$campo]['name'])) return [];
    $lista = [];
    foreach ($_FILES[$campo]['name'] as $i => $n) {
        if (($_FILES[$campo]['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $lista[] = [
            'name' => $n, 'tmp_name' => $_FILES[$campo]['tmp_name'][$i],
            'error' => $_FILES[$campo]['error'][$i], 'size' => $_FILES[$campo]['size'][$i],
        ];
    }
    return $lista;
}

function json_resposta($dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}
