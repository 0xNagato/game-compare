<?php

namespace Database\Seeders;

use App\Models\Console;
use App\Models\GameAlias;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use App\Models\TheGamesDbGame;
use App\Models\VideoGame;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TgdbMirrorProductSeeder extends Seeder
{
    public function run(): void
    {
        $entries = TheGamesDbGame::query()
            ->orderBy('category')
            ->orderBy('title')
            ->get();

        if ($entries->isEmpty()) {
            $this->command?->warn('TheGamesDB mirror is empty; skipping TGDB mirror product seed.');

            return;
        }

        $allowedEntries = $entries->filter(fn (TheGamesDbGame $entry) => $this->shouldSeedEntry($entry))->values();

        if ($allowedEntries->isEmpty()) {
            $this->command?->warn('No TGDB mirror entries matched the allowed platform filters; skipping.');

            return;
        }

        $seeded = 0;
        $skipped = $entries->count() - $allowedEntries->count();

        $allowedEntries->each(function (TheGamesDbGame $entry) use (&$seeded): void {
            $slug = $entry->slug ?: Str::slug((string) $entry->title);

            if ($slug === '') {
                return;
            }

            $category = $entry->category ?: 'Game';
            $platformName = $entry->platform ?: 'Multi-platform';
            $family = $this->determinePlatformFamily($platformName);

            $releaseDate = $this->normalizeDate($entry->release_date);

            $computedUid = $this->computeUid($entry->title, $releaseDate?->toDateString(), $family);

            $existing = Product::query()
                ->where('slug', $slug)
                ->orWhere('uid', $computedUid)
                ->first();

            $metadata = $this->buildProductMetadata($entry, $existing?->metadata ?? []);
            $externalIds = $this->buildExternalIds($entry, $existing?->external_ids ?? []);
            $synopsis = $existing?->synopsis ?: Arr::get($entry->metadata, 'overview');
            $rating = $this->determineRating($existing);

            $product = $existing ?? new Product;

            $product->slug = $slug;
            $product->name = $entry->title;
            $product->platform = $platformName;
            $product->category = $category;
            $product->release_date = $releaseDate?->toDateString();
            $product->metadata = $metadata;
            $product->uid = $existing?->uid ?? $computedUid;
            $product->primary_platform_family = $family;
            $product->popularity_score = $existing?->popularity_score ?? 0.55;
            $product->rating = $rating;
            $product->freshness_score = $this->calculateFreshnessScore($releaseDate);
            $product->external_ids = $externalIds;
            $product->synopsis = $synopsis;

            $product->save();

            $this->syncPlatforms($product, $platformName);
            $this->syncGenres($product, $entry->genres ?? []);
            $this->syncAlias($product, $entry);

            if (Str::lower($category) === 'hardware') {
                $this->syncConsole($product, $entry);
            } else {
                $this->syncVideoGame($product, $entry);
            }

            $seeded++;
        });

        $this->command?->info(sprintf('Seeded %d product(s) from TheGamesDB mirror (skipped %d).', $seeded, $skipped));
    }

    protected function shouldSeedEntry(TheGamesDbGame $entry): bool
    {
        $category = Str::lower($entry->category ?? '');

        if ($category !== '' && ! in_array($category, ['game', 'hardware'], true)) {
            return false;
        }

        if (! $this->matchesAllowedPlatform($entry)) {
            return false;
        }

        return true;
    }

    protected function matchesAllowedPlatform(TheGamesDbGame $entry): bool
    {
        $candidates = $this->platformCandidates($entry);

        if ($candidates->isEmpty()) {
            return false;
        }

        return $candidates
            ->contains(function (string $platform): bool {
                return Str::contains($platform, self::ALLOWED_PLATFORM_KEYWORDS);
            });
    }

    protected const ALLOWED_PLATFORM_KEYWORDS = [
        'steam',
        'pc',
        'windows',
        'mac',
        'linux',
        'commodore',
        'amiga',
        'nintendo',
        'switch',
        'wii',
        'wii u',
        'gamecube',
        'game cube',
        'game boy',
        'gameboy',
        'gba',
        'ds',
        '3ds',
        '2ds',
        'nes',
        'snes',
        'n64',
        'playstation',
        'ps',
        'ps1',
        'ps2',
        'ps3',
        'ps4',
        'ps5',
        'psp',
        'vita',
        'xbox',
        'xbox 360',
        'xbox one',
        'xbox series',
        'series x',
        'series s',
        'android',
        'ios',
        'mobile',
        'stadia',
        'oculus',
        'meta quest',
        'dreamcast',
        'saturn',
        'genesis',
        'mega drive',
        'master system',
        'game gear',
        'turbografx',
        'turbo grafx',
        'neo geo',
        'arcade',
        'coleco',
        'intellivision',
        'atari',
        'lynx',
        'wonderswan',
        'ouya',
        'mac os',
        'pc engine',
        'msx',
        'amstrad',
        'zx spectrum',
        'apple ii',
        'game & watch',
    ];

    protected const TGDB_PLATFORM_ID_MAP = [
        1 => 'pc',
        2 => 'nintendo gamecube',
        3 => 'nintendo 64',
        4 => 'nintendo game boy',
        5 => 'nintendo game boy advance',
        6 => 'super nintendo (snes)',
        7 => 'nintendo entertainment system (nes)',
        8 => 'nintendo ds',
        9 => 'nintendo wii',
        10 => 'sony playstation',
        11 => 'sony playstation 2',
        12 => 'sony playstation 3',
        13 => 'sony playstation portable',
        14 => 'microsoft xbox',
        15 => 'microsoft xbox 360',
        16 => 'sega dreamcast',
        17 => 'sega saturn',
        18 => 'sega genesis',
        20 => 'sega game gear',
        21 => 'sega cd',
        22 => 'atari 2600',
        23 => 'arcade',
        24 => 'neo geo',
        26 => 'atari 5200',
        27 => 'atari 7800',
        28 => 'atari jaguar',
        29 => 'atari jaguar cd',
        30 => 'atari xe',
        31 => 'colecovision',
        32 => 'intellivision',
        33 => 'sega 32x',
        34 => 'turbografx 16',
        35 => 'sega master system',
        36 => 'sega mega drive',
        37 => 'mac os',
        38 => 'nintendo wii u',
        39 => 'sony playstation vita',
        40 => 'commodore 64',
        41 => 'nintendo game boy color',
        4911 => 'amiga',
        4912 => 'nintendo 3ds',
        4913 => 'sinclair zx spectrum',
        4914 => 'amstrad cpc',
        4915 => 'ios',
        4916 => 'android',
        4917 => 'philips cd-i',
        4918 => 'nintendo virtual boy',
        4919 => 'sony playstation 4',
        4920 => 'microsoft xbox one',
        4921 => 'ouya',
        4922 => 'neo geo pocket',
        4923 => 'neo geo pocket color',
        4924 => 'atari lynx',
        4925 => 'wonderswan',
        4926 => 'wonderswan color',
        4927 => 'magnavox odyssey 2',
        4928 => 'fairchild channel f',
        4929 => 'msx',
        4930 => 'pc-fx',
        4931 => 'sharp x68000',
        4932 => 'fm towns marty',
        4933 => 'pc-88',
        4934 => 'pc-98',
        4935 => 'nuon',
        4936 => 'famicom disk system',
        4937 => 'atari st',
        4938 => 'n-gage',
        4939 => 'vectrex',
        4940 => 'game.com',
        4941 => 'trs-80 color computer',
        4942 => 'apple ii',
        4943 => 'atari 800',
        4944 => 'acorn archimedes',
        4945 => 'commodore vic-20',
        4946 => 'commodore 128',
        4947 => 'amiga cd32',
        4948 => 'mega duck',
        4949 => 'sega sg-1000',
        4950 => 'game & watch',
        4951 => 'lcd handheld',
        4952 => 'dragon 32/64',
        4953 => 'texas instruments ti-99/4a',
        4954 => 'acorn electron',
        4955 => 'turbografx cd',
        4956 => 'neo geo cd',
        4957 => 'nintendo pokemon mini',
        4958 => 'sega pico',
        4959 => 'watara supervision',
        4960 => 'tomy tutor',
        4961 => 'magnavox odyssey',
        4962 => 'gakken compact vision',
        4963 => 'emerson arcadia 2001',
        4964 => 'casio pv-1000',
        4965 => 'epoch cassette vision',
        4966 => 'epoch super cassette vision',
        4967 => 'rca studio ii',
        4968 => 'bally astrocade',
        4969 => 'apf mp-1000',
        4970 => 'coleco telstar arcade',
        4971 => 'nintendo switch',
        4972 => 'milton bradley microvision',
        4973 => 'entex select-a-game',
        4974 => 'entex adventure vision',
        4975 => 'pioneer laseractive',
        4976 => 'action max',
        4977 => 'sharp x1',
        4978 => 'fujitsu fm-7',
        4979 => 'sam coupe',
        4980 => 'sony playstation 5',
        4981 => 'microsoft xbox series x',
        4982 => 'tandy visual interactive system',
        4983 => 'tiger r-zone',
        4984 => 'xavix port',
        4985 => 'evercade',
        4986 => 'oric-1',
        4987 => 'hyperscan',
        4988 => 'vtech v.smile',
        4989 => 'mattel aquarius',
        4990 => 'oculus quest',
        4991 => 'casio loopy',
        4992 => 'gizmondo',
        4993 => 'philips tele-spiel es-2201',
        4994 => 'interton vc 4000',
        4995 => 'bandai tv jack 5000',
        4996 => 'shg black point',
        4997 => 'bbc bridge companion',
        4998 => 'vtech socrates',
        4999 => 'amstrad gx4000',
        5000 => 'playdia',
        5001 => 'apple pippin',
        5002 => 'game wave',
        5003 => 'palmtex super micro',
        5004 => 'gamate',
        5005 => 'vtech creativision',
        5006 => 'commodore 16',
        5007 => 'commodore plus/4',
        5008 => 'commodore pet',
        5009 => 'sinclair zx80',
        5010 => 'sinclair zx81',
        5011 => 'stadia',
        5012 => 'didj',
        5013 => 'bbc micro',
        5014 => 'acorn atom',
        5015 => 'gp32',
        5016 => 'playdate',
        5017 => 'tapwave zodiac',
        5018 => 'j2me',
        5019 => 'jupiter ace',
        5020 => 'sinclair ql',
        5021 => 'nintendo switch 2',
    ];

    protected function platformCandidates(TheGamesDbGame $entry): \Illuminate\Support\Collection
    {
        $metadata = is_array($entry->metadata) ? $entry->metadata : [];

        $rawValues = collect([
            $entry->platform,
            Arr::get($metadata, 'platform'),
            Arr::get($metadata, 'platform_name'),
            Arr::get($metadata, 'platforms'),
            Arr::get($metadata, 'sources.thegamesdb.platform_name'),
            Arr::get($metadata, 'sources.rawg.platform'),
            Arr::get($metadata, 'sources.nexarda.platform'),
        ])->flatten();

        $platformIds = collect([
            Arr::get($metadata, 'platform_id'),
            Arr::get($metadata, 'raw.platform_id'),
            Arr::get($metadata, 'raw.platform'),
        ])->flatten();

        return $rawValues
            ->merge($platformIds)
            ->flatten()
            ->map(function ($value) {
                if (is_numeric($value)) {
                    $id = (int) $value;

                    if (isset(self::TGDB_PLATFORM_ID_MAP[$id])) {
                        $value = self::TGDB_PLATFORM_ID_MAP[$id];
                    }
                }

                if (! is_string($value)) {
                    return null;
                }

                $normalized = trim(Str::lower($value));

                return $normalized === '' ? null : $normalized;
            })
            ->filter()
            ->unique()
            ->values();
    }

    protected function buildProductMetadata(TheGamesDbGame $entry, array $existing): array
    {
        $metadata = is_array($existing) ? $existing : [];
        $mirrorMetadata = array_filter([
            'external_id' => $entry->external_id,
            'category' => $entry->category,
            'platform' => $entry->platform,
            'players' => $entry->players,
            'developer' => $entry->developer,
            'publisher' => $entry->publisher,
            'image_url' => $entry->image_url,
            'thumb_url' => $entry->thumb_url,
            'last_synced_at' => $entry->last_synced_at?->toIso8601String(),
            'overview' => Arr::get($entry->metadata, 'overview'),
            'search_queries' => Arr::get($entry->metadata, 'search_queries'),
        ], fn ($value) => $value !== null && $value !== '');

        $metadata['sources'] = array_merge($metadata['sources'] ?? [], [
            'thegamesdb' => $mirrorMetadata,
        ]);

        if (! empty($entry->genres) && is_array($entry->genres)) {
            $metadata['genres'] = array_values(array_unique(array_merge(
                $metadata['genres'] ?? [],
                array_values(array_filter($entry->genres, fn ($genre) => is_string($genre) && $genre !== '')),
            )));
        }

        if (is_string($entry->platform) && $entry->platform !== '') {
            $metadata['platforms'] = array_values(array_unique(array_merge(
                $metadata['platforms'] ?? [],
                [$entry->platform],
            )));
        }

        return $metadata;
    }

    protected function determineRating(?Product $existing): float
    {
        if ($existing && $existing->rating !== null) {
            return (float) $existing->rating;
        }

        return 0.0;
    }

    protected function buildExternalIds(TheGamesDbGame $entry, array $existing): array
    {
        $ids = is_array($existing) ? $existing : [];
        $ids['thegamesdb'] = (string) $entry->external_id;

        return $ids;
    }

    protected function syncPlatforms(Product $product, ?string $platformName): void
    {
        if (! is_string($platformName) || trim($platformName) === '') {
            return;
        }

        $code = Str::slug($platformName);
        $family = $this->determinePlatformFamily($platformName) ?? 'multi';

        $platform = Platform::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $platformName,
                'family' => $family,
            ]
        );

        $product->platforms()->syncWithoutDetaching([$platform->id]);
    }

    protected function syncGenres(Product $product, mixed $genres): void
    {
        if (! is_array($genres)) {
            return;
        }

        foreach ($genres as $genre) {
            if (! is_string($genre) || trim($genre) === '') {
                continue;
            }

            $slug = Str::slug($genre);

            $genreModel = Genre::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $genre]
            );

            $product->genres()->syncWithoutDetaching([$genreModel->id]);
        }
    }

    protected function syncAlias(Product $product, TheGamesDbGame $entry): void
    {
        GameAlias::query()->updateOrCreate(
            [
                'provider' => 'thegamesdb',
                'provider_game_id' => (string) $entry->external_id,
            ],
            [
                'product_id' => $product->id,
                'alias_title' => $entry->title,
            ]
        );
    }

    protected function syncConsole(Product $product, TheGamesDbGame $entry): void
    {
        $releaseDate = $this->normalizeDate($entry->release_date);

        Console::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'name' => $entry->title,
                'manufacturer' => Arr::get($entry->metadata, 'manufacturer') ?: $entry->publisher,
                'release_date' => $releaseDate?->toDateString(),
                'metadata' => array_filter([
                    'platform' => $entry->platform,
                    'players' => $entry->players,
                    'overview' => Arr::get($entry->metadata, 'overview'),
                    'raw' => Arr::get($entry->metadata, 'raw'),
                ], fn ($value) => $value !== null && $value !== '' && $value !== []),
            ]
        );
    }

    protected function syncVideoGame(Product $product, TheGamesDbGame $entry): void
    {
        $primaryGenre = is_array($entry->genres) ? Arr::first(array_filter($entry->genres, fn ($genre) => is_string($genre) && $genre !== '')) : null;

        $releaseDate = $this->normalizeDate($entry->release_date);

        VideoGame::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'title' => $entry->title,
                'genre' => $primaryGenre,
                'release_date' => $releaseDate?->toDateString(),
                'developer' => $entry->developer,
                'metadata' => array_filter([
                    'platform' => $entry->platform,
                    'publisher' => $entry->publisher,
                    'players' => $entry->players,
                    'overview' => Arr::get($entry->metadata, 'overview'),
                    'raw' => Arr::get($entry->metadata, 'raw'),
                ], fn ($value) => $value !== null && $value !== '' && $value !== []),
            ]
        );
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

    protected function computeUid(string $title, ?string $releaseDate, ?string $platformFamily): string
    {
        $normalized = Str::lower($title).'|'.($releaseDate ?? 'unknown').'|'.($platformFamily ?? 'unknown');

        return hash('sha256', $normalized);
    }

    protected function calculateFreshnessScore(?Carbon $releaseDate): float
    {
        if (! $releaseDate instanceof Carbon) {
            return 0.5;
        }

        if ($releaseDate->isFuture()) {
            return 1.0;
        }

        $days = $releaseDate->diffInDays(Carbon::now());
        $score = 1 - min($days, 730) / 730;

        return round(max(0.1, min(1.0, $score)), 3);
    }

    protected function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}