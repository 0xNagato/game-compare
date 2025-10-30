<?php

use App\Models\Product;
use App\Models\TheGamesDbGame;
use App\Services\Media\Providers\TheGamesDbProvider;

it('returns media from the mirror when querying by name', function (): void {
    $product = Product::factory()->create([
        'name' => 'Halo Infinite',
        'platform' => 'Xbox Series X',
    ]);

    TheGamesDbGame::query()->create([
        'external_id' => 42,
        'title' => 'Halo Infinite',
        'slug' => 'halo-infinite',
        'platform' => 'Xbox Series X',
        'image_url' => 'https://cdn.test/box/halo.jpg',
        'thumb_url' => 'https://cdn.test/thumbs/halo.jpg',
        'metadata' => [
            'overview' => 'Fight as Master Chief again.',
        ],
        'last_synced_at' => now(),
    ]);

    config()->set('media.providers.thegamesdb.options', [
        'enabled' => true,
    ]);

    $provider = app(TheGamesDbProvider::class, ['options' => config('media.providers.thegamesdb.options')]);

    $result = $provider->fetch($product);

    expect($result)->toHaveCount(1)
        ->and($result->first()->url)->toBe('https://cdn.test/box/halo.jpg');
});

it('returns mirror media for a platform lookup', function (): void {
    TheGamesDbGame::query()->create([
        'external_id' => 99,
        'title' => 'Metroid Prime',
        'slug' => 'metroid-prime',
        'platform' => 'Nintendo Switch',
        'image_url' => 'https://cdn.test/box/metroid.jpg',
        'thumb_url' => null,
        'metadata' => [
            'overview' => 'Explore Tallon IV.',
        ],
        'last_synced_at' => now(),
    ]);

    config()->set('media.providers.thegamesdb.options', [
        'enabled' => true,
    ]);

    $provider = app(TheGamesDbProvider::class, ['options' => config('media.providers.thegamesdb.options')]);

    $result = $provider->fetchByPlatform(0, ['platform_name' => 'Nintendo Switch']);

    expect($result)->toHaveCount(1)
        ->and($result->first()->thumbnailUrl)->toBe('https://cdn.test/box/metroid.jpg');
});
