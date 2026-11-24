<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_login();
$uid = (int) $u['id'];
$id = (int) get('id');

$c = one('SELECT c.*, i.titulo AS imovel, a.nome AS anunc_nome, a.foto AS anunc_foto, it.nome AS inter_nome, it.foto AS inter_foto
          FROM conversas c JOIN usuarios a ON a.id = c.anunciante_id JOIN usuarios it ON it.id = c.interessado_id
          LEFT JOIN imoveis i ON i.id = c.imovel_id WHERE c.id = ?', [$id]);

$participante = $c && in_array($uid, [(int) $c['interessado_id'], (int) $c['anunciante_id']], true);
if (!$c || (!$participante && !e_admin())) {
    flash('erro', 'Conversa não encontrada.');
    redirect('chat/index.php');
}

$souAnunc = $uid === (int) $c['anunciante_id'];
$outroNome = $souAnunc ? $c['inter_nome'] : $c['anunc_nome'];
$outroFoto = $souAnunc ? $c['inter_foto'] : $c['anunc_foto'];

$titulo = 'Conversa com ' . $outroNome;
$scripts = ['js/chat.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <div class="chat caixa" data-chat="<?= $id ?>" data-eu="<?= $uid ?>" data-api="<?= e(url('chat/api.php')) ?>" data-somente-leitura="<?= $participante ? '0' : '1' ?>">
    <div class="chat-topo">
      <a href="<?= url('chat/index.php') ?>" class="voltar" aria-label="Voltar">←</a>
      <img class="avatar" src="<?= e(upload_url($outroFoto, 'assets/img/avatar.svg')) ?>" alt="">
      <div>
        <?php if (!$participante): ?>
          <strong><?= e($c['inter_nome']) ?> ↔ <?= e($c['anunc_nome']) ?></strong><small>Visualização da administração (somente leitura)</small>
        <?php else: ?>
          <strong><?= $souAnunc ? e($outroNome) : '<a href="' . url('anunciante.php?id=' . (int) $c['anunciante_id']) . '">' . e($outroNome) . '</a>' ?></strong>
        <?php endif; ?>
        <?php if ($c['imovel']): ?><small>Sobre: <a href="<?= url('imovel.php?id=' . (int) $c['imovel_id']) ?>"><?= e($c['imovel']) ?></a></small><?php endif; ?>
      </div>
    </div>
    <div class="chat-mensagens" data-mensagens aria-live="polite">
      <p class="chat-carregando">Carregando mensagens…</p>
    </div>
    <?php if ($participante): ?>
      <form class="chat-form" data-chat-form>
        <textarea name="texto" rows="1" maxlength="2000" placeholder="Escreva sua mensagem…" required></textarea>
        <button class="btn" type="submit">Enviar</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
