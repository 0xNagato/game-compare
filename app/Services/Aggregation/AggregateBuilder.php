<?php

namespace App\Services\Aggregation;

use App\Models\DatasetSnapshot;
use App\Models\PriceSeriesAggregate;
use App\Models\RegionPrice;
use App\Support\Analytics\TrendlineCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AggregateBuilder
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function build(array $context = []): void
    {
        [$from, $to] = $this->resolveWindow($context);
        $bucket = Arr::get($context, 'bucket', 'day');

        $snapshot = DatasetSnapshot::query()->create([
            'kind' => 'aggregate_build',
            'status' => 'running',
            'started_at' => now(),
            'context' => [
                'bucket' => $bucket,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'product_id' => Arr::get($context, 'product_id'),
                'regions' => (array) Arr::get($context, 'regions', []),
            ],
        ]);

        try {
            $series = $this->computeSeries($context, $bucket, $from, $to);

            DB::transaction(function () use ($series): void {
                $this->persistAggregates($series);
            });

            $this->primeSeriesCache($series, $bucket, $from, $to);
            $this->primeMapCache($series, $bucket, $from, $to);

            $snapshot->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'row_count' => $series->count(),
            ]);
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            Log::error('aggregates.build_failed', [
                'error' => $exception->getMessage(),
                'snapshot_id' => $snapshot->id,
            ]);

            throw $exception;
        }
    }

    /**
     * Build pre-aggregated daily series from raw region prices.
     *
     * Contract
     * - input: context with optional product_id and regions, bucket 'day', window [from,to]
     * - output: Collection<int, array{
     *     product_id: int,
     *     region_code: string,
     *     bucket: string,
     *     tax_inclusive: bool,
     *     window_start: Carbon,
     *     window_end: Carbon,
     *     sample_count: int,
     *     retailer_count: int,
     *     min_btc: float,
     *     max_btc: float,
     *     avg_btc: float,
     *     min_fiat: float,
     *     max_fiat: float,
     *     avg_fiat: float,
     *   }>
     * @param  array<string, mixed>  $context
     * @return Collection<int, array{
     *   product_id:int, region_code:string, bucket:string, tax_inclusive:bool,
     *   window_start: Carbon, window_end: Carbon,
     *   sample_count:int, retailer_count:int,
     *   min_btc: float, max_btc: float, avg_btc: float,
     *   min_fiat: float, max_fiat: float, avg_fiat: float
     * }>
     */
    protected function computeSeries(array $context, string $bucket, Carbon $from, Carbon $to): Collection
    {
        if ($bucket !== 'day') {
            throw new \InvalidArgumentException("Unsupported aggregation bucket [{$bucket}].");
        }

        $productId = Arr::get($context, 'product_id');
        $regionCodes = array_filter((array) Arr::get($context, 'regions', []));

        $query = RegionPrice::query()
            ->selectRaw('sku_regions.product_id as product_id')
            ->selectRaw('sku_regions.region_code as region_code')
            ->selectRaw('region_prices.tax_inclusive as tax_inclusive')
            ->selectRaw('COUNT(*) as sample_count')
            ->selectRaw('COUNT(DISTINCT sku_regions.retailer) as retailer_count')
            ->selectRaw('MIN(region_prices.btc_value) as min_btc')
            ->selectRaw('MAX(region_prices.btc_value) as max_btc')
            ->selectRaw('AVG(region_prices.btc_value) as avg_btc')
            ->selectRaw('MIN(region_prices.fiat_amount) as min_fiat')
            ->selectRaw('MAX(region_prices.fiat_amount) as max_fiat')
            ->selectRaw('AVG(region_prices.fiat_amount) as avg_fiat')
            ->selectRaw('DATE(region_prices.recorded_at) as bucket_start')
            ->join('sku_regions', 'region_prices.sku_region_id', '=', 'sku_regions.id')
            ->whereBetween('region_prices.recorded_at', [$from, $to])
            ->when($productId, function (\Illuminate\Database\Eloquent\Builder $builder) use ($productId) {
                return $builder->where('sku_regions.product_id', $productId);
            })
            ->when($regionCodes, function (\Illuminate\Database\Eloquent\Builder $builder) use ($regionCodes) {
                return $builder->whereIn('sku_regions.region_code', $regionCodes);
            })
            ->groupBy('sku_regions.product_id', 'sku_regions.region_code', 'region_prices.tax_inclusive', DB::raw('DATE(region_prices.recorded_at)'));

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($query->get())->map(fn ($row): array => (array) $row);

        return $rows->map(function (array $row) use ($bucket) {
            $windowStart = Carbon::parse((string) $row['bucket_start'])->startOfDay();
            $windowEnd = (clone $windowStart)->addDay();

            return [
                'product_id' => (int) $row['product_id'],
                'region_code' => (string) $row['region_code'],
                'bucket' => $bucket,
                'tax_inclusive' => (bool) $row['tax_inclusive'],
                'window_start' => $windowStart,
                'window_end' => $windowEnd,
                'sample_count' => (int) $row['sample_count'],
                'retailer_count' => (int) $row['retailer_count'],
                'min_btc' => round((float) $row['min_btc'], 8),
                'max_btc' => round((float) $row['max_btc'], 8),
                'avg_btc' => round((float) $row['avg_btc'], 8),
                'min_fiat' => round((float) $row['min_fiat'], 2),
                'max_fiat' => round((float) $row['max_fiat'], 2),
                'avg_fiat' => round((float) $row['avg_fiat'], 2),
            ];
        });
    }

    /**
     * @param  Collection<int, array{
     *   product_id:int, region_code:string, bucket:string, tax_inclusive:bool,
     *   window_start: Carbon, window_end: Carbon,
     *   min_btc: float, max_btc: float, avg_btc: float,
     *   min_fiat: float, max_fiat: float, avg_fiat: float,
     *   sample_count:int, retailer_count:int
     * }>  $series
     */
    protected function persistAggregates(Collection $series): void
    {
        if ($series->isEmpty()) {
            return;
        }

        /** @var array<int, array<string, mixed>> $payload */
        $payload = $series->map(function (array $row): array {
            return [
                'product_id' => $row['product_id'],
                'region_code' => $row['region_code'],
                'bucket' => $row['bucket'],
                'window_start' => $row['window_start']->toDateTimeString(),
                'window_end' => $row['window_end']->toDateTimeString(),
                'tax_inclusive' => $row['tax_inclusive'],
                'min_btc' => $row['min_btc'],
                'max_btc' => $row['max_btc'],
                'avg_btc' => $row['avg_btc'],
                'min_fiat' => $row['min_fiat'],
                'max_fiat' => $row['max_fiat'],
                'avg_fiat' => $row['avg_fiat'],
                'sample_count' => $row['sample_count'],
                'metadata' => json_encode([
                    'retailer_count' => $row['retailer_count'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        PriceSeriesAggregate::query()->upsert(
            $payload,
            ['product_id', 'region_code', 'bucket', 'window_start', 'tax_inclusive'],
            ['window_end', 'min_btc', 'max_btc', 'avg_btc', 'min_fiat', 'max_fiat', 'avg_fiat', 'sample_count', 'metadata', 'updated_at']
        );
    }

    /**
     * Prime Redis cache for compare series payloads.
     *
     * @param  Collection<int, array{
     *   product_id:int, region_code:string, bucket:string, tax_inclusive:bool,
     *   window_start: Carbon, window_end: Carbon,
     *   min_btc: float, max_btc: float, avg_btc: float,
     *   min_fiat: float, max_fiat: float, avg_fiat: float,
     *   sample_count:int
     * }>  $series
     */
    protected function primeSeriesCache(Collection $series, string $bucket, Carbon $from, Carbon $to): void
    {
        if ($series->isEmpty()) {
            return;
        }

        $series
            ->groupBy('product_id')
            ->each(function (Collection $productRows, $productId) use ($bucket, $from, $to): void {
                $productRows
                    ->groupBy('tax_inclusive')
                    ->each(function (Collection $rows, $taxKey) use ($bucket, $from, $to, $productId): void {
                        $taxInclusive = (bool) $taxKey;
                        $regions = $rows->pluck('region_code')->unique()->sort()->values();
                        $regionsHash = sha1($regions->join(','));
                        $key = sprintf(
                            'series:%s:%s:%s:%s:%s:%s',
                            (int) $productId,
                            $bucket,
                            $regionsHash,
                            $from->toDateString(),
                            $to->toDateString(),
                            $taxInclusive ? 'tax' : 'notax'
                        );

                        /** @var array{
                         *   product_id:int,
                         *   bucket:string,
                         *   include_tax:bool,
                         *   meta: array{from:string,to:string,unit:string,cache_ttl:int,regions:array<int, string>},
                         *   series: array<int, array{region:string,points:array<int, array{0:string,1:float}>,fiat_points:array<int, array{0:string,1:float}>,trend:array<string,mixed>,fiat_trend:array<string,mixed>,sample_count:int}>
                         * } $payload
                         */
                        $payload = [
                            'product_id' => $productId,
                            'bucket' => $bucket,
                            'include_tax' => $taxInclusive,
                            'meta' => [
                                'from' => $from->toIso8601String(),
                                'to' => $to->toIso8601String(),
                                'unit' => 'BTC',
                                'cache_ttl' => 1200,
                                'regions' => $regions->all(),
                            ],
                            'series' => $rows
                                ->groupBy('region_code')
                                ->sortKeys()
                                ->map(function (Collection $regionRows, string $region): array {
                                    $sorted = $regionRows->sortBy('window_start');

                                    /** @var Collection<int, array{0:string,1:float}> $btcPoints */
                                    $btcPoints = $sorted->map(fn (array $row): array => [
                                        $row['window_start']->toDateString(),
                                        (float) $row['avg_btc'],
                                    ])->values();

                                    /** @var Collection<int, array{0:string,1:float}> $fiatPoints */
                                    $fiatPoints = $sorted->map(fn (array $row): array => [
                                        $row['window_start']->toDateString(),
                                        (float) $row['avg_fiat'],
                                    ])->values();

                                    return [
                                        'region' => $region,
                                        'points' => $btcPoints->all(),
                                        'fiat_points' => $fiatPoints->all(),
                                        'trend' => TrendlineCalculator::fromPoints($btcPoints),
                                        'fiat_trend' => TrendlineCalculator::fromPoints($fiatPoints),
                                        'sample_count' => $sorted->sum('sample_count'),
                                    ];
                                })->values()->all(),
                        ];

                        Cache::lock("lock:{$key}", 10)->block(3, function () use ($key, $payload): void {
                            Cache::put($key, $payload, now()->addMinutes(20));
                        });
                    });
            });
    }

    /**
     * Prime Redis cache for choropleth map payloads.
     *
     * @param  Collection<int, array{
     *   product_id:int, region_code:string, bucket:string, tax_inclusive:bool,
     *   window_start: Carbon, window_end: Carbon,
     *   avg_btc: float, sample_count:int
     * }>  $series
     */
    protected function primeMapCache(Collection $series, string $bucket, Carbon $from, Carbon $to): void
    {
        if ($series->isEmpty()) {
            return;
        }

        $windowLabel = sprintf('%dd', max(1, $from->diffInDays($to) + 1));

        $series
            ->groupBy('product_id')
            ->each(function (Collection $productRows, $productId) use ($from, $to, $windowLabel): void {
                $productRows
                    ->groupBy('tax_inclusive')
                    ->each(function (Collection $rows, $taxKey) use ($from, $to, $windowLabel, $productId): void {
                        $taxInclusive = (bool) $taxKey;
                        $key = sprintf(
                            'map:%s:mean_btc:%s:%s',
                            (int) $productId,
                            $windowLabel,
                            $taxInclusive ? 'tax' : 'notax'
                        );

                        /** @var array{
                         *   product_id:int, stat:string, window:string, include_tax:bool,
                         *   meta: array{from:string,to:string,cache_ttl:int},
                         *   regions: array<int, array{code:string,value:float,sample_count:int}>
                         * } $payload
                         */
                        $payload = [
                            'product_id' => $productId,
                            'stat' => 'mean_btc',
                            'window' => $windowLabel,
                            'include_tax' => $taxInclusive,
                            'meta' => [
                                'from' => $from->toIso8601String(),
                                'to' => $to->toIso8601String(),
                                'cache_ttl' => 1800,
                            ],
                            'regions' => $rows
                                ->groupBy('region_code')
                                ->map(function (Collection $regionRows, string $region): array {
                                    $avg = $regionRows->avg('avg_btc');
                                    return [
                                        'code' => $region,
                                        'value' => round((float) ($avg ?? 0.0), 8),
                                        'sample_count' => $regionRows->sum('sample_count'),
                                    ];
                                })->values()->all(),
                        ];

                        Cache::lock("lock:{$key}", 10)->block(3, function () use ($key, $payload): void {
                            Cache::put($key, $payload, now()->addMinutes(30));
                        });
                    });
            });
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveWindow(array $context): array
    {
        $from = Arr::get($context, 'from');
        $to = Arr::get($context, 'to');

        $fromCarbon = $from ? Carbon::parse($from)->startOfDay() : now()->copy()->subDays(30)->startOfDay();
        $toCarbon = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        if ($fromCarbon->greaterThan($toCarbon)) {
            [$fromCarbon, $toCarbon] = [$toCarbon->copy()->startOfDay(), $fromCarbon->copy()->endOfDay()];
        }

        return [$fromCarbon, $toCarbon];
    }
}
