<?php

namespace App\Services\PriceIngestion\Providers;

use App\Models\TheGamesDbGame;
use App\Services\TheGamesDb\TheGamesDbMirrorRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TheGamesDbProvider
{
    private const PROVIDER_KEY = 'thegamesdb';

    public function __construct(
        private readonly TheGamesDbMirrorRepository $mirror,
        private readonly int $defaultDailyCap = 6000,
    ) {}

    /**
     * @param  array<string, mixed>  $options
    * @return array{
    *   results: array<int, array{
    *     game: array{title:string, slug:string, platform:string, category:string, external_id:string, metadata: array<string, mixed>},
    *     deals: array<int, array<string, mixed>>
    *   }>,
    *   meta: array<string, mixed>
    * }
     */
    public function fetchDeals(array $options = []): array
    {
        $requests = $this->normalizeRequests($options);
        $dailyCap = max(1, (int) ($options['daily_cap']
            ?? config('pricing.providers.thegamesdb.options.daily_cap')
            ?? $this->defaultDailyCap));

        if ($requests->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => self::PROVIDER_KEY,
                    'generated_at' => now()->toIso8601String(),
                    'requested' => 0,
                    'processed_requests' => 0,
                    'result_count' => 0,
                    'message' => 'No TheGamesDB game queries configured.',
                    'stub' => false,
                    'rate_limit' => [
                        'daily_limit' => $dailyCap,
                        'daily_used' => null,
                        'daily_remaining' => null,
                        'per_minute_limit' => null,
                        'per_minute_used' => null,
                        'per_minute_remaining' => null,
                        'blocked_daily' => false,
                        'blocked_per_minute' => false,
                        'retry_after_seconds' => null,
                    ],
                ],
            ];
        }

        $results = [];

        foreach ($requests as $request) {
            $lookupResults = $this->lookupMirror($request, $options);
            $results = array_merge($results, $lookupResults);
        }

        $results = collect($results)
            ->filter(fn ($item) => is_array($item) && isset($item['game']['slug']))
            ->unique(fn ($item) => $item['game']['slug'])
            ->values()
            ->all();

        $requestedCount = $requests->count();
        $regions = array_values(array_filter((array) ($options['regions'] ?? config('pricing.providers.thegamesdb.regions', ['GLOBAL']))));
        $platformSummary = $this->summarizePlatforms($requests, $results);

        return [
            'results' => $results,
            'meta' => [
                'provider' => self::PROVIDER_KEY,
                'generated_at' => now()->toIso8601String(),
                'kind' => 'metadata_catalog',
                'regions' => $regions,
                'platforms' => $platformSummary,
                'requested' => $requestedCount,
                'processed_requests' => $requestedCount,
                'result_count' => count($results),
                'skipped_due_to_rate_limit' => 0,
                'failed_requests' => 0,
                'errors' => [],
                'rate_limit' => [
                    'daily_limit' => $dailyCap,
                    'daily_used' => null,
                    'daily_remaining' => null,
                    'per_minute_limit' => null,
                    'per_minute_used' => null,
                    'per_minute_remaining' => null,
                    'blocked_daily' => false,
                    'blocked_per_minute' => false,
                    'retry_after_seconds' => null,
                ],
                'stub' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function lookupMirror(array $request, array $options): array
    {
        $platforms = $request['platforms'] ?? $options['platforms'] ?? null;

        $limit = $this->normalizeLimit($request['limit'] ?? null);

        $games = $this->mirror->search($request['query'], [
            'platforms' => $platforms,
        ], $limit);

        if ($games->isEmpty()) {
            return [];
        }

        $items = $games
            ->map(fn (TheGamesDbGame $game) => $this->mapMirrorGameToResult($game, $request))
            // Keep only arrays to satisfy static analysis
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();

        /** @var array<int, array<string, mixed>> $items */
        return $items;
    }

    /**
     * @return array{
     *   game: array{title:string, slug:string, platform:string, category:string, external_id:string, metadata: array<string, mixed>},
     *   deals: array<0, never>
     * }|null
     */
    protected function mapMirrorGameToResult(TheGamesDbGame $game, array $request): ?array
    {
        $rawTitle = $game->title ?? $request['title'] ?? $request['query'];
        $title = is_string($rawTitle) ? trim($rawTitle) : '';

        if ($title === '') {
            return null;
        }

        $preferredSlug = $request['slug'] ?? '';
        $slug = trim($preferredSlug !== '' ? $preferredSlug : ($game->slug ?? Str::slug($title)));

        if ($slug === '') {
            return null;
        }

        $releaseDate = $game->release_date;
        $releaseDateStr = $releaseDate instanceof \DateTimeInterface
            ? $releaseDate->format('Y-m-d')
            : null;

        $metadata = array_filter(array_merge(
            is_array($request['metadata']) ? $request['metadata'] : [],
            [
                'overview' => Arr::get($game->metadata, 'overview'),
                'players' => $game->players,
                'genres' => $this->normalizeGenres($game->genres),
                'developer' => $game->developer,
                'publisher' => $game->publisher,
                'release_date' => $releaseDateStr,
                'box_art' => $this->buildBoxArtMetadata($game),
            ],
        ), static fn ($value) => $value !== null && $value !== []);

        return [
            'game' => array_filter([
                'title' => $title,
                'slug' => $slug,
                'platform' => $request['platform'] !== ''
                    ? $request['platform']
                    : ($game->platform ?? 'Multi-platform'),
                'category' => $request['category'] !== '' ? $request['category'] : 'Game',
                'external_id' => (string) $game->external_id,
                'metadata' => $metadata,
            ]),
            'deals' => [],
        ];
    }

    /**
     * @return array{image:string, thumb:string}|null
     */
    protected function buildBoxArtMetadata(TheGamesDbGame $game): ?array
    {
        if (! $game->image_url) {
            return null;
        }

        return array_filter([
            'image' => $game->image_url,
            'thumb' => $game->thumb_url,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeGenres(mixed $genres): array
    {
        if (is_array($genres)) {
            return collect($genres)
                ->map(fn ($genre) => is_string($genre) ? trim($genre) : null)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (is_string($genres) && $genres !== '') {
            return [trim($genres)];
        }

        return [];
    }

    protected function makeSlug(array $entry, string $fallback): string
    {
        $slug = $entry['slug'] ?? $entry['product_slug'] ?? null;

        if (is_string($slug) && trim($slug) !== '') {
            return Str::slug($slug);
        }

        return Str::slug($fallback);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, array{
     *   query:string, title:string, slug:string, platform:string, category:string, limit:int|null, platforms:mixed, metadata: array<string, mixed>
     * }>
     */
    protected function normalizeRequests(array $options): Collection
    {
        $games = Arr::get($options, 'games', []);

        return collect($games)
            ->flatMap(function ($entry) {
                if (! is_array($entry)) {
                    return [];
                }

                $queries = $entry['queries'] ?? [$entry['query'] ?? $entry['title'] ?? null];

                return collect($queries)
                    ->map(function ($query) use ($entry) {
                        $name = is_string($query) ? trim($query) : null;

                        if ($name === null || $name === '') {
                            return null;
                        }

                        $slug = $this->makeSlug($entry, $name);

                        if ($slug === '') {
                            return null;
                        }

                        return [
                            'query' => $name,
                            'title' => (string) ($entry['title'] ?? $name),
                            'slug' => $slug,
                            'platform' => (string) ($entry['platform'] ?? 'Multi-platform'),
                            'category' => (string) ($entry['category'] ?? 'Game'),
                            'limit' => $this->normalizeLimit($entry['limit'] ?? null),
                            'platforms' => $entry['platforms'] ?? null,
                            'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
                        ];
                    })
            ->filter();
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{platform:string|null}>  $requests
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, string>
     */
    protected function summarizePlatforms(Collection $requests, array $results): array
    {
        $fromRequests = $requests
            ->pluck('platform')
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter()
            ->all();

        $fromResults = collect($results)
            ->map(fn ($result) => Arr::get($result, 'game.platform'))
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter()
            ->all();

        return collect(array_merge($fromRequests, $fromResults))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeLimit(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
