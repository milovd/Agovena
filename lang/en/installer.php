<?php

declare(strict_types=1);

return [
    'title' => 'Install Agovena',
    'brand_alt' => 'Agovena',
    'tagline' => 'Self-hosted commerce setup',
    'footer' => 'Agovena installer. Configure your store once, then manage it in Admin.',
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
        'logo' => 'Store logo (optional)',
        'favicon' => 'Favicon (optional)',
        'use_logo_as_favicon' => 'Use store logo as favicon',
        'theme' => 'Theme',
    ],

    'welcome' => [
        'heading' => 'Welcome to Agovena',
        'lede' => 'Let’s get your store ready.',
        'ready_title' => 'Your server is ready for Agovena',
        'ready_text' => 'Required system checks passed. Continue to create your owner account and store.',
        'blocked_title' => 'Your server needs attention',
        'blocked_text' => 'Fix the issues below, then refresh this page to continue.',
        'warnings_summary' => ':count optional warning(s)',
        'technical_details' => 'Technical details',
        'doctor_hint' => 'For a full technical checklist, run php artisan agovena:doctor.',
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
        'heading' => 'Store branding',
        'lede' => 'Optional. Add your store logo now, or skip and configure branding later in Admin.',
        'product_vs_store' => 'The Agovena mark above is the product identity. Upload your merchant store logo here.',
        'logo_hint' => 'PNG, JPG, WebP or GIF up to 2 MB.',
        'favicon_hint' => 'Optional separate favicon image.',
        'uploading' => 'Uploading…',
    ],

    'theme' => [
        'heading' => 'Storefront Theme',
        'lede' => 'Confirm the Theme for your storefront. You can customize it after installation.',
        'selected' => 'Selected',
        'customize_later' => 'You can install and change Themes later. Appearance can be customized in Admin after setup.',
    ],

    'complete' => [
        'eyebrow' => 'Installation complete',
        'heading' => 'Your Agovena store is ready',
        'lede' => ':store is installed. Sign in to Admin to manage catalog, orders, and settings.',
        'summary_store' => 'Store',
        'summary_locale' => 'Language',
        'summary_currency' => 'Currency',
        'summary_theme' => 'Theme',
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
        'migrations' => 'Application schema is current',
        'storage_link' => 'Uploaded images may not display',
        'storage_link_message' => 'Store logos and other public images may not appear until public file access is available on this server.',
        'storage_link_technical' => 'Could not create the public/storage link to storage/app/public. On the server, run: php artisan storage:link',
        'themes' => 'Theme availability',
        'extensions_table' => 'Extensions table',
        'tax_rates_table' => 'Tax rates table',
        'production_debug' => 'Production debug disabled',
        'queue_connection' => 'Queue connection',
        'mail_default' => 'Mail transport',
        'scheduler' => 'Scheduler heartbeat',
    ],
];
