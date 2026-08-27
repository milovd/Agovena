<?php

declare(strict_types=1);

return [
    'unauthenticated' => 'Authenticatie is vereist.',
    'unauthorized' => 'Je mag deze actie niet uitvoeren.',
    'not_found' => 'De gevraagde bron is niet gevonden.',
    'rate_limited' => 'Te veel verzoeken. Probeer het zo opnieuw.',
    'ip_not_allowed' => 'Dit IP-adres heeft geen toegang tot de API.',
    'not_installed' => 'Deze winkel is nog niet geïnstalleerd.',
    'payment_failed' => 'Betaling kon niet worden gestart.',
    'checkout_failed' => 'Afrekenen kon niet worden afgerond.',
    'auth' => [
        'invalid' => 'Deze inloggegevens komen niet overeen.',
        'unavailable' => 'Dit account kan de API niet gebruiken.',
    ],
    'http' => [
        '401' => 'Authenticatie is vereist.',
        '403' => 'Je mag deze actie niet uitvoeren.',
        '404' => 'De gevraagde bron is niet gevonden.',
        '409' => 'Die actie is in de huidige status niet geldig.',
        '422' => 'Het verzoek kon niet worden verwerkt.',
        '429' => 'Te veel verzoeken. Probeer het zo opnieuw.',
    ],
];
