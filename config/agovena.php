<?php

declare(strict_types=1);

return [
    /*
     * Platform version for Extension/Module compatibility constraints.
     */
    'version' => '0.0.1',

    /*
     * Available UI locales. Add a matching lang/{code}/ directory, then list
     * the locale here so Settings → General can select it site-wide.
     *
     * @var array<string, string> code => native label
     */
    'locales' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    'payments' => [
        /*
     * When true, checkout may offer a development-only instant payment method.
     * Never enable in production. Defaults to true only for local+debug.
     */
        'allow_development_instant_pay' => env('AGOVENA_DEV_INSTANT_PAY') !== null
            ? filter_var(env('AGOVENA_DEV_INSTANT_PAY'), FILTER_VALIDATE_BOOLEAN)
            : (env('APP_ENV') === 'local' && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)),
    ],

    /*
     * Module/Extension package installation. Composer/VCS sources are allowlisted.
     * Isolated Composer project lives under storage/app/packages/composer.
     */
    'packages' => [
        'allowed_hosts' => [
            'github.com',
            'gitlab.com',
            'bitbucket.org',
            'codeberg.org',
        ],
        'extra_path_prefixes' => [],
        'extra_module_paths' => [],
        'extra_extension_paths' => [],
        'optional_packages_path' => env('AGOVENA_OPTIONAL_PACKAGES_PATH'),
        'composer_timeout' => 180,
        'composer_binary' => env('AGOVENA_COMPOSER_BINARY'),
        /*
         * GitHub monorepo distribution (option B). Core installs individual packages
         * from subdirectories into storage/app/packages/{modules|extensions}/{id}.
         * Set AGOVENA_PACKAGES_MONOREPO_URL when the real monorepo is published.
         */
        'monorepo' => [
            'repository' => env('AGOVENA_PACKAGES_MONOREPO_URL', 'https://github.com/milovd/optional-packages'),
            'default_ref' => env('AGOVENA_PACKAGES_MONOREPO_REF', 'main'),
            'packages' => [
                'inventory' => ['kind' => 'module', 'path' => 'modules/inventory'],
                'shipping' => ['kind' => 'module', 'path' => 'modules/shipping'],
                'digital' => ['kind' => 'module', 'path' => 'modules/digital'],
                'digital-delivery' => ['kind' => 'module', 'path' => 'modules/digital-delivery'],
                'subscriptions' => ['kind' => 'module', 'path' => 'modules/subscriptions'],
                'provisioning' => ['kind' => 'module', 'path' => 'modules/provisioning'],
                'events' => ['kind' => 'module', 'path' => 'modules/events'],
                'mollie' => ['kind' => 'extension', 'path' => 'extensions/payments/mollie'],
                'stripe' => ['kind' => 'extension', 'path' => 'extensions/payments/stripe'],
                'paypal' => ['kind' => 'extension', 'path' => 'extensions/payments/paypal'],
                'pterodactyl' => ['kind' => 'extension', 'path' => 'extensions/provisioning/pterodactyl'],
                'proxmox' => ['kind' => 'extension', 'path' => 'extensions/provisioning/proxmox'],
                'postnl' => ['kind' => 'extension', 'path' => 'extensions/shipping/postnl'],
            ],
        ],
    ],

    'security' => [
        'privileged_two_factor' => filter_var(env('AGOVENA_PRIVILEGED_2FA', true), FILTER_VALIDATE_BOOLEAN),
        'password_timeout' => (int) env('AGOVENA_PASSWORD_TIMEOUT', 900),
        'headers' => [
            'enabled' => filter_var(env('AGOVENA_SECURITY_HEADERS', true), FILTER_VALIDATE_BOOLEAN),
            'csp' => env('AGOVENA_CSP'),
            'hsts' => filter_var(env('AGOVENA_HSTS', true), FILTER_VALIDATE_BOOLEAN),
            'hsts_max_age' => (int) env('AGOVENA_HSTS_MAX_AGE', 31536000),
            'frame' => env('AGOVENA_FRAME_OPTIONS', 'DENY'),
            'referrer' => env('AGOVENA_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        ],
    ],

    /*
     * Automatic tax rates (when store.automatic_tax_rates is on).
     * Production default: vatnode HTTP JSON (EC TEDB-sourced). catalog is for
     * automated tests / offline only and must not be treated as a live API.
     */
    'tax' => [
        'automatic_provider' => env('AGOVENA_TAX_AUTOMATIC_PROVIDER', 'vatnode'),
        'vatnode_url' => env(
            'AGOVENA_TAX_VATNODE_URL',
            'https://cdn.jsdelivr.net/gh/vatnode/eu-vat-rates-data@main/data/eu-vat-rates-data.json',
        ),
        'cache_ttl' => (int) env('AGOVENA_TAX_CACHE_TTL', 86400),
    ],

    /*
     * Mid-market FX sync (Admin → Currencies → Sync rates). Frankfurter v1 is
     * ECB-sourced; api.frankfurter.app permanently redirects here.
     */
    'currency' => [
        'frankfurter_url' => env(
            'AGOVENA_FRANKFURTER_URL',
            'https://api.frankfurter.dev/v1/latest',
        ),
    ],

    'retention' => [
        'email_logs_days' => (int) env('AGOVENA_EMAIL_LOG_RETENTION', 90),
        'audit_logs_days' => (int) env('AGOVENA_AUDIT_LOG_RETENTION', 365),
        'webhook_events_days' => (int) env('AGOVENA_WEBHOOK_EVENT_RETENTION', 90),
    ],
];
