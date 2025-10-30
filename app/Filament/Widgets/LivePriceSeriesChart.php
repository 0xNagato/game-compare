<?php

namespace App\Filament\Widgets;

use App\Models\PriceSeriesAggregate;
use App\Models\RegionPrice;
use Filament\Support\Colors\Color;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use marineusde\LarapexCharts\Charts\LineChart as LarapexLineChart;
use marineusde\LarapexCharts\Options\XAxisOption;

class LivePriceSeriesChart extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.live-price-series-chart';

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        [$from, $to] = [
            Carbon::now()->subDays(30)->startOfDay(),
            Carbon::now()->endOfDay(),
        ];

        $rawSeries = $this->gatherSeries($from, $to);

        if ($rawSeries->isEmpty()) {
            return [
                'chart' => null,
                'headline' => 'No price activity yet',
                'subheadline' => 'Ingest data or seed demo fixtures to unlock the live chart.',
                'lastUpdated' => null,
                'productName' => null,
            ];
        }

        $series = $this->normalizeSeries($rawSeries);

        $topProductId = $this->resolvePrimaryProduct($series);

        if ($topProductId === null) {
            return [
                'chart' => null,
                'headline' => 'Insufficient data',
                'subheadline' => 'We need at least one aggregated product to plot a series.',
                'lastUpdated' => null,
                'productName' => null,
            ];
        }

        $productSeries = $series
            ->where('product_id', $topProductId)
            ->groupBy('region_code')
            ->sortByDesc(fn (Collection $rows) => $rows->sum('sample_count'))
            ->take(4);

        $labels = $productSeries
            ->flatMap(fn (Collection $rows) => $rows->pluck('window_start'))
            ->map(fn (Carbon $date) => $date->copy()->toDateString())
            ->unique()
            ->sort()
            ->values();

        $datasets = $productSeries->map(function (Collection $rows, string $region) use ($labels) {
            $lookup = $rows->keyBy(function (array $row): string {
                /** @var Carbon $start */
                $start = $row['window_start'];

                return $start->toDateString();
            });

            $points = $labels->map(function (string $date) use ($lookup) {
                $row = $lookup->get($date);

                return $row ? (float) $row['avg_btc'] : null;
            });

            return [
                'label' => $region,
                'points' => $points->all(),
            ];
        })->values();

        $chart = $this->buildChart($labels->all(), $datasets);

        $productName = $this->resolveProductName($series, $topProductId);
        $lastUpdated = $this->resolveLastUpdated($series, $topProductId);

        return [
            'chart' => $chart,
            'headline' => $productName ?? 'Tracked Product',
            'subheadline' => 'BTC-normalised daily averages across priority regions',
            'lastUpdated' => $lastUpdated,
            'productName' => $productName,
        ];
    }

    protected function gatherSeries(Carbon $from, Carbon $to): Collection
    {
        $aggregated = PriceSeriesAggregate::query()
            ->with('product')
            ->where('bucket', 'day')
            ->whereBetween('window_start', [$from, $to])
            ->orderBy('window_start')
            ->get();

        if ($aggregated->isNotEmpty()) {
            return $aggregated;
        }

        return $this->buildRealtimeSeries($from, $to);
    }

    protected function buildRealtimeSeries(Carbon $from, Carbon $to): Collection
    {
        $rows = RegionPrice::query()
            ->selectRaw('sku_regions.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('sku_regions.region_code as region_code')
            ->selectRaw('DATE(region_prices.recorded_at) as bucket_start')
            ->selectRaw('AVG(region_prices.btc_value) as avg_btc')
            ->selectRaw('COUNT(*) as sample_count')
            ->join('sku_regions', 'region_prices.sku_region_id', '=', 'sku_regions.id')
            ->join('products', 'sku_regions.product_id', '=', 'products.id')
            ->whereBetween('region_prices.recorded_at', [$from, $to])
            ->groupBy('sku_regions.product_id', 'products.name', 'sku_regions.region_code', DB::raw('DATE(region_prices.recorded_at)'))
            ->orderBy('bucket_start')
            ->get();

        return $rows->map(function ($row) {
            $start = Carbon::parse($row->bucket_start)->startOfDay();

            return [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'region_code' => (string) $row->region_code,
                'avg_btc' => (float) $row->avg_btc,
                'sample_count' => (int) $row->sample_count,
                'window_start' => $start,
                'window_end' => $start->copy()->endOfDay(),
            ];
        });
    }

    protected function normalizeSeries(Collection $series): Collection
    {
        return $series->map(function ($row) {
            $productId = (int) data_get($row, 'product_id');
            $productName = data_get($row, 'product.name') ?? data_get($row, 'product_name');
            $regionCode = (string) data_get($row, 'region_code');
            $avgBtc = (float) data_get($row, 'avg_btc');
            $sampleCount = (int) data_get($row, 'sample_count', 0);

            $windowStart = data_get($row, 'window_start');
            if ($windowStart instanceof Carbon) {
                $windowStart = $windowStart->copy();
            } else {
                $windowStart = Carbon::parse((string) $windowStart);
            }

            $windowEnd = data_get($row, 'window_end');
            if ($windowEnd instanceof Carbon) {
                $windowEnd = $windowEnd->copy();
            } elseif ($windowEnd !== null) {
                $windowEnd = Carbon::parse((string) $windowEnd);
            } else {
                $windowEnd = $windowStart->copy()->endOfDay();
            }

            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'region_code' => $regionCode,
                'avg_btc' => $avgBtc,
                'sample_count' => $sampleCount,
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
            ];
        });
    }

    protected function resolvePrimaryProduct(Collection $series): ?int
    {
        return $series
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->sum('sample_count'))
            ->sortDesc()
            ->keys()
            ->first();
    }

    protected function resolveProductName(Collection $series, int $productId): ?string
    {
        $first = $series->firstWhere('product_id', $productId);

        return $first['product_name'] ?? null;
    }

    protected function resolveLastUpdated(Collection $series, int $productId): ?string
    {
        $latest = $series
            ->where('product_id', $productId)
            ->sortByDesc('window_end')
            ->first();

        if ($latest === null) {
            return null;
        }

        /** @var Carbon $windowEnd */
        $windowEnd = $latest['window_end'];

        return $windowEnd->diffForHumans();
    }

    /**
     * @param  array<int, string>  $labels
     * @param  Collection<int, array{label: string, points: array<int, float|null>}>  $datasets
     */
    protected function buildChart(array $labels, Collection $datasets): ?LarapexLineChart
    {
        if (empty($labels) || $datasets->isEmpty()) {
            return null;
        }

        $palette = $this->resolvePalette($datasets->count());

        $allPoints = $datasets
            ->flatMap(static fn (array $dataset) => collect($dataset['points']))
            ->filter(static fn ($value) => $value !== null)
            ->map(static fn ($value) => (float) $value);

        if ($allPoints->isEmpty()) {
            return null;
        }

        $minValue = (float) $allPoints->min();
        $maxValue = (float) $allPoints->max();

        $range = max($maxValue - $minValue, 0.0);
        $padding = max($range * 0.1, 1.0e-8);

        if ($range === 0.0) {
            $padding = max(abs($minValue) * 0.05, 1.0e-8);
        }

        $chartMin = $minValue - $padding;
        $chartMax = $maxValue + $padding;

        $tickAmount = min(8, max(3, (int) ceil(count($labels) / 4)));

        $chart = new LarapexLineChart;
        $chart->setToolbar(true)
            ->setHeight(360)
            ->setColors($palette)
            ->setStroke(2, $palette, 'smooth')
            ->setMarkers($palette, 4, 6)
            ->setShowLegend(true)
            ->setTitle('Live BTC‑normalised prices')
            ->setSubtitle('Daily averages — last 30 days')
            ->setLabels($labels)
            ->setDataset($datasets->map(function (array $dataset) {
                return [
                    'name' => $dataset['label'],
                    'data' => array_map(static fn ($value) => $value !== null ? round((float) $value, 8) : null, $dataset['points']),
                ];
            })->all())
            ->setXAxisOption((new XAxisOption($labels))->setShowLabels(true))
            ->setAdditionalOptions([
                'tooltip' => ['shared' => true, 'intersect' => false],
                'legend' => [
                    'show' => true,
                    'position' => 'top',
                    'horizontalAlign' => 'right',
                ],
                'grid' => [
                    'borderColor' => '#e6edf3',
                    'strokeDashArray' => 4,
                ],
                'yaxis' => [
                    [
                        'min' => round($chartMin, 8),
                        'max' => round($chartMax, 8),
                        'tickAmount' => $tickAmount,
                    ],
                ],
            ]);

        return $chart;
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePalette(int $count): array
    {
        $palette = [
            Color::Amber[500],
            Color::Rose[500],
            Color::Sky[500],
            Color::Emerald[500],
            Color::Violet[500],
            Color::Slate[500],
        ];

        if ($count <= count($palette)) {
            return array_slice($palette, 0, $count);
        }

        $extended = [];
        for ($i = 0; $i < $count; $i++) {
            $extended[] = $palette[$i % count($palette)];
        }

        return $extended;
    }
}
