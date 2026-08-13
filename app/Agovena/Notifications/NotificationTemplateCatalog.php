<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

final class NotificationTemplateCatalog
{
    /**
     * @return list<NotificationTemplateDefinition>
     */
    public function all(): array
    {
        return [
            new NotificationTemplateDefinition(
                key: 'order_placed',
                label: 'admin.notifications.keys.order_placed',
                placeholders: ['name', 'number', 'total', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'payment_recorded',
                label: 'admin.notifications.keys.payment_recorded',
                placeholders: ['name', 'number', 'total', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'invoice_issued',
                label: 'admin.notifications.keys.invoice_issued',
                placeholders: ['name', 'number', 'total', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'credit_note_issued',
                label: 'admin.notifications.keys.credit_note_issued',
                placeholders: ['name', 'number', 'total', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'refund_processed',
                label: 'admin.notifications.keys.refund_processed',
                placeholders: ['name', 'number', 'total', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'ticket_replied',
                label: 'admin.notifications.keys.ticket_replied',
                placeholders: ['name', 'number', 'subject', 'action_url', 'action_label'],
            ),
            new NotificationTemplateDefinition(
                key: 'subscription_cancelled',
                label: 'admin.notifications.keys.subscription_cancelled',
                placeholders: ['name', 'number', 'detail', 'action_url', 'action_label'],
            ),
        ];
    }

    public function find(string $key): ?NotificationTemplateDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        return null;
    }

    public function defaultSubject(string $key): string
    {
        return (string) __('notifications.'.$key.'.subject');
    }

    public function defaultBody(string $key): string
    {
        $greeting = (string) __('notifications.greeting');
        $lineKey = 'notifications.'.$key.'.line';
        $line = __($lineKey);
        if ($line === $lineKey) {
            $line = (string) __('notifications.'.$key.'.detail');
        }
        $total = $key === 'ticket_replied' || $key === 'subscription_cancelled'
            ? ''
            : (string) __('notifications.total');

        $parts = array_values(array_filter([$greeting, $line, $total], static fn (string $part): bool => $part !== ''));

        return implode("\n\n", $parts);
    }
}
