<?php $atual = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="subnav subnav-admin">
  <?php foreach ([
      'index.php' => 'Painel', 'usuarios.php' => 'Usuários', 'imoveis.php' => 'Imóveis', 'conversas.php' => 'Conversas',
      'avaliacoes.php' => 'Avaliações', 'planos.php' => 'Planos', 'cupons.php' => 'Cupons', 'mercadopago.php' => 'Mercado Pago', 'pagamentos.php' => 'Pagamentos',
  ] as $arq => $rot): ?>
    <a href="<?= url('admin/' . $arq) ?>" class="<?= $atual === $arq ? 'ativo' : '' ?>"><?= $rot ?></a>
  <?php endforeach; ?>
</nav>
