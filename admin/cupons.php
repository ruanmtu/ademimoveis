<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');

$planos = all('SELECT id, nome, preco, periodicidade_meses FROM planos ORDER BY ordem, preco');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $acao = post('acao', 'salvar');

    if ($acao === 'alternar') {
        q('UPDATE cupons SET ativo = 1 - ativo WHERE id = ?', [$id]);
        flash('sucesso', 'Situação do cupom alterada.');
        redirect('admin/cupons.php');
    }
    if ($acao === 'excluir') {
        if ((int) val('SELECT COUNT(*) FROM cupom_usos WHERE cupom_id = ?', [$id]) > 0) {
            flash('erro', 'Este cupom já foi usado. Desative-o em vez de excluir, para manter o histórico.');
        } else {
            q('DELETE FROM cupons WHERE id = ?', [$id]);
            flash('sucesso', 'Cupom excluído.');
        }
        redirect('admin/cupons.php');
    }

    $codigo = normalizar_codigo(post('codigo'));
    $tipo = post('tipo') === 'valor' ? 'valor' : 'percentual';
    $valor = num_or_null(post('valor'));
    $duracao = in_array(post('duracao'), ['primeira', 'cobrancas', 'sempre'], true) ? post('duracao') : 'primeira';
    $nCob = $duracao === 'cobrancas' ? max(2, (int) post('duracao_cobrancas')) : null;
    $planoId = (int) post('plano_id') ?: null;
    $maxUsos = post('max_usos') !== '' ? max(1, (int) post('max_usos')) : null;
    $validoAte = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('valido_ate')) ? post('valido_ate') : null;

    $erros = [];
    if (strlen($codigo) < 3) $erros[] = 'O código precisa ter pelo menos 3 letras ou números.';
    if ($valor === null || $valor <= 0) $erros[] = 'Informe o valor do desconto.';
    if ($tipo === 'percentual' && $valor > 100) $erros[] = 'O desconto percentual vai até 100%.';
    if (val('SELECT 1 FROM cupons WHERE codigo = ? AND id <> ?', [$codigo, $id])) $erros[] = 'Já existe um cupom com esse código.';

    if ($erros) {
        flash('erro', implode(' ', $erros));
        $_SESSION['cupom_form'] = $_POST;
        redirect('admin/cupons.php' . ($id ? '?editar=' . $id : '?novo=1'));
    }

    $dados = [$codigo, mb_substr(post('descricao'), 0, 255) ?: null, $tipo, $valor, $duracao, $nCob, $planoId, $maxUsos, $validoAte, post('ativo') ? 1 : 0];
    if ($id) {
        q('UPDATE cupons SET codigo=?, descricao=?, tipo=?, valor=?, duracao=?, duracao_cobrancas=?, plano_id=?, max_usos=?, valido_ate=?, ativo=? WHERE id = ?', [...$dados, $id]);
        flash('sucesso', "Cupom $codigo atualizado. Mudanças valem para novos usos; quem já usou mantém o desconto contratado.");
    } else {
        q('INSERT INTO cupons (codigo, descricao, tipo, valor, duracao, duracao_cobrancas, plano_id, max_usos, valido_ate, ativo, criado_em)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)', [...$dados, agora()]);
        flash('sucesso', "Cupom $codigo criado.");
    }
    redirect('admin/cupons.php');
}

// ----- Detalhe de usos -----
$verUsos = (int) get('usos');
$usos = $verUsos ? all('SELECT cu.*, u.nome, u.email, p.nome AS plano FROM cupom_usos cu JOIN usuarios u ON u.id = cu.usuario_id
                        LEFT JOIN planos p ON p.id = cu.plano_id WHERE cu.cupom_id = ? ORDER BY cu.id DESC', [$verUsos]) : [];

$cupons = all('SELECT c.*, p.nome AS plano FROM cupons c LEFT JOIN planos p ON p.id = c.plano_id ORDER BY c.ativo DESC, c.id DESC');
$editar = get('editar') ? one('SELECT * FROM cupons WHERE id = ?', [(int) get('editar')]) : null;
$mostrarForm = $editar || get('novo');
$padrao = ['id' => 0, 'codigo' => '', 'descricao' => '', 'tipo' => 'percentual', 'valor' => '', 'duracao' => 'primeira',
           'duracao_cobrancas' => 3, 'plano_id' => '', 'max_usos' => '', 'valido_ate' => '', 'ativo' => 1];
$f = array_merge($padrao, $editar ?? [], is_array($_SESSION['cupom_form'] ?? null) ? $_SESSION['cupom_form'] : []);
unset($_SESSION['cupom_form']);

$titulo = 'Cupons de desconto';
$scripts = ['js/cupons.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <div class="secao-titulo">
    <h1>Cupons de desconto</h1>
    <?php if (!$mostrarForm): ?><a class="btn" href="?novo=1">+ Novo cupom</a><?php endif; ?>
  </div>
  <?php require __DIR__ . '/_nav.php'; ?>

  <?php if ($mostrarForm): ?>
    <form method="post" class="caixa form-cupom" data-form-cupom>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($f['id'] ?? 0) ?>">
      <h2><?= $editar ? 'Editar cupom ' . e($editar['codigo']) : 'Novo cupom' ?></h2>
      <div class="linha-3">
        <label>Código <small>(o anunciante digita)</small>
          <input type="text" name="codigo" value="<?= e($f['codigo']) ?>" required maxlength="40" placeholder="BEMVINDO50" style="text-transform:uppercase">
        </label>
        <label>Tipo de desconto
          <select name="tipo" data-tipo>
            <option value="percentual" <?= $f['tipo'] === 'percentual' ? 'selected' : '' ?>>Porcentagem (%)</option>
            <option value="valor" <?= $f['tipo'] === 'valor' ? 'selected' : '' ?>>Valor fixo (R$)</option>
          </select>
        </label>
        <label>Desconto <span data-unidade><?= $f['tipo'] === 'valor' ? '(R$)' : '(%)' ?></span>
          <input type="text" inputmode="decimal" name="valor" required value="<?= $f['valor'] !== '' ? e(rtrim(rtrim(number_format((float) str_replace(',', '.', (string) $f['valor']), 2, ',', ''), '0'), ',')) : '' ?>" placeholder="50">
        </label>
      </div>
      <div class="linha-3">
        <label>Vale por quanto tempo?
          <select name="duracao" data-duracao>
            <option value="primeira" <?= $f['duracao'] === 'primeira' ? 'selected' : '' ?>>Só na primeira cobrança</option>
            <option value="cobrancas" <?= $f['duracao'] === 'cobrancas' ? 'selected' : '' ?>>Nas primeiras X cobranças</option>
            <option value="sempre" <?= $f['duracao'] === 'sempre' ? 'selected' : '' ?>>Para sempre</option>
          </select>
        </label>
        <label data-n-cobrancas>Quantas cobranças? <input type="number" min="2" max="60" name="duracao_cobrancas" value="<?= (int) ($f['duracao_cobrancas'] ?: 3) ?>"></label>
        <label>Plano
          <select name="plano_id">
            <option value="">Todos os planos</option>
            <?php foreach ($planos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $f['plano_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> (<?= dinheiro($p['preco']) . sufixo_periodo((int) $p['periodicidade_meses']) ?>)</option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="linha-3">
        <label>Limite de usos <small>(vazio = sem limite)</small> <input type="number" min="1" name="max_usos" value="<?= e($f['max_usos']) ?>"></label>
        <label>Válido até <small>(vazio = sem prazo)</small> <input type="date" name="valido_ate" value="<?= e($f['valido_ate']) ?>"></label>
        <label>Observação interna <input type="text" name="descricao" value="<?= e($f['descricao']) ?>" maxlength="255" placeholder="Campanha de lançamento"></label>
      </div>
      <label class="check"><input type="checkbox" name="ativo" value="1" <?= $f['ativo'] ? 'checked' : '' ?>> Cupom ativo</label>
      <p class="dica" data-resumo-cupom></p>
      <p class="dica">Cada anunciante pode usar o mesmo cupom só uma vez. Um cupom de 100% libera o período grátis na hora, sem passar pelo Mercado Pago.</p>
      <div class="acoes-linha">
        <button class="btn"><?= $editar ? 'Salvar cupom' : 'Criar cupom' ?></button>
        <a class="btn btn-claro" href="<?= url('admin/cupons.php') ?>">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($verUsos): ?>
    <div class="caixa mt">
      <h2>Quem usou o cupom <?= e(val('SELECT codigo FROM cupons WHERE id = ?', [$verUsos])) ?></h2>
      <?php if ($usos): ?>
        <table class="tabela"><thead><tr><th>Anunciante</th><th>Plano</th><th>Valor normal</th><th>Com desconto</th><th>Data</th></tr></thead><tbody>
          <?php foreach ($usos as $x): ?>
            <tr><td><a href="<?= url('admin/usuarios.php?id=' . (int) $x['usuario_id']) ?>"><?= e($x['nome']) ?></a><br><small><?= e($x['email']) ?></small></td>
                <td><?= e($x['plano'] ?? '-') ?></td><td><?= dinheiro($x['valor_original']) ?></td><td><?= dinheiro($x['valor_com_desconto']) ?></td><td><small><?= data_br($x['criado_em'], true) ?></small></td></tr>
          <?php endforeach; ?>
        </tbody></table>
      <?php else: ?><p class="vazio">Ninguém usou este cupom ainda.</p><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tabela-wrap mt">
    <table class="tabela">
      <thead><tr><th>Código</th><th>Desconto</th><th>Duração</th><th>Plano</th><th>Usos</th><th>Validade</th><th>Situação</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cupons as $c):
          $expirado = $c['valido_ate'] && $c['valido_ate'] < hoje();
          $esgotado = $c['max_usos'] !== null && (int) $c['usos'] >= (int) $c['max_usos']; ?>
        <tr>
          <td><strong class="codigo-cupom"><?= e($c['codigo']) ?></strong><?php if ($c['descricao']): ?><br><small><?= e($c['descricao']) ?></small><?php endif; ?></td>
          <td><?= e(desconto_texto($c)) ?></td>
          <td><?= e(ucfirst(duracao_cupom_texto($c))) ?></td>
          <td><?= e($c['plano'] ?? 'Todos') ?></td>
          <td><a href="?usos=<?= (int) $c['id'] ?>"><?= (int) $c['usos'] ?><?= $c['max_usos'] !== null ? ' / ' . (int) $c['max_usos'] : '' ?></a></td>
          <td><?= $c['valido_ate'] ? data_br($c['valido_ate']) : 'Sem prazo' ?></td>
          <td><?php if (!$c['ativo']): ?><span class="status status-pausado">inativo</span>
              <?php elseif ($expirado): ?><span class="status status-removido">expirado</span>
              <?php elseif ($esgotado): ?><span class="status status-removido">esgotado</span>
              <?php else: ?><span class="status status-ativo">ativo</span><?php endif; ?></td>
          <td class="acoes">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <a class="btn btn-sm btn-claro" href="?editar=<?= (int) $c['id'] ?>">Editar</a>
              <button class="btn btn-sm btn-claro" name="acao" value="alternar"><?= $c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
              <button class="btn btn-sm btn-perigo" name="acao" value="excluir" data-confirmar="Excluir o cupom <?= e($c['codigo']) ?>?">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cupons): ?><tr><td colspan="8" class="vazio">Nenhum cupom criado ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
