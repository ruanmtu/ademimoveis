<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) get('id');
$a = one("SELECT * FROM usuarios WHERE id = ? AND tipo = 'anunciante'", [$id]);
if (!$a || ($a['status'] !== 'ativo' && !e_admin())) {
    http_response_code(404);
    $titulo = 'Anunciante não encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container secao"><h1>Anunciante não encontrado</h1></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$u = usuario();
$souEu = $u && (int) $u['id'] === $id;

// Quem pode avaliar: usuário logado (não o próprio) que já conversou com o anunciante
$podeAvaliar = $u && !$souEu && $u['tipo'] !== 'admin'
    && (bool) val('SELECT 1 FROM conversas WHERE interessado_id = ? AND anunciante_id = ? LIMIT 1', [$u['id'], $id]);
$minhaAval = $u ? one('SELECT * FROM avaliacoes WHERE anunciante_id = ? AND autor_id = ?', [$id, $u['id']]) : null;

if (is_post() && post('acao') === 'avaliar') {
    csrf_check();
    if (!$podeAvaliar) {
        flash('erro', 'Você precisa ter conversado com este anunciante para avaliá-lo.');
    } else {
        $nota = (int) post('nota');
        $coment = mb_substr(post('comentario'), 0, 1000);
        if ($nota < 1 || $nota > 5) {
            flash('erro', 'Escolha uma nota de 1 a 5.');
        } elseif ($minhaAval) {
            q('UPDATE avaliacoes SET nota = ?, comentario = ?, criado_em = ? WHERE id = ?', [$nota, $coment, agora(), $minhaAval['id']]);
            flash('sucesso', 'Sua avaliação foi atualizada.');
        } else {
            q('INSERT INTO avaliacoes (anunciante_id, autor_id, nota, comentario, criado_em) VALUES (?,?,?,?,?)',
              [$id, $u['id'], $nota, $coment, agora()]);
            flash('sucesso', 'Obrigado pela avaliação!');
        }
    }
    redirect('anunciante.php?id=' . $id . '#avaliacoes');
}

$aval = media_avaliacoes($id);
$avaliacoes = all("SELECT av.*, us.nome AS autor FROM avaliacoes av JOIN usuarios us ON us.id = av.autor_id
                   WHERE av.anunciante_id = ? AND av.status = 'visivel' ORDER BY av.criado_em DESC LIMIT 50", [$id]);
$imoveis = all("SELECT i.*, (SELECT arquivo FROM imovel_fotos fo WHERE fo.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa
                FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id
                WHERE i.anunciante_id = ? AND " . sql_imovel_visivel() . " ORDER BY i.criado_em DESC", [$id]);

$titulo = $a['nome'];
require __DIR__ . '/includes/header.php';
?>
<div class="container secao">
  <div class="perfil-topo caixa">
    <img class="avatar avatar-g" src="<?= e(upload_url($a['foto'], 'assets/img/avatar.svg')) ?>" alt="">
    <div class="perfil-info">
      <h1><?= e($a['nome']) ?></h1>
      <p class="estrelas"><?= $aval['total'] ? estrelas($aval['media']) . ' <strong>' . number_format($aval['media'], 1, ',', '') . '</strong> · ' . $aval['total'] . ' avaliação' . ($aval['total'] > 1 ? 'ões' : '') : 'Ainda sem avaliações' ?></p>
      <ul class="perfil-dados">
        <?php if ($a['creci'] && $a['creci_status'] === 'verificado'): ?><li class="selo-creci"><?= icone('verificado', 16) ?>CRECI-<?= e($a['creci_uf']) ?> <?= e($a['creci']) ?> verificado</li>
        <?php elseif ($a['creci']): ?><li>CRECI-<?= e($a['creci_uf']) ?> <?= e($a['creci']) ?></li><?php endif; ?>
        <?php if ($a['cidade']): ?><li><?= e($a['cidade']) ?><?= $a['estado'] ? ' - ' . e($a['estado']) : '' ?></li><?php endif; ?>
        <li>Na Ademimóveis desde <?= date('m/Y', strtotime($a['criado_em'])) ?></li>
        <li><?= count($imoveis) ?> imóve<?= count($imoveis) === 1 ? 'l' : 'is' ?> anunciado<?= count($imoveis) === 1 ? '' : 's' ?></li>
      </ul>
      <?php if ($a['bio']): ?><p class="bio"><?= nl2br(e($a['bio'])) ?></p><?php endif; ?>
    </div>
    <div class="perfil-acoes">
      <?php if ($souEu): ?>
        <a class="btn" href="<?= url('conta.php') ?>">Editar meu perfil</a>
      <?php elseif ($u): ?>
        <form method="post" action="<?= url('chat/iniciar.php') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="anunciante_id" value="<?= $id ?>">
          <button class="btn" type="submit">💬 Conversar no chat</button>
        </form>

      <?php else: ?>
        <?php $_SESSION['voltar_para'] = $_SERVER['REQUEST_URI'] ?? ''; ?>
        <a class="btn" href="<?= url('login.php') ?>">Entrar para conversar</a>
      <?php endif; ?>
      <?php if (!$souEu && ($zap = link_whatsapp($a['whatsapp'], 'Olá, ' . explode(' ', $a['nome'])[0] . '! Vi seu perfil na Ademimóveis e gostaria de saber mais sobre seus imóveis.'))): ?>
        <a class="btn btn-whatsapp" target="_blank" rel="noopener" href="<?= e($zap) ?>"><?= icone('whatsapp', 20) ?>WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="mt">Imóveis de <?= e(explode(' ', $a['nome'])[0]) ?></h2>
  <?php if ($imoveis): ?>
    <div class="grade-imoveis">
      <?php foreach ($imoveis as $im) include __DIR__ . '/includes/card_imovel.php'; ?>
    </div>
  <?php else: ?>
    <p class="vazio">Nenhum imóvel disponível no momento.</p>
  <?php endif; ?>

  <h2 class="mt" id="avaliacoes">Avaliações</h2>
  <?php if ($podeAvaliar): ?>
    <form method="post" class="caixa form-avaliacao">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="avaliar">
      <strong><?= $minhaAval ? 'Editar sua avaliação' : 'Avalie este anunciante' ?></strong>
      <div class="seletor-estrelas">
        <?php for ($n = 5; $n >= 1; $n--): ?>
          <input type="radio" id="n<?= $n ?>" name="nota" value="<?= $n ?>" <?= ($minhaAval['nota'] ?? 0) == $n ? 'checked' : '' ?> required>
          <label for="n<?= $n ?>" title="<?= $n ?> estrela<?= $n > 1 ? 's' : '' ?>">★</label>
        <?php endfor; ?>
      </div>
      <textarea name="comentario" rows="3" maxlength="1000" placeholder="Conte como foi seu atendimento (opcional)"><?= e($minhaAval['comentario'] ?? '') ?></textarea>
      <button class="btn" type="submit">Enviar avaliação</button>
    </form>
  <?php elseif ($u && !$souEu && $u['tipo'] !== 'admin'): ?>
    <p class="dica">Converse com o anunciante pelo chat para poder deixar uma avaliação.</p>
  <?php endif; ?>

  <?php if ($avaliacoes): ?>
    <div class="lista-avaliacoes">
      <?php foreach ($avaliacoes as $av): ?>
        <div class="avaliacao">
          <div class="avaliacao-topo"><strong><?= e($av['autor']) ?></strong><span class="estrelas"><?= estrelas((float) $av['nota']) ?></span><small><?= data_br($av['criado_em']) ?></small></div>
          <?php if ($av['comentario']): ?><p><?= nl2br(e($av['comentario'])) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vazio">Nenhuma avaliação ainda.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
