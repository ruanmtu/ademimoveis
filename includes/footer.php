</main>
<?php /* Telefone, e-mail e endereço são FICTÍCIOS (provisórios): troque pelos reais antes do lançamento.
         O corretor responsável e o CRECI são reais. */ ?>
<footer class="rodape">
  <div class="container rodape-grade">
    <div class="rodape-marca">
      <img class="rodape-logo" src="<?= asset('img/logo-completa.webp') ?>" alt="ADemóveis - Corretora de Imóveis" width="150" height="104" loading="lazy">
      <p>Imóveis para comprar e alugar, direto com quem anuncia.</p>
      <div class="rodape-corretor">
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
      <h4>Contato</h4>
      <a href="tel:+558530000000">(85) 3000-0000</a>
      <a href="mailto:contato@ademoveis.com.br">contato@ademoveis.com.br</a>
      <span>Av. Exemplo, 1000, Centro<br>Fortaleza - CE</span>
    </div>
  </div>
  <div class="container rodape-base">
    <span>&copy; <?= date('Y') ?> ADemóveis. Todos os direitos reservados. Corretor responsável: Dawidson Oliveira Anfrisio, CRECI-CE 24199.</span>
    <span class="rodape-creditos">Desenvolvido por <strong>BV Tecnologias</strong></span>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
