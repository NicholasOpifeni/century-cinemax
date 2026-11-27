<?php
declare(strict_types=1);

// Edit a film: what the site says about it, when it runs, and its formats and languages.
// Refresh from TMDB updates only the poster, backdrop, trailer, rating and colours.

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Admin;
use App\FilmImport;
use App\Films;
use App\Tmdb;

Admin::require();

$film = Films::byId((int) ($_POST['id'] ?? $_GET['id'] ?? 0));
if ($film === null) {
    abort(404);
}
$id = $film['id'];
$here = '/admin/film.php?id=' . $id;
$formats = ['2D', '3D', 'Dolby Atmos'];

if (is_post() && ($_POST['action'] ?? '') === 'refresh') {
    if (!$film['tmdb_id']) {
        flash('This film isn’t linked to TMDB, so there’s nothing to refresh.', 'error');
        redirect($here, 303);
    }
    try {
        $tmdb = Tmdb::fromConfig();
        $details = $tmdb->movie($film['tmdb_id']);
        $media = FilmImport::media($tmdb, $details);
        db_run(
            'UPDATE films SET poster_path = ?, backdrop_path = ?, trailer_key = ?, vote_average = ?, vote_count = ?, accent_hex = ?, tone_hex = ?,
                    runtime_min = COALESCE(runtime_min, ?), cast = IF(JSON_LENGTH(cast) = 0, ?, cast)
             WHERE id = ?',
            [
                $media['poster_path'], $media['backdrop_path'], $media['trailer_key'] ?? $film['trailer_key'], $media['vote_average'],
                $media['vote_count'], $media['accent_hex'], $media['tone_hex'],
                (int) ($details['runtime'] ?? 0) > 0 ? (int) $details['runtime'] : null,
                json_encode(array_slice(array_column($details['credits']['cast'] ?? [], 'name'), 0, 6), JSON_UNESCAPED_UNICODE),
                $id,
            ],
        );
        flash('Updated the poster, backdrop, trailer, rating and colours from TMDB.');
    } catch (RuntimeException $e) {
        flash('TMDB didn’t answer, so nothing changed. Try again in a minute.', 'error');
    }
    redirect($here, 303);
}

$input = [
    'title' => $film['title'],
    'slug' => $film['slug'],
    'synopsis' => $film['synopsis'],
    'certificate' => $film['certificate'],
    'runtime_min' => (string) ($film['runtime_min'] ?? ''),
    'genres' => implode("\n", $film['genres']),
    'languages' => implode("\n", $film['languages']),
    'formats' => $film['formats'],
    'director_label' => $film['director_label'],
    'director' => $film['director'],
    'cast' => implode("\n", $film['cast']),
    'opens_on' => $film['opens_on']->format('Y-m-d'),
    'ends_on' => $film['ends_on']->format('Y-m-d'),
    'trailer_key' => (string) ($film['trailer_key'] ?? ''),
    'featured' => $film['featured'],
    'sort_order' => (string) $film['sort_order'],
];
$errors = [];

if (is_post()) {
    $text = fn (string $key, int $max): string => mb_substr(is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '', 0, $max);
    foreach (['title' => 160, 'slug' => 80, 'synopsis' => 2000, 'certificate' => 10, 'runtime_min' => 3, 'genres' => 500, 'languages' => 500,
             'director_label' => 20, 'director' => 160, 'cast' => 1000, 'opens_on' => 10, 'ends_on' => 10, 'trailer_key' => 20, 'sort_order' => 5] as $key => $max) {
        $input[$key] = $text($key, $max);
    }
    $input['formats'] = array_values(array_intersect($formats, array_filter((array) ($_POST['formats'] ?? []), 'is_string')));
    $input['featured'] = !empty($_POST['featured']);

    // One item per line, so a language like "Japanese, English subtitles" can keep its comma.
    $lines = fn (string $value, int $max): array => array_slice(array_values(array_unique(array_filter(
        array_map('trim', preg_split('/\R/', $value) ?: []),
        fn (string $line): bool => $line !== '',
    ))), 0, $max);
    $genres = $lines($input['genres'], 5);
    $languages = $lines($input['languages'], 4);
    $cast = $lines($input['cast'], 8);
    $date = fn (string $value): ?DateTimeImmutable => ($d = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $d->format('Y-m-d') === $value ? $d : null;
    $opens = $date($input['opens_on']);
    $ends = $date($input['ends_on']);

    if ($input['title'] === '') {
        $errors['title'] = 'Give the film a title.';
    }
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $input['slug'])) {
        $errors['slug'] = 'Use lowercase letters, numbers and single hyphens, like the-wild-robot.';
    } elseif (db_run('SELECT 1 FROM films WHERE slug = ? AND id <> ?', [$input['slug'], $id])->fetchColumn()) {
        $errors['slug'] = 'Another film already has that address.';
    }
    if ($input['synopsis'] === '') {
        $errors['synopsis'] = 'Write a short synopsis. The film page and the hero show it.';
    }
    if ($input['certificate'] === '') {
        $errors['certificate'] = 'Give an age rating, or TBC.';
    }
    if ($input['runtime_min'] !== '' && (!ctype_digit($input['runtime_min']) || (int) $input['runtime_min'] < 1 || (int) $input['runtime_min'] > 400)) {
        $errors['runtime_min'] = 'Runtime is in minutes, between 1 and 400. Leave it empty if it isn’t known yet.';
    }
    if (!$genres) {
        $errors['genres'] = 'Add at least one genre.';
    }
    if (!$languages) {
        $errors['languages'] = 'Add at least one language.';
    } elseif (max(array_map('mb_strlen', $languages)) > 40) {
        $errors['languages'] = 'Keep each language under 40 characters.';
    }
    if (!$input['formats']) {
        $errors['formats'] = 'Pick at least one format.';
    }
    if (!in_array($input['director_label'], ['Director', 'Directors'], true)) {
        $input['director_label'] = 'Director';
    }
    if ($opens === null) {
        $errors['opens_on'] = 'Pick the opening date.';
    }
    if ($ends === null) {
        $errors['ends_on'] = 'Pick the last day it shows.';
    } elseif ($opens !== null && $ends < $opens) {
        $errors['ends_on'] = 'The last day can’t be before the opening date.';
    }
    if ($input['trailer_key'] !== '' && !preg_match('/^[\w-]{6,20}$/', $input['trailer_key'])) {
        $errors['trailer_key'] = 'Use the part of the YouTube link after v=, like dQw4w9WgXcQ, or leave it empty.';
    }
    if ($input['sort_order'] !== '' && !preg_match('/^-?\d{1,4}$/', $input['sort_order'])) {
        $errors['sort_order'] = 'Use a whole number. Lower numbers come first.';
    }

    if (!$errors) {
        db_run(
            'UPDATE films SET title = ?, slug = ?, synopsis = ?, certificate = ?, runtime_min = ?, genres = ?, languages = ?, formats = ?,
                    director_label = ?, director = ?, cast = ?, opens_on = ?, ends_on = ?, trailer_key = ?, featured = ?, sort_order = ?
             WHERE id = ?',
            [
                $input['title'], $input['slug'], $input['synopsis'], $input['certificate'],
                $input['runtime_min'] === '' ? null : (int) $input['runtime_min'],
                json_encode($genres, JSON_UNESCAPED_UNICODE), json_encode($languages, JSON_UNESCAPED_UNICODE), json_encode($input['formats']),
                $input['director_label'], $input['director'], json_encode($cast, JSON_UNESCAPED_UNICODE),
                $opens->format('Y-m-d'), $ends->format('Y-m-d'), $input['trailer_key'] === '' ? null : $input['trailer_key'],
                $input['featured'] ? 1 : 0, (int) $input['sort_order'], $id,
            ],
        );
        // Shows already planned that no longer fit the film. They stay until someone moves or cancels them.
        $misfits = (int) db_run(
            'SELECT COUNT(*) FROM showtimes WHERE film_id = ? AND starts_at > NOW()
               AND (DATE(starts_at) < ? OR DATE(starts_at) > ? OR NOT JSON_CONTAINS(?, JSON_QUOTE(format)) OR NOT JSON_CONTAINS(?, JSON_QUOTE(language)))',
            [$id, $opens->format('Y-m-d'), $ends->format('Y-m-d'), json_encode($input['formats']), json_encode($languages, JSON_UNESCAPED_UNICODE)],
        )->fetchColumn();
        flash('Saved ' . $input['title'] . '.');
        if ($misfits > 0) {
            flash("$misfits upcoming " . ($misfits === 1 ? 'show doesn’t' : 'shows don’t') . ' fit these dates, formats or languages any more. Move or cancel them under Showtimes.', 'error');
        }
        redirect($here, 303);
    }
}

/** The error under a field, and the attributes that tie it to the field. */
$err = static function (string $key) use ($errors): array {
    return isset($errors[$key])
        ? [' aria-invalid="true" aria-describedby="err-' . $key . '"', '<p class="err" id="err-' . $key . '">' . e($errors[$key]) . '</p>']
        : ['', ''];
};
$field = static function (string $key, string $label, string $control, string $hint = '') use ($err): string {
    [, $message] = $err($key);
    return '<div class="field"><label for="f-' . $key . '">' . e($label) . ($hint !== '' ? ' <small>' . e($hint) . '</small>' : '') . '</label>' . $control . $message . '</div>';
};
$text = static function (string $key, string $type = 'text', string $extra = '') use ($input, $err): string {
    [$attrs] = $err($key);
    return '<input id="f-' . $key . '" type="' . $type . '" name="' . $key . '" value="' . e((string) $input[$key]) . '"' . $extra . $attrs . '>';
};
$area = static function (string $key, int $rows) use ($input, $err): string {
    [$attrs] = $err($key);
    return '<textarea id="f-' . $key . '" name="' . $key . '" rows="' . $rows . '"' . $attrs . '>' . e((string) $input[$key]) . '</textarea>';
};

admin_open($film['title'], 'films', ['films' => [$film]]);
?>
<div class="adm-page">
  <p class="crumbs"><a href="/admin/films.php">Films</a></p>
  <div class="adm-head">
    <div>
      <h1><?= e($film['title']) ?></h1>
      <p><?= e(Films::statusLine($film)) ?>. <a href="/film.php?slug=<?= e($film['slug']) ?>" target="_blank" rel="noopener">See it on the site</a></p>
    </div>
  </div>
  <?php if ($errors): ?>
    <p class="adm-alert" role="alert">Some things need fixing before this can be saved. They’re marked below.</p>
  <?php endif; ?>

  <div class="film-edit">
    <form class="adm-form" method="post" action="/admin/film.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="save">

      <fieldset>
        <legend>On the site</legend>
        <?= $field('title', 'Title', $text('title', 'text', ' maxlength="160" required')) ?>
        <?= $field('slug', 'Web address', $text('slug', 'text', ' maxlength="80" required spellcheck="false"'), '/film.php?slug=…, changing it breaks old links') ?>
        <?= $field('synopsis', 'Synopsis', $area('synopsis', 5), 'our own words win over TMDB’s') ?>
        <div class="field-row">
          <?= $field('certificate', 'Age rating', $text('certificate', 'text', ' maxlength="10" list="certificates" required')) ?>
          <?= $field('runtime_min', 'Runtime', $text('runtime_min', 'number', ' min="1" max="400" inputmode="numeric"'), 'minutes') ?>
        </div>
        <datalist id="certificates"><option value="G"><option value="PG"><option value="PG-13"><option value="15"><option value="R"><option value="18"><option value="TBC"></datalist>
        <div class="field-row">
          <?= $field('genres', 'Genres', $area('genres', 4), 'one per line') ?>
          <?= $field('cast', 'Stars', $area('cast', 4), 'one per line, the first four show') ?>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="f-director_label">Credit</label>
            <select id="f-director_label" name="director_label">
              <?php foreach (['Director', 'Directors'] as $label): ?>
                <option<?= $input['director_label'] === $label ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?= $field('director', 'Name', $text('director', 'text', ' maxlength="160"'), 'two names: “Anthony Russo, Joe Russo”') ?>
        </div>
        <?= $field('trailer_key', 'YouTube trailer', $text('trailer_key', 'text', ' maxlength="20" spellcheck="false"'), 'the part after v=') ?>
      </fieldset>

      <fieldset>
        <legend>When and how it shows</legend>
        <div class="field-row">
          <?= $field('opens_on', 'Opens', $text('opens_on', 'date', ' required')) ?>
          <?= $field('ends_on', 'Last day', $text('ends_on', 'date', ' required'), 'usually five weeks later') ?>
        </div>
        <?php [$fAttrs, $fMessage] = $err('formats'); ?>
        <div class="field" role="group" aria-labelledby="formats-label"<?= $fAttrs !== '' ? ' aria-describedby="err-formats"' : '' ?>>
          <span class="label" id="formats-label">Formats <small>Atmos only plays in Atmos halls</small></span>
          <div class="checks">
            <?php foreach ($formats as $format): ?>
              <label class="check"><input type="checkbox" name="formats[]" value="<?= e($format) ?>"<?= in_array($format, $input['formats'], true) ? ' checked' : '' ?>><span><?= e($format) ?></span></label>
            <?php endforeach; ?>
          </div>
          <?= $fMessage ?>
        </div>
        <?= $field('languages', 'Languages', $area('languages', 2), 'one per line, like “Japanese, English subtitles”') ?>
        <div class="field-row">
          <div class="field">
            <span class="label">Home page</span>
            <label class="check"><input type="checkbox" name="featured" value="1"<?= $input['featured'] ? ' checked' : '' ?>><span>Feature in the hero carousel</span></label>
          </div>
          <?= $field('sort_order', 'Order', $text('sort_order', 'number', ' step="1"'), 'lower comes first') ?>
        </div>
      </fieldset>

      <div class="adm-actions">
        <button class="btn btn-sm btn-y" type="submit">Save changes</button>
        <a class="btn btn-sm btn-o" href="/admin/films.php">Cancel</a>
      </div>
    </form>

    <aside class="film-side <?= film_class($film) ?>" aria-label="From TMDB">
      <?= pic($film, 'w342', 'side-poster', false) ?>
      <dl class="side-facts">
        <dt>TMDB</dt>
        <dd><?= $film['tmdb_id'] ? '<a href="https://www.themoviedb.org/movie/' . (int) $film['tmdb_id'] . '" target="_blank" rel="noopener">Film ' . (int) $film['tmdb_id'] . '</a>' : 'Not linked' ?></dd>
        <dt>Rating</dt>
        <dd><?= $film['vote_count'] > 0 ? e(number_format((float) $film['vote_average'], 1)) . ' from ' . e(number_format($film['vote_count'])) . ' votes' : 'No votes yet' ?></dd>
        <dt>Trailer</dt>
        <dd><?= $film['trailer_key'] ? '<a href="https://www.youtube.com/watch?v=' . e($film['trailer_key']) . '" target="_blank" rel="noopener">Watch on YouTube</a>' : 'None' ?></dd>
        <dt>Colours</dt>
        <dd class="swatches"><i class="sw-accent" title="<?= e($film['accent_hex']) ?>"></i><i class="sw-tone" title="<?= e($film['tone_hex']) ?>"></i><code><?= e($film['accent_hex']) ?>, <?= e($film['tone_hex']) ?></code></dd>
      </dl>
      <?php if ($film['tmdb_id']): ?>
        <form method="post" action="/admin/film.php">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="refresh">
          <button class="btn btn-sm btn-o" type="submit">Refresh from TMDB</button>
          <p class="hint">Updates the poster, backdrop, trailer, rating and colours. Your words, dates and formats stay as they are.</p>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php
page_close();
