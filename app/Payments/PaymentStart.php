<?php
declare(strict_types=1);

namespace App\Payments;

/** What a provider says when a payment starts. */
final class PaymentStart
{
    public function __construct(
        /** The provider's id for this payment, kept on the booking. */
        public readonly string $reference,
        /** A page to send the customer to, like a card provider's payment page. Null for mobile money. */
        public readonly ?string $redirect = null,
    ) {
    }
}
