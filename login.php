<?php
require __DIR__ . '/includes/bootstrap.php';

if (logado()) redirect(destino_pos_login(usuario()));

$email = '';
$erro = '';
if (is_post()) {
    csrf_check();
    $email = mb_strtolower(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    if ($email === '' || strlen($senha) > 200) {
        $erro = 'E-mail ou senha incorretos. Confira e tente de novo.';
    } elseif (login_bloqueado($email)) {
        $erro = 'Muitas tentativas erradas. Por segurança, aguarde 15 minutos e tente de novo, ou use "Esqueceu a senha?".';
        registrar_evento('login_bloqueado', 'email:' . $email, null, 'E-mail: ' . $email);
    } else {
        $u = one('SELECT * FROM usuarios WHERE email = ?', [$email]);
        // confere a senha mesmo quando o e-mail não existe, para o tempo de resposta não revelar quem tem conta
        $senhaOk = password_verify($senha, $u['senha_hash'] ?? '$2y$10$xN7k3o6EUPegFyGFZNJmhOZVcZKJgt.vmY89qNBEy.cSaYP1hn/uy');
        if ($u && $senhaOk) {
            if ($u['status'] !== 'ativo') {
                $erro = 'Sua conta está bloqueada. Fale com o suporte para entender o motivo.';
            } else {
                if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
                }
                limpar_falhas_login($email);
                entrar((int) $u['id']);
                redirect(destino_pos_login($u));
            }
        } else {
            registrar_falha_login($email, $u ? (int) $u['id'] : null);
            $erro = 'E-mail ou senha incorretos. Confira e tente de novo.';
        }
    }
}

$titulo = 'Entrar';
$scripts = ['js/auth.js'];
$ladoTitulo = 'Que bom ver você de novo';
$ladoTexto = 'Entre para continuar de onde parou.';
$ladoItens = [
    ['casa', 'Imóveis salvos na conversa', 'Retome o contato com quem anuncia pelo chat.'],
    ['portaria', 'Anunciantes avaliados', 'Veja a nota e os comentários de outros clientes.'],
    ['chave', 'Para quem anuncia', 'Gerencie imóveis, mensagens e sua assinatura.'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-pagina">
  <div class="auth-cartao">
    <?php require __DIR__ . '/includes/auth_lado.php'; ?>

    <section class="auth-form">
      <form method="post" novalidate data-form-auth>
        <h1>Entrar</h1>
        <p class="sub">Um único acesso para clientes, anunciantes e administração.</p>
        <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
        <?= csrf_field() ?>

        <label for="email">E-mail</label>
        <div class="campo-icone">
          <?= icone('email', 20) ?>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email" placeholder="voce@exemplo.com">
        </div>

        <label for="senha">Senha</label>
        <div class="campo-icone">
          <?= icone('cadeado', 20) ?>
          <input type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="Sua senha">
          <button type="button" class="btn-olho" data-mostrar-senha="senha" aria-label="Mostrar senha"><?= icone('olho', 20) ?></button>
        </div>

        <div class="auth-esqueci"><a href="<?= url('esqueci-senha.php') ?>">Esqueceu a senha?</a></div>
        <button class="btn btn-bloco btn-auth" type="submit" data-carregando="Entrando...">Entrar</button>

        <div class="auth-separador"><span>ainda não tem conta?</span></div>
        <a class="btn btn-claro btn-bloco" href="<?= url('cadastro.php') ?>">Criar conta grátis</a>
        <p class="auth-seguro"><?= icone('cadeado', 16) ?>Seus dados ficam protegidos e não são compartilhados.</p>
      </form>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
