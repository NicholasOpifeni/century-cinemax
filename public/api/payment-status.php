<?php
declare(strict_types=1);

// checkout.js asks here every couple of seconds while a payment is waiting.
// Answers {status, message, next}; once paid, next is the ticket.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Bookings;
use App\Payments\Payments;

$ref = Bookings::parseRef(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');
$booking = $ref !== null && Payments::isMine($ref) ? Bookings::byRef($ref) : null;
if ($booking === null) {
    json_out(['status' => 'missing', 'message' => 'We couldn’t find that payment.', 'next' => null], 404);
}

json_out(Payments::check($booking));
