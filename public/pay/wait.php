<?php
declare(strict_types=1);

// The "Check your phone" step without JavaScript: the page reloads every two seconds until the
// payment settles, then moves on. With JavaScript the same thing happens in a sheet on checkout.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Bookings;
use App\Payments\Payments;

$ref = Bookings::parseRef(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');
$booking = $ref !== null && Payments::isMine($ref) ? Bookings::byRef($ref) : null;
if ($booking === null) {
    abort(404);
}

$result = Payments::check($booking);
if ($result['status'] !== 'pending') {
    if ($result['message'] !== '') {
        flash($result['message'], 'error');
    }
    redirect($result['next'] ?? '/checkout.php', 303);
}

$wallet = $booking['pay_method'] === 'airtel' ? 'Airtel Money' : 'MoMo';

page_open([
    'title' => 'Check your phone',
    'header' => 'dark',
    'footer' => false,
    'noindex' => true,
    'refresh' => 2,
]);
?>
<div class="navy waiting">
  <div class="sheet" role="status">
    <div class="spin"></div>
    <h1>Check your phone</h1>
    <p>We sent a prompt to <?= e(format_phone($booking['phone'])) ?>. Enter your <?= e($wallet) ?> PIN to pay <?= e(ugx((int) $booking['total'])) ?>.</p>
    <form method="post" action="/pay/cancel.php">
      <?= csrf_field() ?>
      <input type="hidden" name="ref" value="<?= e($booking['ref']) ?>">
      <button class="btn btn-sm btn-o" type="submit">Cancel payment</button>
    </form>
    <p class="demo">Concept demo. No money is taken.</p>
  </div>
</div>
<?php
page_close();
