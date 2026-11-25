<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class Showtimes
{
    public const CLEAN_MINUTES = 20;
    // Used to plan the hall when a film's runtime isn't known yet.
    public const PLANNING_RUNTIME = 120;

    private const SLOTS = ['10:30', '13:15', '16:00', '18:45', '21:30'];
    private const LONG_SLOTS = ['11:00', '14:45', '18:30'];
    private const LONG_FILM = 170;

    /**
     * Shows still to start at a cinema on a day, grouped by film id.
     *
     * @return array<int, list<array{id: int, starts_at: DateTimeImmutable, format: string, language: string, hall: int}>>
     */
    public static function forCinemaOn(int $cinemaId, DateTimeImmutable $day): array
    {
        $rows = db_run(
            'SELECT s.id, s.film_id, s.starts_at, s.format, s.language, h.number AS hall
             FROM showtimes s JOIN halls h ON h.id = s.hall_id
             WHERE h.cinema_id = ? AND s.starts_at >= ? AND s.starts_at < ? AND s.starts_at > NOW()
             ORDER BY s.starts_at, h.number',
            [$cinemaId, $day->format('Y-m-d 00:00:00'), $day->modify('+1 day')->format('Y-m-d 00:00:00')],
        )->fetchAll();

        $byFilm = [];
        foreach ($rows as $row) {
            $byFilm[(int) $row['film_id']][] = [
                'id' => (int) $row['id'],
                'starts_at' => new DateTimeImmutable($row['starts_at']),
                'format' => $row['format'],
                'language' => $row['language'],
                'hall' => (int) $row['hall'],
            ];
        }
        return $byFilm;
    }

    /**
     * Plans one cinema's day and saves it. Films take turns choosing a slot, so every film gets
     * a show before any film gets a second one, and a hall never runs two shows within
     * the film's runtime plus cleaning. Dolby Atmos only goes in Atmos halls.
     *
     * A day that already has shows is left alone unless $replace is set. Replacing keeps
     * any show with bookings or holds and plans around it.
     */
    public static function generateDay(int $cinemaId, DateTimeImmutable $day, bool $replace = false): int
    {
        $date = $day->format('Y-m-d');
        $from = "$date 00:00:00";
        $to = $day->modify('+1 day')->format('Y-m-d 00:00:00');

        $halls = db_run('SELECT id, is_atmos FROM halls WHERE cinema_id = ? ORDER BY number', [$cinemaId])->fetchAll();
        if (!$halls) {
            return 0;
        }

        if ($replace) {
            db_run(
                'DELETE FROM showtimes
                 WHERE hall_id IN (SELECT id FROM halls WHERE cinema_id = ?) AND starts_at >= ? AND starts_at < ?
                   AND id NOT IN (SELECT showtime_id FROM bookings)
                   AND id NOT IN (SELECT showtime_id FROM seat_holds)',
                [$cinemaId, $from, $to],
            );
        }

        $existing = db_run(
            'SELECT s.hall_id, s.starts_at, s.ends_at FROM showtimes s JOIN halls h ON h.id = s.hall_id
             WHERE h.cinema_id = ? AND s.starts_at >= ? AND s.starts_at < ?',
            [$cinemaId, $from, $to],
        )->fetchAll();
        if ($existing && !$replace) {
            return 0;
        }

        $busy = array_fill_keys(array_column($halls, 'id'), []);
        foreach ($existing as $show) {
            $busy[$show['hall_id']][] = [strtotime($show['starts_at']), strtotime($show['ends_at']) + self::CLEAN_MINUTES * 60];
        }

        $films = db_run(
            'SELECT id, runtime_min, languages, formats FROM films WHERE opens_on <= ? AND ends_on >= ? ORDER BY sort_order',
            [$date, $date],
        )->fetchAll();

        $rand = new Randomizer(new Mt19937(crc32("$cinemaId|$date")));
        $hasAtmos = in_array(1, array_map('intval', array_column($halls, 'is_atmos')), true);

        $queues = [];
        foreach ($films as $film) {
            $runtime = (int) ($film['runtime_min'] ?: self::PLANNING_RUNTIME);
            $lists = [];
            foreach (json_decode($film['formats'], true) as $format) {
                if ($format === 'Dolby Atmos' && !$hasAtmos) {
                    continue;
                }
                foreach (json_decode($film['languages'], true) as $language) {
                    $times = $rand->shuffleArray(self::pickTimes($rand, $runtime, $format));
                    $lists[] = array_map(
                        fn (string $time): array => ['film' => (int) $film['id'], 'runtime' => $runtime, 'format' => $format, 'language' => $language, 'time' => $time],
                        $times,
                    );
                }
            }
            $queues[] = self::interleave($lists);
        }
        $queues = $rand->shuffleArray($queues);

        // Each turn, a film keeps trying its next slot until one fits somewhere.
        $plan = [];
        while (array_filter($queues)) {
            foreach ($queues as &$queue) {
                while (($show = array_shift($queue)) !== null) {
                    $start = strtotime("$date {$show['time']}:00");
                    $end = $start + $show['runtime'] * 60;
                    $hallId = self::freeHall($rand, $halls, $busy, $show['format'], $start, $end + self::CLEAN_MINUTES * 60);
                    if ($hallId !== null) {
                        $busy[$hallId][] = [$start, $end + self::CLEAN_MINUTES * 60];
                        $plan[] = $show + ['hall' => $hallId, 'start' => $start, 'end' => $end];
                        break;
                    }
                }
            }
            unset($queue);
        }

        $insert = db()->prepare(
            'INSERT INTO showtimes (film_id, hall_id, starts_at, ends_at, format, language) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($plan as $show) {
            $insert->execute([$show['film'], $show['hall'], date('Y-m-d H:i:s', $show['start']), date('Y-m-d H:i:s', $show['end']), $show['format'], $show['language']]);
        }
        return count($plan);
    }

    /** The mockup's slots. 3D and Atmos get the later ones only, and a day keeps at least two. */
    private static function pickTimes(Randomizer $rand, int $runtime, string $format): array
    {
        $slots = $runtime > self::LONG_FILM ? self::LONG_SLOTS : self::SLOTS;
        if ($format !== '2D') {
            $last = count($slots) - 1;
            $slots = array_values(array_filter($slots, fn (int $i): bool => $i % 2 === 1 || $i === $last, ARRAY_FILTER_USE_KEY));
        }
        $picked = array_values(array_filter($slots, fn (): bool => $rand->getInt(0, 99) >= 25));
        return count($picked) >= 2 ? $picked : array_slice($slots, -2);
    }

    /** [[a1, a2], [b1]] becomes [a1, b1, a2], so a film's 2D, 3D and language versions alternate. */
    private static function interleave(array $lists): array
    {
        $out = [];
        for ($i = 0; $lists; $i++) {
            foreach ($lists as $k => $list) {
                if (!isset($list[$i])) {
                    unset($lists[$k]);
                    continue;
                }
                $out[] = $list[$i];
            }
        }
        return $out;
    }

    /** Ordinary shows try the ordinary halls first, so the Atmos hall stays free for Atmos. */
    private static function freeHall(Randomizer $rand, array $halls, array $busy, string $format, int $start, int $until): ?int
    {
        $atmos = array_values(array_filter($halls, fn (array $h): bool => (int) $h['is_atmos'] === 1));
        $plain = array_values(array_filter($halls, fn (array $h): bool => (int) $h['is_atmos'] === 0));
        $order = $format === 'Dolby Atmos' ? $atmos : [...$rand->shuffleArray($plain), ...$atmos];

        foreach ($order as $hall) {
            $free = true;
            foreach ($busy[$hall['id']] as [$from, $to]) {
                if ($start < $to && $from < $until) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                return (int) $hall['id'];
            }
        }
        return null;
    }
}
