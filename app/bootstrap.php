<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

date_default_timezone_set('Africa/Kampala');

// The built-in dev server shows errors in the page; anything else only logs them.
error_reporting(E_ALL);
ini_set('display_errors', in_array(PHP_SAPI, ['cli', 'cli-server'], true) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

if (!is_file(ROOT . '/config/secrets.php')) {
    http_response_code(500);
    exit("Missing config/secrets.php. Copy config/secrets.example.php to config/secrets.php and fill it in.\n");
}
if (!is_file(ROOT . '/vendor/autoload.php')) {
    http_response_code(500);
    exit("Missing vendor/. Run composer install first.\n");
}

require ROOT . '/vendor/autoload.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/ui.php';

if (PHP_SAPI !== 'cli') {
    start_session();
    send_base_headers();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();
    }
}
