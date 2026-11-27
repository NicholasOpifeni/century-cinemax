<?php
declare(strict_types=1);

// Admin dashboard: today's bookings and revenue, this week's revenue, and how full today's shows are.
// Demo bookings (from bin/seed-demo-bookings.php) can be left out of every figure.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;
use App\Bookings;
use App\Tickets;

Admin::require();

$withDemo = ($_GET['demo'] ?? '1') !== '0';
$demoSql = $withDemo ? '' : ' AND b.phone <> ?';
$demoArg = $withDemo ? [] : [Bookings::DEMO_PHONE];

$today = new DateTimeImmutable('today');
$monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
$tomorrow = $today->modify('+1 day');

$takings = static function (DateTimeImmutable $from) use ($demoSql, $demoArg): array {
    $row = db_run(
        "SELECT COUNT(*) AS n, COALESCE(SUM(b.total), 0) AS ugx, COALESCE(SUM(b.seat_count), 0) AS seats
         FROM bookings b WHERE b.status = 'paid' AND b.paid_at >= ?$demoSql",
        [$from->format('Y-m-d H:i:s'), ...$demoArg],
    )->fetch();
    return ['n' => (int) $row['n'], 'ugx' => (int) $row['ugx'], 'seats' => (int) $row['seats']];
};
$day = $takings($today);
$week = $takings($monday);

$shows = db_run(
    "SELECT s.id, s.starts_at, s.format, s.language, f.title, f.languages, c.name AS cinema, h.number AS hall,
            (SELECT COUNT(*) FROM seats se WHERE se.hall_id = s.hall_id) AS capacity,
            (SELECT COUNT(*) FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id WHERE bs.showtime_id = s.id$demoSql) AS sold
     FROM showtimes s
     JOIN films f ON f.id = s.film_id
     JOIN halls h ON h.id = s.hall_id
     JOIN cinemas c ON c.id = h.cinema_id
     WHERE s.starts_at >= ? AND s.starts_at < ?
     ORDER BY c.sort_order, s.starts_at, h.number",
    [...$demoArg, $today->format('Y-m-d H:i:s'), $tomorrow->format('Y-m-d H:i:s')],
)->fetchAll();
$capacity = array_sum(array_column($shows, 'capacity'));
$sold = array_sum(array_column($shows, 'sold'));
$byCinema = [];
foreach ($shows as $show) {
    $byCinema[$show['cinema']][] = $show;
}

$latest = db_run(
    "SELECT b.ref, b.paid_at, b.total, b.pay_method, b.seat_labels, b.phone, f.title, s.starts_at, c.name AS cinema
     FROM bookings b
     JOIN showtimes s ON s.id = b.showtime_id
     JOIN films f ON f.id = s.film_id
     JOIN halls h ON h.id = s.hall_id
     JOIN cinemas c ON c.id = h.cinema_id
     WHERE b.status = 'paid'$demoSql
     ORDER BY b.paid_at DESC, b.id DESC
     LIMIT 10",
    $demoArg,
)->fetchAll();

$percent = static fn (int $part, int $whole): int => $whole > 0 ? (int) round($part * 100 / $whole) : 0;
$now = new DateTimeImmutable();

admin_open('Dashboard', 'dashboard');
?>
<div class="adm-page">
  <div class="adm-head">
    <div>
      <h1>Today</h1>
      <p><?= e($today->format('l j F Y')) ?></p>
    </div>
    <nav class="days" aria-label="Demo bookings">
      <a class="pill" href="/admin/?demo=1" aria-current="<?= $withDemo ? 'true' : 'false' ?>">With demo bookings</a>
      <a class="pill" href="/admin/?demo=0" aria-current="<?= $withDemo ? 'false' : 'true' ?>">Without</a>
    </nav>
  </div>

  <div class="stats">
    <div class="stat"><small>Bookings today</small><b><?= number_format($day['n']) ?></b><span><?= number_format($day['seats']) ?> seats</span></div>
    <div class="stat"><small>Revenue today</small><b><?= e(ugx($day['ugx'])) ?></b><span>paid since midnight</span></div>
    <div class="stat"><small>Revenue this week</small><b><?= e(ugx($week['ugx'])) ?></b><span><?= number_format($week['n']) ?> bookings since <?= e($monday->format('D j M')) ?></span></div>
    <div class="stat"><small>Today’s shows</small><b><?= $percent($sold, $capacity) ?>% full</b><span><?= number_format($sold) ?> of <?= number_format($capacity) ?> seats, <?= count($shows) ?> shows</span></div>
  </div>
  <?php if ($withDemo): ?>
    <p class="adm-note">Includes the made-up bookings from <code>bin/seed-demo-bookings.php</code> (phone <?= e(format_phone(Bookings::DEMO_PHONE)) ?>).</p>
  <?php endif; ?>

  <section aria-labelledby="shows-h">
    <h2 id="shows-h">Today’s shows</h2>
    <?php if (!$shows): ?>
      <p class="empty">No shows today. Generate some under Showtimes.</p>
    <?php endif; ?>
    <?php foreach ($byCinema as $cinema => $list): ?>
      <h3><?= e($cinema) ?></h3>
      <div class="table-wrap">
        <table class="adm-table">
          <thead><tr><th>Time</th><th>Film</th><th>Hall</th><th>Format</th><th class="w-occ">Sold</th></tr></thead>
          <tbody>
            <?php foreach ($list as $show): $p = $percent((int) $show['sold'], (int) $show['capacity']); $started = new DateTimeImmutable($show['starts_at']) <= $now; ?>
              <tr<?= $started ? ' class="dim"' : '' ?>>
                <td class="num"><?= e(substr($show['starts_at'], 11, 5)) ?><?= $started ? '<small class="inline">started</small>' : '' ?></td>
                <td><?= e($show['title']) ?><?= count(json_decode($show['languages'], true) ?: []) > 1 ? '<small class="inline">' . e($show['language']) . '</small>' : '' ?></td>
                <td class="num"><?= (int) $show['hall'] ?></td>
                <td><?= e(format_label($show['format'])) ?></td>
                <td>
                  <span class="occ"><meter min="0" max="<?= (int) $show['capacity'] ?>" value="<?= (int) $show['sold'] ?>" low="<?= (int) ((int) $show['capacity'] * 0.3) ?>" high="<?= (int) ((int) $show['capacity'] * 0.8) ?>" optimum="<?= (int) $show['capacity'] ?>"></meter>
                  <span><?= (int) $show['sold'] ?> of <?= (int) $show['capacity'] ?>, <?= $p ?>%</span></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </section>

  <section aria-labelledby="latest-h">
    <h2 id="latest-h">Latest bookings</h2>
    <?php if (!$latest): ?>
      <p class="empty">No paid bookings yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="adm-table">
          <thead><tr><th>Paid</th><th>Ticket</th><th>Film and show</th><th>Seats</th><th>Paid with</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($latest as $b): $starts = new DateTimeImmutable($b['starts_at']); ?>
              <tr>
                <td class="num"><?= e((new DateTimeImmutable($b['paid_at']))->format('D j M, H:i')) ?></td>
                <td><a href="<?= e(Bookings::ticketPath($b['ref'])) ?>" target="_blank" rel="noopener"><?= e(Bookings::formatRef($b['ref'])) ?></a><?= $b['phone'] === Bookings::DEMO_PHONE ? '<small class="inline">demo</small>' : '' ?></td>
                <td><?= e($b['title']) ?><small><?= e(d_short($starts) . ', ' . $starts->format('H:i') . ', ' . $b['cinema']) ?></small></td>
                <td><?= e($b['seat_labels']) ?></td>
                <td><?= e(ucfirst(Tickets::PAY_METHODS[$b['pay_method']] ?? $b['pay_method'])) ?></td>
                <td class="num"><?= e(ugx((int) $b['total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php
page_close();
