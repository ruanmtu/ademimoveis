<?php
/**
 * URL de notificações (Webhooks) do Mercado Pago.
 * Configure no painel do Mercado Pago: https://SEU-SITE/pagamento/webhook.php
 * Eventos: "Planos e assinaturas" (subscription_preapproval e subscription_authorized_payment).
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mercadopago.php';

$corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
$tipo = (string) ($corpo['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
$dataId = (string) ($corpo['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? '');

if ($dataId === '' || !preg_match('/^[A-Za-z0-9_-]{1,80}$/', $dataId)) {
    http_response_code(200); // responde 200 para o MP não ficar reenviando lixo
    exit('ignorado');
}
if (!mp_webhook_valido($dataId)) {
    http_response_code(401);
    exit('assinatura invalida');
}
if (!mp_configurado()) {
    http_response_code(503);
    exit('mercado pago nao configurado');
}

try {
    if ($tipo === 'subscription_preapproval' || $tipo === 'preapproval') {
        mp_sincronizar_assinatura(mp_buscar_assinatura($dataId));
    } elseif ($tipo === 'subscription_authorized_payment' || $tipo === 'authorized_payment') {
        // Cobrança recorrente: consulta a fatura, atualiza a validade e controla o desconto do cupom
        $fat = mp_request('GET', '/authorized_payments/' . rawurlencode($dataId));
        if (!empty($fat['preapproval_id'])) {
            $pre = mp_buscar_assinatura((string) $fat['preapproval_id']);
            $ref = mp_ler_referencia($pre['external_reference'] ?? null);
            $uid = $ref['usuario'] ?? null;
            $stPag = (string) ($fat['payment']['status'] ?? $fat['status'] ?? '');
            $jaProcessada = (bool) val("SELECT 1 FROM pagamentos WHERE mp_id = ? AND tipo = 'mensalidade' AND status = 'approved'", [$dataId]);
            registrar_pagamento($uid, $dataId, 'mensalidade', $stPag, (float) ($fat['transaction_amount'] ?? 0), $fat);
            mp_sincronizar_assinatura($pre);
            if ($uid && $stPag === 'approved' && !$jaProcessada) {
                mp_processar_cobranca_aprovada($uid, (string) $pre['id']);
            }
        }
    }
    http_response_code(200);
    echo 'ok';
} catch (Throwable $ex) {
    error_log('[Ademimoveis webhook] ' . $ex->getMessage());
    http_response_code(500); // o MP tenta novamente depois
    echo 'erro';
}
