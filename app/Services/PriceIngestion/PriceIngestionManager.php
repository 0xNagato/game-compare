<?php

namespace App\Services\PriceIngestion;

use App\Models\Country;
use App\Models\Currency;
use App\Models\DatasetSnapshot;
use App\Models\ExchangeRate;
use App\Models\LocalCurrency;
use App\Models\Product;
use App\Models\ProviderUsage;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PriceIngestionManager
{
    /**
     * @param  array<int, string>  $providers
     * @param  array<string, mixed>  $context
     */
    public function ingestWithRotation(array $providers, array $context = []): void
    {
        $provider = $this->selectNextProvider($providers);

        if ($provider === null) {
            throw new ModelNotFoundException('No enabled pricing providers available for rotation.');
        }

        $this->ingest($provider, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function ingest(string $provider, array $context = []): void
    {
        $providerConfig = config(sprintf('pricing.providers.%s', $provider));

        if (! $providerConfig) {
            throw new ModelNotFoundException("Unknown pricing provider [{$provider}].");
        }

        if (($providerConfig['enabled'] ?? true) !== true) {
            throw new RuntimeException("Pricing provider [{$provider}] is disabled.");
        }

        $this->recordProviderUsage($provider);

        if (! isset($providerConfig['class'])) {
            throw new RuntimeException(sprintf('Pricing provider [%s] is missing a class binding.', $provider));
        }

        /** @var class-string $providerClass */
        $providerClass = (string) $providerConfig['class'];

        $providerInstance = app($providerClass);

        if (! method_exists($providerInstance, 'fetchDeals')) {
            throw new RuntimeException(sprintf('%s must implement fetchDeals().', $providerConfig['class']));
        }

        $snapshot = DatasetSnapshot::create([
            'kind' => 'price_ingest',
            'provider' => $provider,
            'status' => 'running',
            'started_at' => now(),
            'context' => $context,
        ]);

        try {
            $payload = $providerInstance->fetchDeals($providerConfig['options'] ?? []);

            $rowCount = 0;
            $regionIds = [];

            DB::transaction(function () use (&$rowCount, &$regionIds, $payload, $provider, $snapshot): void {
                $results = Arr::get($payload, 'results', []);

                foreach ($results as $result) {
                    if (! is_array($result)) {
                        continue;
                    }

                    [$product, $created] = $this->findOrCreateProduct($result['game'] ?? [], $provider);

                    if ($created) {
                        Log::info('price_ingest.product_created', [
                            'provider' => $provider,
                            'product_id' => $product->id,
                            'slug' => $product->slug,
                        ]);
                    }

                    $deals = collect($result['deals'] ?? [])
                        ->filter(fn ($deal) => is_array($deal) && filled($deal['store_id'] ?? null))
                        ->all();

                    foreach ($deals as $deal) {
                        $storeId = (string) $deal['store_id'];
                        $storeConfig = config(sprintf('pricing.stores.%s', $storeId));

                        if (! $storeConfig) {
                            continue;
                        }

                        $skuRegion = null;

                        try {
                            $skuRegion = $this->findOrCreateSkuRegion($product, $storeConfig, $deal, $provider);
                            $regionIds[] = $skuRegion->id;

                            if ($this->persistPricePoint($skuRegion, $deal, $storeConfig, $provider)) {
                                $rowCount++;
                            }
                        } catch (ProviderException $exception) {
                            Log::warning('price_ingest.deal_failed', [
                                'provider' => $provider,
                                'snapshot_id' => $snapshot->id,
                                'product_id' => $product->id,
                                'sku_region_id' => $skuRegion?->id,
                                'deal' => $deal,
                                'error' => $exception->getMessage(),
                            ]);

                            continue;
                        }
                    }
                }
            });

            $snapshot->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'row_count' => $rowCount,
                'context' => array_merge($snapshot->context ?? [], [
                    'sku_region_ids' => array_values(array_unique($regionIds)),
                    'meta' => Arr::get($payload, 'meta'),
                ]),
            ]);

            $this->touchRegionsFromContext($snapshot->context ?? []);
        } catch (ProviderException $exception) {
            Log::warning('price_ingest.provider_failed', [
                'provider' => $provider,
                'snapshot_id' => $snapshot->id,
                'error' => $exception->getMessage(),
            ]);

            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            Log::error('price_ingest.failed', [
                'provider' => $provider,
                'snapshot_id' => $snapshot->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function touchRegionsFromContext(array $context): void
    {
        if (! isset($context['sku_region_ids'])) {
            return;
        }

        SkuRegion::query()
            ->whereIn('id', (array) $context['sku_region_ids'])
            ->update(['updated_at' => now()]);
    }

    /**
     * @param  array<int, string>  $providers
     */
    protected function selectNextProvider(array $providers): ?string
    {
        $enabled = collect($providers)
            ->map(fn ($provider) => (string) $provider)
            ->filter(fn ($provider) => config(sprintf('pricing.providers.%s', $provider)) !== null)
            ->filter(fn ($provider) => (bool) (config(sprintf('pricing.providers.%s.enabled', $provider), true)))
            ->values();

        if ($enabled->isEmpty()) {
            return null;
        }

        $usageMap = ProviderUsage::query()
            ->whereIn('provider', $enabled->all())
            ->get()
            ->keyBy('provider');

        return $enabled
            ->sort(function (string $a, string $b) use ($usageMap) {
                $aUsage = $usageMap->get($a);
                $bUsage = $usageMap->get($b);

                $aDaily = $aUsage?->daily_calls ?? 0;
                $bDaily = $bUsage?->daily_calls ?? 0;

                if ($aDaily === $bDaily) {
                    $aLast = $aUsage?->last_called_at?->timestamp ?? 0;
                    $bLast = $bUsage?->last_called_at?->timestamp ?? 0;

                    if ($aLast === $bLast) {
                        return strcmp($a, $b);
                    }

                    return $aLast <=> $bLast;
                }

                return $aDaily <=> $bDaily;
            })
            ->first();
    }

    protected function recordProviderUsage(string $provider): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $todayDate = $today->toDateString();
        $todayTimestamp = $today->toDateTimeString();

        $updatePayload = [
            'last_called_at' => $now,
            'updated_at' => $now,
            'total_calls' => DB::raw('total_calls + 1'),
            'daily_calls' => DB::raw(sprintf(
                "CASE WHEN DATE(daily_window) = '%s' THEN daily_calls + 1 ELSE 1 END",
                $todayDate
            )),
            'daily_window' => DB::raw(sprintf(
                "CASE WHEN DATE(daily_window) = '%s' THEN daily_window ELSE '%s' END",
                $todayDate,
                $todayTimestamp
            )),
        ];

        $affected = DB::table('provider_usages')
            ->where('provider', $provider)
            ->update($updatePayload);

        if ($affected === 0) {
            try {
                DB::table('provider_usages')->insert([
                    'provider' => $provider,
                    'total_calls' => 1,
                    'daily_calls' => 1,
                    'daily_window' => $today,
                    'last_called_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (Throwable $exception) {
                DB::table('provider_usages')
                    ->where('provider', $provider)
                    ->update($updatePayload);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array{0: Product, 1: bool}
     */
    protected function findOrCreateProduct(array $game, string $provider): array
    {
        $slug = $game['slug'] ?? str($game['title'] ?? '')->slug()->toString();

        if ($slug === '') {
            throw new RuntimeException('Pricing payload missing a resolvable product slug.');
        }

        $product = Product::query()->where('slug', $slug)->first();

        $incoming = [
            'name' => $game['title'] ?? null,
            'platform' => $game['platform'] ?? null,
            'category' => $game['category'] ?? null,
        ];

        $metadata = $this->mergeProductMetadata(
            (array) ($product?->metadata ?? []),
            (array) ($game['metadata'] ?? []),
            $provider,
            $game
        );

        if (! $product) {
            $product = Product::query()->create([
                'slug' => $slug,
                'name' => $incoming['name'] ?? 'Unknown Title',
                'platform' => $incoming['platform'] ?? 'Unknown',
                'category' => $incoming['category'],
                'metadata' => $metadata,
            ]);

            return [$product, true];
        }

        $updates = [];

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($product->{$key} !== $value) {
                $updates[$key] = $value;
            }
        }

        if ($metadata !== $product->metadata) {
            $updates['metadata'] = $metadata;
        }

        if ($updates !== []) {
            $product->fill($updates);
            $product->save();
        }

        return [$product, false];
    }

    /**
     * @param  array<string, mixed>  $storeConfig
     * @param  array<string, mixed>  $deal
     */
    protected function findOrCreateSkuRegion(Product $product, array $storeConfig, array $deal, string $provider): SkuRegion
    {
        /** @var SkuRegion $skuRegion */
        $skuRegion = SkuRegion::query()->firstOrNew([
            'product_id' => $product->id,
            'region_code' => $storeConfig['region_code'],
            'retailer' => $storeConfig['retailer'],
        ]);

        $currencyModel = $this->resolveCurrencyModel($storeConfig['currency']);
        $country = $this->resolveCountryModel($storeConfig['region_code'], $currencyModel);
        $this->ensureLocalCurrencyEntry($currencyModel, $country->code);

        $metadata = (array) ($skuRegion->metadata ?? []);

        $metadata['store_id'] = $deal['store_id'] ?? $metadata['store_id'] ?? null;
        $metadata['last_deal_id'] = $deal['deal_id'] ?? $metadata['last_deal_id'] ?? null;
        $metadata['providers'] = array_values(array_unique(array_filter(array_merge(
            $metadata['providers'] ?? [],
            [$provider]
        ))));

        $skuRegion->fill([
            'currency' => $storeConfig['currency'],
            'sku' => $deal['deal_id'] ?? $skuRegion->sku,
            'country_id' => $country->id,
            'currency_id' => $currencyModel->id,
            'metadata' => array_filter($metadata, static fn ($value) => $value !== null && $value !== []),
        ]);

        $skuRegion->save();

        return $skuRegion;
    }

    /**
     * @param  array<string, mixed>  $storeConfig
     * @param  array<string, mixed>  $deal
     */
    protected function persistPricePoint(SkuRegion $skuRegion, array $deal, array $storeConfig, string $provider): bool
    {
        $salePrice = (float) ($deal['sale_price'] ?? 0);

        if ($salePrice <= 0) {
            return false;
        }

        $currency = $storeConfig['currency'];
        $btcRate = $this->resolveRate($currency, 'BTC');

        if ($btcRate === null) {
            Log::warning('price_ingest.missing_btc_rate', [
                'currency' => $currency,
                'sku_region_id' => $skuRegion->id,
            ]);

            return false;
        }

        $fxRate = $this->resolveRate($currency, 'USD') ?? 1.0;

        $recordedAt = isset($deal['last_change'])
            ? Carbon::createFromTimestamp((int) $deal['last_change'])
            : now();

        $currencyModel = $this->resolveSkuRegionCurrency($skuRegion, $currency);
        $country = $this->resolveSkuRegionCountry($skuRegion, $storeConfig['region_code'], $currencyModel);
        $this->ensureLocalCurrencyEntry($currencyModel, $country?->code ?? $storeConfig['region_code']);

        RegionPrice::create([
            'sku_region_id' => $skuRegion->id,
            'currency_id' => $currencyModel?->id,
            'country_id' => $country?->id,
            'recorded_at' => $recordedAt,
            'fiat_amount' => $salePrice,
            'local_amount' => $salePrice,
            'btc_value' => $this->toSatoshiPrecision($salePrice * $btcRate),
            'tax_inclusive' => (bool) ($storeConfig['tax_inclusive'] ?? true),
            'fx_rate_snapshot' => $fxRate,
            'btc_rate_snapshot' => $this->toSatoshiPrecision($btcRate),
            'raw_payload' => [
                'provider' => $provider,
                'deal' => $deal,
            ],
        ]);

        return true;
    }

    protected function resolveSkuRegionCurrency(SkuRegion $skuRegion, string $fallbackCode): Currency
    {
        $relation = $skuRegion->getRelationValue('currency');

        if ($relation instanceof Currency) {
            return $relation;
        }

        if ($skuRegion->currency_id) {
            $loaded = Currency::query()->find($skuRegion->currency_id);

            if ($loaded instanceof Currency) {
                return $loaded;
            }
        }

        return $this->resolveCurrencyModel($fallbackCode);
    }

    protected function resolveSkuRegionCountry(SkuRegion $skuRegion, string $regionCode, Currency $currency): Country
    {
        $relation = $skuRegion->getRelationValue('country');

        if ($relation instanceof Country) {
            return $relation;
        }

        if ($skuRegion->country_id) {
            $loaded = Country::query()->find($skuRegion->country_id);

            if ($loaded instanceof Country) {
                return $loaded;
            }
        }

        return $this->resolveCountryModel($regionCode, $currency);
    }

    protected function resolveCurrencyModel(string $code): Currency
    {
        $normalized = strtoupper(trim($code));

        /** @var Currency $currency */
        $currency = Currency::query()->firstOrNew(['code' => $normalized]);

        if (! $currency->exists) {
            $currency->fill([
                'name' => $normalized,
                'symbol' => null,
                'decimals' => $normalized === 'JPY' ? 0 : 2,
                'is_crypto' => $normalized === 'BTC',
            ]);
        }

        $currency->save();

        return $currency;
    }

    protected function resolveCountryModel(string $regionCode, Currency $currency): Country
    {
        $code = strtoupper(trim($regionCode));

        if ($code === '') {
            $code = 'GLOBAL';
        }

        /** @var Country $country */
        $country = Country::query()->firstOrNew(['code' => $code]);

        if (! $country->exists) {
            $country->fill([
                'name' => $this->lookupCountryName($code),
                'region' => $this->lookupCountryRegion($code),
            ]);
        }

        $country->currency()->associate($currency);
        $country->save();

        return $country;
    }

    protected function ensureLocalCurrencyEntry(Currency $currency, string $regionCode): void
    {
        $normalizedRegion = strtoupper(trim($regionCode));

        if ($normalizedRegion === '') {
            $normalizedRegion = 'GLOBAL';
        }

        $code = $normalizedRegion.'_'.$currency->code;

        LocalCurrency::query()->firstOrCreate([
            'currency_id' => $currency->id,
            'code' => $code,
        ], [
            'name' => sprintf('%s %s', $normalizedRegion, $currency->code),
        ]);
    }

    protected function lookupCountryName(string $code): string
    {
        return match ($code) {
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'EU' => 'European Union',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'SG' => 'Singapore',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'ZA' => 'South Africa',
            'IN' => 'India',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'PE' => 'Peru',
            'PT' => 'Portugal',
            'IE' => 'Ireland',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'RU' => 'Russia',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            default => $code,
        };
    }

    protected function lookupCountryRegion(string $code): ?string
    {
        return match ($code) {
            'US', 'CA', 'MX' => 'North America',
            'BR', 'AR', 'CL', 'PE' => 'South America',
            'GB', 'UK', 'IE', 'DE', 'FR', 'ES', 'IT', 'PT', 'NL', 'BE', 'SE', 'NO', 'DK', 'FI', 'PL', 'CZ', 'EU', 'AT', 'CH' => 'Europe',
            'JP', 'KR', 'CN', 'HK', 'TW', 'SG' => 'Asia Pacific',
            'AU', 'NZ' => 'Oceania',
            'AE', 'SA' => 'Middle East',
            'ZA' => 'Africa',
            'IN' => 'Asia',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    protected function mergeProductMetadata(array $current, array $incoming, string $provider, array $game): array
    {
        $metadata = array_merge($current, $incoming);

        $metadata['providers'] = array_values(array_unique(array_filter(array_merge(
            $current['providers'] ?? [],
            $incoming['providers'] ?? [],
            [$provider]
        ))));

        if (isset($game['external_id'])) {
            $externalIds = $metadata['external_ids'] ?? [];

            if (! is_array($externalIds)) {
                $externalIds = [];
            }

            $externalIds[$provider] = $game['external_id'];
            $metadata['external_ids'] = $externalIds;
        }

        return array_filter($metadata, static fn ($value) => $value !== null && $value !== []);
    }

    protected function resolveRate(string $base, string $quote): ?float
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            return 1.0;
        }

        $direct = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->orderByDesc('fetched_at')
            ->value('rate');

        if ($direct !== null) {
            return (float) $direct;
        }

        $inverse = ExchangeRate::query()
            ->where('base_currency', $quote)
            ->where('quote_currency', $base)
            ->orderByDesc('fetched_at')
            ->value('rate');

        if ($inverse === null) {
            return null;
        }

        $inverseFloat = (float) $inverse;

        if ($inverseFloat == 0.0) {
            return null;
        }

        return round(1 / $inverseFloat, 12);
    }

    protected function toSatoshiPrecision(float $value): float
    {
        return (float) number_format($value, 8, '.', '');
    }
}
