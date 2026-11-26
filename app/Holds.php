<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDOException;

/**
 * Seats held while a customer checks out. Each visitor has a hold key in their session, and holds
 * one set of seats at a time. The unique key on (showtime_id, seat_id) means two people can never
 * hold the same seat, and a seat that's already booked can't be held.
 */
final class Holds
{
    private const DUPLICATE = 1062;
    private const DEADLOCK = 1213;

    /** This visitor's hold key, made the first time they press Checkout. */
    public static function key(): string
    {
        if (!is_string($_SESSION['hold_key'] ?? null) || !preg_match('/^[0-9a-f]{32}$/', $_SESSION['hold_key'])) {
            $_SESSION['hold_key'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['hold_key'];
    }

    /** The visitor's hold key if they have one, without making one. */
    public static function existingKey(): ?string
    {
        $key = $_SESSION['hold_key'] ?? null;
        return is_string($key) && preg_match('/^[0-9a-f]{32}$/', $key) ? $key : null;
    }

    public static function clearExpired(): void
    {
        db_run('DELETE FROM seat_holds WHERE expires_at <= NOW()');
        // A payment nobody finished: once its seats have gone, the booking can't be paid any more.
        db_run(
            "UPDATE bookings b SET b.status = 'expired'
             WHERE b.status = 'pending' AND b.created_at < NOW() - INTERVAL 15 MINUTE
               AND NOT EXISTS (SELECT 1 FROM seat_holds h WHERE h.booking_id = b.id)"
        );
    }

    /**
     * Holds seats on a show for this visitor, replacing anything they held before.
     * Either every seat is held or none is.
     *
     * @param list<int> $seatIds seats that belong to the show's hall
     * @return array{ok: true, expires_at: DateTimeImmutable}|array{ok: false, taken: list<int>}
     */
    public static function place(int $showtimeId, array $seatIds, string $holdKey): array
    {
        sort($seatIds); // the same order for everyone, which keeps lock waits short and deadlocks rare
        $minutes = max(1, Settings::int('hold_minutes'));

        for ($attempt = 1; ; $attempt++) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                self::clearExpired();
                db_run('DELETE FROM seat_holds WHERE hold_key = ? AND booking_id IS NULL', [$holdKey]);

                $taken = [];
                $insert = $pdo->prepare(
                    'INSERT INTO seat_holds (showtime_id, seat_id, hold_key, expires_at)
                     VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)'
                );
                foreach ($seatIds as $seatId) {
                    try {
                        $insert->execute([$showtimeId, $seatId, $holdKey, $minutes]);
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? null) !== self::DUPLICATE) {
                            throw $e;
                        }
                        $taken[] = $seatId; // someone else is holding it
                    }
                }

                // A locking read sees bookings committed a moment ago, not just this transaction's snapshot.
                $in = implode(',', array_fill(0, count($seatIds), '?'));
                $booked = db_run(
                    "SELECT seat_id FROM booking_seats WHERE showtime_id = ? AND seat_id IN ($in) FOR SHARE",
                    [$showtimeId, ...$seatIds],
                )->fetchAll(\PDO::FETCH_COLUMN);
                $taken = array_values(array_unique([...$taken, ...array_map('intval', $booked)]));

                if ($taken) {
                    $pdo->rollBack();
                    sort($taken);
                    return ['ok' => false, 'taken' => $taken];
                }

                $expires = db_run('SELECT MIN(expires_at) FROM seat_holds WHERE hold_key = ?', [$holdKey])->fetchColumn();
                $pdo->commit();
                return ['ok' => true, 'expires_at' => new DateTimeImmutable((string) $expires)];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Two people grabbing overlapping seats at the same moment can deadlock. Try once more.
                if (($e->errorInfo[1] ?? null) === self::DEADLOCK && $attempt < 3) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /** Lets go of this visitor's seats, unless they're already paying for them. */
    public static function release(string $holdKey): void
    {
        db_run('DELETE FROM seat_holds WHERE hold_key = ? AND booking_id IS NULL', [$holdKey]);
    }

    /**
     * What this visitor is holding right now, if anything.
     *
     * @return array{showtime_id: int, seat_ids: list<int>, expires_at: DateTimeImmutable}|null
     */
    public static function current(string $holdKey): ?array
    {
        $rows = db_run(
            'SELECT showtime_id, seat_id, expires_at FROM seat_holds WHERE hold_key = ? AND expires_at > NOW() ORDER BY seat_id',
            [$holdKey],
        )->fetchAll();
        if (!$rows) {
            return null;
        }
        return [
            'showtime_id' => (int) $rows[0]['showtime_id'],
            'seat_ids' => array_map(fn (array $r): int => (int) $r['seat_id'], $rows),
            'expires_at' => new DateTimeImmutable(min(array_column($rows, 'expires_at'))),
        ];
    }
}
