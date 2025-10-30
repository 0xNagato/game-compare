<?php

namespace App\Filament\Widgets;

use App\Models\Alert;
use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LiveKeyMetrics extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected function getCards(): array
    {
        $trackedProducts = Product::query()->count();
        $distinctRegions = SkuRegion::query()
            ->distinct()
            ->count('region_code');

        $recentIngestWindow = [
            Carbon::now()->subHours(24),
            Carbon::now(),
        ];

        $snapshotsToday = RegionPrice::query()
            ->whereBetween('recorded_at', $recentIngestWindow)
            ->count();

        $activeAlerts = Alert::query()
            ->where('is_active', true)
            ->count();

        $latestAverage = PriceSeriesAggregate::query()
            ->where('bucket', 'day')
            ->orderByDesc('window_start')
            ->value('avg_btc');

        $latestAverageBtc = $latestAverage !== null
            ? (float) $latestAverage
            : null;

        $btcDescription = $latestAverageBtc !== null
            ? sprintf('Mean BTC over the most recent rollup window')
            : 'Awaiting aggregate build run';

        return [
            Stat::make('Tracked Products', number_format($trackedProducts))
                ->description(match (true) {
                    $distinctRegions >= 50 => 'Wide regional coverage already in play',
                    $distinctRegions >= 10 => 'Healthy spread of regions observed',
                    default => 'Seed more regions to unlock richer comparisons',
                })
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('primary'),

            Stat::make('Snapshots (24h)', number_format($snapshotsToday))
                ->description('Fresh price points captured the past 24 hours')
                ->descriptionIcon('heroicon-o-bolt')
                ->chart($this->ingestSparkline())
                ->color('warning'),

            Stat::make('Active Alerts', number_format($activeAlerts))
                ->description('Automations ready to fire on price moves')
                ->descriptionIcon('heroicon-o-bell-alert')
                ->color($activeAlerts > 0 ? 'success' : 'gray'),

            Stat::make('Latest Mean BTC', $latestAverageBtc !== null
                ? Str::of(number_format($latestAverageBtc, 8))->append(' BTC')
                : '—')
                ->description($btcDescription)
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('sky'),
        ];
    }

    /**
     * Builds a light sparkline for the previous seven days of ingestion.
     *
     * @return array<int, float>
     */
    protected function ingestSparkline(): array
    {
        $start = Carbon::now()->subDays(6)->startOfDay();

        $series = RegionPrice::query()
            ->selectRaw('DATE(recorded_at) as day, COUNT(*) as samples')
            ->where('recorded_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(static function ($row): array {
                return [Carbon::parse($row->day)->toDateString() => (float) $row->samples];
            });

        return collect(range(0, 6))
            ->map(static fn (int $offset): string => $start->copy()->addDays($offset)->toDateString())
            ->map(static fn (string $day) => $series->get($day, 0.0))
            ->all();
    }
}
