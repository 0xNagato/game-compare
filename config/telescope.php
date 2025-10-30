<?php

return [
    // Disable Telescope by default in this workspace to avoid DB writes when tables are not migrated.
    // You can enable it by setting TELESCOPE_ENABLED=true in .env and running telescope:install & migrations.
    'enabled' => (bool) env('TELESCOPE_ENABLED', false),

    // Default driver; irrelevant when disabled.
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    // Only record entries when explicitly enabled.
    'path' => env('TELESCOPE_PATH', 'telescope'),

    'queue' => env('TELESCOPE_QUEUE', 'default'),

    'truncate' => [
        'limit' => env('TELESCOPE_TRUNCATE_LIMIT', 1000),
    ],
];
