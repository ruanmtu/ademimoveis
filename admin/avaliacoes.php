<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acao = post('acao');
    if ($acao === 'ocultar') q("UPDATE avaliacoes SET status = 'oculta' WHERE id = ?", [$id]);
    if ($acao === 'mostrar') q("UPDATE avaliacoes SET status = 'visivel' WHERE id = ?", [$id]);
    if ($acao === 'excluir') q('DELETE FROM avaliacoes WHERE id = ?', [$id]);
    flash('sucesso', 'Avaliação atualizada.');
    redirect('admin/avaliacoes.php');
}

$total = (int) val('SELECT COUNT(*) FROM avaliacoes');
$pg = paginar($total, 30);
$lista = all("SELECT av.*, a.nome AS anunciante, au.nome AS autor FROM avaliacoes av
              JOIN usuarios a ON a.id = av.anunciante_id JOIN usuarios au ON au.id = av.autor_id
              ORDER BY av.id DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}");

$titulo = 'Avaliações';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Avaliações <small>(<?= $total ?>)</small></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Anunciante</th><th>Autor</th><th>Nota</th><th>Comentário</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $av): ?>
      <tr>
        <td><a href="<?= url('anunciante.php?id=' . (int) $av['anunciante_id']) ?>"><?= e($av['anunciante']) ?></a></td>
        <td><a href="<?= url('admin/usuarios.php?id=' . (int) $av['autor_id']) ?>"><?= e($av['autor']) ?></a><br><small><?= data_br($av['criado_em']) ?></small></td>
        <td class="estrelas"><?= estrelas((float) $av['nota']) ?></td>
        <td><?= e(mb_strimwidth((string) $av['comentario'], 0, 200, '…')) ?></td>
        <td><span class="status status-<?= e($av['status']) ?>"><?= e($av['status']) ?></span></td>
        <td class="acoes">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $av['id'] ?>">
            <?php if ($av['status'] === 'visivel'): ?><button class="btn btn-sm btn-claro" name="acao" value="ocultar">Ocultar</button>
            <?php else: ?><button class="btn btn-sm btn-claro" name="acao" value="mostrar">Mostrar</button><?php endif; ?>
            <button class="btn btn-sm btn-perigo" name="acao" value="excluir" data-confirmar="Excluir esta avaliação?">Excluir</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= links_paginacao($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
