<?php
require __DIR__ . '/../includes/bootstrap.php';
exigir_tipo('admin');
seg_tabela();

$nomes = [
    'login_ok' => 'Entrou na conta', 'login_falha' => 'Senha errada', 'login_bloqueado' => 'Login bloqueado (muitas tentativas)',
    'cadastro' => 'Conta criada', 'reset_pedido' => 'Pediu link de nova senha', 'senha_redefinida' => 'Senha redefinida pelo e-mail',
    'senha_trocada' => 'Senha trocada em Minha conta', 'senha_atual_falha' => 'Senha atual errada (Minha conta)',
    'conversa_nova' => 'Iniciou conversa',
];
$filtro = get('evento');
if ($filtro !== '' && !isset($nomes[$filtro])) $filtro = '';

// login_falha_ip duplica login_falha (serve só para o limite por IP); não aparece na lista
$onde = $filtro !== '' ? 'WHERE s.acao = ?' : "WHERE s.acao <> 'login_falha_ip'";
$params = $filtro !== '' ? [$filtro] : [];
$total = (int) val("SELECT COUNT(*) FROM seguranca_eventos s $onde", $params);
$pg = paginar($total, 50);
$lista = all("SELECT s.*, u.nome, u.email FROM seguranca_eventos s LEFT JOIN usuarios u ON u.id = s.usuario_id
              $onde ORDER BY s.id DESC LIMIT {$pg['limite']} OFFSET {$pg['offset']}", $params);

$dia = date('Y-m-d H:i:s', time() - 86400);
$n = [
    'falhas' => (int) val("SELECT COUNT(*) FROM seguranca_eventos WHERE acao = 'login_falha' AND criado_em >= ?", [$dia]),
    'bloqueios' => (int) val("SELECT COUNT(*) FROM seguranca_eventos WHERE acao = 'login_bloqueado' AND criado_em >= ?", [$dia]),
    'ips' => (int) val("SELECT COUNT(DISTINCT ip) FROM seguranca_eventos WHERE acao = 'login_falha' AND criado_em >= ?", [$dia]),
    'logins' => (int) val("SELECT COUNT(*) FROM seguranca_eventos WHERE acao = 'login_ok' AND criado_em >= ?", [$dia]),
];

$appUrlFixo = (string) parse_url((string) APP_URL, PHP_URL_HOST);
$appUrlFixo = $appUrlFixo !== '' && !in_array($appUrlFixo, ['localhost', '127.0.0.1'], true);
$verif = [
    [conexao_https(), 'Conexão segura (HTTPS)', 'Todas as páginas abrem com cadeado; quem entra por http:// é levado para https://.',
        'O site está abrindo sem HTTPS. Ative o SSL no hPanel: Segurança → SSL.'],
    [!APP_DEBUG, 'Erros técnicos escondidos', 'Visitantes não veem mensagens internas do sistema.',
        'APP_DEBUG está ligado no config.php. Troque para false.'],
    [!is_file(ROOT_PATH . '/instalar.php'), 'Instalador removido', 'O arquivo instalar.php não está no servidor.',
        'Apague o arquivo instalar.php pelo Gerenciador de Arquivos do hPanel (ele já está travado, mas não precisa mais existir).'],
    [MP_ACCESS_TOKEN === '' || MP_WEBHOOK_SECRET !== '', 'Notificações do Mercado Pago assinadas', 'O site confere a assinatura secreta de cada aviso do Mercado Pago.',
        'Falta a assinatura secreta (MP_WEBHOOK_SECRET) no config.php. Mesmo sem ela, o site confirma cada pagamento direto com o Mercado Pago.'],
    [$appUrlFixo, 'Endereço do site fixo', 'Os links de nova senha enviados por e-mail sempre apontam para ' . e((string) APP_URL) . '.',
        'Defina APP_URL no config.php com o endereço do site (ex.: https://www.ademimoveis.com.br). Isso garante que o link de nova senha sempre aponte para o seu domínio.'],
    [is_file(UPLOAD_PATH . '/.htaccess'), 'Pasta de fotos protegida', 'Arquivos enviados pelos usuários nunca são executados como programa.',
        'O arquivo uploads/.htaccess sumiu. Publique o site de novo pelo GitHub.'],
    [version_compare(PHP_VERSION, '8.1', '>='), 'PHP atualizado (' . PHP_VERSION . ')', 'Versão com suporte de segurança.',
        'Atualize o PHP para 8.2 ou mais novo no hPanel: Avançado → Configuração do PHP.'],
];

$titulo = 'Segurança';
require __DIR__ . '/../includes/header.php';
?>
<div class="container secao">
  <h1>Segurança</h1>
  <?php require __DIR__ . '/_nav.php'; ?>

  <div class="cards-resumo">
    <div class="caixa"><span>Senhas erradas (24 h)</span><strong><?= $n['falhas'] ?></strong><small>de <?= $n['ips'] ?> conexão(ões)</small></div>
    <div class="caixa"><span>Logins bloqueados (24 h)</span><strong><?= $n['bloqueios'] ?></strong><small>5 erros seguidos travam a conta por 15 min</small></div>
    <div class="caixa"><span>Entradas com sucesso (24 h)</span><strong><?= $n['logins'] ?></strong></div>
  </div>

  <h2 class="mt">Verificação do servidor</h2>
  <ul class="verificacao caixa">
    <?php foreach ($verif as [$ok, $titulo_, $txtOk, $txtFalta]): ?>
      <li class="<?= $ok ? 'ok' : 'falta' ?>">
        <span class="verificacao-icone" aria-hidden="true"><?= $ok ? '✓' : '!' ?></span>
        <div><strong><?= e($titulo_) ?></strong><small><?= $ok ? $txtOk : e($txtFalta) ?></small></div>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="secao-titulo mt">
    <h2>Registro de acessos <small>(<?= $total ?>)</small></h2>
    <form method="get" class="form-filtro-curto">
      <select name="evento" aria-label="Filtrar por evento">
        <option value="">Todos os eventos</option>
        <?php foreach ($nomes as $k => $rot): ?><option value="<?= e($k) ?>" <?= $filtro === $k ? 'selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-claro">Filtrar</button>
    </form>
  </div>
  <p class="dica">Os registros ficam guardados por 6 meses, como pede o Marco Civil da Internet. Muitos erros de senha seguidos para a mesma conta indicam alguém tentando adivinhar a senha: o site bloqueia sozinho, e você pode bloquear o usuário em <a href="<?= url('admin/usuarios.php') ?>">Usuários</a> se for o caso.</p>
  <?php if (!$lista): ?>
    <p class="vazio">Nenhum evento registrado ainda.</p>
  <?php else: ?>
  <div class="tabela-wrap">
  <table class="tabela">
    <thead><tr><th>Data</th><th>Evento</th><th>Conta</th><th>Detalhe</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $ev): ?>
      <tr class="<?= in_array($ev['acao'], ['login_falha', 'login_bloqueado', 'senha_atual_falha'], true) ? 'linha-alerta' : '' ?>">
        <td><small><?= data_br($ev['criado_em'], true) ?></small></td>
        <td><?= e($nomes[$ev['acao']] ?? $ev['acao']) ?></td>
        <td><?= $ev['usuario_id'] && $ev['nome'] !== null ? '<a href="' . url('admin/usuarios.php?id=' . (int) $ev['usuario_id']) . '">' . e($ev['nome']) . '</a><br><small>' . e($ev['email']) . '</small>' : '-' ?></td>
        <td><small><?= e($ev['detalhe'] ?? '-') ?></small></td>
        <td><small><?= e($ev['ip'] ?? '-') ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?= links_paginacao($pg) ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
