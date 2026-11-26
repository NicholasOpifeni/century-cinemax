<?php
declare(strict_types=1);

// The e-ticket. Links carry sig, an HMAC of the reference, so a ticket can't be found by guessing:
// a wrong or missing sig is a 404. Download prints just the ticket; Add to calendar is calendar.php.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Bookings;
use App\Payments\Payments;
use App\Tickets;

$ref = Bookings::parseRef(is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '');
$sig = is_string($_GET['sig'] ?? null) ? $_GET['sig'] : '';
if ($ref === null || !Bookings::signatureMatches($ref, $sig)) {
    abort(404);
}
$ticket = Tickets::find($ref);
if ($ticket === null) {
    abort(404);
}

$film = $ticket['film'];
$cancelled = $ticket['status'] === 'cancelled';
$justBooked = !$cancelled && Payments::isMine($ref);
$label = Bookings::formatRef($ref);
$query = 'ref=' . rawurlencode($label) . '&sig=' . rawurlencode($sig);

page_open([
    'title' => 'Ticket ' . $label,
    'description' => 'Your Century Cinemax ticket for ' . $film['title'] . '.',
    'header' => 'dark',
    'films' => [$film],
    'noindex' => true,
]);
?>
<div class="navy tk-page <?= film_class($film) ?>">
  <div class="done">
    <?php if ($cancelled): ?>
      <h1>This booking was cancelled</h1>
      <p>Its seats have been released and the ticket no longer works at the entrance.</p>
    <?php elseif ($justBooked): ?>
      <h1>You’re booked</h1>
      <p>Your ticket is on its way to <?= e(format_phone($ticket['phone'])) ?> on WhatsApp and SMS. Show the QR code at the entrance.</p>
    <?php else: ?>
      <h1>Your ticket</h1>
      <p>Show the QR code at the entrance.</p>
    <?php endif; ?>
  </div>

  <div class="tk-wrap">
    <article class="ticket<?= $cancelled ? ' void' : '' ?>" aria-label="Ticket <?= e($label) ?>">
      <?= pic($film, 'w342') ?>
      <div class="tk-panel">
        <?= wordmark('span') ?>
        <h2><?= e($film['title']) ?></h2>
        <?php if ($film['genres']): ?>
          <div class="genres"><?php foreach ($film['genres'] as $i => $genre): ?><?= $i ? '<i></i>' : '' ?><span><?= e($genre) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="icons">
          <span><?= icon('cal') ?><?= e(mb_strtoupper(d_short($ticket['starts_at']))) ?></span>
          <span><?= icon('clock') ?><?= e($ticket['starts_at']->format('H:i')) ?></span>
          <span><?= icon('seat') ?>HALL <?= (int) $ticket['hall'] ?>, <?= e(mb_strtoupper(format_label($ticket['format']))) ?></span>
        </div>
        <div class="credits">
          <div><b>Cinema:</b> <?= e($ticket['cinema_name']) ?></div>
          <div><b><?= $cancelled ? 'Was paid' : 'Paid' ?>:</b> <?= e(ugx((int) $ticket['total'])) ?> with <?= e(Tickets::PAY_METHODS[$ticket['pay_method']] ?? $ticket['pay_method']) ?></div>
          <?php if (count($film['languages']) > 1): ?>
            <div><b>Language:</b> <?= e($ticket['language']) ?></div>
          <?php endif; ?>
          <?php if ($ticket['snacks']): ?>
            <div><b>Snacks:</b> <?= e(Tickets::snackLine($ticket)) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="tk-stub">
        <?php if ($cancelled): ?>
          <span class="void-mark">Cancelled</span>
        <?php else: ?>
          <div class="qr-box" role="img" aria-label="QR code for ticket <?= e($label) ?>"><?= Tickets::qr($ticket['url']) ?></div>
          <small>Scan at the entrance</small>
        <?php endif; ?>
        <small class="gap">Ticket number</small>
        <span class="n"><?= e($label) ?></span>
        <small>Seats</small>
        <span class="s"><?= e($ticket['seat_labels']) ?></span>
      </div>
    </article>
  </div>

  <?php if (!$cancelled): ?>
    <div class="tk-actions">
      <button class="btn btn-sm btn-b js-only" type="button" data-print>Download ticket</button>
      <a class="btn btn-sm btn-o" href="/calendar.php?<?= e($query) ?>">Add to calendar</a>
      <a class="btn btn-sm btn-o" href="<?= e(Tickets::whatsappUrl($ticket)) ?>" target="_blank" rel="noopener">Share on WhatsApp</a>
    </div>
  <?php endif; ?>

  <div class="tips">
    <div><b>Arrive 15 minutes early</b>Snacks are ready at the counter. Give your ticket number to collect them.</div>
    <div><b>Running late?</b>Your seats are kept until the film starts. Adverts and trailers run for about 15 minutes.</div>
    <div><b>Lost this page?</b>Find it again any time with your ticket number and phone number, under Find my booking.</div>
  </div>
</div>
<?php
page_close();
