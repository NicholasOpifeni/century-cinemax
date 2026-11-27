<?php
declare(strict_types=1);

// Films: every film with its status, dates and formats, and a way to add more from TMDB.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;
use App\Films;

Admin::require();

$films = Films::all();
$ahead = db()->query('SELECT film_id, COUNT(*) FROM showtimes WHERE starts_at > NOW() GROUP BY film_id')->fetchAll(PDO::FETCH_KEY_PAIR);

$tabs = ['all' => 'All', 'now' => 'Now showing', 'soon' => 'Coming soon', 'ended' => 'Ended'];
$show = is_string($_GET['show'] ?? null) && isset($tabs[$_GET['show']]) ? $_GET['show'] : 'all';
$counts = array_count_values(array_map(Films::status(...), $films));
$listed = $show === 'all' ? $films : array_values(array_filter($films, fn (array $f): bool => Films::status($f) === $show));

admin_open('Films', 'films');
?>
<div class="adm-page">
  <div class="adm-head">
    <div>
      <h1>Films</h1>
      <p>A film shows on the site from its opening date to its end date. It can be booked from <?= (int) App\Settings::int('advance_days') ?> days before it opens.</p>
    </div>
    <a class="btn btn-sm btn-y" href="/admin/import.php">Add a film from TMDB</a>
  </div>

  <nav class="days" aria-label="Which films">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="pill" href="/admin/films.php<?= $key === 'all' ? '' : '?show=' . $key ?>" aria-current="<?= $key === $show ? 'true' : 'false' ?>"><?= e($label) ?> <small><?= $key === 'all' ? count($films) : (int) ($counts[$key] ?? 0) ?></small></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$listed): ?>
    <p class="empty">No films here.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="adm-table films-table">
        <thead><tr><th><span class="sr">Poster</span></th><th>Film</th><th>Status</th><th>Formats</th><th>Languages</th><th class="num">Shows ahead</th><th><span class="sr">Edit</span></th></tr></thead>
        <tbody>
          <?php foreach ($listed as $film): $status = Films::status($film); ?>
            <tr>
              <td class="thumb"><?= pic($film, 'w92') ?></td>
              <td>
                <a class="strong" href="/admin/film.php?id=<?= $film['id'] ?>"><?= e($film['title']) ?></a><?= $film['featured'] ? ' <span class="tag">Featured</span>' : '' ?>
                <small><?= e($film['certificate']) ?>, <?= e(run_short($film['runtime_min'])) ?><?= $film['tmdb_id'] ? '' : ', not linked to TMDB' ?></small>
              </td>
              <td>
                <span class="state state-<?= $status ?>"><?= e(['now' => 'Showing', 'soon' => Films::isBookable($film) ? 'Booking open' : 'Coming soon', 'ended' => 'Ended'][$status]) ?></span>
                <small><?= e($film['opens_on']->format('j M Y')) ?> to <?= e($film['ends_on']->format('j M Y')) ?></small>
              </td>
              <td><?= e(implode(', ', array_map(format_label(...), $film['formats']))) ?></td>
              <td><?= e(implode('; ', $film['languages'])) ?></td>
              <td class="num"><?= (int) ($ahead[$film['id']] ?? 0) ?></td>
              <td><a class="btn btn-sm btn-o" href="/admin/film.php?id=<?= $film['id'] ?>">Edit<span class="sr"> <?= e($film['title']) ?></span></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
page_close();
