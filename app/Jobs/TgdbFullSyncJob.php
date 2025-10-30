<?php

namespace App\Jobs;

use App\Services\TheGamesDb\TheGamesDbApiClient;
use App\Services\TheGamesDb\TheGamesDbMirrorRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TgdbFullSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(private readonly array $options = [])
    {
        $this->onQueue('fetch');
    }

    public function backoff(): array
    {
        return [60, 120, 240, 480, 960];
    }

    public function handle(TheGamesDbApiClient $client, TheGamesDbMirrorRepository $mirror): void
    {
        $config = array_merge(config('pricing.providers.thegamesdb.options', []), $this->options);
        $gamesConfig = Arr::get($config, 'games', []);

        $fields = $this->formatFields($config['fields'] ?? null);
        $include = $config['include'] ?? 'boxart,platforms';

        $totalUpserts = 0;

        foreach ($gamesConfig as $entry) {
            $queries = $this->normalizeQueries($entry);

            foreach ($queries as $query) {
                $payload = $client->searchByName($query, true, array_filter([
                    'fields' => $fields,
                    'include' => $include,
                ]));

                if (! $payload) {
                    continue;
                }

                $games = Arr::get($payload, 'data.games', []);
                $artwork = Arr::get($payload, 'include.boxart', []);
                $platformIndex = $this->indexPlatforms(Arr::get($payload, 'include.platforms', []));
                $baseImageUrl = Arr::get($payload, 'data.base_url.original');

                foreach ($games as $game) {
                    if (! is_array($game)) {
                        continue;
                    }

                    $attributes = $this->transformGame($game, $entry, $artwork, $baseImageUrl, $platformIndex);

                    if ($attributes === null) {
                        continue;
                    }

                    // Policy: do not ingest PC titles released before 2015
                    $platformName = strtolower((string) ($attributes['platform'] ?? ''));
                    $release = $attributes['release_date'] ?? null;
                    if ($release instanceof \Illuminate\Support\Carbon) {
                        if ((str_contains($platformName, 'pc') || str_contains($platformName, 'windows')) && $release->lt(\Illuminate\Support\Carbon::create(2015, 1, 1))) {
                            continue;
                        }
                    }

                    $mirror->upsertGame($attributes);
                    $totalUpserts++;
                }
            }
        }

        $mirror->updateFullSyncState(now(), [
            'last_full_sync_count' => $totalUpserts,
        ]);

        Log::info('thegamesdb.mirror.full_sync', [
            'total_upserts' => $totalUpserts,
            'queries_evaluated' => $gamesConfig === [] ? 0 : collect($gamesConfig)->sum(function ($entry) {
                $queries = $entry['queries'] ?? [$entry['query'] ?? $entry['title'] ?? null];

                return collect($queries)
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->count();
            }),
            'options' => $this->options,
        ]);
    }

    public function tags(): array
    {
        return array_values(array_filter([
            'provider:thegamesdb',
            'mirror:full-sync',
            isset($this->options['source']) ? 'source:'.$this->options['source'] : null,
        ]));
    }

    protected function normalizeQueries(array $entry): Collection
    {
        $queries = $entry['queries'] ?? [$entry['query'] ?? $entry['title'] ?? null];

        return collect($queries)
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter()
            ->unique()
            ->values();
    }

    protected function transformGame(array $game, array $context, array $artwork, mixed $baseImageUrl, array $platformIndex): ?array
    {
        $title = (string) ($game['game_title'] ?? $game['name'] ?? ($context['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        $slug = isset($context['slug']) && $context['slug'] !== ''
            ? Str::slug($context['slug'])
            : Str::slug($title);

        $boxArt = $this->resolveBoxArt($game['id'] ?? null, $artwork);

        $imageUrl = $this->buildImageUrl($boxArt['filename'] ?? null, $baseImageUrl);
        $thumbUrl = $this->buildImageUrl($boxArt['thumb'] ?? null, $baseImageUrl) ?: $imageUrl;

        $releaseDate = $this->parseDate($game['release_date'] ?? null);
        $platformId = $this->extractPlatformId($game['platform'] ?? null);
        $platformName = $this->resolvePlatformName($platformId, $platformIndex, $context['platform'] ?? $game['platform'] ?? null);

        return array_filter([
            'external_id' => (int) ($game['id'] ?? 0),
            'title' => $title,
            'slug' => $slug,
            'platform' => $platformName ?? (is_string($game['platform'] ?? null) ? (string) $game['platform'] : (string) ($context['platform'] ?? 'Multi-platform')),
            'category' => (string) ($context['category'] ?? 'Game'),
            'players' => $game['players'] ?? null,
            'genres' => $this->normalizeGenres($game['genres'] ?? []),
            'developer' => $game['developer'] ?? null,
            'publisher' => $game['publisher'] ?? null,
            'release_date' => $releaseDate,
            'image_url' => $imageUrl,
            'thumb_url' => $thumbUrl,
            'metadata' => array_filter([
                'overview' => $game['overview'] ?? null,
                'search_queries' => $context['queries'] ?? null,
                'raw' => $game,
                'platform_id' => $platformId,
                'platform_name' => $platformName,
            ], static fn ($value) => $value !== null && $value !== []),
            'last_synced_at' => now(),
        ], static fn ($value) => $value !== null);
    }

    protected function resolveBoxArt(mixed $gameId, array $artwork): array
    {
        if (! (int) $gameId) {
            return [];
        }

        return collect($artwork)
            ->filter(fn ($item) => is_array($item) && (int) ($item['game_id'] ?? 0) === (int) $gameId)
            ->sortByDesc(fn ($item) => ($item['side'] ?? '') === 'front')
            ->map(fn ($item) => [
                'filename' => $item['filename'] ?? null,
                'thumb' => $item['thumbnail'] ?? $item['thumb'] ?? null,
            ])
            ->first() ?? [];
    }

    protected function buildImageUrl(?string $path, mixed $baseImageUrl): ?string
    {
        if (! $path || ! is_string($baseImageUrl) || trim($baseImageUrl) === '') {
            return null;
        }

        return rtrim($baseImageUrl, '/').'/'.ltrim($path, '/');
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function normalizeGenres(mixed $genres): array
    {
        if (is_array($genres)) {
            return collect($genres)
                ->map(fn ($value) => is_string($value) ? trim($value) : null)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (is_string($genres)) {
            return [trim($genres)];
        }

        return [];
    }

    protected function formatFields(mixed $fields): ?string
    {
        if (is_string($fields)) {
            $trimmed = trim($fields);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_array($fields)) {
            $filtered = collect($fields)
                ->map(fn ($field) => is_string($field) ? trim($field) : null)
                ->filter()
                ->unique()
                ->values();

            return $filtered->isEmpty() ? null : $filtered->join(',');
        }

        return null;
    }

    protected function indexPlatforms(mixed $platforms): array
    {
        if (! is_array($platforms)) {
            return [];
        }

        return collect($platforms)
            ->mapWithKeys(function ($platform, $key) {
                $id = null;
                $name = null;

                if (is_array($platform)) {
                    $id = $platform['id'] ?? $platform['platform_id'] ?? $key;
                    $name = $platform['name'] ?? $platform['platform_name'] ?? null;
                } elseif (is_scalar($platform)) {
                    $id = $key;
                    $name = (string) $platform;
                }

                $id = is_numeric($id) ? (int) $id : null;

                if ($id === null || ! is_string($name) || trim($name) === '') {
                    return [];
                }

                return [$id => $name];
            })
            ->all();
    }

    protected function resolvePlatformName(?int $platformId, array $index, mixed $fallback): ?string
    {
        if ($platformId !== null && isset($index[$platformId])) {
            return $index[$platformId];
        }

        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        return null;
    }

    protected function extractPlatformId(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value['id'] ?? $value['platform_id'] ?? Arr::first($value);
        }

        if (is_numeric($value)) {
            $int = (int) $value;

            return $int >= 0 ? $int : null;
        }

        return null;
    }
}
