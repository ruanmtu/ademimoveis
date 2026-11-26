<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_tipo('anunciante');

$id = (int) (get('id') ?: post('id'));
$im = null;
if ($id) {
    $im = one("SELECT * FROM imoveis WHERE id = ? AND anunciante_id = ? AND status <> 'removido'", [$id, $u['id']]);
    if (!$im) { flash('erro', 'Imóvel não encontrado.'); redirect('painel/imoveis.php'); }
} else {
    // novo imóvel: exige CPF/CNPJ informado, assinatura em dia e respeita o limite do plano
    if (!$u['documento']) {
        flash('aviso', 'Antes de anunciar, informe seu CPF ou CNPJ em Minha conta.');
        redirect('conta.php');
    }
    if (!anunciante_em_dia($u)) {
        flash('aviso', 'Ative sua assinatura para cadastrar imóveis.');
        redirect('painel/assinatura.php');
    }
    $limite = (int) val('SELECT limite_imoveis FROM planos WHERE id = ?', [$u['plano_id']]);
    $qtd = (int) val("SELECT COUNT(*) FROM imoveis WHERE anunciante_id = ? AND status <> 'removido'", [$u['id']]);
    if ($limite > 0 && $qtd >= $limite) {
        flash('aviso', "Seu plano permite até $limite imóveis. Exclua um anúncio ou mude de plano.");
        redirect('painel/imoveis.php');
    }
}

garantir_coluna_contato();
$limiteFotos = limite_fotos_usuario($u);
$zapConta = link_whatsapp($u['whatsapp']) ? $u['whatsapp'] : '';
$campos = ['titulo', 'descricao', 'finalidade', 'tipo', 'preco', 'condominio', 'iptu', 'area', 'quartos', 'banheiros',
           'vagas', 'cep', 'endereco', 'bairro', 'cidade', 'estado', 'status'];
$d = $im ?? array_fill_keys($campos, '') + ['status' => 'ativo', 'cidade' => $u['cidade'] ?? '', 'estado' => $u['estado'] ?? ''];
$comMarcadas = $im ? comodidades_do_imovel($id) : [];
$contatoZap = $im ? (int) ($im['contato_whatsapp'] ?? 1) : 1;
$zapDigitado = '';
$erros = [];

if (is_post()) {
    csrf_check();
    $acao = post('acao', 'salvar');

    // ---- ações nas fotos de um imóvel existente ----
    if ($im && in_array($acao, ['apagar_foto', 'capa'], true)) {
        $foto = one('SELECT * FROM imovel_fotos WHERE id = ? AND imovel_id = ?', [(int) post('foto_id'), $id]);
        if ($foto && $acao === 'apagar_foto') {
            apagar_upload($foto['arquivo']);
            q('DELETE FROM imovel_fotos WHERE id = ?', [$foto['id']]);
            flash('sucesso', 'Foto removida.');
        } elseif ($foto && $acao === 'capa') {
            q('UPDATE imovel_fotos SET ordem = ordem + 1 WHERE imovel_id = ?', [$id]);
            q('UPDATE imovel_fotos SET ordem = 0 WHERE id = ?', [$foto['id']]);
            flash('sucesso', 'Foto de capa atualizada.');
        }
        redirect('painel/imovel_form.php?id=' . $id . '#fotos');
    }

    // ---- salvar dados ----
    foreach ($campos as $c) $d[$c] = post($c);
    $comMarcadas = array_values(array_intersect(array_filter((array) ($_POST['comodidades'] ?? []), 'is_string'), array_keys(comodidades())));
    if (mb_strlen($d['titulo']) < 5) $erros[] = 'O título precisa ter pelo menos 5 caracteres.';
    if (!isset(finalidades()[$d['finalidade']])) $erros[] = 'Escolha a finalidade (venda, aluguel ou temporada).';
    if (!isset(tipos_imovel()[$d['tipo']])) $erros[] = 'Escolha o tipo do imóvel.';
    $preco = num_or_null($d['preco']);
    if ($preco === null || $preco <= 0) $erros[] = 'Informe um preço válido.';
    if ($d['cidade'] === '') $erros[] = 'Informe a cidade.';
    if (!in_array($d['estado'], estados(), true)) $erros[] = 'Escolha o estado.';
    if (!in_array($d['status'], ['ativo', 'pausado'], true)) $d['status'] = 'ativo';
    // contato: WhatsApp no anúncio ou somente o chat da plataforma
    $contatoZap = post('contato') === 'chat' ? 0 : 1;
    $zapDigitado = mb_substr(post('whatsapp_novo'), 0, 20);
    if ($contatoZap && !$zapConta) {
        if ($zapDigitado === '') $erros[] = 'Informe seu WhatsApp para mostrar no anúncio, ou escolha "Somente pelo chat".';
        elseif (!link_whatsapp($zapDigitado)) $erros[] = 'WhatsApp inválido. Use DDD + número, como (85) 98999-0000.';
    }

    $novas = arquivos_multiplos('fotos');
    $jaTem = $im ? (int) val('SELECT COUNT(*) FROM imovel_fotos WHERE imovel_id = ?', [$id]) : 0;
    if ($jaTem + count($novas) > $limiteFotos) $erros[] = 'Máximo de ' . $limiteFotos . ' fotos por imóvel.';

    if (!$erros) {
        $vals = [
            mb_substr($d['titulo'], 0, 150), mb_substr($d['descricao'], 0, 10000), $d['finalidade'], $d['tipo'], $preco,
            num_or_null($d['condominio']), num_or_null($d['iptu']), num_or_null($d['area']),
            min(255, max(0, (int) $d['quartos'])), min(255, max(0, (int) $d['banheiros'])), min(255, max(0, (int) $d['vagas'])),
            $d['cep'] ?: null, $d['endereco'] ?: null, $d['bairro'] ?: null, $d['cidade'], $d['estado'], $d['status'], agora(),
        ];
        if ($contatoZap && !$zapConta) {
            q('UPDATE usuarios SET whatsapp = ? WHERE id = ?', [$zapDigitado, $u['id']]);
            $zapConta = $zapDigitado;
        }
        if ($im) {
            q('UPDATE imoveis SET titulo=?, descricao=?, finalidade=?, tipo=?, preco=?, condominio=?, iptu=?, area=?, quartos=?,
               banheiros=?, vagas=?, cep=?, endereco=?, bairro=?, cidade=?, estado=?, status=?, atualizado_em=? WHERE id = ?',
              [...$vals, $id]);
        } else {
            q('INSERT INTO imoveis (titulo, descricao, finalidade, tipo, preco, condominio, iptu, area, quartos, banheiros, vagas,
               cep, endereco, bairro, cidade, estado, status, atualizado_em, anunciante_id, criado_em)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$vals, $u['id'], agora()]);
            $id = insert_id();
        }

        q('UPDATE imoveis SET contato_whatsapp = ? WHERE id = ?', [$contatoZap, $id]);
        q('DELETE FROM imovel_comodidades WHERE imovel_id = ?', [$id]);
        foreach ($comMarcadas as $c) {
            q('INSERT INTO imovel_comodidades (imovel_id, comodidade) VALUES (?, ?)', [$id, $c]);
        }

        $falhas = 0;
        $motivos = [];
        $ordem = (int) val('SELECT COALESCE(MAX(ordem), -1) FROM imovel_fotos WHERE imovel_id = ?', [$id]);
        foreach ($novas as $arq) {
            try {
                $rel = salvar_imagem($arq, 'imoveis', 2048);
                q('INSERT INTO imovel_fotos (imovel_id, arquivo, ordem, criado_em) VALUES (?,?,?,?)', [$id, $rel, ++$ordem, agora()]);
            } catch (RuntimeException $ex) {
                $falhas++;
                $motivos[$ex->getMessage()] = true;
            }
        }
        flash('sucesso', 'Imóvel salvo.' . ($falhas ? " $falhas foto(s) não puderam ser enviadas: " . implode(' ', array_keys($motivos)) : ''));
        redirect('painel/imovel_form.php?id=' . $id);
    }
}

$fotos = $id ? all('SELECT * FROM imovel_fotos WHERE imovel_id = ? ORDER BY ordem, id', [$id]) : [];
$titulo = $im ? 'Editar imóvel' : 'Novo imóvel';
$scripts = ['js/imovel_form.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1><?= e($titulo) ?></h1>
  <?php require __DIR__ . '/_nav.php'; ?>
  <?php if ($erros): ?><div class="alerta alerta-erro"><?= implode('<br>', array_map('e', $erros)) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="form-imovel">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="caixa">
      <h2>Informações principais</h2>
      <label>Título do anúncio <input type="text" name="titulo" value="<?= e($d['titulo']) ?>" required maxlength="150" placeholder="Ex.: Casa 3 quartos com quintal no Centro"></label>
      <div class="linha-3">
        <label>Finalidade
          <select name="finalidade" required><option value="">Selecione</option>
            <?php foreach (finalidades() as $k => $v): ?><option value="<?= $k ?>" <?= $d['finalidade'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Tipo
          <select name="tipo" required><option value="">Selecione</option>
            <?php foreach (tipos_imovel() as $k => $v): ?><option value="<?= $k ?>" <?= $d['tipo'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Situação
          <select name="status">
            <option value="ativo" <?= $d['status'] === 'ativo' ? 'selected' : '' ?>>Publicado</option>
            <option value="pausado" <?= $d['status'] === 'pausado' ? 'selected' : '' ?>>Pausado</option>
          </select>
        </label>
      </div>
      <label>Descrição <textarea name="descricao" rows="7" maxlength="10000" placeholder="Descreva o imóvel, diferenciais, proximidades..."><?= e($d['descricao']) ?></textarea></label>
    </div>

    <div class="caixa">
      <h2>Valores e características</h2>
      <div class="linha-3">
        <label>Preço (R$) <input type="text" inputmode="decimal" name="preco" value="<?= e($d['preco']) ?>" required placeholder="350.000,00"></label>
        <label>Condomínio (R$) <input type="text" inputmode="decimal" name="condominio" value="<?= e($d['condominio']) ?>"></label>
        <label>IPTU (R$) <input type="text" inputmode="decimal" name="iptu" value="<?= e($d['iptu']) ?>"></label>
      </div>
      <div class="linha-4">
        <label>Área (m²) <input type="text" inputmode="decimal" name="area" value="<?= e($d['area']) ?>"></label>
        <label>Quartos <input type="number" min="0" max="50" name="quartos" value="<?= e($d['quartos'] ?: 0) ?>"></label>
        <label>Banheiros <input type="number" min="0" max="50" name="banheiros" value="<?= e($d['banheiros'] ?: 0) ?>"></label>
        <label>Vagas <input type="number" min="0" max="50" name="vagas" value="<?= e($d['vagas'] ?: 0) ?>"></label>
      </div>
    </div>

    <div class="caixa">
      <h2>Comodidades</h2>
      <p class="dica">Marque o que o imóvel tem. Os clientes usam esses itens nos filtros de busca.</p>
      <div class="chips-check">
        <?php foreach (comodidades() as $k => [$rot, $ic]): ?>
          <label><input type="checkbox" name="comodidades[]" value="<?= e($k) ?>" <?= in_array($k, $comMarcadas, true) ? 'checked' : '' ?>><span><?= icone($ic, 18) ?><?= e($rot) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="caixa">
      <h2>Localização</h2>
      <div class="linha-3">
        <label>CEP <input type="text" name="cep" value="<?= e($d['cep']) ?>" maxlength="10" data-cep placeholder="00000-000"></label>
        <label class="span-2">Endereço (opcional, aparece no anúncio) <input type="text" name="endereco" value="<?= e($d['endereco']) ?>" maxlength="200"></label>
      </div>
      <div class="linha-3">
        <label>Bairro <input type="text" name="bairro" value="<?= e($d['bairro']) ?>" maxlength="120"></label>
        <label>Cidade <input type="text" name="cidade" value="<?= e($d['cidade']) ?>" required maxlength="120"></label>
        <label>Estado
          <select name="estado" required><option value="">--</option>
            <?php foreach (estados() as $uf): ?><option <?= $d['estado'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
    </div>

    <div class="caixa" id="contato">
      <h2>Como os interessados falam com você</h2>
      <p class="dica">O chat da Ademimóveis fica sempre disponível. Escolha se o seu número de WhatsApp também aparece neste anúncio.</p>
      <div class="opcoes-contato" data-opcoes-contato>
        <label class="opcao-contato">
          <input type="radio" name="contato" value="whatsapp" <?= $contatoZap ? 'checked' : '' ?>>
          <span>
            <span class="opcao-contato-icone opcao-zap"><?= icone('whatsapp', 22) ?></span>
            <strong>Chat e WhatsApp</strong>
            <small>O anúncio mostra um botão para chamar você no WhatsApp<?= $zapConta ? ', no número ' . e(formatar_telefone($zapConta)) : '' ?>. Resposta mais rápida para quem tem pressa.</small>
          </span>
        </label>
        <label class="opcao-contato">
          <input type="radio" name="contato" value="chat" <?= $contatoZap ? '' : 'checked' ?>>
          <span>
            <span class="opcao-contato-icone"><?= icone('cadeado', 22) ?></span>
            <strong>Somente pelo chat</strong>
            <small>Seu número fica escondido. Os interessados falam com você só pelo chat do site, e as mensagens chegam no seu painel.</small>
          </span>
        </label>
      </div>
      <?php if (!$zapConta): ?>
        <div class="campo-zap-novo" data-campo-zap>
          <label>Seu WhatsApp <input type="tel" name="whatsapp_novo" value="<?= e($zapDigitado) ?>" maxlength="20" placeholder="(00) 00000-0000" data-telefone></label>
          <p class="dica">Fica salvo na sua conta. Você pode trocar depois em <a href="<?= url('conta.php') ?>">Minha conta</a>.</p>
        </div>
      <?php else: ?>
        <p class="dica">Para trocar o número, vá em <a href="<?= url('conta.php') ?>">Minha conta</a>.</p>
      <?php endif; ?>
    </div>

    <div class="caixa" id="fotos">
      <h2>Fotos <small>(seu plano permite até <?= $limiteFotos ?>, até <?= limite_foto_mb() ?> MB cada)</small></h2>
      <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-preview
             data-max-mb="<?= limite_foto_mb() ?>" data-max-lado="2560">
      <p class="dica" data-status-fotos>Fotos grandes são reduzidas no seu aparelho antes de enviar, para subir mais rápido. A qualidade continua ótima para o anúncio.</p>
      <div class="preview-fotos" data-preview-area></div>
    </div>

    <button class="btn btn-grande" type="submit">Salvar imóvel</button>
  </form>

  <?php if ($fotos): ?>
    <div class="caixa mt">
      <h2>Fotos cadastradas</h2>
      <div class="fotos-gerenciar">
        <?php foreach ($fotos as $k => $fo): ?>
          <div class="foto-item">
            <img src="<?= e(upload_url($fo['arquivo'])) ?>" alt="">
            <?php if ($k === 0): ?><span class="etiqueta">Capa</span><?php endif; ?>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="foto_id" value="<?= (int) $fo['id'] ?>">
              <?php if ($k !== 0): ?><button class="btn btn-sm btn-claro" name="acao" value="capa">Usar como capa</button><?php endif; ?>
              <button class="btn btn-sm btn-perigo" name="acao" value="apagar_foto" data-confirmar="Remover esta foto?">Remover</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
