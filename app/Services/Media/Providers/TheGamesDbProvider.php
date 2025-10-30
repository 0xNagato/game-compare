<?php

namespace App\Services\Media\Providers;

use App\Models\Product;
use App\Models\TheGamesDbGame;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use App\Services\TheGamesDb\TheGamesDbMirrorRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TheGamesDbProvider implements ProductMediaProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private readonly TheGamesDbMirrorRepository $mirror,
        private readonly array $options = [],
    ) {}

    public function enabled(): bool
    {
        return ($this->options['enabled'] ?? true) === true;
    }

    public function getName(): string
    {
        return 'thegamesdb';
    }

    public function fetch(Product $product, array $context = []): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $query = $context['query'] ?? $product->name;

        if (blank($query)) {
            return collect();
        }

        $platforms = $this->mergePlatforms([
            $product->platform,
            ...($this->options['platforms'] ?? []),
        ], $context['platforms'] ?? null);

        $limit = $this->resolveLimit($context['limit'] ?? null);

        $results = $this->mirror->search($query, [
            'platforms' => $platforms,
        ], $limit);

        return $this->mapGamesToMedia($results);
    }

    public function fetchByPlatform(int $platformId, array $context = []): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $limit = $this->resolveLimit($context['limit'] ?? null);
        $platformName = $context['platform_name'] ?? (string) $platformId;

        $results = $this->mirror->forPlatform($platformName, $limit);

        return $this->mapGamesToMedia($results);
    }

    protected function mapGamesToMedia(Collection $games): Collection
    {
        return $games
            ->filter(fn ($game) => $game instanceof TheGamesDbGame)
            ->map(fn (TheGamesDbGame $game) => $this->makeMediaData($game))
            ->filter()
            ->values();
    }

    protected function makeMediaData(TheGamesDbGame $game): ?ProductMediaData
    {
        if (! $game->image_url) {
            return null;
        }

        $metadata = $game->metadata ?? [];

        return new ProductMediaData(
            source: $this->getName(),
            externalId: (string) $game->external_id,
            mediaType: 'image',
            title: $game->title,
            caption: Arr::get($metadata, 'overview'),
            url: $game->image_url,
            thumbnailUrl: $game->thumb_url ?: $game->image_url,
            attribution: 'TheGamesDB Community',
            license: 'Community-sourced (non-commercial use)',
            licenseUrl: 'https://thegamesdb.net',
            metadata: array_filter([
                'players' => $game->players,
                'genres' => $game->genres,
                'developer' => $game->developer,
                'release_date' => $game->release_date instanceof \DateTimeInterface ? $game->release_date->format('Y-m-d') : (is_string($game->release_date) && $game->release_date !== '' ? $game->release_date : null),
                'platform' => $game->platform,
            ], static fn ($value) => $value !== null && $value !== []),
        );
    }

    /**
     * @param  array<int, mixed>|null  $override
     */
    protected function mergePlatforms(array $defaults, mixed $override): ?array
    {
        $overrides = [];

        if (is_array($override)) {
            $overrides = $override;
        } elseif (is_scalar($override) && $override !== null) {
            $overrides = [(string) $override];
        }

        $merged = collect([$defaults, $overrides])
            ->flatten()
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $merged === [] ? null : $merged;
    }

    protected function resolveLimit(mixed $value): ?int
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
