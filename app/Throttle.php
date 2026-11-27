<?php
declare(strict_types=1);

namespace App;

/**
 * Counts tries per visitor session, for forms worth guessing at: log in, Find my booking and
 * forgotten passwords. Clearing cookies starts the count again, so this slows guessing down rather
 * than stopping a determined attacker; a real site would also count by IP address.
 */
final class Throttle
{
    public static function tooMany(string $name, int $limit, int $seconds): bool
    {
        return count(self::recent($name, $seconds)) >= $limit;
    }

    public static function hit(string $name, int $seconds): void
    {
        $tries = self::recent($name, $seconds);
        $tries[] = time();
        $_SESSION['throttle'][$name] = $tries;
    }

    public static function clear(string $name): void
    {
        unset($_SESSION['throttle'][$name]);
    }

    /** @return list<int> */
    private static function recent(string $name, int $seconds): array
    {
        $since = time() - $seconds;
        return array_values(array_filter(
            (array) ($_SESSION['throttle'][$name] ?? []),
            fn (mixed $t): bool => is_int($t) && $t > $since,
        ));
    }
}
