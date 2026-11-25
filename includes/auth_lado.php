<?php
/**
 * Lado da marca nas telas de login e cadastro (inspirado no padrão BV).
 * @var string $ladoTitulo
 * @var string $ladoTexto
 * @var array  $ladoItens  [[ícone, título, texto], ...]
 */
?>
<section class="auth-marca" aria-hidden="false">
  <span class="auth-chamine" aria-hidden="true"></span>
  <div class="auth-telhado" aria-hidden="true"></div>
  <div class="auth-marca-conteudo">
    <img class="auth-logo" src="<?= asset('img/logo-completa.webp') ?>" alt="Ademóveis - Corretora de Imóveis" width="220" height="152">
    <h2><?= e($ladoTitulo) ?></h2>
    <p><?= e($ladoTexto) ?></p>
    <ul class="auth-beneficios">
      <?php foreach ($ladoItens as [$ic, $tit, $txt]): ?>
        <li>
          <span class="auth-beneficio-icone"><?= icone($ic, 22) ?></span>
          <span><strong><?= e($tit) ?></strong><?= e($txt) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
