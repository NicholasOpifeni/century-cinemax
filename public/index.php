<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use chillerlan\QRCode\QRCode;

// Setup check. The home page replaces this.
$checks = [['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>=')]];

$missing = array_filter(['pdo_mysql', 'gd', 'curl', 'mbstring', 'openssl'], fn ($ext) => !extension_loaded($ext));
$checks[] = [$missing ? 'Missing PHP extensions: ' . implode(', ', $missing) : 'PHP extensions: pdo_mysql, gd, curl, mbstring, openssl', !$missing];

try {
    $version = db()->query('SELECT VERSION()')->fetchColumn();
    $tables = (int) db()->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    $dbNow = (string) db()->query('SELECT NOW()')->fetchColumn();
    $checks[] = ["MySQL $version, database " . config('db.name') . " with $tables tables", $tables > 0];
    $checks[] = ["Kampala time in PHP and MySQL: $dbNow", abs(strtotime($dbNow) - time()) < 5];
} catch (PDOException) {
    $checks[] = ['Can’t reach MySQL on port ' . config('db.port') . '. Start MAMP’s servers, then run bin/setup-db.php.', false];
}

try {
    $checks[] = ['QR code library', str_starts_with((new QRCode())->render(app_url('/')), 'data:image/svg+xml')];
} catch (Throwable) {
    $checks[] = ['QR code library failed to render', false];
}

$allOk = !in_array(false, array_column($checks, 1), true);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup check | Cinemax</title>
<link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
<main class="plain">
  <a class="wm" href="/" aria-label="Century Cinemax home"><b>CENTURY</b><span>CINEMAX</span></a>
  <h1><?= $allOk ? 'Everything’s connected' : 'Something needs fixing' ?></h1>
  <p>A quick check that the server, database and libraries are ready.</p>
  <ul class="checks">
    <?php foreach ($checks as [$label, $ok]): ?>
      <li class="<?= $ok ? 'ok' : 'fail' ?>"><?= e($label) ?></li>
    <?php endforeach; ?>
  </ul>
  <a class="btn btn-sm btn-o" href="/nothing-here">Try the 404 page</a>
</main>
</body>
</html>
