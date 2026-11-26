<?php
declare(strict_types=1);

namespace App\Payments;

use App\Bookings;

/**
 * The steps every payment goes through, whichever provider takes it: start it, check on it
 * until it's settled, or cancel it. The status API, the waiting page and the card return all
 * call check(), so a booking is confirmed the same way however the customer gets there.
 */
final class Payments
{
    private const SESSION = 'payments';

    public static function gateway(): PaymentGateway
    {
        return new DemoGateway(max(1, (int) config('demo_payment_seconds', 5)));
    }

    /** Starts paying for a pending booking and remembers it's this visitor's. */
    public static function start(array $booking): PaymentStart
    {
        $start = self::gateway()->start($booking);
        db_run('UPDATE bookings SET gateway_ref = ? WHERE id = ?', [$start->reference, $booking['id']]);
        $refs = $_SESSION[self::SESSION] ?? [];
        $refs[] = $booking['ref'];
        $_SESSION[self::SESSION] = array_slice(array_values(array_unique($refs)), -10);
        return $start;
    }

    /** Whether this visitor started the payment for a booking. Payment pages answer to nobody else. */
    public static function isMine(string $ref): bool
    {
        return in_array($ref, $_SESSION[self::SESSION] ?? [], true);
    }

    /**
     * Brings a booking up to date and says what the customer should see next.
     *
     * @return array{status: string, message: string, next: ?string}
     *   status is paid, pending, failed, cancelled or expired; next is where to send them, if anywhere
     */
    public static function check(array $booking): array
    {
        $ref = $booking['ref'];
        $seats = '/seats.php?show=' . $booking['showtime_id'];

        if ($booking['status'] === 'pending') {
            $live = (int) db_run('SELECT COUNT(*) FROM seat_holds WHERE booking_id = ? AND expires_at > NOW()', [$booking['id']])->fetchColumn();
            $answer = self::gateway()->status($booking);
            if ($answer === PaymentGateway::PAID) {
                $booking['status'] = Bookings::confirm((int) $booking['id']);
                if ($booking['status'] === 'failed') {
                    return [
                        'status' => 'failed',
                        'message' => 'Someone else booked one of your seats before the payment came through, so we stopped it. Nothing was taken. Pick your seats again.',
                        'next' => $seats,
                    ];
                }
            } elseif ($answer === PaymentGateway::FAILED) {
                Bookings::close((int) $booking['id'], 'failed', true);
                return ['status' => 'failed', 'message' => 'The payment didn’t go through. Your seats are still held, so you can try again.', 'next' => '/checkout.php'];
            } elseif ($live === 0) {
                Bookings::close((int) $booking['id'], 'expired', false);
                return ['status' => 'expired', 'message' => 'Time ran out before the payment came through, so we let your seats go. Pick them again.', 'next' => $seats];
            } else {
                return ['status' => 'pending', 'message' => '', 'next' => null];
            }
        }

        if ($booking['status'] === 'paid') {
            unset($_SESSION['checkout_show']); // nothing left to time out
        }
        return match ($booking['status']) {
            'paid' => ['status' => 'paid', 'message' => '', 'next' => Bookings::ticketPath($ref)],
            'cancelled' => ['status' => 'cancelled', 'message' => 'That payment was cancelled.', 'next' => '/checkout.php'],
            'expired' => ['status' => 'expired', 'message' => 'Time ran out before the payment came through, so we let your seats go. Pick them again.', 'next' => $seats],
            default => ['status' => 'failed', 'message' => 'The payment didn’t go through.', 'next' => '/checkout.php'],
        };
    }

    /** The customer pressed Cancel payment. Their seats stay held for the rest of their time. */
    public static function cancel(array $booking): void
    {
        if ($booking['status'] === 'pending') {
            self::gateway()->cancel($booking);
            Bookings::close((int) $booking['id'], 'cancelled', true);
        }
    }
}
