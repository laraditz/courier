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

    'webhook' => [
        /*
         * Maximum inbound webhook requests per minute, per driver per IP.
         * Set to null to disable throttling. Override for a single carrier
         * with drivers.{driver}.webhook.rate_limit.
         */
        'rate_limit' => env('COURIER_WEBHOOK_RATE_LIMIT', 60),
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
            'appkey',
            'appsecret',
            'signature',
            'digest',
            'apiaccount',
        ],
    ],
];
