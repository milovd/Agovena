<?php

declare(strict_types=1);

return [
    'update_required' => [
        'title' => 'Database update required',
        'tagline' => 'This installation needs a schema update',
        'footer' => 'Agovena does not run database migrations from the web. Use the command below on the server.',
        'heading' => 'This Agovena installation needs a database update',
        'lede' => 'The application code is newer than the database schema. Pages are paused so they do not fail with missing tables.',
        'instruction' => 'On the server, in the Agovena directory, run:',
        'migrate_alternative' => 'Equivalent: :command',
        'pending_summary' => '{1} :count pending migration|[2,*] :count pending migrations',
        'doctor_hint' => 'After the command finishes, refresh this page. For a full checklist run php artisan agovena:doctor.',
    ],
];
