<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Films and where they are in their run. Status comes from the dates:
 *   now     opened on or before today and not past its end date
 *   soon    opens after today
 *   ended   its end date has passed
 * A film can be booked while it's showing, or from advance_days before it opens.
 */
final class Films
{
    private const COLUMNS = 'id, slug, tmdb_id, title, synopsis, certificate, runtime_min, genres, languages, formats,
        director_label, director, cast, opens_on, ends_on, poster_path, backdrop_path, trailer_key,
        vote_average, vote_count, accent_hex, tone_hex, featured, sort_order';

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }

    public static function bySlug(string $slug): ?array
    {
        $row = db_run('SELECT ' . self::COLUMNS . ' FROM films WHERE slug = ?', [$slug])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function byId(int $id): ?array
    {
        $row = db_run('SELECT ' . self::COLUMNS . ' FROM films WHERE id = ?', [$id])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    /** Every film, for the admin: showing first, then coming soon by opening date, then ended. */
    public static function all(): array
    {
        return self::many(
            'ORDER BY (ends_on < CURDATE()) , (opens_on > CURDATE()), IF(opens_on > CURDATE(), opens_on, NULL), sort_order, ends_on DESC',
            [],
        );
    }

    /** Films in cinemas on a given day, whether they opened long ago or open that day. */
    public static function showingOn(DateTimeImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        return self::many('WHERE opens_on <= ? AND ends_on >= ? ORDER BY sort_order', [$date, $date]);
    }

    public static function comingSoon(): array
    {
        return self::many('WHERE opens_on > ? ORDER BY opens_on, sort_order', [self::today()->format('Y-m-d')]);
    }

    public static function featured(): array
    {
        return self::many('WHERE featured = 1 AND ends_on >= ? ORDER BY sort_order', [self::today()->format('Y-m-d')]);
    }

    /** Upcoming films first, soonest first, then the featured films that are showing. */
    public static function withTrailers(int $limit = 8): array
    {
        $soon = array_filter(self::comingSoon(), fn (array $f): bool => $f['trailer_key'] !== null);
        $now = array_filter(self::featured(), fn (array $f): bool => $f['trailer_key'] !== null && self::status($f) === 'now');
        return array_slice([...$soon, ...$now], 0, $limit);
    }

    /** Matches the title, director or cast, ignoring case and accents. */
    public static function search(string $query, int $limit = 6): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        return self::many(
            'WHERE ends_on >= ? AND (title LIKE ? OR director LIKE ? OR CAST(cast AS CHAR) COLLATE utf8mb4_unicode_ci LIKE ?)
             ORDER BY opens_on > ?, sort_order LIMIT ' . max(1, $limit),
            [self::today()->format('Y-m-d'), $like, $like, $like, self::today()->format('Y-m-d')],
        );
    }

    public static function status(array $film): string
    {
        $today = self::today();
        if ($film['opens_on'] > $today) {
            return 'soon';
        }
        return $film['ends_on'] < $today ? 'ended' : 'now';
    }

    public static function isBookable(array $film): bool
    {
        return match (self::status($film)) {
            'now' => true,
            'soon' => $film['opens_on'] <= self::today()->modify('+' . Settings::int('advance_days') . ' days'),
            default => false,
        };
    }

    public static function statusLine(array $film): string
    {
        return match (self::status($film)) {
            'now' => 'Now showing',
            'ended' => 'No longer showing',
            default => (self::isBookable($film) ? 'Booking open, opens ' : 'Opens ') . self::openingDate($film),
        };
    }

    /** "Fri 18 Dec", with the year added when it isn't this year. */
    public static function openingDate(array $film): string
    {
        $opens = $film['opens_on'];
        return d_short($opens) . ($opens->format('Y') !== self::today()->format('Y') ? ' ' . $opens->format('Y') : '');
    }

    private static function many(string $where, array $params): array
    {
        return array_map(self::hydrate(...), db_run('SELECT ' . self::COLUMNS . ' FROM films ' . $where, $params)->fetchAll());
    }

    private static function hydrate(array $row): array
    {
        foreach (['genres', 'languages', 'formats', 'cast'] as $key) {
            $row[$key] = json_decode((string) $row[$key], true) ?: [];
        }
        foreach (['id', 'vote_count', 'sort_order'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['tmdb_id'] = $row['tmdb_id'] !== null ? (int) $row['tmdb_id'] : null;
        $row['runtime_min'] = $row['runtime_min'] !== null ? (int) $row['runtime_min'] : null;
        $row['vote_average'] = $row['vote_average'] !== null ? (float) $row['vote_average'] : null;
        $row['featured'] = (bool) $row['featured'];
        $row['opens_on'] = new DateTimeImmutable($row['opens_on']);
        $row['ends_on'] = new DateTimeImmutable($row['ends_on']);
        return $row;
    }
}
