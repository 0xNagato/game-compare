<?php

namespace App\Console;

use App\Console\Commands\ImportGiantBombCatalogueCommand;
use App\Console\Commands\MapProductsToVideoGamesCommand;
use App\Console\Commands\RefreshTopTwenty;
use App\Console\Commands\SyncTheGamesDbMediaCommand;
use App\Console\Commands\SyncTheGamesDbMirrorCommand;
use App\Jobs\AnalyzePriceDropsJob;
use App\Jobs\BuildAggregatesJob;
use App\Jobs\FetchFxJob;
use App\Jobs\FetchPricesJob;
use App\Jobs\FetchProductMediaJob;
use App\Jobs\TgdbIncrementalUpdateJob;
use App\Jobs\TgdbSweepShardJob;
use App\Models\Product;
use App\Support\Concerns\DispatchesWithQueueFallback;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Collection;

class Kernel extends ConsoleKernel
{
    use DispatchesWithQueueFallback;

    /**
     * @var array<int, class-string>
     */
    protected $commands = [
        MapProductsToVideoGamesCommand::class,
    ImportGiantBombCatalogueCommand::class,
        SyncTheGamesDbMediaCommand::class,
        SyncTheGamesDbMirrorCommand::class,
        RefreshTopTwenty::class,
        \App\Console\Commands\VerifyAndSeedFromProviders::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            $context = [
                'jitter_seconds' => 45,
            ];

            $this->dispatchWithQueueFallback(
                fn () => FetchFxJob::dispatch($context),
                fn () => FetchFxJob::dispatchSync($context),
                'scheduler.fetch_fx'
            );
        })->name('fetch-fx-hourly')->hourly()->withoutOverlapping();

        foreach ($this->scheduledPriceProviders() as $provider) {
            $schedule->call(function () use ($provider): void {
                $context = [
                    'source' => 'scheduler',
                ];

                $this->dispatchWithQueueFallback(
                    fn () => FetchPricesJob::dispatch($provider, $context),
                    fn () => FetchPricesJob::dispatchSync($provider, $context),
                    "scheduler.fetch_prices.{$provider}"
                );
            })->name("fetch-prices-{$provider}")
                ->hourlyAt(5)
                ->withoutOverlapping();
        }

        $schedule->call(function (): void {
            $context = [
                'bucket' => 'day',
            ];

            $this->dispatchWithQueueFallback(
                fn () => BuildAggregatesJob::dispatch($context),
                fn () => BuildAggregatesJob::dispatchSync($context),
                'scheduler.build_aggregates'
            );
        })->name('build-aggregates-hourly')->hourlyAt(15)->withoutOverlapping();

        $schedule->call(function (): void {
            $context = [
                'window_minutes' => (int) config('pricing.analysis.window_minutes', 60),
            ];

            $this->dispatchWithQueueFallback(
                fn () => AnalyzePriceDropsJob::dispatch($context),
                fn () => AnalyzePriceDropsJob::dispatchSync($context),
                'scheduler.analyze_price_drops'
            );
        })->name('analyze-price-drops-hourly')->hourlyAt(25)->withoutOverlapping();

        $schedule->call(fn () => $this->dispatchMediaHydrationForMissing())
            ->name('hydrate-media-missing-hourly')
            ->hourlyAt(40)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->call(fn () => $this->dispatchMediaHydrationForStale())
            ->name('hydrate-media-stale-daily')
            ->dailyAt('03:20')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('top:refresh --dispatch-only')
            ->name('top-refresh-hourly')
            ->hourlyAt(35)
            ->withoutOverlapping();

        $schedule->job(new TgdbSweepShardJob(['source' => 'scheduler']))
            ->name('tgdb-sweep-nightly')
            ->dailyAt('02:10')
            ->onQueue('fetch')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->job(new TgdbIncrementalUpdateJob(['source' => 'scheduler']))
            ->name('tgdb-incremental-sync-hourly')
            ->hourlyAt(50)
            ->onQueue('fetch')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    protected function dispatchMediaHydrationForMissing(): void
    {
        $limit = max((int) config('media.scheduler.missing_limit', 12), 1);

        $productIds = Product::query()
            ->whereDoesntHave('media')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->pluck('id');

        $this->dispatchMediaHydrationJobs($productIds, 'missing');
    }

    protected function dispatchMediaHydrationForStale(): void
    {
        $limit = max((int) config('media.scheduler.stale_limit', 12), 1);
        $staleDays = max((int) config('media.scheduler.stale_days', 14), 1);
        $threshold = now()->subDays($staleDays);

        $productIds = Product::query()
            ->whereHas('media', function ($query) use ($threshold): void {
                $query->whereNull('fetched_at')
                    ->orWhere('fetched_at', '<=', $threshold);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->pluck('id');

        $this->dispatchMediaHydrationJobs($productIds, 'stale');
    }

    protected function dispatchMediaHydrationJobs(Collection $productIds, string $mode): void
    {
        $ids = $productIds->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $contextBase = [
            'source' => 'scheduler',
            'mode' => $mode,
        ];

        $ids->each(function (int $productId) use ($contextBase): void {
            FetchProductMediaJob::dispatch($productId, $contextBase);
        });
    }

    /**
     * @return array<int, string>
     */
    protected function scheduledPriceProviders(): array
    {
        return collect(config('pricing.providers', []))
            ->filter(fn (array $provider) => ($provider['enabled'] ?? true) === true)
            ->keys()
            ->all();
    }
}
