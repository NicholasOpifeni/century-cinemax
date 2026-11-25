<?php
declare(strict_types=1);

/*
 * Seeds cinemas, halls and their seats, prices, settings, snacks and the admin account
 * from database/seed.json and config/secrets.php.
 *
 *   bin/php database/seed.php
 *
 * Safe to run again. Cinemas, halls and seats are brought in line with seed.json.
 * Prices, settings, snacks and the admin account are only added if they're missing,
 * so changes made in the admin panel are kept.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\SeatMap;

$seed = json_decode((string) file_get_contents(__DIR__ . '/seed.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = db();
$pdo->beginTransaction();

$cinemaUpsert = $pdo->prepare(
    'INSERT INTO cinemas (slug, name, area, sort_order) VALUES (?, ?, ?, ?) AS new
     ON DUPLICATE KEY UPDATE name = new.name, area = new.area, sort_order = new.sort_order'
);
$hallUpsert = $pdo->prepare(
    'INSERT INTO halls (cinema_id, number, is_atmos, layout_json) VALUES (?, ?, ?, ?) AS new
     ON DUPLICATE KEY UPDATE is_atmos = new.is_atmos, layout_json = new.layout_json'
);
$seatUpsert = $pdo->prepare(
    'INSERT INTO seats (hall_id, row_label, seat_number, grid_col, label, type) VALUES (?, ?, ?, ?, ?, ?) AS new
     ON DUPLICATE KEY UPDATE grid_col = new.grid_col, type = new.type'
);

$hallCount = 0;
$seatCount = 0;
foreach ($seed['cinemas'] as $i => $cinema) {
    $cinemaUpsert->execute([$cinema['slug'], $cinema['name'], $cinema['area'], ($i + 1) * 10]);
    $cinemaId = (int) db_run('SELECT id FROM cinemas WHERE slug = ?', [$cinema['slug']])->fetchColumn();

    foreach ($cinema['halls'] as $hall) {
        $layout = $seed['layouts'][$hall['layout']] ?? throw new RuntimeException("Unknown layout {$hall['layout']}");
        $hallUpsert->execute([$cinemaId, $hall['number'], $hall['atmos'] ? 1 : 0, json_encode($layout)]);
        $hallId = (int) db_run('SELECT id FROM halls WHERE cinema_id = ? AND number = ?', [$cinemaId, $hall['number']])->fetchColumn();
        $hallCount++;

        foreach (SeatMap::seatsFromLayout($layout) as $seat) {
            $seatUpsert->execute([$hallId, $seat['row'], $seat['number'], $seat['col'], $seat['label'], $seat['type']]);
            $seatCount++;
        }
    }
}

$days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$priceInsert = $pdo->prepare('INSERT IGNORE INTO prices (day_of_week, base_price) VALUES (?, ?)');
$pricesAdded = 0;
foreach ($days as $dow => $day) {
    $priceInsert->execute([$dow, $seed['prices'][$day]]);
    $pricesAdded += $priceInsert->rowCount();
}

$settingInsert = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (?, ?)');
$settingsAdded = 0;
foreach ($seed['settings'] as $name => $value) {
    $settingInsert->execute([$name, (string) $value]);
    $settingsAdded += $settingInsert->rowCount();
}

$snacksAdded = 0;
foreach ($seed['snacks'] as $i => $snack) {
    $exists = db_run('SELECT 1 FROM snacks WHERE name = ?', [$snack['name']])->fetchColumn();
    if (!$exists) {
        db_run(
            'INSERT INTO snacks (name, description, price, sort_order) VALUES (?, ?, ?, ?)',
            [$snack['name'], $snack['description'], $snack['price'], ($i + 1) * 10],
        );
        $snacksAdded++;
    }
}

$adminEmail = (string) config('admin_email');
$adminAdded = false;
if (!db_run('SELECT 1 FROM admins WHERE email = ?', [$adminEmail])->fetchColumn()) {
    db_run(
        'INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)',
        ['Admin', $adminEmail, password_hash((string) config('admin_password'), PASSWORD_DEFAULT)],
    );
    $adminAdded = true;
}

$pdo->commit();

echo count($seed['cinemas']) . " cinemas, $hallCount halls and $seatCount seats are in place.\n";
echo "Added $pricesAdded prices, $settingsAdded settings and $snacksAdded snacks. Existing ones were kept.\n";
echo $adminAdded ? "Added the admin account $adminEmail.\n" : "The admin account $adminEmail already exists, so it was left alone.\n";
