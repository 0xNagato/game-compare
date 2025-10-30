<?php

namespace App\Providers;

use App\Services\Media\Contracts\ProductMediaProvider;
use App\Services\Media\ProductMediaAggregator;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProductMediaAggregator::class, function ($app) {
            $providers = collect(config('media.providers', []))
                ->filter(fn (array $config) => ($config['enabled'] ?? true) === true)
                ->mapWithKeys(function (array $config, string $key) use ($app) {
                    $class = $config['class'] ?? null;

                    if (! $class) {
                        return [];
                    }

                    /** @var ProductMediaProvider $provider */
                    $provider = $app->make($class, ['options' => $config['options'] ?? []]);

                    return [$key => $provider];
                })
                ->all();

            /** @var CacheFactory $cacheFactory */
            $cacheFactory = $app->make(CacheFactory::class);

            return new ProductMediaAggregator($providers, $cacheFactory->store());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
