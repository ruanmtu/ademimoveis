<?php
require __DIR__ . '/includes/bootstrap.php';

if (logado()) redirect(destino_pos_login(usuario()));

// Quem vem de "Anunciar" já escolheu o plano antes de chegar aqui
$planoEscolhido = null;
$idPlano = (int) (is_post() ? ($_POST['plano_id'] ?? 0) : get('plano'));
if ($idPlano) $planoEscolhido = one('SELECT * FROM planos WHERE id = ? AND ativo = 1', [$idPlano]);
$cupomEscolhido = normalizar_codigo(is_post() ? post('cupom') : get('cupom'));
if (!is_post() && get('tipo') === 'anunciante' && !$planoEscolhido) {
    redirect('anunciar.php' . ($cupomEscolhido ? '?cupom=' . urlencode($cupomEscolhido) : ''));
}

$d = ['nome' => '', 'email' => '', 'telefone' => '', 'tipo' => get('tipo') === 'anunciante' || $planoEscolhido ? 'anunciante' : 'cliente',
      'documento' => '', 'creci' => '', 'creci_uf' => '', 'cidade' => '', 'estado' => ''];
$erros = [];   // campo => mensagem

if (is_post()) {
    csrf_check();
    foreach ($d as $k => $_) $d[$k] = post($k);
    if ($planoEscolhido) $d['tipo'] = 'anunciante';
    $d['email'] = mb_strtolower($d['email']);
    $senha = (string) ($_POST['senha'] ?? '');
    $senha2 = (string) ($_POST['senha2'] ?? '');
    $anunc = $d['tipo'] === 'anunciante';

    // Só é possível escolher cliente ou anunciante. Administrador é definido manualmente.
    if (!in_array($d['tipo'], ['cliente', 'anunciante'], true)) $erros['tipo'] = 'Escolha como você vai usar a Ademimóveis.';
    if (mb_strlen($d['nome']) < 3) $erros['nome'] = 'Informe o nome completo.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Digite um e-mail válido, como voce@exemplo.com.';
    elseif (val('SELECT 1 FROM usuarios WHERE email = ?', [$d['email']])) $erros['email'] = 'Este e-mail já tem conta. Use a opção Entrar.';
    foreach (['nome' => 120, 'email' => 190, 'telefone' => 20, 'documento' => 20, 'creci' => 20, 'cidade' => 120] as $k => $max) {
        $d[$k] = mb_substr((string) $d[$k], 0, $max);
    }
    if ($probSenha = problema_senha($senha, $d['email'], $d['nome'])) $erros['senha'] = $probSenha;
    elseif ($senha !== $senha2) $erros['senha2'] = 'As duas senhas estão diferentes.';
    if ($d['estado'] !== '' && !in_array($d['estado'], estados(), true)) $erros['estado'] = 'Estado inválido.';
    if (empty($_POST['termos'])) $erros['termos'] = 'Para criar a conta, aceite os termos de uso.';

    $creci = null;
    // Documento: clientes informam CPF; anunciantes, CPF ou CNPJ
    $doc = identificar_documento($d['documento']);
    if ($d['documento'] === '') {
        $erros['documento'] = $anunc ? 'Informe o CPF ou o CNPJ.' : 'Informe seu CPF.';
    } elseif (!$doc) {
        $erros['documento'] = $anunc ? 'CPF ou CNPJ inválido. Confira os números.' : 'CPF inválido. Confira os números.';
    } elseif (!$anunc && $doc['tipo'] !== 'cpf') {
        $erros['documento'] = 'Para conta de cliente, use o CPF. Empresas podem criar conta de anunciante.';
    } elseif (val('SELECT 1 FROM usuarios WHERE documento = ?', [$doc['numero']])) {
        $erros['documento'] = 'Já existe uma conta com este ' . strtoupper($doc['tipo']) . '. Use a opção Entrar ou Esqueceu a senha.';
    }
    if ($anunc) {
        if (mb_strlen(preg_replace('/\D/', '', $d['telefone'])) < 10) $erros['telefone'] = 'Informe um telefone com DDD.';
        // CRECI é opcional: só é considerado se a pessoa marcar que tem
        if (empty($_POST['tem_creci'])) { $d['creci'] = ''; $d['creci_uf'] = ''; }
        if ($d['creci'] !== '') {
            $lido = ler_creci($d['creci'], $doc['tipo'] ?? 'cpf');
            if ($lido && $lido['uf'] && $d['creci_uf'] === '') $d['creci_uf'] = $lido['uf'];
            if (!in_array($d['creci_uf'], estados(), true)) $erros['creci_uf'] = 'Escolha o estado do CRECI.';
            $creci = $lido['numero'] ?? null;
            if (!$creci) {
                $erros['creci'] = ($doc['tipo'] ?? '') === 'cnpj'
                    ? 'CRECI inválido. Digite como está no registro da imobiliária, por exemplo 1234 ou 1234-J.'
                    : 'CRECI inválido. Digite como está na sua carteira, por exemplo CRECI-CE 24199.';
            } elseif (!isset($erros['creci_uf']) && val('SELECT 1 FROM usuarios WHERE creci = ? AND creci_uf = ?', [$creci, $d['creci_uf']])) {
                $erros['creci'] = 'Este CRECI já está em outra conta. Se for seu, fale com o suporte.';
            }
        }
    }

    // no máximo 5 contas novas por hora a partir do mesmo endereço de internet (evita robôs criando contas falsas)
    if (!$erros && limite_excedido('cadastro', 'ip:' . ip_cliente(), defined('LIMITE_CADASTROS_HORA') ? (int) LIMITE_CADASTROS_HORA : 5, 3600)) {
        $erros['geral'] = 'Muitas contas criadas a partir desta conexão na última hora. Tente de novo mais tarde.';
    }

    if (!$erros) {
        q('INSERT INTO usuarios (tipo, nome, email, senha_hash, telefone, whatsapp, documento_tipo, documento, creci, creci_uf, creci_status,
             cidade, estado, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $d['tipo'], $d['nome'], $d['email'], password_hash($senha, PASSWORD_DEFAULT),
            $d['telefone'] ?: null, $anunc ? ($d['telefone'] ?: null) : null,
            $doc['tipo'] ?? null, $doc['numero'] ?? null,
            $creci, $creci ? $d['creci_uf'] : null, $creci ? 'pendente' : 'nao_informado',
            $d['cidade'] ?: null, $d['estado'] ?: null, agora(),
        ]);
        $novoId = insert_id();
        registrar_evento('cadastro', 'ip:' . ip_cliente(), $novoId, $d['tipo']);
        entrar($novoId);
        if ($anunc) {
            $creciMsg = $creci ? ' Seu CRECI será conferido pela nossa equipe.' : '';
            if ($planoEscolhido) {
                flash('sucesso', 'Conta criada! Agora é só confirmar o pagamento do plano ' . $planoEscolhido['nome'] . '.' . $creciMsg);
                redirect('painel/assinatura.php?' . http_build_query(array_filter(['plano' => (int) $planoEscolhido['id'], 'cupom' => $cupomEscolhido ?: null])));
            }
            flash('sucesso', 'Conta criada! Agora escolha um plano para publicar seus imóveis.' . $creciMsg);
            redirect('painel/assinatura.php');
        }
        flash('sucesso', 'Conta criada! Bem-vindo(a) à Ademimóveis.');
        redirect('index.php');
    }
}

function erro_campo(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? '<p class="campo-erro" id="erro-' . $campo . '">' . e($erros[$campo]) . '</p>' : '';
}
function aria_erro(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? ' aria-invalid="true" aria-describedby="erro-' . $campo . '"' : '';
}

$planos = all('SELECT * FROM planos WHERE ativo = 1');
$aPartir = $planos ? min(array_map(fn ($p) => (float) $p['preco'] / max(1, (int) $p['periodicidade_meses']), $planos)) : null;

$titulo = $planoEscolhido ? 'Criar conta de anunciante' : 'Criar conta';
$scripts = ['js/auth.js', 'js/documentos.js'];
if ($planoEscolhido) {
    $ladoTitulo = 'Falta pouco para seu anúncio ir ao ar';
    $ladoTexto = 'Crie sua conta e, em seguida, confirme o pagamento no Mercado Pago.';
    $ladoItens = [
        ['chave', 'Anuncie venda, aluguel ou temporada', 'Fotos, mapa do bairro, comodidades e WhatsApp no anúncio.'],
        ['busca', 'Receba contatos de interessados', 'Mensagens pelo chat do site e direto no seu WhatsApp.'],
        ['verificado', 'Perfil com avaliações', 'Clientes avaliam seu atendimento e seu CRECI ganha selo de verificado.'],
    ];
} else {
    $ladoTitulo = 'Seu próximo endereço começa aqui';
    $ladoTexto = 'Crie sua conta em menos de um minuto.';
    $ladoItens = [
        ['busca', 'Busque do seu jeito', 'Filtros por bairro, quartos, preço e comodidades.'],
        ['chave', 'Fale direto com quem anuncia', 'Chat dentro do site, sem expor seu telefone.'],
        ['verificado', 'Contas conferidas', 'CPF validado em todas as contas e CRECI verificado pela equipe.'],
    ];
}
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-pagina">
  <div class="auth-cartao auth-cartao-largo">
    <?php require __DIR__ . '/includes/auth_lado.php'; ?>

    <section class="auth-form">
      <form method="post" novalidate data-cadastro data-form-auth>
        <h1><?= $planoEscolhido ? 'Crie sua conta de anunciante' : 'Criar conta' ?></h1>
        <p class="sub">Já tem conta? <a href="<?= url('login.php') ?>">Entrar</a></p>
        <?php if ($erros): ?><div class="alerta alerta-erro" role="alert"><?= e($erros['geral'] ?? 'Confira os campos marcados abaixo.') ?></div><?php endif; ?>
        <?= csrf_field() ?>

        <?php if ($planoEscolhido): $perE = (int) $planoEscolhido['periodicidade_meses']; ?>
          <ol class="passos-anunciar passos-compacto" aria-label="Etapas para anunciar">
            <li class="feito"><span>1</span>Plano</li>
            <li class="ativo" aria-current="step"><span>2</span>Sua conta</li>
            <li><span>3</span>Pagamento</li>
          </ol>
          <input type="hidden" name="tipo" value="anunciante">
          <input type="hidden" name="plano_id" value="<?= (int) $planoEscolhido['id'] ?>">
          <?php if ($cupomEscolhido): ?><input type="hidden" name="cupom" value="<?= e($cupomEscolhido) ?>"><?php endif; ?>
          <div class="plano-escolhido">
            <div>
              <span>Plano escolhido</span>
              <strong><?= e($planoEscolhido['nome']) ?></strong>
              <small><?= texto_limite_imoveis($planoEscolhido['limite_imoveis']) ?>, até <?= (int) $planoEscolhido['limite_fotos'] ?> fotos por imóvel</small>
            </div>
            <div class="plano-escolhido-preco">
              <strong><?= dinheiro($planoEscolhido['preco']) ?><small><?= sufixo_periodo($perE) ?></small></strong>
              <a href="<?= url('anunciar.php' . ($cupomEscolhido ? '?cupom=' . urlencode($cupomEscolhido) : '')) ?>">Trocar plano</a>
            </div>
          </div>
        <?php else: ?>
          <fieldset class="escolha-tipo">
            <legend>Como você vai usar a Ademimóveis?</legend>
            <label class="opcao-tipo">
              <input type="radio" name="tipo" value="cliente" <?= $d['tipo'] === 'cliente' ? 'checked' : '' ?>>
              <span><?= icone('busca', 22) ?><strong>Quero encontrar um imóvel</strong><small>Buscar, conversar com anunciantes e avaliar. Grátis.</small></span>
            </label>
            <label class="opcao-tipo">
              <input type="radio" name="tipo" value="anunciante" <?= $d['tipo'] === 'anunciante' ? 'checked' : '' ?>>
              <span><?= icone('chave', 22) ?><strong>Quero anunciar imóveis</strong><small>Para corretores, imobiliárias e proprietários.<?= $aPartir ? ' A partir de ' . dinheiro($aPartir) . '/mês.' : '' ?></small></span>
            </label>
          </fieldset>
        <?php endif; ?>
        <?= erro_campo($erros, 'tipo') ?>

        <div>
          <label for="documento" data-rotulo-documento>CPF</label>
          <div class="campo-icone">
            <?= icone('documento', 20) ?>
            <input type="text" id="documento" name="documento" value="<?= e($d['documento']) ?>" maxlength="18" autocomplete="off"
                   placeholder="000.000.000-00" data-documento<?= aria_erro($erros, 'documento') ?>>
            <span class="campo-status" data-documento-status aria-live="polite"></span>
          </div>
          <?= erro_campo($erros, 'documento') ?>
        </div>

        <label for="nome" data-rotulo-nome>Nome completo</label>
        <div class="campo-icone">
          <?= icone('usuario', 20) ?>
          <input type="text" id="nome" name="nome" value="<?= e($d['nome']) ?>" required maxlength="120" autocomplete="name"<?= aria_erro($erros, 'nome') ?>>
        </div>
        <?= erro_campo($erros, 'nome') ?>

        <div class="linha-2">
          <div>
            <label for="email">E-mail</label>
            <div class="campo-icone">
              <?= icone('email', 20) ?>
              <input type="email" id="email" name="email" value="<?= e($d['email']) ?>" required maxlength="190" autocomplete="email" placeholder="voce@exemplo.com"<?= aria_erro($erros, 'email') ?>>
            </div>
            <?= erro_campo($erros, 'email') ?>
          </div>
          <div>
            <label for="telefone">Telefone / WhatsApp</label>
            <div class="campo-icone">
              <?= icone('telefone', 20) ?>
              <input type="tel" id="telefone" name="telefone" value="<?= e($d['telefone']) ?>" maxlength="20" autocomplete="tel" placeholder="(00) 00000-0000" data-telefone<?= aria_erro($erros, 'telefone') ?>>
            </div>
            <?= erro_campo($erros, 'telefone') ?>
          </div>
        </div>

        <div class="so-anunciante" data-so-anunciante>
          <div class="bloco-creci">
            <label class="check check-creci">
              <input type="checkbox" name="tem_creci" value="1" data-tem-creci <?= !empty($_POST['tem_creci']) ? 'checked' : '' ?>>
              <span><strong>Sou corretor(a) ou imobiliária e tenho CRECI</strong><small>Opcional. Proprietários podem anunciar sem CRECI. Quem informa e tem o registro confirmado pela equipe ganha o selo "CRECI verificado" no perfil.</small></span>
            </label>
            <div class="campos-creci" data-campos-creci>
              <div class="linha-creci">
                <div>
                  <label for="creci">Número do CRECI</label>
                  <input type="text" id="creci" name="creci" value="<?= e($d['creci']) ?>" maxlength="20" placeholder="Ex.: CRECI-CE 24199" data-creci<?= aria_erro($erros, 'creci') ?>>
                </div>
                <div>
                  <label for="creci_uf">Estado</label>
                  <select id="creci_uf" name="creci_uf"<?= aria_erro($erros, 'creci_uf') ?>>
                    <option value="">UF</option>
                    <?php foreach (estados() as $uf): ?><option <?= $d['creci_uf'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
                  </select>
                </div>
              </div>
              <p class="campo-ajuda" data-creci-ajuda>Digite como aparece na sua carteira do CRECI.</p>
              <?= erro_campo($erros, 'creci') ?><?= erro_campo($erros, 'creci_uf') ?>
            </div>
          </div>

          <div class="linha-2">
            <div>
              <label for="cidade">Cidade onde atua</label>
              <input type="text" id="cidade" name="cidade" value="<?= e($d['cidade']) ?>" maxlength="120">
            </div>
            <div>
              <label for="estado">Estado</label>
              <select id="estado" name="estado">
                <option value="">UF</option>
                <?php foreach (estados() as $uf): ?><option <?= $d['estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="linha-2">
          <div>
            <label for="senha">Senha</label>
            <div class="campo-icone">
              <?= icone('cadeado', 20) ?>
              <input type="password" id="senha" name="senha" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" data-forca<?= aria_erro($erros, 'senha') ?>>
              <button type="button" class="btn-olho" data-mostrar-senha="senha" aria-label="Mostrar senha"><?= icone('olho', 20) ?></button>
            </div>
            <div class="forca-senha" data-forca-barra><span></span></div>
            <?= erro_campo($erros, 'senha') ?>
          </div>
          <div>
            <label for="senha2">Confirmar senha</label>
            <div class="campo-icone">
              <?= icone('cadeado', 20) ?>
              <input type="password" id="senha2" name="senha2" required minlength="8" autocomplete="new-password" placeholder="Repita a senha"<?= aria_erro($erros, 'senha2') ?>>
              <button type="button" class="btn-olho" data-mostrar-senha="senha2" aria-label="Mostrar senha"><?= icone('olho', 20) ?></button>
            </div>
            <?= erro_campo($erros, 'senha2') ?>
          </div>
        </div>

        <label class="check aceite"><input type="checkbox" name="termos" value="1" required <?= !empty($_POST['termos']) ? 'checked' : '' ?>> <span>Li e aceito os <a href="<?= url('termos.php') ?>" target="_blank">termos de uso</a></span></label>
        <?= erro_campo($erros, 'termos') ?>

        <button class="btn btn-bloco btn-auth" type="submit" data-carregando="Criando conta..." data-texto-anunciante="<?= $planoEscolhido ? 'Criar conta e ir para o pagamento' : 'Criar conta e escolher plano' ?>">Criar conta</button>
        <p class="auth-seguro"><?= icone('cadeado', 16) ?>Seu CPF ou CNPJ não aparece no site; ele serve só para garantir que cada conta é de uma pessoa real.</p>
      </form>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
