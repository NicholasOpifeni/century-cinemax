<?php
declare(strict_types=1);

// Pay now on the checkout page posts here. It saves a pending booking for the held seats and
// starts the payment. Mobile money then waits for the customer's phone (checkout.js polls the
// status API, or /pay/wait.php refreshes without JavaScript). Card goes to the provider's page.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Bookings;
use App\Checkout;
use App\Holds;
use App\Payments\Payments;
use App\Showtimes;

if (!is_post()) {
    redirect('/checkout.php');
}

$reply = static function (bool $ok, string $message, int $status, string $to, array $extra = []): never {
    if (wants_json()) {
        json_out(['ok' => $ok, 'message' => $message, 'redirect' => $to] + $extra, $status);
    }
    if ($message !== '') {
        flash($message, $ok ? 'info' : 'error');
    }
    redirect($to, 303);
};

$key = Holds::existingKey();
$hold = $key ? Holds::current($key) : null;
if ($hold === null) {
    $last = (int) ($_SESSION['checkout_show'] ?? 0);
    $reply(false, 'Time ran out, so we let your seats go. Pick them again.', 410, $last ? '/seats.php?show=' . $last : '/');
}
$show = Showtimes::find($hold['showtime_id']);
if ((int) ($_POST['show'] ?? 0) !== $show['id']) {
    $reply(false, 'Your seats changed in another tab. Check them here and try again.', 409, '/checkout.php');
}

$method = is_string($_POST['method'] ?? null) ? $_POST['method'] : '';
$phone = normalise_phone(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
$quantities = Checkout::quantities($_POST['snacks'] ?? []);
$ownGlasses = !empty($_POST['own_glasses']);

$problem = match (true) {
    !in_array($method, ['mtn', 'airtel', 'card'], true) => 'Choose how you’d like to pay.',
    $phone === null => 'Enter the 9 digits of your number after +256, like 772 123 456.',
    default => null,
};
if ($problem !== null) {
    // Without JavaScript the page reloads, so keep what they chose.
    $_SESSION['checkout_old'] = [
        'snacks' => $quantities,
        'own_glasses' => $ownGlasses,
        'method' => $method,
        'phone' => is_string($_POST['phone'] ?? null) ? mb_substr($_POST['phone'], 0, 20) : '',
    ];
    $reply(false, $problem, 422, '/checkout.php');
}

$booking = Bookings::createPending($show, $key, $phone, $method, $quantities, $ownGlasses, App\Users::current()['id'] ?? null);
if ($booking === null) {
    $reply(false, 'Time ran out, so we let your seats go. Pick them again.', 410, '/seats.php?show=' . $show['id']);
}
$_SESSION['phone'] = $phone;
$_SESSION['pay_method'] = $method;

$start = Payments::start($booking);
$reply(true, '', 200, $start->redirect ?? '/pay/wait.php?ref=' . $booking['ref'], [
    'ref' => $booking['ref'],
    'method' => $method,
    'phone' => format_phone($phone),
    'total' => (int) $booking['total'],
    'card' => $start->redirect !== null,
    'poll' => '/api/payment-status.php?ref=' . $booking['ref'],
    'cancel' => '/pay/cancel.php',
]);
