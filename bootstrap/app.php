<?php

use App\Http\Middleware\HandleAppearance;
use App\Jobs\AnalyzePriceDropsJob;
use App\Jobs\BuildAggregatesJob;
use App\Jobs\FetchFxJob;
use App\Jobs\FetchPricesJob;
use App\Jobs\FetchProductMediaJob;
use App\Models\Product;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $providers = collect(config('pricing.providers', []));

        $providers->values()->each(function (array $config, int $index) use ($schedule, $providers): void {
            $provider = $providers->keys()->get($index);

            $schedule->job(new FetchPricesJob(
                provider: (string) $provider,
                context: [
                    'regions' => $config['regions'] ?? [],
                ],
            ))
                ->name("fetch-prices-{$provider}")
                ->hourlyAt(min(5 + ($index * 10), 55))
                ->withoutOverlapping()
                ->onOneServer();
        });

        $schedule->job(new FetchFxJob([
            'pairs' => config('pricing.fx.pairs', []),
            'provider' => config('pricing.fx.provider', 'coingecko'),
        ]))
            ->name('fetch-fx-rates')
            ->hourlyAt(3)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new BuildAggregatesJob([
            'bucket' => 'day',
        ]))
            ->name('build-aggregates')
            ->hourlyAt(35)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new AnalyzePriceDropsJob([
            'window_minutes' => config('pricing.analysis.window_minutes', 60),
            'drop_percentage' => config('pricing.analysis.drop_percentage', 5),
        ]))
            ->name('analyze-price-drops')
            ->hourlyAt(45)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->call(function (): void {
            Product::query()
                ->whereNotNull('name')
                ->orderByDesc('updated_at')
                ->limit(15)
                ->pluck('id')
                ->each(function (int $productId): void {
                    FetchProductMediaJob::dispatch($productId)
                        ->delay(now()->addSeconds(random_int(5, 120)));
                });
        })
            ->name('fetch-product-media')
            ->twiceDaily(2, 14)
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
