<?php
declare(strict_types=1);

/*
 * Plans showtimes at every cinema, from today for 14 days.
 *
 *   bin/php bin/generate-showtimes.php
 *   bin/php bin/generate-showtimes.php --days=7 --from=2026-12-01
 *   bin/php bin/generate-showtimes.php --replace   re-plan days that already have shows
 *
 * Days that already have shows are skipped unless --replace is given. Replacing never
 * removes a show that has bookings or held seats.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Showtimes;

$options = getopt('', ['days:', 'from:', 'replace']);
$days = max(1, min(60, (int) ($options['days'] ?? 14)));
$replace = isset($options['replace']);

try {
    $start = new DateTimeImmutable($options['from'] ?? 'today');
} catch (Exception) {
    fwrite(STDERR, "Use --from=YYYY-MM-DD for the first day.\n");
    exit(1);
}

$cinemas = db()->query('SELECT id, name FROM cinemas ORDER BY sort_order')->fetchAll();
if (!$cinemas) {
    fwrite(STDERR, "There are no cinemas yet. Run bin/php database/seed.php first.\n");
    exit(1);
}

$total = 0;
for ($i = 0; $i < $days; $i++) {
    $day = $start->modify("+$i days");
    $parts = [];
    foreach ($cinemas as $cinema) {
        $added = Showtimes::generateDay((int) $cinema['id'], $day, $replace);
        $total += $added;
        $parts[] = "{$cinema['name']} $added";
    }
    echo $day->format('D j M') . ': ' . implode(', ', $parts) . "\n";
}

echo "\nAdded $total shows.";
echo $replace ? "\n" : " Days that already had shows were left alone.\n";
