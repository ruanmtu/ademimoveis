<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acao = post('acao');

    if ($acao === 'excluir') {
        if ((int) val('SELECT COUNT(*) FROM usuarios WHERE plano_id = ?', [$id]) > 0) {
            flash('erro', 'Há anunciantes neste plano. Desative-o (desmarque "Disponível") em vez de excluir.');
        } else {
            q('DELETE FROM planos WHERE id = ?', [$id]);
            flash('sucesso', 'Plano excluído.');
        }
        redirect('admin/planos.php');
    }

    $nome = post('nome');
    $preco = num_or_null(post('preco'));
    $per = (int) post('periodicidade_meses');
    if ($nome === '' || $preco === null || $preco < 1) {
        flash('erro', 'Informe o nome e um preço de pelo menos R$ 1,00 (mínimo aceito pelo Mercado Pago).');
        redirect('admin/planos.php' . ($id ? '?editar=' . $id : '?novo=1'));
    }
    if (!isset(periodicidades()[$per])) $per = 1;

    $dados = [
        mb_substr($nome, 0, 80), mb_substr(post('descricao'), 0, 255) ?: null, $preco, $per,
        max(0, (int) post('limite_imoveis')), max(1, (int) post('limite_fotos')), max(0, (int) post('destaques')),
        mb_substr(post('recursos'), 0, 2000) ?: null, post('recomendado') ? 1 : 0, (int) post('ordem'), post('ativo') ? 1 : 0,
    ];
    if (post('recomendado')) q('UPDATE planos SET recomendado = 0'); // só um plano "recomendado"
    if ($id) {
        $antes = one('SELECT * FROM planos WHERE id = ?', [$id]);
        q('UPDATE planos SET nome=?, descricao=?, preco=?, periodicidade_meses=?, limite_imoveis=?, limite_fotos=?, destaques=?,
           recursos=?, recomendado=?, ordem=?, ativo=? WHERE id = ?', [...$dados, $id]);
        $msg = 'Plano atualizado.';
        if ($antes && ((float) $antes['preco'] !== $preco || (int) $antes['periodicidade_meses'] !== $per)) {
            $msg .= ' O novo valor vale para novas assinaturas. Quem já assina continua pagando o valor contratado até trocar de plano.';
        }
        flash('sucesso', $msg);
    } else {
        q('INSERT INTO planos (nome, descricao, preco, periodicidade_meses, limite_imoveis, limite_fotos, destaques, recursos,
           recomendado, ordem, ativo, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [...$dados, agora()]);
        flash('sucesso', 'Plano criado.');
    }
    redirect('admin/planos.php');
}

$planos = all("SELECT p.*,
                 (SELECT COUNT(*) FROM usuarios u WHERE u.plano_id = p.id AND u.tipo = 'anunciante') AS anunciantes,
                 (SELECT COUNT(*) FROM usuarios u WHERE u.plano_id = p.id AND " . sql_anunciante_em_dia('u') . ") AS em_dia
               FROM planos p ORDER BY p.ordem, p.preco");

$editar = null;
if (get('editar')) $editar = one('SELECT * FROM planos WHERE id = ?', [(int) get('editar')]);
$mostrarForm = $editar || get('novo');
$f = $editar ?? ['id' => 0, 'nome' => '', 'descricao' => '', 'preco' => '', 'periodicidade_meses' => 1, 'limite_imoveis' => 0,
                 'limite_fotos' => 20, 'destaques' => 0, 'recursos' => '', 'recomendado' => 0, 'ordem' => count($planos) + 1, 'ativo' => 1];

$titulo = 'Planos';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <div class="secao-titulo">
    <h1>Planos dos anunciantes</h1>
    <?php if (!$mostrarForm): ?><a class="btn" href="?novo=1">+ Novo plano</a><?php endif; ?>
  </div>
  <?php require __DIR__ . '/_nav.php'; ?>

  <?php if ($mostrarForm): ?>
    <form method="post" class="caixa form-plano">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <h2><?= $editar ? 'Editar plano: ' . e($editar['nome']) : 'Novo plano' ?></h2>
      <div class="linha-3">
        <label>Nome do plano <input type="text" name="nome" value="<?= e($f['nome']) ?>" required maxlength="80" placeholder="Ex.: Profissional"></label>
        <label>Valor (R$) <input type="text" inputmode="decimal" name="preco" required
               value="<?= $f['preco'] !== '' ? e(number_format((float) $f['preco'], 2, ',', '.')) : '' ?>" placeholder="89,90"></label>
        <label>Cobrança
          <select name="periodicidade_meses">
            <?php foreach (periodicidades() as $m => $rot): ?><option value="<?= $m ?>" <?= (int) $f['periodicidade_meses'] === $m ? 'selected' : '' ?>><?= $rot ?> (a cada <?= $m ?> <?= $m > 1 ? 'meses' : 'mês' ?>)</option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <label>Descrição curta <input type="text" name="descricao" value="<?= e($f['descricao']) ?>" maxlength="255" placeholder="Ex.: Para corretores ativos"></label>
      <div class="linha-3">
        <label>Limite de imóveis <small>(0 = ilimitado)</small> <input type="number" min="0" name="limite_imoveis" value="<?= (int) $f['limite_imoveis'] ?>"></label>
        <label>Fotos por imóvel <input type="number" min="1" max="100" name="limite_fotos" value="<?= (int) $f['limite_fotos'] ?>"></label>
        <label>Imóveis em destaque <small>(aparecem primeiro na busca)</small> <input type="number" min="0" name="destaques" value="<?= (int) $f['destaques'] ?>"></label>
      </div>
      <label>Benefícios exibidos no plano <small>(um por linha)</small>
        <textarea name="recursos" rows="4" placeholder="Chat com interessados&#10;Perfil com avaliações"><?= e(implode("\n", recursos_plano($f['recursos']))) ?></textarea>
      </label>
      <div class="acoes-linha">
        <label class="check"><input type="checkbox" name="ativo" value="1" <?= $f['ativo'] ? 'checked' : '' ?>> Disponível para novas assinaturas</label>
        <label class="check"><input type="checkbox" name="recomendado" value="1" <?= $f['recomendado'] ? 'checked' : '' ?>> Marcar como "Mais escolhido"</label>
        <label>Ordem de exibição <input type="number" name="ordem" value="<?= (int) $f['ordem'] ?>" style="width:90px"></label>
      </div>
      <div class="acoes-linha">
        <button class="btn"><?= $editar ? 'Salvar alterações' : 'Criar plano' ?></button>
        <a class="btn btn-claro" href="<?= url('admin/planos.php') ?>">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>

  <div class="planos mt">
    <?php foreach ($planos as $p): ?>
      <div class="caixa plano <?= $p['recomendado'] ? 'plano-recomendado' : '' ?> <?= $p['ativo'] ? '' : 'plano-inativo' ?>">
        <?php if ($p['recomendado']): ?><span class="selo-plano">Mais escolhido</span><?php endif; ?>
        <h3><?= e($p['nome']) ?> <?php if (!$p['ativo']): ?><span class="status status-pausado">indisponível</span><?php endif; ?></h3>
        <div class="preco-grande"><?= dinheiro($p['preco']) ?><small><?= sufixo_periodo((int) $p['periodicidade_meses']) ?></small></div>
        <?php if ($p['descricao']): ?><p class="dica"><?= e($p['descricao']) ?></p><?php endif; ?>
        <ul>
          <li><?= texto_limite_imoveis($p['limite_imoveis']) ?></li>
          <li><?= (int) $p['limite_fotos'] ?> fotos por imóvel</li>
          <li><?= $p['destaques'] ? (int) $p['destaques'] . ' imóve' . ($p['destaques'] > 1 ? 'is' : 'l') . ' em destaque' : 'Sem destaques' ?></li>
          <?php foreach (recursos_plano($p['recursos']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
        </ul>
        <p class="dica"><strong><?= (int) $p['anunciantes'] ?></strong> anunciante<?= $p['anunciantes'] == 1 ? '' : 's' ?> · <?= (int) $p['em_dia'] ?> em dia</p>
        <form method="post" class="acoes-linha">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <a class="btn btn-sm" href="?editar=<?= (int) $p['id'] ?>">Editar</a>
          <a class="btn btn-sm btn-claro" href="<?= url('admin/usuarios.php?tipo=anunciante&plano=' . (int) $p['id']) ?>">Ver anunciantes</a>
          <button class="btn btn-sm btn-perigo" name="acao" value="excluir" data-confirmar="Excluir o plano <?= e($p['nome']) ?>?">Excluir</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
