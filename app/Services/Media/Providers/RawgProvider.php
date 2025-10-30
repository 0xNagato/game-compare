<?php

namespace App\Services\Media\Providers;

use App\Models\Product;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use App\Services\Media\Support\RawgRateLimiter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RawgProvider implements ProductMediaProvider
{
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
        return 'rawg';
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

        if (blank($query)) {
            return collect();
        }

        $baseUrl = rtrim($this->options['base_url'] ?? 'https://api.rawg.io/api', '/');
        $apiKey = $this->options['api_key'];
        $pageSize = (int) ($this->options['page_size'] ?? 8);
        $fetchTrailers = ($this->options['fetch_trailers'] ?? true) === true;
        $fetchScreenshots = ($this->options['fetch_screenshots'] ?? true) === true;
        $rateLimit = max((int) ($this->options['rate_limit_per_minute'] ?? 0), 0);

        if ($rateLimit > 0) {
            $this->throttle($rateLimit);
        }

        $response = $this->httpClient($baseUrl)->get('/games', [
            'search' => $query,
            'page_size' => $pageSize,
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            return collect();
        }

        $results = data_get($response->json(), 'results', []);

        if (! is_array($results)) {
            return collect();
        }

        $videoOnly = (($context['video_only'] ?? $context['prefer_videos'] ?? false) === true);
        $collection = collect($results)
            ->take($pageSize)
            ->flatMap(function (array $item) use ($baseUrl, $apiKey, $fetchTrailers, $fetchScreenshots, $rateLimit, $context, $videoOnly): array {
                $mediaEntries = [];

                if (! $videoOnly && filled($item['background_image'] ?? null)) {
                    $screenshots = collect($item['short_screenshots'] ?? [])
                        ->pluck('image')
                        ->filter()
                        ->values();

                    $mediaEntries[] = new ProductMediaData(
                        source: $this->getName(),
                        externalId: isset($item['id']) ? (string) $item['id'] : null,
                        mediaType: 'image',
                        title: $item['name'] ?? null,
                        caption: $this->buildCaption($item),
                        url: $item['background_image'],
                        thumbnailUrl: $screenshots->first() ?: $item['background_image'],
                        attribution: 'Imagery courtesy RAWG.io',
                        license: 'See RAWG Terms of Use',
                        licenseUrl: 'https://rawg.io/terms-of-service',
                        metadata: [
                            'slug' => $item['slug'] ?? null,
                            'released' => $item['released'] ?? null,
                            'rating' => $item['rating'] ?? null,
                            'metacritic' => $item['metacritic'] ?? null,
                            'platforms' => collect($item['platforms'] ?? [])
                                ->pluck('platform.name')
                                ->filter()
                                ->all(),
                            'stores' => collect($item['stores'] ?? [])
                                ->pluck('store.name')
                                ->filter()
                                ->all(),
                        ],
                    );
                }

                $clip = $item['clip'] ?? null;
                $videoUrl = null;

                if (is_array($clip)) {
                    $videoUrl = $clip['video'] ?? $clip['clip'] ?? null;
                }

                if ($videoUrl) {
                    $mediaEntries[] = new ProductMediaData(
                        source: $this->getName(),
                        externalId: isset($item['id']) ? (string) $item['id'].':clip' : null,
                        mediaType: 'video',
                        title: ($item['name'] ?? 'Gameplay').' Trailer',
                        caption: 'Gameplay clip via RAWG.io',
                        url: $videoUrl,
                        thumbnailUrl: $clip['preview'] ?? $clip['clips']['320'] ?? $item['background_image'] ?? null,
                        attribution: 'Video courtesy RAWG.io',
                        license: 'See RAWG Terms of Use',
                        licenseUrl: 'https://rawg.io/terms-of-service',
                        metadata: array_filter([
                            'slug' => $item['slug'] ?? null,
                            'clips' => $clip['clips'] ?? null,
                        ]),
                    );
                }

                if ($fetchTrailers) {
                    $mediaEntries = array_merge(
                        $mediaEntries,
                        $this->fetchGameMovies($baseUrl, $apiKey, $item, rateLimit: $rateLimit)
                    );
                }

                if (! $videoOnly && $fetchScreenshots) {
                    $existingImages = collect([$item['background_image'] ?? null])
                        ->merge($item['short_screenshots'] ?? [])
                        ->map(fn ($shot) => is_array($shot) ? ($shot['image'] ?? null) : $shot)
                        ->filter()
                        ->values()
                        ->all();

                    if (blank($item['background_image'] ?? null) || count($existingImages) < 2) {
                        $mediaEntries = array_merge(
                            $mediaEntries,
                            $this->fetchGameScreenshots(
                                $baseUrl,
                                $apiKey,
                                $item,
                                $existingImages,
                                rateLimit: $rateLimit
                            )
                        );
                    }
                }

                return $mediaEntries;
            });

        return $collection
            ->filter()
            ->unique(function (ProductMediaData $media) {
                return ($media->url ?? '').'|'.($media->externalId ?? '').'|'.$media->mediaType;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function buildCaption(array $item): ?string
    {
        $released = $item['released'] ?? null;
        $rating = $item['rating'] ?? null;

        $bits = collect([
            $released ? 'Released '.$released : null,
            $rating ? sprintf('RAWG rating %.1f/5', $rating) : null,
        ])->filter();

        return $bits->isNotEmpty() ? $bits->implode(' · ') : null;
    }

    protected function throttle(int $rateLimit): void
    {
        RawgRateLimiter::await($rateLimit);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return ProductMediaData[]
     */
    protected function fetchGameMovies(string $baseUrl, string $apiKey, array $item, int $limit = 2, int $rateLimit = 0): array
    {
        $gameId = $item['id'] ?? null;

        if (! $gameId || (int) ($item['movies_count'] ?? 0) <= 0) {
            return [];
        }

        $cacheKey = sprintf('rawg:movies:%s', $gameId);
        $ttl = $this->cacheTtl();

        $movies = Cache::remember($cacheKey, $ttl, function () use ($baseUrl, $apiKey, $gameId, $rateLimit) {
            RawgRateLimiter::await($rateLimit);

            $response = $this->httpClient($baseUrl)->get("/games/{$gameId}/movies", [
                'key' => $apiKey,
                'page_size' => 6,
            ]);

            if ($response->failed()) {
                return [];
            }

            $results = data_get($response->json(), 'results', []);

            return is_array($results) ? $results : [];
        });

        $items = collect($movies)
            ->take($limit)
            ->map(function (array $movie) use ($item) {
                $videoUrl = $this->resolveMovieUrl($movie);

                if (! $videoUrl) {
                    return null;
                }

                $thumbnail = $movie['preview'] ?? $movie['image'] ?? $item['background_image'] ?? null;
                $youtubeId = $movie['external_id'] ?? null;
                $title = $movie['name'] ?? (($item['name'] ?? 'Gameplay').' Trailer');

                return new ProductMediaData(
                    source: $this->getName(),
                    externalId: isset($movie['id']) ? (string) $movie['id'] : ($youtubeId ? 'yt:'.$youtubeId : null),
                    mediaType: 'video',
                    title: $title,
                    caption: $youtubeId ? 'Trailer via RAWG · YouTube' : 'Gameplay capture via RAWG',
                    url: $videoUrl,
                    thumbnailUrl: $thumbnail,
                    attribution: 'Video courtesy RAWG.io',
                    license: 'See RAWG Terms of Use',
                    licenseUrl: 'https://rawg.io/terms-of-service',
                    metadata: array_filter([
                        'external_id' => $youtubeId,
                        'stream_urls' => $movie['data'] ?? null,
                        'runtime' => $movie['metadata']['runtime'] ?? null,
                    ]),
                );
            })
            // Ensure we only keep valid ProductMediaData instances for static analysis
            ->filter(fn ($m) => $m instanceof ProductMediaData)
            ->values()
            ->all();

        /** @var ProductMediaData[] $items */
        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $excludeUrls
     * @return ProductMediaData[]
     */
    protected function fetchGameScreenshots(string $baseUrl, string $apiKey, array $item, array $excludeUrls = [], int $limit = 4, int $rateLimit = 0): array
    {
        $gameId = $item['id'] ?? null;

        if (! $gameId) {
            return [];
        }

        $cacheKey = sprintf('rawg:screens:%s', $gameId);
        $ttl = $this->cacheTtl();

        $screens = Cache::remember($cacheKey, $ttl, function () use ($baseUrl, $apiKey, $gameId, $rateLimit) {
            RawgRateLimiter::await($rateLimit);

            $response = $this->httpClient($baseUrl)->get("/games/{$gameId}/screenshots", [
                'key' => $apiKey,
                'page_size' => 12,
            ]);

            if ($response->failed()) {
                return [];
            }

            $results = data_get($response->json(), 'results', []);

            return is_array($results) ? $results : [];
        });

        $exclusion = collect($excludeUrls)->filter()->values();

        return collect($screens)
            ->filter(fn (array $shot) => filled($shot['image'] ?? null))
            ->reject(function (array $shot) use ($exclusion) {
                return $exclusion->contains($shot['image'] ?? null);
            })
            ->take($limit)
            ->map(function (array $shot) use ($item) {
                return new ProductMediaData(
                    source: $this->getName(),
                    externalId: isset($shot['id']) ? (string) $shot['id'] : null,
                    mediaType: 'image',
                    title: $item['name'] ?? null,
                    caption: 'Screenshot courtesy RAWG.io',
                    url: $shot['image'],
                    thumbnailUrl: $shot['image'],
                    attribution: 'Imagery courtesy RAWG.io',
                    license: 'See RAWG Terms of Use',
                    licenseUrl: 'https://rawg.io/terms-of-service',
                    metadata: [
                        'slug' => $item['slug'] ?? null,
                        'width' => $shot['width'] ?? null,
                        'height' => $shot['height'] ?? null,
                    ],
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $movie
     */
    protected function resolveMovieUrl(array $movie): ?string
    {
        $data = $movie['data'] ?? [];
        $youtubeId = $movie['external_id'] ?? null;

        if ($youtubeId) {
            return sprintf('https://www.youtube.com/watch?v=%s', $youtubeId);
        }

        if (is_array($data)) {
            foreach (['max', '480', 'stream'] as $key) {
                if (! empty($data[$key])) {
                    return $data[$key];
                }
            }
        }

        if (! empty($movie['preview'])) {
            return $movie['preview'];
        }

        return null;
    }

    protected function cacheTtl(): \DateTimeInterface
    {
        $minutes = (int) ($this->options['cache_minutes'] ?? 90);

        if ($minutes < 15) {
            $minutes = 15;
        }

        return now()->addMinutes($minutes);
    }

    protected function httpClient(string $baseUrl): PendingRequest
    {
        return Http::timeout(config('media.http_timeout', 10))
            ->baseUrl($baseUrl)
            ->acceptJson();
    }
}
