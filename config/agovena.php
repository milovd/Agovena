<?php

declare(strict_types=1);

return [
    'payments' => [
        /*
     * When true, checkout may offer a development-only instant payment method.
     * Never enable in production. Defaults to true only for local+debug.
     */
        'allow_development_instant_pay' => env('AGOVENA_DEV_INSTANT_PAY') !== null
            ? filter_var(env('AGOVENA_DEV_INSTANT_PAY'), FILTER_VALIDATE_BOOLEAN)
            : (env('APP_ENV') === 'local' && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)),
    ],
];
