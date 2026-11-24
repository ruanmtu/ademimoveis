<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mercadopago.php';
$admin = exigir_tipo('admin');

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    $alvo = one('SELECT * FROM usuarios WHERE id = ?', [$id]);
    $acao = post('acao');
    $eu = $id === (int) $admin['id'];

    if (!$alvo) {
        flash('erro', 'Usuário não encontrado.');
        redirect('admin/usuarios.php');
    }
    if ($eu && in_array($acao, ['bloquear', 'tipo', 'excluir'], true)) {
        flash('erro', 'Você não pode bloquear, rebaixar ou excluir a própria conta.');
        redirect('admin/usuarios.php?id=' . $id);
    }

    switch ($acao) {
        case 'bloquear':
            q("UPDATE usuarios SET status = 'bloqueado' WHERE id = ?", [$id]);
            flash('sucesso', 'Usuário bloqueado. Os anúncios dele saíram do ar.');
            break;
        case 'desbloquear':
            q("UPDATE usuarios SET status = 'ativo' WHERE id = ?", [$id]);
            flash('sucesso', 'Usuário desbloqueado.');
            break;
        case 'tipo':
            $tipo = post('tipo');
            if (in_array($tipo, ['cliente', 'anunciante', 'admin'], true)) {
                q('UPDATE usuarios SET tipo = ? WHERE id = ?', [$tipo, $id]);
                flash('sucesso', 'Tipo de conta alterado para ' . $tipo . '.');
            }
            break;
        case 'assinatura':
            $st = post('assinatura_status');
            $ate = post('assinatura_ate');
            $plano = (int) post('plano_id') ?: null;
            if (in_array($st, ['inativa', 'pendente', 'ativa', 'cancelada'], true)
                && ($ate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate))) {
                q('UPDATE usuarios SET assinatura_status = ?, assinatura_ate = ? WHERE id = ?', [$st, $ate ?: null, $id]);
                registrar_evento_admin($id, 'assinatura_manual', $st, ['por_admin' => $admin['id'], 'ate' => $ate]);
                flash('sucesso', 'Situação da assinatura atualizada manualmente.');
            } else {
                flash('erro', 'Dados de assinatura inválidos.');
            }
            break;
        case 'plano':
            $novo = one('SELECT * FROM planos WHERE id = ?', [(int) post('plano_id')]);
            if (!$novo) { flash('erro', 'Plano inválido.'); break; }
            q('UPDATE usuarios SET plano_id = ? WHERE id = ?', [$novo['id'], $id]);
            $msg = 'Plano alterado para ' . $novo['nome'] . '. Limites de imóveis, fotos e destaques já valem.';
            if (post('atualizar_mp') && $alvo['mp_preapproval_id'] && mp_configurado()) {
                try {
                    // o Mercado Pago permite trocar o valor; a periodicidade continua a da assinatura original
                    $valor = (float) $novo['preco'];
                    if ($alvo['cupom_id'] && ($alvo['cupom_cobrancas_restantes'] === null || (int) $alvo['cupom_cobrancas_restantes'] > 0)
                        && ($cp = one('SELECT * FROM cupons WHERE id = ?', [$alvo['cupom_id']]))) {
                        $valor = max(1, preco_com_cupom($valor, $cp));
                    }
                    mp_atualizar_valor($alvo['mp_preapproval_id'], $valor);
                    $msg .= ' Cobrança no Mercado Pago atualizada para ' . dinheiro($valor) . '.';
                } catch (RuntimeException $ex) {
                    $msg .= ' Atenção: não foi possível atualizar o valor no Mercado Pago (' . $ex->getMessage() . ').';
                }
            }
            registrar_evento_admin($id, 'plano_alterado', $novo['nome'], ['por_admin' => $admin['id'], 'de' => $alvo['plano_id'], 'para' => $novo['id']]);
            flash('sucesso', $msg);
            break;
        case 'remover_cupom':
            q('UPDATE usuarios SET cupom_id = NULL, cupom_cobrancas_restantes = NULL WHERE id = ?', [$id]);
            flash('sucesso', 'Cupom removido do anunciante (o valor no Mercado Pago não foi alterado).');
            break;
        case 'senha':
            $nova = substr(strtr(base64_encode(random_bytes(9)), '+/', 'xy'), 0, 12);
            q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $id]);
            flash('sucesso', "Senha temporária de {$alvo['email']}: $nova  (passe ao usuário e peça para trocar em Minha conta)");
            break;
        case 'excluir':
            foreach (all('SELECT f.arquivo FROM imovel_fotos f JOIN imoveis i ON i.id = f.imovel_id WHERE i.anunciante_id = ?', [$id]) as $fo) {
                apagar_upload($fo['arquivo']);
            }
            apagar_upload($alvo['foto']);
            q('DELETE FROM usuarios WHERE id = ?', [$id]);
            flash('sucesso', 'Usuário excluído com todos os dados.');
            redirect('admin/usuarios.php');
    }
    redirect('admin/usuarios.php?id=' . $id);
}

function registrar_evento_admin(int $uid, string $tipo, string $status, array $dados): void
{
    q('INSERT INTO pagamentos (usuario_id, tipo, status, payload, criado_em) VALUES (?,?,?,?,?)',
      [$uid, $tipo, $status, json_encode($dados), agora()]);
}

// ---------- Detalhe ----------
$id = (int) get('id');
if ($id) {
    $x = one('SELECT * FROM usuarios WHERE id = ?', [$id]);
    if (!$x) redirect('admin/usuarios.php');
    $planos = all('SELECT * FROM planos ORDER BY preco');
    $imoveis = all("SELECT id, titulo, status, preco FROM imoveis WHERE anunciante_id = ? AND status <> 'removido' ORDER BY id DESC", [$id]);
    $titulo = 'Usuário: ' . $x['nome'];
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="container secao">
      <h1><?= e($x['nome']) ?></h1>
      <?php require __DIR__ . '/_nav.php'; ?>
      <p><a href="<?= url('admin/usuarios.php') ?>">← Voltar para a lista</a></p>
      <div class="duas-colunas">
        <div class="caixa">
          <h2>Dados</h2>
          <dl class="dados">
            <dt>E-mail</dt><dd><?= e($x['email']) ?></dd>
            <dt>Telefone</dt><dd><?= e($x['telefone'] ?: '-') ?></dd>
            <dt>Tipo</dt><dd><?= e($x['tipo']) ?></dd>
            <dt>Situação</dt><dd><span class="status status-<?= e($x['status']) ?>"><?= e($x['status']) ?></span></dd>
            <dt>Cidade</dt><dd><?= e(trim(($x['cidade'] ?? '') . ' ' . ($x['estado'] ?? '')) ?: '-') ?></dd>
            <dt>CRECI</dt><dd><?= e($x['creci'] ?: '-') ?></dd>
            <dt>Cadastro</dt><dd><?= data_br($x['criado_em'], true) ?></dd>
            <dt>Último login</dt><dd><?= data_br($x['ultimo_login'], true) ?></dd>
          </dl>
          <form method="post" class="acoes-linha">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <?php if ($x['status'] === 'ativo'): ?>
              <button class="btn btn-sm btn-claro" name="acao" value="bloquear" data-confirmar="Bloquear este usuário?">Bloquear</button>
            <?php else: ?>
              <button class="btn btn-sm" name="acao" value="desbloquear">Desbloquear</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-claro" name="acao" value="senha" data-confirmar="Gerar uma nova senha temporária?">Gerar senha temporária</button>
            <button class="btn btn-sm btn-perigo" name="acao" value="excluir" data-confirmar="EXCLUIR definitivamente este usuário, imóveis, fotos e conversas? Não tem como desfazer.">Excluir</button>
          </form>
          <form method="post" class="acoes-linha">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="acao" value="tipo">
            <select name="tipo">
              <?php foreach (['cliente', 'anunciante', 'admin'] as $t): ?><option <?= $x['tipo'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-claro" data-confirmar="Alterar o tipo de conta?">Alterar tipo</button>
          </form>
        </div>

        <?php if ($x['tipo'] === 'anunciante'): ?>
        <form method="post" class="caixa">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="acao" value="assinatura">
          <h2>Assinatura</h2>
          <p>Em dia: <strong><?= anunciante_em_dia($x) ? 'Sim' : 'Não' ?></strong>
            <?php if ($x['mp_preapproval_id']): ?><br><small>Mercado Pago: <?= e($x['mp_preapproval_id']) ?></small><?php endif; ?></p>
          <label>Situação
            <select name="assinatura_status">
              <?php foreach (['inativa', 'pendente', 'ativa', 'cancelada'] as $s): ?><option value="<?= $s ?>" <?= $x['assinatura_status'] === $s ? 'selected' : '' ?>><?= status_assinatura_label($s) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Válida até (vazio = sem prazo) <input type="date" name="assinatura_ate" value="<?= e($x['assinatura_ate']) ?>"></label>
          <button class="btn">Salvar situação</button>
          <p class="dica">Use para liberar anunciantes manualmente (ex.: pagamento por fora, cortesia ou período de teste).</p>
        </form>

        <form method="post" class="caixa">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="acao" value="plano">
          <h2>Plano</h2>
          <?php $pa = plano_do_usuario($x); ?>
          <p>Plano atual: <strong><?= e($pa['nome']) ?></strong><?= $pa['id'] ? ' · ' . dinheiro($pa['preco']) . sufixo_periodo((int) $pa['periodicidade_meses']) : '' ?></p>
          <?php if ($x['cupom_id'] && ($cx = one('SELECT * FROM cupons WHERE id = ?', [$x['cupom_id']]))): ?>
            <p class="dica">Cupom em uso: <strong class="codigo-cupom"><?= e($cx['codigo']) ?></strong> (<?= e(desconto_texto($cx)) ?><?= $x['cupom_cobrancas_restantes'] !== null ? ', faltam ' . (int) $x['cupom_cobrancas_restantes'] . ' cobrança(s)' : '' ?>)
              <button class="btn btn-sm btn-claro" name="acao" value="remover_cupom" formnovalidate data-confirmar="Remover o cupom deste anunciante?">Remover cupom</button></p>
          <?php endif; ?>
          <label>Mudar para
            <select name="plano_id" required>
              <?php foreach ($planos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $x['plano_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?> — <?= dinheiro($p['preco']) . sufixo_periodo((int) $p['periodicidade_meses']) ?><?= $p['ativo'] ? '' : ' (indisponível)' ?></option><?php endforeach; ?>
            </select>
          </label>
          <?php if ($x['mp_preapproval_id']): ?>
            <label class="check"><input type="checkbox" name="atualizar_mp" value="1" checked> Atualizar também o valor cobrado no Mercado Pago</label>
          <?php endif; ?>
          <button class="btn">Alterar plano</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($imoveis): ?>
        <div class="caixa mt">
          <h2>Imóveis</h2>
          <table class="tabela"><tbody>
            <?php foreach ($imoveis as $im): ?>
              <tr><td><a href="<?= url('imovel.php?id=' . (int) $im['id']) ?>"><?= e($im['titulo']) ?></a></td><td><?= dinheiro($im['preco']) ?></td><td><span class="status status-<?= e($im['status']) ?>"><?= e($im['status']) ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ---------- Lista ----------
$where = ['1=1'];
$params = [];
if (($busca = get('q')) !== '') {
    $where[] = '(nome LIKE ? OR email LIKE ? OR telefone LIKE ?)';
    array_push($params, "%$busca%", "%$busca%", "%$busca%");
}
if (in_array($tipo = get('tipo'), ['cliente', 'anunciante', 'admin'], true)) { $where[] = 'tipo = ?'; $params[] = $tipo; }
if (in_array($st = get('status'), ['ativo', 'bloqueado'], true)) { $where[] = 'status = ?'; $params[] = $st; }
if ((int) get('plano') > 0) { $where[] = 'plano_id = ?'; $params[] = (int) get('plano'); }

$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM usuarios WHERE $w", $params);
$pg = paginar($total, 25);
$lista = all("SELECT * FROM usuarios WHERE $w ORDER BY id DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}", $params);

$titulo = 'Usuários';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Usuários <small>(<?= $total ?>)</small></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <form class="filtro-linha" method="get">
    <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Nome, e-mail ou telefone">
    <select name="tipo"><option value="">Todos os tipos</option><?php foreach (['cliente', 'anunciante', 'admin'] as $t): ?><option <?= get('tipo') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">Qualquer situação</option><option <?= get('status') === 'ativo' ? 'selected' : '' ?>>ativo</option><option <?= get('status') === 'bloqueado' ? 'selected' : '' ?>>bloqueado</option></select>
    <?php if ((int) get('plano')): ?><input type="hidden" name="plano" value="<?= (int) get('plano') ?>"><?php endif; ?>
    <button class="btn btn-sm">Filtrar</button>
  </form>
  <?php if ((int) get('plano')): ?><p class="dica">Mostrando anunciantes do plano <strong><?= e(val('SELECT nome FROM planos WHERE id = ?', [(int) get('plano')])) ?></strong> · <a href="?tipo=anunciante">ver todos</a></p><?php endif; ?>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Nome</th><th>Tipo</th><th>Situação</th><th>Assinatura</th><th>Cadastro</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $x): ?>
      <tr>
        <td><a href="?id=<?= (int) $x['id'] ?>"><strong><?= e($x['nome']) ?></strong></a><br><small><?= e($x['email']) ?></small></td>
        <td><span class="status status-<?= e($x['tipo']) ?>"><?= e($x['tipo']) ?></span></td>
        <td><span class="status status-<?= e($x['status']) ?>"><?= e($x['status']) ?></span></td>
        <td><?= $x['tipo'] === 'anunciante' ? e(plano_do_usuario($x)['nome']) . ' · ' . e(status_assinatura_label($x['assinatura_status'])) . ($x['assinatura_ate'] ? '<br><small>até ' . data_br($x['assinatura_ate']) . '</small>' : '') : '-' ?></td>
        <td><small><?= data_br($x['criado_em']) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= links_paginacao($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
