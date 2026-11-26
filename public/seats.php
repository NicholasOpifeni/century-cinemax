<?php
declare(strict_types=1);

// The seat map for one show. Seats are checkboxes in a form, so picking works without JavaScript;
// seats.js adds the live total, the eight-seat limit and arrow-key movement. Checkout posts to hold.php.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Films;
use App\Holds;
use App\Pricing;
use App\SeatMap;
use App\Settings;
use App\Showtimes;

$show = Showtimes::find((int) ($_GET['show'] ?? 0));
if ($show === null) {
    abort(404);
}
$film = Films::bySlug($show['film_slug']);
$day = $show['starts_at']->setTime(0, 0);
$multiLanguage = count($film['languages']) > 1;
$back = '/film.php?' . http_build_query([
    'slug' => $film['slug'],
    'date' => $day->format('Y-m-d'),
    'lang' => $multiLanguage ? $show['language'] : null,
    'format' => $show['format'],
    'show' => $show['id'],
]);

if ($show['starts_at'] <= new DateTimeImmutable()) {
    flash('That show has already started. Pick another time.', 'error');
    redirect($back);
}
if (!Films::isBookable($film)) {
    flash('Booking for ' . $film['title'] . ' opens closer to ' . Films::openingDate($film) . '.', 'error');
    redirect($back);
}

$holdKey = Holds::existingKey();

// seats.js asks every so often which seats have gone, so it can grey them out.
if (wants_json()) {
    [$taken, $mine] = SeatMap::takenAndMine($show['id'], $holdKey);
    json_out(['taken' => array_values($taken), 'mine' => array_values($mine)]);
}

$map = SeatMap::forShowtime($show['id'], $holdKey);

// After a clash without JavaScript, hold.php leaves the seats that went and the ones still wanted.
$lost = [];
$picked = [];
$conflict = $_SESSION['seat_conflict'] ?? null;
if (is_array($conflict) && ($conflict['show'] ?? null) === $show['id']) {
    $lost = array_flip($conflict['lost'] ?? []);
    $picked = array_flip($conflict['picked'] ?? []);
}
unset($_SESSION['seat_conflict']);

$prices = [
    'standard' => Pricing::seatPrice($day, $show['format'], 'standard'),
    'recliner' => Pricing::seatPrice($day, $show['format'], 'recliner'),
];
$max = Settings::int('max_seats');
$isToday = $day == Films::today();

// Seats that start out ticked: the visitor's own holds, or what they still wanted after a clash.
$chosen = [];
foreach ($map['rows'] as $row) {
    foreach ($row['seats'] as $seat) {
        if (!$seat['taken'] && ($seat['mine'] || isset($picked[$seat['id']]))) {
            $chosen[] = $seat;
        }
    }
}
$chosenTotal = array_sum(array_map(fn (array $s): int => $prices[$s['type']], $chosen));

page_open([
    'title' => 'Seats for ' . $film['title'],
    'description' => 'Choose your seats for ' . $film['title'] . ' at ' . $show['cinema_name'] . '.',
    'header' => 'dark',
    'scripts' => ['seats.js'],
    'footer' => false,
    'noindex' => true,
    'body_class' => 'booking',
]);
?>
<div class="navy">
  <div class="s-head">
    <a class="back" href="<?= e($back) ?>" aria-label="Back to <?= e($film['title']) ?>"><?= icon('left') ?></a>
    <div>
      <h1>Select your Seats</h1>
      <p><?= e($film['title']) ?>. <?= e($show['cinema_name']) ?>, hall <?= $show['hall'] ?>, <?= e(format_label($show['format'])) ?><?= $multiLanguage ? ', ' . e($show['language']) : '' ?></p>
    </div>
  </div>

  <form class="seat-form" method="post" action="/hold.php" data-max="<?= $max ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="show" value="<?= $show['id'] ?>">

    <div class="map-wrap">
      <div class="map" role="group" aria-label="Seat map, screen at the top">
        <div class="screen" aria-hidden="true"></div>
        <div class="throw" aria-hidden="true"></div>
        <div class="seatgrid">
          <?php $previous = null; ?>
          <?php foreach ($map['rows'] as $row): ?>
            <?php if ($row['type'] === 'recliner' && $previous !== null && $previous !== 'recliner'): ?>
              <div class="aisle" aria-hidden="true"></div>
            <?php endif; $previous = $row['type']; ?>
            <span class="rl" aria-hidden="true"><?= e($row['row']) ?></span>
            <?php for ($col = 1; $col <= $map['columns']; $col++): $seat = $row['seats'][$col] ?? null; ?>
              <?php if ($seat === null): ?>
                <span class="gap"></span>
              <?php else:
                $recliner = $seat['type'] === 'recliner';
                $wasLost = isset($lost[$seat['id']]);
                $checked = !$seat['taken'] && ($seat['mine'] || isset($picked[$seat['id']]));
                $name = "Row {$row['row']}, seat {$seat['number']}" . ($recliner ? ', recliner' : '');
                $label = $seat['taken'] ? $name . ($wasLost ? ', just taken' : ', reserved') : $name . ', ' . ugx($prices[$seat['type']]);
              ?>
                <label class="seat<?= $recliner ? ' recl' : '' ?><?= $wasLost ? ' lost' : '' ?>"><input type="checkbox" name="seats[]" value="<?= $seat['id'] ?>" data-label="<?= e($seat['label']) ?>" data-name="<?= e($name) ?>" data-price="<?= $prices[$seat['type']] ?>" aria-label="<?= e($label) ?>"<?= $checked ? ' checked' : '' ?><?= $seat['taken'] ? ' disabled' : '' ?>><span aria-hidden="true"><?= e($seat['label']) ?></span></label>
              <?php endif; ?>
            <?php endfor; ?>
            <span class="rl" aria-hidden="true"><?= e($row['row']) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="map-line" aria-hidden="true"></div>
      </div>
    </div>
    <p class="drag">Drag sideways to see the whole hall.</p>

    <div class="legend">
      <span><i class="lg-normal"></i>NORMAL</span>
      <span><i class="lg-reserved"></i>RESERVED</span>
      <span><i class="lg-recl"></i>RECLINERS</span>
      <span><i class="lg-selected"></i>SELECTED</span>
    </div>
    <p class="prices">Normal <?= e(ugx($prices['standard'])) ?>. Recliner <?= e(ugx($prices['recliner'])) ?>. Up to <?= $max ?> seats per booking.</p>

    <div class="bbar">
      <div class="bi when"><small>Date</small><b><?= $isToday ? 'Today, ' : '' ?><?= e($day->format('j M y')) ?></b></div>
      <div class="bi when"><small>Time</small><b><?= e($show['starts_at']->format('H:i')) ?></b></div>
      <div class="bi grow">
        <small>Your Seats</small>
        <div class="chips" data-empty="Tap the seats you want">
          <?php if ($chosen): ?>
            <?php foreach ($chosen as $seat): ?><span><?= e($seat['label']) ?></span><?php endforeach; ?>
          <?php else: ?>
            <em>Tap the seats you want</em>
          <?php endif; ?>
        </div>
      </div>
      <div class="bi tot"><small>Total</small><b class="total"><?= e(ugx($chosenTotal)) ?></b></div>
      <button class="cta" type="submit">Checkout</button>
    </div>
    <p class="sr" id="seat-status" role="status"></p>
  </form>
</div>
<?php
page_close();
