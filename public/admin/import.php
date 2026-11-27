<?php
declare(strict_types=1);

// Add a film from TMDB: search, then import. The new film opens in the editor so its dates,
// formats and languages can be checked before it shows on the site.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;
use App\FilmImport;
use App\Tmdb;

Admin::require();
$tmdb = Tmdb::fromConfig();

if (is_post()) {
    $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);
    $existing = $tmdbId > 0 ? db_run('SELECT id FROM films WHERE tmdb_id = ?', [$tmdbId])->fetchColumn() : false;
    if ($existing) {
        flash('That film is already here.');
        redirect('/admin/film.php?id=' . (int) $existing, 303);
    }
    try {
        $details = $tmdbId > 0 ? $tmdb->movie($tmdbId) : [];
    } catch (RuntimeException) {
        $details = [];
    }
    if (empty($details['id'])) {
        flash('TMDB didn’t find that film, or didn’t answer. Search again and try once more.', 'error');
        redirect('/admin/import.php', 303);
    }

    $film = FilmImport::newFilm($tmdb, $details);
    $order = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM films')->fetchColumn();
    db_run(
        'INSERT INTO films (slug, tmdb_id, title, synopsis, certificate, runtime_min, genres, languages, formats, director_label, director, cast,
                            opens_on, ends_on, poster_path, backdrop_path, trailer_key, vote_average, vote_count, accent_hex, tone_hex, featured, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
        [
            $film['slug'], $film['tmdb_id'], $film['title'], $film['synopsis'], $film['certificate'], $film['runtime_min'],
            json_encode($film['genres'], JSON_UNESCAPED_UNICODE), json_encode($film['languages'], JSON_UNESCAPED_UNICODE),
            json_encode($film['formats']), $film['director_label'], $film['director'], json_encode($film['cast'], JSON_UNESCAPED_UNICODE),
            $film['opens_on'], $film['ends_on'], $film['poster_path'], $film['backdrop_path'], $film['trailer_key'],
            $film['vote_average'], $film['vote_count'], $film['accent_hex'], $film['tone_hex'], $order,
        ],
    );
    flash('Added ' . $film['title'] . '. Check its opening date, formats, languages and age rating, then save.');
    redirect('/admin/film.php?id=' . (int) db()->lastInsertId(), 303);
}

$q = mb_substr(is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '', 0, 100);
$year = is_string($_GET['year'] ?? null) && preg_match('/^(19|20)\d\d$/', $_GET['year']) ? $_GET['year'] : '';
$results = [];
$error = null;
if ($q !== '') {
    try {
        $results = array_slice($tmdb->search($q, $year !== '' ? (int) $year : null), 0, 12);
    } catch (RuntimeException) {
        $error = 'TMDB didn’t answer. Check the internet connection and the token in config/secrets.php, then search again.';
    }
}
$here = $results ? db()->query('SELECT tmdb_id, id FROM films WHERE tmdb_id IS NOT NULL')->fetchAll(PDO::FETCH_KEY_PAIR) : [];

admin_open('Add a film', 'films');
?>
<div class="adm-page">
  <p class="crumbs"><a href="/admin/films.php">Films</a></p>
  <div class="adm-head">
    <div>
      <h1>Add a film from TMDB</h1>
      <p>Search by title. The poster, backdrop, trailer, cast and colours come with it.</p>
    </div>
  </div>

  <form class="adm-search" method="get" action="/admin/import.php" role="search">
    <label class="field"><span>Title</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="The Wild Robot" required></label>
    <label class="field year"><span>Year <small>optional</small></span><input name="year" value="<?= e($year) ?>" inputmode="numeric" maxlength="4" placeholder="2026"></label>
    <button class="btn btn-sm btn-y" type="submit">Search</button>
  </form>

  <?php if ($error !== null): ?>
    <p class="adm-alert" role="alert"><?= e($error) ?></p>
  <?php elseif ($q !== '' && !$results): ?>
    <p class="empty">Nothing on TMDB matches “<?= e($q) ?>”<?= $year !== '' ? ' in ' . e($year) : '' ?>. Try fewer words, or no year.</p>
  <?php endif; ?>

  <?php if ($results): ?>
    <ul class="tmdb-results">
      <?php foreach ($results as $r): $poster = Tmdb::imageUrl($r['poster_path'] ?? null, 'w185'); ?>
        <li>
          <div class="pic"><span class="pt"><?= e($r['title'] ?? '') ?></span><?php if ($poster): ?><img src="<?= e($poster) ?>" alt="" loading="lazy"><?php endif; ?></div>
          <div class="tmdb-body">
            <b><?= e($r['title'] ?? 'Untitled') ?></b>
            <small><?= !empty($r['release_date']) ? e((new DateTimeImmutable($r['release_date']))->format('j M Y')) : 'No release date yet' ?><?= ($r['original_title'] ?? '') !== ($r['title'] ?? '') ? ', ' . e($r['original_title']) : '' ?></small>
            <p><?= e(clip((string) ($r['overview'] ?? ''), 180)) ?></p>
          </div>
          <?php if (isset($here[$r['id']])): ?>
            <a class="btn btn-sm btn-o" href="/admin/film.php?id=<?= (int) $here[$r['id']] ?>">Already added</a>
          <?php else: ?>
            <form method="post" action="/admin/import.php">
              <?= csrf_field() ?>
              <input type="hidden" name="tmdb_id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-sm btn-y" type="submit">Add<span class="sr"> <?= e($r['title'] ?? '') ?></span></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php
page_close();
