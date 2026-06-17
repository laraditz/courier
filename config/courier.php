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
];
