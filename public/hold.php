<?php
declare(strict_types=1);

// Checkout on the seat map posts here. The seats are held for hold_minutes and the customer goes on
// to pay. If anyone got to a seat first, nothing is held and they go back to the map with it marked.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Films;
use App\Holds;
use App\Settings;
use App\Showtimes;

if (!is_post()) {
    redirect('/');
}

$show = Showtimes::find((int) ($_POST['show'] ?? 0));
$seatsPage = $show ? '/seats.php?show=' . $show['id'] : '/';

/** Answers with JSON for seats.js, or a flash message and a redirect for a plain form post. */
$reply = static function (bool $ok, string $message, int $status, string $to, array $extra = []): never {
    if (wants_json()) {
        json_out(['ok' => $ok, 'message' => $message, 'redirect' => $to] + $extra, $status);
    }
    if ($message !== '') {
        flash($message, $ok ? 'info' : 'error');
    }
    redirect($to, 303);
};

if ($show === null) {
    $reply(false, 'We couldn’t find that show. Pick a time again.', 404, '/#now');
}
$film = Films::bySlug($show['film_slug']);
if ($show['starts_at'] <= new DateTimeImmutable()) {
    $reply(false, 'That show has already started. Pick another time.', 410, '/film.php?slug=' . rawurlencode($film['slug']));
}
if (!Films::isBookable($film)) {
    $reply(false, 'Booking for ' . $film['title'] . ' isn’t open yet.', 409, '/film.php?slug=' . rawurlencode($film['slug']));
}

$ids = [];
foreach ((array) ($_POST['seats'] ?? []) as $id) {
    if (is_string($id) && ctype_digit($id)) {
        $ids[(int) $id] = (int) $id;
    }
}
$ids = array_values($ids);
$max = Settings::int('max_seats');

if (!$ids) {
    $reply(false, 'Pick at least one seat.', 422, $seatsPage);
}
if (count($ids) > $max) {
    $reply(false, "You can book up to $max seats at a time.", 422, $seatsPage);
}

// Every seat has to be in this show's hall.
$in = implode(',', array_fill(0, count($ids), '?'));
$labels = db_run("SELECT id, label FROM seats WHERE hall_id = ? AND id IN ($in)", [$show['hall_id'], ...$ids])->fetchAll(PDO::FETCH_KEY_PAIR);
if (count($labels) !== count($ids)) {
    $reply(false, 'Some of those seats aren’t in this hall. Pick them again.', 422, $seatsPage);
}

$result = Holds::place($show['id'], $ids, Holds::key());

if (!$result['ok']) {
    $gone = array_map(fn (int $id): string => $labels[$id], $result['taken']);
    $message = human_list($gone) . (count($gone) === 1 ? ' was' : ' were') . ' just taken by someone else. Pick '
        . (count($gone) === 1 ? 'another seat.' : 'other seats.');
    // Without JavaScript the map reloads; this lets it mark the lost seats and keep the rest ticked.
    $_SESSION['seat_conflict'] = ['show' => $show['id'], 'lost' => $result['taken'], 'picked' => array_values(array_diff($ids, $result['taken']))];
    $reply(false, $message, 409, $seatsPage, ['taken' => $result['taken']]);
}

// Checkout sends them back to this show's seat map if their time runs out.
$_SESSION['checkout_show'] = $show['id'];
$reply(true, '', 200, '/checkout.php', ['expires_at' => $result['expires_at']->format(DATE_ATOM)]);
