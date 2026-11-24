<?php
require __DIR__ . '/includes/bootstrap.php';
$u = exigir_login();
$anunc = $u['tipo'] === 'anunciante';

if (is_post()) {
    csrf_check();
    $acao = post('acao');

    if ($acao === 'dados') {
        $nome = post('nome');
        $estado = post('estado');
        if (mb_strlen($nome) < 3) {
            flash('erro', 'Informe seu nome completo.');
        } elseif ($estado !== '' && !in_array($estado, estados(), true)) {
            flash('erro', 'Estado inválido.');
        } else {
            $foto = $u['foto'];
            try {
                // Documento: o anunciante informa uma vez; depois só a administração altera
                $docTipo = $u['documento_tipo'];
                $docNum = $u['documento'];
                if ($u['tipo'] !== 'admin' && !$u['documento'] && post('documento') !== '') {
                    $doc = identificar_documento(post('documento'));
                    if (!$doc) throw new RuntimeException($anunc ? 'CPF ou CNPJ inválido. Confira os números.' : 'CPF inválido. Confira os números.');
                    if (!$anunc && $doc['tipo'] !== 'cpf') throw new RuntimeException('Para conta de cliente, use o CPF.');
                    if (val('SELECT 1 FROM usuarios WHERE documento = ? AND id <> ?', [$doc['numero'], $u['id']])) {
                        throw new RuntimeException('Este ' . strtoupper($doc['tipo']) . ' já está em outra conta. Fale com o suporte.');
                    }
                    [$docTipo, $docNum] = [$doc['tipo'], $doc['numero']];
                }
                // CRECI: mudou número ou estado -> volta para "aguardando verificação"
                $creci = $u['creci']; $creciUf = $u['creci_uf']; $creciSt = $u['creci_status']; $creciEm = $u['creci_verificado_em'];
                if ($anunc) {
                    $novoCreci = post('creci'); $novaUf = post('creci_uf');
                    if ($novoCreci === '') {
                        [$creci, $creciUf, $creciSt, $creciEm] = [null, null, 'nao_informado', null];
                    } else {
                        $lido = ler_creci($novoCreci, $docTipo ?? 'cpf');
                        if ($lido && $lido['uf'] && $novaUf === '') $novaUf = $lido['uf'];
                        if (!in_array($novaUf, estados(), true)) throw new RuntimeException('Escolha o estado do CRECI.');
                        $norm = $lido['numero'] ?? null;
                        if (!$norm) throw new RuntimeException('CRECI inválido. Digite como está na sua carteira, por exemplo CRECI-CE 24199.');
                        if (val('SELECT 1 FROM usuarios WHERE creci = ? AND creci_uf = ? AND id <> ?', [$norm, $novaUf, $u['id']])) {
                            throw new RuntimeException('Este CRECI já está em outra conta. Fale com o suporte.');
                        }
                        if ($norm !== $u['creci'] || $novaUf !== $u['creci_uf']) {
                            [$creci, $creciUf, $creciSt, $creciEm] = [$norm, $novaUf, 'pendente', null];
                        }
                    }
                }
                if ($anunc && post('whatsapp') !== '' && !link_whatsapp(post('whatsapp'))) {
                    throw new RuntimeException('WhatsApp inválido. Use DDD + número, como (85) 98999-0000.');
                }
                // foto só é trocada depois que todo o resto foi validado
                if (!empty($_FILES['foto']['name'])) {
                    $foto = salvar_imagem($_FILES['foto'], 'perfis', 500);
                    apagar_upload($u['foto']);
                }
                q('UPDATE usuarios SET documento_tipo = ?, documento = ?, creci_uf = ?, creci_status = ?, creci_verificado_em = ? WHERE id = ?',
                  [$docTipo, $docNum, $creciUf, $creciSt, $creciEm, $u['id']]);
                q('UPDATE usuarios SET nome = ?, telefone = ?, whatsapp = ?, creci = ?, cidade = ?, estado = ?, bio = ?, foto = ? WHERE id = ?', [
                    $nome, post('telefone') ?: null,
                    $anunc ? (post('whatsapp') ?: null) : $u['whatsapp'],
                    $creci,
                    post('cidade') ?: null, $estado ?: null,
                    $anunc ? (mb_substr(post('bio'), 0, 2000) ?: null) : $u['bio'],
                    $foto, $u['id'],
                ]);
                flash('sucesso', 'Dados atualizados.');
            } catch (RuntimeException $ex) {
                flash('erro', $ex->getMessage());
            }
        }
    } elseif ($acao === 'senha') {
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');
        if (!password_verify($atual, $u['senha_hash'])) {
            flash('erro', 'Senha atual incorreta.');
        } elseif (strlen($nova) < 8) {
            flash('erro', 'A nova senha precisa ter pelo menos 8 caracteres.');
        } elseif ($nova !== ($_POST['senha_nova2'] ?? '')) {
            flash('erro', 'As senhas não conferem.');
        } else {
            q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('sucesso', 'Senha alterada.');
        }
    }
    redirect('conta.php');
}

$titulo = 'Minha conta';
$scripts = ['js/documentos.js', 'js/auth.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container secao conta">
  <h1>Minha conta</h1>
  <p class="sub">Tipo de conta: <strong><?= e(['cliente' => 'Cliente', 'anunciante' => 'Anunciante', 'admin' => 'Administrador'][$u['tipo']]) ?></strong>
    <?php if ($anunc): ?> · <a href="<?= url('anunciante.php?id=' . (int) $u['id']) ?>">Ver meu perfil público</a><?php endif; ?></p>

  <div class="duas-colunas">
    <form method="post" enctype="multipart/form-data" class="caixa">
      <?= csrf_field() ?><input type="hidden" name="acao" value="dados">
      <h2>Dados<?= $anunc ? ' do perfil' : '' ?></h2>
      <div class="foto-perfil">
        <img class="avatar avatar-g" src="<?= e(upload_url($u['foto'], 'assets/img/avatar.svg')) ?>" alt="">
        <label>Foto <input type="file" name="foto" accept="image/*"></label>
      </div>
      <label>Nome <input type="text" name="nome" value="<?= e($u['nome']) ?>" required maxlength="120"></label>
      <label>E-mail <input type="email" value="<?= e($u['email']) ?>" disabled></label>
      <div class="linha-2">
        <label>Telefone <input type="tel" name="telefone" value="<?= e($u['telefone']) ?>" maxlength="30"></label>
        <?php if ($anunc): ?><label>WhatsApp para contato <input type="tel" name="whatsapp" value="<?= e($u['whatsapp']) ?>" maxlength="20" placeholder="(00) 00000-0000" data-telefone>
          <small class="dica">Aparece como botão "Chamar no WhatsApp" nos seus anúncios e no seu perfil. Deixe vazio para não mostrar.</small></label><?php endif; ?>
      </div>
      <?php if ($u['tipo'] === 'cliente'): ?>
        <label>CPF
          <?php if ($u['documento']): ?>
            <input type="text" value="<?= e(mascarar_documento($u['documento'])) ?>" disabled>
            <small class="dica">Para alterar, fale com o suporte.</small>
          <?php else: ?>
            <input type="text" name="documento" maxlength="14" placeholder="000.000.000-00" data-documento>
          <?php endif; ?>
        </label>
      <?php endif; ?>
      <?php if ($anunc): ?>
        <label>CPF ou CNPJ
          <?php if ($u['documento']): ?>
            <input type="text" value="<?= e(formatar_documento($u['documento'])) ?>" disabled>
            <small class="dica">Para alterar, fale com o suporte.</small>
          <?php else: ?>
            <input type="text" name="documento" maxlength="18" placeholder="000.000.000-00 ou 00.000.000/0000-00" data-documento>
            <small class="dica">Obrigatório para anunciar. Não aparece no site.</small>
          <?php endif; ?>
        </label>
        <div class="linha-creci">
          <label>CRECI <small class="dica">(opcional)</small>
            <input type="text" name="creci" value="<?= e($u['creci']) ?>" maxlength="20" placeholder="Ex.: CRECI-CE 24199" data-creci>
          </label>
          <label>Estado
            <select name="creci_uf"><option value="">UF</option><?php foreach (estados() as $uf): ?><option <?= $u['creci_uf'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?></select>
          </label>
        </div>
        <?php if ($u['creci']): ?>
          <p class="dica">Situação do CRECI:
            <?php if ($u['creci_status'] === 'verificado'): ?><span class="selo-creci"><?= icone('verificado', 15) ?>Verificado</span>
            <?php else: ?><strong><?= e(creci_status_label($u['creci_status'])) ?></strong><?= $u['creci_status'] === 'recusado' ? '. Confira o número e o estado.' : '. Nossa equipe confere no site do CRECI do seu estado.' ?><?php endif; ?>
          </p>
        <?php endif; ?>
      <?php endif; ?>
      <div class="linha-2">
        <label>Cidade <input type="text" name="cidade" value="<?= e($u['cidade']) ?>" maxlength="120"></label>
        <label>Estado <select name="estado"><option value="">--</option><?php foreach (estados() as $uf): ?><option <?= $u['estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?></select></label>
      </div>
      <?php if ($anunc): ?><label>Sobre você (aparece no perfil) <textarea name="bio" rows="5" maxlength="2000"><?= e($u['bio']) ?></textarea></label><?php endif; ?>
      <button class="btn" type="submit">Salvar</button>
    </form>

    <form method="post" class="caixa">
      <?= csrf_field() ?><input type="hidden" name="acao" value="senha">
      <h2>Alterar senha</h2>
      <label>Senha atual <input type="password" name="senha_atual" required autocomplete="current-password"></label>
      <label>Nova senha <input type="password" name="senha_nova" required minlength="8" autocomplete="new-password"></label>
      <label>Confirmar nova senha <input type="password" name="senha_nova2" required minlength="8" autocomplete="new-password"></label>
      <button class="btn" type="submit">Alterar senha</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
