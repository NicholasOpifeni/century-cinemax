<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Values from the settings table, which the admin can change. */
final class Settings
{
    private const DEFAULTS = [
        'recliner_extra' => 10000,
        'atmos_extra' => 2000,
        'glasses_price' => 8000,
        'hold_minutes' => 8,
        'max_seats' => 8,
        'advance_days' => 14,
    ];

    public static function int(string $name): int
    {
        static $values = null;
        $values ??= db()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        return (int) ($values[$name] ?? self::DEFAULTS[$name] ?? 0);
    }
}
