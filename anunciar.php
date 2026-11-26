<?php
// Primeiro passo de quem quer anunciar: escolher o plano. Depois vem o cadastro e o pagamento.
require __DIR__ . '/includes/bootstrap.php';

$u = usuario();
$cupom = normalizar_codigo(get('cupom'));

// Anunciante já logado escolhe o plano direto na assinatura
if ($u && $u['tipo'] === 'anunciante') {
    $q = array_filter(['plano' => (int) get('plano') ?: null, 'cupom' => $cupom ?: null]);
    redirect('painel/assinatura.php' . ($q ? '?' . http_build_query($q) : ''));
}

$planos = all('SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, preco');

$titulo = 'Anuncie seu imóvel';
$descricaoPagina = 'Escolha um plano e anuncie seus imóveis na ' . SITE_NOME . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="container secao anunciar">
  <ol class="passos-anunciar" aria-label="Etapas para anunciar">
    <li class="ativo" aria-current="step"><span>1</span>Plano</li>
    <li><span>2</span>Sua conta</li>
    <li><span>3</span>Pagamento</li>
  </ol>

  <header class="anunciar-topo">
    <h1>Anuncie seus imóveis na <?= e(SITE_NOME) ?></h1>
    <p class="sub">Escolha o plano que combina com a quantidade de imóveis que você tem. Depois é só criar sua conta e pagar pelo Mercado Pago. Você pode trocar de plano ou cancelar quando quiser.</p>
  </header>

  <?php if ($u): ?>
    <div class="alerta alerta-aviso">
      <?php if ($u['tipo'] === 'admin'): ?>
        Você está conectado como administrador. Os planos são editados em <a href="<?= url('admin/planos.php') ?>">Administração › Planos</a>.
      <?php else: ?>
        Você está conectado com uma conta de cliente, que não pode publicar anúncios. Para anunciar, <a href="<?= url('logout.php') ?>">saia</a> e crie uma conta de anunciante com outro e-mail.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$planos): ?>
    <p class="vazio">Os planos ainda estão sendo preparados. Fale com o nosso atendimento pelo rodapé da página.</p>
  <?php else: ?>
    <div class="planos">
      <?php foreach ($planos as $p):
          $per = (int) $p['periodicidade_meses'];
          $link = url('cadastro.php?' . http_build_query(array_filter(['plano' => (int) $p['id'], 'cupom' => $cupom ?: null]))); ?>
        <article class="caixa plano <?= $p['recomendado'] ? 'plano-recomendado' : '' ?>">
          <?php if ($p['recomendado']): ?><span class="selo-plano">Mais escolhido</span><?php endif; ?>
          <h2 class="plano-nome"><?= e($p['nome']) ?></h2>
          <?php if ($p['descricao']): ?><p class="dica"><?= e($p['descricao']) ?></p><?php endif; ?>
          <div class="preco-grande"><?= dinheiro($p['preco']) ?><small><?= sufixo_periodo($per) ?></small></div>
          <?php if ($per > 1): ?><p class="dica">equivale a <?= dinheiro((float) $p['preco'] / $per) ?>/mês</p><?php endif; ?>
          <ul>
            <li><?= texto_limite_imoveis($p['limite_imoveis']) ?></li>
            <li>Até <?= (int) $p['limite_fotos'] ?> fotos por imóvel</li>
            <?php if ($p['destaques']): ?><li><?= (int) $p['destaques'] ?> imóve<?= $p['destaques'] > 1 ? 'is' : 'l' ?> em destaque</li><?php endif; ?>
            <?php foreach (recursos_plano($p['recursos']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
          </ul>
          <?php if ($u): ?>
            <button class="btn btn-bloco btn-claro" type="button" disabled>Escolher <?= e($p['nome']) ?></button>
          <?php else: ?>
            <a class="btn btn-bloco <?= $p['recomendado'] ? '' : 'btn-claro' ?>" href="<?= $link ?>">Escolher <?= e($p['nome']) ?></a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="dica anunciar-rodape">
    Já anuncia aqui? <a href="<?= url('login.php') ?>">Entre na sua conta</a> para ver ou trocar seu plano.
    <?= $cupom ? ' Cupom <strong>' . e($cupom) . '</strong> será aplicado no pagamento.' : ' Tem um cupom? Você informa na etapa de pagamento.' ?>
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
