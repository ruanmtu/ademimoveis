<?php
/** @var array $im  linha de imoveis (com 'capa' opcional) */
$capa = $im['capa'] ?? capa_imovel((int) $im['id']);
?>
<a class="card-imovel" href="<?= url('imovel.php?id=' . (int) $im['id']) ?>">
  <div class="card-foto">
    <img src="<?= e(upload_url($capa)) ?>" alt="<?= e($im['titulo']) ?>" loading="lazy">
    <span class="etiqueta etiqueta-<?= e($im['finalidade']) ?>"><?= e(finalidades()[$im['finalidade']] ?? '') ?></span>
  </div>
  <div class="card-corpo">
    <div class="card-preco"><?= dinheiro($im['preco']) ?><?= $im['finalidade'] !== 'venda' ? '<small>/mês</small>' : '' ?></div>
    <h3><?= e($im['titulo']) ?></h3>
    <p class="card-local"><?= e(trim(($im['bairro'] ? $im['bairro'] . ', ' : '') . $im['cidade'] . ' - ' . $im['estado'])) ?></p>
    <ul class="card-info">
      <?php if ($im['area']): ?><li><?= e(rtrim(rtrim(number_format((float) $im['area'], 2, ',', '.'), '0'), ',')) ?> m²</li><?php endif; ?>
      <?php if ($im['quartos']): ?><li><?= (int) $im['quartos'] ?> quarto<?= $im['quartos'] > 1 ? 's' : '' ?></li><?php endif; ?>
      <?php if ($im['banheiros']): ?><li><?= (int) $im['banheiros'] ?> banh.</li><?php endif; ?>
      <?php if ($im['vagas']): ?><li><?= (int) $im['vagas'] ?> vaga<?= $im['vagas'] > 1 ? 's' : '' ?></li><?php endif; ?>
    </ul>
  </div>
</a>
