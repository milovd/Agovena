<?php

declare(strict_types=1);

return [
    'title' => 'Agovena installeren',
    'brand_alt' => 'Agovena',
    'tagline' => 'Self-hosted commerce-installatie',
    'footer' => 'Agovena-installer. Configureer je winkel één keer, beheer daarna in Admin.',
    'progress_aria' => 'Installatievoortgang',
    'progress_status' => 'Stap :current van :total',

    'steps' => [
        'welcome' => 'Welkom',
        'owner' => 'Eigenaar',
        'store' => 'Winkel',
        'regional' => 'Regio',
        'branding' => 'Branding',
        'theme' => 'Thema',
        'complete' => 'Klaar',
    ],

    'actions' => [
        'continue' => 'Doorgaan',
        'back' => 'Terug',
        'skip' => 'Nu overslaan',
        'install' => 'Agovena installeren',
        'installing' => 'Bezig met installeren…',
        'open_admin' => 'Admin openen',
        'view_storefront' => 'Storefront bekijken',
    ],

    'fields' => [
        'owner_name' => 'Je naam',
        'owner_email' => 'E-mail',
        'owner_password' => 'Wachtwoord',
        'owner_password_confirmation' => 'Bevestig wachtwoord',
        'site_name' => 'Winkelnaam',
        'store_url' => 'Winkel-URL',
        'locale' => 'Standaardtaal',
        'timezone' => 'Tijdzone',
        'currency' => 'Basisvaluta',
        'logo' => 'Winkellogo (optioneel)',
        'favicon' => 'Favicon (optioneel)',
        'use_logo_as_favicon' => 'Gebruik winkellogo als favicon',
        'theme' => 'Thema',
    ],

    'welcome' => [
        'heading' => 'Welkom bij Agovena',
        'lede' => 'Laten we je winkel klaarzetten.',
        'ready_title' => 'Je server is klaar voor Agovena',
        'ready_text' => 'De vereiste systeemcontroles zijn geslaagd. Ga verder om je eigenaarsaccount en winkel aan te maken.',
        'blocked_title' => 'Je server heeft aandacht nodig',
        'blocked_text' => 'Los de onderstaande problemen op en vernieuw daarna deze pagina.',
        'warnings_summary' => ':count optionele waarschuwing(en)',
        'technical_details' => 'Technische details',
        'doctor_hint' => 'Voor de volledige technische checklist: php artisan agovena:doctor.',
    ],

    'owner' => [
        'heading' => 'Maak het eigenaarsaccount',
        'lede' => 'Deze medewerker krijgt de owner-rol met volledige Admin-rechten.',
    ],

    'store' => [
        'heading' => 'Winkelidentiteit',
        'lede' => 'Alleen wat nodig is om je winkel te benoemen. Je kunt later meer verfijnen in Admin.',
        'url_help' => 'Ingesteld via APP_URL in je omgeving. Pas dat daar aan als dit adres onjuist is.',
    ],

    'regional' => [
        'heading' => 'Regionale instellingen',
        'lede' => 'Kies de taal, tijdzone en basisvaluta voor je winkel.',
        'locale_help' => 'Talen komen uit de Agovena-localecatalogus. Je kunt later wisselen in Instellingen.',
    ],

    'branding' => [
        'heading' => 'Winkelbranding',
        'lede' => 'Optioneel. Voeg nu je winkellogo toe, of sla over en configureer branding later in Admin.',
        'product_vs_store' => 'Het Agovena-merk hierboven is de productidentiteit. Upload hier het logo van je winkel.',
        'logo_hint' => 'PNG, JPG, WebP of GIF tot 2 MB.',
        'favicon_hint' => 'Optioneel apart favicon-bestand.',
        'uploading' => 'Uploaden…',
    ],

    'theme' => [
        'heading' => 'Storefront-thema',
        'lede' => 'Bevestig het thema voor je storefront. Je kunt het na installatie aanpassen.',
        'selected' => 'Geselecteerd',
        'customize_later' => 'Je kunt later thema’s installeren en wisselen. Uiterlijk kun je na de setup in Admin aanpassen.',
    ],

    'complete' => [
        'eyebrow' => 'Installatie voltooid',
        'heading' => 'Je Agovena-winkel is klaar',
        'lede' => ':store is geïnstalleerd. Log in op Admin om catalogus, bestellingen en instellingen te beheren.',
        'summary_store' => 'Winkel',
        'summary_locale' => 'Taal',
        'summary_currency' => 'Valuta',
        'summary_theme' => 'Thema',
    ],

    'errors' => [
        'requirements' => 'Los de mislukte verplichte controles op voordat je doorgaat.',
    ],

    'checks' => [
        'php_version' => 'PHP-versie (≥ 8.3)',
        'ext_openssl' => 'OpenSSL-extensie',
        'ext_pdo' => 'PDO-extensie',
        'ext_mbstring' => 'Mbstring-extensie',
        'ext_tokenizer' => 'Tokenizer-extensie',
        'ext_xml' => 'XML-extensie',
        'ext_ctype' => 'Ctype-extensie',
        'ext_json' => 'JSON-extensie',
        'ext_fileinfo' => 'Fileinfo-extensie',
        'app_key' => 'APP_KEY ingesteld',
        'storage_writable' => 'Storage-pad schrijfbaar',
        'bootstrap_cache_writable' => 'Bootstrap-cache schrijfbaar',
        'database' => 'Databaseverbinding',
        'migrations' => 'Applicatieschema is actueel',
        'storage_link' => 'Geüploade afbeeldingen worden mogelijk niet getoond',
        'storage_link_message' => 'Winkellogo’s en andere openbare afbeeldingen verschijnen mogelijk niet totdat openbare bestanden op deze server beschikbaar zijn.',
        'storage_link_technical' => 'Kon de public/storage-koppeling naar storage/app/public niet maken. Voer op de server uit: php artisan storage:link',
        'themes' => 'Thema-beschikbaarheid',
        'extensions_table' => 'Extensietabel',
        'tax_rates_table' => 'Btw-tarieventabel',
        'production_debug' => 'Productie-debug uitgeschakeld',
        'queue_connection' => 'Wachtrijverbinding',
        'mail_default' => 'E-mailtransport',
        'scheduler' => 'Scheduler-heartbeat',
    ],
];
