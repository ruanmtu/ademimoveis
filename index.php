<?php
require __DIR__ . '/includes/bootstrap.php';

$destaques = all("SELECT i.*, (SELECT arquivo FROM imovel_fotos f WHERE f.imovel_id = i.id ORDER BY ordem, id LIMIT 1) AS capa
                  FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id
                  WHERE " . sql_imovel_visivel() . "
                  ORDER BY i.destaque DESC, i.criado_em DESC LIMIT 8");

$cidades = all("SELECT i.cidade, i.estado, COUNT(*) AS total
                FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id
                WHERE " . sql_imovel_visivel() . "
                GROUP BY i.cidade, i.estado ORDER BY total DESC LIMIT 6");

// Sugestões de cidade/bairro para o campo de busca
$locais = [];
foreach (all("SELECT DISTINCT i.cidade, i.bairro FROM imoveis i JOIN usuarios u ON u.id = i.anunciante_id
              WHERE " . sql_imovel_visivel() . " LIMIT 300") as $l) {
    $locais[$l['cidade']] = true;
    if ($l['bairro']) $locais[$l['bairro']] = true;
}
$locais = array_keys($locais);
sort($locais);

// Atalhos de busca prontos: [título, subtítulo, ícone, filtros]
$buscasPopulares = [
    ['Casas com 2 quartos', 'à venda', 'casa', ['finalidade' => 'venda', 'tipo' => 'casa', 'quartos' => 2]],
    ['Casas com 3 quartos', 'à venda', 'casa', ['finalidade' => 'venda', 'tipo' => 'casa', 'quartos' => 3]],
    ['Apartamentos com 2 quartos', 'à venda', 'predio', ['finalidade' => 'venda', 'tipo' => 'apartamento', 'quartos' => 2]],
    ['Apartamentos para alugar', 'todos os tamanhos', 'chave', ['finalidade' => 'aluguel', 'tipo' => 'apartamento']],
    ['Casas para alugar', 'todos os tamanhos', 'chave', ['finalidade' => 'aluguel', 'tipo' => 'casa']],
    ['Aluguel até R$ 1.500', 'casas e apartamentos', 'dinheiro', ['finalidade' => 'aluguel', 'preco_max' => 1500]],
    ['Casas com piscina', 'à venda', 'piscina', ['finalidade' => 'venda', 'tipo' => 'casa', 'comodidades' => ['piscina']]],
    ['Aceitam pets', 'para alugar', 'pets', ['finalidade' => 'aluguel', 'comodidades' => ['aceita_pets']]],
    ['Terrenos', 'à venda', 'terreno', ['finalidade' => 'venda', 'tipo' => 'terreno']],
    ['Imóveis comerciais', 'salas, lojas e galpões', 'loja', ['tipo' => 'comercial']],
    ['Com garagem para 2 carros', 'à venda', 'carro', ['finalidade' => 'venda', 'vagas' => 2]],
    ['Temporada', 'casas de praia e campo', 'quintal', ['finalidade' => 'temporada']],
];

// Foto de fundo opcional: coloque um arquivo em assets/img/hero.jpg
$heroFoto = is_file(ROOT_PATH . '/assets/img/hero.jpg') ? asset('img/hero.jpg') : null;

require __DIR__ . '/includes/header.php';
?>
<section class="container hero-wrap">
  <div class="hero"<?= $heroFoto ? ' style="--hero-foto: url(\'' . e($heroFoto) . '\')"' : '' ?>>
    <div class="hero-conteudo">
      <h1>Imóveis à venda e para alugar<br><span>Encontre seu lar na ADemóveis</span></h1>
      <p class="slogan">Realizando seus sonhos!</p>

      <form class="painel-busca" action="<?= url('imoveis.php') ?>" method="get">
        <div class="abas-finalidade" role="radiogroup" aria-label="O que você procura?">
          <label><input type="radio" name="finalidade" value="venda" checked><span>Comprar</span></label>
          <label><input type="radio" name="finalidade" value="aluguel"><span>Alugar</span></label>
          <label><input type="radio" name="finalidade" value="temporada"><span>Temporada</span></label>
        </div>

        <div class="busca-campos">
          <label class="campo campo-local">
            <?= icone('local', 20) ?>
            <input type="text" name="local" placeholder="Cidade ou bairro" aria-label="Cidade ou bairro" list="lista-locais" autocomplete="off">
          </label>
          <label class="campo">
            <?= icone('casa', 20) ?>
            <select name="tipo" aria-label="Tipo de imóvel">
              <option value="">Todos os imóveis</option>
              <?php foreach (tipos_imovel() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="campo">
            <?= icone('cama', 20) ?>
            <select name="quartos" aria-label="Quartos">
              <option value="">Quartos</option>
              <?php for ($q = 1; $q <= 4; $q++): ?><option value="<?= $q ?>"><?= $q ?>+ quarto<?= $q > 1 ? 's' : '' ?></option><?php endfor; ?>
            </select>
          </label>
          <button class="btn btn-buscar" type="submit"><?= icone('busca', 20) ?> Buscar</button>
          <button class="btn-filtros" type="button" data-alternar="#mais-filtros" aria-expanded="false" aria-controls="mais-filtros" title="Mais filtros">
            <?= icone('filtros', 20) ?><span>Filtros</span>
          </button>
        </div>

        <div class="mais-filtros" id="mais-filtros" hidden>
          <div class="mais-filtros-linha">
            <label>Preço mínimo <input type="text" inputmode="decimal" name="preco_min" placeholder="R$ 0"></label>
            <label>Preço máximo <input type="text" inputmode="decimal" name="preco_max" placeholder="Sem limite"></label>
            <label>Banheiros
              <select name="banheiros"><option value="">Qualquer</option><?php for ($q = 1; $q <= 4; $q++): ?><option value="<?= $q ?>"><?= $q ?>+</option><?php endfor; ?></select>
            </label>
            <label>Vagas
              <select name="vagas"><option value="">Qualquer</option><?php for ($q = 1; $q <= 4; $q++): ?><option value="<?= $q ?>"><?= $q ?>+</option><?php endfor; ?></select>
            </label>
          </div>
          <p class="mais-filtros-titulo">Comodidades</p>
          <div class="chips-check">
            <?php foreach (comodidades() as $k => [$rot, $ic]): ?>
              <label><input type="checkbox" name="comodidades[]" value="<?= e($k) ?>"><span><?= icone($ic, 18) ?><?= e($rot) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <datalist id="lista-locais">
          <?php foreach ($locais as $l): ?><option value="<?= e($l) ?>"><?php endforeach; ?>
        </datalist>
      </form>
    </div>
  </div>

  <nav class="atalhos-comodidades" aria-label="Buscar por comodidade">
    <?php foreach (comodidades() as $k => [$rot, $ic]): ?>
      <a href="<?= url('imoveis.php?comodidades[]=' . urlencode($k)) ?>"><?= icone($ic, 24) ?><span><?= e($rot) ?></span></a>
    <?php endforeach; ?>
  </nav>
</section>

<section class="container secao buscas-populares">
  <h2>Buscas populares</h2>
  <div class="grade-buscas">
    <?php foreach ($buscasPopulares as [$rot, $sub, $ic, $q]): ?>
      <a class="busca-pronta" href="<?= url('imoveis.php?' . http_build_query($q)) ?>">
        <span class="busca-pronta-icone"><?= icone($ic, 24) ?></span>
        <span><strong><?= e($rot) ?></strong><small><?= e($sub) ?></small></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="container secao">
  <div class="secao-titulo">
    <h2>Imóveis recentes</h2>
    <a href="<?= url('imoveis.php') ?>">Ver todos →</a>
  </div>
  <?php if ($destaques): ?>
    <div class="grade-imoveis">
      <?php foreach ($destaques as $im) include __DIR__ . '/includes/card_imovel.php'; ?>
    </div>
  <?php else: ?>
    <p class="vazio">Ainda não há imóveis anunciados. <a href="<?= url('cadastro.php?tipo=anunciante') ?>">Seja o primeiro a anunciar!</a></p>
  <?php endif; ?>
</section>

<?php if ($cidades): ?>
<section class="container secao">
  <h2>Cidades com mais imóveis</h2>
  <div class="chips">
    <?php foreach ($cidades as $c): ?>
      <a class="chip" href="<?= url('imoveis.php?local=' . urlencode($c['cidade'])) ?>"><?= e($c['cidade']) ?> - <?= e($c['estado']) ?> <small>(<?= (int) $c['total'] ?>)</small></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="cta-anunciante">
  <div class="container cta-inner">
    <div>
      <h2>Você é corretor ou proprietário?</h2>
      <p>Anuncie seus imóveis, receba mensagens de interessados e construa sua reputação com avaliações.</p>
    </div>
    <a class="btn btn-grande" href="<?= url('cadastro.php?tipo=anunciante') ?>">Quero anunciar</a>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
