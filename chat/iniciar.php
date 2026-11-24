<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = exigir_login();
if (!is_post()) redirect('index.php');
csrf_check();

$anuncId = (int) post('anunciante_id');
$imovelId = (int) post('imovel_id') ?: null;
$texto = mb_substr(post('mensagem'), 0, 2000);

$anunc = one("SELECT * FROM usuarios WHERE id = ? AND tipo = 'anunciante' AND status = 'ativo'", [$anuncId]);
if (!$anunc || $anuncId === (int) $u['id']) {
    flash('erro', 'Não é possível iniciar esta conversa.');
    redirect('index.php');
}
if ($imovelId && !val('SELECT 1 FROM imoveis WHERE id = ? AND anunciante_id = ?', [$imovelId, $anuncId])) {
    $imovelId = null;
}

$sql = 'SELECT id FROM conversas WHERE interessado_id = ? AND anunciante_id = ? AND ' . ($imovelId ? 'imovel_id = ?' : 'imovel_id IS NULL');
$conv = val($sql, $imovelId ? [$u['id'], $anuncId, $imovelId] : [$u['id'], $anuncId]);

if (!$conv) {
    q('INSERT INTO conversas (interessado_id, anunciante_id, imovel_id, criado_em, atualizado_em) VALUES (?,?,?,?,?)',
      [$u['id'], $anuncId, $imovelId, agora(), agora()]);
    $conv = insert_id();
}
if ($texto !== '') {
    q('INSERT INTO mensagens (conversa_id, remetente_id, texto, criado_em) VALUES (?,?,?,?)', [$conv, $u['id'], $texto, agora()]);
    q('UPDATE conversas SET atualizado_em = ? WHERE id = ?', [agora(), $conv]);
}
redirect('chat/conversa.php?id=' . (int) $conv);
