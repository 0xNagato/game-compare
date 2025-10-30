<?php

return [
    'path' => env('HORIZON_PATH', 'horizon'),

    'domain' => env('HORIZON_DOMAIN'),

    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    'prefix' => env('HORIZON_PREFIX', 'horizon'),

    'middleware' => ['web', 'auth'],

    'waits' => [
        'redis:default' => 60,
        'redis:fetch' => 90,
        'redis:media' => 90,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 1440,
        'completed' => 1440,
        'recent_failed' => 2880,
        'failed' => 10080,
        'monitored' => 4320,
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    'environments' => [
        'production' => [
            'supervisor-production' => [
                'connection' => env('HORIZON_CONNECTION', 'redis'),
                'queue' => [
                    'default',
                    'providers:rawg',
                    'providers:giantbomb',
                    'offers',
                    'aggregate',
                    'verify',
                    'media',
                ],
                'balance' => 'auto',
                'processes' => 10,
                'timeout' => 120,
                'tries' => 3,
            ],
            'supervisor-fetch' => [
                'connection' => env('HORIZON_CONNECTION', 'redis'),
                'queue' => [
                    'fetch',
                ],
                'balance' => 'auto',
                'processes' => 4,
                'timeout' => 120,
                'tries' => 3,
            ],
        ],
        'local' => [
            'supervisor-local' => [
                'connection' => env('HORIZON_CONNECTION', 'redis'),
                'queue' => [
                    'default',
                    'providers:rawg',
                    'providers:giantbomb',
                    'offers',
                    'aggregate',
                    'verify',
                    'media',
                ],
                'balance' => 'auto',
                'processes' => 6,
                'timeout' => 90,
                'tries' => 3,
            ],
            'supervisor-fetch' => [
                'connection' => env('HORIZON_CONNECTION', 'redis'),
                'queue' => [
                    'fetch',
                ],
                'balance' => 'auto',
                'processes' => 2,
                'timeout' => 90,
                'tries' => 3,
            ],
        ],
    ],
];
