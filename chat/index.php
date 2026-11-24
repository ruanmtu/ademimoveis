<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_login();
$uid = (int) $u['id'];

$conversas = all('SELECT c.*, i.titulo AS imovel,
                    CASE WHEN c.interessado_id = ? THEN a.nome ELSE it.nome END AS outro_nome,
                    CASE WHEN c.interessado_id = ? THEN a.foto ELSE it.foto END AS outro_foto,
                    (SELECT texto FROM mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima,
                    (SELECT COUNT(*) FROM mensagens m WHERE m.conversa_id = c.id AND m.lida = 0 AND m.remetente_id <> ?) AS nao_lidas
                  FROM conversas c
                  JOIN usuarios a ON a.id = c.anunciante_id
                  JOIN usuarios it ON it.id = c.interessado_id
                  LEFT JOIN imoveis i ON i.id = c.imovel_id
                  WHERE c.interessado_id = ? OR c.anunciante_id = ?
                  ORDER BY c.atualizado_em DESC', [$uid, $uid, $uid, $uid, $uid]);

$titulo = 'Mensagens';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Mensagens</h1>
  <?php if ($u['tipo'] === 'anunciante') require __DIR__ . '/../painel/_nav.php'; ?>
  <?php if ($conversas): ?>
    <ul class="lista-conversas">
      <?php foreach ($conversas as $c): ?>
        <li class="<?= $c['nao_lidas'] ? 'nao-lida' : '' ?>">
          <a href="<?= url('chat/conversa.php?id=' . (int) $c['id']) ?>">
            <img class="avatar" src="<?= e(upload_url($c['outro_foto'], 'assets/img/avatar.svg')) ?>" alt="">
            <div>
              <strong><?= e($c['outro_nome']) ?></strong>
              <?php if ($c['imovel']): ?><small> · <?= e($c['imovel']) ?></small><?php endif; ?>
              <p><?= e(mb_strimwidth((string) ($c['ultima'] ?? 'Conversa iniciada'), 0, 120, '…')) ?></p>
            </div>
            <span class="conversa-meta"><small><?= data_br($c['atualizado_em'], true) ?></small>
              <?php if ($c['nao_lidas']): ?><span class="badge"><?= (int) $c['nao_lidas'] ?></span><?php endif; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="vazio">Você ainda não tem conversas. Encontre um imóvel e fale com o anunciante!</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
