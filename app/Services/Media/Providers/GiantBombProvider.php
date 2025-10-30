<?php

namespace App\Services\Media\Providers;

use App\Models\Product;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GiantBombProvider implements ProductMediaProvider
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $searchCache = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(private readonly array $options = []) {}

    public function enabled(): bool
    {
        return ($this->options['enabled'] ?? true) === true && filled($this->options['api_key'] ?? null);
    }

    public function getName(): string
    {
        return 'giantbomb';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, ProductMediaData>
     */
    public function fetch(Product $product, array $context = []): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $query = $context['query'] ?? $product->name;
        $resource = $context['resource'] ?? 'game';
        $limit = (int) ($this->options['limit'] ?? 6);
        $videoLimit = (int) ($this->options['video_limit'] ?? 4);
        $includeVideos = ($this->options['include_videos'] ?? true) === true;

        if (blank($query)) {
            return collect();
        }

        $baseUrl = rtrim($this->options['base_url'] ?? 'https://www.giantbomb.com/api', '/');
        $apiKey = $this->options['api_key'];

        $mediaItems = collect();

        $gameResults = $this->search($baseUrl, $apiKey, $query, $resource, $limit);
        $videoOnly = (($context['video_only'] ?? $context['prefer_videos'] ?? false) === true);

        if (! $videoOnly) {
            $mediaItems = $mediaItems->merge(
                collect($gameResults)
                    ->filter(fn (array $item) => ! empty($item['image']['original_url'] ?? null))
                    ->map(function (array $item) {
                        $image = $item['image'] ?? [];

                        $title = $item['name'] ?? null;
                        $deck = $item['deck'] ?? null;

                        return new ProductMediaData(
                            source: $this->getName(),
                            externalId: isset($item['id']) ? (string) $item['id'] : null,
                            mediaType: 'image',
                            title: $title,
                            caption: $deck,
                            url: $image['original_url'],
                            thumbnailUrl: $image['small_url'] ?? $image['super_url'] ?? null,
                            attribution: sprintf('Images © Giant Bomb / CBS Interactive'),
                            license: 'Non-commercial use with attribution',
                            licenseUrl: 'https://www.giantbomb.com/terms-of-use/',
                            metadata: [
                                'resource_type' => $item['resource_type'] ?? null,
                                'site_detail_url' => $item['site_detail_url'] ?? null,
                                'platforms' => $item['platforms'] ?? [],
                            ]
                        );
                    })
            );
        }

        if ($includeVideos) {
            $videoResults = $this->search($baseUrl, $apiKey, $query, 'video', $videoLimit);

            $mediaItems = $mediaItems->merge(
                collect($videoResults)
                    ->filter(function (array $video) {
                        return filled($video['high_url'] ?? $video['hd_url'] ?? $video['low_url'] ?? null);
                    })
                    ->map(function (array $video) {
                        $image = $video['image'] ?? [];
                        $url = $video['hd_url']
                            ?? $video['high_url']
                            ?? $video['low_url']
                            ?? $video['api_detail_url']
                            ?? null;

                        return new ProductMediaData(
                            source: $this->getName(),
                            externalId: isset($video['id']) ? (string) $video['id'] : null,
                            mediaType: 'video',
                            title: $video['name'] ?? 'Giant Bomb Feature',
                            caption: $video['deck'] ?? 'Giant Bomb hosted video',
                            url: $url,
                            thumbnailUrl: $image['medium_url'] ?? $image['screen_url'] ?? null,
                            attribution: 'Video © Giant Bomb / CBS Interactive',
                            license: 'Non-commercial use with attribution',
                            licenseUrl: 'https://www.giantbomb.com/terms-of-use/',
                            metadata: array_filter([
                                'length_seconds' => $video['length_seconds'] ?? null,
                                'publish_date' => $video['publish_date'] ?? null,
                                'site_detail_url' => $video['site_detail_url'] ?? null,
                                'video_type' => $video['video_type'] ?? null,
                            ]),
                        );
                    })
            );
        }

        return $mediaItems
            ->filter()
            ->unique(function (ProductMediaData $media) {
                return ($media->url ?? '').'|'.($media->externalId ?? '').'|'.$media->mediaType;
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function search(string $baseUrl, string $apiKey, string $query, string $resource, int $limit = 6): array
    {
        $limit = max($limit, 1);
        $cacheKey = md5(implode('|', [$baseUrl, $resource, $query, $limit, $apiKey]));

        if (array_key_exists($cacheKey, $this->searchCache)) {
            return $this->searchCache[$cacheKey];
        }

        $response = $this->httpClient($baseUrl)->get('search/', [
            'api_key' => $apiKey,
            'format' => 'json',
            'query' => $query,
            'resources' => $resource,
            'limit' => $limit,
        ]);

        if ($response->failed()) {
            return $this->searchCache[$cacheKey] = [];
        }

        $results = data_get($response->json(), 'results', []);

        return $this->searchCache[$cacheKey] = (is_array($results) ? $results : []);
    }

    protected function httpClient(string $baseUrl): PendingRequest
    {
        return Http::timeout(config('media.http_timeout', 10))
            ->baseUrl(rtrim($baseUrl, '/').'/')
            ->withHeaders([
                'User-Agent' => $this->options['user_agent'] ?? 'GameCompareBot/1.0',
            ])
            ->acceptJson();
    }
}