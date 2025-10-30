<?php

declare(strict_types=1);

use App\Models\GiantBombGame;
use App\Services\Catalogue\PriceCrossReferencer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

it('prefers mirrored Giant Bomb records over the raw JSON file when available', function (): void {
    Cache::flush();

    GiantBombGame::create([
        'guid' => '3030-424242',
        'giantbomb_id' => 424242,
        'name' => 'DB Test Game',
        'slug' => 'db-test-game',
        'site_detail_url' => 'https://www.giantbomb.com/db-test-game/3030-424242/',
        'platforms' => ['PC'],
        'aliases' => ['DB Test'],
        'primary_image_url' => 'https://cdn.test/db-test.jpg',
        'normalized_name' => 'db test game',
        'payload_hash' => 'hash-1',
        'last_synced_at' => Carbon::now(),
    ]);

    $service = new PriceCrossReferencer(
        giantBombFile: 'not-present.json',
        nexardaFile: 'not-present.json',
        priceGuideFile: 'not-present.csv'
    );

    $collection = $service->build();

    expect($collection)->not()->toBeEmpty();
    $match = $collection->firstWhere('name', 'DB Test Game');
    expect($match)->not()->toBeNull();
    expect($match['image'])->toBe('https://cdn.test/db-test.jpg');
});
