<?php

declare(strict_types=1);

return [
    'title' => 'Agovena installeren',
    'tagline' => 'Self-hosted commerce-installatie',
    'footer' => 'Agovena-installer — configureer je winkel één keer, beheer daarna in Admin.',
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
        'logo' => 'Logo (optioneel)',
        'favicon' => 'Favicon (optioneel)',
        'use_logo_as_favicon' => 'Gebruik logo als favicon',
        'theme' => 'Thema',
    ],

    'welcome' => [
        'heading' => 'Welkom bij Agovena',
        'lede' => 'We maken je eigenaarsaccount en de minimale winkelconfiguratie. Databasegegevens en serverinrichting blijven in je deployment-omgeving.',
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
        'heading' => 'Branding',
        'lede' => 'Optioneel. Upload nu een logo, of sla over en voeg branding later toe via Admin → Instellingen.',
        'uploading' => 'Uploaden…',
    ],

    'theme' => [
        'heading' => 'Bevestig thema',
        'lede' => 'Activeer een thema voor de storefront. Je kunt het na installatie aanpassen.',
        'customize_later' => 'Uiterlijk, kleuren en homepage-secties kun je na de setup in Admin aanpassen.',
    ],

    'complete' => [
        'eyebrow' => 'Installatie voltooid',
        'heading' => 'Je winkel is klaar',
        'lede' => ':store is geïnstalleerd. Log in op Admin om catalogus, bestellingen en instellingen te beheren.',
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
        'migrations' => 'Vereiste migraties toegepast',
        'storage_link' => 'Publieke storage-link',
        'themes' => 'Thema-beschikbaarheid',
    ],
];
