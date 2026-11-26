<?php
declare(strict_types=1);

namespace App\Payments;

use DateTimeImmutable;

/**
 * Pretend payments. No money moves and nothing is sent to a phone.
 *   MTN MoMo and Airtel Money: approved by themselves a few seconds after they start,
 *   as if the customer had entered their PIN on their phone.
 *   Card: waits for the Approve or Decline button on /pay/demo-card.php, which stands in
 *   for the card provider's own page.
 */
final class DemoGateway implements PaymentGateway
{
    private const APPROVED = ':approved';
    private const DECLINED = ':declined';

    public function __construct(private readonly int $approveAfterSeconds)
    {
    }

    public function start(array $booking): PaymentStart
    {
        return new PaymentStart(
            'demo_' . bin2hex(random_bytes(8)),
            $booking['pay_method'] === 'card' ? '/pay/demo-card.php?ref=' . $booking['ref'] : null,
        );
    }

    public function status(array $booking): string
    {
        if ($booking['pay_method'] === 'card') {
            $reference = (string) $booking['gateway_ref'];
            return match (true) {
                str_ends_with($reference, self::APPROVED) => self::PAID,
                str_ends_with($reference, self::DECLINED) => self::FAILED,
                default => self::PENDING,
            };
        }
        $waited = time() - (new DateTimeImmutable($booking['created_at']))->getTimestamp();
        return $waited >= $this->approveAfterSeconds ? self::PAID : self::PENDING;
    }

    public function cancel(array $booking): void
    {
        // Nothing to tell anyone in the demo.
    }

    /** The demo card page's buttons. A real card provider would report this itself. */
    public function answerCard(array $booking, bool $approved): void
    {
        $reference = preg_replace('/:(approved|declined)$/', '', (string) $booking['gateway_ref']);
        db_run(
            "UPDATE bookings SET gateway_ref = ? WHERE id = ? AND status = 'pending'",
            [$reference . ($approved ? self::APPROVED : self::DECLINED), $booking['id']],
        );
    }
}
