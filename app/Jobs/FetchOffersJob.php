<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\PriceIngestion\Exceptions\ProviderException;
use App\Services\PriceIngestion\PriceIngestionManager;
use App\Services\TokenBucketRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string>  $regions
     */
    public function __construct(public int $productId, public array $regions = ['US', 'EU', 'CA'])
    {
        $this->onQueue('offers');
    }

    public function backoff(): int
    {
        return 45;
    }

    public function handle(TokenBucketRateLimiter $limiter, PriceIngestionManager $manager): void
    {
        $limits = config('providers.limits');
        $provider = 'pricecharting';
        $limitConfig = Arr::get($limits, $provider, ['max_rps' => 1.0, 'burst' => 1]);

        $result = $limiter->attempt($provider, (float) $limitConfig['max_rps'], (int) $limitConfig['burst']);

        if (! $result['allowed']) {
            $this->release($result['retry_after']);

            return;
        }

        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $optionsPath = "pricing.providers.{$provider}.options";
        $providerEnabled = config("pricing.providers.{$provider}.enabled", true);
        $token = Arr::get(config($optionsPath, []), 'token') ?? env('PRICECHARTING_TOKEN');

        if (! $providerEnabled || blank($token)) {
            Log::notice('offers.pricecharting_skipped', [
                'product_id' => $product->id,
                'reason' => 'provider_disabled_or_missing_token',
            ]);

            return;
        }

        $originalOptions = config($optionsPath, []);

        $catalogEntry = [
            'product_slug' => $product->slug,
            'title' => $product->name,
            'platform' => $product->platform ?? $product->primary_platform_family ?? 'Unknown',
            'category' => $product->category ?? 'Game',
            'search' => $product->name,
        ];

        $overriddenOptions = $originalOptions;
        $overriddenOptions['catalog'] = [$catalogEntry];

        if (! empty($this->regions)) {
            $overriddenOptions['regions'] = $this->regions;
        }

        config()->set($optionsPath, $overriddenOptions);

        try {
            $manager->ingest($provider, [
                'product_id' => $product->id,
                'regions' => $this->regions,
                'source' => 'FetchOffersJob',
            ]);
        } catch (ProviderException $exception) {
            Log::warning('offers.ingest_provider_error', [
                'product_id' => $product->id,
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('offers.ingest_failed', [
                'product_id' => $product->id,
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            config()->set($optionsPath, $originalOptions);
        }

        BuildSeriesJob::dispatch($product->id);
    }
}
