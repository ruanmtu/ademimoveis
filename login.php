<?php
require __DIR__ . '/includes/bootstrap.php';

if (logado()) redirect(destino_pos_login(usuario()));

$email = '';
$erro = '';
if (is_post()) {
    csrf_check();
    $email = mb_strtolower(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    if (login_bloqueado()) {
        $erro = 'Muitas tentativas. Aguarde 10 minutos e tente novamente.';
    } else {
        $u = one('SELECT * FROM usuarios WHERE email = ?', [$email]);
        if ($u && password_verify($senha, $u['senha_hash'])) {
            if ($u['status'] !== 'ativo') {
                $erro = 'Sua conta está bloqueada. Entre em contato com o suporte.';
            } else {
                if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
                }
                entrar((int) $u['id']);
                redirect(destino_pos_login($u));
            }
        } else {
            registrar_falha_login();
            $erro = 'E-mail ou senha incorretos.';
        }
    }
}

$titulo = 'Entrar';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth">
  <form method="post" class="caixa auth-caixa">
    <h1>Entrar</h1>
    <p class="sub">Um único acesso para clientes, anunciantes e administração.</p>
    <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>E-mail <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email"></label>
    <label>Senha <input type="password" name="senha" required autocomplete="current-password"></label>
    <button class="btn btn-bloco" type="submit">Entrar</button>
    <p class="auth-rodape">Ainda não tem conta? <a href="<?= url('cadastro.php') ?>">Cadastre-se</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
