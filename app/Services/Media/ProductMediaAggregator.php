<?php

namespace App\Services\Media;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\DTOs\ProductMediaData;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductMediaAggregator
{
    /**
     * @param  array<string, ProductMediaProvider>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, ProductMediaData>
     */
    public function fetchAndStore(Product $product, array $context = []): Collection
    {
        $providerKeys = array_keys($this->providers);

        $results = collect();

        foreach ($providerKeys as $key) {
            $provider = $this->providers[$key];

            if (! $provider->enabled()) {
                continue;
            }

            $cacheKey = $this->cacheKey($product, $key, $context);

            /** @var Collection<int, ProductMediaData>|array<int, ProductMediaData> $providerResults */
            $providerResults = $this->cache->remember($cacheKey, now()->addSeconds((int) config('media.cache_ttl', 3600)), function () use ($provider, $product, $context, $key) {
                try {
                    return $provider->fetch($product, $context);
                } catch (\Throwable $exception) {
                    Log::warning('media.provider_failed', [
                        'provider' => $key,
                        'product_id' => $product->id,
                        'error' => $exception->getMessage(),
                    ]);

                    return collect();
                }
            });

            $providerResults = $providerResults instanceof Collection
                ? $providerResults
                : collect($providerResults);

            $results = $results->merge($providerResults);
        }

        if ($results->isEmpty()) {
            return collect();
        }

        $this->persistResults($product, $results);

        return $results;
    }

    /**
     * @param  Collection<int, ProductMediaData>  $results
     */
    protected function persistResults(Product $product, Collection $results): void
    {
        $payload = $results
            ->map(function (ProductMediaData $data) use ($product) {
                $existing = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('source', $data->source)
                    ->when($data->externalId, fn ($query) => $query->where('external_id', $data->externalId))
                    ->first();

                $attributes = array_merge($data->toArray(), [
                    'product_id' => $product->id,
                    'fetched_at' => now(),
                ]);

                if ($existing) {
                    $existing->fill($attributes)->save();

                    return $existing;
                }

                return ProductMedia::create($attributes);
            });

        Log::info('media.product_assets_persisted', [
            'product_id' => $product->id,
            'count' => $payload->count(),
        ]);
    }

    protected function cacheKey(Product $product, string $provider, array $context = []): string
    {
        $hash = sha1((string) json_encode([
            'product' => $product->id,
            'provider' => $provider,
            // Include search query/resource so variant queries are not de-duped by cache
            'query' => (string) ($context['query'] ?? ''),
            'resource' => (string) ($context['resource'] ?? ''),
        ]));

        return sprintf('media:%s:%s', $provider, $hash);
    }

    public static function make(): self
    {
        $configuredProviders = collect(config('media.providers', []))
            ->filter(fn (array $config) => ($config['enabled'] ?? true) === true)
            ->map(function (array $config, string $key) {
                /** @var ProductMediaProvider $provider */
                $provider = app($config['class'], ['options' => $config['options'] ?? []]);

                return [$key, $provider];
            })
            ->mapWithKeys(fn (array $tuple) => [$tuple[0] => $tuple[1]])
            ->all();

        return app(self::class, [
            'providers' => $configuredProviders,
            'cache' => Cache::store(),
        ]);
    }
}