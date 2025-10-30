<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'alert_webhook' => env('DISCORD_ALERT_WEBHOOK'),
    ],

    'geo' => [
        'countries_url' => env('GEO_COUNTRIES_URL', 'https://raw.githubusercontent.com/datasets/geo-countries/master/data/countries.geojson'),
    ],

    'thegamesdb' => [
        'base_url' => env('THEGAMESDB_BASE_URL', 'https://api.thegamesdb.net/v1'),
        'public_key' => env('THEGAMESDB_PUBLIC_KEY'),
        'private_key' => env('THEGAMESDB_PRIVATE_KEY'),
        'user_agent' => env('THEGAMESDB_USER_AGENT', 'PriceCompareBot/1.0 (+contact)'),
    ],

];
