<?php

use App\Models\Product;
use App\Services\Media\Providers\NexardaMediaProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('media.providers.nexarda.options.base_url', 'https://www.nexarda.com/api/v3');
    config()->set('media.providers.nexarda.options.timeout', 15);
    config()->set('media.providers.nexarda.options.cache_minutes', 60);
    config()->set('media.providers.nexarda.options.api_key', 'test-nexarda-key');
});

it('fetches cover and trailer assets from nexarda', function (): void {
    $product = Product::factory()->create([
        'metadata' => [
            'nexarda' => [
                'slug' => 'elden-ring',
                'id' => '1234',
            ],
        ],
    ]);

    Http::fake([
        'https://www.nexarda.com/api/v3/games/info*' => Http::response([
            'success' => true,
            'data' => [
                'title' => 'Elden Ring',
                'summary' => 'Become the Elden Lord.',
                'images' => [
                    'cover' => [
                        'id' => 111,
                        'url' => 'https://cdn.nexarda.com/images/elden-ring-cover.jpg',
                        'thumb' => 'https://cdn.nexarda.com/images/elden-ring-cover-thumb.jpg',
                        'caption' => 'Official cover art',
                        'width' => 2048,
                        'height' => 2732,
                    ],
                    'banner' => [
                        'id' => 222,
                        'url' => 'https://cdn.nexarda.com/images/elden-ring-banner.jpg',
                        'thumb' => 'https://cdn.nexarda.com/images/elden-ring-banner-thumb.jpg',
                        'caption' => 'Promotional banner',
                        'width' => 3840,
                        'height' => 1080,
                    ],
                ],
                'media' => [
                    'youtube_trailer_ids' => [
                        'KfjG9ZLGBHE',
                    ],
                    'raw_trailer_urls' => [
                        'https://videos.nexarda.com/elden-ring-launch.mp4',
                    ],
                ],
            ],
        ]),
    ]);

    $provider = new NexardaMediaProvider(config('media.providers.nexarda.options', []) + ['enabled' => true]);

    $media = $provider->fetch($product);

    expect($media)->toHaveCount(4)
        ->and($media->first()->mediaType)->toBe('image')
        ->and($media->first()->url)->toBe('https://cdn.nexarda.com/images/elden-ring-cover.jpg')
        ->and($media->get(2)->mediaType)->toBe('video')
        ->and($media->get(2)->url)->toBe('https://www.youtube.com/watch?v=KfjG9ZLGBHE')
        ->and($media->get(2)->thumbnailUrl)->toBe('https://img.youtube.com/vi/KfjG9ZLGBHE/hqdefault.jpg')
        ->and($media->last()->mediaType)->toBe('video')
        ->and($media->last()->url)->toBe('https://videos.nexarda.com/elden-ring-launch.mp4');

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/games/info')
            && ($request->data()['key'] ?? null) === 'test-nexarda-key'
            && ($request->header('X-Api-Key')[0] ?? null) === 'test-nexarda-key';
    });
});
