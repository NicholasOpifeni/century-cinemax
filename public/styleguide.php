<?php
declare(strict_types=1);

// The shared components on one page, for checking them against the mockup.

require_once dirname(__DIR__) . '/app/bootstrap.php';

$films = db()->query(
    "SELECT id, title, poster_path, backdrop_path, accent_hex, tone_hex, vote_average, vote_count, certificate
     FROM films WHERE slug IN ('the-hunger-games-sunrise-on-the-reaping', 'godzilla-minus-zero', 'the-cat-in-the-hat', 'avengers-doomsday')
     ORDER BY sort_order"
)->fetchAll();
$standIn = ['id' => 0, 'title' => 'A film with no poster yet', 'poster_path' => null, 'backdrop_path' => null];
$first = $films[0] ?? $standIn;

$tokens = [
    'bg' => ['--bg', '#07070B', 'Home'],
    'navy' => ['--navy', '#05051A', 'Seats, checkout'],
    'navy-2' => ['--navy-2', '#0C0C28', 'Order summary'],
    'soft' => ['--soft', '#D7D7E0', 'Secondary text'],
    'muted' => ['--muted', '#8D8DA8', 'Quiet text'],
    'faint' => ['--faint', '#5A5A74', 'Notes'],
    'yellow' => ['--yellow', '#F5C518', 'Book, badges'],
    'blue' => ['--blue', '#0A5CFF', 'Reserved, checkout'],
    'seat' => ['--seat', '#6C6C71', 'Free seat'],
    'recl' => ['--recl', '#24244B', 'Recliner'],
];

page_open(['title' => 'Style guide', 'films' => $films, 'noindex' => true]);
?>
<div class="sg">
  <div>
    <h1>Style guide</h1>
    <p class="sg-lead">The parts every page is built from.</p>
  </div>

  <section>
    <h2>Colours</h2>
    <div class="sg-swatches">
      <?php foreach ($tokens as $class => [$name, $hex, $use]): ?>
        <div class="sg-swatch"><i class="sw-<?= e($class) ?>"></i><span><?= e($name) ?> <small><?= e($hex) ?>, <?= e($use) ?></small></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section>
    <h2>Film colours, taken from each poster</h2>
    <div class="sg-swatches">
      <?php foreach ($films as $film): ?>
        <div class="sg-swatch sg-film <?= film_class($film) ?>"><i></i><span><?= e($film['title']) ?> <small><?= e($film['accent_hex']) ?> accent, <?= e($film['tone_hex']) ?> panel</small></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sg-type">
    <h2>Type, Geist</h2>
    <div class="sg-t1">Hero title</div>
    <div class="sg-t2">Section heading</div>
    <div class="sg-t3">Film page title</div>
    <div class="sg-meta">
      <?php if ((int) ($first['vote_count'] ?? 0) >= 20): ?>
        <span class="imdb">TMDB</span><span><?= e(number_format((float) $first['vote_average'], 1)) ?></span><span class="dim">(<?= e(number_format((int) $first['vote_count'])) ?>)</span>
      <?php endif; ?>
      <span class="bar-s"></span><span>2026</span><span class="bar-s"></span><span>2 hours 38 minutes</span><span class="bar-s"></span><span>Action</span>
    </div>
    <p class="sg-t4">Body copy at 17.5px with loose leading, as in the hero synopsis. <button class="see" type="button" data-toast="In a real synopsis this shows the rest of the text.">See more</button></p>
  </section>

  <section>
    <h2>Buttons</h2>
    <div class="sg-row">
      <button class="btn btn-o" type="button">Watch trailer</button>
      <button class="btn btn-y" type="button"><?= icon('play') ?> Book tickets</button>
      <button class="btn btn-b" type="button">Checkout</button>
      <button class="btn btn-sm btn-y" type="button">Book early</button>
      <button class="btn btn-sm btn-o" type="button" data-toast="We’ll message you on WhatsApp when it opens for booking.">Remind me</button>
      <button class="btn btn-sm btn-b" type="button" disabled>Disabled</button>
      <button class="sq" type="button" aria-label="Previous film"><?= icon('left') ?></button>
      <button class="sq" type="button" aria-label="Next film"><?= icon('right') ?></button>
    </div>
  </section>

  <section>
    <h2>Pills and badges</h2>
    <div class="sg-row">
      <button class="pill" type="button" aria-pressed="true">Today</button>
      <button class="pill" type="button" aria-pressed="false">Thu 26 Nov</button>
      <button class="pill" type="button" aria-pressed="false">Fri 27 Nov</button>
      <a class="pill" href="#">13:15</a>
      <a class="pill" href="#">18:45</a>
      <span class="imdb">TMDB</span>
      <span class="imdb">PG-13</span>
    </div>
    <div class="sg-panel <?= e(film_class($first)) ?>">
      <p>Time pills on a film panel use the film’s accent:</p>
      <div class="sg-row">
        <button class="tp" type="button" aria-pressed="false">10:30</button>
        <button class="tp" type="button" aria-pressed="true">18:45</button>
        <button class="tp" type="button" aria-pressed="false">21:30</button>
        <button class="tp" type="button" disabled>Atmos</button>
      </div>
    </div>
  </section>

  <section>
    <h2>Pictures, with the stand-in for films without one</h2>
    <div class="sg-pics">
      <?php foreach (array_slice($films, 0, 3) as $film): ?>
        <?= pic($film) ?>
      <?php endforeach; ?>
      <?= pic($standIn) ?>
      <?= wide($first, 'w780') ?>
    </div>
  </section>

  <section>
    <h2>Sheet and toast</h2>
    <div class="sg-row">
      <div class="sheet" role="group" aria-label="Payment sheet example">
        <div class="spin"></div>
        <h2>Check your phone</h2>
        <p>We sent a prompt to +256 772 123 456. Enter your MoMo PIN to pay UGX 34,000.</p>
        <button class="btn btn-sm btn-o" type="button">Cancel payment</button>
      </div>
      <button class="btn btn-sm btn-o" type="button" data-toast="You can book up to 8 seats at a time.">Show a toast</button>
    </div>
  </section>
</div>
<?php
page_close();
