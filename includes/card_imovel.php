<?php
/** @var array $im  linha de imoveis (com 'capa' opcional) */
$capa = $im['capa'] ?? capa_imovel((int) $im['id']);
?>
<a class="card-imovel" href="<?= url('imovel.php?id=' . (int) $im['id']) ?>">
  <div class="card-foto">
    <img src="<?= e(upload_url($capa)) ?>" alt="<?= e($im['titulo']) ?>" loading="lazy">
    <span class="etiqueta etiqueta-<?= e($im['finalidade']) ?>"><?= e(finalidades()[$im['finalidade']] ?? '') ?></span>
    <?php if (!empty($im['destaque'])): ?><span class="etiqueta etiqueta-destaque card-selo">Destaque</span><?php endif; ?>
  </div>
  <div class="card-corpo">
    <div class="card-preco"><?= dinheiro($im['preco']) ?><?= $im['finalidade'] !== 'venda' ? '<small>/mês</small>' : '' ?></div>
    <h3><?= e($im['titulo']) ?></h3>
    <p class="card-local"><?= icone('local', 15) ?><?= e(trim(($im['bairro'] ? $im['bairro'] . ', ' : '') . $im['cidade'] . ' - ' . $im['estado'])) ?></p>
    <ul class="card-info">
      <?php if ($im['area']): ?><li title="Área"><?= icone('area', 16) ?><?= e(rtrim(rtrim(number_format((float) $im['area'], 2, ',', '.'), '0'), ',')) ?> m²</li><?php endif; ?>
      <?php if ($im['quartos']): ?><li title="Quartos"><?= icone('cama', 16) ?><?= (int) $im['quartos'] ?></li><?php endif; ?>
      <?php if ($im['banheiros']): ?><li title="Banheiros"><?= icone('banho', 16) ?><?= (int) $im['banheiros'] ?></li><?php endif; ?>
      <?php if ($im['vagas']): ?><li title="Vagas"><?= icone('carro', 16) ?><?= (int) $im['vagas'] ?></li><?php endif; ?>
    </ul>
  </div>
</a>
