<?php
/**
 * Diagnóstico da integração com o Mercado Pago (somente leitura).
 * Mostra se as chaves estão configuradas e consulta /users/me para validar o Access Token.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mercadopago.php';
exigir_tipo('admin');

$token = (string) MP_ACCESS_TOKEN;
$segredo = (string) MP_WEBHOOK_SECRET;
$conta = null;
$erroConta = '';
if ($token !== '' && get('testar')) {
    try {
        $conta = mp_request('GET', '/users/me');
    } catch (RuntimeException $ex) {
        $erroConta = $ex->getMessage();
    }
}
$ehTeste = $conta && (in_array('test_user', (array) ($conta['tags'] ?? []), true) || str_starts_with($token, 'TEST-')
            || str_starts_with((string) ($conta['nickname'] ?? ''), 'TESTUSER'));
$urlWebhook = url('pagamento/webhook.php');
$ultimos = all("SELECT tipo, status, criado_em FROM pagamentos WHERE tipo IN ('assinatura','mensalidade','assinatura_criada') ORDER BY id DESC LIMIT 5");

function linha_ok(bool $ok, string $sim, string $nao): string
{
    return '<span class="status ' . ($ok ? 'status-ativo' : 'status-removido') . '">' . e($ok ? $sim : $nao) . '</span>';
}

$titulo = 'Mercado Pago';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Mercado Pago</h1>
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="duas-colunas">
    <div class="caixa">
      <h2>Configuração</h2>
      <dl class="dados">
        <dt>Access Token</dt>
        <dd><?= linha_ok($token !== '', 'Configurado', 'Faltando') ?>
          <?php if ($token !== ''): ?><br><small class="dica"><?= e(substr($token, 0, 8)) ?>…<?= e(substr($token, -4)) ?></small><?php endif; ?></dd>
        <dt>Assinatura secreta</dt>
        <dd><?= linha_ok($segredo !== '', 'Configurada', 'Faltando') ?>
          <?php if ($segredo === ''): ?><br><small class="dica">Opcional, mas recomendada (Webhooks → Configurar notificações).</small><?php endif; ?></dd>
        <dt>Tolerância</dt>
        <dd><?= (int) ASSINATURA_TOLERANCIA_DIAS ?> dias após o vencimento</dd>
      </dl>
      <?php if ($token !== ''): ?>
        <a class="btn" href="?testar=1">Testar conexão com o Mercado Pago</a>
      <?php else: ?>
        <p class="dica">Preencha <code>MP_ACCESS_TOKEN</code> em <code>config/config.php</code> no servidor.</p>
      <?php endif; ?>
    </div>

    <div class="caixa">
      <h2>Resultado do teste</h2>
      <?php if ($conta): ?>
        <div class="alerta alerta-sucesso">Conexão funcionando. O token é válido.</div>
        <dl class="dados">
          <dt>Conta</dt><dd><?= e($conta['nickname'] ?? '-') ?></dd>
          <dt>ID</dt><dd><?= e((string) ($conta['id'] ?? '-')) ?></dd>
          <dt>País</dt><dd><?= e($conta['site_id'] ?? '-') ?></dd>
          <dt>Modo</dt><dd><?= $ehTeste ? '<span class="status status-pausado">Teste</span> nenhuma cobrança é real' : '<span class="status status-ativo">Produção</span> cobranças reais' ?></dd>
        </dl>
      <?php elseif ($erroConta): ?>
        <div class="alerta alerta-erro">O Mercado Pago recusou o token: <?= e($erroConta) ?>. Confira se copiou o Access Token inteiro, sem espaços.</div>
      <?php else: ?>
        <p class="dica">Clique em "Testar conexão" para conferir o token.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="caixa mt">
    <h2>Notificações (Webhooks)</h2>
    <p>Cadastre este endereço no Mercado Pago, em <strong>Webhooks → Configurar notificações</strong>, no modo de teste e no de produção:</p>
    <p><code class="codigo-cupom"><?= e($urlWebhook) ?></code></p>
    <p class="dica">Eventos em "Planos e assinaturas": Vinculação de uma assinatura e Pagamento recorrente de uma assinatura.</p>
    <h3 class="mt">Últimos eventos recebidos</h3>
    <?php if ($ultimos): ?>
      <table class="tabela"><tbody>
        <?php foreach ($ultimos as $x): ?><tr><td><?= e($x['tipo']) ?></td><td><?= e($x['status'] ?? '-') ?></td><td><small><?= data_br($x['criado_em'], true) ?></small></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?>
      <p class="vazio">Nenhum evento de assinatura ainda. Eles aparecem aqui depois do primeiro teste.</p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
