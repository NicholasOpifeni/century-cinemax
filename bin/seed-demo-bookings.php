<?php
declare(strict_types=1);

/*
 * Fills upcoming shows with made-up paid bookings, so the seat maps look like a real week.
 * Every demo booking uses the phone number in Bookings::DEMO_PHONE, so they're easy to filter
 * out or remove. Nothing here is needed for the site to work.
 *
 *   bin/php bin/seed-demo-bookings.php            fill shows that don't have demo bookings yet
 *   bin/php bin/seed-demo-bookings.php --days=3   only the next three days
 *   bin/php bin/seed-demo-bookings.php --clear    remove every demo booking
 *
 * A show is filled to between 10 and 45 percent: busier at weekends and in the evening, with
 * people sitting in small groups and the middle of the hall going first.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Bookings;
use App\Pricing;
use App\Settings;
use Random\Engine\Mt19937;
use Random\Randomizer;

$options = getopt('', ['days:', 'clear']);

if (isset($options['clear'])) {
    $removed = db_run('DELETE FROM bookings WHERE phone = ?', [Bookings::DEMO_PHONE])->rowCount();
    echo "Removed $removed demo bookings.\n";
    exit(0);
}

$days = max(1, min(60, (int) ($options['days'] ?? 14)));
$until = (new DateTimeImmutable('today'))->modify("+$days days")->format('Y-m-d 00:00:00');

$shows = db_run(
    'SELECT s.id, s.hall_id, s.starts_at, s.format FROM showtimes s
     WHERE s.starts_at > NOW() AND s.starts_at < ?
       AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.showtime_id = s.id AND b.phone = ?)
     ORDER BY s.starts_at',
    [$until, Bookings::DEMO_PHONE],
)->fetchAll();

$glasses = Settings::int('glasses_price');
$methods = ['mtn', 'mtn', 'mtn', 'airtel', 'airtel', 'card'];
$groupSizes = [1, 2, 2, 2, 2, 3, 3, 4, 4, 5, 6];
$hallSeats = [];
$bookingCount = 0;
$seatCount = 0;

$insertBooking = db()->prepare(
    "INSERT INTO bookings (ref, showtime_id, phone, status, pay_method, gateway_ref, seat_labels, seat_count,
                           seats_total, glasses_total, total, created_at, paid_at)
     VALUES (?, ?, ?, 'paid', ?, 'demo', ?, ?, ?, ?, ?, NOW(), NOW())"
);
$insertSeat = db()->prepare('INSERT INTO booking_seats (booking_id, showtime_id, seat_id, price) VALUES (?, ?, ?, ?)');

foreach ($shows as $show) {
    $showId = (int) $show['id'];
    $startsAt = new DateTimeImmutable($show['starts_at']);
    $rand = new Randomizer(new Mt19937(crc32("demo|$showId")));
    $fraction = fn (float $max): float => $rand->getInt(0, 1_000_000) / 1_000_000 * $max; // getFloat() needs PHP 8.3

    $hallSeats[$show['hall_id']] ??= db_run(
        'SELECT id, row_label, seat_number, grid_col, label, type FROM seats WHERE hall_id = ? ORDER BY row_label, seat_number',
        [$show['hall_id']],
    )->fetchAll();

    // Seats someone really booked or is holding stay as they are.
    $taken = array_flip(db_run(
        'SELECT seat_id FROM booking_seats WHERE showtime_id = ? UNION SELECT seat_id FROM seat_holds WHERE showtime_id = ?',
        [$showId, $showId],
    )->fetchAll(PDO::FETCH_COLUMN));

    $byRow = [];
    $byId = [];
    $weights = [];
    foreach ($hallSeats[$show['hall_id']] as $seat) {
        $byRow[$seat['row_label']][(int) $seat['seat_number']] = $seat;
        $byId[(int) $seat['id']] = $seat;
        // The middle of the hall fills first, the recliners a little more slowly.
        $centre = (int) $seat['grid_col'] >= 8 && (int) $seat['grid_col'] <= 13 && str_contains('DEFGH', $seat['row_label']);
        $weights[(int) $seat['id']] = $seat['type'] === 'recliner' ? 0.8 : ($centre ? 1.6 : 0.85);
    }

    $weekend = in_array((int) $startsAt->format('w'), [0, 5, 6], true);
    $evening = (int) $startsAt->format('G') >= 18;
    $target = (int) round(count($weights) * (($weekend ? 0.2 : 0.1) + ($evening ? 0.05 : 0) + $fraction(0.2)));

    $sold = [];
    $groups = [];
    for ($tries = 0; count($sold) < $target && $tries < 400; $tries++) {
        // Pick a first seat, favouring the heavier ones.
        $pick = null;
        $sum = 0.0;
        foreach ($weights as $id => $weight) {
            if (!isset($taken[$id]) && !isset($sold[$id])) {
                $sum += $weight;
                if ($fraction($sum) < $weight) {
                    $pick = $id;
                }
            }
        }
        if ($pick === null) {
            break;
        }
        // Then the people with them sit along the same row.
        $first = $byId[$pick];
        $size = min($groupSizes[$rand->getInt(0, count($groupSizes) - 1)], $target - count($sold));
        $group = [];
        for ($n = (int) $first['seat_number']; count($group) < $size && isset($byRow[$first['row_label']][$n]); $n++) {
            $seat = $byRow[$first['row_label']][$n];
            if (isset($taken[$seat['id']]) || isset($sold[$seat['id']])) {
                break;
            }
            $group[] = $seat;
            $sold[$seat['id']] = true;
        }
        if ($group) {
            $groups[] = $group;
        }
    }

    db()->beginTransaction();
    foreach ($groups as $group) {
        $prices = array_map(fn (array $s): int => Pricing::seatPrice($startsAt, $show['format'], $s['type']), $group);
        $seatsTotal = array_sum($prices);
        $glassesTotal = $show['format'] === '3D' ? count($group) * $glasses : 0;
        $insertBooking->execute([
            Bookings::newRef(), $showId, Bookings::DEMO_PHONE, $methods[$rand->getInt(0, count($methods) - 1)],
            implode(' ', array_column($group, 'label')), count($group), $seatsTotal, $glassesTotal, $seatsTotal + $glassesTotal,
        ]);
        $bookingId = (int) db()->lastInsertId();
        foreach ($group as $i => $seat) {
            $insertSeat->execute([$bookingId, $showId, $seat['id'], $prices[$i]]);
        }
        $bookingCount++;
        $seatCount += count($group);
    }
    db()->commit();
}

printf("Added %d demo bookings (%d seats) across %d shows.\n", $bookingCount, $seatCount, count($shows));
