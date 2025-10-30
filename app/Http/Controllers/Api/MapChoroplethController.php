<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceSeriesAggregate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MapChoroplethController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'stat' => ['nullable', 'in:mean_btc'],
            'window' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'include_tax' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false, 'on', 'off'])],
        ])->validate();

        $productId = (int) $validated['product_id'];
        $stat = $validated['stat'] ?? 'mean_btc';
        $includeTax = array_key_exists('include_tax', $validated)
            ? $this->toBoolean($validated['include_tax'])
            : true;

        [$from, $to, $windowLabel] = $this->resolveWindow($validated);

        $cacheKey = sprintf(
            'map:%d:%s:%s:%s',
            $productId,
            $stat,
            $windowLabel,
            $includeTax ? 'tax' : 'notax'
        );

        $payload = Cache::get($cacheKey);

        if (! $payload) {
            $payload = Cache::lock("lock:{$cacheKey}", 10)->block(3, function () use ($cacheKey, $productId, $stat, $windowLabel, $includeTax, $from, $to) {
                if ($existing = Cache::get($cacheKey)) {
                    return $existing;
                }

                $rows = PriceSeriesAggregate::query()
                    ->where('product_id', $productId)
                    ->where('bucket', 'day')
                    ->where('tax_inclusive', $includeTax)
                    ->whereBetween('window_start', [$from, $to])
                    ->get();

                $regions = $rows
                    ->groupBy('region_code')
                    ->map(function (Collection $group, string $region) {
                        return [
                            'code' => $region,
                            'value' => round($group->avg('avg_btc'), 8),
                            'sample_count' => $group->sum('sample_count'),
                        ];
                    })
                    ->values()
                    ->all();

                $payload = [
                    'product_id' => $productId,
                    'stat' => $stat,
                    'window' => $windowLabel,
                    'include_tax' => $includeTax,
                    'regions' => $regions,
                    'meta' => [
                        'from' => $from->toIso8601String(),
                        'to' => $to->toIso8601String(),
                        'cache_ttl' => 1800,
                    ],
                ];

                Cache::put($cacheKey, $payload, now()->addMinutes(30));

                return $payload;
            });
        }

        $response = response()->json($payload);
        $response->setEtag(sha1(json_encode($payload)));
        $response->header('Cache-Control', 'public, max-age=1800');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveWindow(array $validated): array
    {
        $from = Arr::get($validated, 'from');
        $to = Arr::get($validated, 'to');
        $window = Arr::get($validated, 'window');

        if ($window && preg_match('/^(\d+)[dD]$/', $window, $matches)) {
            $days = max(1, (int) $matches[1]);
            $toCarbon = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
            $fromCarbon = $from
                ? Carbon::parse($from)->startOfDay()
                : $toCarbon->copy()->subDays($days - 1)->startOfDay();
        } else {
            $fromCarbon = $from ? Carbon::parse($from)->startOfDay() : now()->copy()->subDays(29)->startOfDay();
            $toCarbon = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        }

        if ($fromCarbon->greaterThan($toCarbon)) {
            [$fromCarbon, $toCarbon] = [$toCarbon->copy()->startOfDay(), $fromCarbon->copy()->endOfDay()];
        }

        $windowLabel = sprintf('%dd', max(1, $fromCarbon->diffInDays($toCarbon) + 1));

        return [$fromCarbon, $toCarbon, $windowLabel];
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
