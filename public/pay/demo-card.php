<?php
declare(strict_types=1);

// Stands in for a card provider's secure payment page. It never asks for card numbers, CVVs or
// PINs, and nothing about a card is stored: the two buttons just say how the payment went.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Bookings;
use App\Payments\DemoGateway;
use App\Payments\Payments;

$raw = $_POST['ref'] ?? $_GET['ref'] ?? '';
$ref = Bookings::parseRef(is_string($raw) ? $raw : '');
$booking = $ref !== null && Payments::isMine($ref) ? Bookings::byRef($ref) : null;
if ($booking === null || $booking['pay_method'] !== 'card') {
    abort(404);
}

if (is_post()) {
    $gateway = Payments::gateway();
    if ($gateway instanceof DemoGateway && $booking['status'] === 'pending') {
        $gateway->answerCard($booking, ($_POST['answer'] ?? '') === 'approve');
    }
    $booking = Bookings::byRef($ref);
}

// Once the payment is settled, carry on to the ticket or back to checkout.
$result = Payments::check($booking);
if ($result['status'] !== 'pending') {
    if ($result['message'] !== '') {
        flash($result['message'], 'error');
    }
    redirect($result['next'] ?? '/checkout.php', 303);
}

page_open([
    'title' => 'Demo card payment',
    'header' => 'dark',
    'footer' => false,
    'noindex' => true,
]);
?>
<div class="navy waiting">
  <div class="sheet card-demo">
    <h1>Demo card payment</h1>
    <p>This is where your card provider’s secure payment page would open. In this demo no card details are asked for, sent or stored.</p>
    <dl class="card-sum">
      <dt>Paying</dt><dd>Cinemax</dd>
      <dt>Booking</dt><dd><?= e(Bookings::formatRef($booking['ref'])) ?></dd>
      <dt>Amount</dt><dd><?= e(ugx((int) $booking['total'])) ?></dd>
    </dl>
    <form method="post" action="/pay/demo-card.php" class="sheet-btns">
      <?= csrf_field() ?>
      <input type="hidden" name="ref" value="<?= e($booking['ref']) ?>">
      <button class="btn btn-sm btn-b" type="submit" name="answer" value="approve">Approve test payment</button>
      <button class="btn btn-sm btn-o" type="submit" name="answer" value="decline">Decline</button>
    </form>
    <p class="demo">Concept demo. No money is taken.</p>
  </div>
</div>
<?php
page_close();
