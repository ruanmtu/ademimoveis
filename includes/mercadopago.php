<?php
declare(strict_types=1);

/**
 * Integração com Mercado Pago - Assinaturas (preapproval sem plano associado).
 * Documentação: https://www.mercadopago.com.br/developers/pt/docs/subscriptions
 *
 * external_reference = "usuario:ID:plano:ID" ou "usuario:ID:plano:ID:cupom:ID"
 */

function mp_configurado(): bool
{
    return MP_ACCESS_TOKEN !== '';
}

function mp_request(string $metodo, string $caminho, ?array $corpo = null): array
{
    $ch = curl_init('https://api.mercadopago.com' . $caminho);
    $headers = [
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
        'Content-Type: application/json',
        'X-Idempotency-Key: ' . bin2hex(random_bytes(16)),
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Falha de comunicação com o Mercado Pago: ' . $erro);
    }
    $dados = json_decode((string) $resp, true) ?? [];
    if ($code >= 400) {
        $msg = $dados['message'] ?? ('HTTP ' . $code);
        throw new RuntimeException('Mercado Pago recusou a solicitação: ' . $msg);
    }
    return $dados;
}

/** Cria a assinatura recorrente e devolve os dados (init_point = link de pagamento). */
function mp_criar_assinatura(array $usuario, array $plano, float $valor, ?array $cupom = null): array
{
    $motivo = APP_NAME . ' - Plano ' . $plano['nome'];
    if ($cupom) $motivo .= ' (cupom ' . $cupom['codigo'] . ')';
    $ref = 'usuario:' . $usuario['id'] . ':plano:' . $plano['id'] . ($cupom ? ':cupom:' . $cupom['id'] : '');
    return mp_request('POST', '/preapproval', [
        'reason'             => mb_substr($motivo, 0, 250),
        'external_reference' => $ref,
        'payer_email'        => $usuario['email'],
        'auto_recurring'     => [
            'frequency'          => max(1, (int) $plano['periodicidade_meses']),
            'frequency_type'     => 'months',
            'transaction_amount' => round($valor, 2),
            'currency_id'        => 'BRL',
        ],
        'back_url' => url('painel/assinatura.php?retorno=1'),
        'status'   => 'pending',
    ]);
}

function mp_buscar_assinatura(string $preapprovalId): array
{
    return mp_request('GET', '/preapproval/' . rawurlencode($preapprovalId));
}

function mp_cancelar_assinatura(string $preapprovalId): array
{
    return mp_request('PUT', '/preapproval/' . rawurlencode($preapprovalId), ['status' => 'cancelled']);
}

/** Muda o valor das próximas cobranças (usado quando o desconto do cupom acaba ou o admin troca o plano). */
function mp_atualizar_valor(string $preapprovalId, float $valor): array
{
    return mp_request('PUT', '/preapproval/' . rawurlencode($preapprovalId), [
        'auto_recurring' => ['transaction_amount' => round($valor, 2), 'currency_id' => 'BRL'],
    ]);
}

function mp_ler_referencia(?string $ref): ?array
{
    if (!preg_match('/^usuario:(\d+):plano:(\d+)(?::cupom:(\d+))?$/', (string) $ref, $m)) return null;
    return ['usuario' => (int) $m[1], 'plano' => (int) $m[2], 'cupom' => isset($m[3]) ? (int) $m[3] : null];
}

/**
 * Atualiza o usuário a partir dos dados da assinatura consultados NA API
 * (nunca confia apenas no conteúdo da notificação).
 */
function mp_sincronizar_assinatura(array $pre): void
{
    $ref = mp_ler_referencia($pre['external_reference'] ?? null);
    if (!$ref) return;
    $uid = $ref['usuario'];
    $u = one('SELECT * FROM usuarios WHERE id = ?', [$uid]);
    if (!$u) return;
    $preId = (string) ($pre['id'] ?? '');
    $status = $pre['status'] ?? '';
    $ehNova = $u['mp_preapproval_novo'] && $u['mp_preapproval_novo'] === $preId;   // troca de plano
    $ehAtual = $u['mp_preapproval_id'] === $preId;

    if ($status === 'authorized') {
        $tol = (int) ASSINATURA_TOLERANCIA_DIAS;
        $prox = !empty($pre['next_payment_date']) ? strtotime($pre['next_payment_date']) : strtotime('+1 month');
        $ate = date('Y-m-d', strtotime("+$tol days", $prox));
        if ($u['assinatura_ate'] && $u['assinatura_ate'] > $ate) $ate = $u['assinatura_ate'];

        if ($ehNova && $u['mp_preapproval_id'] && $u['mp_preapproval_id'] !== $preId) {
            // nova assinatura confirmada: cancela a antiga para não cobrar duas vezes
            try { mp_cancelar_assinatura($u['mp_preapproval_id']); } catch (RuntimeException $ex) { error_log('[ADemoveis] ' . $ex->getMessage()); }
        }

        $trocou = $ehNova || !$ehAtual || $u['assinatura_status'] !== 'ativa' || (int) $u['plano_id'] !== $ref['plano'];
        q("UPDATE usuarios SET assinatura_status = 'ativa', assinatura_ate = ?, plano_id = ?, mp_preapproval_id = ?,
           mp_preapproval_novo = CASE WHEN mp_preapproval_novo = ? THEN NULL ELSE mp_preapproval_novo END WHERE id = ?",
          [$ate, $ref['plano'], $preId, $preId, $uid]);

        // cupom: registra o uso uma única vez (registrar_uso_cupom ignora repetições)
        $cupom = $ref['cupom'] ? one('SELECT * FROM cupons WHERE id = ?', [$ref['cupom']]) : null;
        $plano = one('SELECT * FROM planos WHERE id = ?', [$ref['plano']]);
        if ($cupom && $plano) {
            if (registrar_uso_cupom($cupom, $uid, $plano, (float) ($pre['auto_recurring']['transaction_amount'] ?? 0))) {
                q('UPDATE usuarios SET cupom_id = ?, cupom_cobrancas_restantes = ? WHERE id = ?', [$cupom['id'], cobrancas_do_cupom($cupom), $uid]);
            }
        } elseif ($trocou) {
            q('UPDATE usuarios SET cupom_id = NULL, cupom_cobrancas_restantes = NULL WHERE id = ?', [$uid]);
        }
    } elseif (in_array($status, ['cancelled', 'paused'], true)) {
        if ($ehAtual) {
            // mantém visível até o fim do período já pago
            q("UPDATE usuarios SET assinatura_status = 'cancelada' WHERE id = ?", [$uid]);
        } elseif ($ehNova) {
            q('UPDATE usuarios SET mp_preapproval_novo = NULL WHERE id = ?', [$uid]);
        }
    } elseif ($status === 'pending' && $ehAtual) {
        q("UPDATE usuarios SET assinatura_status = 'pendente' WHERE id = ? AND assinatura_status NOT IN ('ativa')", [$uid]);
    }

    registrar_pagamento($uid, $preId, 'assinatura', $status, (float) ($pre['auto_recurring']['transaction_amount'] ?? 0), $pre);
}

/**
 * Chamado a cada cobrança aprovada: desconta uma cobrança do cupom e, quando o desconto
 * acaba, volta o valor da assinatura para o preço cheio do plano.
 */
function mp_processar_cobranca_aprovada(int $uid, string $preapprovalId): void
{
    $u = one('SELECT * FROM usuarios WHERE id = ?', [$uid]);
    if (!$u || !$u['cupom_id'] || $u['cupom_cobrancas_restantes'] === null || $u['mp_preapproval_id'] !== $preapprovalId) return;
    $rest = max(0, (int) $u['cupom_cobrancas_restantes'] - 1);
    q('UPDATE usuarios SET cupom_cobrancas_restantes = ? WHERE id = ?', [$rest, $uid]);
    if ($rest === 0) {
        $plano = plano_do_usuario($u);
        mp_atualizar_valor($preapprovalId, (float) $plano['preco']);
        q('UPDATE usuarios SET cupom_id = NULL, cupom_cobrancas_restantes = NULL WHERE id = ?', [$uid]);
        registrar_pagamento($uid, $preapprovalId, 'fim_desconto_cupom', 'valor_atualizado', (float) $plano['preco'], ['plano' => $plano['nome']]);
    }
}

function registrar_pagamento(?int $uid, string $mpId, string $tipo, ?string $status, ?float $valor, $payload): void
{
    q('INSERT INTO pagamentos (usuario_id, mp_id, tipo, status, valor, payload, criado_em) VALUES (?,?,?,?,?,?,?)',
      [$uid, $mpId, $tipo, $status, $valor, json_encode($payload, JSON_UNESCAPED_UNICODE), agora()]);
}

/** Valida a assinatura x-signature enviada pelo Mercado Pago (se MP_WEBHOOK_SECRET estiver configurado). */
function mp_webhook_valido(string $dataId): bool
{
    if (MP_WEBHOOK_SECRET === '') return true;
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $reqId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $ts = $v1 = '';
    foreach (explode(',', $sig) as $parte) {
        [$k, $v] = array_map('trim', explode('=', $parte, 2) + [1 => '']);
        if ($k === 'ts') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    if ($ts === '' || $v1 === '') return false;
    $manifesto = 'id:' . mb_strtolower($dataId) . ';';
    if ($reqId !== '') $manifesto .= 'request-id:' . $reqId . ';';
    $manifesto .= 'ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifesto, MP_WEBHOOK_SECRET), $v1);
}
