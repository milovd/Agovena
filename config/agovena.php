<?php

declare(strict_types=1);

return [
    /*
     * Platform version for Extension/Module compatibility constraints.
     */
    'version' => '0.1.0',

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
        'composer_timeout' => 180,
        'composer_binary' => env('AGOVENA_COMPOSER_BINARY'),
    ],
];
