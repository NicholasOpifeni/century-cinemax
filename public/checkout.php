<?php
declare(strict_types=1);

// Checkout for the seats this visitor is holding: snacks, 3D glasses, how to pay and the phone
// number for the ticket. Pay now posts to /pay/start.php. checkout.js adds the steppers, the
// live total, the hold timer and the payment sheet; without it the same form still works.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Checkout;
use App\Films;
use App\Holds;
use App\Settings;
use App\Showtimes;

$key = Holds::existingKey();
$hold = $key ? Holds::current($key) : null;
if ($hold === null) {
    $last = (int) ($_SESSION['checkout_show'] ?? 0);
    unset($_SESSION['checkout_show']);
    if ($last > 0) {
        flash('Time ran out, so we let your seats go. Pick them again.', 'error');
        redirect('/seats.php?show=' . $last);
    }
    redirect('/');
}

$show = Showtimes::find($hold['showtime_id']);
$film = Films::bySlug($show['film_slug']);
if ($show['starts_at'] <= new DateTimeImmutable()) {
    Holds::release($key);
    flash('That show has already started. Pick another time.', 'error');
    redirect('/film.php?slug=' . rawurlencode($film['slug']));
}

// What was typed before, if Pay now came back with a problem and JavaScript is off.
$old = is_array($_SESSION['checkout_old'] ?? null) ? $_SESSION['checkout_old'] : [];
unset($_SESSION['checkout_old']);

$seats = Checkout::seats($hold['seat_ids']);
$snacks = Checkout::snacks();
$quantities = Checkout::quantities($old['snacks'] ?? []);
$is3d = $show['format'] === '3D';
$ownGlasses = $is3d && !empty($old['own_glasses']);
$quote = Checkout::quote($show, $seats, $quantities, $ownGlasses);
$seatsOnly = Checkout::quote($show, $seats, [], true)['seats_total'];
$glassesPrice = Settings::int('glasses_price');

$methods = ['mtn' => 'MTN MoMo', 'airtel' => 'Airtel Money', 'card' => 'Visa or Mastercard'];
$method = $old['method'] ?? ($_SESSION['pay_method'] ?? 'mtn');
$method = isset($methods[$method]) ? $method : 'mtn';
// The number last paid with, or the logged-in customer's, without the +256.
$knownPhone = $_SESSION['phone'] ?? (App\Users::current()['phone'] ?? null);
$phone = $old['phone'] ?? (is_string($knownPhone) ? substr(format_phone($knownPhone), 5) : '');
$hints = [
    'mtn' => 'We’ll send a payment prompt to this MTN number. Approve it with your PIN on your phone. This site never asks for your PIN.',
    'airtel' => 'We’ll send a payment prompt to this Airtel number. Approve it with your PIN on your phone. This site never asks for your PIN.',
    'card' => 'You’ll enter your card on a secure payment page, then come straight back here.',
];

$secondsLeft = max(0, $hold['expires_at']->getTimestamp() - time());
$multiLanguage = count($film['languages']) > 1;
$seatWord = count($seats) === 1 ? 'seat' : 'seats';

page_open([
    'title' => 'Checkout',
    'description' => 'Pay for your seats at Century Cinemax.',
    'header' => 'dark',
    'films' => [$film],
    'scripts' => ['checkout.js'],
    'footer' => false,
    'noindex' => true,
    'body_class' => 'booking',
]);
?>
<div class="navy <?= film_class($film) ?>">
  <div class="s-head">
    <a class="back" href="/seats.php?show=<?= $show['id'] ?>" aria-label="Back to seats"><?= icon('left') ?></a>
    <div>
      <h1>Checkout</h1>
      <p><?= count($seats) ?> <?= $seatWord ?> for <?= e($film['title']) ?></p>
    </div>
    <span class="hold" data-expires="<?= $hold['expires_at']->getTimestamp() ?>" data-now="<?= time() ?>">Seats held for <b><?= intdiv($secondsLeft, 60) ?>:<?= sprintf('%02d', $secondsLeft % 60) ?></b></span>
  </div>

  <form class="pay-form" method="post" action="/pay/start.php" novalidate
        data-seats-total="<?= $seatsOnly ?>" data-seat-count="<?= count($seats) ?>" data-glasses="<?= $is3d ? $glassesPrice : 0 ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="show" value="<?= $show['id'] ?>">
    <div class="pay">
      <div>
        <section class="blk" aria-labelledby="snacks-h">
          <h2 id="snacks-h">Add snacks</h2>
          <p>Collect them at the counter with your ticket number. You can skip this.</p>
          <?php foreach ($snacks as $snack): $q = $quantities[$snack['id']]; ?>
            <div class="snack">
              <div><b id="snack-<?= $snack['id'] ?>"><?= e($snack['name']) ?></b><small><?= e($snack['description']) ?></small></div>
              <span class="amt"><?= e(ugx($snack['price'])) ?></span>
              <div class="step">
                <button type="button" data-step="-1" aria-label="Remove one <?= e($snack['name']) ?>"<?= $q === 0 ? ' disabled' : '' ?>><?= icon('minus') ?></button>
                <input type="number" name="snacks[<?= $snack['id'] ?>]" value="<?= $q ?>" min="0" max="<?= Checkout::MAX_SNACKS ?>" inputmode="numeric"
                       aria-labelledby="snack-<?= $snack['id'] ?>" data-id="<?= $snack['id'] ?>" data-price="<?= $snack['price'] ?>">
                <button type="button" data-step="1" aria-label="Add one <?= e($snack['name']) ?>"<?= $q === Checkout::MAX_SNACKS ? ' disabled' : '' ?>><?= icon('plus') ?></button>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($is3d): ?>
            <label class="check"><input type="checkbox" name="own_glasses" value="1"<?= $ownGlasses ? ' checked' : '' ?>><span>I’m bringing my own 3D glasses<small>Otherwise we add reusable glasses at <?= e(ugx($glassesPrice)) ?> a pair.</small></span></label>
          <?php endif; ?>
        </section>

        <section class="blk" aria-labelledby="pay-h">
          <h2 id="pay-h">Pay with</h2>
          <p>Your ticket is sent to this number on WhatsApp and SMS.</p>
          <div class="methods" role="radiogroup" aria-labelledby="pay-h">
            <?php foreach ($methods as $value => $label): ?>
              <label class="tp"><input type="radio" name="method" value="<?= $value ?>"<?= $value === $method ? ' checked' : '' ?>><?= e($label) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="phone"><span>+256</span><input name="phone" inputmode="tel" autocomplete="tel-national" placeholder="772 123 456" value="<?= e($phone) ?>" aria-label="Phone number, after +256" aria-describedby="pay-hint"></label>
          <p class="hint" id="pay-hint" data-mtn="<?= e($hints['mtn']) ?>" data-airtel="<?= e($hints['airtel']) ?>" data-card="<?= e($hints['card']) ?>"><?= e($hints[$method]) ?></p>
          <p class="demo">Concept demo. No money is taken.</p>
        </section>
      </div>

      <aside class="sum" aria-label="Your order">
        <div class="sum-film">
          <?= pic($film, 'w185') ?>
          <div>
            <h3><?= e($film['title']) ?></h3>
            <p><?= e($show['cinema_name']) ?>, hall <?= $show['hall'] ?><br><?= e(d_short($show['starts_at'])) ?>, <?= e($show['starts_at']->format('H:i')) ?><br><?= e(format_label($show['format'])) ?><?= $multiLanguage ? ', ' . e($show['language']) : '' ?></p>
          </div>
        </div>
        <div class="sum-rows">
          <div class="srow"><span>Seats</span><div class="chips"><?php foreach ($seats as $seat): ?><span><?= e($seat['label']) ?></span><?php endforeach; ?></div></div>
          <?php foreach ($quote['lines'] as $line): if (!in_array($line['key'], ['standard', 'recliner'], true)) continue; ?>
            <div class="srow"><span><?= e($line['label']) ?></span><span><?= e(ugx($line['amount'])) ?></span></div>
          <?php endforeach; ?>
          <?php if ($is3d): ?>
            <div class="srow" data-line="glasses"<?= $ownGlasses ? ' hidden' : '' ?>><span><?= count($seats) ?> × 3D glasses</span><span><?= e(ugx(count($seats) * $glassesPrice)) ?></span></div>
          <?php endif; ?>
          <?php foreach ($snacks as $snack): $q = $quantities[$snack['id']]; ?>
            <div class="srow" data-line="snack-<?= $snack['id'] ?>"<?= $q === 0 ? ' hidden' : '' ?>><span><span class="q"><?= $q ?></span> × <?= e(mb_strtolower($snack['name'])) ?></span><span class="amt"><?= e(ugx($q * $snack['price'])) ?></span></div>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>

    <div class="bbar">
      <div class="bi when"><small>Date</small><b><?= e($show['starts_at']->format('j M y')) ?></b></div>
      <div class="bi when"><small>Time</small><b><?= e($show['starts_at']->format('H:i')) ?></b></div>
      <div class="bi grow"><small>Your Seats</small><div class="chips"><?php foreach ($seats as $seat): ?><span><?= e($seat['label']) ?></span><?php endforeach; ?></div></div>
      <div class="bi tot"><small>Total</small><b class="total"><?= e(ugx($quote['total'])) ?></b></div>
      <button class="cta" type="submit">Pay now</button>
    </div>
    <p class="sr" id="pay-status" role="status"></p>
  </form>
</div>
<?php
page_close();
