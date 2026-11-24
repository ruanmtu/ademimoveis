<?php
declare(strict_types=1);

/**
 * Validação de CPF, CNPJ (numérico e alfanumérico) e CRECI.
 *
 * CNPJ alfanumérico (Receita Federal, a partir de jul/2026): as 12 primeiras posições
 * podem ter letras A-Z; os 2 dígitos verificadores continuam numéricos. O cálculo é
 * o módulo 11 de sempre, com cada caractere valendo (código ASCII - 48):
 * '0'..'9' = 0..9, 'A' = 17, 'B' = 18 ... 'Z' = 42.
 *
 * O dígito verificador só prova que o número é bem formado, não que a pessoa ou
 * empresa existe. CRECI não tem dígito verificador: aqui conferimos o formato e a
 * administração confirma na consulta pública do CRECI do estado.
 */

function limpar_documento(string $v): string
{
    return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $v));
}

function validar_cpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += (int) $cpf[$i] * (($t + 1) - $i);
        $dv = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $dv) return false;
    }
    return true;
}

function cnpj_dv(string $base): int
{
    $pesos = strlen($base) === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $soma = 0;
    foreach (str_split($base) as $i => $c) $soma += (ord($c) - 48) * $pesos[$i];
    $resto = $soma % 11;
    return $resto < 2 ? 0 : 11 - $resto;
}

function validar_cnpj(string $cnpj): bool
{
    $cnpj = limpar_documento($cnpj);
    if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj)) return false;
    if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    $dv1 = cnpj_dv(substr($cnpj, 0, 12));
    $dv2 = cnpj_dv(substr($cnpj, 0, 12) . $dv1);
    return $cnpj[12] === (string) $dv1 && $cnpj[13] === (string) $dv2;
}

/** Descobre o tipo e valida. Retorna ['tipo' => 'cpf'|'cnpj', 'numero' => '...'] ou null. */
function identificar_documento(string $v): ?array
{
    $d = limpar_documento($v);
    if (strlen($d) === 11 && ctype_digit($d) && validar_cpf($d)) return ['tipo' => 'cpf', 'numero' => $d];
    if (strlen($d) === 14 && validar_cnpj($d)) return ['tipo' => 'cnpj', 'numero' => $d];
    return null;
}

function formatar_documento(?string $d): string
{
    if (!$d) return '';
    if (strlen($d) === 11) return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $d);
    if (strlen($d) === 14) return preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $d);
    return $d;
}

/** Mostra o documento parcialmente (para telas que não precisam do número inteiro). */
function mascarar_documento(?string $d): string
{
    if (!$d) return '';
    $f = formatar_documento($d);
    return strlen($d) === 11 ? '***.' . substr($f, 4, 7) . '-**' : $f;
}

/**
 * Normaliza o CRECI. Aceita "12345", "12345-F", "CRECI 12.345-J" etc.
 * O sufixo segue o tipo do anunciante: F para pessoa física (CPF), J para jurídica (CNPJ).
 * Retorna "12345-F" ou null se o formato for inválido.
 */
function normalizar_creci(string $creci, string $tipoDoc): ?string
{
    $c = strtoupper(preg_replace('/^\s*CRECI[\s\-\/]*[A-Z]{0,2}[\s\-:]*/i', '', trim($creci)));
    if (!preg_match('/^(\d{1,3}(?:\.?\d{3})?|\d{1,7})\s*-?\s*([FJ])?$/', $c, $m)) return null;
    $num = ltrim(str_replace('.', '', $m[1]), '0');
    if ($num === '' || strlen($num) > 7) return null;
    $sufixoEsperado = $tipoDoc === 'cnpj' ? 'J' : 'F';
    if (!empty($m[2]) && $m[2] !== $sufixoEsperado) return null;
    return $num . '-' . $sufixoEsperado;
}

function creci_status_label(string $s): string
{
    return ['nao_informado' => 'Não informado', 'pendente' => 'Aguardando verificação',
            'verificado' => 'Verificado', 'recusado' => 'Não confirmado'][$s] ?? $s;
}

/** Link de busca para a consulta pública do CRECI do estado (cada estado tem seu site). */
function link_consulta_creci(string $uf, string $creci): string
{
    return 'https://www.google.com/search?q=' . rawurlencode("CRECI-$uf consulta pública corretor $creci");
}
