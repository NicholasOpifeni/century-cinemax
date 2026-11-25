<?php
declare(strict_types=1);

/*
 * Loads database/films.json into the films table and fills in posters, backdrops, ratings,
 * trailers and poster colours from TMDB. Our synopses, dates, certificates, formats and
 * languages always win over TMDB's.
 *
 *   bin/php bin/import-films.php             import, then print what matched on TMDB
 *   bin/php bin/import-films.php --save-ids  also write the matched TMDB ids into films.json
 *   bin/php bin/import-films.php --offline   skip TMDB and use the stand-in colours
 *
 * Films with a tmdb_id in films.json are fetched by id, without searching.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\PosterColours;
use App\Tmdb;

const RUN_DAYS = 35;

$saveIds = in_array('--save-ids', $argv, true);
$offline = in_array('--offline', $argv, true);

$jsonFile = ROOT . '/database/films.json';
$films = json_decode((string) file_get_contents($jsonFile), true, 512, JSON_THROW_ON_ERROR);
$tmdb = Tmdb::fromConfig();

/** @return array{0: ?array, 1: string} the TMDB film and how we found it */
function find_on_tmdb(Tmdb $tmdb, array $film): array
{
    if (!empty($film['tmdb_id'])) {
        return [$tmdb->movie((int) $film['tmdb_id']), 'saved id'];
    }

    $results = $tmdb->search($film['q'], (int) $film['year']);
    if ($results) {
        return [$results[0], 'title and year'];
    }

    $results = $tmdb->search($film['q']);
    foreach ($results as $result) {
        if (str_starts_with((string) ($result['release_date'] ?? ''), (string) $film['year'])) {
            return [$result, 'title, same year'];
        }
    }
    return $results ? [$results[0], 'title only, check'] : [null, 'no match'];
}

$upsert = db()->prepare(<<<'SQL'
    INSERT INTO films (slug, tmdb_id, title, synopsis, certificate, runtime_min, genres, languages, formats,
        director_label, director, cast, opens_on, ends_on, poster_path, backdrop_path, trailer_key,
        vote_average, vote_count, accent_hex, tone_hex, featured, sort_order)
    VALUES (:slug, :tmdb_id, :title, :synopsis, :certificate, :runtime_min, :genres, :languages, :formats,
        :director_label, :director, :cast, :opens_on, :ends_on, :poster_path, :backdrop_path, :trailer_key,
        :vote_average, :vote_count, :accent_hex, :tone_hex, :featured, :sort_order) AS new
    ON DUPLICATE KEY UPDATE
        tmdb_id = new.tmdb_id, title = new.title, synopsis = new.synopsis, certificate = new.certificate,
        runtime_min = new.runtime_min, genres = new.genres, languages = new.languages, formats = new.formats,
        director_label = new.director_label, director = new.director, cast = new.cast, opens_on = new.opens_on,
        ends_on = new.ends_on, poster_path = new.poster_path, backdrop_path = new.backdrop_path,
        trailer_key = new.trailer_key, vote_average = new.vote_average, vote_count = new.vote_count,
        accent_hex = new.accent_hex, tone_hex = new.tone_hex, featured = new.featured, sort_order = new.sort_order
    SQL);

$report = [];
$usedIds = [];

foreach ($films as $i => $film) {
    $found = null;
    $how = $offline ? 'offline' : 'no match';
    $details = [];

    if (!$offline) {
        try {
            [$found, $how] = find_on_tmdb($tmdb, $film);
            if ($found) {
                $details = isset($found['credits']) ? $found : $tmdb->movie((int) $found['id']);
            }
        } catch (RuntimeException $e) {
            $how = 'error: ' . $e->getMessage();
        }
    }

    $tmdbId = $found ? (int) $found['id'] : null;
    if ($tmdbId !== null && isset($usedIds[$tmdbId])) {
        $how = 'same TMDB film as ' . $usedIds[$tmdbId] . ', skipped';
        $found = null;
        $details = [];
        $tmdbId = null;
    }
    if ($tmdbId !== null) {
        $usedIds[$tmdbId] = $film['title'];
    }

    $runtime = $film['runtime_min'] ?? null;
    if (!$runtime && (int) ($details['runtime'] ?? 0) > 0) {
        $runtime = (int) $details['runtime'];
    }
    $cast = $film['cast'] ?: array_slice(array_column($details['credits']['cast'] ?? [], 'name'), 0, 6);
    $poster = $details['poster_path'] ?? null;
    $trailer = Tmdb::bestTrailer($details['videos']['results'] ?? []);

    $colours = ['accent' => strtoupper($film['accent']), 'tone' => strtoupper($film['tone'])];
    $colourFrom = 'stand-in';
    if ($poster) {
        try {
            $colours = PosterColours::fromImage($tmdb->imageBytes($poster, 'w92'));
            $colourFrom = 'poster';
        } catch (RuntimeException) {
            // Keep the stand-in colours.
        }
    }

    $opens = new DateTimeImmutable($film['opens_on']);
    $ends = isset($film['ends_on']) ? new DateTimeImmutable($film['ends_on']) : $opens->modify('+' . RUN_DAYS . ' days');

    $upsert->execute([
        'slug' => $film['slug'],
        'tmdb_id' => $tmdbId,
        'title' => $film['title'],
        'synopsis' => $film['synopsis'],
        'certificate' => $film['certificate'],
        'runtime_min' => $runtime,
        'genres' => json_encode($film['genres'], JSON_UNESCAPED_UNICODE),
        'languages' => json_encode($film['languages'], JSON_UNESCAPED_UNICODE),
        'formats' => json_encode($film['formats'], JSON_UNESCAPED_UNICODE),
        'director_label' => $film['director_label'],
        'director' => $film['director'],
        'cast' => json_encode($cast, JSON_UNESCAPED_UNICODE),
        'opens_on' => $opens->format('Y-m-d'),
        'ends_on' => $ends->format('Y-m-d'),
        'poster_path' => $poster,
        'backdrop_path' => $details['backdrop_path'] ?? null,
        'trailer_key' => $trailer,
        'vote_average' => (int) ($details['vote_count'] ?? 0) > 0 ? round((float) $details['vote_average'], 1) : null,
        'vote_count' => (int) ($details['vote_count'] ?? 0),
        'accent_hex' => $colours['accent'],
        'tone_hex' => $colours['tone'],
        'featured' => empty($film['featured']) ? 0 : 1,
        'sort_order' => ($i + 1) * 10,
    ]);

    if ($saveIds && $tmdbId !== null) {
        $films[$i]['tmdb_id'] = $tmdbId;
    }

    $report[] = [
        'ours' => $film['title'],
        'tmdb' => $details['title'] ?? '',
        'released' => $details['release_date'] ?? '',
        'id' => $tmdbId ?? '',
        'how' => $how,
        'trailer' => $trailer ? 'yes' : 'no',
        'colours' => $colours['accent'] . ' ' . $colours['tone'] . " ($colourFrom)",
    ];
}

function pad(string $text, int $width): string
{
    $text = mb_strlen($text) > $width ? mb_substr($text, 0, $width - 1) . '…' : $text;
    return $text . str_repeat(' ', $width - mb_strlen($text));
}

$columns = ['ours' => 30, 'tmdb' => 30, 'released' => 10, 'id' => 8, 'how' => 18, 'trailer' => 7, 'colours' => 27];
$headings = ['ours' => 'Our title', 'tmdb' => 'TMDB title', 'released' => 'Released', 'id' => 'TMDB id', 'how' => 'Matched by', 'trailer' => 'Trailer', 'colours' => 'Accent, panel'];
$line = fn (array $row): string => implode('  ', array_map(fn ($key, $width) => pad((string) $row[$key], $width), array_keys($columns), $columns));

echo $line($headings), "\n", str_repeat('-', array_sum($columns) + 2 * (count($columns) - 1)), "\n";
foreach ($report as $row) {
    echo rtrim($line($row)), "\n";
}

if ($saveIds) {
    // Only touch the tmdb_id lines, so the rest of the file keeps its layout.
    $json = (string) file_get_contents($jsonFile);
    foreach ($films as $film) {
        $json = preg_replace(
            '/("slug": "' . preg_quote($film['slug'], '/') . '",(?:(?!"slug":).)*?"tmdb_id": )(null|\d+)/s',
            '${1}' . ($film['tmdb_id'] ?? 'null'),
            $json,
            1,
        );
    }
    file_put_contents($jsonFile, $json);
    echo "\nSaved the TMDB ids to database/films.json.\n";
}

echo "\nImported " . count($films) . " films.\n";
