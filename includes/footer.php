</main>
<?php /* Atendimento: Samuel Venâncio, (88) 98223-7038 (real). O e-mail ainda é provisório.
         Corretor responsável (credenciamento, não é o contato de atendimento): Dawidson Oliveira Anfrisio, CRECI-CE 24199. */ ?>
<footer class="rodape">
  <div class="container rodape-grade">
    <div class="rodape-marca">
      <img class="rodape-logo" src="<?= asset('img/logo-completa.webp') ?>" alt="Ademimóveis - Corretora de Imóveis" width="150" height="102" loading="lazy">
      <p>Imóveis para comprar e alugar, direto com quem anuncia.</p>
      <div class="rodape-corretor">
        <small>Corretor responsável</small>
        <strong>Dawidson Oliveira Anfrisio</strong>
        <span>Corretor de Imóveis</span>
        <span class="rodape-creci">CRECI-CE 24199</span>
      </div>
      <a class="selo-seguro" href="<?= url('privacidade.php#seguranca') ?>" title="Veja como protegemos sua conta e seus dados">
        <svg class="selo-seguro-escudo" width="34" height="40" viewBox="0 0 34 40" aria-hidden="true">
          <defs><linearGradient id="seloOuro" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f3bf62"/><stop offset="1" stop-color="#c4621c"/></linearGradient></defs>
          <path d="M17 1.5 31.5 7v11.2c0 9.6-6.2 16.6-14.5 20.3C8.7 34.8 2.5 27.8 2.5 18.2V7L17 1.5Z" fill="url(#seloOuro)"/>
          <path d="M17 5 28 9.2v9c0 7.6-4.8 13.3-11 16.4-6.2-3.1-11-8.8-11-16.4v-9L17 5Z" fill="#2a1a12"/>
          <rect x="11" y="17.5" width="12" height="9.5" rx="2" fill="#e9a23b"/>
          <path d="M13.5 17.5v-2.6a3.5 3.5 0 0 1 7 0v2.6" fill="none" stroke="#e9a23b" stroke-width="2.2"/>
          <circle cx="17" cy="21.6" r="1.4" fill="#2a1a12"/><path d="M17 22.4v2.2" stroke="#2a1a12" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span><strong>Site seguro</strong><small>Conexão criptografada · LGPD</small></span>
      </a>
    </div>
    <nav class="rodape-col" aria-label="Imóveis">
      <h4>Imóveis</h4>
      <a href="<?= url('imoveis.php?finalidade=venda') ?>">Comprar</a>
      <a href="<?= url('imoveis.php?finalidade=aluguel') ?>">Alugar</a>
      <a href="<?= url('imoveis.php?finalidade=temporada') ?>">Temporada</a>
    </nav>
    <nav class="rodape-col" aria-label="Para anunciantes">
      <h4>Anunciantes</h4>
      <a href="<?= url('anunciar.php') ?>">Anunciar imóvel</a>
      <a href="<?= url('anunciar.php') ?>">Planos</a>
      <a href="<?= url('termos.php') ?>">Termos de uso</a>
      <a href="<?= url('privacidade.php') ?>">Privacidade e segurança</a>
    </nav>
    <div class="rodape-col">
      <h4>Atendimento</h4>
      <span class="rodape-contato-nome">Samuel Venâncio</span>
      <a href="tel:+5588982237038"><?= icone('telefone', 16) ?>(88) 98223-7038</a>
      <a href="mailto:contato@ademimoveis.com.br"><?= icone('email', 16) ?>contato@ademimoveis.com.br</a>
      <a class="rodape-whatsapp" href="<?= e(link_whatsapp('88982237038', 'Olá, Samuel! Vim pelo site da Ademimóveis e gostaria de atendimento.')) ?>" target="_blank" rel="noopener" aria-label="Falar com Samuel Venâncio no WhatsApp">
        <?= icone('whatsapp', 20) ?>Falar no WhatsApp
      </a>
    </div>
  </div>
  <div class="container rodape-base">
    <span>&copy; <?= date('Y') ?> Ademimóveis. Todos os direitos reservados. Corretor responsável: Dawidson Oliveira Anfrisio, CRECI-CE 24199.</span>
    <span class="rodape-creditos">Desenvolvido por <strong>BV Tecnologias</strong></span>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($scripts)) foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
