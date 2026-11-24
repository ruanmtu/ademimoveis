<?php
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $reset = one('SELECT r.*, u.nome, u.email FROM senha_resets r JOIN usuarios u ON u.id = r.usuario_id
                  WHERE r.token_hash = ? AND r.usado_em IS NULL AND r.expira_em >= ? AND u.status = ?',
                 [hash('sha256', $token), agora(), 'ativo']);
}

$erros = [];
if ($reset && is_post()) {
    csrf_check();
    $senha = (string) ($_POST['senha'] ?? '');
    if (strlen($senha) < 8) $erros['senha'] = 'Use pelo menos 8 caracteres.';
    elseif ($senha !== ($_POST['senha2'] ?? '')) $erros['senha2'] = 'As duas senhas estão diferentes.';

    if (!$erros) {
        q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $reset['usuario_id']]);
        // este e qualquer outro link pendente deixam de valer
        q('UPDATE senha_resets SET usado_em = ? WHERE usuario_id = ? AND usado_em IS NULL', [agora(), $reset['usuario_id']]);
        q('INSERT INTO pagamentos (usuario_id, tipo, status, payload, criado_em) VALUES (?,?,?,?,?)',
          [$reset['usuario_id'], 'senha_redefinida', 'ok', json_encode(['ip' => $_SERVER['REMOTE_ADDR'] ?? '']), agora()]);
        if (logado()) sair();
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        flash('sucesso', 'Senha alterada! Entre com a nova senha.');
        redirect('login.php');
    }
}

$titulo = 'Criar nova senha';
$scripts = ['js/auth.js'];
$ladoTitulo = 'Quase lá';
$ladoTexto = 'Escolha uma senha que só você saiba.';
$ladoItens = [
    ['cadeado', 'Pelo menos 8 caracteres', 'Misture letras, números e símbolos.'],
    ['olho-fechado', 'Não reutilize senhas', 'Evite a mesma senha de outros sites.'],
    ['chave', 'Vale na hora', 'Depois de salvar, entre com a senha nova.'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-pagina">
  <div class="auth-cartao">
    <?php require __DIR__ . '/includes/auth_lado.php'; ?>
    <section class="auth-form">
      <?php if (!$reset): ?>
        <div class="auth-confirmacao">
          <span class="auth-confirmacao-icone auth-confirmacao-erro"><?= icone('cadeado', 30) ?></span>
          <h1>Este link não vale mais</h1>
          <p>Ele expirou, já foi usado ou está incompleto. Por segurança, cada link funciona uma única vez e por tempo limitado.</p>
          <a class="btn btn-bloco btn-auth" href="<?= url('esqueci-senha.php') ?>">Pedir um novo link</a>
          <a class="link-secundario" href="<?= url('login.php') ?>">Voltar para o login</a>
        </div>
      <?php else: ?>
        <form method="post" novalidate data-form-auth>
          <h1>Criar nova senha</h1>
          <p class="sub">Para a conta <strong><?= e($reset['email']) ?></strong>.</p>
          <?php if ($erros): ?><div class="alerta alerta-erro" role="alert">Confira os campos abaixo.</div><?php endif; ?>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <label for="senha">Nova senha</label>
          <div class="campo-icone">
            <?= icone('cadeado', 20) ?>
            <input type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" data-forca autofocus<?= isset($erros['senha']) ? ' aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-olho" data-mostrar-senha="senha" aria-label="Mostrar senha"><?= icone('olho', 20) ?></button>
          </div>
          <div class="forca-senha" data-forca-barra><span></span></div>
          <?php if (isset($erros['senha'])): ?><p class="campo-erro"><?= e($erros['senha']) ?></p><?php endif; ?>

          <label for="senha2">Repita a nova senha</label>
          <div class="campo-icone">
            <?= icone('cadeado', 20) ?>
            <input type="password" id="senha2" name="senha2" required minlength="8" autocomplete="new-password"<?= isset($erros['senha2']) ? ' aria-invalid="true"' : '' ?>>
            <button type="button" class="btn-olho" data-mostrar-senha="senha2" aria-label="Mostrar senha"><?= icone('olho', 20) ?></button>
          </div>
          <?php if (isset($erros['senha2'])): ?><p class="campo-erro"><?= e($erros['senha2']) ?></p><?php endif; ?>

          <button class="btn btn-bloco btn-auth" type="submit" data-carregando="Salvando...">Salvar nova senha</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
