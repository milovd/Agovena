<?php

declare(strict_types=1);

namespace App\Agovena\Api;

final class ApiTokenAbilities
{
    public const ALL = '*';

    public const ACCOUNT_READ = 'account.read';

    public const ACCOUNT_UPDATE = 'account.update';

    public const ADDRESSES_READ = 'addresses.read';

    public const ADDRESSES_CREATE = 'addresses.create';

    public const ADDRESSES_UPDATE = 'addresses.update';

    public const ADDRESSES_DELETE = 'addresses.delete';

    public const ORDERS_READ = 'orders.read';

    public const ORDERS_CREATE = 'orders.create';

    public const ORDERS_PAY = 'orders.pay';

    public const INVOICES_READ = 'invoices.read';

    public const CREDIT_NOTES_READ = 'credit_notes.read';

    public const TICKETS_READ = 'tickets.read';

    public const SUBSCRIPTIONS_READ = 'subscriptions.read';

    public const SERVICES_READ = 'services.read';

    public const DOWNLOADS_READ = 'downloads.read';

    public const DIGITAL_SECRETS_READ = 'digital_secrets.read';

    public const EVENT_TICKETS_READ = 'event_tickets.read';

    public const TOKENS_REVOKE = 'tokens.revoke';

    /**
     * @return list<array{key: string, group: string, action: string, label: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::ACCOUNT_READ, 'group' => 'account', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.account_read', 'description' => 'admin.api_tokens.ability_descriptions.account_read'],
            ['key' => self::ACCOUNT_UPDATE, 'group' => 'account', 'action' => 'update', 'label' => 'admin.api_tokens.ability_labels.account_update', 'description' => 'admin.api_tokens.ability_descriptions.account_update'],
            ['key' => self::ADDRESSES_READ, 'group' => 'addresses', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.addresses_read', 'description' => 'admin.api_tokens.ability_descriptions.addresses_read'],
            ['key' => self::ADDRESSES_CREATE, 'group' => 'addresses', 'action' => 'create', 'label' => 'admin.api_tokens.ability_labels.addresses_create', 'description' => 'admin.api_tokens.ability_descriptions.addresses_create'],
            ['key' => self::ADDRESSES_UPDATE, 'group' => 'addresses', 'action' => 'update', 'label' => 'admin.api_tokens.ability_labels.addresses_update', 'description' => 'admin.api_tokens.ability_descriptions.addresses_update'],
            ['key' => self::ADDRESSES_DELETE, 'group' => 'addresses', 'action' => 'delete', 'label' => 'admin.api_tokens.ability_labels.addresses_delete', 'description' => 'admin.api_tokens.ability_descriptions.addresses_delete'],
            ['key' => self::ORDERS_READ, 'group' => 'orders', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.orders_read', 'description' => 'admin.api_tokens.ability_descriptions.orders_read'],
            ['key' => self::ORDERS_CREATE, 'group' => 'orders', 'action' => 'create', 'label' => 'admin.api_tokens.ability_labels.orders_create', 'description' => 'admin.api_tokens.ability_descriptions.orders_create'],
            ['key' => self::ORDERS_PAY, 'group' => 'orders', 'action' => 'pay', 'label' => 'admin.api_tokens.ability_labels.orders_pay', 'description' => 'admin.api_tokens.ability_descriptions.orders_pay'],
            ['key' => self::INVOICES_READ, 'group' => 'invoices', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.invoices_read', 'description' => 'admin.api_tokens.ability_descriptions.invoices_read'],
            ['key' => self::CREDIT_NOTES_READ, 'group' => 'credit_notes', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.credit_notes_read', 'description' => 'admin.api_tokens.ability_descriptions.credit_notes_read'],
            ['key' => self::TICKETS_READ, 'group' => 'tickets', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.tickets_read', 'description' => 'admin.api_tokens.ability_descriptions.tickets_read'],
            ['key' => self::SUBSCRIPTIONS_READ, 'group' => 'subscriptions', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.subscriptions_read', 'description' => 'admin.api_tokens.ability_descriptions.subscriptions_read'],
            ['key' => self::SERVICES_READ, 'group' => 'services', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.services_read', 'description' => 'admin.api_tokens.ability_descriptions.services_read'],
            ['key' => self::DOWNLOADS_READ, 'group' => 'downloads', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.downloads_read', 'description' => 'admin.api_tokens.ability_descriptions.downloads_read'],
            ['key' => self::DIGITAL_SECRETS_READ, 'group' => 'digital_secrets', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.digital_secrets_read', 'description' => 'admin.api_tokens.ability_descriptions.digital_secrets_read'],
            ['key' => self::EVENT_TICKETS_READ, 'group' => 'event_tickets', 'action' => 'read', 'label' => 'admin.api_tokens.ability_labels.event_tickets_read', 'description' => 'admin.api_tokens.ability_descriptions.event_tickets_read'],
            ['key' => self::TOKENS_REVOKE, 'group' => 'tokens', 'action' => 'revoke', 'label' => 'admin.api_tokens.ability_labels.tokens_revoke', 'description' => 'admin.api_tokens.ability_descriptions.tokens_revoke'],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge(
            [self::ALL],
            array_column(self::definitions(), 'key'),
        );
    }

    /** @return list<string> */
    public static function normalize(array $abilities): array
    {
        $abilities = array_values(array_filter($abilities, static fn (mixed $ability): bool => is_string($ability)));
        if (in_array(self::ALL, $abilities, true)) {
            return [self::ALL];
        }

        return array_values(array_intersect(array_column(self::definitions(), 'key'), array_unique($abilities)));
    }

    /** @return array<string, list<array{key: string, group: string, action: string, label: string, description: string}>> */
    public static function groupedDefinitions(): array
    {
        $groups = [];
        foreach (self::definitions() as $definition) {
            $groups[$definition['group']][] = $definition;
        }

        return $groups;
    }

    public static function forRoute(string $routeName): ?string
    {
        foreach ([
            'api.v1.subscriptions.' => self::SUBSCRIPTIONS_READ,
            'api.v1.services.' => self::SERVICES_READ,
            'api.v1.downloads.' => self::DOWNLOADS_READ,
            'api.v1.digital-secrets.' => self::DIGITAL_SECRETS_READ,
            'api.v1.event-tickets.' => self::EVENT_TICKETS_READ,
        ] as $routePrefix => $ability) {
            if (str_starts_with($routeName, $routePrefix)) {
                return $ability;
            }
        }

        return null;
    }
}
