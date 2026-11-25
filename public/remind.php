<?php
declare(strict_types=1);

// Saves a phone number to be told when a film opens for booking. No message is sent; the admin exports the list.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Films;

if (!is_post()) {
    redirect('/');
}

$back = local_path(is_string($_POST['return'] ?? null) ? $_POST['return'] : '/#soon');

$reply = static function (bool $ok, string $message, int $status = 200) use ($back): never {
    if (wants_json()) {
        json_out(['ok' => $ok, 'message' => $message], $status);
    }
    flash($message, $ok ? 'info' : 'error');
    redirect($back, 303);
};

$film = Films::bySlug(is_string($_POST['film'] ?? null) ? $_POST['film'] : '');
if ($film === null || Films::status($film) === 'ended') {
    $reply(false, 'We couldn’t find that film.', 404);
}
if (Films::isBookable($film)) {
    $reply(false, $film['title'] . ' is already open for booking.', 409);
}

$phone = normalise_phone(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
if ($phone === null) {
    $reply(false, 'Enter a Ugandan mobile number, like 772 123 456.', 422);
}

$added = db_run('INSERT IGNORE INTO reminders (film_id, phone) VALUES (?, ?)', [$film['id'], $phone])->rowCount() > 0;

$reply(true, $added
    ? 'We’ll message ' . format_phone($phone) . ' on WhatsApp when ' . $film['title'] . ' opens for booking.'
    : format_phone($phone) . ' is already on the list for ' . $film['title'] . '.');
