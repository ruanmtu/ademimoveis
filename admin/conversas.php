<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

$where = '1=1';
$params = [];
if (($busca = get('q')) !== '') {
    $where = '(it.nome LIKE ? OR it.email LIKE ? OR a.nome LIKE ? OR a.email LIKE ?)';
    $params = array_fill(0, 4, "%$busca%");
}
$base = "FROM conversas c JOIN usuarios it ON it.id = c.interessado_id JOIN usuarios a ON a.id = c.anunciante_id
         LEFT JOIN imoveis i ON i.id = c.imovel_id WHERE $where";
$total = (int) val("SELECT COUNT(*) $base", $params);
$pg = paginar($total, 30);
$lista = all("SELECT c.*, it.nome AS interessado, a.nome AS anunciante, i.titulo AS imovel,
                (SELECT COUNT(*) FROM mensagens m WHERE m.conversa_id = c.id) AS n_msgs
              $base ORDER BY c.atualizado_em DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}", $params);

$titulo = 'Conversas';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Conversas <small>(<?= $total ?>)</small></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <p class="dica">Acesso para suporte e moderação (somente leitura). Use apenas quando houver denúncia ou pedido de ajuda.</p>
  <form class="filtro-linha" method="get">
    <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Nome ou e-mail de um participante">
    <button class="btn btn-sm">Buscar</button>
  </form>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Interessado</th><th>Anunciante</th><th>Imóvel</th><th>Mensagens</th><th>Última atividade</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $c): ?>
      <tr>
        <td><a href="<?= url('admin/usuarios.php?id=' . (int) $c['interessado_id']) ?>"><?= e($c['interessado']) ?></a></td>
        <td><a href="<?= url('admin/usuarios.php?id=' . (int) $c['anunciante_id']) ?>"><?= e($c['anunciante']) ?></a></td>
        <td><?= e($c['imovel'] ?? '-') ?></td>
        <td><?= (int) $c['n_msgs'] ?></td>
        <td><small><?= data_br($c['atualizado_em'], true) ?></small></td>
        <td><a class="btn btn-sm btn-claro" href="<?= url('chat/conversa.php?id=' . (int) $c['id']) ?>">Ver</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= links_paginacao($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
