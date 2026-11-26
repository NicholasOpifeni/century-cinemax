<?php
declare(strict_types=1);

namespace App;

final class SeatMap
{
    /**
     * Turns a hall layout into its seats. Seats are numbered left to right within each row,
     * so with a first block at columns 3 to 4, A1 sits in column 3.
     *
     * @return list<array{row: string, number: int, col: int, label: string, type: string}>
     */
    public static function seatsFromLayout(array $layout): array
    {
        $seats = [];
        foreach ($layout['rows'] as $row) {
            $number = 0;
            foreach ($row['blocks'] as [$from, $to]) {
                for ($col = $from; $col <= $to; $col++) {
                    $number++;
                    $seats[] = [
                        'row' => $row['row'],
                        'number' => $number,
                        'col' => $col,
                        'label' => $row['row'] . $number,
                        'type' => $row['type'] === 'recliner' ? 'recliner' : 'standard',
                    ];
                }
            }
        }
        return $seats;
    }

    /**
     * A show's hall, row by row, with each seat marked taken if it's booked or held by someone.
     * Seats held under $holdKey are this visitor's own, so they're marked mine instead.
     * Rows come in the layout's order, front to back, and seats are keyed by grid column.
     *
     * @return array{hall: int, columns: int, total: int, free: int,
     *   rows: list<array{row: string, type: string, seats: array<int, array{id: int, label: string, number: int, type: string, taken: bool, mine: bool}>}>}|null
     */
    public static function forShowtime(int $showtimeId, ?string $holdKey = null): ?array
    {
        $show = db_run(
            'SELECT s.hall_id, h.number, h.layout_json FROM showtimes s JOIN halls h ON h.id = s.hall_id WHERE s.id = ?',
            [$showtimeId],
        )->fetch();
        if (!$show) {
            return null;
        }
        $layout = json_decode($show['layout_json'], true);

        [$taken, $mine] = self::takenAndMine($showtimeId, $holdKey);

        $byRow = [];
        foreach (db_run('SELECT id, row_label, seat_number, grid_col, label, type FROM seats WHERE hall_id = ?', [$show['hall_id']]) as $seat) {
            $byRow[$seat['row_label']][(int) $seat['grid_col']] = [
                'id' => (int) $seat['id'],
                'label' => $seat['label'],
                'number' => (int) $seat['seat_number'],
                'type' => $seat['type'],
                'taken' => isset($taken[$seat['id']]),
                'mine' => isset($mine[$seat['id']]),
            ];
        }

        $rows = [];
        $total = 0;
        $free = 0;
        foreach ($layout['rows'] as $row) {
            $seats = $byRow[$row['row']] ?? [];
            ksort($seats);
            $rows[] = ['row' => $row['row'], 'type' => $row['type'], 'seats' => $seats];
            $total += count($seats);
            $free += count(array_filter($seats, fn (array $s): bool => !$s['taken']));
        }

        return ['hall' => (int) $show['number'], 'columns' => (int) $layout['columns'], 'total' => $total, 'free' => $free, 'rows' => $rows];
    }

    /**
     * Seat ids that are booked or held by others, and those held by this visitor, as sets.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public static function takenAndMine(int $showtimeId, ?string $holdKey = null): array
    {
        $rows = db_run(
            'SELECT seat_id, 0 AS mine FROM booking_seats WHERE showtime_id = ?
             UNION ALL
             SELECT seat_id, hold_key = ? AS mine FROM seat_holds WHERE showtime_id = ? AND expires_at > NOW()',
            [$showtimeId, $holdKey ?? '', $showtimeId],
        )->fetchAll();

        $taken = [];
        $mine = [];
        foreach ($rows as $row) {
            if ((int) $row['mine'] === 1) {
                $mine[(int) $row['seat_id']] = (int) $row['seat_id'];
            } else {
                $taken[(int) $row['seat_id']] = (int) $row['seat_id'];
            }
        }
        return [$taken, $mine];
    }
}
