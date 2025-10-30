<?php

use App\Models\Product;
use App\Services\TheGamesDb\TheGamesDbMirrorRepository as MirrorRepository;
use Illuminate\Support\Facades\Artisan;

it('syncs product media from thegamesdb with the public key by default', function (): void {
    $mirror = new MirrorRepository;

    $product = Product::factory()->create([
        'name' => 'Metroid Prime',
        'slug' => 'metroid-prime',
        'platform' => 'Nintendo GameCube',
    ]);

    config()->set('media.providers.thegamesdb.options', [
        'base_url' => 'https://api.test',
        'public_key' => 'public-key',
        'private_key' => 'private-key',
        'enabled' => true,
    ]);

    $mirror->upsertGame([
        'external_id' => 1337,
        'title' => 'Metroid Prime',
        'slug' => 'metroid-prime',
        'platform' => 'Nintendo GameCube',
        'image_url' => 'https://cdn.test/box/metroid-prime.jpg',
        'thumb_url' => 'https://cdn.test/thumb/metroid-prime.jpg',
        'metadata' => [
            'overview' => 'Explore Tallon IV.',
            'genres' => ['Adventure'],
        ],
    ]);

    Artisan::call('media:sync-thegamesdb', [
        '--product' => 'metroid-prime',
    ]);

    $this->assertDatabaseHas('product_media', [
        'product_id' => $product->id,
        'source' => 'thegamesdb',
        'external_id' => '1337',
        'url' => 'https://cdn.test/box/metroid-prime.jpg',
    ]);
});

it('syncs product media from thegamesdb with the private key when requested', function (): void {
    $mirror = new MirrorRepository;

    $product = Product::factory()->create([
        'name' => 'Metroid Prime Remastered',
        'slug' => 'metroid-prime-remastered',
        'platform' => 'Nintendo Switch',
    ]);

    config()->set('media.providers.thegamesdb.options', [
        'base_url' => 'https://api.test',
        'public_key' => 'public-key',
        'private_key' => 'private-key',
        'enabled' => true,
    ]);

    $mirror->upsertGame([
        'external_id' => 1441,
        'title' => 'Metroid Prime Remastered',
        'slug' => 'metroid-prime-remastered',
        'platform' => 'Nintendo Switch',
        'image_url' => 'https://cdn.test/box/metroid-prime-remastered.jpg',
        'thumb_url' => 'https://cdn.test/thumb/metroid-prime-remastered.jpg',
        'metadata' => [
            'overview' => 'Remastered adventure.',
            'genres' => ['Adventure'],
        ],
    ]);

    Artisan::call('media:sync-thegamesdb', [
        '--product' => 'metroid-prime-remastered',
        '--use-private-key' => true,
    ]);

    $this->assertDatabaseHas('product_media', [
        'product_id' => $product->id,
        'source' => 'thegamesdb',
        'external_id' => '1441',
        'url' => 'https://cdn.test/box/metroid-prime-remastered.jpg',
    ]);
});