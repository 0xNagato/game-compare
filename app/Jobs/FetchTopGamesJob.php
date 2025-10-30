<?php

namespace App\Jobs;

use App\Models\GameAlias;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Services\Catalogue\CatalogueAggregator;
use App\Services\Catalogue\DTOs\TrendingGameData;
use App\Services\TokenBucketRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FetchTopGamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('providers:rawg');
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(TokenBucketRateLimiter $limiter, CatalogueAggregator $aggregator, DatabaseManager $database): void
    {
        $limits = config('providers.limits');
        $rateLimitedProvider = 'rawg';
        $limitConfig = Arr::get($limits, $rateLimitedProvider, ['max_rps' => 1.0, 'burst' => 1]);
        $pipeline = 'catalogue.aggregate';

        $sourcesConfig = config('catalogue.sources', []);
        $rawgEnabled = (bool) ($sourcesConfig['rawg']['enabled'] ?? true);

        // If RAWG is enabled but we're rate-limited, temporarily disable RAWG for this pass
        if ($rawgEnabled) {
            $result = $limiter->attempt($rateLimitedProvider, (float) $limitConfig['max_rps'], (int) $limitConfig['burst']);
            if (! $result['allowed']) {
                // Only release when RAWG is the only enabled source; otherwise continue with other sources
                $otherSourcesEnabled = collect($sourcesConfig)
                    ->except(['rawg'])
                    ->filter(fn ($s) => (bool) (($s['enabled'] ?? true) === true))
                    ->isNotEmpty();

                if (! $otherSourcesEnabled) {
                    $this->release($result['retry_after']);
                    return;
                }

                // Disable RAWG for this run and proceed with remaining sources
                config(['catalogue.sources.rawg.enabled' => false]);
            }
        }

        $limit = (int) config('catalogue.trending_seed_limit', 20);
        $windowDays = (int) config('catalogue.trending_seed_window_days', 180);

        $aggregate = $aggregator->aggregate($limit, $windowDays);

        $snapshot = \App\Models\DatasetSnapshot::create([
            'kind' => 'seed:trending_games',
            'provider' => $pipeline,
            'status' => 'running',
            'started_at' => now(),
            'context' => [
                'limit' => $limit,
                'queue' => $this->queue,
                'sources' => $aggregate->sources,
                'rate_limited_provider' => $rateLimitedProvider,
            ],
        ]);

        try {
            $games = $aggregate->entries;
        } catch (Throwable $exception) {
            Log::error('catalogue.fetch_top_games_failed', [
                'pipeline' => $pipeline,
                'error' => $exception->getMessage(),
                'snapshot_id' => $snapshot->id,
            ]);

            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($games->isEmpty()) {
            Log::warning('catalogue.fetch_top_games_empty', ['pipeline' => $pipeline]);

            $snapshot->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'row_count' => 0,
            ]);

            return;
        }

        $productIds = [];

        try {
            $database->transaction(function () use (&$productIds, $games): void {
                /** @var Collection<int, TrendingGameData> $games */
                $games = $games->values();
                $total = max(1, $games->count());

                $games->each(function (TrendingGameData $game, int $index) use (&$productIds, $total): void {
                    $product = $this->persistGame($game, $index, $total);

                    if ($product) {
                        $productIds[$product->id] = $product->name;
                    }
                });
            });

            collect($productIds)
                ->each(function (string $name, int $productId): void {
                    EnrichGameJob::dispatch($productId);

                    if ($this->pricingProviderEnabled('pricecharting')) {
                        FetchOffersJob::dispatch($productId);
                    }

                    BuildSeriesJob::dispatch($productId);
                    if (! config('catalogue.skip_verify_links', false)) {
                        VerifyLinksJob::dispatch($productId);
                    }
                    FetchProductMediaJob::dispatch($productId, ['query' => $name]);
                });

            $snapshot->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'row_count' => $games->count(),
                'context' => array_merge($snapshot->context ?? [], [
                    'dispatched_product_ids' => array_keys($productIds),
                ]),
            ]);

            Log::info('catalogue.fetch_top_games_completed', [
                'pipeline' => $pipeline,
                'count' => count($productIds),
                'snapshot_id' => $snapshot->id,
            ]);
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            Log::error('catalogue.fetch_top_games_pipeline_failed', [
                'pipeline' => $pipeline,
                'error' => $exception->getMessage(),
                'snapshot_id' => $snapshot->id,
            ]);

            throw $exception;
        }
    }

    protected function persistGame(TrendingGameData $game, int $index, int $total): ?Product
    {
        $slug = Str::slug($game->slug ?: $game->name);

        if ($slug === '') {
            return null;
        }

        $releaseDate = $game->releasedAt?->toDateString();
        $platformName = $game->primaryPlatform();
        $platformFamily = $this->determinePlatformFamily($platformName);
        $resolvedFamily = $platformFamily ?? 'unknown';

        // Policy: do not ingest additional PC titles released before 2015
        if ($resolvedFamily === 'pc' && $game->releasedAt !== null) {
            try {
                if ($game->releasedAt->lt(\Carbon\CarbonImmutable::create(2015, 1, 1))) {
                    return null;
                }
            } catch (\Throwable) {
                // ignore parse errors
            }
        }
        $computedUid = $this->computeUid($game->name, $releaseDate, $resolvedFamily);

        // Prefer matching existing product by UID to avoid uniqueness violations
        $product = Product::query()->where('uid', $computedUid)->first();
        if (! $product) {
            $product = Product::query()->where('slug', $slug)->first();
        }
        if (! $product) {
            $product = new Product();
            $product->slug = $slug;
        }

        $metadata = (array) ($product->metadata ?? []);
        $sourceKey = $game->source();
        $gameMetadata = $game->metadata();
        $sourceMetadata = array_merge($gameMetadata, [
            'popularity_rank' => $index + 1,
            'fetched_at' => now()->toIso8601String(),
        ]);

        $existingSources = (array) ($metadata['sources'] ?? []);
        $existingSources[$sourceKey] = $sourceMetadata;
        $metadata['sources'] = $existingSources;
        $metadata['platforms'] = array_values(array_unique(array_merge($metadata['platforms'] ?? [], $game->platforms)));
        $metadata['genres'] = array_values(array_unique(array_merge($metadata['genres'] ?? [], $game->genres)));

        $externalIds = (array) ($product->external_ids ?? []);
        $rawgId = $gameMetadata['rawg_id'] ?? null;

        if ($rawgId !== null) {
            $externalIds['rawg'] = (string) $rawgId;
        }

        $tgdbId = $gameMetadata['thegamesdb_id'] ?? null;

        if ($tgdbId !== null) {
            $externalIds['thegamesdb'] = (string) $tgdbId;
        }

        $giantbombId = $gameMetadata['giantbomb_id'] ?? null;

        if ($giantbombId !== null) {
            $externalIds['giantbomb'] = (string) $giantbombId;
        }

        $nexardaId = $gameMetadata['nexarda_id'] ?? null;

        if ($nexardaId !== null) {
            $externalIds['nexarda'] = (string) $nexardaId;
        }

        $resolvedPlatform = $platformName !== 'Unknown' ? $platformName : ($product->platform ?? 'Unknown');
        $resolvedFamily = $platformFamily ?? ($product->primary_platform_family ?? null);

        $product->fill([
            'name' => $game->name,
            'platform' => $resolvedPlatform,
            'category' => 'Game',
            'release_date' => $releaseDate,
            'metadata' => $metadata,
            'uid' => $computedUid,
            'primary_platform_family' => $resolvedFamily,
            'popularity_score' => $this->calculatePopularityScore($game, $index, $total),
            'rating' => $this->calculateRating($game),
            'freshness_score' => $this->calculateFreshnessScore($game->releasedAt),
            'external_ids' => $externalIds,
        ]);

        $product->save();

        $this->syncPlatforms($product, $game->platforms);
        $this->syncGenres($product, $game->genres);
        $this->syncAlias($product, $game);

        return $product;
    }

    protected function syncPlatforms(Product $product, array $platforms): void
    {
        collect($platforms)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->each(function (string $name) use ($product): void {
                $code = Str::slug($name);
                $family = $this->determinePlatformFamily($name) ?? 'pc';

                $platform = Platform::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'family' => $family,
                    ]
                );

                $product->platforms()->syncWithoutDetaching([$platform->id]);
            });
    }

    protected function syncGenres(Product $product, array $genres): void
    {
        collect($genres)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->each(function (string $name) use ($product): void {
                $slug = Str::slug($name);

                $genre = Genre::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name]
                );

                $product->genres()->syncWithoutDetaching([$genre->id]);
            });
    }

    protected function syncAlias(Product $product, TrendingGameData $game): void
    {
        $provider = $game->source();
        $metadata = $game->metadata();
        $providerIdentifier = match ($provider) {
            'rawg' => $metadata['rawg_slug'] ?? $game->slug,
            'thegamesdb_mirror' => $metadata['thegamesdb_id'] ?? $game->slug,
            'giantbomb' => $metadata['giantbomb_id'] ?? $metadata['giantbomb_url'] ?? $game->slug,
            'nexarda' => $metadata['nexarda_slug'] ?? $metadata['nexarda_id'] ?? $game->slug,
            default => $game->slug,
        };

        if ($providerIdentifier === null || $providerIdentifier === '') {
            return;
        }

        $aliasProvider = match ($provider) {
            'thegamesdb_mirror' => 'thegamesdb',
            default => $provider,
        };

        GameAlias::query()->updateOrCreate(
            [
                'provider' => $aliasProvider,
                'provider_game_id' => (string) $providerIdentifier,
            ],
            [
                'product_id' => $product->id,
                'alias_title' => $game->name,
            ]
        );
    }

    protected function computeUid(string $title, ?string $releaseDate, ?string $platformFamily): string
    {
        $normalized = Str::lower($title).'|'.($releaseDate ?? 'unknown').'|'.($platformFamily ?? 'unknown');

        return hash('sha256', $normalized);
    }

    protected function determinePlatformFamily(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $normalized = Str::lower($platform);

        return match (true) {
            str_contains($normalized, 'playstation') || str_contains($normalized, 'ps') => 'playstation',
            str_contains($normalized, 'xbox') => 'xbox',
            str_contains($normalized, 'switch') || str_contains($normalized, 'nintendo') || str_contains($normalized, 'wii') => 'nintendo',
            str_contains($normalized, 'pc') || str_contains($normalized, 'windows') || str_contains($normalized, 'steam') => 'pc',
            str_contains($normalized, 'mobile') || str_contains($normalized, 'android') || str_contains($normalized, 'ios') => 'mobile',
            str_contains($normalized, 'sega') => 'sega',
            str_contains($normalized, 'arcade') => 'arcade',
            str_contains($normalized, 'commodore') || str_contains($normalized, 'atari') || str_contains($normalized, 'retro') => 'retro',
            default => null,
        };
    }

    protected function calculatePopularityScore(TrendingGameData $game, int $index, int $total): float
    {
        $rankScore = 1 - ($index / max(1, $total));
        $ratingScore = $game->metacritic !== null
            ? min(1, max(0, $game->metacritic / 100))
            : ($game->rating !== null ? min(1, max(0, $game->rating / 5)) : 0.5);

        $score = (0.7 * $rankScore) + (0.3 * $ratingScore);

        return round(max(0.0, min(1.0, $score)), 3);
    }

    protected function calculateRating(TrendingGameData $game): int
    {
        if ($game->metacritic !== null) {
            return (int) max(0, min(100, $game->metacritic));
        }

        if ($game->rating !== null) {
            return (int) max(0, min(100, round($game->rating * 20)));
        }

        return (int) ($game->rating ? round($game->rating * 20) : 0);
    }

    protected function calculateFreshnessScore(?CarbonImmutable $releasedAt): float
    {
        if ($releasedAt === null) {
            return 0.5;
        }

        if ($releasedAt->isFuture()) {
            return 1.0;
        }

        $days = $releasedAt->diffInDays(CarbonImmutable::now());
        $score = 1 - min($days, 730) / 730; // taper over two years

        return round(max(0.1, min(1.0, $score)), 3);
    }

    protected function pricingProviderEnabled(string $provider): bool
    {
        $key = 'pricing.providers.'.$provider.'.enabled';
        $val = config($key);
        return $val === null ? true : (bool) $val;
    }
}