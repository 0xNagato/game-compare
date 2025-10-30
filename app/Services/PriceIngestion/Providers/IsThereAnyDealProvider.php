<?php

namespace App\Services\PriceIngestion\Providers;

use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IsThereAnyDealProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.isthereanydeal.com',
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
        $apiKey = $this->resolveApiKey($options);

        $requests = collect(Arr::get($options, 'requests', []))
            ->filter(fn ($request) => is_array($request) && $this->hasLookupContext($request))
            ->values();

        if ($requests->isEmpty()) {
            return [
                'results' => [],
                'meta' => [
                    'provider' => 'itad',
                    'generated_at' => now()->toIso8601String(),
                    'product_count' => 0,
                    'message' => 'No IsThereAnyDeal plains or titles were provided.',
                ],
            ];
        }

        $globalStoreMap = $this->normalizeStoreMap(Arr::get($options, 'store_map', []));

    $results = $requests->map(function (array $request) use ($apiKey, $globalStoreMap, $options) {
            $plain = $this->resolvePlain($apiKey, $request);

            if (! $plain) {
                return null;
            }

            $regions = $this->normalizeRegions($request['regions'] ?? Arr::get($options, 'default_regions', []));

            if ($regions->isEmpty()) {
                return null;
            }

            $storeMap = $this->mergeStoreMaps($globalStoreMap, $this->normalizeStoreMap($request['store_map'] ?? []));

            $deals = $regions->flatMap(function (array $region) use ($apiKey, $plain, $storeMap) {
                $payload = $this->requestPrices($apiKey, $plain, $region);

                return $this->transformOffers($payload, $plain, $region, $storeMap);
            })->filter()->groupBy('store_id')->map(fn (Collection $group) => $group->sortBy('sale_price')->first())->values();

            if ($deals->isEmpty()) {
                return null;
            }

            $descriptor = $this->buildProductDescriptor($request, $plain, $deals);

            return [
                'game' => $descriptor,
                'deals' => $deals->all(),
            ];
    })->filter()->values()->all();

    /** @var array<int, array{game: array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}, deals: array<int, array{deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>}>}> $results */

        return [
            'results' => $results,
            'meta' => [
                'provider' => 'itad',
                'generated_at' => now()->toIso8601String(),
                'product_count' => $requests->count(),
                'stub' => false,
            ],
        ];
    }

    protected function resolveApiKey(array $options): string
    {
        $apiKey = Arr::get($options, 'api_key')
            ?? env('ITAD_API_KEY')
            ?? env('ISTHEREANYDEAL_API_KEY')
            ?? env('ISTHEREADEAL_API_KEY');

        if (! is_string($apiKey) || blank($apiKey)) {
            throw new ProviderException('IsThereAnyDeal API key is required.');
        }

        return $apiKey;
    }

    protected function hasLookupContext(array $request): bool
    {
        return filled($request['plain'] ?? null)
            || filled($request['title'] ?? null)
            || filled(Arr::get($request, 'product.title'));
    }

    protected function resolvePlain(string $apiKey, array $request): ?string
    {
        $plain = Arr::get($request, 'plain');

        if (is_string($plain) && $plain !== '') {
            return Str::snake($plain, '-');
        }

        $title = Arr::get($request, 'title', Arr::get($request, 'product.title'));

        if (! is_string($title) || blank($title)) {
            return null;
        }

        $searchPayload = $this->requestSearch($apiKey, $title);
        $result = collect(data_get($searchPayload, 'data.results', []))
            ->firstWhere('title', $title) ?? collect(data_get($searchPayload, 'data.results', []))->first();

        $resolved = is_array($result) ? ($result['plain'] ?? null) : null;

        return is_string($resolved) ? $resolved : null;
    }

    /**
     * @param  array<int, mixed>  $regions
     */
    /**
     * @param  array<int, mixed>  $regions
     * @return Collection<int, array{currency:string, country:string, region:string, region_code:string}>
     */
    protected function normalizeRegions(array $regions): Collection
    {
        $collection = collect($regions)
            ->map(function ($region): ?array {
                if (! is_array($region)) {
                    return null;
                }

                $currency = Str::upper((string) Arr::get($region, 'currency', 'USD'));
                $country = Str::lower((string) Arr::get($region, 'country', 'us'));
                $area = Str::lower((string) Arr::get($region, 'region', $country));

                return [
                    'currency' => $currency,
                    'country' => $country,
                    'region' => $area,
                    'region_code' => Str::upper((string) Arr::get($region, 'region_code', $country)),
                ];
            })
            ->filter(fn ($region) => is_array($region) && filled($region['currency']) && filled($region['country']))
            ->unique(fn ($region) => is_array($region) ? $region['currency'].':'.$region['country'].':'.$region['region'] : null)
            ->values();

        /** @var Collection<int, array{currency:string, country:string, region:string, region_code:string}> $collection */
        return $collection;
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $storeMap
     */
    /**
     * @param  array<string, array<string, array<string, string>>>  $storeMap
     * @return Collection<string, Collection<string, array{store_id:string, region_code:string, currency:string}>>
     */
    protected function normalizeStoreMap(array $storeMap): Collection
    {
        return collect($storeMap)
            ->mapWithKeys(function ($currencies, $storeName): array {
                $normalizedCurrencies = collect($currencies ?? [])
                    ->mapWithKeys(function ($config, $currency): array {
                        $currencyCode = Str::upper((string) $currency);

                        return [
                            $currencyCode => [
                                'store_id' => (string) Arr::get($config, 'store_id'),
                                'region_code' => Str::upper((string) Arr::get($config, 'region_code', 'GLOBAL')),
                                'currency' => $currencyCode,
                            ],
                        ];
                    })
                    ->filter(fn ($config) => filled($config['store_id'] ?? null));

                return [Str::lower($storeName) => $normalizedCurrencies];
            })
            ->filter(fn (Collection $currencies) => $currencies->isNotEmpty());
    }

    /**
     * @param  Collection<string, Collection<string, array{store_id:string, region_code:string, currency:string}>>  $base
     * @param  Collection<string, Collection<string, array{store_id:string, region_code:string, currency:string}>>  $override
     * @return Collection<string, Collection<string, array{store_id:string, region_code:string, currency:string}>>
     */
    protected function mergeStoreMaps(Collection $base, Collection $override): Collection
    {
        return $base->map(function (Collection $currencies, string $store) use ($override): Collection {
            $overrideCurrencies = $override->get($store, collect());

            return $currencies->merge($overrideCurrencies);
        })->merge($override->reject(fn ($currencies, $store) => $base->has($store)));
    }

    /**
     * @param  array<string, mixed>  $region
     * @return Collection<int, array<string, mixed>>
     */
    protected function transformOffers(array $payload, string $plain, array $region, Collection $storeMap): Collection
    {
        $offers = data_get($payload, "data.{$plain}.list", []);

        if (! is_array($offers) || empty($offers)) {
            return collect();
        }

        return collect($offers)
            ->map(function ($offer) use ($plain, $region, $storeMap): ?array {
                if (! is_array($offer)) {
                    return null;
                }

                $storeSlug = Str::lower((string) data_get($offer, 'shop.slug', data_get($offer, 'shop.id')));

                if ($storeSlug === '') {
                    return null;
                }

                $currency = $region['currency'];
                $storeConfig = $storeMap->get($storeSlug)?->get($currency) ?? [
                    'store_id' => sprintf('itad_%s_%s', $storeSlug, Str::lower($currency)),
                    'region_code' => $region['region_code'],
                    'currency' => $currency,
                ];

                $salePrice = (float) ($offer['price_new'] ?? 0);

                if ($salePrice <= 0) {
                    return null;
                }

                $normalPrice = (float) ($offer['price_old'] ?? $salePrice);
                $timestamp = (int) ($offer['timestamp'] ?? $offer['added'] ?? now()->timestamp);

                return [
                    'deal_id' => sprintf('itad:%s:%s:%s', $plain, $storeSlug, Str::lower($currency)),
                    'store_id' => $storeConfig['store_id'],
                    'sale_price' => round($salePrice, 2),
                    'normal_price' => round($normalPrice, 2),
                    'currency' => $storeConfig['currency'] ?? $currency,
                    'region_code' => $storeConfig['region_code'] ?? $region['region_code'],
                    'last_change' => $timestamp,
                    'extras' => array_filter([
                        'offer_url' => $offer['url'] ?? null,
                        'shop' => $offer['shop'] ?? null,
                        'price_cut' => $offer['price_cut'] ?? null,
                        'drm' => $offer['drm'] ?? null,
                    ], fn ($value) => $value !== null && $value !== []),
                ];
            })
        ->filter();
    }

    /**
     * @param  Collection<int, array{
     *   deal_id:string, store_id:string, sale_price:float, normal_price:float, currency:string, region_code:string, last_change:int, extras: array<string, mixed>
     * }> $deals
     * @return array{title:string, slug:string, platform:string, category:string, metadata: array<string, mixed>}
     */
    protected function buildProductDescriptor(array $request, string $plain, Collection $deals): array
    {
        $product = (array) Arr::get($request, 'product', []);
        $title = Arr::get($product, 'title', Arr::get($request, 'title'));

        if (! is_string($title) || blank($title)) {
            $title = Str::headline(str_replace(['-', '_'], ' ', $plain));
        }

        $slug = Arr::get($product, 'slug', Str::slug($title));

        return [
            'title' => $title,
            'slug' => $slug,
            'platform' => Arr::get($product, 'platform', 'PC'),
            'category' => Arr::get($product, 'category', 'Game'),
            'metadata' => array_filter([
                'source' => 'itad',
                'plain' => $plain,
                'currencies' => $deals->pluck('currency')->unique()->values()->all(),
                'region_codes' => $deals->pluck('region_code')->unique()->values()->all(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestSearch(string $apiKey, string $query): array
    {
        $response = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/v02/search/search/', [
                'key' => $apiKey,
                'q' => $query,
            ]);

        if ($response->failed()) {
            throw new ProviderException('IsThereAnyDeal search request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ProviderException('IsThereAnyDeal search payload was invalid.');
        }

        return $payload;
    }

    /**
     * @param  array{country:string, region:string}  $region
     * @return array<string, mixed>
     */
    protected function requestPrices(string $apiKey, string $plain, array $region): array
    {
        $response = Http::timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->get('/v01/game/prices/', [
                'key' => $apiKey,
                'plains' => $plain,
                'country' => $region['country'],
                'region' => $region['region'],
            ]);

        if ($response->failed()) {
            throw new ProviderException(sprintf('IsThereAnyDeal price request failed for plain [%s].', $plain));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ProviderException('IsThereAnyDeal price payload was invalid.');
        }

        return $payload;
    }
}