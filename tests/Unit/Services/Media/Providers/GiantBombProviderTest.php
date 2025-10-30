<?php

use App\Models\Product;
use App\Services\Media\Providers\GiantBombProvider;
use Illuminate\Support\Facades\Http;

it('collects image and video entries from Giant Bomb search results', function (): void {
    $product = Product::factory()->create([
        'name' => 'Dark Souls Remastered',
    ]);

    Http::fake(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
        $resource = $query['resources'] ?? '';

        if ($resource === 'game') {
            return Http::response([
                'results' => [
                    [
                        'id' => 101,
                        'name' => 'Dark Souls Remastered',
                        'deck' => 'Prepare to die again.',
                        'resource_type' => 'game',
                        'site_detail_url' => 'https://www.giantbomb.com/dark-souls-remastered/3030-101/',
                        'image' => [
                            'original_url' => 'https://giantbomb.com/images/dsr-cover.jpg',
                            'small_url' => 'https://giantbomb.com/images/dsr-cover-small.jpg',
                        ],
                        'platforms' => [
                            ['name' => 'PC'],
                        ],
                    ],
                ],
            ]);
        }

        if ($resource === 'video') {
            return Http::response([
                'results' => [
                    [
                        'id' => 202,
                        'name' => 'Dark Souls Remastered Trailer',
                        'deck' => 'Official trailer via Giant Bomb',
                        'high_url' => 'https://videos.giantbomb.com/dsr-trailer-high.mp4',
                        'image' => [
                            'medium_url' => 'https://giantbomb.com/images/dsr-trailer-medium.jpg',
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([], 404);
    });

    $provider = new GiantBombProvider([
        'api_key' => 'test-giantbomb-key',
        'enabled' => true,
        'include_videos' => true,
        'limit' => 4,
        'video_limit' => 2,
    ]);

    $media = $provider->fetch($product);

    expect($media)->toHaveCount(2)
        ->and($media->first()->mediaType)->toBe('image')
        ->and($media->first()->url)->toBe('https://giantbomb.com/images/dsr-cover.jpg')
        ->and($media->last()->mediaType)->toBe('video')
        ->and($media->last()->url)->toBe('https://videos.giantbomb.com/dsr-trailer-high.mp4');

    Http::assertSentCount(2);
});
