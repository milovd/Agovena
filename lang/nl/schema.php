<?php

declare(strict_types=1);

return [
    'update_required' => [
        'title' => 'Database-update vereist',
        'tagline' => 'Deze installatie heeft een schema-update nodig',
        'footer' => 'Agovena voert geen databasemigraties uit via het web. Gebruik het onderstaande commando op de server.',
        'heading' => 'Deze Agovena-installatie heeft een database-update nodig',
        'lede' => 'De applicatiecode is nieuwer dan het databaseschema. Pagina’s zijn gepauzeerd zodat ze niet falen door ontbrekende tabellen.',
        'instruction' => 'Voer op de server, in de Agovena-map, dit commando uit:',
        'migrate_alternative' => 'Gelijkwaardig: :command',
        'pending_summary' => '{1} :count openstaande migratie|[2,*] :count openstaande migraties',
        'doctor_hint' => 'Vernieuw deze pagina nadat het commando is afgerond. Voor de volledige checklist: php artisan agovena:doctor.',
    ],
];
