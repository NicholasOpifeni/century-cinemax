<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use RuntimeException;

/**
 * Turns a TMDB film into one of ours, for the admin's import and refresh buttons. TMDB fills in
 * what we don't know yet; our own synopsis, dates, certificate, formats and languages are never
 * overwritten by a refresh.
 */
final class FilmImport
{
    public const RUN_DAYS = 35;

    /** Images, trailer, rating and poster colours: what a refresh may update. */
    public static function media(Tmdb $tmdb, array $details): array
    {
        $poster = $details['poster_path'] ?? null;
        $colours = ['accent' => '#C6CDD8', 'tone' => '#0C0D11'];
        if ($poster) {
            try {
                $colours = PosterColours::fromImage($tmdb->imageBytes($poster, 'w92'));
            } catch (RuntimeException) {
                // Keep the stand-in colours.
            }
        }
        $votes = (int) ($details['vote_count'] ?? 0);
        return [
            'poster_path' => $poster,
            'backdrop_path' => $details['backdrop_path'] ?? null,
            'trailer_key' => Tmdb::bestTrailer($details['videos']['results'] ?? []),
            'vote_average' => $votes > 0 ? round((float) $details['vote_average'], 1) : null,
            'vote_count' => $votes,
            'accent_hex' => $colours['accent'],
            'tone_hex' => $colours['tone'],
        ];
    }

    /** Everything for a new film. The admin checks the dates, formats and languages afterwards. */
    public static function newFilm(Tmdb $tmdb, array $details): array
    {
        $today = new DateTimeImmutable('today');
        $released = !empty($details['release_date']) ? new DateTimeImmutable($details['release_date']) : null;
        $opens = $released !== null && $released > $today ? $released : $today;

        $directors = array_values(array_unique(array_column(
            array_filter($details['credits']['crew'] ?? [], fn (array $c): bool => ($c['job'] ?? '') === 'Director'),
            'name',
        )));

        return [
            'tmdb_id' => (int) $details['id'],
            'slug' => self::freeSlug((string) $details['title']),
            'title' => mb_substr((string) $details['title'], 0, 160),
            'synopsis' => trim((string) ($details['overview'] ?? '')) ?: 'Synopsis to come.',
            'certificate' => 'TBC',
            'runtime_min' => (int) ($details['runtime'] ?? 0) > 0 ? (int) $details['runtime'] : null,
            'genres' => array_slice(array_column($details['genres'] ?? [], 'name'), 0, 3),
            'languages' => [self::language($details)],
            'formats' => ['2D'],
            'director_label' => count($directors) > 1 ? 'Directors' : 'Director',
            'director' => implode(', ', array_slice($directors, 0, 2)),
            'cast' => array_slice(array_column($details['credits']['cast'] ?? [], 'name'), 0, 6),
            'opens_on' => $opens->format('Y-m-d'),
            'ends_on' => $opens->modify('+' . self::RUN_DAYS . ' days')->format('Y-m-d'),
        ] + self::media($tmdb, $details);
    }

    /** English, or the original language shown with English subtitles, as the cinemas do. */
    private static function language(array $details): string
    {
        $code = (string) ($details['original_language'] ?? 'en');
        if ($code === 'en') {
            return 'English';
        }
        foreach ($details['spoken_languages'] ?? [] as $spoken) {
            if (($spoken['iso_639_1'] ?? '') === $code && !empty($spoken['english_name'])) {
                return $spoken['english_name'] . ', English subtitles';
            }
        }
        return 'English';
    }

    /** "the-wild-robot", or "the-wild-robot-2" if that's taken. */
    public static function freeSlug(string $title, ?int $exceptId = null): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title))), '-');
        $base = substr($base !== '' ? $base : 'film', 0, 70);
        $slug = $base;
        for ($n = 2; db_run('SELECT 1 FROM films WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0])->fetchColumn(); $n++) {
            $slug = "$base-$n";
        }
        return $slug;
    }
}
