<?php
/**
 * Cria o PRIMEIRO administrador. Só funciona enquanto não existir nenhum admin.
 * Depois de usar, APAGUE este arquivo do servidor.
 */
require __DIR__ . '/includes/bootstrap.php';

$existeAdmin = (bool) val("SELECT 1 FROM usuarios WHERE tipo = 'admin' LIMIT 1");
$erros = [];

if (!$existeAdmin && is_post()) {
    csrf_check();
    $nome = post('nome');
    $email = mb_strtolower(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    if (mb_strlen($nome) < 3) $erros[] = 'Informe o nome.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
    if (strlen($senha) < 10) $erros[] = 'Use uma senha com pelo menos 10 caracteres.';
    elseif ($probSenha = problema_senha($senha, $email, $nome)) $erros[] = $probSenha;
    if (!$erros && val('SELECT 1 FROM usuarios WHERE email = ?', [$email])) $erros[] = 'E-mail já cadastrado.';
    if (!$erros) {
        q("INSERT INTO usuarios (tipo, nome, email, senha_hash, criado_em) VALUES ('admin', ?, ?, ?, ?)",
          [$nome, $email, password_hash($senha, PASSWORD_DEFAULT), agora()]);
        entrar(insert_id());
        flash('sucesso', 'Administrador criado! Agora apague o arquivo instalar.php do servidor.');
        redirect('admin/index.php');
    }
}

$titulo = 'Instalação';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth">
  <?php if ($existeAdmin): ?>
    <div class="caixa auth-caixa">
      <h1>Instalação concluída</h1>
      <p>Já existe um administrador. Por segurança, <strong>apague o arquivo instalar.php</strong> do servidor.</p>
    </div>
  <?php else: ?>
    <form method="post" class="caixa auth-caixa">
      <h1>Criar administrador</h1>
      <p class="sub">Primeiro acesso da Ademimóveis.</p>
      <?php if ($erros): ?><div class="alerta alerta-erro"><?= implode('<br>', array_map('e', $erros)) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <label>Nome <input type="text" name="nome" required></label>
      <label>E-mail <input type="email" name="email" required></label>
      <label>Senha (mín. 10 caracteres) <input type="password" name="senha" required minlength="10"></label>
      <button class="btn btn-bloco" type="submit">Criar administrador</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
