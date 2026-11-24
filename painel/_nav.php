<?php $atual = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="subnav">
  <a href="<?= url('painel/index.php') ?>" class="<?= $atual === 'index.php' ? 'ativo' : '' ?>">Resumo</a>
  <a href="<?= url('painel/imoveis.php') ?>" class="<?= in_array($atual, ['imoveis.php', 'imovel_form.php'], true) ? 'ativo' : '' ?>">Meus imóveis</a>
  <a href="<?= url('chat/index.php') ?>">Mensagens</a>
  <a href="<?= url('painel/assinatura.php') ?>" class="<?= $atual === 'assinatura.php' ? 'ativo' : '' ?>">Assinatura</a>
  <a href="<?= url('conta.php') ?>">Meu perfil</a>
</nav>
