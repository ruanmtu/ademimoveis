<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) get('id');
$im = one('SELECT i.*, ' . sql_imovel_visivel() . ' AS visivel FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id WHERE i.id = ?', [$id]);
$u = usuario();
$dono = $u && $im && (int) $im['anunciante_id'] === (int) $u['id'];

if (!$im || $im['status'] === 'removido' && !e_admin() || (!$im['visivel'] && !$dono && !e_admin())) {
    http_response_code(404);
    $titulo = 'Imóvel não encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container secao"><h1>Imóvel não encontrado</h1><p>Este anúncio não está mais disponível.</p><a class="btn" href="' . url('imoveis.php') . '">Ver outros imóveis</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (!$dono) {
    q('UPDATE imoveis SET visualizacoes = visualizacoes + 1 WHERE id = ?', [$id]);
}

$fotos = all('SELECT * FROM imovel_fotos WHERE imovel_id = ? ORDER BY ordem, id', [$id]);
$anunc = one('SELECT id, nome, foto, creci, cidade, estado, whatsapp, criado_em FROM usuarios WHERE id = ?', [$im['anunciante_id']]);
$aval = media_avaliacoes((int) $anunc['id']);
$similares = all("SELECT i.*, (SELECT arquivo FROM imovel_fotos fo WHERE fo.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa
                  FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id
                  WHERE " . sql_imovel_visivel() . " AND i.id <> ? AND i.cidade = ? AND i.finalidade = ?
                  ORDER BY i.criado_em DESC LIMIT 4", [$id, $im['cidade'], $im['finalidade']]);

$titulo = $im['titulo'];
$descricaoPagina = mb_substr(strip_tags((string) $im['descricao']), 0, 155);
$scripts = ['js/galeria.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container secao">
  <?php if (!$im['visivel']): ?>
    <div class="alerta alerta-aviso">Este anúncio não está visível para o público (pausado ou assinatura inativa). Só você<?= e_admin() ? ' e a administração' : '' ?> pode vê-lo.</div>
  <?php endif; ?>

  <nav class="migalhas"><a href="<?= url('imoveis.php') ?>">Imóveis</a> › <a href="<?= url('imoveis.php?local=' . urlencode($im['cidade'])) ?>"><?= e($im['cidade']) ?></a> › <?= e($im['titulo']) ?></nav>

  <div class="imovel-layout">
    <div>
      <div class="galeria" data-galeria>
        <div class="galeria-principal">
          <img src="<?= e(upload_url($fotos[0]['arquivo'] ?? null)) ?>" alt="<?= e($im['titulo']) ?>" data-galeria-principal>
          <?php if (count($fotos) > 1): ?>
            <button class="galeria-nav ant" data-galeria-ant aria-label="Foto anterior">‹</button>
            <button class="galeria-nav prox" data-galeria-prox aria-label="Próxima foto">›</button>
          <?php endif; ?>
        </div>
        <?php if (count($fotos) > 1): ?>
          <div class="galeria-miniaturas">
            <?php foreach ($fotos as $k => $fo): ?>
              <img src="<?= e(upload_url($fo['arquivo'])) ?>" alt="Foto <?= $k + 1 ?>" data-galeria-mini class="<?= $k === 0 ? 'ativa' : '' ?>" loading="lazy">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="imovel-cabecalho">
        <span class="etiqueta etiqueta-<?= e($im['finalidade']) ?>"><?= e(finalidades()[$im['finalidade']]) ?></span>
        <span class="etiqueta etiqueta-neutra"><?= e(tipos_imovel()[$im['tipo']]) ?></span>
        <h1><?= e($im['titulo']) ?></h1>
        <p class="card-local"><?= e(trim(($im['endereco'] ? $im['endereco'] . ' - ' : '') . ($im['bairro'] ? $im['bairro'] . ', ' : '') . $im['cidade'] . ' - ' . $im['estado'])) ?></p>
      </div>

      <ul class="caracteristicas">
        <?php if ($im['area']): ?><li><strong><?= e(rtrim(rtrim(number_format((float) $im['area'], 2, ',', '.'), '0'), ',')) ?> m²</strong><span>Área</span></li><?php endif; ?>
        <li><strong><?= (int) $im['quartos'] ?></strong><span>Quartos</span></li>
        <li><strong><?= (int) $im['banheiros'] ?></strong><span>Banheiros</span></li>
        <li><strong><?= (int) $im['vagas'] ?></strong><span>Vagas</span></li>
      </ul>

      <?php $coms = comodidades_do_imovel($id); if ($coms): ?>
        <h2>Comodidades</h2>
        <ul class="lista-comodidades">
          <?php foreach ($coms as $c) if (isset(comodidades()[$c])): [$rot, $ic] = comodidades()[$c]; ?>
            <li><?= icone($ic, 20) ?><?= e($rot) ?></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>

      <h2>Descrição</h2>
      <div class="descricao"><?= nl2br(e($im['descricao'] ?: 'Sem descrição.')) ?></div>
      <p class="meta-anuncio">Anúncio nº <?= (int) $im['id'] ?> · Atualizado em <?= data_br($im['atualizado_em']) ?> · <?= (int) $im['visualizacoes'] ?> visualizações</p>
    </div>

    <aside class="imovel-lateral">
      <div class="caixa">
        <div class="preco-grande"><?= dinheiro($im['preco']) ?><?= $im['finalidade'] !== 'venda' ? '<small>/mês</small>' : '' ?></div>
        <?php if ($im['condominio']): ?><p class="linha-valor"><span>Condomínio</span><span><?= dinheiro($im['condominio']) ?></span></p><?php endif; ?>
        <?php if ($im['iptu']): ?><p class="linha-valor"><span>IPTU</span><span><?= dinheiro($im['iptu']) ?></span></p><?php endif; ?>
      </div>

      <div class="caixa anunciante-box">
        <a class="anunciante-mini" href="<?= url('anunciante.php?id=' . (int) $anunc['id']) ?>">
          <img class="avatar" src="<?= e(upload_url($anunc['foto'], 'assets/img/avatar.svg')) ?>" alt="">
          <div>
            <strong><?= e($anunc['nome']) ?></strong>
            <?php if ($anunc['creci']): ?><small>CRECI <?= e($anunc['creci']) ?></small><?php endif; ?>
            <small class="estrelas"><?= $aval['total'] ? estrelas($aval['media']) . ' ' . number_format($aval['media'], 1, ',', '') . ' (' . $aval['total'] . ')' : 'Sem avaliações' ?></small>
          </div>
        </a>

        <?php if ($dono): ?>
          <a class="btn btn-bloco" href="<?= url('painel/imovel_form.php?id=' . $id) ?>">Editar anúncio</a>
        <?php elseif ($u): ?>
          <form method="post" action="<?= url('chat/iniciar.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="anunciante_id" value="<?= (int) $anunc['id'] ?>">
            <input type="hidden" name="imovel_id" value="<?= $id ?>">
            <label>Mensagem
              <textarea name="mensagem" rows="4" maxlength="2000">Olá! Tenho interesse no imóvel "<?= e($im['titulo']) ?>" (nº <?= $id ?>). Ele ainda está disponível?</textarea>
            </label>
            <button class="btn btn-bloco" type="submit">Enviar mensagem pelo chat</button>
          </form>
        <?php else: ?>
          <p>Entre na sua conta para conversar com o anunciante.</p>
          <a class="btn btn-bloco" href="<?= url('login.php') ?>" onclick="return true">Entrar para conversar</a>
          <?php $_SESSION['voltar_para'] = $_SERVER['REQUEST_URI'] ?? ''; ?>
        <?php endif; ?>
        <a class="link-perfil" href="<?= url('anunciante.php?id=' . (int) $anunc['id']) ?>">Ver perfil e outros imóveis</a>
      </div>
    </aside>
  </div>

  <?php if ($similares): ?>
    <h2 class="mt">Imóveis parecidos em <?= e($im['cidade']) ?></h2>
    <div class="grade-imoveis">
      <?php foreach ($similares as $im) include __DIR__ . '/includes/card_imovel.php'; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
