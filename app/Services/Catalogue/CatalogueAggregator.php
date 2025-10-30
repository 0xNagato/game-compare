<?php

namespace App\Services\Catalogue;

use App\Models\TheGamesDbGame;
use App\Services\Catalogue\DTOs\TrendingGameData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogueAggregator
{
    public function __construct(
        private readonly TrendingGameImporter $rawgImporter,
        private readonly GiantBombTrendingImporter $giantBombImporter,
        private readonly NexardaTrendingImporter $nexardaImporter,
        private readonly ?NexardaFeedImporter $nexardaFeedImporter = null,
    ) {}

    public function aggregate(int $limit, ?int $windowDays = null): CatalogueAggregateResult
    {
        $limit = max(1, $limit);
        $sourcesConfig = config('catalogue.sources', []);

        $collection = collect();
        $meta = [];
        $remaining = $limit;

        foreach ($sourcesConfig as $sourceKey => $settings) {
            if (! is_array($settings) || ! $this->sourceEnabled($settings)) {
                continue;
            }

            // If we've satisfied remaining, only continue fetching from sources explicitly marked as always_fetch
            if ($remaining <= 0 && empty($settings['always_fetch'])) {
                continue;
            }

            $requestedLimit = $this->resolveSourceLimit($settings, $remaining, $limit);

            if ($requestedLimit <= 0 && $remaining <= 0) {
                continue;
            }

            $take = $requestedLimit > 0 ? $requestedLimit : ($remaining > 0 ? $remaining : $limit);

            if ($take <= 0) {
                continue;
            }

            try {
                $items = $this->fetchSourceEntries($sourceKey, $take, $windowDays, $settings);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('catalogue.aggregate.source_failed', [
                    'source' => $sourceKey,
                    'error' => $e->getMessage(),
                ]);
                $items = collect();
            }

            $collection = $collection->merge($items);

            $meta[$sourceKey] = [
                'count' => $items->count(),
                'requested' => $take,
            ];

            $remaining = max(0, $remaining - $items->count());
        }

        $unique = $collection
            ->filter()
            ->unique(fn (TrendingGameData $item) => Str::lower($item->slug))
            ->values();

        return new CatalogueAggregateResult(
            entries: $unique->take($limit),
            sources: $meta,
            totalRequested: $limit,
        );
    }

    protected function fetchSourceEntries(string $source, int $limit, ?int $windowDays, array $config): Collection
    {
        return match ($source) {
            'rawg' => $this->rawgImporter->fetch($limit, $windowDays),
            'thegamesdb_mirror' => $this->fetchMirrorEntries($limit, $config),
            'giantbomb' => $this->giantBombImporter->fetch($limit, $windowDays, $config),
            'nexarda' => $this->nexardaImporter->fetch($limit, $windowDays, $config),
            'nexarda_feed' => $this->nexardaFeedImporter
                ? $this->nexardaFeedImporter->fetch($limit, $config)
                : collect(),
            default => collect(),
        };
    }

    protected function fetchMirrorEntries(int $limit, array $config): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $categories = $config['categories'] ?? ['Hardware', 'Console', 'Game'];
        $platforms = $config['platforms'] ?? null;
        $offset = (int) ($config['offset'] ?? 0);
        $family = $config['family'] ?? null;

        $query = TheGamesDbGame::query()
            ->select(['*'])
            ->when(! empty($categories), fn ($builder) => $builder->whereIn('category', (array) $categories))
            ->when(! empty($platforms), fn ($builder) => $builder->whereIn('platform', (array) $platforms))
            ->when(! empty($family), function ($builder) use ($family) {
                $patterns = $this->familyLikePatterns((string) $family);
                if (! empty($patterns)) {
                    $builder->where(function ($q) use ($patterns) {
                        foreach ($patterns as $pat) {
                            $q->orWhere('platform', 'like', $pat);
                        }
                    });
                }
            })
            ->orderByDesc('release_date')
            ->orderByDesc('last_synced_at')
            ->orderBy('title');

        if ($offset > 0) {
            $query->skip($offset);
        }

        // Global policy: avoid adding older PC titles (<2015)
        $records = $query
            ->where(function ($q) {
                $q->whereRaw("LOWER(platform) NOT LIKE '%pc%'")
                  ->orWhereDate('release_date', '>=', '2015-01-01');
            })
            ->limit($limit)
            ->get();

        return $records->map(fn (TheGamesDbGame $game) => TrendingGameData::fromMirror($game));
    }

    /**
     * Return SQL LIKE patterns for TGDB platform names that map to a given family.
     *
     * @return array<int, string>
     */
    protected function familyLikePatterns(string $family): array
    {
        $family = Str::lower($family);
        return match (true) {
            str_contains($family, 'nintendo') => ['%Nintendo%', '%Switch%', '%Wii%', '%3DS%', '%DS%'],
            str_contains($family, 'playstation') || str_contains($family, 'ps') => ['%PlayStation%', '%PS %', '%PS-%', '%PSX%'],
            str_contains($family, 'xbox') => ['%Xbox%'],
            str_contains($family, 'retro') => ['%Atari%', '%Sega%', '%Genesis%', '%Mega Drive%', '%Saturn%', '%Dreamcast%', '%Master System%', '%Amiga%', '%Commodore%', '%Neo Geo%', '%Arcade%'],
            default => [],
        };
    }

    protected function sourceEnabled(array $settings): bool
    {
        return (bool) ($settings['enabled'] ?? true);
    }

    protected function resolveSourceLimit(array $settings, int $remaining, int $fallback): int
    {
        $preferred = $settings['limit'] ?? null;

        if ($preferred === null) {
            return $remaining > 0 ? $remaining : $fallback;
        }

        $preferred = (int) $preferred;

        if ($preferred <= 0) {
            return $remaining > 0 ? $remaining : $fallback;
        }

        return $remaining > 0 ? min($preferred, max(1, $remaining)) : $preferred;
    }
}