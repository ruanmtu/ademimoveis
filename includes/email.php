<?php
declare(strict_types=1);

/**
 * Envio de e-mail pela função mail() da hospedagem (mesmo método do BV Fotos na Hostinger).
 * Mensagem em duas versões (texto e HTML) para funcionar em qualquer leitor de e-mail.
 *
 * Em desenvolvimento/testes, defina MAIL_MODO = 'arquivo' no config.php:
 * o e-mail é gravado em MAIL_PASTA em vez de enviado.
 */

function email_remetente(): string
{
    $from = defined('MAIL_FROM') ? (string) MAIL_FROM : '';
    if ($from === '') {
        $host = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
        $host = preg_replace('/^www\./', '', $host);
        $from = 'nao-responda@' . $host;
    }
    return $from;
}

function enviar_email(string $para, string $assunto, string $html, string $texto): bool
{
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    $nome = defined('MAIL_FROM_NOME') && !in_array(MAIL_FROM_NOME, ['', 'ADemóveis', 'ADemoveis', 'Ademóveis', 'Ademoveis'], true) ? (string) MAIL_FROM_NOME : SITE_NOME;
    $from = email_remetente();
    $fronteira = 'adm_' . bin2hex(random_bytes(12));
    $assuntoCod = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

    $headers = [
        'MIME-Version: 1.0',
        'From: =?UTF-8?B?' . base64_encode($nome) . "?= <$from>",
        "Reply-To: $from",
        'Content-Type: multipart/alternative; boundary="' . $fronteira . '"',
        'X-Mailer: Ademimoveis',
    ];
    $corpo = "--$fronteira\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto)) . "\r\n"
        . "--$fronteira\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . "--$fronteira--\r\n";

    if (defined('MAIL_MODO') && MAIL_MODO === 'arquivo') {
        $pasta = defined('MAIL_PASTA') ? MAIL_PASTA : sys_get_temp_dir();
        @mkdir($pasta, 0700, true);
        return (bool) file_put_contents($pasta . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml',
            "To: $para\r\nSubject: $assunto\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $texto . "\r\n\r\n" . $html);
    }

    $ok = @mail($para, $assuntoCod, $corpo, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) error_log('[Ademimoveis] Falha na função mail() ao enviar e-mail.');
    return $ok;
}

/** E-mail de redefinição de senha no visual da Ademimóveis (tabelas e estilos inline). */
function email_recuperacao(string $nome, string $link, int $minutos): array
{
    $primeiro = trim(explode(' ', trim($nome))[0] ?? '') ?: 'Olá';
    $n = htmlspecialchars($primeiro, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $logo = htmlspecialchars(url('assets/img/logo-email.png'), ENT_QUOTES, 'UTF-8');
    $site = htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8');
    $quando = date('d/m/Y \à\s H:i');
    $ano = date('Y');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Redefinir senha</title></head>
<body style="margin:0;padding:0;background:#f5f3f0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3f0;padding:28px 12px;">
<tr><td align="center">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
    <tr><td style="height:14px;background:#c4621c;border-bottom:4px solid #e9a23b;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td align="center" style="background:#2a1a12;padding:26px 24px 22px;">
      <a href="{$site}"><img src="{$logo}" width="180" alt="Ademimóveis - Corretora de Imóveis" style="display:block;border:0;width:180px;max-width:100%;height:auto;"></a>
    </td></tr>
    <tr><td style="padding:34px 36px 8px;color:#221a15;">
      <p style="margin:0 0 6px;font-size:22px;font-weight:bold;color:#2a1a12;">{$n}, vamos criar uma senha nova?</p>
      <p style="margin:0 0 22px;font-size:15px;line-height:24px;color:#6b5f57;">Recebemos um pedido para redefinir a senha da sua conta na Ademimóveis em {$quando}. Clique no botão abaixo para escolher a nova senha.</p>
    </td></tr>
    <tr><td align="center" style="padding:0 36px 8px;">
      <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$l}" style="height:50px;v-text-anchor:middle;width:260px;" arcsize="24%" fillcolor="#c4621c" stroke="f"><center style="color:#ffffff;font-family:Arial;font-size:16px;font-weight:bold;">Criar nova senha</center></v:roundrect><![endif]-->
      <!--[if !mso]><!--><a href="{$l}" style="display:inline-block;background:#c4621c;color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;padding:15px 34px;border-radius:12px;border-bottom:3px solid #9d4a12;">Criar nova senha</a><!--<![endif]-->
    </td></tr>
    <tr><td style="padding:22px 36px 6px;">
      <p style="margin:0 0 10px;font-size:13px;line-height:20px;color:#6b5f57;">O link vale por <strong>{$minutos} minutos</strong> e só pode ser usado uma vez. Se o botão não abrir, copie este endereço no navegador:</p>
      <p style="margin:0 0 18px;font-size:12px;line-height:18px;word-break:break-all;"><a href="{$l}" style="color:#9d4a12;">{$l}</a></p>
      <p style="margin:0 0 26px;padding:12px 14px;background:#fdf4e4;border-radius:10px;font-size:13px;line-height:20px;color:#6b4106;">Não foi você? Pode ignorar este e-mail: sua senha atual continua a mesma.</p>
    </td></tr>
    <tr><td style="background:#2a1a12;padding:18px 30px;font-size:11px;line-height:18px;color:#bba795;" align="center">
      Mensagem automática, não precisa responder.<br>&copy; {$ano} Ademimóveis. Todos os direitos reservados.<br>Desenvolvido por <strong style="color:#ffffff;">BV Tecnologias</strong>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;

    $texto = "$primeiro, recebemos um pedido para redefinir a senha da sua conta na Ademimóveis em $quando.\n\n"
        . "Para criar uma nova senha, acesse:\n$link\n\n"
        . "O link vale por $minutos minutos e só pode ser usado uma vez.\n"
        . "Não foi você? Ignore este e-mail: sua senha atual continua a mesma.\n\n"
        . "Ademimóveis - Desenvolvido por BV Tecnologias";

    return [$html, $texto];
}
