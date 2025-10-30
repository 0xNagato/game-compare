<?php

namespace App\Jobs;

use App\Models\TheGamesDbGame;
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

class TgdbSweepShardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(private readonly array $options = [])
    {
        $this->onQueue('fetch');
    }

    public function backoff(): array
    {
        return [120, 240, 480, 960, 1800];
    }

    public function handle(TheGamesDbApiClient $client, TheGamesDbMirrorRepository $mirror): void
    {
        $config = config('pricing.providers.thegamesdb.options', []);
        $sweepDefaults = [
            'window_days' => 14,
            'daily_budget' => 1800,
            'chunk_size' => 25,
        ];
        $sweepConfig = array_merge($sweepDefaults, is_array($config['sweep'] ?? null) ? $config['sweep'] : []);

        $windowDays = max(1, (int) ($this->options['window_days'] ?? $sweepConfig['window_days']));
        $totalShards = max(1, (int) ($this->options['total_shards'] ?? $windowDays));
        $shard = (int) ($this->options['shard'] ?? (now()->dayOfYear % $totalShards));
        $shard = $shard % $totalShards;
        $chunkSize = max(1, (int) ($this->options['chunk_size'] ?? $sweepConfig['chunk_size']));
        $budget = max(1, (int) ($this->options['daily_budget'] ?? $sweepConfig['daily_budget']));
        $fields = $this->formatFields($config['fields'] ?? null);
        $include = $this->normalizeIncludeList($config['include'] ?? 'boxart', ['platforms']);
        $discoveryConfig = $this->buildDiscoveryConfig($config, $fields, $include);

        $callsUsed = 0;
        $upserts = 0;
        $queried = 0;
        $discoverySummary = null;

        TheGamesDbGame::query()
            ->select(['id', 'external_id'])
            ->whereNotNull('external_id')
            ->whereRaw('MOD(external_id, ?) = ?', [$totalShards, $shard])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $games) use (&$callsUsed, $budget, $client, $fields, $include, &$upserts, $mirror, &$queried) {
                if ($callsUsed >= $budget) {
                    return false;
                }

                $ids = $games->pluck('external_id')
                    ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
                    ->filter()
                    ->values();

                if ($ids->isEmpty()) {
                    return true;
                }

                $callsUsed++;
                $queried += $ids->count();

                $payload = $client->byIds($ids->all(), true, array_filter([
                    'fields' => $fields,
                    'include' => $include,
                ]), 'games.sweep');

                if (! $payload) {
                    return true;
                }

                $gamesData = Arr::get($payload, 'data.games', []);
                $artwork = Arr::get($payload, 'include.boxart', []);
                $platformIndex = $this->indexPlatforms(Arr::get($payload, 'include.platforms', []));
                $baseImageUrl = Arr::get($payload, 'data.base_url.original');

                foreach ($gamesData as $game) {
                    if (! is_array($game)) {
                        continue;
                    }

                    $attributes = $this->transformGame($game, $artwork, $baseImageUrl, $platformIndex);

                    if ($attributes === null) {
                        continue;
                    }

                    $mirror->upsertGame($attributes);
                    $upserts++;
                }

                return $callsUsed < $budget;
            });

        if ($discoveryConfig['enabled'] && $callsUsed < $budget) {
            $discoveryBudget = $budget - $callsUsed;
            if ($discoveryBudget > 0) {
                $discoverySummary = $this->runDiscovery($client, $mirror, $discoveryConfig, $discoveryBudget);
                $callsUsed += $discoverySummary['requests'];
                $upserts += $discoverySummary['upserts'];
                $queried += $discoverySummary['queried'];
            }
        }

        $mirror->updateSweepState(now(), [
            'last_sweep_shard' => $shard,
            'total_shards' => $totalShards,
            'window_days' => $windowDays,
            'calls_used' => $callsUsed,
            'budget' => $budget,
            'upserts' => $upserts,
            'queried_ids' => $queried,
            'discovery' => $discoverySummary,
        ]);

        Log::info('thegamesdb.mirror.sweep', [
            'shard' => $shard,
            'total_shards' => $totalShards,
            'window_days' => $windowDays,
            'calls_used' => $callsUsed,
            'budget' => $budget,
            'upserts' => $upserts,
            'queried_ids' => $queried,
            'discovery' => $discoverySummary,
            'options' => $this->options,
        ]);
    }

    protected function buildDiscoveryConfig(array $config, ?string $defaultFields, ?string $defaultInclude): array
    {
        $defaults = [
            'enabled' => false,
            'start_id' => 1,
            'batch_size' => 200,
            'max_id' => null,
            'use_private_key' => true,
            'fields' => $defaultFields,
            'include' => $defaultInclude,
            'requests_per_run' => 1,
        ];

        $discovery = is_array($config['discovery'] ?? null) ? $config['discovery'] : [];

        $merged = array_merge($defaults, $discovery);
        $merged['batch_size'] = max(1, (int) $merged['batch_size']);
        $merged['start_id'] = max(1, (int) $merged['start_id']);
        $merged['requests_per_run'] = max(1, (int) $merged['requests_per_run']);

        if ($merged['fields']) {
            $merged['fields'] = $this->formatFields($merged['fields']);
        }

        if ($merged['include']) {
            $merged['include'] = $this->normalizeIncludeList($merged['include'], ['platforms']);
        }

        return $merged;
    }

    protected function transformGame(array $game, array $artwork, mixed $baseImageUrl, array $platformIndex): ?array
    {
        $title = (string) ($game['game_title'] ?? $game['name'] ?? '');

        if ($title === '') {
            return null;
        }

        $slug = Str::slug((string) ($game['slug'] ?? $title));
        $boxArt = $this->resolveBoxArt($game['id'] ?? null, $artwork);
        $imageUrl = $this->buildImageUrl($boxArt['filename'] ?? null, $baseImageUrl);
        $thumbUrl = $this->buildImageUrl($boxArt['thumb'] ?? null, $baseImageUrl) ?: $imageUrl;
        $platformId = $this->extractPlatformId($game['platform'] ?? null);
        $platformName = $this->resolvePlatformName($platformId, $platformIndex, $game['platform'] ?? null);

        return array_filter([
            'external_id' => (int) ($game['id'] ?? 0),
            'title' => $title,
            'slug' => $slug,
            'platform' => $platformName ?? (is_string($game['platform'] ?? null) ? (string) $game['platform'] : null),
            'category' => $game['category'] ?? 'Game',
            'players' => $game['players'] ?? null,
            'genres' => $this->normalizeGenres($game['genres'] ?? []),
            'developer' => $game['developer'] ?? null,
            'publisher' => $game['publisher'] ?? null,
            'release_date' => $this->parseDate($game['release_date'] ?? null),
            'image_url' => $imageUrl,
            'thumb_url' => $thumbUrl,
            'metadata' => array_filter([
                'overview' => $game['overview'] ?? null,
                'raw' => $game,
                'platform_id' => $platformId,
                'platform_name' => $platformName,
            ]),
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
        } catch (\Throwable) {
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

    protected function runDiscovery(TheGamesDbApiClient $client, TheGamesDbMirrorRepository $mirror, array $config, int $remainingBudget): array
    {
        $maxRequests = min($remainingBudget, $config['requests_per_run']);

        if ($maxRequests < 1) {
            return [
                'requests' => 0,
                'upserts' => 0,
                'queried' => 0,
                'range' => null,
            ];
        }

        $state = $mirror->latestSyncState();
        $metadata = $state->metadata ?? [];
        $discovery = is_array($metadata['discovery'] ?? null) ? $metadata['discovery'] : [];

        $nextStartId = max($config['start_id'], (int) ($discovery['next_start_id'] ?? $config['start_id']));
        $maxId = isset($config['max_id']) ? (int) $config['max_id'] : null;

        $requests = 0;
        $totalUpserts = 0;
        $totalQueried = 0;
        $lastRange = null;

        while ($requests < $maxRequests) {
            if ($maxId !== null && $nextStartId > $maxId) {
                break;
            }

            $rangeEnd = $nextStartId + ($config['batch_size'] - 1);

            if ($maxId !== null) {
                $rangeEnd = min($rangeEnd, $maxId);
            }

            if ($rangeEnd < $nextStartId) {
                break;
            }

            $ids = range($nextStartId, $rangeEnd);

            if ($ids === []) {
                break;
            }

            $params = array_filter([
                'fields' => $config['fields'] ?? null,
                'include' => $config['include'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            $payload = $client->byIds($ids, (bool) $config['use_private_key'], $params, 'games.discovery');

            $games = Arr::get($payload, 'data.games', []);
            $artwork = Arr::get($payload, 'include.boxart', []);
            $platformIndex = $this->indexPlatforms(Arr::get($payload, 'include.platforms', []));
            $baseImageUrl = Arr::get($payload, 'data.base_url.original');

            $upserts = 0;

            foreach ($games as $game) {
                if (! is_array($game)) {
                    continue;
                }

                $attributes = $this->transformGame($game, $artwork, $baseImageUrl, $platformIndex);

                if ($attributes === null) {
                    continue;
                }

                $mirror->upsertGame($attributes);
                $upserts++;
            }

            $mirror->updateDiscoveryState(now(), [
                'next_start_id' => $rangeEnd + 1,
                'last_requested_count' => count($ids),
                'last_upsert_count' => $upserts,
                'last_requested_range' => [$nextStartId, $rangeEnd],
            ]);

            $requests++;
            $totalUpserts += $upserts;
            $totalQueried += count($ids);
            $lastRange = [$nextStartId, $rangeEnd];
            $nextStartId = $rangeEnd + 1;

            if ($maxId !== null && $nextStartId > $maxId) {
                break;
            }
        }

        return [
            'requests' => $requests,
            'upserts' => $totalUpserts,
            'queried' => $totalQueried,
            'range' => $lastRange,
        ];
    }

    protected function normalizeIncludeList(mixed $include, array $required = []): ?string
    {
        $items = collect();

        if (is_string($include)) {
            $items = collect(explode(',', $include));
        } elseif (is_array($include)) {
            $items = collect($include);
        }

        $items = $items
            ->map(fn ($value) => is_string($value) ? strtolower(trim($value)) : null)
            ->filter();

        foreach ($required as $value) {
            if (is_string($value)) {
                $items->push(strtolower(trim($value)));
            }
        }

        $unique = $items->filter()->unique()->values();

        return $unique->isEmpty() ? null : $unique->join(',');
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

    public function tags(): array
    {
        return array_values(array_filter([
            'provider:thegamesdb',
            'mirror:sweep',
            isset($this->options['source']) ? 'source:'.$this->options['source'] : null,
        ]));
    }
}