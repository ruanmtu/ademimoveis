</main>
<?php /* Atendimento: Samuel Venâncio, (88) 98223-7038 (real). O e-mail ainda é provisório.
         Corretor responsável (credenciamento, não é o contato de atendimento): Dawidson Oliveira Anfrisio, CRECI-CE 24199. */ ?>
<footer class="rodape">
  <div class="container rodape-grade">
    <div class="rodape-marca">
      <img class="rodape-logo" src="<?= asset('img/logo-completa.webp') ?>" alt="Ademóveis - Corretora de Imóveis" width="150" height="104" loading="lazy">
      <p>Imóveis para comprar e alugar, direto com quem anuncia.</p>
      <div class="rodape-corretor">
        <small>Corretor responsável</small>
        <strong>Dawidson Oliveira Anfrisio</strong>
        <span>Corretor de Imóveis</span>
        <span class="rodape-creci">CRECI-CE 24199</span>
      </div>
    </div>
    <nav class="rodape-col" aria-label="Imóveis">
      <h4>Imóveis</h4>
      <a href="<?= url('imoveis.php?finalidade=venda') ?>">Comprar</a>
      <a href="<?= url('imoveis.php?finalidade=aluguel') ?>">Alugar</a>
      <a href="<?= url('imoveis.php?finalidade=temporada') ?>">Temporada</a>
    </nav>
    <nav class="rodape-col" aria-label="Para anunciantes">
      <h4>Anunciantes</h4>
      <a href="<?= url('cadastro.php?tipo=anunciante') ?>">Anunciar imóvel</a>
      <a href="<?= url('painel/assinatura.php') ?>">Planos</a>
      <a href="<?= url('termos.php') ?>">Termos de uso</a>
    </nav>
    <div class="rodape-col">
      <h4>Atendimento</h4>
      <span class="rodape-contato-nome">Samuel Venâncio</span>
      <a href="tel:+5588982237038"><?= icone('telefone', 16) ?>(88) 98223-7038</a>
      <a href="mailto:contato@ademoveis.com.br"><?= icone('email', 16) ?>contato@ademoveis.com.br</a>
      <a class="rodape-whatsapp" href="<?= e(link_whatsapp('88982237038', 'Olá, Samuel! Vim pelo site da Ademóveis e gostaria de atendimento.')) ?>" target="_blank" rel="noopener" aria-label="Falar com Samuel Venâncio no WhatsApp">
        <?= icone('whatsapp', 20) ?>Falar no WhatsApp
      </a>
    </div>
  </div>
  <div class="container rodape-base">
    <span>&copy; <?= date('Y') ?> Ademóveis. Todos os direitos reservados. Corretor responsável: Dawidson Oliveira Anfrisio, CRECI-CE 24199.</span>
    <span class="rodape-creditos">Desenvolvido por <strong>BV Tecnologias</strong></span>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
