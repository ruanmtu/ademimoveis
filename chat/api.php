<?php
/**
 * API do chat (JSON).
 *  GET  ?conversa=ID&depois=ULTIMO_ID   -> lista mensagens novas e marca como lidas
 *  POST conversa=ID, texto=...          -> envia mensagem (exige header X-CSRF-Token)
 */
require __DIR__ . '/../includes/bootstrap.php';

pagina_privada();
$u = usuario();
if (!$u) json_resposta(['erro' => 'Não autenticado'], 401);
$uid = (int) $u['id'];

$convId = (int) ($_GET['conversa'] ?? $_POST['conversa'] ?? 0);
$c = one('SELECT * FROM conversas WHERE id = ?', [$convId]);
$participante = $c && in_array($uid, [(int) $c['interessado_id'], (int) $c['anunciante_id']], true);
if (!$c || (!$participante && !e_admin())) json_resposta(['erro' => 'Conversa não encontrada'], 404);

if (is_post()) {
    csrf_check();
    if (!$participante) json_resposta(['erro' => 'Somente leitura'], 403);
    $texto = trim((string) ($_POST['texto'] ?? ''));
    if ($texto === '') json_resposta(['erro' => 'Mensagem vazia'], 422);
    $texto = mb_substr($texto, 0, 2000);

    // limite anti-spam: no máximo 20 mensagens por minuto
    $recentes = (int) val('SELECT COUNT(*) FROM mensagens WHERE remetente_id = ? AND criado_em >= ?',
                          [$uid, date('Y-m-d H:i:s', time() - 60)]);
    if ($recentes >= 20) json_resposta(['erro' => 'Muitas mensagens em pouco tempo. Aguarde um instante.'], 429);

    q('INSERT INTO mensagens (conversa_id, remetente_id, texto, criado_em) VALUES (?,?,?,?)', [$convId, $uid, $texto, agora()]);
    $mid = insert_id();
    q('UPDATE conversas SET atualizado_em = ? WHERE id = ?', [agora(), $convId]);
    json_resposta(['ok' => true, 'id' => $mid]);
}

$depois = max(0, (int) ($_GET['depois'] ?? 0));
$msgs = all('SELECT m.id, m.remetente_id, m.texto, m.lida, m.criado_em, us.nome
             FROM mensagens m JOIN usuarios us ON us.id = m.remetente_id
             WHERE m.conversa_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 200', [$convId, $depois]);

if ($participante && $msgs) {
    q('UPDATE mensagens SET lida = 1 WHERE conversa_id = ? AND remetente_id <> ? AND lida = 0', [$convId, $uid]);
}

// até qual das minhas mensagens a outra pessoa já leu (para o "visto")
$lidasAte = (int) val('SELECT COALESCE(MAX(id), 0) FROM mensagens WHERE conversa_id = ? AND remetente_id = ? AND lida = 1', [$convId, $uid]);

json_resposta(['mensagens' => array_map(fn ($m) => [
    'id'    => (int) $m['id'],
    'meu'   => (int) $m['remetente_id'] === $uid,
    'nome'  => $m['nome'],
    'texto' => $m['texto'],
    'hora'  => data_br($m['criado_em'], true),
    'dia'   => date('Y-m-d', strtotime($m['criado_em'])),
    'hm'    => date('H:i', strtotime($m['criado_em'])),
], $msgs), 'lidas_ate' => $lidasAte, 'hoje' => date('Y-m-d')]);
