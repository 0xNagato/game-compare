<?php

return [
    'providers' => [
        'thegamesdb' => [
            'enabled' => env('THEGAMESDB_ENABLED', true),
            'class' => App\Services\Media\Providers\TheGamesDbProvider::class,
            'options' => [
                'base_url' => env('THEGAMESDB_BASE_URL', 'https://api.thegamesdb.net/v1'),
                'public_key' => env('THEGAMESDB_PUBLIC_KEY'),
                'private_key' => env('THEGAMESDB_PRIVATE_KEY'),
                'platforms' => [],
            ],
        ],
        'giantbomb' => [
            'enabled' => env('GIANTBOMB_ENABLED', true),
            'class' => App\Services\Media\Providers\GiantBombProvider::class,
            'options' => [
                'base_url' => env('GIANTBOMB_BASE_URL', 'https://www.giantbomb.com/api'),
                'api_key' => env('GIANTBOMB_API_KEY'),
                'user_agent' => env('GIANTBOMB_USER_AGENT', 'GameCompareBot/1.0 (portfolio use)'),
                'limit' => env('GIANTBOMB_PAGE_SIZE', 6),
                'video_limit' => env('GIANTBOMB_VIDEO_LIMIT', 6),
                'include_videos' => true,
            ],
        ],
        'nexarda' => [
            'enabled' => env('NEXARDA_MEDIA_ENABLED', true),
            'class' => App\Services\Media\Providers\NexardaMediaProvider::class,
            'options' => [
                'base_url' => env('NEXARDA_BASE_URL', 'https://www.nexarda.com/api/v3'),
                'timeout' => env('NEXARDA_MEDIA_TIMEOUT', 20),
                'cache_minutes' => env('NEXARDA_MEDIA_CACHE_MINUTES', 180),
                // Allow either NEXARDA_API_KEY or CATALOGUE_NEXARDA_API_KEY
                'api_key' => env('NEXARDA_API_KEY', env('CATALOGUE_NEXARDA_API_KEY')),
            ],
        ],
        'rawg' => [
            'enabled' => env('RAWG_ENABLED', true),
            'class' => App\Services\Media\Providers\RawgProvider::class,
            'options' => [
                'base_url' => env('RAWG_BASE_URL', 'https://api.rawg.io/api'),
                'api_key' => env('RAWG_API_KEY'),
                'page_size' => env('RAWG_PAGE_SIZE', 20),
                'rate_limit_per_minute' => env('RAWG_REQS_PER_MIN', 30),
                'fetch_trailers' => true,
                'fetch_screenshots' => true,
            ],
        ],
        'wikimedia_commons' => [
            'enabled' => true,
            'class' => App\Services\Media\Providers\WikimediaCommonsProvider::class,
            'options' => [
                'base_url' => env('WIKIMEDIA_COMMONS_BASE_URL', 'https://commons.wikimedia.org/w/api.php'),
                'default_license' => 'CC BY-SA',
            ],
        ],
    ],

    'http_timeout' => env('MEDIA_HTTP_TIMEOUT', 20),

    'cache_ttl' => env('MEDIA_CACHE_TTL', 3600),

    // Media proxy settings for inline video playback
    'proxy' => [
        'enabled' => env('MEDIA_PROXY_ENABLED', true),
        'timeout' => env('MEDIA_PROXY_TIMEOUT', 20),
        // Limit proxy to trusted hosts only to avoid open proxy abuse
        'allowed_hosts' => [
            // Giant Bomb video CDN (examples)
            'giantbomb.com',
            '*.giantbomb.com',
            // Nexarda raw trailer hosts (if any direct links are provided)
            'nexarda.com',
            '*.nexarda.com',
            // Wikimedia Commons direct media (for dev/demo trailers)
            'upload.wikimedia.org',
            '*.wikimedia.org',
            // Add additional trusted CDNs as needed
        ],
    ],

    'scheduler' => [
        'missing_limit' => env('MEDIA_SCHEDULER_MISSING_LIMIT', 12),
        'stale_limit' => env('MEDIA_SCHEDULER_STALE_LIMIT', 12),
        'stale_days' => env('MEDIA_SCHEDULER_STALE_DAYS', 14),
    ],
];