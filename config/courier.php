<?php

return [
    'default' => env('COURIER_DRIVER', 'sfexpress'),

    'drivers' => [
        'sfexpress' => [
            'account' => env('SFEXPRESS_ACCOUNT'),
            'key'     => env('SFEXPRESS_KEY'),
            'secret'  => env('SFEXPRESS_SECRET'),
            'sandbox' => env('SFEXPRESS_SANDBOX', false),
        ],
    ],

    'logging' => [
        'enabled' => env('COURIER_LOGGING_ENABLED', true),

        'retention_days' => env('COURIER_LOGGING_RETENTION_DAYS', 90),

        'redact' => [
            'authorization',
            'api_key',
            'apikey',
            'key',
            'secret',
            'token',
            'password',
        ],
    ],
];
