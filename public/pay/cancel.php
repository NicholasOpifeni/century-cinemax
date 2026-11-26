<?php
declare(strict_types=1);

// Cancel payment, from the "Check your phone" sheet or the waiting page. The seats stay held.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Bookings;
use App\Payments\Payments;

if (!is_post()) {
    redirect('/checkout.php');
}

$ref = Bookings::parseRef(is_string($_POST['ref'] ?? null) ? $_POST['ref'] : '');
$booking = $ref !== null && Payments::isMine($ref) ? Bookings::byRef($ref) : null;
if ($booking !== null) {
    Payments::cancel($booking);
}
$message = 'Payment cancelled. Your seats are still held.';

if (wants_json()) {
    json_out(['ok' => $booking !== null, 'message' => $booking !== null ? $message : 'We couldn’t find that payment.'], $booking !== null ? 200 : 404);
}
if ($booking !== null) {
    flash($message);
}
redirect('/checkout.php', 303);
