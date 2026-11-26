<?php
declare(strict_types=1);

// Add to calendar: the show as an .ics file, signed like the ticket link.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Bookings;
use App\Tickets;

$ref = Bookings::parseRef(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');
$sig = is_string($_GET['sig'] ?? null) ? $_GET['sig'] : '';
if ($ref === null || !Bookings::signatureMatches($ref, $sig)) {
    abort(404);
}
$ticket = Tickets::find($ref);
if ($ticket === null || $ticket['status'] !== 'paid') {
    abort(404);
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="cinemax-' . Bookings::formatRef($ref) . '.ics"');
header('Cache-Control: private, no-store');
echo Tickets::calendar($ticket);
