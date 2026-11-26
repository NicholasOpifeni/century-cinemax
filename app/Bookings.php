<?php
declare(strict_types=1);

namespace App;

use PDOException;

/**
 * A booking starts as pending when the customer presses Pay, holding on to their held seats.
 * It becomes paid in confirm(), which writes booking_seats inside one transaction. The unique key
 * on (showtime_id, seat_id) there is what makes a double booking impossible.
 */
final class Bookings
{
    /** Every booking made by bin/seed-demo-bookings.php uses this number, so they're easy to find. */
    public const DEMO_PHONE = '256700000000';

    /** No 0, O, 1 or I, so a reference read out over the phone can't be misheard. */
    private const REF_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const DUPLICATE = 1062;

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

    /**
     * The signature on ticket links: an HMAC of the reference with app_key, so a ticket can't be
     * found by guessing references. 22 URL-safe characters.
     */
    public static function signature(string $ref): string
    {
        $mac = hash_hmac('sha256', 'ticket:' . $ref, (string) config('app_key'), true);
        return rtrim(strtr(base64_encode(substr($mac, 0, 16)), '+/', '-_'), '=');
    }

    public static function signatureMatches(string $ref, string $signature): bool
    {
        return hash_equals(self::signature($ref), $signature);
    }

    public static function ticketPath(string $ref): string
    {
        return '/ticket.php?ref=' . rawurlencode(self::formatRef($ref)) . '&sig=' . self::signature($ref);
    }

    public static function byRef(string $ref): ?array
    {
        $row = db_run('SELECT * FROM bookings WHERE ref = ?', [$ref])->fetch();
        return $row ?: null;
    }

    /**
     * Saves a pending booking for the seats this visitor is holding, with its snacks, and ties
     * the holds to it. Holds get at least two more minutes so a payment started near the end
     * of the eight can still finish. Any earlier pending booking on the same holds is cancelled.
     *
     * @param array $show from Showtimes::find()
     * @param array<int, int> $quantities snack id => how many
     * @return array|null the booking, or null if the seats are no longer held
     */
    public static function createPending(array $show, string $holdKey, string $phone, string $method, array $quantities, bool $ownGlasses, ?int $userId = null): ?array
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            Holds::clearExpired();
            $seatIds = array_map('intval', db_run(
                'SELECT seat_id FROM seat_holds WHERE hold_key = ? AND showtime_id = ? AND expires_at > NOW() FOR UPDATE',
                [$holdKey, $show['id']],
            )->fetchAll(\PDO::FETCH_COLUMN));
            if (!$seatIds) {
                $pdo->rollBack();
                return null;
            }

            db_run(
                "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW()
                 WHERE status = 'pending' AND id IN (SELECT booking_id FROM seat_holds WHERE hold_key = ? AND booking_id IS NOT NULL)",
                [$holdKey],
            );

            $seats = Checkout::seats($seatIds);
            $quote = Checkout::quote($show, $seats, $quantities, $ownGlasses);
            $ref = self::newRef();
            db_run(
                "INSERT INTO bookings (ref, showtime_id, user_id, phone, status, pay_method, seat_labels, seat_count, own_glasses,
                                       seats_total, glasses_total, snacks_total, total)
                 VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $ref, $show['id'], $userId, $phone, $method, implode(' ', array_column($seats, 'label')), count($seats),
                    $ownGlasses && $show['format'] === '3D' ? 1 : 0, $quote['seats_total'], $quote['glasses_total'], $quote['snacks_total'], $quote['total'],
                ],
            );
            $bookingId = (int) $pdo->lastInsertId();
            foreach ($quote['snacks'] as $snack) {
                db_run(
                    'INSERT INTO booking_snacks (booking_id, snack_id, quantity, unit_price) VALUES (?, ?, ?, ?)',
                    [$bookingId, $snack['snack_id'], $snack['quantity'], $snack['unit_price']],
                );
            }
            db_run(
                'UPDATE seat_holds SET booking_id = ?, expires_at = GREATEST(expires_at, NOW() + INTERVAL 2 MINUTE) WHERE hold_key = ? AND showtime_id = ?',
                [$bookingId, $holdKey, $show['id']],
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return self::byRef($ref);
    }

    /**
     * Turns a pending booking into a paid one: its seats go into booking_seats and its holds go.
     * Returns the booking's status afterwards: paid, or failed if a seat was booked by someone
     * else first (only possible if the hold had run out). Calling it twice is harmless.
     */
    public static function confirm(int $bookingId): string
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $booking = db_run('SELECT b.*, s.hall_id, s.starts_at, s.format FROM bookings b JOIN showtimes s ON s.id = b.showtime_id WHERE b.id = ? FOR UPDATE', [$bookingId])->fetch();
            if (!$booking || $booking['status'] !== 'pending') {
                $pdo->commit();
                return $booking ? $booking['status'] : 'missing';
            }

            $labels = explode(' ', $booking['seat_labels']);
            $in = implode(',', array_fill(0, count($labels), '?'));
            $seats = db_run("SELECT id, type FROM seats WHERE hall_id = ? AND label IN ($in)", [$booking['hall_id'], ...$labels])->fetchAll();
            $day = new \DateTimeImmutable($booking['starts_at']);

            $insert = $pdo->prepare('INSERT INTO booking_seats (booking_id, showtime_id, seat_id, price) VALUES (?, ?, ?, ?)');
            foreach ($seats as $seat) {
                $insert->execute([$bookingId, $booking['showtime_id'], $seat['id'], Pricing::seatPrice($day, $booking['format'], $seat['type'])]);
            }
            db_run('DELETE FROM seat_holds WHERE booking_id = ?', [$bookingId]);
            db_run("UPDATE bookings SET status = 'paid', paid_at = NOW() WHERE id = ?", [$bookingId]);
            $pdo->commit();
            return 'paid';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? null) === self::DUPLICATE) {
                // Someone else booked one of these seats while the hold had lapsed. Nothing was written.
                self::close($bookingId, 'failed', false);
                return 'failed';
            }
            throw $e;
        }
    }

    /**
     * Ends a pending booking without payment. With $keepSeats the customer's holds stay, so they
     * can try paying again; otherwise the seats are let go.
     */
    public static function close(int $bookingId, string $status, bool $keepSeats): void
    {
        db_run(
            "UPDATE bookings SET status = ?, cancelled_at = IF(? = 'cancelled', NOW(), cancelled_at) WHERE id = ? AND status = 'pending'",
            [$status, $status, $bookingId],
        );
        db_run(
            $keepSeats ? 'UPDATE seat_holds SET booking_id = NULL WHERE booking_id = ?' : 'DELETE FROM seat_holds WHERE booking_id = ?',
            [$bookingId],
        );
    }
}
