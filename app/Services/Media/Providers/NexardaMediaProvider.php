<?php

namespace App\Services\Media\Providers;

use App\Models\Product;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NexardaMediaProvider implements ProductMediaProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(private readonly array $options = []) {}

    public function enabled(): bool
    {
        return ($this->options['enabled'] ?? true) === true;
    }

    public function getName(): string
    {
        return 'nexarda';
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

        $metadata = is_array($product->getAttribute('metadata')) ? $product->getAttribute('metadata') : [];
        $externalIds = is_array($product->getAttribute('external_ids')) ? $product->getAttribute('external_ids') : [];

        $slug = $context['slug']
            ?? Arr::get($metadata, 'nexarda.slug')
            ?? Arr::get($metadata, 'nexarda_slug')
            ?? Arr::get($metadata, 'slug')
            ?? $product->slug;

        $nexardaId = $context['nexarda_id']
            ?? Arr::get($metadata, 'nexarda.id')
            ?? Arr::get($metadata, 'nexarda_id')
            ?? Arr::get($externalIds, 'nexarda');

        if (blank($slug) && blank($nexardaId)) {
            return collect();
        }

        $baseUrl = rtrim($this->options['base_url'] ?? 'https://www.nexarda.com/api/v3', '/');
        $timeout = (int) ($this->options['timeout'] ?? config('media.http_timeout', 20));
        $apiKey = Arr::get($this->options, 'api_key');

        $cacheKey = $this->cacheKey($slug, $nexardaId);
        $ttl = now()->addMinutes(max((int) ($this->options['cache_minutes'] ?? 120), 30));

    $payload = Cache::remember($cacheKey, $ttl, fn () => $this->requestGameInfo($baseUrl, $timeout, $slug, $nexardaId, $apiKey));

        if (! is_array($payload)) {
            return collect();
        }

    // New API shape uses `product` key
    $data = Arr::get($payload, 'product') ?? Arr::get($payload, 'data');

        if (! is_array($data)) {
            return collect();
        }

        // Product Details endpoint returns image URLs directly (not nested objects)
        $images = collect([
            Arr::has($data, 'images.cover') ? ['url' => Arr::get($data, 'images.cover')] : null,
            Arr::has($data, 'images.banner') ? ['url' => Arr::get($data, 'images.banner')] : null,
        ])->filter(fn ($value) => is_array($value) && filled(Arr::get($value, 'url')));

        // Nexarda v3 public API doesn't currently expose trailer collections; keep a placeholder for future fields
        $youtubeTrailers = collect(Arr::get($data, 'media.youtube_trailer_ids', []))
            ->filter(fn ($id) => is_string($id) && filled($id))
            ->map(fn (string $id) => [
                'external_id' => $id,
                'url' => sprintf('https://www.youtube.com/watch?v=%s', $id),
                'thumbnail' => sprintf('https://img.youtube.com/vi/%s/hqdefault.jpg', $id),
                'source' => 'youtube',
            ]);

        $rawTrailers = collect(Arr::get($data, 'media.raw_trailer_urls', []))
            ->filter(fn ($url) => is_string($url) && filled($url))
            ->map(fn (string $url) => [
                'external_id' => '',
                'url' => $url,
                'thumbnail' => '',
                'source' => 'direct',
            ]);

        $trailers = $youtubeTrailers->merge($rawTrailers);

    $defaultTitle = Arr::get($data, 'title') ?? Arr::get($data, 'name') ?? $product->name ?? null;
    $defaultThumbnail = Arr::get($data, 'images.cover');

        $items = collect();

        $images->each(function (array $image) use (&$items, $data) {
            $url = Arr::get($image, 'url');

            if (blank($url)) {
                return;
            }

        $items->push(new ProductMediaData(
                source: $this->getName(),
                externalId: Arr::get($image, 'id') ? (string) Arr::get($image, 'id') : null,
                mediaType: 'image',
                title: Arr::get($data, 'title'),
                caption: Arr::get($image, 'caption') ?? Arr::get($data, 'summary'),
                url: $url,
            thumbnailUrl: Arr::get($image, 'thumb') ?? $url,
                attribution: 'Artwork courtesy Nexarda',
                license: 'Nexarda API (non-commercial use)',
                licenseUrl: 'https://www.nexarda.com/api',
                metadata: array_filter([
                    'width' => Arr::get($image, 'width'),
                    'height' => Arr::get($image, 'height'),
                    'type' => Arr::get($image, 'type'),
                ]),
            ));
        });

        $trailers->each(function (array $trailer) use (&$items, $data, $defaultTitle, $defaultThumbnail) {
            $url = Arr::get($trailer, 'url');

            if (blank($url)) {
                return;
            }

            $title = Arr::get($trailer, 'title')
                ?? ($defaultTitle ? $defaultTitle.' Trailer' : 'Trailer');

            $items->push(new ProductMediaData(
                source: $this->getName(),
                externalId: Arr::get($trailer, 'external_id') ? (string) Arr::get($trailer, 'external_id') : null,
                mediaType: 'video',
                title: $title,
                caption: Arr::get($trailer, 'description') ?? Arr::get($data, 'summary') ?? Arr::get($data, 'short_desc') ?? 'Trailer via Nexarda',
                url: $url,
                thumbnailUrl: (function ($thumb) use ($defaultThumbnail) {
                    return is_string($thumb) && $thumb !== '' ? $thumb : $defaultThumbnail;
                })(Arr::get($trailer, 'thumbnail')),
                attribution: 'Trailer courtesy Nexarda',
                license: 'Nexarda API (non-commercial use)',
                licenseUrl: 'https://www.nexarda.com/api',
                metadata: array_filter([
                    'source' => Arr::get($trailer, 'source') ?? null,
                    'publish_date' => Arr::get($trailer, 'date'),
                ]),
            ));
        });

        return $items
            ->filter()
            ->unique(fn (ProductMediaData $item) => ($item->url ?? '').'|'.($item->externalId ?? '').'|'.$item->mediaType)
            ->values();
    }

    protected function cacheKey(?string $slug, ?string $nexardaId): string
    {
        return 'media:nexarda:'.md5((string) json_encode([
            'slug' => $slug,
            'id' => $nexardaId,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(?string $apiKey): array
    {
        if (! filled($apiKey)) {
            return [];
        }

        return [
            'X-Api-Key' => $apiKey,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function requestGameInfo(string $baseUrl, int $timeout, ?string $slug, ?string $nexardaId, ?string $apiKey): ?array
    {
        $client = $this->httpClient($baseUrl, $timeout, $apiKey)->withTelemetry('media.nexarda');

        // If we already have an ID, fetch product details directly
        if (filled($nexardaId)) {
            $resp = $client->get('/product', [
                'type' => 'game',
                'id' => $nexardaId,
            ]);

            if ($resp->failed()) {
                return null;
            }

            $json = $resp->json();
            return is_array($json) && ($json['success'] ?? false) === true ? $json : null;
        }

        // Otherwise, search by slug/name to resolve ID, then fetch product
        $query = trim((string) ($slug ?? ''));
        if ($query === '') {
            return null;
        }

        $search = $client->get('/search', [
            'type' => 'games',
            'q' => $query,
            'output' => 'json',
        ]);

        if ($search->failed()) {
            return null;
        }

        $sjson = $search->json();
        if (! is_array($sjson) || ($sjson['success'] ?? false) !== true) {
            return null;
        }

        $first = collect(Arr::get($sjson, 'results.items', []))
            ->filter(fn ($item) => is_array($item) && Arr::get($item, 'type') === 'Game')
            ->first();

        $resolvedId = $first ? (string) Arr::get($first, 'game_info.id') : null;
        if (! filled($resolvedId)) {
            return null;
        }

        $prod = $client->get('/product', [
            'type' => 'game',
            'id' => $resolvedId,
        ]);

        if ($prod->failed()) {
            return null;
        }

        $pjson = $prod->json();
        return is_array($pjson) && ($pjson['success'] ?? false) === true ? $pjson : null;
    }

    protected function httpClient(string $baseUrl, int $timeout, ?string $apiKey): PendingRequest
    {
        return Http::timeout($timeout)
            ->retry(2, 250)
            ->acceptJson()
            ->withHeaders($this->authHeaders($apiKey))
            ->baseUrl($baseUrl);
    }
}