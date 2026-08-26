<?php

declare(strict_types=1);

return [
    'tagline' => 'Self-hosted commerce',
    'footer' => 'Agovena',
    'actions' => [
        'home' => 'Back to store',
        'back' => 'Go back',
    ],
    '404' => [
        'title' => 'Page not found',
        'heading' => 'This page could not be found',
        'lede' => 'The address may be wrong, or the product or page is no longer available.',
    ],
    '403' => [
        'title' => 'Not allowed',
        'heading' => 'You do not have access',
        'lede' => 'This page requires a signed-in account with the right permissions.',
    ],
    '419' => [
        'title' => 'Session expired',
        'heading' => 'Your session expired',
        'lede' => 'Refresh the page and try again. If you were filling in a form, submit it once more.',
    ],
    '500' => [
        'title' => 'Something went wrong',
        'heading' => 'Something went wrong',
        'lede' => 'The store could not complete this request. Try again in a moment. If this continues, the operator should check the application log.',
    ],
    '503' => [
        'title' => 'Temporarily unavailable',
        'heading' => 'The store is temporarily unavailable',
        'lede' => 'Agovena is being updated or is in maintenance. Please try again shortly.',
    ],
    '405' => [
        'title' => 'Method not allowed',
        'heading' => 'This method is not allowed here',
        'lede' => 'The requested action is not supported for this address.',
    ],
    '505' => [
        'title' => 'HTTP version not supported',
        'heading' => 'This HTTP version is not supported',
        'lede' => 'Use a current browser or client and try again.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'heading' => 'Too many requests',
        'lede' => 'Please wait a moment and try again.',
    ],
];
