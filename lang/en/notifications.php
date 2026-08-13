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
    'credit_note_issued' => [
        'subject' => 'Credit note :number issued',
        'line' => 'Credit note :number is now available.',
        'action' => 'View credit note',
    ],
    'refund_processed' => [
        'subject' => 'Refund processed for order :number',
        'line' => 'A refund of :total was processed for order :number.',
        'action' => 'View order',
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
    'shipment_sent' => [
        'subject' => 'Order :number has shipped',
        'line' => 'A shipment for order :number is on its way.',
        'action' => 'View order',
    ],
    'subscription_renewal' => [
        'subject' => 'Renewal invoice for :number',
        'line' => 'A renewal order :detail is ready for subscription :number.',
        'action' => 'View order',
    ],
    'subscription_renewal_paid' => [
        'subject' => 'Renewal paid for :number',
        'line' => 'Automatic renewal for subscription :number was paid. Order :detail.',
        'action' => 'View order',
    ],
    'subscription_renewal_failed' => [
        'subject' => 'Renewal payment failed for :number',
        'line' => 'Automatic renewal for subscription :number could not be charged. Order :detail is waiting for payment.',
        'action' => 'Pay now',
    ],
    'subscription_past_due' => [
        'subject' => 'Subscription :number is past due',
        'line' => 'Payment for subscription :number is overdue.',
        'action' => 'View subscriptions',
    ],
    'plan_change_applied' => [
        'subject' => 'Plan change applied',
        'line' => 'Your plan change for :number is now active.',
        'action' => 'View subscriptions',
    ],
    'service_activated' => [
        'subject' => 'Service :number is active',
        'line' => 'Service :number has been activated.',
        'action' => 'View service',
    ],
    'service_suspended' => [
        'subject' => 'Service :number is paused',
        'line' => 'Service :number has been suspended.',
        'action' => 'View service',
    ],
    'digital_entitlement_granted' => [
        'subject' => 'Your download is ready',
        'line' => 'Downloads for order :number are now available.',
        'action' => 'View downloads',
    ],
    'event_ticket_issued' => [
        'subject' => 'Your tickets for order :number',
        'line' => 'Event tickets for order :number are now available.',
        'action' => 'View tickets',
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
