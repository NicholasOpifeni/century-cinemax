<?php
declare(strict_types=1);

namespace App;

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
}
