<?php
declare(strict_types=1);

// The movie page: the film's details, then cinema, day, language, format and time for booking.
// Every choice is a plain GET form, so it works without JavaScript; film.js swaps the panel in place.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Cinemas;
use App\Films;
use App\Pricing;
use App\SeatMap;
use App\Settings;
use App\Showtimes;
use App\Tmdb;

$param = static fn (string $key): string => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';

$film = Films::bySlug($param('slug'));
if ($film === null) {
    abort(404);
}

// Picking a cinema here makes it the visitor's cinema, then the address drops it again,
// so the header picker and this page always agree.
if ($param('cinema') !== '') {
    if (Cinemas::bySlug($param('cinema')) !== null) {
        Cinemas::remember($param('cinema'));
    }
    // A show from the old cinema won't match the new one, so it falls back to the default there.
    $keep = array_filter(['slug' => $film['slug'], 'date' => $param('date'), 'lang' => $param('lang'), 'format' => $param('format'), 'show' => $param('show')]);
    redirect('/film.php?' . http_build_query($keep), 303);
}

$today = Films::today();
$status = Films::status($film);
$cinema = Cinemas::current();
$cinemas = Cinemas::all();
$bookable = Films::isBookable($film) && $cinema !== null;
$advance = Settings::int('advance_days');
$counts = ['one', 'two', 'three', 'four', 'five', 'six'];

// "Japanese, English subtitles" shows as a language and a subtitles line, like the mockup.
$spoken = implode(', ', $film['languages']);
$subtitles = null;
if (count($film['languages']) === 1 && preg_match('/^(.+?),\s*(.+ subtitles)$/i', $film['languages'][0], $m)) {
    [$spoken, $subtitles] = [$m[1], $m[2]];
}

// Filled in below when the film can be booked.
$days = $formats = $atmosAt = $shows = [];
$date = $url = $statusText = '';
$show = $map = null;

if ($bookable) {
    // Seven days from today, or from the opening day for an early booking, and never past the run.
    $first = $film['opens_on'] > $today ? $film['opens_on'] : $today;
    $last = min($first->modify('+6 days'), $film['ends_on']);
    $days = [];
    for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
        $days[$d->format('Y-m-d')] = $d;
    }
    $date = $param('date');
    if (!isset($days[$date])) {
        // Open on the first day that still has a show, so late at night the page skips to tomorrow.
        $date = Showtimes::daysWithShows($film['id'], $cinema['id'], $first, $last)[0] ?? array_key_first($days);
    }
    $day = $days[$date];

    $languages = $film['languages'];
    $lang = in_array($param('lang'), $languages, true) ? $param('lang') : $languages[0];

    $formats = array_values(array_filter($film['formats'], fn (string $f): bool => $f !== 'Dolby Atmos' || $cinema['has_atmos']));
    $atmosAt = array_column(array_filter($cinemas, fn (array $c): bool => $c['has_atmos']), 'name');

    $all = Showtimes::forFilm($film['id'], $cinema['id'], $day);
    $matching = fn (string $format): array => array_values(array_filter(
        $all,
        fn (array $s): bool => $s['format'] === $format && $s['language'] === $lang,
    ));

    $format = $param('format');
    if (!in_array($format, $formats, true)) {
        // The first format with a show that day, so the page opens on something you can book.
        $format = $formats[0] ?? '2D';
        foreach ($formats as $f) {
            if ($matching($f)) {
                $format = $f;
                break;
            }
        }
    }
    $shows = $matching($format);

    $show = null;
    foreach ($shows as $s) {
        if ((string) $s['id'] === $param('show')) {
            $show = $s;
        }
    }
    if ($show === null && $shows) {
        // Like the mockup, an evening show is picked to start with.
        $evening = array_values(array_filter($shows, fn (array $s): bool => (int) $s['starts_at']->format('G') >= 18));
        $show = $evening[0] ?? $shows[array_key_last($shows)];
    }
    $map = $show ? SeatMap::forShowtime($show['id']) : null;

    // Read out after the panel changes in place.
    $label = format_label($format);
    $statusText = $show && $map
        ? sprintf(
            '%d %s time%s on %s. %s picked, hall %d, %d seat%s free.',
            count($shows), $label, count($shows) === 1 ? '' : 's', $day->format('l j F'),
            $show['starts_at']->format('H:i'), $map['hall'], $map['free'], $map['free'] === 1 ? '' : 's',
        )
        : "No $label shows at {$cinema['name']} on " . $day->format('l j F') . '.';

    $url = '/film.php?' . http_build_query([
        'slug' => $film['slug'],
        'date' => $date,
        'lang' => count($languages) > 1 ? $lang : null,
        'format' => $format,
        'show' => $show['id'] ?? null,
    ]);
}

page_open([
    'title' => $film['title'],
    'description' => clip($film['synopsis'], 155),
    'films' => [$film],
    'scripts' => ['preview.js', 'film.js'],
]);
?>
<section class="detail <?= film_class($film) ?>" aria-labelledby="film-h">
  <?php if ($film['poster_path']): ?>
    <div class="blurbg" aria-hidden="true"><img src="<?= e(Tmdb::imageUrl($film['poster_path'], 'w342')) ?>" alt=""></div>
  <?php endif; ?>
  <div class="card">
    <?= pic($film, 'w780', 'poster', false, $film['trailer_key'] ? preview_layer($film['trailer_key']) : '') ?>
    <div class="panel">
      <h1 id="film-h"><?= e($film['title']) ?></h1>
      <?php if ($film['genres']): ?>
        <div class="genres">
          <?php foreach ($film['genres'] as $i => $genre): ?><?= $i ? '<i></i>' : '' ?><span><?= e($genre) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="icons">
        <span><?= icon('clock') ?><span class="sr">Runtime </span><?= e(run_caps($film['runtime_min'])) ?></span>
        <span><?= icon('chat') ?><span class="sr">Language </span><?= e(mb_strtoupper($spoken)) ?></span>
        <?php if ($subtitles !== null): ?>
          <span><?= icon('cc') ?><?= e(mb_strtoupper($subtitles)) ?></span>
        <?php endif; ?>
        <?php if ($film['vote_count'] >= 20 && $film['vote_average'] !== null): ?>
          <span><span class="imdb" title="TMDB audience rating">TMDB</span><?= e(number_format($film['vote_average'], 1)) ?>/10</span>
        <?php else: ?>
          <span><span class="imdb"><?= e($film['certificate']) ?></span>AGE RATING</span>
        <?php endif; ?>
      </div>
      <div class="credits">
        <?php if ($film['director'] !== ''): ?>
          <div><b><?= e($film['director_label']) ?>:</b> <?= e($film['director']) ?></div>
        <?php endif; ?>
        <?php if ($film['cast']): ?>
          <div><b>Stars:</b> <?= e(implode(', ', array_slice($film['cast'], 0, 4))) ?></div>
        <?php endif; ?>
        <div><b><?= $status === 'soon' ? 'Opens' : 'Released' ?>:</b> <?= e($film['opens_on']->format('j M Y')) ?></div>
      </div>
      <p class="about"><b>About:</b> <?= e($film['synopsis']) ?></p>
      <div class="hr"></div>

      <?php if ($bookable): ?>
        <form class="book" method="get" action="/film.php" data-autosubmit data-url="<?= e($url) ?>" data-cinema="<?= e($cinema['slug']) ?>" data-status="<?= e($statusText) ?>" aria-label="Book tickets">
          <?php // These come first so that a pressed button, later in the form, overrides them. ?>
          <input type="hidden" name="slug" value="<?= e($film['slug']) ?>">
          <?php if (count($languages) > 1): ?><input type="hidden" name="lang" value="<?= e($lang) ?>"><?php endif; ?>
          <input type="hidden" name="format" value="<?= e($format) ?>">
          <dl class="bk">
            <dt><label for="bk-cinema">Cinema:</label></dt>
            <dd>
              <span class="drop">
                <select id="bk-cinema" name="cinema">
                  <?php foreach ($cinemas as $c): ?>
                    <option value="<?= e($c['slug']) ?>"<?= $c['id'] === $cinema['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                  <?php endforeach; ?>
                </select><?= icon('chev') ?>
              </span>
            </dd>
            <dt><label for="bk-date">Date:</label></dt>
            <dd>
              <span class="drop">
                <select id="bk-date" name="date">
                  <?php foreach ($days as $key => $d): ?>
                    <option value="<?= $key ?>"<?= $key === $date ? ' selected' : '' ?>><?= $d == $today ? 'Today, ' : '' ?><?= e(d_long($d)) ?></option>
                  <?php endforeach; ?>
                </select><?= icon('chev') ?>
              </span>
              <noscript><button class="tp tp-go" type="submit">Show times</button></noscript>
            </dd>
            <?php if (count($languages) > 1): ?>
              <dt id="bk-lang">Language:</dt>
              <dd role="group" aria-labelledby="bk-lang">
                <?php foreach ($languages as $l): ?>
                  <button class="tp" type="submit" name="lang" value="<?= e($l) ?>" aria-pressed="<?= $l === $lang ? 'true' : 'false' ?>"><?= e($l) ?></button>
                <?php endforeach; ?>
              </dd>
            <?php endif; ?>
            <dt id="bk-format">Format:</dt>
            <dd role="group" aria-labelledby="bk-format">
              <?php foreach (['2D', '3D', 'Dolby Atmos'] as $f): $offered = in_array($f, $formats, true); ?>
                <?php $why = !$offered && in_array($f, $film['formats'], true) && $atmosAt ? 'Dolby Atmos is only at ' . implode(' and ', $atmosAt) : null; ?>
                <button class="tp" type="submit" name="format" value="<?= e($f) ?>" aria-pressed="<?= $f === $format ? 'true' : 'false' ?>"<?= $offered ? '' : ' disabled' ?><?= $why ? ' title="' . e($why) . '"' : '' ?>><?= e(format_label($f)) ?></button>
              <?php endforeach; ?>
            </dd>
            <dt id="bk-time">Time:</dt>
            <dd role="group" aria-labelledby="bk-time">
              <?php if ($shows): ?>
                <?php foreach ($shows as $s): ?>
                  <button class="tp" type="submit" name="show" value="<?= $s['id'] ?>" aria-pressed="<?= $show && $s['id'] === $show['id'] ? 'true' : 'false' ?>"><?= $s['starts_at']->format('H:i') ?></button>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="note">No <?= e(format_label($format)) ?> shows at <?= e($cinema['name']) ?> on this day.</span>
              <?php endif; ?>
            </dd>
            <?php if ($show && $map): ?>
              <dt class="dt-top">Seats:</dt>
              <dd>
                <div class="halls">
                  <a class="hall" href="/seats.php?show=<?= $show['id'] ?>" aria-label="<?= e("Choose seats in hall {$map['hall']}, {$map['free']} of {$map['total']} free") ?>">
                    <?= mini_map($map) ?>
                    <small>HALL <?= $map['hall'] ?>, <?= $map['free'] ?> SEAT<?= $map['free'] === 1 ? '' : 'S' ?> FREE</small>
                  </a>
                </div>
              </dd>
            <?php endif; ?>
          </dl>
          <div class="go-row">
            <small><?= $show ? e(ugx(Pricing::seatPrice($day, $format))) . ' A SEAT' : '' ?></small>
            <?php if ($show): ?>
              <a class="go" href="/seats.php?show=<?= $show['id'] ?>">Choose seats</a>
            <?php else: ?>
              <button class="go" type="button" disabled>Choose seats</button>
            <?php endif; ?>
          </div>
        </form>
        <p class="sr" id="book-status" role="status"></p>

      <?php elseif ($status === 'soon'): ?>
        <form class="book" id="remind" method="post" action="/remind.php">
          <?= csrf_field() ?>
          <input type="hidden" name="film" value="<?= e($film['slug']) ?>">
          <input type="hidden" name="return" value="/film.php?slug=<?= e(rawurlencode($film['slug'])) ?>">
          <dl class="bk">
            <dt>Opens:</dt>
            <dd><?= e(d_long($film['opens_on']) . ' ' . $film['opens_on']->format('Y')) ?>, <?= count($cinemas) === 1 ? 'at ' . e($cinemas[0]['name']) : 'at all ' . e($counts[count($cinemas) - 1] ?? (string) count($cinemas)) . ' cinemas' ?></dd>
          </dl>
          <?php // Without JavaScript the number goes in here. With it, Remind me opens the sheet instead. ?>
          <label class="phone nojs-only"><span>+256</span><input name="phone" inputmode="tel" autocomplete="tel-national" placeholder="772 123 456" aria-label="Phone number for a WhatsApp reminder"></label>
          <div class="go-row">
            <small>Booking opens <?= $advance === 14 ? 'two weeks' : e($advance . ' days') ?> before</small>
            <button class="go" type="submit" data-remind="<?= e($film['slug']) ?>" data-title="<?= e($film['title']) ?>">Remind me</button>
          </div>
        </form>

      <?php else: ?>
        <dl class="bk">
          <dt>Ended:</dt>
          <dd>Its run finished on <?= e(d_long($film['ends_on']) . ' ' . $film['ends_on']->format('Y')) ?></dd>
        </dl>
        <div class="go-row"><a class="go" href="/#now">See what’s on</a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
page_close();
