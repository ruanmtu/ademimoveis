<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_tipo('anunciante');

$emDia = anunciante_em_dia($u);
$tot = one("SELECT COUNT(*) AS total, SUM(status = 'ativo') AS ativos, SUM(visualizacoes) AS views
            FROM imoveis WHERE anunciante_id = ? AND status <> 'removido'", [$u['id']]);
$naoLidas = mensagens_nao_lidas((int) $u['id']);
$aval = media_avaliacoes((int) $u['id']);
$conversas = all('SELECT c.*, us.nome AS interessado, i.titulo AS imovel,
                    (SELECT texto FROM mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima
                  FROM conversas c JOIN usuarios us ON us.id = c.interessado_id LEFT JOIN imoveis i ON i.id = c.imovel_id
                  WHERE c.anunciante_id = ? ORDER BY c.atualizado_em DESC LIMIT 5', [$u['id']]);

$titulo = 'Meu painel';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Olá, <?= e(explode(' ', $u['nome'])[0]) ?></h1>
  <?php require __DIR__ . '/_nav.php'; ?>

  <?php if (!$emDia): ?>
    <div class="alerta alerta-aviso">
      Sua assinatura está <strong><?= e(mb_strtolower(status_assinatura_label($u['assinatura_status']))) ?></strong>. Seus imóveis não aparecem para o público.
      <a href="<?= url('painel/assinatura.php') ?>">Ativar assinatura</a>
    </div>
  <?php endif; ?>

  <div class="cards-resumo">
    <div class="caixa"><span>Imóveis publicados</span><strong><?= (int) $tot['ativos'] ?></strong><small>de <?= (int) $tot['total'] ?> cadastrados</small></div>
    <div class="caixa"><span>Visualizações</span><strong><?= (int) $tot['views'] ?></strong><small>somando todos os anúncios</small></div>
    <div class="caixa"><span>Mensagens não lidas</span><strong><?= $naoLidas ?></strong><small><a href="<?= url('chat/index.php') ?>">abrir chat</a></small></div>
    <div class="caixa"><span>Avaliação</span><strong><?= $aval['total'] ? number_format($aval['media'], 1, ',', '') : '-' ?></strong><small><?= $aval['total'] ?> avaliações</small></div>
  </div>

  <div class="secao-titulo mt">
    <h2>Conversas recentes</h2>
    <a class="btn btn-sm" href="<?= url('painel/imovel_form.php') ?>">+ Novo imóvel</a>
  </div>
  <?php if ($conversas): ?>
    <ul class="lista-conversas">
      <?php foreach ($conversas as $c): ?>
        <li><a href="<?= url('chat/conversa.php?id=' . (int) $c['id']) ?>">
          <strong><?= e($c['interessado']) ?></strong><?php if ($c['imovel']): ?> · <small><?= e($c['imovel']) ?></small><?php endif; ?>
          <p><?= e(mb_strimwidth((string) $c['ultima'], 0, 120, '…')) ?></p>
        </a></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="vazio">Nenhuma conversa ainda.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
