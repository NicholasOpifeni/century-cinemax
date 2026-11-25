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
}
