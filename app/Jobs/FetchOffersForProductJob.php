<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\TokenBucketRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class FetchOffersForProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string>  $regions
     */
    public function __construct(public int $productId, public array $regions = ['US', 'GB', 'EU', 'CA'])
    {
        $this->onQueue('offers');
    }

    public function backoff(): int
    {
        return 30;
    }

    public function handle(TokenBucketRateLimiter $limiter): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        // Build per-provider context payloads so ingestion is targeted to this product
        $contexts = $this->buildProviderContexts($product);

        foreach ($contexts as $provider => $context) {
            $limitCfg = Arr::get(config('providers.limits'), $provider, ['max_rps' => 1.0, 'burst' => 1]);
            $permit = $limiter->attempt($provider, (float) $limitCfg['max_rps'], (int) $limitCfg['burst']);
            if (! $permit['allowed']) {
                $this->release((int) $permit['retry_after']);

                return;
            }

            Log::info('offers.dispatch_fetch', [
                'provider' => $provider,
                'product' => $product->id,
                'slug' => $product->slug,
            ]);

            // Dispatch a provider-specific ingestion with temporary option overrides
            FetchPricesJob::dispatch($provider, $context);
        }
    }

    /**
     * @return array<string, array<string,mixed>>
     */
    protected function buildProviderContexts(Product $product): array
    {
        $contexts = [];

        // Prefer platforms: xbox, playstation, nintendo, pc (steam)
        $platformFamily = $product->primary_platform_family ?? 'pc';
        $platform = $product->platform ?? $platformFamily;

        // NEXARDA (aggregator of many retailers)
        if (config('pricing.providers.nexarda.enabled', true)) {
            $contexts['nexarda'] = [
                'source' => 'FetchOffersForProductJob',
                'products' => [[
                    'id' => null,
                    'type' => 'game',
                    'title' => $product->name,
                    'slug' => $product->slug,
                    'platform' => $platform,
                    'category' => $product->category ?? 'Game',
                    'regions' => array_map(fn ($r) => ['region_code' => $r], $this->regions),
                ]],
            ];
        }

        // ITAD (PC ecosystem heavy; still include to map PC/Steam/retailers)
        if (config('pricing.providers.itad.enabled')) {
            $contexts['itad'] = [
                'source' => 'FetchOffersForProductJob',
                'requests' => [[
                    'title' => $product->name,
                    'plain' => $product->slug,
                    'product' => [
                        'title' => $product->name,
                        'slug' => $product->slug,
                        'platform' => $platform,
                        'category' => $product->category ?? 'Game',
                    ],
                    'regions' => array_map(fn ($r) => ['region_code' => $r, 'country' => strtolower($r)], $this->regions),
                ]],
            ];
        }

        // PriceCharting (secondary market, hardware + games)
        if (config('pricing.providers.pricecharting.enabled')) {
            $contexts['pricecharting'] = [
                'source' => 'FetchOffersForProductJob',
                'catalog' => [[
                    'product_slug' => $product->slug,
                    'title' => $product->name,
                    'platform' => $platform,
                    'category' => $product->category ?? 'Game',
                    'search' => $product->name.' '.$platform,
                ]],
            ];
        }

        // Unofficial storefront stubs (execute only if enabled by config)
        if (config('pricing.providers.steam_store.enabled', false)) {
            $contexts['steam_store'] = [
                'source' => 'FetchOffersForProductJob',
                'apps' => [], // fill via mapping if known
            ];
        }
        if (config('pricing.providers.playstation_store.enabled', false)) {
            $contexts['playstation_store'] = [
                'source' => 'FetchOffersForProductJob',
                'catalog_queries' => [$product->name],
            ];
        }
        if (config('pricing.providers.microsoft_store.enabled', false)) {
            $contexts['microsoft_store'] = [
                'source' => 'FetchOffersForProductJob',
                'product_ids' => [],
            ];
        }
        if (config('pricing.providers.nintendo_eshop.enabled', false)) {
            $contexts['nintendo_eshop'] = [
                'source' => 'FetchOffersForProductJob',
                'title_ids' => [],
            ];
        }

        return $contexts;
    }
}
