<?php

namespace App\Services\Catalogue\DTOs;

use App\Models\TheGamesDbGame;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrendingGameData
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?CarbonImmutable $releasedAt,
        public readonly array $platforms,
        public readonly array $genres,
        public readonly array $stores,
        public readonly ?float $rating,
        public readonly ?int $metacritic,
        public readonly array $raw,
    ) {}

    public static function fromRawg(array $payload): self
    {
        $name = (string) Arr::get($payload, 'name', 'Unknown Title');
        $slug = (string) Arr::get($payload, 'slug', Str::slug($name));

        $released = Arr::get($payload, 'released');
        $releasedAt = filled($released)
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $released)
            : null;

        $platforms = collect(Arr::get($payload, 'platforms', []))
            ->pluck('platform.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $genres = collect(Arr::get($payload, 'genres', []))
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $stores = collect(Arr::get($payload, 'stores', []))
            ->pluck('store.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rating = Arr::has($payload, 'rating') ? (float) Arr::get($payload, 'rating') : null;
        $metacritic = Arr::has($payload, 'metacritic') ? (int) Arr::get($payload, 'metacritic') : null;

        return new self(
            name: $name,
            slug: $slug,
            releasedAt: $releasedAt ?: null,
            platforms: $platforms,
            genres: $genres,
            stores: $stores,
            rating: $rating,
            metacritic: $metacritic,
            raw: [
                'source' => 'rawg',
                'record' => $payload,
            ],
        );
    }

    public static function fromMirror(TheGamesDbGame $game): self
    {
        $name = $game->title ?? 'Unknown Title';
        $slug = filled($game->slug) ? $game->slug : Str::slug($name);

        $release = $game->release_date;
        $releaseAt = match (true) {
            $release instanceof CarbonImmutable => $release,
            $release instanceof Carbon => CarbonImmutable::instance($release),
            is_string($release) && trim($release) !== '' => CarbonImmutable::parse($release),
            default => null,
        };

        $platforms = collect([$game->platform])
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values()
            ->all();

        $genres = is_array($game->genres)
            ? collect($game->genres)->filter(fn ($value) => filled($value))->unique()->values()->all()
            : [];

        $lastSynced = $game->last_synced_at;
        $metadata = array_filter([
            'thegamesdb_id' => $game->external_id,
            'category' => $game->category,
            'platform' => $game->platform,
            'players' => $game->players,
            'developer' => $game->developer,
            'publisher' => $game->publisher,
            'image_url' => $game->image_url,
            'thumb_url' => $game->thumb_url,
            'mirror_last_synced_at' => $lastSynced instanceof \DateTimeInterface ? $lastSynced->format(\DateTimeInterface::ATOM) : null,
        ]);

        if (is_array($game->metadata)) {
            $metadata = array_merge($game->metadata, $metadata);
        }

        return new self(
            name: $name,
            slug: $slug,
            releasedAt: $releaseAt,
            platforms: $platforms,
            genres: $genres,
            stores: [],
            rating: null,
            metacritic: null,
            raw: [
                'source' => 'thegamesdb_mirror',
                'record' => array_merge($game->toArray(), ['metadata' => $metadata]),
            ],
        );
    }

    public static function fromGiantBomb(array $payload): self
    {
        $name = (string) Arr::get($payload, 'name', 'Unknown Title');
        $slug = Str::slug($name);

        $releaseAt = null;
        $originalRelease = Arr::get($payload, 'original_release_date');

        if (filled($originalRelease)) {
            try {
                $releaseAt = CarbonImmutable::parse((string) $originalRelease);
            } catch (\Throwable) {
                $releaseAt = null;
            }
        }

        if ($releaseAt === null) {
            $expectedYear = Arr::get($payload, 'expected_release_year');

            if ($expectedYear) {
                $expectedMonth = Arr::get($payload, 'expected_release_month') ?: 1;
                $expectedDay = Arr::get($payload, 'expected_release_day') ?: 1;

                try {
                    $releaseAt = CarbonImmutable::createFromFormat(
                        'Y-m-d',
                        sprintf('%04d-%02d-%02d', $expectedYear, $expectedMonth, $expectedDay)
                    );
                } catch (\Throwable) {
                    $releaseAt = null;
                }
            }
        }

        $platforms = self::extractStrings(Arr::get($payload, 'platforms'));
        $genres = self::extractStrings(Arr::get($payload, 'genres'));
        $storeUrl = Arr::get($payload, 'site_detail_url');
        $stores = $storeUrl ? [$storeUrl] : [];

        return new self(
            name: $name,
            slug: $slug,
            releasedAt: $releaseAt,
            platforms: $platforms,
            genres: $genres,
            stores: $stores,
            rating: null,
            metacritic: null,
            raw: [
                'source' => 'giantbomb',
                'record' => $payload,
            ],
        );
    }

    public static function fromNexarda(array $payload): self
    {
        $name = (string) (Arr::get($payload, 'title') ?? Arr::get($payload, 'name') ?? 'Unknown Title');
        $slug = (string) (Arr::get($payload, 'slug') ?? Str::slug($name));

        $releaseAt = null;
        $release = Arr::get($payload, 'release_date') ?? Arr::get($payload, 'released_on');

        if (filled($release)) {
            try {
                $releaseAt = CarbonImmutable::parse((string) $release);
            } catch (\Throwable) {
                $releaseAt = null;
            }
        }

        if ($releaseAt === null) {
            $year = Arr::get($payload, 'release_year');

            if ($year) {
                $month = Arr::get($payload, 'release_month') ?: 1;
                $day = Arr::get($payload, 'release_day') ?: 1;

                try {
                    $releaseAt = CarbonImmutable::createFromFormat(
                        'Y-m-d',
                        sprintf('%04d-%02d-%02d', $year, $month, $day)
                    );
                } catch (\Throwable) {
                    $releaseAt = null;
                }
            }
        }

        $platforms = self::extractStrings(Arr::get($payload, 'platforms'));
        if (empty($platforms)) {
            $platforms = self::extractStrings(Arr::get($payload, 'platform_list'));
        }

        $genres = self::extractStrings(Arr::get($payload, 'genres'));
        if (empty($genres)) {
            $genres = self::extractStrings(Arr::get($payload, 'genre_list'));
        }

        $stores = self::extractStrings(Arr::get($payload, 'stores'));
        if (empty($stores)) {
            $stores = self::extractStrings(Arr::get($payload, 'storefronts'));
        }

        $rating = Arr::get($payload, 'rating');
        $score = Arr::get($payload, 'score');

        return new self(
            name: $name,
            slug: $slug,
            releasedAt: $releaseAt,
            platforms: $platforms,
            genres: $genres,
            stores: $stores,
            rating: is_numeric($rating) ? (float) $rating : null,
            metacritic: is_numeric($score) ? (int) $score : null,
            raw: [
                'source' => 'nexarda',
                'record' => $payload,
            ],
        );
    }

    public function source(): string
    {
        return (string) ($this->raw['source'] ?? 'rawg');
    }

    public function primaryPlatform(): string
    {
        return $this->platforms[0] ?? 'Unknown';
    }

    public function metadata(): array
    {
        $base = array_filter([
            'source' => $this->source(),
            'genres' => $this->genres,
            'platforms' => $this->platforms,
            'stores' => $this->stores,
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        return match ($this->source()) {
            'thegamesdb_mirror' => $this->mirrorMetadata($base),
            'giantbomb' => $this->giantBombMetadata($base),
            'nexarda' => $this->nexardaMetadata($base),
            default => $this->rawgMetadata($base),
        };
    }

    protected function rawRecord(): array
    {
        $raw = $this->raw;

        if (! is_array($raw)) {
            return [];
        }

        if (array_key_exists('record', $raw) && is_array($raw['record'])) {
            return $raw['record'];
        }

        return $raw;
    }

    protected static function extractStrings(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }

                    if (is_array($item)) {
                        return Arr::get($item, 'name')
                            ?? Arr::get($item, 'title')
                            ?? Arr::get($item, 'label');
                    }

                    return null;
                })
                ->filter(fn ($item) => is_string($item) && $item !== '')
                ->map(fn ($item) => trim($item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (is_string($value)) {
            return collect(preg_split('/[,|\n]/', $value) ?: [])
                ->map(fn ($item) => trim($item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    private function rawgMetadata(array $base): array
    {
        $record = $this->rawRecord();

        $metadata = array_merge($base, array_filter([
            'rawg_id' => Arr::get($record, 'id'),
            'rawg_slug' => Arr::get($record, 'slug', $this->slug),
            'rawg_url' => 'https://rawg.io/games/'.$this->slug,
            'rating' => $this->rating,
            'metacritic' => $this->metacritic,
        ], fn ($value) => $value !== null && $value !== ''));

        $runtime = Arr::get($record, 'playtime');
        if ($runtime) {
            $metadata['average_playtime_hours'] = (int) $runtime;
        }

        $esrb = Arr::get($record, 'esrb_rating.name');
        if ($esrb) {
            $metadata['esrb_rating'] = $esrb;
        }

        $tags = collect(Arr::get($record, 'tags', []))
            ->map(fn ($tag) => is_array($tag) ? Arr::get($tag, 'name') : $tag)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($tags)) {
            $metadata['tags'] = $tags;
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    private function mirrorMetadata(array $base): array
    {
        $record = $this->rawRecord();

        $metadata = array_merge($base, array_filter([
            'thegamesdb_id' => Arr::get($record, 'external_id'),
            'mirror_category' => Arr::get($record, 'category'),
            'mirror_platform' => Arr::get($record, 'platform'),
            'players' => Arr::get($record, 'players'),
            'developer' => Arr::get($record, 'developer'),
            'publisher' => Arr::get($record, 'publisher'),
            'image_url' => Arr::get($record, 'image_url'),
            'thumb_url' => Arr::get($record, 'thumb_url'),
            'mirror_last_synced_at' => Arr::get($record, 'metadata.mirror_last_synced_at'),
        ], fn ($value) => $value !== null && $value !== ''));

        if (empty($metadata['genres'])) {
            $mirrorGenres = Arr::get($record, 'genres', []);
            if (! empty($mirrorGenres)) {
                $metadata['genres'] = array_values(array_unique(array_filter($mirrorGenres)));
            }
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    private function giantBombMetadata(array $base): array
    {
        $record = $this->rawRecord();

        $metadata = array_merge($base, array_filter([
            'giantbomb_id' => Arr::get($record, 'id'),
            'giantbomb_url' => Arr::get($record, 'site_detail_url'),
            'deck' => Arr::get($record, 'deck'),
            'image_url' => Arr::get($record, 'image.original_url'),
        ], fn ($value) => $value !== null && $value !== ''));

        $aliases = Arr::get($record, 'aliases');
        if (is_string($aliases) && $aliases !== '') {
            $metadata['aliases'] = collect(preg_split('/\r?\n|,/', $aliases) ?: [])
                ->map(fn ($alias) => trim($alias))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (empty($metadata['genres'])) {
            $metadata['genres'] = self::extractStrings(Arr::get($record, 'genres'));
        }

        if (empty($metadata['platforms'])) {
            $metadata['platforms'] = self::extractStrings(Arr::get($record, 'platforms'));
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    private function nexardaMetadata(array $base): array
    {
        $record = $this->rawRecord();

        $metadata = array_merge($base, array_filter([
            'nexarda_id' => Arr::get($record, 'id'),
            'nexarda_slug' => Arr::get($record, 'slug', $this->slug),
            'nexarda_url' => Arr::get($record, 'website') ?? Arr::get($record, 'url'),
            'summary' => Arr::get($record, 'summary') ?? Arr::get($record, 'short_desc'),
            'score' => Arr::get($record, 'score'),
            'age_rating' => Arr::get($record, 'age_rating') ?? Arr::get($record, 'esrb'),
            'storefront_links' => Arr::get($record, 'storefront_links'),
        ], fn ($value) => $value !== null && $value !== ''));

        if (empty($metadata['genres'])) {
            $metadata['genres'] = self::extractStrings(Arr::get($record, 'genres'));
        }

        if (empty($metadata['platforms'])) {
            $metadata['platforms'] = self::extractStrings(Arr::get($record, 'platforms'));
        }

        if (empty($metadata['stores'])) {
            $metadata['stores'] = self::extractStrings(Arr::get($record, 'stores'));
        }

        return array_filter($metadata, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    public function toProductAttributes(): array
    {
        return [
            'name' => $this->name,
            'platform' => $this->primaryPlatform(),
            'category' => 'Game',
            'release_date' => $this->releasedAt?->toDateString(),
            'metadata' => $this->metadata(),
        ];
    }
}
