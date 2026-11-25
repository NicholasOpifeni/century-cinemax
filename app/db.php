<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // MAMP's MySQL has no time zone tables, so use Kampala's offset. It's UTC+3 all year.
    $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

    return $pdo;
}

/** Prepares and runs a query in one go. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
