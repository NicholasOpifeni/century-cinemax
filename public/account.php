<?php
declare(strict_types=1);

// My tickets: bookings made while logged in, and guest bookings made with the same phone number.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Bookings;
use App\Users;

$user = Users::requireLogin();
$tickets = Users::tickets($user);

/** One booking in the list, linking to its ticket. */
$row = static function (array $t): string {
    $film = ['title' => $t['title'], 'poster_path' => $t['poster_path']];
    $cancelled = $t['status'] === 'cancelled';
    return '<li><a class="tk-row' . ($cancelled ? ' void' : '') . '" href="' . e(Bookings::ticketPath($t['ref'])) . '">'
        . pic($film, 'w185')
        . '<span class="tk-row-body"><b>' . e($t['title']) . '</b>'
        . '<span>' . e(d_short($t['starts_at']) . ', ' . $t['starts_at']->format('H:i')) . '</span>'
        . '<span>' . e($t['cinema_name'] . ', hall ' . $t['hall'] . ', ' . format_label($t['format'])) . '</span>'
        . '<span>Seats ' . e($t['seat_labels']) . '</span></span>'
        . '<span class="tk-row-ref">' . ($cancelled ? '<em>Cancelled</em>' : '') . e(Bookings::formatRef($t['ref'])) . '</span>'
        . '</a></li>';
};

page_open([
    'title' => 'My tickets',
    'active' => 'account',
    'noindex' => true,
]);
?>
<div class="plain account">
  <h1>My tickets</h1>
  <p>Hello, <?= e(strtok($user['name'], ' ')) ?>. Tickets booked while you’re logged in, or with <?= e(format_phone($user['phone'])) ?>, show up here.</p>

  <section aria-labelledby="up-h">
    <h2 id="up-h">Coming up</h2>
    <?php if ($tickets['upcoming']): ?>
      <ul class="tk-list"><?php foreach ($tickets['upcoming'] as $t) { echo $row($t); } ?></ul>
    <?php else: ?>
      <p class="empty">Nothing booked yet. <a href="/#now">See what’s on</a></p>
    <?php endif; ?>
  </section>

  <?php if ($tickets['past']): ?>
    <section aria-labelledby="past-h">
      <h2 id="past-h">Past</h2>
      <ul class="tk-list past"><?php foreach ($tickets['past'] as $t) { echo $row($t); } ?></ul>
    </section>
  <?php endif; ?>

  <section aria-labelledby="me-h">
    <h2 id="me-h">Your details</h2>
    <dl class="details">
      <dt>Name</dt><dd><?= e($user['name']) ?></dd>
      <dt>Phone</dt><dd><?= e(format_phone($user['phone'])) ?></dd>
      <dt>Email</dt><dd><?= $user['email'] ? e($user['email']) : '<span class="dim">None</span>' ?></dd>
    </dl>
    <form method="post" action="/logout.php">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-o" type="submit">Log out</button>
    </form>
  </section>
</div>
<?php
page_close();
