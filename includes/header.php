<?php
/** @var string $titulo */
$u = usuario();
$naoLidas = $u ? mensagens_nao_lidas((int) $u['id']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($titulo) ? $titulo . ' | ' . SITE_NOME : SITE_NOME . ' - Imóveis para comprar e alugar') ?></title>
<meta name="description" content="<?= e($descricaoPagina ?? 'Ademóveis: encontre casas, apartamentos e terrenos para comprar ou alugar e fale direto com o anunciante.') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= asset('img/favicon.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('img/favicon.png') ?>">
<meta name="theme-color" content="#3b2418">
<?php
  $ogTitulo = isset($titulo) ? $titulo . ' | ' . SITE_NOME : SITE_NOME . ' - Imóveis para comprar e alugar';
  $ogDescricao = $descricaoPagina ?? 'Ademóveis: encontre casas, apartamentos e terrenos para comprar ou alugar e fale direto com o anunciante.';
  $ogUrl = BASE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NOME) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="<?= e($ogTitulo) ?>">
<meta property="og:description" content="<?= e($ogDescricao) ?>">
<meta property="og:url" content="<?= e($ogUrl) ?>">
<meta property="og:image" content="<?= e($ogImagem ?? url('assets/img/logo-completa.png')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Dancing+Script:wght@600;700&family=Figtree:ital,wght@0,400..700;1,400&display=swap">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
<header class="topo">
  <div class="container topo-inner">
    <a class="logo" href="<?= url('index.php') ?>">
      <img src="<?= asset('img/logo-topo.webp') ?>" alt="Ademóveis - Corretora de Imóveis" width="125" height="52">
    </a>
    <button class="menu-toggle" aria-label="Abrir menu" data-toggle-menu>☰</button>
    <nav class="menu" id="menu">
      <a href="<?= url('imoveis.php?finalidade=venda') ?>">Comprar</a>
      <a href="<?= url('imoveis.php?finalidade=aluguel') ?>">Alugar</a>
      <?php if ($u): ?>
        <a href="<?= url('chat/index.php') ?>">Mensagens<?php if ($naoLidas): ?> <span class="badge"><?= $naoLidas ?></span><?php endif; ?></a>
        <?php if ($u['tipo'] === 'anunciante'): ?>
          <a href="<?= url('painel/index.php') ?>">Meu painel</a>
        <?php elseif ($u['tipo'] === 'admin'): ?>
          <a href="<?= url('admin/index.php') ?>">Administração</a>
        <?php endif; ?>
        <a href="<?= url('conta.php') ?>" class="menu-usuario"><?= e(explode(' ', $u['nome'])[0]) ?></a>
        <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-claro">Sair</a>
      <?php else: ?>
        <a href="<?= url('cadastro.php?tipo=anunciante') ?>">Anuncie seu imóvel</a>
        <a href="<?= url('login.php') ?>" class="btn btn-sm">Entrar</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?php $fl = flashes(); if ($fl): ?>
  <div class="container flashes">
    <?php foreach ($fl as $fm): ?>
      <div class="alerta alerta-<?= e($fm['tipo']) ?>"><?= e($fm['msg']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
