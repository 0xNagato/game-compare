<?php

namespace App\Jobs;

use App\Models\GameAlias;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Services\TokenBucketRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnrichGameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $productId)
    {
        $this->onQueue('providers:giantbomb');
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(TokenBucketRateLimiter $limiter): void
    {
        $limits = config('providers.limits');
        $provider = 'giantbomb';
        $limitConfig = Arr::get($limits, $provider, ['max_rps' => 1.0, 'burst' => 1]);

        $result = $limiter->attempt($provider, (float) $limitConfig['max_rps'], (int) $limitConfig['burst']);

        if (! $result['allowed']) {
            $this->release($result['retry_after']);

            return;
        }

        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $config = config('media.providers.giantbomb.options', []);
        $enabled = config('media.providers.giantbomb.enabled', true);
        $apiKey = $config['api_key'] ?? env('GIANTBOMB_API_KEY');

        if (! $enabled || blank($apiKey)) {
            Log::notice('catalogue.enrich_skipped_due_to_missing_giantbomb', [
                'product_id' => $product->id,
            ]);

            return;
        }

        $baseUrl = rtrim($config['base_url'] ?? 'https://www.giantbomb.com/api', '/');
        $userAgent = $config['user_agent'] ?? 'GameCompareBot/1.0 (portfolio use)';

        $searchTerms = $this->buildSearchTerms($product);

        $gamePayload = null;

        foreach ($searchTerms as $term) {
            $gamePayload = $this->searchGiantBomb($baseUrl, $apiKey, $userAgent, $term);

            if ($gamePayload !== null) {
                break;
            }
        }

        if ($gamePayload === null) {
            Log::warning('catalogue.enrich_no_match', [
                'product_id' => $product->id,
            ]);

            return;
        }

        $this->updateProductFromPayload($product, $gamePayload);

        $platforms = Arr::get($gamePayload, 'platforms');
        $genres = Arr::get($gamePayload, 'genres');

        $this->syncPlatforms($product, is_array($platforms) ? $platforms : []);
        $this->syncGenres($product, is_array($genres) ? $genres : []);
        $this->syncAlias($product, $gamePayload);

        FetchProductMediaJob::dispatch($product->id, [
            'query' => $product->name,
            'resource' => 'game',
        ]);
    }

    protected function buildSearchTerms(Product $product): array
    {
        $rawg = Arr::get($product->metadata, 'sources.rawg.rawg_slug');
        $externalRawg = Arr::get($product->external_ids, 'rawg');

        return collect([
            $rawg,
            $externalRawg,
            $product->slug,
            $product->name,
        ])->filter()->unique()->values()->all();
    }

    protected function searchGiantBomb(string $baseUrl, string $apiKey, string $userAgent, string $query): ?array
    {
        $response = Http::timeout(10)
            ->baseUrl($baseUrl)
            ->withHeaders([
                'User-Agent' => $userAgent,
            ])
            ->acceptJson()
            ->get('/search/', [
                'api_key' => $apiKey,
                'format' => 'json',
                'query' => $query,
                'resources' => 'game',
                'limit' => 5,
                'field_list' => implode(',', [
                    'id',
                    'name',
                    'deck',
                    'original_release_date',
                    'expected_release_year',
                    'expected_release_month',
                    'expected_release_day',
                    'site_detail_url',
                    'platforms',
                    'genres',
                    'aliases',
                    'image',
                    'original_game_rating',
                    'similar_games',
                ]),
            ]);

        if ($response->failed()) {
            Log::warning('catalogue.enrich_giantbomb_request_failed', [
                'query' => $query,
                'status' => $response->status(),
            ]);

            return null;
        }

        $results = Arr::get($response->json(), 'results', []);

        if (! is_array($results) || empty($results)) {
            return null;
        }

        $normalizedQuery = Str::lower($query);

        return collect($results)
            ->filter(fn ($result) => is_array($result))
            ->sortByDesc(function (array $item) use ($normalizedQuery) {
                $name = Str::lower($item['name'] ?? '');

                if ($name === $normalizedQuery) {
                    return 3;
                }

                if (Str::contains($name, $normalizedQuery)) {
                    return 2;
                }

                similar_text($normalizedQuery, $name, $percent);

                return $percent / 100;
            })
            ->first();
    }

    protected function updateProductFromPayload(Product $product, array $payload): void
    {
        $metadata = (array) ($product->metadata ?? []);
        $giantbombMetadata = array_filter([
            'id' => $payload['id'] ?? null,
            'site_detail_url' => $payload['site_detail_url'] ?? null,
            'aliases' => $this->explodeAliases($payload['aliases'] ?? null),
            'platforms' => collect($payload['platforms'] ?? [])->pluck('name')->filter()->values()->all(),
            'genres' => collect($payload['genres'] ?? [])->pluck('name')->filter()->values()->all(),
            'image' => Arr::get($payload, 'image.original_url'),
            'fetched_at' => now()->toIso8601String(),
        ]);

        $metadata['sources']['giantbomb'] = $giantbombMetadata;

        $externalIds = (array) ($product->external_ids ?? []);

        if (isset($payload['id'])) {
            $externalIds['giantbomb'] = (string) $payload['id'];
        }

        $synopsis = $payload['deck'] ?? $product->synopsis;

        $releaseDate = $product->release_date;
        $originalRelease = $payload['original_release_date'] ?? null;

        if (! $releaseDate && $originalRelease) {
            try {
                $releaseDate = CarbonImmutable::parse($originalRelease)->toDateString();
            } catch (\Throwable) {
                $releaseDate = $product->release_date;
            }
        }

        if (! $releaseDate) {
            $expectedYear = $payload['expected_release_year'] ?? null;
            $expectedMonth = $payload['expected_release_month'] ?? 1;
            $expectedDay = $payload['expected_release_day'] ?? 1;

            if ($expectedYear) {
                $releaseDate = sprintf('%04d-%02d-%02d', $expectedYear, $expectedMonth, $expectedDay);
            }
        }

        $platformFamily = $product->primary_platform_family;
        $platforms = collect($payload['platforms'] ?? [])
            ->pluck('name')
            ->filter()
            ->values();

        if ($platformFamily === null && $platforms->isNotEmpty()) {
            $platformFamily = $this->determinePlatformFamily($platforms->first());
        }

        $product->fill([
            'synopsis' => $synopsis,
            'metadata' => $metadata,
            'external_ids' => $externalIds,
            'release_date' => $releaseDate,
            'primary_platform_family' => $platformFamily,
            'platform' => $platforms->first() ?? $product->platform,
        ]);

        if ($product->rating <= 0) {
            $product->rating = $this->estimateRating($payload, $product->rating);
        }

        $product->save();
    }

    protected function syncPlatforms(Product $product, array $platforms): void
    {
        collect($platforms)
            ->filter(fn ($platform) => is_array($platform))
            ->each(function (array $platform) use ($product): void {
                $name = $platform['name'] ?? null;

                if (! is_string($name) || $name === '') {
                    return;
                }

                $code = Str::slug($name);
                $family = $this->determinePlatformFamily($name) ?? 'pc';

                $model = Platform::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'family' => $family,
                        'metadata' => array_filter([
                            'giantbomb_id' => $platform['id'] ?? null,
                        ]),
                    ]
                );

                $product->platforms()->syncWithoutDetaching([$model->id]);
            });
    }

    protected function syncGenres(Product $product, array $genres): void
    {
        collect($genres)
            ->filter(fn ($genre) => is_array($genre))
            ->each(function (array $genre) use ($product): void {
                $name = $genre['name'] ?? null;

                if (! is_string($name) || $name === '') {
                    return;
                }

                $slug = Str::slug($name);

                $model = Genre::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name]
                );

                $product->genres()->syncWithoutDetaching([$model->id]);
            });
    }

    protected function syncAlias(Product $product, array $payload): void
    {
        if (! isset($payload['id'])) {
            return;
        }

        GameAlias::query()->updateOrCreate(
            [
                'provider' => 'giantbomb',
                'provider_game_id' => (string) $payload['id'],
            ],
            [
                'product_id' => $product->id,
                'alias_title' => $payload['name'] ?? $product->name,
            ]
        );

        foreach ($this->explodeAliases($payload['aliases'] ?? null) as $alias) {
            GameAlias::query()->updateOrCreate(
                [
                    'provider' => 'alias',
                    'provider_game_id' => sha1('alias:'.$alias),
                ],
                [
                    'product_id' => $product->id,
                    'alias_title' => $alias,
                ]
            );
        }
    }

    protected function explodeAliases(?string $aliases): array
    {
        if (! is_string($aliases) || $aliases === '') {
            return [];
        }

        return collect(preg_split("/[\r\n]+/", $aliases) ?: [])
            ->map(fn (string $alias) => trim($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function determinePlatformFamily(string $platform): string
    {
        $normalized = Str::lower($platform);

        return match (true) {
            str_contains($normalized, 'playstation') || str_contains($normalized, 'ps') => 'playstation',
            str_contains($normalized, 'xbox') => 'xbox',
            str_contains($normalized, 'switch') || str_contains($normalized, 'nintendo') => 'nintendo',
            str_contains($normalized, 'mobile') || str_contains($normalized, 'android') || str_contains($normalized, 'ios') => 'mobile',
            str_contains($normalized, 'sega') => 'sega',
            str_contains($normalized, 'arcade') => 'arcade',
            str_contains($normalized, 'commodore') || str_contains($normalized, 'atari') || str_contains($normalized, 'retro') => 'retro',
            default => 'pc',
        };
    }

    protected function estimateRating(array $payload, int $current): int
    {
        $ratings = collect($payload['original_game_rating'] ?? [])
            ->pluck('name')
            ->filter()
            ->map(fn (string $name) => Str::upper($name))
            ->values();

        if ($ratings->isEmpty()) {
            return $current;
        }

        $mapping = [
            'EC' => 70,
            'E' => 75,
            'E10+' => 78,
            'T' => 82,
            'M' => 88,
            'AO' => 65,
        ];

        $score = $ratings
            ->map(fn (string $rating) => $mapping[$rating] ?? null)
            ->filter()
            ->avg();

        return $score ? (int) round($score) : $current;
    }
}
