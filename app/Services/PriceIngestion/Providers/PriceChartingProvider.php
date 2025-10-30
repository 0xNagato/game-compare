<?php

namespace App\Services\PriceIngestion\Providers;

use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PriceChartingProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://www.pricecharting.com/api',
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
        $token = $this->resolveToken($options);

        $catalog = $this->normalizeCatalog(Arr::get($options, 'catalog', []));

        if ($catalog->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => 'pricecharting',
                    'generated_at' => now()->toIso8601String(),
                    'product_count' => 0,
                    'message' => 'No PriceCharting catalog entries configured.',
                ],
            ];
        }

        $storeMap = collect(Arr::get($options, 'store_map', []));
        $regionCodes = $this->resolveRegionCodes($storeMap);

        if (empty($regionCodes)) {
            $regionCodes = ['US'];
        }

    $results = $catalog->map(function (array $entry) use ($token, $storeMap) {
            try {
                $productId = $this->resolveProductId($token, $entry);

                if ($productId === null) {
                    Log::notice('price_ingest.pricecharting_product_unresolved', [
                        'title' => $entry['title'],
                        'slug' => $entry['product_slug'],
                    ]);

                    return null;
                }

                $payload = $this->requestProduct($token, $productId);
                $product = data_get($payload, 'product');

                if (! is_array($product)) {
                    Log::notice('price_ingest.pricecharting_invalid_product_payload', [
                        'product_id' => $productId,
                    ]);

                    return null;
                }

                $deals = $this->transformProductPrices($product, $entry, $storeMap);

                if ($deals->isEmpty()) {
                    return null;
                }

                return [
                    'game' => [
                        'title' => $entry['title'],
                        'slug' => $entry['product_slug'],
                        'platform' => $entry['platform'],
                        'category' => $entry['category'],
                        'metadata' => array_filter([
                            'source' => 'pricecharting',
                            'pricecharting_id' => $product['id'] ?? $productId,
                            'console_name' => $product['console-name'] ?? null,
                        ]),
                    ],
                    'deals' => $deals->values()->all(),
                ];
            } catch (ProviderException $exception) {
                Log::warning('price_ingest.pricecharting_entry_failed', [
                    'slug' => $entry['product_slug'],
                    'title' => $entry['title'],
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
    })->filter()->values()->all();

    /** @var array<int, array{game: array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}, deals: array<int, array{deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>}>}> $results */

        return [
            'results' => $results,
            'meta' => [
                'provider' => 'pricecharting',
                'generated_at' => now()->toIso8601String(),
                'product_count' => $catalog->count(),
                'stub' => false,
                'regions' => $regionCodes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function resolveToken(array $options): string
    {
        $token = Arr::get($options, 'token', env('PRICECHARTING_TOKEN'));

        if (! is_string($token) || blank($token)) {
            throw new ProviderException('PriceCharting API token is required.');
        }

        return $token;
    }

    /**
     * @param  array<int, mixed>  $catalog
     * @return Collection<int, array{
     *   product_slug:string, title:string, platform:string, category:string, product_id: string|null, search: mixed
     * }>
     */
    protected function normalizeCatalog(array $catalog): Collection
    {
        /** @var Collection<int, array{product_slug:string, title:string, platform:string, category:string, product_id:string|null, search:mixed}> $out */
        $out = collect($catalog)
            ->map(function ($entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $slug = (string) ($entry['product_slug'] ?? $entry['slug'] ?? '');

                if ($slug === '') {
                    return null;
                }

                return [
                    'product_slug' => $slug,
                    'title' => (string) ($entry['title'] ?? Str::headline(str_replace('-', ' ', $slug))),
                    'platform' => (string) ($entry['platform'] ?? 'Multi-platform'),
                    'category' => (string) ($entry['category'] ?? 'Physical'),
                    'product_id' => is_string($entry['product_id'] ?? null) && $entry['product_id'] !== '' ? (string) $entry['product_id'] : null,
                    'search' => $entry['search'] ?? $entry['title'] ?? $slug,
                ];
        })
            ->filter(fn ($row) => is_array($row))
            ->values();
        return $out;
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function resolveProductId(string $token, array $entry): ?string
    {
        if (is_string($entry['product_id'] ?? null) && $entry['product_id'] !== '') {
            return $entry['product_id'];
        }

        $query = (string) ($entry['search'] ?? $entry['title']);

        if ($query === '') {
            return null;
        }

        $payload = $this->requestSearch($token, $query);

        $results = data_get($payload, 'products', []);

        if (! is_array($results) || empty($results)) {
            return null;
        }

        $match = collect($results)->first(function ($product) use ($entry) {
            $name = (string) ($product['product-name'] ?? $product['name'] ?? '');
            $console = (string) ($product['console-name'] ?? '');

            return Str::lower($name) === Str::lower($entry['title'])
                || Str::contains(Str::lower($name), Str::lower($entry['title']))
                || Str::contains(Str::lower($console), Str::lower($entry['platform']));
        }) ?? $results[0];

        $id = $match['product-id'] ?? $match['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, mixed>  $storeMap
     * @return Collection<int, array{
     *   deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>
     * }>
     */
    protected function transformProductPrices(array $product, array $entry, Collection $storeMap): Collection
    {
        $conditions = [
            'loose' => ['price_key' => 'loose-price', 'date_key' => 'loose-price-date'],
            'complete' => ['price_key' => 'complete-price', 'date_key' => 'complete-price-date'],
            'new' => ['price_key' => 'new-price', 'date_key' => 'new-price-date'],
        ];

        $items = collect($conditions)
            ->map(function (array $config, string $condition) use ($product, $storeMap): ?array {
                $price = $product[$config['price_key']] ?? null;

                if (! is_scalar($price)) {
                    return null;
                }

                $amount = (float) $price;

                if ($amount <= 0) {
                    return null;
                }

                $date = $product[$config['date_key']] ?? null;
                $timestamp = $this->parseDateToTimestamp($date);

                $storeConfig = $storeMap->get($condition);
                $storeId = $this->resolveStoreId($storeConfig, $condition);
                $regionCode = $this->resolveRegionCode($storeConfig) ?? 'US';

                return [
                    'deal_id' => sprintf('pricecharting:%s:%s', $condition, $product['id'] ?? Str::slug($storeId)),
                    'store_id' => $storeId,
                    'sale_price' => round($amount, 2),
                    'normal_price' => round($amount, 2),
                    'currency' => Str::upper($product['currency-code'] ?? 'USD'),
                    'region_code' => Str::upper($regionCode),
                    'last_change' => $timestamp,
                    'extras' => array_filter([
                        'condition' => $condition,
                        'raw_product' => $product,
                    ]),
                ];
            })
            ->filter()
            ->values();

        /** @var Collection<int, array{deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>}> $items */
        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestSearch(string $token, string $query): array
    {
        $response = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/products', [
                't' => $token,
                'q' => $query,
            ]);

        if ($response->failed()) {
            throw new ProviderException('PriceCharting search request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ProviderException('PriceCharting search payload was invalid.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestProduct(string $token, string $productId): array
    {
        $response = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/product', [
                't' => $token,
                'id' => $productId,
            ]);

        if ($response->failed()) {
            throw new ProviderException(sprintf('PriceCharting product request failed for id [%s].', $productId));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ProviderException('PriceCharting product payload was invalid.');
        }

        return $payload;
    }

    protected function parseDateToTimestamp(mixed $date): int
    {
        if (! is_string($date) || $date === '') {
            return (int) now()->timestamp;
        }

        try {
            $ts = strtotime($date);
            return is_int($ts) ? $ts : (int) now()->timestamp;
        } catch (\Throwable) {
            return (int) now()->timestamp;
        }
    }

    protected function resolveStoreId(mixed $storeConfig, string $condition): string
    {
        if (is_array($storeConfig)) {
            $storeId = $storeConfig['store_id'] ?? null;
        } else {
            $storeId = $storeConfig;
        }

        if (is_string($storeId) && $storeId !== '') {
            return $storeId;
        }

        return sprintf('pricecharting_%s_usd', $condition);
    }

    /**
     * @param  Collection<string, mixed>  $storeMap
     * @return array<int, string>
     */
    protected function resolveRegionCodes(Collection $storeMap): array
    {
        /** @var array<int, string> $codes */
        $codes = $storeMap
            ->map(fn ($storeConfig) => $this->resolveRegionCode($storeConfig))
            ->filter(fn ($region) => is_string($region) && $region !== '')
            ->unique()
            ->values()
            ->all();
        return $codes;
    }

    protected function resolveRegionCode(mixed $storeConfig): ?string
    {
        if (is_array($storeConfig)) {
            return isset($storeConfig['region_code']) && $storeConfig['region_code'] !== ''
                ? (string) $storeConfig['region_code']
                : null;
        }

        if (is_string($storeConfig)) {
            $config = config(sprintf('pricing.stores.%s', $storeConfig));

            if (is_array($config) && isset($config['region_code'])) {
                return (string) $config['region_code'];
            }
        }

        return null;
    }
}