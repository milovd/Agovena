<?php

use App\Models\Customer;
use App\Models\StaffUser;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'staff'),
    ],

    'guards' => [
        'staff' => [
            'driver' => 'session',
            'provider' => 'staff_users',
        ],
        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],
    ],

    'providers' => [
        'staff_users' => [
            'driver' => 'eloquent',
            'model' => StaffUser::class,
        ],
        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],
    ],

    'passwords' => [
        'staff' => [
            'provider' => 'staff_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
