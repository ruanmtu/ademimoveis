<?php
require __DIR__ . '/includes/bootstrap.php';

$where = [sql_imovel_visivel()];
$params = [];

$f = [
    'finalidade' => get('finalidade'), 'tipo' => get('tipo'), 'local' => get('local'),
    'preco_min' => get('preco_min'), 'preco_max' => get('preco_max'),
    'quartos' => get('quartos'), 'banheiros' => get('banheiros'), 'vagas' => get('vagas'), 'q' => get('q'),
    'ordem' => get('ordem', 'recentes'),
];
// comodidades: aceita ?comodidades[]=piscina ou ?comodidade=piscina
$comSel = (array) ($_GET['comodidades'] ?? (get('comodidade') !== '' ? [get('comodidade')] : []));
$comSel = array_values(array_intersect(array_filter($comSel, 'is_string'), array_keys(comodidades())));

if (isset(finalidades()[$f['finalidade']])) { $where[] = 'i.finalidade = ?'; $params[] = $f['finalidade']; }
if (isset(tipos_imovel()[$f['tipo']]))      { $where[] = 'i.tipo = ?';       $params[] = $f['tipo']; }
if ($f['local'] !== '') {
    $where[] = '(i.cidade LIKE ? OR i.bairro LIKE ?)';
    $params[] = '%' . $f['local'] . '%';
    $params[] = '%' . $f['local'] . '%';
}
if (($v = num_or_null($f['preco_min'])) !== null) { $where[] = 'i.preco >= ?'; $params[] = $v; }
if (($v = num_or_null($f['preco_max'])) !== null) { $where[] = 'i.preco <= ?'; $params[] = $v; }
if ((int) $f['quartos'] > 0) { $where[] = 'i.quartos >= ?'; $params[] = (int) $f['quartos']; }
if ((int) $f['vagas'] > 0)   { $where[] = 'i.vagas >= ?';   $params[] = (int) $f['vagas']; }
if ((int) $f['banheiros'] > 0) { $where[] = 'i.banheiros >= ?'; $params[] = (int) $f['banheiros']; }
foreach ($comSel as $c) {
    $where[] = 'EXISTS (SELECT 1 FROM imovel_comodidades ic WHERE ic.imovel_id = i.id AND ic.comodidade = ?)';
    $params[] = $c;
}
if ($f['q'] !== '') {
    $where[] = '(i.titulo LIKE ? OR i.descricao LIKE ?)';
    $params[] = '%' . $f['q'] . '%';
    $params[] = '%' . $f['q'] . '%';
}

$ordem = match ($f['ordem']) {
    'menor_preco' => 'i.preco ASC',
    'maior_preco' => 'i.preco DESC',
    'maior_area'  => 'i.area DESC',
    default       => 'i.destaque DESC, i.criado_em DESC',
};

$base = 'FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id WHERE ' . implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) $base", $params);
$pg = paginar($total, 12);
$imoveis = all("SELECT i.*, (SELECT arquivo FROM imovel_fotos fo WHERE fo.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa
                $base ORDER BY $ordem LIMIT {$pg['limite']} OFFSET {$pg['offset']}", $params);

// quantos filtros estão em uso (para o botão "Filtros" no celular)
$nFiltros = count(array_filter([$f['finalidade'], $f['tipo'], $f['local'], $f['preco_min'], $f['preco_max'], $f['quartos'], $f['vagas'], $f['banheiros'], $f['q']], fn ($v) => $v !== '')) + count($comSel);

$titulo = 'Buscar imóveis';
require __DIR__ . '/includes/header.php';
?>
<div class="container busca-layout">
  <aside class="filtros" id="painel-filtros" data-filtros>
    <form method="get">
      <div class="filtros-topo">
        <h3>Filtrar</h3>
        <button type="button" class="filtros-fechar" data-fechar-filtros aria-label="Fechar filtros">✕</button>
      </div>
      <label>Finalidade
        <select name="finalidade">
          <option value="">Todas</option>
          <?php foreach (finalidades() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['finalidade'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Tipo
        <select name="tipo">
          <option value="">Todos</option>
          <?php foreach (tipos_imovel() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Cidade ou bairro <input type="text" name="local" value="<?= e($f['local']) ?>"></label>
      <div class="linha-2">
        <label>Preço mín. <input type="text" inputmode="decimal" name="preco_min" value="<?= e($f['preco_min']) ?>" placeholder="R$"></label>
        <label>Preço máx. <input type="text" inputmode="decimal" name="preco_max" value="<?= e($f['preco_max']) ?>" placeholder="R$"></label>
      </div>
      <div class="linha-2">
        <label>Quartos
          <select name="quartos"><option value="">Qualquer</option><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= (int) $f['quartos'] === $i ? 'selected' : '' ?>><?= $i ?>+</option><?php endfor; ?></select>
        </label>
        <label>Vagas
          <select name="vagas"><option value="">Qualquer</option><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= (int) $f['vagas'] === $i ? 'selected' : '' ?>><?= $i ?>+</option><?php endfor; ?></select>
        </label>
      </div>
      <label>Banheiros
        <select name="banheiros"><option value="">Qualquer</option><?php for ($i = 1; $i <= 4; $i++): ?><option value="<?= $i ?>" <?= (int) $f['banheiros'] === $i ? 'selected' : '' ?>><?= $i ?>+</option><?php endfor; ?></select>
      </label>
      <fieldset class="filtro-comodidades">
        <legend>Comodidades</legend>
        <?php foreach (comodidades() as $k => [$rot, $ic]): ?>
          <label class="check"><input type="checkbox" name="comodidades[]" value="<?= e($k) ?>" <?= in_array($k, $comSel, true) ? 'checked' : '' ?>> <?= e($rot) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <label>Palavra-chave <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="piscina, varanda..."></label>
      <button class="btn btn-bloco" type="submit">Aplicar filtros</button>
      <a class="limpar" href="<?= url('imoveis.php') ?>">Limpar filtros</a>
    </form>
  </aside>

  <section class="resultados">
    <button type="button" class="btn btn-claro btn-filtros-celular" data-abrir-filtros aria-controls="painel-filtros" aria-expanded="false">
      <?= icone('filtros', 20) ?>Filtros<?php if ($nFiltros): ?> <span class="badge"><?= $nFiltros ?></span><?php endif; ?>
    </button>
    <div class="resultados-topo">
      <p><strong><?= $total ?></strong> imóve<?= $total === 1 ? 'l encontrado' : 'is encontrados' ?></p>
      <form method="get" class="ordem">
        <?php foreach ($f as $k => $v) if ($k !== 'ordem' && $v !== '') echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
        <?php foreach ($comSel as $c) echo '<input type="hidden" name="comodidades[]" value="' . e($c) . '">'; ?>
        <select name="ordem" onchange="this.form.submit()" aria-label="Ordenar">
          <option value="recentes" <?= $f['ordem'] === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
          <option value="menor_preco" <?= $f['ordem'] === 'menor_preco' ? 'selected' : '' ?>>Menor preço</option>
          <option value="maior_preco" <?= $f['ordem'] === 'maior_preco' ? 'selected' : '' ?>>Maior preço</option>
          <option value="maior_area" <?= $f['ordem'] === 'maior_area' ? 'selected' : '' ?>>Maior área</option>
        </select>
      </form>
    </div>
    <?php if ($imoveis): ?>
      <div class="grade-imoveis">
        <?php foreach ($imoveis as $im) include __DIR__ . '/includes/card_imovel.php'; ?>
      </div>
      <?= links_paginacao($pg) ?>
    <?php else: ?>
      <p class="vazio">Nenhum imóvel encontrado com esses filtros.</p>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
