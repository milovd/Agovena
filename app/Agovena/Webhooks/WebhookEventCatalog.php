<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

final class WebhookEventCatalog
{
    /** @return list<string> */
    public function all(): array
    {
        return [
            'order.created',
            'order.paid',
            'order.cancelled',
            'payment.recorded',
            'refund.recorded',
            'credit_note.issued',
        ];
    }
}
