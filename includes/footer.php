</main>
<footer class="rodape">
  <div class="container rodape-inner">
    <div>
      <img class="rodape-logo" src="<?= asset('img/logo-completa.webp') ?>" alt="ADemóveis - Corretora de Imóveis" width="220" height="152" loading="lazy">
      <p>Imóveis para comprar e alugar, direto com quem anuncia.</p>
    </div>
    <div>
      <a href="<?= url('imoveis.php') ?>">Buscar imóveis</a>
      <a href="<?= url('cadastro.php?tipo=anunciante') ?>">Seja anunciante</a>
      <a href="<?= url('termos.php') ?>">Termos de uso</a>
    </div>
  </div>
  <div class="container copy">© <?= date('Y') ?> ADemóveis. Todos os direitos reservados.</div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
