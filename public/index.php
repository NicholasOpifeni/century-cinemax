<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Cinemas;
use App\Films;
use App\Pricing;
use App\Settings;
use App\Showtimes;

$today = Films::today();
$cinema = Cinemas::current();
$cinemas = Cinemas::all();

// ?day=2026-11-26 picks one of the next seven days for Now showing.
$dayIndex = 0;
if (is_string($_GET['day'] ?? null) && ($picked = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['day'])) !== false) {
    $offset = (int) $today->diff($picked)->format('%r%a');
    $dayIndex = $offset >= 0 && $offset <= 6 ? $offset : 0;
}
$day = $today->modify("+$dayIndex days");

$featured = Films::featured();
$showing = Films::showingOn($day);
$times = $cinema ? Showtimes::forCinemaOn($cinema['id'], $day) : [];
$soon = Films::comingSoon();
$trailers = Films::withTrailers();

$prices = array_map(Pricing::basePrice(...), range(0, 6));
$cheapest = min($prices);
$cheapDays = array_keys($prices, $cheapest, true);
$dayNames = ['Sundays', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays'];
$counts = ['One', 'Two', 'Three', 'Four', 'Five', 'Six'];
$advance = Settings::int('advance_days');

/** Small label on a time pill for anything other than the film's usual format and language. */
$showTag = static function (array $film, array $show): string {
    $tag = $show['format'] !== '2D' ? format_label($show['format']) : '';
    if (count($film['languages']) > 1) {
        $tag = trim($tag . ' ' . $show['language']);
    }
    return $tag;
};

page_open([
    'header' => 'over',
    'active' => 'now',
    'scripts' => ['home.js'],
    'description' => 'What’s on at Century Cinemax in Kampala. Pick a film and a time, choose your seats and pay with MTN MoMo, Airtel Money or card.',
]);
?>
<section class="hero" aria-label="Featured films">
  <h1 class="sr">Book cinema tickets at Century Cinemax in Kampala</h1>
  <?php foreach ($featured as $i => $film): ?>
    <div class="pic wide<?= $i === 0 ? ' on' : '' ?>"<?= $i === 0 ? '' : ' hidden' ?>>
      <?php if ($film['backdrop_path']): ?>
        <img src="<?= e(App\Tmdb::imageUrl($film['backdrop_path'], 'w1280')) ?>" alt=""<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?>>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="hero-in">
    <?php foreach ($featured as $i => $film): ?>
      <div class="hero-slide"<?= $i === 0 ? '' : ' hidden' ?>>
        <h2 class="hero-title"><?= e($film['title']) ?></h2>
        <div class="meta">
          <?= rating_badge($film, true) ?>
          <span class="dot"></span>
          <span><?= e($film['opens_on']->format('Y')) ?></span>
          <span class="bar-s"></span>
          <span><?= e(run_long($film['runtime_min'])) ?></span>
          <?php if ($film['genres']): ?>
            <span class="bar-s"></span>
            <span><?= e($film['genres'][0]) ?></span>
          <?php endif; ?>
        </div>
        <p class="syn">
          <span class="syn-short"><?= e(clip($film['synopsis'], 170)) ?> <button class="see" type="button">See more</button></span>
          <span class="syn-full"><?= e($film['synopsis']) ?></span>
        </p>
        <div class="btns">
          <?php if ($film['trailer_key']): ?>
            <a class="btn btn-o" href="https://www.youtube.com/watch?v=<?= e($film['trailer_key']) ?>" target="_blank" rel="noopener" data-trailer="<?= e($film['trailer_key']) ?>" data-title="<?= e($film['title']) ?>">Watch trailer</a>
          <?php endif; ?>
          <a class="btn btn-y" href="/film.php?slug=<?= e($film['slug']) ?>"><?= icon('play') ?> <?= Films::isBookable($film) ? 'Book tickets' : 'See details' ?></a>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (count($featured) > 1): ?>
      <div class="arrows">
        <button class="sq" type="button" data-step="-1" aria-label="Previous film"><?= icon('left') ?></button>
        <button class="sq" type="button" data-step="1" aria-label="Next film"><?= icon('right') ?></button>
      </div>
      <nav class="carousel" aria-label="Featured films">
        <?php foreach ($featured as $i => $film): ?>
          <a href="/film.php?slug=<?= e($film['slug']) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= e($film['title']) ?>"><?= pic($film, 'w500', '', $i > 3) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<section class="sec" id="now" aria-labelledby="now-h">
  <div class="sec-h">
    <div>
      <h2 id="now-h">Now showing</h2>
      <p><?= $cinema ? 'At ' . e($cinema['name']) . '. ' : '' ?>Pick a time to choose your seats.</p>
    </div>
    <nav class="days" aria-label="Day">
      <?php for ($i = 0; $i < 7; $i++): $d = $today->modify("+$i days"); ?>
        <a class="pill" href="/?day=<?= $d->format('Y-m-d') ?>#now" aria-current="<?= $i === $dayIndex ? 'true' : 'false' ?>"><?= $i === 0 ? 'Today' : e(d_short($d)) ?></a>
      <?php endfor; ?>
    </nav>
  </div>
  <?php if (!$showing): ?>
    <p class="empty">Nothing is showing on <?= e(d_short($day)) ?> yet. Try another day.</p>
  <?php else: ?>
    <div class="films">
      <?php foreach ($showing as $film): $shows = $times[$film['id']] ?? []; ?>
        <article class="film">
          <a class="open" href="/film.php?slug=<?= e($film['slug']) ?>"><?= pic($film) ?><h3><?= e($film['title']) ?></h3></a>
          <div class="info">
            <span><?= e($film['certificate']) ?></span>
            <span class="bar-s"></span>
            <span><?= e(run_short($film['runtime_min'])) ?></span>
            <?php if ($film['vote_count'] >= 20): ?>
              <span class="bar-s"></span>
              <span class="imdb">TMDB</span>
              <span><?= e(number_format((float) $film['vote_average'], 1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="times">
            <?php foreach (array_slice($shows, 0, 4) as $show): $tag = $showTag($film, $show); $time = $show['starts_at']->format('H:i'); ?>
              <a class="pill" href="/seats.php?show=<?= $show['id'] ?>" aria-label="<?= e("$time, " . ($tag !== '' ? "$tag, " : '') . "hall {$show['hall']}. Choose seats for {$film['title']}") ?>"><?= $time ?><?php if ($tag !== ''): ?><small><?= e($tag) ?></small><?php endif; ?></a>
            <?php endforeach; ?>
            <?php if (count($shows) > 4): ?>
              <a class="pill" href="/film.php?slug=<?= e($film['slug']) ?>">More times</a>
            <?php elseif (!$shows): ?>
              <p class="none"><?= $dayIndex === 0 ? 'No more shows here today.' : 'No shows here on this day.' ?></p>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($soon): ?>
<section class="sec" id="soon" aria-labelledby="soon-h">
  <div class="sec-h">
    <div>
      <h2 id="soon-h">Coming soon</h2>
      <p>Book early up to <?= $advance === 14 ? 'two weeks' : e($advance . ' days') ?> before a film opens, or get a reminder on WhatsApp.</p>
    </div>
  </div>
  <div class="films">
    <?php foreach ($soon as $film): ?>
      <article class="film">
        <a class="open" href="/film.php?slug=<?= e($film['slug']) ?>"><?= pic($film) ?><h3><?= e($film['title']) ?></h3></a>
        <span class="when"><?= e(Films::openingDate($film)) ?></span>
        <div class="act">
          <?php if (Films::isBookable($film)): ?>
            <a class="btn btn-sm btn-y" href="/film.php?slug=<?= e($film['slug']) ?>">Book early</a>
          <?php else: ?>
            <a class="btn btn-sm btn-o" href="/film.php?slug=<?= e($film['slug']) ?>#remind" data-remind="<?= e($film['slug']) ?>" data-title="<?= e($film['title']) ?>">Remind me</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($trailers): ?>
<section class="sec" id="trailers" aria-labelledby="trailers-h">
  <div class="sec-h"><div><h2 id="trailers-h">Trailers</h2></div></div>
  <div class="trailers">
    <?php foreach ($trailers as $film): ?>
      <a class="trl" href="https://www.youtube.com/watch?v=<?= e($film['trailer_key']) ?>" target="_blank" rel="noopener" data-trailer="<?= e($film['trailer_key']) ?>" data-title="<?= e($film['title']) ?>">
        <span class="trl-pic"><?= wide($film, 'w780') ?><span class="play"><span><?= icon('playBig') ?></span></span></span>
        <b><span class="sr">Watch the trailer for </span><?= e($film['title']) ?></b>
        <small><?= e(Films::statusLine($film)) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="sec" id="cinemas" aria-labelledby="cinemas-h">
  <div class="sec-h" id="offers">
    <div>
      <h2 id="cinemas-h">Our cinemas</h2>
      <p><?= e(($counts[count($cinemas) - 1] ?? count($cinemas)) . ' cinema' . (count($cinemas) === 1 ? '' : 's')) ?> in Kampala, open from 10:00 every day.
        Every seat is <?= e(ugx($cheapest)) ?> on <?= e(implode(' and ', array_map(fn (int $d): string => $dayNames[$d], $cheapDays))) ?>.</p>
    </div>
  </div>
  <div class="cinemas">
    <?php foreach ($cinemas as $c): $mine = $cinema && $c['id'] === $cinema['id']; ?>
      <div class="cin<?= $mine ? ' mine' : '' ?>">
        <h3><?= e($c['name']) ?></h3>
        <p><?= e($c['area']) ?><br><?= e($c['halls'] . ' halls' . ($c['has_atmos'] ? ', one with Dolby Atmos' : '')) ?></p>
        <?php if ($mine): ?>
          <span class="here">Showing films here</span>
        <?php else: ?>
          <form method="post" action="/cinema.php">
            <?= csrf_field() ?>
            <input type="hidden" name="cinema" value="<?= e($c['slug']) ?>">
            <input type="hidden" name="return" value="/#now">
            <button class="btn btn-sm btn-o" type="submit">Show films here</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
page_close();
