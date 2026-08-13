<?php

return [
    'greeting' => 'Hello :name,',
    'total' => 'Total: :total',
    'order_placed' => [
        'subject' => 'Order :number received',
        'line' => 'We received your order :number.',
        'action' => 'View order',
    ],
    'payment_recorded' => [
        'subject' => 'Payment recorded for order :number',
        'line' => 'Your payment for order :number was recorded.',
        'action' => 'View order',
    ],
    'invoice_issued' => [
        'subject' => 'Invoice :number issued',
        'line' => 'Invoice :number is now available.',
        'action' => 'View invoice',
    ],
    'ticket_replied' => [
        'subject' => 'Reply to ticket :number',
        'line' => 'A staff member replied to “:subject”.',
        'action' => 'View reply',
    ],
    'subscription_cancelled' => [
        'subject' => 'Subscription :number cancellation',
        'at_period_end' => 'Subscription :number will end after the current period.',
        'immediate' => 'Subscription :number has been cancelled.',
        'action' => 'View subscriptions',
    ],
    'provisioning' => [
        'manual' => 'Manual',
        'refresh_status' => 'Refresh status',
        'details' => 'Service details',
        'status' => 'Status',
        'reference' => 'Reference',
        'invalid_action' => 'This service action is invalid.',
        'action_unavailable' => 'This service action is unavailable.',
    ],
    'plan_changes' => [
        'not_allowed' => 'This plan change is not allowed.',
        'currency_mismatch' => 'Plans with different currencies cannot be changed.',
        'order_line' => 'Plan change to :product',
        'already_pending' => 'A plan change is already pending for this subscription.',
        'cannot_apply' => 'This plan change cannot be applied.',
        'cannot_cancel' => 'This plan change cannot be cancelled.',
    ],
];
