<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_login();
$uid = (int) $u['id'];
$id = (int) get('id');
garantir_coluna_contato();

$c = one('SELECT c.*, a.nome AS anunc_nome, a.foto AS anunc_foto, a.whatsapp AS anunc_whatsapp, a.creci, a.creci_status,
                 it.nome AS inter_nome, it.foto AS inter_foto
          FROM conversas c JOIN usuarios a ON a.id = c.anunciante_id JOIN usuarios it ON it.id = c.interessado_id
          WHERE c.id = ?', [$id]);

$participante = $c && in_array($uid, [(int) $c['interessado_id'], (int) $c['anunciante_id']], true);
if (!$c || (!$participante && !e_admin())) {
    flash('erro', 'Conversa não encontrada.');
    redirect('chat/index.php');
}

$im = $c['imovel_id'] ? one("SELECT i.*, (SELECT arquivo FROM imovel_fotos fo WHERE fo.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa
                             FROM imoveis i WHERE i.id = ? AND i.status <> 'removido'", [$c['imovel_id']]) : null;

$souAnunc = $uid === (int) $c['anunciante_id'];
$souInteressado = $uid === (int) $c['interessado_id'];
$outroNome = $souAnunc ? $c['inter_nome'] : $c['anunc_nome'];
$outroFoto = $souAnunc ? $c['inter_foto'] : $c['anunc_foto'];
$creciOk = !$souAnunc && $c['creci'] && $c['creci_status'] === 'verificado';

// WhatsApp do anunciante: só para quem procura imóvel, e só se o anunciante liberou o número
$zap = null;
if ($souInteressado) {
    $primeiro = explode(' ', trim($c['anunc_nome']))[0];
    if ($im) {
        $zap = whatsapp_do_imovel($im, $c['anunc_whatsapp'], "Olá, $primeiro! Estamos conversando pela Ademimóveis sobre o imóvel \"{$im['titulo']}\" (nº {$im['id']}). "
            . url('imovel.php?id=' . (int) $im['id']));
    } elseif (anunciante_mostra_whatsapp((int) $c['anunciante_id'])) {
        $zap = link_whatsapp($c['anunc_whatsapp'], "Olá, $primeiro! Estamos conversando pela Ademimóveis.");
    }
}
$soChat = $souInteressado && !$zap;

$sugestoes = $souInteressado
    ? ['O imóvel ainda está disponível?', 'Posso agendar uma visita?', 'Aceita financiamento?', 'Qual o valor final com taxas?']
    : ['Olá! Obrigado pelo interesse.', 'Sim, ainda está disponível.', 'Qual o melhor dia para a visita?'];

$titulo = 'Conversa com ' . $outroNome;
$scripts = ['js/chat.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao secao-chat">
  <div class="chat" data-chat="<?= $id ?>" data-eu="<?= $uid ?>" data-api="<?= e(url('chat/api.php')) ?>" data-somente-leitura="<?= $participante ? '0' : '1' ?>">
    <header class="chat-topo">
      <a href="<?= url('chat/index.php') ?>" class="chat-voltar" aria-label="Voltar para as conversas">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      </a>
      <img class="chat-avatar" src="<?= e(upload_url($outroFoto, 'assets/img/avatar.svg')) ?>" alt="" width="46" height="46">
      <div class="chat-quem">
        <?php if (!$participante): ?>
          <strong><?= e($c['inter_nome']) ?> e <?= e($c['anunc_nome']) ?></strong>
          <small>Visualização da administração (somente leitura)</small>
        <?php else: ?>
          <strong><?= $souAnunc ? e($outroNome) : '<a href="' . url('anunciante.php?id=' . (int) $c['anunciante_id']) . '">' . e($outroNome) . '</a>' ?></strong>
          <small><?php if ($creciOk): ?><span class="chat-selo"><?= icone('verificado', 14) ?>CRECI verificado</span><?php endif; ?><span class="chat-papel"><?= $souAnunc ? 'Interessado no seu anúncio' : 'Anunciante' ?></span></small>
        <?php endif; ?>
      </div>
      <?php if ($zap): ?>
        <a class="btn btn-sm btn-whatsapp chat-zap" href="<?= e($zap) ?>" target="_blank" rel="noopener" aria-label="Continuar no WhatsApp">
          <?= icone('whatsapp', 18) ?><span>Continuar no WhatsApp</span>
        </a>
      <?php endif; ?>
    </header>

    <?php if ($im): ?>
      <a class="chat-imovel" href="<?= url('imovel.php?id=' . (int) $im['id']) ?>">
        <img src="<?= e(upload_url($im['capa'])) ?>" alt="" width="64" height="48" loading="lazy">
        <span class="chat-imovel-info">
          <strong><?= e($im['titulo']) ?></strong>
          <small><span class="etiqueta etiqueta-<?= e($im['finalidade']) ?>"><?= e(finalidades()[$im['finalidade']] ?? '') ?></span>
            <?= dinheiro($im['preco']) ?><?= $im['finalidade'] !== 'venda' ? '/mês' : '' ?> · <?= e(trim(($im['bairro'] ? $im['bairro'] . ', ' : '') . $im['cidade'])) ?></small>
        </span>
        <span class="chat-imovel-ver">Ver anúncio</span>
      </a>
    <?php elseif ($c['imovel_id']): ?>
      <div class="chat-imovel chat-imovel-off">Este anúncio não está mais disponível.</div>
    <?php endif; ?>

    <div class="chat-mensagens" data-mensagens aria-live="polite">
      <p class="chat-aviso-seguranca"><?= icone('cadeado', 15) ?>Para sua segurança, não faça pagamentos nem envie documentos antes de visitar o imóvel.</p>
      <p class="chat-carregando" data-carregando>Carregando mensagens…</p>
    </div>

    <?php if ($participante): ?>
      <div class="chat-sugestoes" data-sugestoes>
        <?php foreach ($sugestoes as $sg): ?><button type="button" data-sugestao><?= e($sg) ?></button><?php endforeach; ?>
      </div>
      <form class="chat-form" data-chat-form>
        <label class="sr-only" for="chat-texto">Mensagem</label>
        <textarea id="chat-texto" name="texto" rows="1" maxlength="2000" placeholder="Escreva sua mensagem" required></textarea>
        <button class="chat-enviar" type="submit" aria-label="Enviar mensagem">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </form>
      <p class="chat-rodape">
        <?php if ($soChat): ?>Este anunciante atende somente pelo chat da Ademimóveis. <?php endif; ?>
        <span class="so-desktop">Enter envia · Shift + Enter pula linha</span>
      </p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
