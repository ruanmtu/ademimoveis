<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/mercadopago.php';
$u = exigir_tipo('anunciante');

// Volta do checkout do Mercado Pago: consulta a assinatura direto na API
if (get('retorno') && mp_configurado() && ($u['mp_preapproval_novo'] || $u['mp_preapproval_id'])) {
    try {
        mp_sincronizar_assinatura(mp_buscar_assinatura($u['mp_preapproval_novo'] ?: $u['mp_preapproval_id']));
    } catch (RuntimeException $ex) {
        error_log('[ADemoveis] ' . $ex->getMessage());
    }
    $u = one('SELECT * FROM usuarios WHERE id = ?', [$u['id']]);
    flash(anunciante_em_dia($u) && !$u['mp_preapproval_novo'] ? 'sucesso' : 'aviso', anunciante_em_dia($u) && !$u['mp_preapproval_novo']
        ? 'Assinatura confirmada! Seu plano já está valendo.'
        : 'Recebemos seu retorno. Assim que o Mercado Pago confirmar o pagamento, seu plano é ativado automaticamente.');
    redirect('painel/assinatura.php');
}

$emDia = anunciante_em_dia($u);
$temMpAtiva = $u['mp_preapproval_id'] && $u['assinatura_status'] === 'ativa';

if (is_post()) {
    csrf_check();
    $acao = post('acao');

    if ($acao === 'assinar') {
        $plano = one('SELECT * FROM planos WHERE id = ? AND ativo = 1', [(int) post('plano_id')]);
        $codigo = normalizar_codigo(post('cupom'));
        try {
            if (!$plano) throw new RuntimeException('Plano inválido.');
            if ($emDia && (int) $u['plano_id'] === (int) $plano['id'] && $u['assinatura_status'] === 'ativa') {
                throw new RuntimeException('Você já está neste plano.');
            }
            // limite de imóveis do novo plano
            $qtd = (int) val("SELECT COUNT(*) FROM imoveis WHERE anunciante_id = ? AND status <> 'removido'", [$u['id']]);
            if ($plano['limite_imoveis'] > 0 && $qtd > $plano['limite_imoveis']) {
                throw new RuntimeException("O plano {$plano['nome']} permite {$plano['limite_imoveis']} imóveis e você tem $qtd. Exclua alguns antes de trocar.");
            }
            $cupom = $codigo !== '' ? validar_cupom($codigo, $plano, $u) : null;
            $valor = $cupom ? preco_com_cupom((float) $plano['preco'], $cupom) : (float) $plano['preco'];

            if ($cupom && $valor < 1) {
                // cupom que zera a cobrança: libera direto, sem Mercado Pago
                if ($temMpAtiva && mp_configurado()) {
                    try { mp_cancelar_assinatura($u['mp_preapproval_id']); } catch (RuntimeException $ex) { error_log('[ADemoveis] ' . $ex->getMessage()); }
                }
                liberar_periodo_gratis($u, $plano, $cupom);
                $u2 = one('SELECT * FROM usuarios WHERE id = ?', [$u['id']]);
                flash('sucesso', 'Cupom aplicado! Plano ' . $plano['nome'] . ' liberado ' . ($u2['assinatura_ate'] ? 'até ' . data_br($u2['assinatura_ate']) : 'sem prazo') . '.');
                redirect('painel/assinatura.php');
            }
            if (!mp_configurado()) {
                throw new RuntimeException('Pagamento online ainda não configurado. Fale com a administração para ativar seu plano.');
            }
            $pre = mp_criar_assinatura($u, $plano, $valor, $cupom);
            if ($temMpAtiva) {
                // troca de plano: a assinatura atual só é cancelada quando a nova for confirmada
                q('UPDATE usuarios SET mp_preapproval_novo = ? WHERE id = ?', [$pre['id'], $u['id']]);
            } else {
                // o plano só muda quando o Mercado Pago confirmar o pagamento
                q('UPDATE usuarios SET mp_preapproval_id = ?, mp_preapproval_novo = NULL, assinatura_status = ? WHERE id = ?',
                  [$pre['id'], $emDia ? $u['assinatura_status'] : 'pendente', $u['id']]);
            }
            registrar_pagamento((int) $u['id'], (string) $pre['id'], 'assinatura_criada', $pre['status'] ?? 'pending', $valor,
                ['plano' => $plano['nome'], 'cupom' => $cupom['codigo'] ?? null]);
            redirect($pre['init_point']);
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
            redirect('painel/assinatura.php' . ($codigo ? '?cupom=' . urlencode($codigo) : ''));
        }
    } elseif ($acao === 'cancelar' && $u['mp_preapproval_id']) {
        try {
            mp_cancelar_assinatura($u['mp_preapproval_id']);
            q("UPDATE usuarios SET assinatura_status = 'cancelada' WHERE id = ?", [$u['id']]);
            flash('sucesso', 'Renovação cancelada. Seus anúncios ficam no ar até ' . data_br($u['assinatura_ate']) . '.');
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
    }
    redirect('painel/assinatura.php');
}

$planos = all('SELECT * FROM planos WHERE ativo = 1 ORDER BY ordem, preco');
$planoAtual = $u['plano_id'] ? plano_do_usuario($u) : null;
$cupomAtual = $u['cupom_id'] ? one('SELECT * FROM cupons WHERE id = ?', [$u['cupom_id']]) : null;

// Pré-visualização do cupom (?cupom=CODIGO)
$codigoCupom = normalizar_codigo(get('cupom'));
$cupomPrevia = null;
$erroCupom = '';
if ($codigoCupom !== '') {
    $c = one('SELECT * FROM cupons WHERE codigo = ?', [$codigoCupom]);
    try {
        if (!$c) throw new RuntimeException('Cupom inválido.');
        // valida contra o plano do cupom (ou o primeiro plano, se valer para todos)
        $pTeste = $c['plano_id'] ? (one('SELECT * FROM planos WHERE id = ?', [$c['plano_id']]) ?? $planos[0] ?? null) : ($planos[0] ?? null);
        if (!$pTeste) throw new RuntimeException('Nenhum plano disponível.');
        $cupomPrevia = validar_cupom($codigoCupom, $pTeste, $u);
    } catch (RuntimeException $ex) {
        $erroCupom = $ex->getMessage();
    }
}

$titulo = 'Assinatura';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Assinatura</h1>
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="caixa status-assinatura <?= $emDia ? 'ok' : 'pendente' ?>">
    <div><span>Situação</span><strong><?= e(status_assinatura_label($u['assinatura_status'])) ?><?= $emDia ? ' ✓' : '' ?></strong></div>
    <?php if ($planoAtual): ?>
      <div><span>Plano</span><strong><?= e($planoAtual['nome']) ?></strong></div>
      <div><span>Limites</span><strong class="limites"><?= $planoAtual['limite_imoveis'] ? (int) $planoAtual['limite_imoveis'] . ' imóveis' : 'Imóveis ilimitados' ?> · <?= (int) $planoAtual['limite_fotos'] ?> fotos · <?= (int) $planoAtual['destaques'] ?> destaques</strong></div>
    <?php endif; ?>
    <div><span>Válida até</span><strong><?= $u['assinatura_status'] === 'ativa' && !$u['assinatura_ate'] ? 'Sem prazo' : data_br($u['assinatura_ate']) ?></strong></div>
    <?php if ($temMpAtiva): ?>
      <form method="post"><?= csrf_field() ?>
        <button class="btn btn-sm btn-perigo" name="acao" value="cancelar" data-confirmar="Cancelar a renovação automática? Seus anúncios continuam no ar até o fim do período pago.">Cancelar renovação</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($cupomAtual): ?>
    <div class="alerta alerta-sucesso">Cupom <strong><?= e($cupomAtual['codigo']) ?></strong> ativo: <?= e(desconto_texto($cupomAtual)) ?><?= $u['cupom_cobrancas_restantes'] !== null ? ' por mais ' . (int) $u['cupom_cobrancas_restantes'] . ' cobrança(s)' : ' em todas as cobranças' ?>.</div>
  <?php endif; ?>
  <?php if ($u['assinatura_status'] === 'pendente'): ?>
    <div class="alerta alerta-aviso">Estamos aguardando a confirmação do pagamento pelo Mercado Pago. Isso costuma levar poucos minutos (boleto pode levar até 3 dias úteis).</div>
  <?php endif; ?>
  <?php if ($u['mp_preapproval_novo']): ?>
    <div class="alerta alerta-aviso">Sua troca de plano está aguardando a confirmação do pagamento. Seu plano atual continua valendo até lá.</div>
  <?php endif; ?>

  <div class="secao-titulo mt">
    <h2><?= $emDia ? 'Trocar de plano' : 'Escolha seu plano' ?></h2>
    <form method="get" class="form-cupom-aplicar">
      <input type="text" name="cupom" value="<?= e($codigoCupom) ?>" placeholder="Tem um cupom?" aria-label="Código do cupom" maxlength="40">
      <button class="btn btn-sm btn-claro">Aplicar</button>
    </form>
  </div>
  <?php if ($erroCupom): ?><div class="alerta alerta-erro"><?= e($erroCupom) ?></div><?php endif; ?>
  <?php if ($cupomPrevia): ?><div class="alerta alerta-sucesso">Cupom <strong><?= e($cupomPrevia['codigo']) ?></strong>: <?= e(desconto_texto($cupomPrevia)) ?><?= $cupomPrevia['plano_id'] ? ' no plano ' . e(val('SELECT nome FROM planos WHERE id = ?', [$cupomPrevia['plano_id']])) : '' ?>. Escolha o plano abaixo.</div><?php endif; ?>

  <div class="planos">
    <?php foreach ($planos as $p):
        $per = (int) $p['periodicidade_meses'];
        $vale = $cupomPrevia && (!$cupomPrevia['plano_id'] || (int) $cupomPrevia['plano_id'] === (int) $p['id']);
        $comDesc = $vale ? preco_com_cupom((float) $p['preco'], $cupomPrevia) : null;
        $atual = $emDia && (int) $u['plano_id'] === (int) $p['id'] && $u['assinatura_status'] === 'ativa'; ?>
      <form method="post" class="caixa plano <?= $p['recomendado'] ? 'plano-recomendado' : '' ?> <?= $atual ? 'plano-atual' : '' ?>">
        <?= csrf_field() ?><input type="hidden" name="plano_id" value="<?= (int) $p['id'] ?>">
        <?php if ($vale): ?><input type="hidden" name="cupom" value="<?= e($cupomPrevia['codigo']) ?>"><?php endif; ?>
        <?php if ($atual): ?><span class="selo-plano selo-atual">Seu plano</span><?php elseif ($p['recomendado']): ?><span class="selo-plano">Mais escolhido</span><?php endif; ?>
        <h3><?= e($p['nome']) ?></h3>
        <?php if ($p['descricao']): ?><p class="dica"><?= e($p['descricao']) ?></p><?php endif; ?>
        <?php if ($comDesc !== null): ?>
          <div class="preco-antigo"><?= dinheiro($p['preco']) . sufixo_periodo($per) ?></div>
          <div class="preco-grande"><?= $comDesc < 1 ? 'Grátis' : dinheiro($comDesc) ?><small><?= $comDesc < 1 ? '' : sufixo_periodo($per) ?></small></div>
          <p class="dica"><?= e(ucfirst(duracao_cupom_texto($cupomPrevia, $per))) ?><?= $cupomPrevia['duracao'] !== 'sempre' && $comDesc >= 1 ? ', depois ' . dinheiro($p['preco']) . sufixo_periodo($per) : '' ?></p>
        <?php else: ?>
          <div class="preco-grande"><?= dinheiro($p['preco']) ?><small><?= sufixo_periodo($per) ?></small></div>
          <?php if ($per > 1): ?><p class="dica">equivale a <?= dinheiro((float) $p['preco'] / $per) ?>/mês</p><?php endif; ?>
        <?php endif; ?>
        <ul>
          <li><?= $p['limite_imoveis'] ? (int) $p['limite_imoveis'] . ' imóveis' : 'Imóveis ilimitados' ?></li>
          <li>Até <?= (int) $p['limite_fotos'] ?> fotos por imóvel</li>
          <?php if ($p['destaques']): ?><li><?= (int) $p['destaques'] ?> imóve<?= $p['destaques'] > 1 ? 'is' : 'l' ?> em destaque</li><?php endif; ?>
          <?php foreach (recursos_plano($p['recursos']) as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
        </ul>
        <?php if ($atual): ?>
          <button class="btn btn-bloco btn-claro" type="button" disabled>Plano atual</button>
        <?php else: ?>
          <button class="btn btn-bloco" name="acao" value="assinar"><?= $comDesc !== null && $comDesc < 1 ? 'Ativar grátis' : ($emDia ? 'Mudar para este plano' : 'Assinar com Mercado Pago') ?></button>
        <?php endif; ?>
      </form>
    <?php endforeach; ?>
  </div>
  <p class="dica">O pagamento é feito no ambiente seguro do Mercado Pago e renovado automaticamente. Ao trocar de plano, a assinatura antiga é cancelada assim que a nova for confirmada. Você pode cancelar quando quiser.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
