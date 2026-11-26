<?php
declare(strict_types=1);

namespace App;

use DateTimeInterface;
use PDO;

final class Pricing
{
    /** Price of a standard seat on a day of the week, 0 for Sunday to 6 for Saturday. */
    public static function basePrice(int $dayOfWeek): int
    {
        static $prices = null;
        $prices ??= db()->query('SELECT day_of_week, base_price FROM prices')->fetchAll(PDO::FETCH_KEY_PAIR);
        return (int) ($prices[$dayOfWeek] ?? 0);
    }

    /** One seat at one show: the day's price, plus the Dolby Atmos and recliner extras. */
    public static function seatPrice(DateTimeInterface $day, string $format, string $type = 'standard'): int
    {
        return self::basePrice((int) $day->format('w'))
            + ($format === 'Dolby Atmos' ? Settings::int('atmos_extra') : 0)
            + ($type === 'recliner' ? Settings::int('recliner_extra') : 0);
    }
}
