<?php

namespace App\Services\TheGamesDb;

use App\Models\TheGamesDbGame;
use App\Models\VendorSyncState;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TheGamesDbMirrorRepository
{
    private const PROVIDER_KEY = 'thegamesdb';

    public function upsertGame(array $attributes): TheGamesDbGame
    {
        $now = now();

        $payload = array_merge([
            'title' => $attributes['title'] ?? $attributes['game_title'] ?? null,
            'slug' => $attributes['slug'] ?? null,
            'platform' => $attributes['platform'] ?? null,
            'category' => $attributes['category'] ?? null,
            'players' => $attributes['players'] ?? null,
            'genres' => $attributes['genres'] ?? null,
            'developer' => $attributes['developer'] ?? null,
            'publisher' => $attributes['publisher'] ?? null,
            'release_date' => $attributes['release_date'] ?? null,
            'image_url' => $attributes['image_url'] ?? null,
            'thumb_url' => $attributes['thumb_url'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ], ['last_synced_at' => $attributes['last_synced_at'] ?? $now]);

        $externalId = (int) ($attributes['external_id'] ?? $attributes['id'] ?? 0);

        if ($externalId < 1) {
            throw new \InvalidArgumentException('TheGamesDB game payload is missing an external_id.');
        }

        if (! isset($payload['title']) || trim((string) $payload['title']) === '') {
            throw new \InvalidArgumentException('TheGamesDB game payload is missing a title.');
        }

        if (! isset($payload['slug']) || trim((string) $payload['slug']) === '') {
            $payload['slug'] = Str::slug($payload['title']);
        }

        $payload['genres'] = $this->normalizeGenres($payload['genres'] ?? []);

        return tap(TheGamesDbGame::query()->firstOrNew(['external_id' => $externalId]), function (TheGamesDbGame $game) use ($payload, $now): void {
            $game->fill($payload);
            $game->last_synced_at = $payload['last_synced_at'] ?? $now;
            $game->save();
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TheGamesDbGame>
     */
    public function search(string $query, array $filters = [], ?int $limit = null): Collection
    {
        $normalized = trim($query);

        if ($normalized === '') {
            return collect();
        }

        $slug = Str::slug($normalized);
        $platforms = $this->normalizePlatforms($filters['platforms'] ?? null);

        $builder = TheGamesDbGame::query()
            ->select('*')
            ->when($platforms !== null, fn ($q) => $q->whereIn('platform', $platforms))
            ->where(function ($q) use ($normalized, $slug): void {
                $q->where('slug', $slug)
                    ->orWhere('title', 'like', $normalized.'%')
                    ->orWhere('title', 'like', '%'.$normalized.'%');
            })
            ->orderByRaw('slug = ? desc', [$slug])
            ->orderBy('title');

        if ($limit !== null && $limit > 0) {
            $builder->limit($limit);
        }

        return $builder->get();
    }

    public function latestSyncState(): VendorSyncState
    {
        return VendorSyncState::query()->firstOrCreate([
            'provider' => self::PROVIDER_KEY,
        ]);
    }

    public function forPlatform(string $platform, ?int $limit = null): Collection
    {
        $normalized = trim($platform);

        if ($normalized === '') {
            return collect();
        }

        $query = TheGamesDbGame::query()
            ->where('platform', $normalized)
            ->orderByDesc('last_synced_at')
            ->orderBy('title');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function updateFullSyncState(?Carbon $timestamp = null, array $metadata = []): void
    {
        tap($this->latestSyncState(), function (VendorSyncState $state) use ($timestamp, $metadata): void {
            $state->last_full_sync_at = $timestamp ?? now();
            $state->metadata = array_merge($state->metadata ?? [], $metadata);
            $state->save();
        });
    }

    public function updateIncrementalSyncState(?Carbon $timestamp = null, array $metadata = []): void
    {
        tap($this->latestSyncState(), function (VendorSyncState $state) use ($timestamp, $metadata): void {
            $state->last_incremental_sync_at = $timestamp ?? now();
            $state->metadata = array_merge($state->metadata ?? [], $metadata);
            $state->save();
        });
    }

    public function updateSweepState(?Carbon $timestamp = null, array $metadata = []): void
    {
        tap($this->latestSyncState(), function (VendorSyncState $state) use ($timestamp, $metadata): void {
            $existing = $state->metadata ?? [];
            $sweep = is_array($existing['sweep'] ?? null) ? $existing['sweep'] : [];

            $state->metadata = array_merge($existing, [
                'last_sweep_at' => ($timestamp ?? now())->toIso8601String(),
                'sweep' => array_merge($sweep, $metadata),
            ]);

            $state->save();
        });
    }

    public function updateDiscoveryState(?Carbon $timestamp = null, array $metadata = []): void
    {
        tap($this->latestSyncState(), function (VendorSyncState $state) use ($timestamp, $metadata): void {
            $existing = $state->metadata ?? [];
            $discovery = is_array(Arr::get($existing, 'discovery')) ? Arr::get($existing, 'discovery') : [];

            $state->metadata = array_merge($existing, [
                'last_discovery_at' => ($timestamp ?? now())->toIso8601String(),
                'discovery' => array_merge($discovery, $metadata),
            ]);

            $state->save();
        });
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

    protected function normalizePlatforms(mixed $platforms): ?array
    {
        if ($platforms === null) {
            return null;
        }

        if (! is_array($platforms)) {
            $platforms = [$platforms];
        }

        $normalized = collect($platforms)
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }
}
