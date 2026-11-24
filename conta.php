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
                if (!empty($_FILES['foto']['name'])) {
                    $foto = salvar_imagem($_FILES['foto'], 'perfis', 500);
                    apagar_upload($u['foto']);
                }
                q('UPDATE usuarios SET nome = ?, telefone = ?, whatsapp = ?, creci = ?, cidade = ?, estado = ?, bio = ?, foto = ? WHERE id = ?', [
                    $nome, post('telefone') ?: null,
                    $anunc ? (post('whatsapp') ?: null) : $u['whatsapp'],
                    $anunc ? (post('creci') ?: null) : $u['creci'],
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
        <?php if ($anunc): ?><label>WhatsApp (aparece no perfil) <input type="tel" name="whatsapp" value="<?= e($u['whatsapp']) ?>" maxlength="30"></label><?php endif; ?>
      </div>
      <?php if ($anunc): ?><label>CRECI <input type="text" name="creci" value="<?= e($u['creci']) ?>" maxlength="30"></label><?php endif; ?>
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
