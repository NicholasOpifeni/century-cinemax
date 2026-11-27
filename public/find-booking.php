<?php
declare(strict_types=1);

// Find my booking: the ticket number and the phone number it was paid with bring the ticket back.
// Both have to match, and a visitor gets ten wrong tries every ten minutes.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Bookings;
use App\Throttle;

$refInput = '';
$phoneInput = '';
$error = null;

if (is_post()) {
    $refInput = mb_substr(is_string($_POST['ref'] ?? null) ? trim($_POST['ref']) : '', 0, 20);
    $phoneInput = mb_substr(is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '', 0, 20);

    $ref = Bookings::parseRef($refInput);
    $phone = normalise_phone($phoneInput);
    if (Throttle::tooMany('find', 10, 600)) {
        $error = 'That’s a lot of tries. Wait a few minutes, then check your ticket number and try again.';
    } elseif ($ref === null) {
        $error = 'Ticket numbers have eight letters and numbers, like K7QW2-MXP.';
    } elseif ($phone === null) {
        $error = 'Enter the 9 digits of your number after +256, like 772 123 456.';
    } else {
        $found = db_run("SELECT ref FROM bookings WHERE ref = ? AND phone = ? AND status IN ('paid', 'cancelled')", [$ref, $phone])->fetchColumn();
        if ($found) {
            Throttle::clear('find');
            redirect(Bookings::ticketPath((string) $found), 303);
        }
        Throttle::hit('find', 600);
        $error = 'We couldn’t find a booking with that ticket number and phone number. Check both and try again.';
    }
}

page_open([
    'title' => 'Find my booking',
    'description' => 'Find your Century Cinemax ticket with your ticket number and phone number.',
    'noindex' => true,
]);
?>
<div class="plain find">
  <h1>Find my booking</h1>
  <p>Enter the ticket number from your booking and the phone number you paid with.</p>
  <form method="post" action="/find-booking.php" novalidate>
    <?= csrf_field() ?>
    <label class="field">
      <span>Ticket number</span>
      <input class="ref" name="ref" value="<?= e($refInput) ?>" placeholder="K7QW2-MXP" autocomplete="off" autocapitalize="characters" spellcheck="false" required<?= $error !== null ? ' aria-describedby="find-error"' : '' ?>>
    </label>
    <span class="field-label" id="find-phone">Phone number</span>
    <label class="phone"><span>+256</span><input name="phone" value="<?= e($phoneInput) ?>" inputmode="tel" autocomplete="tel-national" placeholder="772 123 456" aria-labelledby="find-phone" required></label>
    <?php if ($error !== null): ?>
      <p class="err" id="find-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <button class="btn btn-y" type="submit">Find my booking</button>
  </form>
</div>
<?php
page_close();
