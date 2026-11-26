<?php
declare(strict_types=1);

namespace App;

final class Bookings
{
    /** Every booking made by bin/seed-demo-bookings.php uses this number, so they're easy to find. */
    public const DEMO_PHONE = '256700000000';

    /** No 0, O, 1 or I, so a reference read out over the phone can't be misheard. */
    private const REF_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** A new booking reference, eight characters, not used before. Stored without the dash. */
    public static function newRef(): string
    {
        do {
            $ref = '';
            for ($i = 0; $i < 8; $i++) {
                $ref .= self::REF_LETTERS[random_int(0, strlen(self::REF_LETTERS) - 1)];
            }
        } while (db_run('SELECT 1 FROM bookings WHERE ref = ?', [$ref])->fetchColumn());
        return $ref;
    }

    /** "K7QW2-MXP", as printed on tickets. */
    public static function formatRef(string $ref): string
    {
        return substr($ref, 0, 5) . '-' . substr($ref, 5);
    }

    /** Takes "k7qw2-mxp", "K7QW2MXP" or "k7qw2 mxp" and gives the stored form, or null. */
    public static function parseRef(string $input): ?string
    {
        $ref = strtoupper((string) preg_replace('/[\s-]+/', '', $input));
        return preg_match('/^[' . self::REF_LETTERS . ']{8}$/', $ref) ? $ref : null;
    }
}
