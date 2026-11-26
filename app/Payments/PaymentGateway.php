<?php
declare(strict_types=1);

namespace App\Payments;

/**
 * What the booking pages need from a payment provider. The site only ships DemoGateway, but a real
 * provider (Flutterwave, the MTN MoMo API, the Airtel Money API) can be added as another class
 * without changing the pages. The pages never see card numbers or PINs: the customer gives those
 * to the provider, on their phone or on the provider's own page.
 */
interface PaymentGateway
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';

    /** Asks the customer to pay for a pending booking. */
    public function start(array $booking): PaymentStart;

    /** Where the payment for a booking has got to: PENDING, PAID or FAILED. */
    public function status(array $booking): string;

    /** The customer gave up, so stop asking them to pay. */
    public function cancel(array $booking): void;
}
