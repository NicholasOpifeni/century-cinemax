<?php
declare(strict_types=1);

// JSON for the header search dropdown, or a results page when JavaScript is off.

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Films;
use App\Tmdb;

$query = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 80) : '');
$films = $query !== '' ? Films::search($query, wants_json() ? 6 : 24) : [];

if (wants_json()) {
    json_out(['films' => array_map(fn (array $film): array => [
        'title' => $film['title'],
        'url' => '/film.php?slug=' . rawurlencode($film['slug']),
        'status' => Films::statusLine($film),
        'poster' => Tmdb::imageUrl($film['poster_path'], 'w92'),
    ], $films)]);
}

page_open([
    'title' => $query !== '' ? 'Search results for ' . $query : 'Search',
    'noindex' => true,
]);
?>
<section class="sec search-page" aria-labelledby="search-h">
  <div class="sec-h">
    <div>
      <h1 id="search-h"><?= $query !== '' ? 'Results for “' . e($query) . '”' : 'Search' ?></h1>
      <p><?= $query === '' ? 'Search by film title, director or actor.' : (count($films) === 1 ? 'One film matches.' : (count($films) ? count($films) . ' films match.' : 'No films match that. Try a title or an actor.')) ?></p>
    </div>
    <form class="search-form" role="search" method="get" action="/search.php">
      <label class="sr" for="search-again">Search films or actors</label>
      <input id="search-again" name="q" type="search" value="<?= e($query) ?>" placeholder="Search films or actors" maxlength="80">
      <button class="btn btn-sm btn-y" type="submit">Search</button>
    </form>
  </div>
  <?php if ($films): ?>
    <div class="films">
      <?php foreach ($films as $film): ?>
        <article class="film">
          <a class="open" href="/film.php?slug=<?= e($film['slug']) ?>"><?= pic($film) ?><h3><?= e($film['title']) ?></h3></a>
          <span class="when"><?= e(Films::statusLine($film)) ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php
page_close();
