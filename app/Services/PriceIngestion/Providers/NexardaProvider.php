<?php

namespace App\Services\PriceIngestion\Providers;

use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NexardaProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://www.nexarda.com/api/v3',
        private readonly int $timeout = 15,
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
        $products = collect(Arr::get($options, 'products', []))
            ->filter(fn ($product) => is_array($product) && filled($product['id'] ?? null))
            ->values();

        if ($products->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => 'nexarda',
                    'generated_at' => now()->toIso8601String(),
                    'product_count' => 0,
                    'message' => 'No NEXARDA products configured for ingestion.',
                ],
            ];
        }

        $storeMap = $this->normalizeStoreMap(Arr::get($options, 'store_map', []));
        $baseUrl = rtrim((string) Arr::get($options, 'base_url', $this->baseUrl), '/');
        $timeout = (int) Arr::get($options, 'timeout', $this->timeout);
        $apiKey = Arr::get($options, 'api_key');

    $results = $products->map(function (array $product) use ($options, $storeMap, $baseUrl, $timeout, $apiKey) {
            $regions = $this->resolveRegions($product, $options);

            if ($regions->isEmpty()) {
                return null;
            }

            $type = (string) ($product['type'] ?? 'game');
            $productId = $product['id'];

            $infoContext = [];

            $deals = $regions->flatMap(function (array $region) use ($type, $productId, &$infoContext, $storeMap, $baseUrl, $timeout, $apiKey) {
                $payload = $this->requestPrices($baseUrl, $timeout, $apiKey, $type, $productId, $region['currency']);

                if (empty($infoContext)) {
                    $infoContext = (array) Arr::get($payload, 'info', []);
                }

                return $this->buildDealsFromOffers($payload, $region, $storeMap, $type, $productId);
            })->filter()->values();

            if ($deals->isEmpty()) {
                return null;
            }

            $info = $this->buildGameDescriptor($product, $deals, $options, $infoContext);

            return [
                'game' => $info,
                'deals' => $deals->all(),
            ];
    })->filter()->values()->all();

    /** @var array<int, array{game: array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}, deals: array<int, array{deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>}>}> $results */

        return [
            'results' => $results,
            'meta' => [
                'provider' => 'nexarda',
                'generated_at' => now()->diffForHumans(),
                'product_count' => $products->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $region
     * @param  Collection<string, Collection<string, array<string, string>>>  $storeMap
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildDealsFromOffers(array $payload, array $region, Collection $storeMap, string $type, string|int $productId): Collection
    {
        $offers = Arr::get($payload, 'prices.list');

        if (! is_array($offers) || empty($offers)) {
            return collect();
        }

        $currency = Str::upper($region['currency']);

        return collect($offers)
            ->filter(fn ($offer) => is_array($offer) && ($offer['price'] ?? 0) > 0)
            ->map(function (array $offer) use ($region, $storeMap, $currency, $payload, $type, $productId) {
                $storeName = Str::lower((string) Arr::get($offer, 'store.name'));
                $mapped = $storeMap->get($storeName)?->get($currency);

                $storeConfig = $mapped ?? [
                    'store_id' => $region['store_id'],
                    'region_code' => $region['region_code'],
                    'currency' => $currency,
                ];

                if (! filled($storeConfig['store_id'] ?? null)) {
                    return null;
                }

                $salePrice = round((float) $offer['price'], 2);

                if ($salePrice <= 0) {
                    return null;
                }

                $normalPrice = (float) Arr::get($offer, 'coupon.price_without', Arr::get($payload, 'prices.highest', $salePrice));
                if ($normalPrice <= 0) {
                    $normalPrice = $salePrice;
                }

                return [
                    'deal_id' => sprintf(
                        'nexarda:%s:%s:%s:%s',
                        $type,
                        $productId,
                        Str::lower($currency),
                        Str::slug((string) Arr::get($offer, 'store.name', 'store'))
                    ),
                    'store_id' => $storeConfig['store_id'],
                    'sale_price' => $salePrice,
                    'normal_price' => round($normalPrice, 2),
                    'currency' => Str::upper((string) ($storeConfig['currency'] ?? $currency)),
                    'region_code' => Str::upper((string) ($storeConfig['region_code'] ?? $region['region_code'])),
                    'last_change' => now()->timestamp,
                    'extras' => array_filter([
                        'offer_url' => Arr::get($offer, 'url'),
                        'store' => Arr::get($offer, 'store'),
                        'max_discount' => Arr::get($payload, 'prices.max_discount'),
                        'offers_considered' => count(Arr::get($payload, 'prices.list', [])),
                    ]),
                ];
            })
            ->filter()
            ->groupBy('store_id')
            ->map(fn (Collection $group) => $group->sortBy('sale_price')->first())
            ->values();
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $storeMap
     * @return Collection<string, Collection<string, array<string, string>>>
     */
    protected function normalizeStoreMap(array $storeMap): Collection
    {
        return collect($storeMap)
            ->mapWithKeys(function ($currencies, $storeName) {
                $normalizedCurrencies = collect($currencies ?? [])
                    ->mapWithKeys(function ($config, $currency) {
                        $currencyCode = Str::upper((string) $currency);

                        return [
                            $currencyCode => [
                                'store_id' => (string) Arr::get($config, 'store_id'),
                                'region_code' => Str::upper((string) Arr::get($config, 'region_code', 'GLOBAL')),
                                'currency' => $currencyCode,
                            ],
                        ];
                    });

                return [Str::lower($storeName) => $normalizedCurrencies];
            });
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  Collection<int, array<string, string>>  $deals
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $product
     * @param  Collection<int, array{
     *   deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>
     * }> $deals
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $infoContext
     * @return array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}
     */
    protected function buildGameDescriptor(array $product, Collection $deals, array $options, array $infoContext): array
    {
        $metadata = array_filter([
            'source' => 'nexarda',
            'nexarda_id' => $product['id'],
            'nexarda_slug' => $this->resolveSlug($product, $infoContext),
            'currencies' => $deals->pluck('currency')->unique()->values()->all(),
            'region_codes' => $deals->pluck('region_code')->unique()->values()->all(),
            'ingest_context' => Arr::get($options, 'context'),
            'cover' => Arr::get($infoContext, 'cover'),
            'banner' => Arr::get($infoContext, 'banner'),
            'release_timestamp' => Arr::get($infoContext, 'release'),
        ], fn ($value) => $value !== null && $value !== []);

        return [
            'title' => Arr::get($product, 'title', Arr::get($infoContext, 'name', 'Unknown Title')),
            'slug' => Arr::get($product, 'slug', $this->resolveSlug($product, $infoContext)),
            'platform' => Arr::get($product, 'platform', 'Unknown'),
            'category' => Arr::get($product, 'category', 'Game'),
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $infoContext
     */
    protected function resolveSlug(array $product, array $infoContext): string
    {
        $slug = Arr::get($product, 'slug');

        if (filled($slug)) {
            return (string) $slug;
        }

        $infoSlug = Arr::get($infoContext, 'slug');

        if (filled($infoSlug)) {
            return Str::of((string) $infoSlug)
                ->afterLast('/')
                ->replace(['(', ')'], '')
                ->slug('-')
                ->toString();
        }

        return Str::slug((string) Arr::get($product, 'title', 'unknown'));
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $options
     * @return Collection<int, array{currency: string, region_code: string, store_id: string}>
     */
    protected function resolveRegions(array $product, array $options): Collection
    {
        $defaults = Arr::get($options, 'default_regions', []);

        $productRegions = collect($product['regions'] ?? [])->map(fn ($region) => $this->normalizeRegionDefinition($region));

        $fallbackRegions = collect($defaults)->map(fn ($region) => $this->normalizeRegionDefinition($region));

        return $productRegions
            ->concat($fallbackRegions)
            ->filter(fn (array $region) => filled($region['currency']) && filled($region['region_code']))
            ->unique(fn (array $region) => $region['currency'])
            ->values();
    }

    protected function defaultStoreId(string $currency): string
    {
        return 'nexarda_'.Str::lower($currency);
    }

    /**
     * @param  array<string, mixed>  $region
     * @return array{currency: string, region_code: string, store_id: string}
     */
    protected function normalizeRegionDefinition(array $region): array
    {
        $currency = Str::upper((string) Arr::get($region, 'currency', 'USD'));

        return [
            'currency' => $currency,
            'region_code' => Str::upper((string) Arr::get($region, 'region_code', 'US')),
            'store_id' => (string) Arr::get($region, 'store_id', $this->defaultStoreId($currency)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function requestPrices(string $baseUrl, int $timeout, ?string $apiKey, string $type, string|int $id, string $currency): array
    {
        $query = array_filter([
            'type' => $type,
            'id' => $id,
            'currency' => $currency,
            'key' => $apiKey,
        ], static fn ($value) => $value !== null && $value !== '');

        $response = Http::acceptJson()
            ->timeout($timeout)
            ->retry(3, 200)
            ->withHeaders($this->authHeaders($apiKey))
            ->get($baseUrl.'/prices', $query);

        if ($response->failed()) {
            throw new ProviderException(sprintf('NEXARDA price request failed for ID [%s] (%s).', $id, $response->status()));
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            throw new ProviderException(sprintf('Unexpected NEXARDA response for ID [%s].', $id));
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(?string $apiKey): array
    {
        if (! filled($apiKey)) {
            return [];
        }

        return [
            'X-Api-Key' => $apiKey,
        ];
    }
}
