<?php
require __DIR__ . '/includes/bootstrap.php';

if (logado()) redirect(destino_pos_login(usuario()));

$d = ['nome' => '', 'email' => '', 'telefone' => '', 'tipo' => get('tipo') === 'anunciante' ? 'anunciante' : 'cliente',
      'creci' => '', 'cidade' => '', 'estado' => ''];
$erros = [];

if (is_post()) {
    csrf_check();
    foreach ($d as $k => $_) $d[$k] = post($k);
    $d['email'] = mb_strtolower($d['email']);
    $senha = (string) ($_POST['senha'] ?? '');
    $senha2 = (string) ($_POST['senha2'] ?? '');

    // Só é possível escolher cliente ou anunciante. Admin é definido manualmente.
    if (!in_array($d['tipo'], ['cliente', 'anunciante'], true)) $erros[] = 'Escolha o tipo de conta.';
    if (mb_strlen($d['nome']) < 3) $erros[] = 'Informe seu nome completo.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
    if (strlen($senha) < 8) $erros[] = 'A senha precisa ter pelo menos 8 caracteres.';
    if ($senha !== $senha2) $erros[] = 'As senhas não conferem.';
    if ($d['tipo'] === 'anunciante' && mb_strlen($d['telefone']) < 8) $erros[] = 'Anunciantes precisam informar um telefone.';
    if ($d['estado'] !== '' && !in_array($d['estado'], estados(), true)) $erros[] = 'Estado inválido.';
    if (empty($_POST['termos'])) $erros[] = 'É preciso aceitar os termos de uso.';
    if (!$erros && val('SELECT 1 FROM usuarios WHERE email = ?', [$d['email']])) $erros[] = 'Este e-mail já está cadastrado.';

    if (!$erros) {
        $plano = null; // o plano é definido quando o anunciante assina
        q('INSERT INTO usuarios (tipo, nome, email, senha_hash, telefone, whatsapp, creci, cidade, estado, plano_id, criado_em)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            $d['tipo'], $d['nome'], $d['email'], password_hash($senha, PASSWORD_DEFAULT),
            $d['telefone'] ?: null, $d['tipo'] === 'anunciante' ? ($d['telefone'] ?: null) : null,
            $d['creci'] ?: null, $d['cidade'] ?: null, $d['estado'] ?: null, $plano ?: null, agora(),
        ]);
        $id = insert_id();
        entrar($id);
        if ($d['tipo'] === 'anunciante') {
            flash('sucesso', 'Conta criada! Agora ative sua assinatura para publicar seus imóveis.');
            redirect('painel/assinatura.php');
        }
        flash('sucesso', 'Conta criada! Bem-vindo(a) à ADemóveis.');
        redirect('index.php');
    }
}

$planos = all('SELECT * FROM planos WHERE ativo = 1 ORDER BY preco');
$titulo = 'Criar conta';
$scripts = ['js/cadastro.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="container auth">
  <form method="post" class="caixa auth-caixa auth-larga" data-cadastro>
    <h1>Criar conta</h1>
    <?php if ($erros): ?><div class="alerta alerta-erro"><?= implode('<br>', array_map('e', $erros)) ?></div><?php endif; ?>
    <?= csrf_field() ?>

    <fieldset class="escolha-tipo">
      <legend>Como você vai usar a ADemóveis?</legend>
      <label class="opcao-tipo">
        <input type="radio" name="tipo" value="cliente" <?= $d['tipo'] === 'cliente' ? 'checked' : '' ?>>
        <span><strong>Sou cliente</strong><small>Quero procurar imóveis e falar com anunciantes. Grátis.</small></span>
      </label>
      <label class="opcao-tipo">
        <input type="radio" name="tipo" value="anunciante" <?= $d['tipo'] === 'anunciante' ? 'checked' : '' ?>>
        <span><strong>Sou anunciante</strong><small>Quero anunciar imóveis.
          <?php if ($planos): ?>A partir de <?= dinheiro(min(array_map(fn ($p) => (float) $p['preco'] / max(1, (int) $p['periodicidade_meses']), $planos))) ?>/mês.<?php endif; ?></small></span>
      </label>
    </fieldset>

    <label>Nome completo <input type="text" name="nome" value="<?= e($d['nome']) ?>" required maxlength="120" autocomplete="name"></label>
    <label>E-mail <input type="email" name="email" value="<?= e($d['email']) ?>" required maxlength="190" autocomplete="email"></label>
    <label>Telefone / WhatsApp <input type="tel" name="telefone" value="<?= e($d['telefone']) ?>" maxlength="30" autocomplete="tel" placeholder="(00) 00000-0000"></label>

    <div class="so-anunciante" data-so-anunciante>
      <label>CRECI (se for corretor) <input type="text" name="creci" value="<?= e($d['creci']) ?>" maxlength="30"></label>
      <div class="linha-2">
        <label>Cidade <input type="text" name="cidade" value="<?= e($d['cidade']) ?>" maxlength="120"></label>
        <label>Estado
          <select name="estado"><option value="">--</option>
            <?php foreach (estados() as $uf): ?><option <?= $d['estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <div class="linha-2">
      <label>Senha <input type="password" name="senha" required minlength="8" autocomplete="new-password"></label>
      <label>Confirmar senha <input type="password" name="senha2" required minlength="8" autocomplete="new-password"></label>
    </div>
    <label class="check"><input type="checkbox" name="termos" value="1" required> Li e aceito os <a href="<?= url('termos.php') ?>" target="_blank">termos de uso</a></label>
    <button class="btn btn-bloco" type="submit">Criar conta</button>
    <p class="auth-rodape">Já tem conta? <a href="<?= url('login.php') ?>">Entrar</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
