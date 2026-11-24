<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            if (DB_DRIVER === 'sqlite') {
                // usado apenas em testes automatizados
                $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $opts);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            exit(APP_DEBUG ? 'Erro ao conectar no banco: ' . $e->getMessage()
                           : 'Erro ao conectar no banco de dados. Verifique config/config.php.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert_id(): int
{
    return (int) db()->lastInsertId();
}
