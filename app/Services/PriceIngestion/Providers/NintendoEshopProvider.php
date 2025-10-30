<?php

namespace App\Services\PriceIngestion\Providers;

use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NintendoEshopProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.ec.nintendo.com/v1',
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param  array<string, mixed>  $options
    * @return array{
    *   results: array<int, array{
    *     game: array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>},
    *     deals: array<int, array{
    *       deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>
    *     }>
    *   }>,
    *   meta: array<string, mixed>
    * }
     */
    public function fetchDeals(array $options = []): array
    {
        $catalog = $this->normalizeCatalog(Arr::get($options, 'catalog', []));

        if ($catalog->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => 'nintendo_eshop',
                    'generated_at' => now()->toIso8601String(),
                    'product_count' => 0,
                    'message' => 'No Nintendo eShop catalog entries configured.',
                ],
            ];
        }

        $regions = $this->normalizeRegions(Arr::get($options, 'countries', []));

        if ($regions->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => 'nintendo_eshop',
                    'generated_at' => now()->toIso8601String(),
                    'product_count' => $catalog->count(),
                    'message' => 'No Nintendo eShop region definitions configured.',
                ],
            ];
        }

        $dealsBySlug = [];

        foreach ($regions as $region) {
            $payload = $this->requestPrices($region, $catalog->keys()->all());

            foreach (data_get($payload, 'prices', []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $nsuid = (string) ($entry['title_id'] ?? $entry['id'] ?? '');

                if ($nsuid === '' || ! $catalog->has($nsuid)) {
                    continue;
                }

                $record = $catalog->get($nsuid);

                if (! is_array($record)) {
                    continue;
                }
                /** @var array{nsuid:string, product_slug:string, title:string, platform:string, category:string} $record */

                $regular = Arr::get($entry, 'regular_price.amount');
                $currency = Arr::get($entry, 'regular_price.currency', $region['currency']);

                if ($regular === null || ! is_numeric($regular)) {
                    continue;
                }

                $sale = Arr::get($entry, 'discount_price.amount');
                $salePrice = is_numeric($sale) && (float) $sale > 0 ? (float) $sale : (float) $regular;
                $normalPrice = (float) $regular;

                $timestamp = $this->resolveTimestamp($entry);

                $dealsBySlug[$record['product_slug']][] = [
                    'deal_id' => sprintf('eshop:%s:%s', Str::lower($region['country']), $nsuid),
                    'store_id' => $region['store_id'],
                    'sale_price' => round($salePrice, 2),
                    'normal_price' => round($normalPrice, 2),
                    'currency' => Str::upper(is_string($currency) && $currency !== '' ? $currency : $region['currency']),
                    'region_code' => $region['region_code'],
                    'last_change' => $timestamp,
                    'extras' => [
                        'raw_price' => $entry,
                        'country' => $region['country'],
                        'language' => $region['language'],
                    ],
                ];
            }
        }

    $results = $catalog->map(function (array $record) use (&$dealsBySlug) {
            $deals = $dealsBySlug[$record['product_slug']] ?? [];

            if (empty($deals)) {
                return null;
            }

            return [
                'game' => [
                    'title' => $record['title'],
                    'slug' => $record['product_slug'],
                    'platform' => $record['platform'],
                    'category' => $record['category'],
                    'metadata' => array_filter([
                        'source' => 'nintendo_eshop',
                        'nsuid' => $record['nsuid'],
                        'regions' => array_values(array_unique(array_map(fn ($deal) => $deal['region_code'], $deals))),
                    ]),
                ],
                'deals' => $deals,
            ];
    })->filter()->values()->all();

    /** @var array<int, array{game: array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}, deals: array<int, array{deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>}>}> $results */

        return [
            'results' => $results,
            'meta' => [
                'provider' => 'nintendo_eshop',
                'generated_at' => now()->toIso8601String(),
                'product_count' => $catalog->count(),
                'stub' => false,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $catalog
     * @return Collection<string, array{nsuid:string, product_slug:string, title:string, platform:string, category:string}>
     */
    protected function normalizeCatalog(array $catalog): Collection
    {
        /** @var Collection<string, array{nsuid:string, product_slug:string, title:string, platform:string, category:string}> $out */
        $out = collect($catalog)
            ->map(function ($entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $nsuid = (string) ($entry['nsuid'] ?? $entry['title_id'] ?? '');
                $slug = (string) ($entry['product_slug'] ?? $entry['slug'] ?? '');

                if ($nsuid === '' || $slug === '') {
                    return null;
                }

                return [
                    'nsuid' => $nsuid,
                    'product_slug' => $slug,
                    'title' => (string) ($entry['title'] ?? Str::headline(str_replace('-', ' ', $slug))),
                    'platform' => (string) ($entry['platform'] ?? 'Nintendo Switch'),
                    'category' => (string) ($entry['category'] ?? 'Game'),
                ];
            })
            ->filter(fn ($row) => is_array($row))
            ->keyBy('nsuid');
        return $out;
    }

    /**
     * @param  array<int, mixed>  $regions
     * @return Collection<int, array{country:string, language:string, region_code:string, currency:string, store_id:string}>
     */
    protected function normalizeRegions(array $regions): Collection
    {
        /** @var Collection<int, array{country:string, language:string, region_code:string, currency:string, store_id:string}> $result */
        $result = collect($regions)
            ->map(function ($region): ?array {
                if (! is_array($region)) {
                    return null;
                }

                $country = Str::upper((string) ($region['country'] ?? 'US'));
                $language = Str::lower((string) ($region['language'] ?? 'en'));

                return [
                    'country' => $country,
                    'language' => $language,
                    'region_code' => Str::upper((string) ($region['region_code'] ?? $country)),
                    'currency' => Str::upper((string) ($region['currency'] ?? 'USD')),
                    'store_id' => (string) ($region['store_id'] ?? sprintf('nintendo_eshop_%s', Str::lower($country))),
                ];
            })
            ->filter(function ($region): bool {
                return is_array($region) && $region['country'] !== '' && $region['store_id'] !== '';
            })
            ->unique(function ($region) {
                return is_array($region) ? ($region['country'].':'.$region['language']) : null;
            })
            ->values();
        return $result;
    }

    /**
     * @param  array{country:string, language:string, region_code:string, currency:string, store_id:string}  $region
     * @param  array<int, string>  $nsuids
     * @return array<string, mixed>
     */
    protected function requestPrices(array $region, array $nsuids): array
    {
        $response = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/price', [
                'country' => $region['country'],
                'lang' => $region['language'],
                'ids' => implode(',', $nsuids),
            ]);

        if ($response->failed()) {
            throw new ProviderException(sprintf('Nintendo eShop price request failed for country [%s].', $region['country']));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ProviderException('Nintendo eShop price payload was invalid.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function resolveTimestamp(array $entry): int
    {
        $date = Arr::get($entry, 'discount_price.start_datetime')
            ?? Arr::get($entry, 'regular_price.start_datetime')
            ?? Arr::get($entry, 'regular_price.updated_at');

        if (! is_string($date) || $date === '') {
            return (int) now()->timestamp;
        }

        try {
            return (int) Carbon::parse($date)->timestamp;
        } catch (\Throwable) {
            return (int) now()->timestamp;
        }
    }
}
