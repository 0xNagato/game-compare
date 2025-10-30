<?php

use App\Services\PriceIngestion\Exceptions\ProviderException;
use App\Services\PriceIngestion\Providers\PriceChartingProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2024-10-01 12:00:00')); // deterministic timestamps
});

afterEach(function () {
    Carbon::setTestNow();
});

it('fetches loose complete and new prices from pricecharting', function () {
    Http::fakeSequence()
        ->push([
            'products' => [
                [
                    'product-id' => '12345',
                    'product-name' => 'Super Mario Bros. Wonder',
                    'console-name' => 'Nintendo Switch',
                ],
            ],
        ])
        ->push([
            'product' => [
                'id' => '12345',
                'currency-code' => 'USD',
                'loose-price' => 44.99,
                'loose-price-date' => '2024-09-28',
                'complete-price' => 52.00,
                'complete-price-date' => '2024-09-27',
                'new-price' => 59.99,
                'new-price-date' => '2024-09-25',
            ],
        ]);

    $provider = new PriceChartingProvider(timeout: 5);

    $payload = $provider->fetchDeals([
        'token' => 'demo-token',
        'catalog' => [
            [
                'product_slug' => 'super-mario-bros-switch',
                'title' => 'Super Mario Bros. Wonder',
                'platform' => 'Nintendo Switch',
                'category' => 'Game',
                'search' => 'Super Mario Bros. Wonder Nintendo Switch',
            ],
        ],
        'store_map' => [
            'loose' => [
                'store_id' => 'pricecharting_loose_usd',
                'region_code' => 'US',
            ],
            'complete' => [
                'store_id' => 'pricecharting_complete_usd',
                'region_code' => 'US',
            ],
            'new' => [
                'store_id' => 'pricecharting_new_usd',
                'region_code' => 'US',
            ],
        ],
    ]);

    expect($payload['results'])->toHaveCount(1);
    expect($payload['meta']['stub'] ?? null)->toBeFalse();

    $deals = $payload['results'][0]['deals'];

    expect($deals)->toHaveCount(3);
    expect($deals[0]['store_id'])->toBe('pricecharting_loose_usd');
    expect($deals[0]['sale_price'])->toBe(44.99);
    expect($deals[1]['store_id'])->toBe('pricecharting_complete_usd');
    expect($deals[1]['sale_price'])->toBe(52.0);
    expect($deals[2]['store_id'])->toBe('pricecharting_new_usd');
    expect($deals[2]['sale_price'])->toBe(59.99);
});

it('throws when token is missing', function () {
    $provider = new PriceChartingProvider;

    $provider->fetchDeals([
        'token' => '',
    ]);
})->throws(ProviderException::class, 'PriceCharting API token is required.');
