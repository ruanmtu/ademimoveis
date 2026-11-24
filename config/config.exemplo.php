<?php
/**
 * ADemóveis - Configurações
 * Copie este arquivo para "config.php" e preencha com seus dados.
 */

// Nome exibido no site
define('APP_NAME', 'ADemóveis');

// Endereço do site, SEM barra no final. Deixe vazio ('') para detectar automaticamente.
// Exemplos: 'https://www.ademoveis.com.br'  ou  'http://localhost/ademoveis'
define('APP_URL', '');

// true mostra erros detalhados (use só no desenvolvimento)
define('APP_DEBUG', false);

define('TIMEZONE', 'America/Sao_Paulo');

// ---------------- Banco de dados (MySQL / phpMyAdmin) ----------------
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'ademoveis');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---------------- Mercado Pago ----------------
// Painel: https://www.mercadopago.com.br/developers/panel/app
// Use as credenciais de TESTE enquanto estiver testando.
define('MP_ACCESS_TOKEN', '');
// "Assinatura secreta" das notificações Webhooks (opcional, mas recomendado)
define('MP_WEBHOOK_SECRET', '');
// Dias extras de tolerância depois do vencimento antes de tirar os anúncios do ar
define('ASSINATURA_TOLERANCIA_DIAS', 3);

// ---------------- Uploads ----------------
define('UPLOAD_MAX_MB', 8);          // tamanho máximo por foto
define('FOTOS_POR_IMOVEL', 20);      // limite de fotos por imóvel
