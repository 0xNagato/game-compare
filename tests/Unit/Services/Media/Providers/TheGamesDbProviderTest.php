<?php

use App\Models\Product;
use App\Services\Media\Providers\TheGamesDbProvider;
use App\Services\TheGamesDb\TheGamesDbMirrorRepository as MirrorRepository;

it('returns media hydrated from the local mirror', function (): void {
    $mirror = new MirrorRepository;

    $product = Product::factory()->create([
        'name' => 'Super Mario Bros.',
        'platform' => 'Nintendo Entertainment System',
    ]);

    $mirror->upsertGame([
        'external_id' => 42,
        'title' => 'Super Mario Bros.',
        'slug' => 'super-mario-bros',
        'platform' => 'Nintendo Entertainment System',
        'image_url' => 'https://cdn.test/super-mario-bros.jpg',
        'thumb_url' => 'https://cdn.test/super-mario-bros-thumb.jpg',
        'metadata' => [
            'overview' => 'Classic platformer.',
        ],
    ]);

    $provider = new TheGamesDbProvider($mirror, ['enabled' => true]);

    $media = $provider->fetch($product, ['limit' => 5]);

    expect($media)->not()->toBeEmpty();

    $first = $media->first();

    expect($first->source)->toBe('thegamesdb')
        ->and($first->externalId)->toBe('42')
        ->and($first->mediaType)->toBe('image')
        ->and($first->title)->toBe('Super Mario Bros.')
        ->and($first->url)->toBe('https://cdn.test/super-mario-bros.jpg')
        ->and($first->thumbnailUrl)->toBe('https://cdn.test/super-mario-bros-thumb.jpg');
});
