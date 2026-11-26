<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/email.php';

if (logado()) redirect(destino_pos_login(usuario()));

const RESET_MINUTOS = 30;
$enviado = false;
$email = '';
$erro = '';

if (is_post()) {
    csrf_check();
    $email = mb_strtolower(post('email'));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';


    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido, como voce@exemplo.com.';
    } elseif (limite_excedido('reset_pedido', 'ip:' . ip_cliente(), 5, 900)) {
        // limite por conexão: 5 pedidos a cada 15 minutos
        $erro = 'Muitos pedidos seguidos. Aguarde 15 minutos e tente de novo.';
    } else {
        registrar_evento('reset_pedido', 'ip:' . ip_cliente(), null, 'E-mail: ' . mb_substr($email, 0, 190));
        $u = one("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'", [$email]);
        // limite por conta: 3 e-mails por hora (evita encher a caixa de alguém)
        $recentes = $u ? (int) val('SELECT COUNT(*) FROM senha_resets WHERE usuario_id = ? AND criado_em >= ?',
                                   [$u['id'], date('Y-m-d H:i:s', time() - 3600)]) : 0;
        if ($u && $recentes < 3) {
            $token = bin2hex(random_bytes(32));
            // links anteriores deixam de valer
            q('UPDATE senha_resets SET usado_em = ? WHERE usuario_id = ? AND usado_em IS NULL', [agora(), $u['id']]);
            q('INSERT INTO senha_resets (usuario_id, token_hash, expira_em, ip, criado_em) VALUES (?,?,?,?,?)', [
                $u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + RESET_MINUTOS * 60), mb_substr($ip, 0, 45), agora(),
            ]);
            [$html, $texto] = email_recuperacao($u['nome'], url('redefinir-senha.php?token=' . $token), RESET_MINUTOS);
            enviar_email($u['email'], 'Redefinir sua senha na Ademimóveis', $html, $texto);
        }
        // Mesma resposta exista ou não a conta: não revela quais e-mails estão cadastrados.
        $enviado = true;
    }
}

$titulo = 'Recuperar senha';
$scripts = ['js/auth.js'];
$ladoTitulo = 'Acontece com todo mundo';
$ladoTexto = 'Em poucos minutos você cria uma senha nova.';
$ladoItens = [
    ['email', 'Enviamos um link', 'Chega no e-mail cadastrado na sua conta.'],
    ['cadeado', 'Seguro', 'O link vale por ' . RESET_MINUTOS . ' minutos e só pode ser usado uma vez.'],
    ['chave', 'Pronto para entrar', 'Escolha a nova senha e acesse normalmente.'],
];
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-pagina">
  <div class="auth-cartao">
    <?php require __DIR__ . '/includes/auth_lado.php'; ?>
    <section class="auth-form">
      <?php if ($enviado): ?>
        <div class="auth-confirmacao">
          <span class="auth-confirmacao-icone"><?= icone('email', 30) ?></span>
          <h1>Confira seu e-mail</h1>
          <p>Se existir uma conta com <strong><?= e($email) ?></strong>, enviamos um link para criar uma nova senha. Ele vale por <?= RESET_MINUTOS ?> minutos.</p>
          <p class="dica">Não chegou em alguns minutos? Olhe a caixa de spam ou lixo eletrônico.</p>
          <a class="btn btn-bloco btn-auth" href="<?= url('login.php') ?>">Voltar para o login</a>
          <a class="link-secundario" href="<?= url('esqueci-senha.php') ?>">Tentar outro e-mail</a>
        </div>
      <?php else: ?>
        <form method="post" novalidate data-form-auth>
          <h1>Esqueceu a senha?</h1>
          <p class="sub">Digite o e-mail da sua conta e enviaremos um link para criar uma senha nova.</p>
          <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
          <?= csrf_field() ?>
          <label for="email">E-mail</label>
          <div class="campo-icone">
            <?= icone('email', 20) ?>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email" placeholder="voce@exemplo.com">
          </div>
          <button class="btn btn-bloco btn-auth" type="submit" data-carregando="Enviando...">Enviar link</button>
          <a class="link-secundario" href="<?= url('login.php') ?>">Lembrei a senha, voltar para o login</a>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
