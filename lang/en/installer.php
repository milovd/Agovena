<?php

declare(strict_types=1);

return [
    'title' => 'Install Agovena',
    'tagline' => 'Self-hosted commerce setup',
    'footer' => 'Agovena installer — configure your store once, then manage it in Admin.',
    'progress_aria' => 'Installation progress',
    'progress_status' => 'Step :current of :total',

    'steps' => [
        'welcome' => 'Welcome',
        'owner' => 'Owner',
        'store' => 'Store',
        'regional' => 'Regional',
        'branding' => 'Branding',
        'theme' => 'Theme',
        'complete' => 'Ready',
    ],

    'actions' => [
        'continue' => 'Continue',
        'back' => 'Back',
        'skip' => 'Skip for now',
        'install' => 'Install Agovena',
        'installing' => 'Installing…',
        'open_admin' => 'Open Admin',
        'view_storefront' => 'View storefront',
    ],

    'fields' => [
        'owner_name' => 'Your name',
        'owner_email' => 'Email',
        'owner_password' => 'Password',
        'owner_password_confirmation' => 'Confirm password',
        'site_name' => 'Store name',
        'store_url' => 'Store URL',
        'locale' => 'Default language',
        'timezone' => 'Timezone',
        'currency' => 'Base currency',
        'logo' => 'Logo (optional)',
        'favicon' => 'Favicon (optional)',
        'use_logo_as_favicon' => 'Use logo as favicon',
        'theme' => 'Theme',
    ],

    'welcome' => [
        'heading' => 'Welcome to Agovena',
        'lede' => 'We will create your owner account and the minimum store configuration. Database credentials and server provisioning stay in your deployment environment.',
    ],

    'owner' => [
        'heading' => 'Create the owner account',
        'lede' => 'This staff user receives the owner role with full Admin permissions.',
    ],

    'store' => [
        'heading' => 'Store identity',
        'lede' => 'Only what is needed to name your store. You can refine settings later in Admin.',
        'url_help' => 'Configured via APP_URL in your environment. Change it there if this address is wrong.',
    ],

    'regional' => [
        'heading' => 'Regional settings',
        'lede' => 'Choose the language, timezone, and base currency for your store.',
        'locale_help' => 'Languages come from the Agovena locale catalog. You can switch later in Settings.',
    ],

    'branding' => [
        'heading' => 'Branding',
        'lede' => 'Optional. Upload a logo now, or skip and add branding later in Admin → Settings.',
        'uploading' => 'Uploading…',
    ],

    'theme' => [
        'heading' => 'Confirm Theme',
        'lede' => 'Activate a Theme for the storefront. You can customize it after installation.',
        'customize_later' => 'Appearance, colors, and homepage sections can be customized in Admin after setup.',
    ],

    'complete' => [
        'eyebrow' => 'Installation complete',
        'heading' => 'Your store is ready',
        'lede' => ':store is installed. Sign in to Admin to manage catalog, orders, and settings.',
    ],

    'errors' => [
        'requirements' => 'Fix the failed required checks before continuing.',
    ],

    'checks' => [
        'php_version' => 'PHP version (≥ 8.3)',
        'ext_openssl' => 'OpenSSL extension',
        'ext_pdo' => 'PDO extension',
        'ext_mbstring' => 'Mbstring extension',
        'ext_tokenizer' => 'Tokenizer extension',
        'ext_xml' => 'XML extension',
        'ext_ctype' => 'Ctype extension',
        'ext_json' => 'JSON extension',
        'ext_fileinfo' => 'Fileinfo extension',
        'app_key' => 'APP_KEY set',
        'storage_writable' => 'Storage path writable',
        'bootstrap_cache_writable' => 'Bootstrap cache writable',
        'database' => 'Database connection',
        'migrations' => 'Required migrations applied',
        'storage_link' => 'Public storage link',
        'themes' => 'Theme availability',
    ],
];
