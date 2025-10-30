<?php

return [
    'weights' => [
        'rawg' => 1.0,
        'giantbomb' => 0.9,
        'thegamesdb' => 0.8,
        'nexarda' => 0.6,
        'wikimedia' => 0.5,
    ],

    'limits' => [
        'rawg' => ['max_rps' => 2.0, 'burst' => 4],
        'giantbomb' => ['max_rps' => 1.0, 'burst' => 2],
        'thegamesdb' => ['max_rps' => 2.0, 'burst' => 4],
        'nexarda' => ['max_rps' => 1.0, 'burst' => 2],
        'wikimedia' => ['max_rps' => 5.0, 'burst' => 10],
        'coingecko' => ['max_rps' => 2.0, 'burst' => 4],
        'pricecharting' => ['max_rps' => 1.0, 'burst' => 2],
    ],

    'queues' => [
        'providers:rawg',
        'providers:giantbomb',
        'providers:thegamesdb',
        'providers:nexarda',
        'providers:wikimedia',
        'offers',
        'aggregate',
        'verify',
    ],
];
