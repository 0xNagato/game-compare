<?php

namespace App\Services\Catalogue;

use App\Models\GiantBombGame;
use App\Models\TheGamesDbGame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileObject;
use Throwable;

class PriceCrossReferencer
{
    private const CACHE_VERSION = 'v3';

    public function __construct(
        private readonly ?string $giantBombFile = null,
        private readonly ?string $nexardaFile = null,
        private readonly ?string $priceGuideFile = null,
        private readonly ?int $cacheMinutes = null,
    ) {}

    public function build(): Collection
    {
        $ttl = now()->addMinutes(max(1, $this->cacheMinutes ?? (int) config('catalogue.cross_reference.cache_minutes', 45)));

        if ($this->shouldBypassPersistentCache()) {
            return $this->buildCollection();
        }

        return Cache::remember($this->cacheKey(), $ttl, fn (): Collection => $this->buildCollection());
    }

    private function shouldBypassPersistentCache(): bool
    {
        if (config('catalogue.cross_reference.runtime_only', false)) {
            return true;
        }

        return Cache::getDefaultDriver() === 'file';
    }

    private function buildCollection(): Collection
    {
        $giantBomb = $this->parseGiantBomb();
        $nexarda = $this->indexNexarda();
        $priceGuide = $this->indexPriceGuide();
        $mirror = $this->indexTheGamesDb();

        if ($giantBomb->isEmpty() && $nexarda->isEmpty() && $priceGuide->isEmpty() && $mirror->isEmpty()) {
            return collect();
        }

        $keys = collect()
            ->merge($giantBomb->keys())
            ->merge($nexarda->keys())
            ->merge($priceGuide->keys())
            ->merge($mirror->keys())
            ->unique()
            ->values();

        return $keys
            ->map(function (string $key) use ($giantBomb, $nexarda, $priceGuide, $mirror) {
                $game = $giantBomb->get($key);
                $digital = $nexarda->get($key);
                $mirrorEntry = $mirror->get($key);

                if (is_array($digital)) {
                    $currencies = collect($digital['currencies'] ?? [])
                        ->filter(fn (array $row) => array_key_exists('amount', $row))
                        ->values();

                    if ($currencies->isEmpty()) {
                        $digital = null;
                    } else {
                        $digital['currencies'] = $currencies->all();
                        $digital['best'] = $currencies
                            ->filter(fn (array $row) => $row['amount'] !== null)
                            ->sortBy('amount')
                            ->first();
                    }
                } else {
                    $digital = null;
                }

                $rawPhysical = $priceGuide->get($key, []);
                $physicalAll = collect(is_array($rawPhysical) ? $rawPhysical : [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(function (array $row) {
                        if (! isset($row['formatted_price']) && $row['price'] !== null) {
                            $row['formatted_price'] = $this->formatUsd($row['price'], null);
                        }

                        return $row;
                    })
                    ->values();

                $bestPhysical = $physicalAll
                    ->filter(fn (array $row) => $row['price'] !== null)
                    ->sortBy('price')
                    ->first();

                $physical = $physicalAll
                    ->sortBy(function (array $row) {
                        return $row['price'] ?? INF;
                    })
                    ->take(6)
                    ->values();

                if (! $game && ! $digital && $physical->isEmpty() && ! $mirrorEntry) {
                    return null;
                }

                $name = $game['name']
                    ?? ($mirrorEntry['name'] ?? null)
                    ?? ($digital['name'] ?? null)
                    ?? ($physical->first()['product_name'] ?? null)
                    ?? (string) Str::of($key)->headline();

                $platforms = collect()
                    ->merge($game['platforms'] ?? [])
                    ->merge($digital['platforms'] ?? [])
                    ->merge($mirrorEntry ? array_filter([$mirrorEntry['platform'] ?? null]) : [])
                    ->merge($physical->pluck('console')->filter()->all())
                    ->filter()
                    ->map(fn ($value) => is_string($value) ? $value : null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $image = $game['image'] ?? ($mirrorEntry['image'] ?? null);
                $image = is_string($image) ? trim($image) : null;

                if ($image === null || $image === '') {
                    return null;
                }

                return [
                    'guid' => $game['guid']
                        ?? ($mirrorEntry && isset($mirrorEntry['id']) ? 'mirror-'.$mirrorEntry['id'] : null),
                    'name' => $name,
                    'image' => $image,
                    'normalized' => $key,
                    'digital' => $digital,
                    'physical' => $physical->all(),
                    'best_physical' => $bestPhysical,
                    'platforms' => $platforms,
                    'has_digital' => $digital !== null,
                    'has_physical' => $physical->isNotEmpty(),
                ];
            })
            ->filter()
            ->values();
    }

    private function cacheKey(): string
    {
        return 'catalogue:crossref:'.self::CACHE_VERSION.':giantbomb-nexarda-priceguide';
    }

    private function parseGiantBomb(): Collection
    {
        $fromDatabase = $this->loadGiantBombFromDatabase();
        if ($fromDatabase->isNotEmpty()) {
            return $fromDatabase;
        }

        return $this->loadGiantBombFromFile();
    }

    private function loadGiantBombFromDatabase(): Collection
    {
        try {
            $games = GiantBombGame::query()
                ->select([
                    'guid',
                    'name',
                    'primary_image_url',
                    'image_super_url',
                    'image_original_url',
                    'image_small_url',
                    'platforms',
                    'normalized_name',
                ])
                ->orderByDesc('last_synced_at')
                ->limit(8000)
                ->get();
        } catch (Throwable) {
            return collect();
        }

        if ($games->isEmpty()) {
            return collect();
        }

        return $games->mapWithKeys(function (GiantBombGame $game): array {
            $normalized = $game->normalized_name ?? $this->normalizeName($game->name);

            if ($normalized === null) {
                return [];
            }

            $image = $game->primary_image_url
                ?? $game->image_super_url
                ?? $game->image_original_url
                ?? $game->image_small_url;

            $platforms = [];
            if (is_array($game->platforms)) {
                $platforms = collect($game->platforms)
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->values()
                    ->all();
            }

            return [$normalized => [
                'guid' => $game->guid,
                'name' => $game->name,
                'image' => $image,
                'normalized' => $normalized,
                'platforms' => $platforms,
            ]];
        });
    }

    private function loadGiantBombFromFile(): Collection
    {
        $path = $this->resolvePath($this->giantBombFile ?? config('catalogue.cross_reference.giant_bomb_catalogue_file'))
            ?? base_path('giant_bomb_games_detailed.json');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return collect();
        }

        try {
            $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return collect();
        }

        if (! is_array($payload)) {
            return collect();
        }

        return collect($payload)
            ->filter(fn ($row) => is_array($row))
            ->mapWithKeys(function (array $row, $guid) {
                $name = $row['name'] ?? null;
                $normalized = $this->normalizeName($name);

                if (! $name || ! $normalized) {
                    return [];
                }

                return [$normalized => [
                    'guid' => is_string($guid) ? $guid : ($row['guid'] ?? null),
                    'name' => $name,
                    'image' => $this->resolveImage($row),
                    'normalized' => $normalized,
                    'platforms' => $this->extractPlatforms($row),
                ]];
            });
    }

    private function indexNexarda(): Collection
    {
        $path = $this->resolvePath($this->nexardaFile ?? config('catalogue.nexarda.local_catalogue_file'))
            ?? base_path('nexarda_product_catalogue.json');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return collect();
        }

        try {
            $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return collect();
        }

        $games = $payload['games'] ?? $payload['items'] ?? $payload;
        if (! is_array($games)) {
            return collect();
        }

        return collect($games)
            ->filter(fn ($row) => is_array($row))
            ->mapWithKeys(function (array $row) {
                $name = $row['name'] ?? null;
                $normalized = $this->normalizeName($name);
                if (! $normalized) {
                    return [];
                }

                $prices = $row['prices'] ?? [];
                $discounts = $row['discounts'] ?? [];

                $currencies = collect($prices)
                    ->map(function ($value, $currency) use ($discounts) {
                        $amount = $this->normalizeNumber($value);
                        $discount = $this->normalizeNumber($discounts[$currency] ?? null);

                        if ($amount === null) {
                            return null;
                        }

                        $code = strtoupper((string) $currency);

                        return [
                            'code' => $code,
                            'amount' => $amount,
                            'formatted' => $amount === 0.0 ? 'Free' : $this->formatCurrency($amount, $code),
                            'discount' => $discount !== null ? (int) round($discount) : null,
                            'is_free' => $amount === 0.0,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [$normalized => [
                    'source' => 'nexarda',
                    'name' => $name,
                    'slug' => $this->sanitizeNexardaSlug($row['slug'] ?? null),
                    'url' => $this->buildNexardaUrl($row['slug'] ?? null),
                    'currencies' => $currencies,
                ]];
            });
    }

    private function indexPriceGuide(): Collection
    {
        $path = $this->resolvePath($this->priceGuideFile ?? config('catalogue.cross_reference.price_guide_file'))
            ?? base_path('price-guide.csv');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return collect();
        }

        try {
            $file = new SplFileObject($path);
        } catch (Throwable) {
            return collect();
        }

        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headers = null;
        $rows = collect();

        foreach ($file as $row) {
            if ($headers === null) {
                $headers = $row;
                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $data = $this->associateRow($headers, $row);
            if ($data === null) {
                continue;
            }

            $name = $data['product-name'] ?? null;
            $normalized = $this->normalizeName($name);
            if (! $normalized) {
                continue;
            }

            $priceRaw = $data['loose-price'] ?? null;
            $price = $this->normalizeNumber($priceRaw);

            $rows->push([
                'normalized' => $normalized,
                'product_name' => $name,
                'id' => $data['id'] ?? null,
                'console' => isset($data['console-name']) ? trim((string) $data['console-name']) : null,
                'price' => $price,
                'formatted_price' => $this->formatUsd($price, $priceRaw),
            ]);
        }

        return $rows
            ->groupBy('normalized')
            ->map(function (Collection $group): array {
                return $group
                    ->map(fn (array $row) => array_diff_key($row, ['normalized' => true]))
                    ->sortBy(function (array $row) {
                        return $row['price'] ?? INF;
                    })
                    ->values()
                    ->all();
            });
    }

    private function indexTheGamesDb(): Collection
    {
        try {
            $games = TheGamesDbGame::query()
                ->select(['external_id', 'title', 'slug', 'platform', 'image_url', 'thumb_url'])
                ->where(function ($query): void {
                    $query->whereNotNull('image_url')
                        ->orWhereNotNull('thumb_url');
                })
                ->limit(5000)
                ->get();
        } catch (Throwable) {
            return collect();
        }

        return $games
            ->mapWithKeys(function (TheGamesDbGame $game): array {
                $normalized = $this->normalizeName($game->title);
                if (! $normalized) {
                    return [];
                }

                $image = $game->image_url ?: $game->thumb_url;
                if (! is_string($image) || trim($image) === '') {
                    return [];
                }

                return [$normalized => [
                    'id' => $game->external_id,
                    'name' => $game->title,
                    'image' => $image,
                    'platform' => $game->platform,
                    'slug' => $game->slug,
                    'thumb' => $game->thumb_url,
                ]];
            });
    }

    private function resolvePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function normalizeName(?string $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $clean = preg_replace('/(\[[^\]]*\]|\([^)]*\))/u', ' ', $name) ?? $name;
        $normalized = Str::of($clean)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->value();

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveImage(array $row): ?string
    {
        $image = $row['image'] ?? null;
        if (is_array($image)) {
            foreach (['super_url', 'original_url', 'small_url'] as $key) {
                if (! empty($image[$key]) && is_string($image[$key])) {
                    return $image[$key];
                }
            }
        }

        $gallery = $row['images'] ?? null;
        if (is_array($gallery)) {
            foreach ($gallery as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                foreach (['super_url', 'original_url', 'small_url'] as $key) {
                    if (! empty($candidate[$key]) && is_string($candidate[$key])) {
                        return $candidate[$key];
                    }
                }
            }
        }

        return null;
    }

    private function normalizeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === 'unavailable') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            $value = preg_replace('/[^0-9.\-]/', '', $value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function associateRow(?array $headers, array $row): ?array
    {
        if (! is_array($headers)) {
            return null;
        }

        $headerCount = count($headers);
        if (count($row) < $headerCount) {
            $row = array_pad($row, $headerCount, null);
        }

        $assoc = [];
        foreach ($headers as $index => $column) {
            if (! is_string($column) || $column === '') {
                continue;
            }

            $assoc[$column] = $row[$index] ?? null;
        }

        return $assoc;
    }

    private function formatCurrency(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2, '.', ',');

        return match ($currency) {
            'USD' => '$'.$formatted,
            'EUR' => '€'.$formatted,
            'GBP' => '£'.$formatted,
            'AUD' => 'A$'.$formatted,
            'CAD' => 'C$'.$formatted,
            default => $currency.' '.$formatted,
        };
    }

    private function formatUsd(?float $amount, mixed $raw): ?string
    {
        if ($amount === null) {
            if (is_string($raw) && trim($raw) !== '') {
                return trim($raw);
            }

            return null;
        }

        $formatted = number_format($amount, 2, '.', ',');
        return '$'.$formatted;
    }

    private function extractPlatforms(array $row): array
    {
        $platforms = $row['platforms'] ?? null;

        if (! is_array($platforms)) {
            return [];
        }

        return collect($platforms)
            ->map(function ($platform) {
                if (is_array($platform)) {
                    return $platform['name'] ?? ($platform['abbreviation'] ?? null);
                }

                return is_string($platform) ? $platform : null;
            })
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function sanitizeNexardaSlug(?string $slug): ?string
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $trimmed = ltrim($slug, '/');

        return $trimmed !== '' ? $trimmed : null;
    }

    private function buildNexardaUrl(?string $slug): ?string
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        if (str_starts_with($slug, 'http://') || str_starts_with($slug, 'https://')) {
            return $slug;
        }

        return 'https://www.nexarda.com/'.ltrim($slug, '/');
    }
}