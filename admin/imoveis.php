<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acao = post('acao');
    if (in_array($acao, ['ativo', 'pausado', 'removido'], true)) {
        q('UPDATE imoveis SET status = ?, atualizado_em = ? WHERE id = ?', [$acao, agora(), $id]);
        flash('sucesso', 'Situação do anúncio alterada.');
    } elseif ($acao === 'destaque') {
        q('UPDATE imoveis SET destaque = 1 - destaque WHERE id = ?', [$id]);
        flash('sucesso', 'Destaque alterado.');
    }
    redirect('admin/imoveis.php?' . http_build_query(array_filter(['q' => post('q'), 'status' => post('status_f'), 'pagina' => post('pagina')])));
}

$where = ['1=1'];
$params = [];
if (($busca = get('q')) !== '') {
    $where[] = '(i.titulo LIKE ? OR i.cidade LIKE ? OR u.nome LIKE ? OR i.id = ?)';
    array_push($params, "%$busca%", "%$busca%", "%$busca%", (int) $busca);
}
$stF = get('status');
if (in_array($stF, ['ativo', 'pausado', 'removido'], true)) { $where[] = 'i.status = ?'; $params[] = $stF; }
else { $where[] = "i.status <> 'removido'"; }

$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id WHERE $w", $params);
$pg = paginar($total, 25);
$lista = all("SELECT i.*, u.nome AS anunciante, " . sql_imovel_visivel() . " AS visivel
              FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id WHERE $w
              ORDER BY i.id DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}", $params);

$titulo = 'Imóveis';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Imóveis <small>(<?= $total ?>)</small></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <form class="filtro-linha" method="get">
    <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Título, cidade, anunciante ou nº">
    <select name="status"><option value="">Publicados e pausados</option>
      <?php foreach (['ativo', 'pausado', 'removido'] as $s): ?><option <?= $stF === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm">Filtrar</button>
  </form>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Nº</th><th>Imóvel</th><th>Anunciante</th><th>Preço</th><th>Situação</th><th>No ar?</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $im): ?>
      <tr>
        <td><?= (int) $im['id'] ?></td>
        <td><a href="<?= url('imovel.php?id=' . (int) $im['id']) ?>"><?= e($im['titulo']) ?></a><?= $im['destaque'] ? ' ⭐' : '' ?><br><small><?= e(finalidades()[$im['finalidade']]) ?> · <?= e($im['cidade']) ?>-<?= e($im['estado']) ?></small></td>
        <td><a href="<?= url('admin/usuarios.php?id=' . (int) $im['anunciante_id']) ?>"><?= e($im['anunciante']) ?></a></td>
        <td><?= dinheiro($im['preco']) ?></td>
        <td><span class="status status-<?= e($im['status']) ?>"><?= e($im['status']) ?></span></td>
        <td><?= $im['visivel'] ? 'Sim' : 'Não' ?></td>
        <td class="acoes">
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
            <input type="hidden" name="q" value="<?= e(get('q')) ?>"><input type="hidden" name="status_f" value="<?= e($stF) ?>"><input type="hidden" name="pagina" value="<?= $pg['pagina'] ?>">
            <button class="btn btn-sm btn-claro" name="acao" value="destaque"><?= $im['destaque'] ? 'Tirar destaque' : 'Destacar' ?></button>
            <?php if ($im['status'] !== 'pausado'): ?><button class="btn btn-sm btn-claro" name="acao" value="pausado">Pausar</button><?php endif; ?>
            <?php if ($im['status'] !== 'ativo'): ?><button class="btn btn-sm btn-claro" name="acao" value="ativo">Publicar</button><?php endif; ?>
            <?php if ($im['status'] !== 'removido'): ?><button class="btn btn-sm btn-perigo" name="acao" value="removido" data-confirmar="Remover este anúncio do site?">Remover</button><?php endif; ?>
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
