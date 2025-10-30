<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceSeriesAggregate;
use App\Support\Analytics\TrendlineCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompareSeriesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'regions' => ['nullable', 'string'],
            'bucket' => ['nullable', 'in:day'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'include_tax' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false, 'on', 'off'])],
        ])->validate();

        $productId = (int) $validated['product_id'];
        $bucket = $validated['bucket'] ?? 'day';
        $includeTax = array_key_exists('include_tax', $validated)
            ? $this->toBoolean($validated['include_tax'])
            : true;

        [$from, $to] = $this->resolveWindow($validated);

        $regions = $this->normalizeRegions($validated['regions'] ?? null);
        $regionsHash = sha1($regions->join(','));

        $cacheKey = sprintf(
            'series:%d:%s:%s:%s:%s:%s',
            $productId,
            $bucket,
            $regionsHash,
            $from->toDateString(),
            $to->toDateString(),
            $includeTax ? 'tax' : 'notax'
        );

        $payload = Cache::get($cacheKey);

        if (! $payload) {
            $payload = Cache::lock("lock:{$cacheKey}", 10)->block(3, function () use ($cacheKey, $productId, $bucket, $includeTax, $from, $to, $regions) {
                if ($existing = Cache::get($cacheKey)) {
                    return $existing;
                }

                $rows = PriceSeriesAggregate::query()
                    ->where('product_id', $productId)
                    ->where('bucket', $bucket)
                    ->where('tax_inclusive', $includeTax)
                    ->when($regions->isNotEmpty(), fn ($query) => $query->whereIn('region_code', $regions->all()))
                    ->whereBetween('window_start', [$from, $to])
                    ->orderBy('window_start')
                    ->get();

                $regionList = $regions->isNotEmpty()
                    ? $regions
                    : $rows->pluck('region_code')->unique()->sort()->values();

                $series = $rows
                    ->groupBy('region_code')
                    ->sortKeys()
                    ->map(function (Collection $group, string $region) {
                        $sorted = $group->sortBy('window_start');

                        $btcPoints = $sorted->map(fn ($row) => [
                            $row->window_start->toDateString(),
                            (float) $row->avg_btc,
                        ])->values();

                        $fiatPoints = $sorted->map(fn ($row) => [
                            $row->window_start->toDateString(),
                            (float) $row->avg_fiat,
                        ])->values();

                        return [
                            'region' => $region,
                            'points' => $btcPoints->all(),
                            'fiat_points' => $fiatPoints->all(),
                            'trend' => TrendlineCalculator::fromPoints($btcPoints),
                            'fiat_trend' => TrendlineCalculator::fromPoints($fiatPoints),
                            'sample_count' => $sorted->sum('sample_count'),
                        ];
                    })
                    ->values()
                    ->all();

                $payload = [
                    'product_id' => $productId,
                    'bucket' => $bucket,
                    'include_tax' => $includeTax,
                    'series' => $series,
                    'meta' => [
                        'from' => $from->toIso8601String(),
                        'to' => $to->toIso8601String(),
                        'unit' => 'BTC',
                        'cache_ttl' => 1200,
                        'regions' => $regionList->all(),
                    ],
                ];

                Cache::put($cacheKey, $payload, now()->addMinutes(20));

                return $payload;
            });
        }

        $response = response()->json($payload);
        $response->setEtag(sha1(json_encode($payload)));
        $response->header('Cache-Control', 'public, max-age=900');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveWindow(array $validated): array
    {
        $from = Arr::get($validated, 'from');
        $to = Arr::get($validated, 'to');

        $fromCarbon = $from ? Carbon::parse($from)->startOfDay() : now()->copy()->subDays(30)->startOfDay();
        $toCarbon = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        if ($fromCarbon->greaterThan($toCarbon)) {
            [$fromCarbon, $toCarbon] = [$toCarbon->copy()->startOfDay(), $fromCarbon->copy()->endOfDay()];
        }

        return [$fromCarbon, $toCarbon];
    }

    protected function normalizeRegions(?string $regions): Collection
    {
        return collect(explode(',', (string) $regions))
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on'], true);
    }
}
