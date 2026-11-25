<?php
// Copy this file to config/secrets.php and fill it in. secrets.php is gitignored.
return [
    'db' => ['host' => '127.0.0.1', 'port' => 8889, 'name' => 'cinemax', 'user' => 'root', 'pass' => 'root'],
    // TMDB API Read Access Token, from https://www.themoviedb.org/settings/api
    'tmdb_token' => 'your-tmdb-read-access-token',
    // 32 random bytes as hex: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
    'app_key' => 'replace-with-64-hex-characters',
    'admin_email' => 'admin@cinemax.test',
    'admin_password' => 'choose-a-long-password',
    // How long the demo MoMo and Airtel payments wait before they approve themselves.
    'demo_payment_seconds' => 5,
    'base_url' => 'http://localhost:8090',
];
