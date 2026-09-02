<?php

declare(strict_types=1);

return [
    'tagline' => 'Self-hosted commerce',
    'footer' => 'Agovena',
    'skip_to_content' => 'Skip to content',
    'status_label' => 'Error :status',
    'theme_to_dark' => 'Switch to dark mode',
    'theme_to_light' => 'Switch to light mode',
    'actions' => [
        'home' => 'Back to store',
        'back' => 'Go back',
    ],
    '404' => [
        'title' => 'Page not found',
        'heading' => 'Oops, this page took a wrong turn',
        'lede' => 'Let’s get you back somewhere familiar.',
        'description' => 'The page you’re looking for doesn’t exist or may have moved.',
    ],
    '403' => [
        'title' => 'Not allowed',
        'heading' => 'This corner is members-only',
        'lede' => 'Sign in with an account that has permission to view this page.',
    ],
    '419' => [
        'title' => 'Session expired',
        'heading' => 'Your session took a short break',
        'lede' => 'Refresh the page and try again. If you were filling in a form, submit it once more.',
    ],
    '500' => [
        'title' => 'Something went wrong',
        'heading' => 'We hit a small snag',
        'lede' => 'The store could not complete this request. Try again in a moment.',
    ],
    '503' => [
        'title' => 'Temporarily unavailable',
        'heading' => 'The shop is taking a short break',
        'lede' => 'We are applying an update. Please come back in a moment.',
    ],
    '405' => [
        'title' => 'Method not allowed',
        'heading' => 'That action does not work here',
        'lede' => 'The requested action is not supported for this address.',
    ],
    '505' => [
        'title' => 'HTTP version not supported',
        'heading' => 'This browser needs an update',
        'lede' => 'Use a current browser or client and try again.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'heading' => 'You are moving a little fast',
        'lede' => 'Give the store a moment, then try again.',
    ],
];
