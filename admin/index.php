<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

$n = [
    'clientes'     => (int) val("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'"),
    'anunciantes'  => (int) val("SELECT COUNT(*) FROM usuarios WHERE tipo = 'anunciante'"),
    'em_dia'       => (int) val('SELECT COUNT(*) FROM usuarios u WHERE ' . sql_anunciante_em_dia()),
    'pendentes'    => (int) val("SELECT COUNT(*) FROM usuarios WHERE tipo = 'anunciante' AND assinatura_status = 'pendente'"),
    'imoveis'      => (int) val('SELECT COUNT(*) FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id WHERE ' . sql_imovel_visivel()),
    'imoveis_tot'  => (int) val("SELECT COUNT(*) FROM imoveis WHERE status <> 'removido'"),
    'conversas'    => (int) val('SELECT COUNT(*) FROM conversas'),
    'mensagens_7d' => (int) val('SELECT COUNT(*) FROM mensagens WHERE criado_em >= ?', [date('Y-m-d H:i:s', strtotime('-7 days'))]),
    'receita_mes'  => (float) val('SELECT COALESCE(SUM(p.preco), 0) FROM usuarios u JOIN planos p ON p.id = u.plano_id
                                   WHERE ' . sql_anunciante_em_dia() . " AND u.mp_preapproval_id IS NOT NULL AND u.assinatura_status = 'ativa'"),
];
$novos = all('SELECT id, nome, email, tipo, criado_em FROM usuarios ORDER BY id DESC LIMIT 8');
$vencendo = all("SELECT id, nome, email, assinatura_ate FROM usuarios WHERE tipo = 'anunciante' AND assinatura_ate BETWEEN ? AND ?
                 ORDER BY assinatura_ate LIMIT 10", [hoje(), date('Y-m-d', strtotime('+7 days'))]);

$titulo = 'Administração';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Administração</h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <?php if (is_file(ROOT_PATH . '/instalar.php')): ?>
    <div class="alerta alerta-erro">Segurança: apague o arquivo <strong>instalar.php</strong> do servidor.</div>
  <?php endif; ?>
  <?php if (MP_ACCESS_TOKEN === ''): ?>
    <div class="alerta alerta-aviso">Mercado Pago não configurado. Anunciantes só podem ser ativados manualmente em <a href="<?= url('admin/usuarios.php?tipo=anunciante') ?>">Usuários</a>.</div>
  <?php endif; ?>

  <div class="cards-resumo">
    <div class="caixa"><span>Clientes</span><strong><?= $n['clientes'] ?></strong></div>
    <div class="caixa"><span>Anunciantes</span><strong><?= $n['anunciantes'] ?></strong><small><?= $n['em_dia'] ?> em dia · <?= $n['pendentes'] ?> aguardando pagamento</small></div>
    <div class="caixa"><span>Imóveis no ar</span><strong><?= $n['imoveis'] ?></strong><small>de <?= $n['imoveis_tot'] ?> cadastrados</small></div>
    <div class="caixa"><span>Receita recorrente</span><strong><?= dinheiro($n['receita_mes']) ?></strong><small>assinaturas ativas via Mercado Pago</small></div>
    <div class="caixa"><span>Conversas</span><strong><?= $n['conversas'] ?></strong><small><?= $n['mensagens_7d'] ?> mensagens nos últimos 7 dias</small></div>
  </div>

  <div class="duas-colunas mt">
    <div class="caixa">
      <h2>Novos cadastros</h2>
      <table class="tabela"><tbody>
        <?php foreach ($novos as $x): ?>
          <tr><td><a href="<?= url('admin/usuarios.php?id=' . (int) $x['id']) ?>"><?= e($x['nome']) ?></a><br><small><?= e($x['email']) ?></small></td>
              <td><span class="status status-<?= e($x['tipo']) ?>"><?= e($x['tipo']) ?></span></td><td><small><?= data_br($x['criado_em']) ?></small></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </div>
    <div class="caixa">
      <h2>Assinaturas vencendo em 7 dias</h2>
      <?php if ($vencendo): ?>
        <table class="tabela"><tbody>
          <?php foreach ($vencendo as $x): ?>
            <tr><td><a href="<?= url('admin/usuarios.php?id=' . (int) $x['id']) ?>"><?= e($x['nome']) ?></a><br><small><?= e($x['email']) ?></small></td><td><?= data_br($x['assinatura_ate']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php else: ?><p class="vazio">Nenhuma.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
