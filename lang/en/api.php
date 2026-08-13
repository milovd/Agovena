<?php

declare(strict_types=1);

return [
    'unauthenticated' => 'Authentication is required.',
    'unauthorized' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'rate_limited' => 'Too many requests. Try again shortly.',
    'not_installed' => 'This store is not installed yet.',
    'payment_failed' => 'Payment could not be started.',
    'checkout_failed' => 'Checkout could not be completed.',
    'http' => [
        '401' => 'Authentication is required.',
        '403' => 'You are not allowed to perform this action.',
        '404' => 'The requested resource was not found.',
        '409' => 'That action is not valid in the current state.',
        '422' => 'The request could not be processed.',
        '429' => 'Too many requests. Try again shortly.',
    ],
    'auth' => [
        'invalid' => 'Those credentials do not match our records.',
        'unavailable' => 'This account cannot use the API.',
    ],
];
