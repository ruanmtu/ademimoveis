<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_tipo('anunciante');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $im = one("SELECT * FROM imoveis WHERE id = ? AND anunciante_id = ? AND status <> 'removido'", [$id, $u['id']]);
    if ($im) {
        $acao = post('acao');
        if ($acao === 'pausar') {
            q("UPDATE imoveis SET status = 'pausado', atualizado_em = ? WHERE id = ?", [agora(), $id]);
            flash('sucesso', 'Anúncio pausado.');
        } elseif ($acao === 'ativar') {
            q("UPDATE imoveis SET status = 'ativo', atualizado_em = ? WHERE id = ?", [agora(), $id]);
            flash('sucesso', 'Anúncio reativado.');
        } elseif ($acao === 'destacar') {
            $lim = (int) plano_do_usuario($u)['destaques'];
            $usados = (int) val("SELECT COUNT(*) FROM imoveis WHERE anunciante_id = ? AND destaque = 1 AND status <> 'removido'", [$u['id']]);
            if ($im['destaque']) {
                q('UPDATE imoveis SET destaque = 0 WHERE id = ?', [$id]);
                flash('sucesso', 'Destaque removido.');
            } elseif ($usados >= $lim) {
                flash('aviso', $lim ? "Seu plano permite $lim imóve" . ($lim > 1 ? 'is' : 'l') . ' em destaque. Remova um destaque ou mude de plano.'
                                    : 'Seu plano não inclui destaques. Veja os planos em Assinatura.');
            } else {
                q('UPDATE imoveis SET destaque = 1 WHERE id = ?', [$id]);
                flash('sucesso', 'Imóvel destacado! Ele aparece primeiro nas buscas.');
            }
        } elseif ($acao === 'excluir') {
            foreach (all('SELECT arquivo FROM imovel_fotos WHERE imovel_id = ?', [$id]) as $fo) apagar_upload($fo['arquivo']);
            q('DELETE FROM imovel_fotos WHERE imovel_id = ?', [$id]);
            q("UPDATE imoveis SET status = 'removido', atualizado_em = ? WHERE id = ?", [agora(), $id]);
            flash('sucesso', 'Anúncio excluído.');
        }
    }
    redirect('painel/imoveis.php');
}

$imoveis = all("SELECT i.*, (SELECT arquivo FROM imovel_fotos f WHERE f.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa,
                  (SELECT COUNT(*) FROM imovel_fotos f WHERE f.imovel_id = i.id) AS n_fotos
                FROM imoveis i WHERE i.anunciante_id = ? AND i.status <> 'removido' ORDER BY i.criado_em DESC", [$u['id']]);

$titulo = 'Meus imóveis';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <div class="secao-titulo">
    <h1>Meus imóveis</h1>
    <a class="btn" href="<?= url('painel/imovel_form.php') ?>">+ Novo imóvel</a>
  </div>
  <?php require __DIR__ . '/_nav.php'; ?>

  <?php if (!anunciante_em_dia($u)): ?>
    <div class="alerta alerta-aviso">Seus anúncios só aparecem no site com a assinatura ativa. <a href="<?= url('painel/assinatura.php') ?>">Ver assinatura →</a></div>
  <?php endif; ?>

  <?php $pl = plano_do_usuario($u); $nDest = count(array_filter($imoveis, fn ($x) => $x['destaque']));
        $nTot = count($imoveis); ?>
  <p class="dica">Plano <strong><?= e($pl['nome']) ?></strong>: <?= $nTot ?> de <?= $pl['limite_imoveis'] ? (int) $pl['limite_imoveis'] : '∞' ?> imóveis · <?= $nDest ?> de <?= (int) $pl['destaques'] ?> destaques usados · <a href="<?= url('painel/assinatura.php') ?>">mudar de plano</a></p>
  <?php if ($imoveis): ?>
    <div class="tabela-wrap">
    <table class="tabela">
      <thead><tr><th></th><th>Imóvel</th><th>Preço</th><th>Status</th><th>Visualizações</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($imoveis as $im): ?>
        <tr>
          <td><img class="mini" src="<?= e(upload_url($im['capa'])) ?>" alt=""></td>
          <td><a href="<?= url('imovel.php?id=' . (int) $im['id']) ?>"><strong><?= e($im['titulo']) ?></strong></a><?= $im['destaque'] ? ' <span class="etiqueta etiqueta-destaque">★ Destaque</span>' : '' ?><br>
              <small><?= e(finalidades()[$im['finalidade']]) ?> · <?= e($im['cidade']) ?> · <?= (int) $im['n_fotos'] ?> fotos</small></td>
          <td><?= dinheiro($im['preco']) ?></td>
          <td><span class="status status-<?= e($im['status']) ?>"><?= $im['status'] === 'ativo' ? 'Publicado' : 'Pausado' ?></span></td>
          <td><?= (int) $im['visualizacoes'] ?></td>
          <td class="acoes">
            <a class="btn btn-sm btn-claro" href="<?= url('painel/imovel_form.php?id=' . (int) $im['id']) ?>">Editar</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
              <?php if ($im['status'] === 'ativo'): ?>
                <button class="btn btn-sm btn-claro" name="acao" value="pausar">Pausar</button>
              <?php else: ?>
                <button class="btn btn-sm btn-claro" name="acao" value="ativar">Publicar</button>
              <?php endif; ?>
              <button class="btn btn-sm btn-claro" name="acao" value="destacar"><?= $im['destaque'] ? '★ Tirar destaque' : '☆ Destacar' ?></button>
              <button class="btn btn-sm btn-perigo" name="acao" value="excluir" data-confirmar="Excluir este anúncio e todas as fotos?">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php else: ?>
    <p class="vazio">Você ainda não cadastrou imóveis. <a href="<?= url('painel/imovel_form.php') ?>">Cadastrar o primeiro</a></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
