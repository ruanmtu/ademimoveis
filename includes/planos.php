<?php
declare(strict_types=1);

// ======================= Planos =======================

function periodicidades(): array
{
    return [1 => 'Mensal', 3 => 'Trimestral', 6 => 'Semestral', 12 => 'Anual'];
}

function sufixo_periodo(int $meses): string
{
    return [1 => '/mês', 3 => '/trimestre', 6 => '/semestre', 12 => '/ano'][$meses] ?? "/$meses meses";
}

function recursos_plano(?string $txt): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\\\\n/', (string) $txt))));
}

/** Plano atual do anunciante (com valores padrão quando não houver plano). */
function plano_do_usuario(array $u): array
{
    $p = $u['plano_id'] ? one('SELECT * FROM planos WHERE id = ?', [$u['plano_id']]) : null;
    return $p ?? [
        'id' => null, 'nome' => 'Sem plano', 'preco' => 0, 'periodicidade_meses' => 1, 'limite_imoveis' => 0,
        'limite_fotos' => FOTOS_POR_IMOVEL, 'destaques' => 0, 'recursos' => '',
    ];
}

function limite_fotos_usuario(array $u): int
{
    $l = (int) plano_do_usuario($u)['limite_fotos'];
    return $l > 0 ? $l : FOTOS_POR_IMOVEL;
}

// ======================= Cupons =======================

function normalizar_codigo(string $c): string
{
    return mb_strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $c));
}

function duracao_cupom_texto(array $c, int $periodo = 1): string
{
    $cob = $periodo === 1 ? 'mensalidade' : 'cobrança';
    return match ($c['duracao']) {
        'primeira'  => "na 1ª $cob",
        'cobrancas' => 'nas ' . (int) $c['duracao_cobrancas'] . " primeiras {$cob}s",
        default     => 'em todas as ' . $cob . 's',
    };
}

function desconto_texto(array $c): string
{
    return $c['tipo'] === 'percentual'
        ? rtrim(rtrim(number_format((float) $c['valor'], 2, ',', '.'), '0'), ',') . '% de desconto'
        : dinheiro($c['valor']) . ' de desconto';
}

function preco_com_cupom(float $preco, array $c): float
{
    $final = $c['tipo'] === 'percentual'
        ? $preco * (1 - min(100, (float) $c['valor']) / 100)
        : $preco - (float) $c['valor'];
    return max(0, round($final, 2));
}

/** Quantas cobranças o desconto vale (null = todas). */
function cobrancas_do_cupom(array $c): ?int
{
    return match ($c['duracao']) {
        'primeira'  => 1,
        'cobrancas' => max(1, (int) $c['duracao_cobrancas']),
        default     => null,
    };
}

/**
 * Valida um cupom para um plano e usuário. Retorna o cupom ou lança RuntimeException
 * com a mensagem para o anunciante.
 */
function validar_cupom(string $codigo, array $plano, array $usuario): array
{
    $codigo = normalizar_codigo($codigo);
    $c = $codigo !== '' ? one('SELECT * FROM cupons WHERE codigo = ?', [$codigo]) : null;
    if (!$c || !$c['ativo']) throw new RuntimeException('Cupom inválido.');
    if ($c['valido_ate'] && $c['valido_ate'] < hoje()) throw new RuntimeException('Este cupom expirou.');
    if ($c['max_usos'] !== null && (int) $c['usos'] >= (int) $c['max_usos']) throw new RuntimeException('Este cupom já atingiu o limite de usos.');
    if ($c['plano_id'] && (int) $c['plano_id'] !== (int) $plano['id']) {
        $nome = val('SELECT nome FROM planos WHERE id = ?', [$c['plano_id']]);
        throw new RuntimeException('Este cupom vale só para o plano ' . ($nome ?: 'específico') . '.');
    }
    if (val('SELECT 1 FROM cupom_usos WHERE cupom_id = ? AND usuario_id = ?', [$c['id'], $usuario['id']])) {
        throw new RuntimeException('Você já usou este cupom.');
    }
    return $c;
}

/** Registra o uso do cupom (uma vez por usuário). Retorna false se já estava registrado. */
function registrar_uso_cupom(array $c, int $uid, array $plano, float $valorFinal): bool
{
    if (val('SELECT 1 FROM cupom_usos WHERE cupom_id = ? AND usuario_id = ?', [$c['id'], $uid])) return false;
    q('INSERT INTO cupom_usos (cupom_id, usuario_id, plano_id, valor_original, valor_com_desconto, criado_em) VALUES (?,?,?,?,?,?)',
      [$c['id'], $uid, $plano['id'], $plano['preco'], $valorFinal, agora()]);
    q('UPDATE cupons SET usos = usos + 1 WHERE id = ?', [$c['id']]);
    return true;
}

/**
 * Cupom de 100% (ou que zera o valor): libera o período grátis direto, sem Mercado Pago.
 */
function liberar_periodo_gratis(array $u, array $plano, array $c): void
{
    $n = cobrancas_do_cupom($c);
    $ate = $n === null ? null : date('Y-m-d', strtotime('+' . ($n * (int) $plano['periodicidade_meses']) . ' months'));
    q("UPDATE usuarios SET assinatura_status = 'ativa', assinatura_ate = ?, plano_id = ?, cupom_id = ?, cupom_cobrancas_restantes = NULL WHERE id = ?",
      [$ate, $plano['id'], $c['id'], $u['id']]);
    registrar_uso_cupom($c, (int) $u['id'], $plano, 0);
    q('INSERT INTO pagamentos (usuario_id, tipo, status, valor, payload, criado_em) VALUES (?,?,?,?,?,?)',
      [$u['id'], 'cupom_gratis', 'ativa', 0, json_encode(['cupom' => $c['codigo'], 'ate' => $ate]), agora()]);
}
