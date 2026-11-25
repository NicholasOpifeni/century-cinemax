<?php
declare(strict_types=1);

/*
 * Creates the database from config/secrets.php if it doesn't exist, then loads database/schema.sql.
 *
 *   bin/php bin/setup-db.php          create anything that's missing, keep existing data
 *   bin/php bin/setup-db.php --fresh  drop every table in this database first
 *
 * Only ever touches the database named in config/secrets.php.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

$fresh = in_array('--fresh', $argv, true);
$c = config('db');
$name = (string) $c['name'];

if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fwrite(STDERR, "The database name in config/secrets.php should only use letters, numbers and underscores.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']),
        $c['user'],
        $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Can't reach MySQL at {$c['host']}:{$c['port']}. Start MAMP's servers and try again.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

$tablesIn = static function () use ($pdo, $name): array {
    $stmt = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name');
    $stmt->execute([$name]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
};

if ($fresh) {
    $existing = $tablesIn();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($existing as $table) {
        $pdo->exec("DROP TABLE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo 'Dropped ' . count($existing) . " tables from $name.\n";
}

$sql = file_get_contents(ROOT . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

foreach (preg_split('/;\s*$/m', $sql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

$tables = $tablesIn();
echo "Database $name is ready with " . count($tables) . " tables:\n  " . implode(', ', $tables) . "\n";
