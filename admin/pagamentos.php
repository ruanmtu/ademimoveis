<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

$total = (int) val('SELECT COUNT(*) FROM pagamentos');
$pg = paginar($total, 40);
$lista = all("SELECT p.*, u.nome, u.email FROM pagamentos p LEFT JOIN usuarios u ON u.id = p.usuario_id
              ORDER BY p.id DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}");

$titulo = 'Pagamentos';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Pagamentos e eventos <small>(<?= $total ?>)</small></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <p class="dica">Histórico das notificações do Mercado Pago e das ativações manuais feitas pela administração.</p>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Data</th><th>Usuário</th><th>Evento</th><th>Situação</th><th>Valor</th><th>ID Mercado Pago</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $p): ?>
      <tr>
        <td><small><?= data_br($p['criado_em'], true) ?></small></td>
        <td><?= $p['usuario_id'] ? '<a href="' . url('admin/usuarios.php?id=' . (int) $p['usuario_id']) . '">' . e($p['nome']) . '</a><br><small>' . e($p['email']) . '</small>' : '-' ?></td>
        <td><?= e($p['tipo']) ?></td>
        <td><?= e($p['status'] ?? '-') ?></td>
        <td><?= $p['valor'] !== null ? dinheiro($p['valor']) : '-' ?></td>
        <td><small><?= e($p['mp_id'] ?? '-') ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= links_paginacao($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
