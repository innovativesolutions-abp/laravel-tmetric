<?php

return [
    'default' => env('TMETRIC_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'token' => env('TMETRIC_TOKEN'),
            'account_id' => env('TMETRIC_ACCOUNT_ID'),
            'legacy_enabled' => false,
            'v3_base_url' => 'https://app.tmetric.com/api/v3',
            'legacy_base_url' => 'https://app.tmetric.com',
            'timeout' => 15,
            'connect_timeout' => 5,
            'max_attempts' => 3,
            'max_retry_delay_seconds' => 30,
        ],
    ],
];
