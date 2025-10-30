<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductVendorCompareController extends Controller
{
    public function __invoke(Product $product, Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'regions' => ['nullable', 'string'],
            'include_tax' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false, 'on', 'off'])],
        ])->validate();

        $includeTax = array_key_exists('include_tax', $validated)
            ? $this->toBoolean($validated['include_tax'])
            : true;

        $regions = $this->normalizeRegions($validated['regions'] ?? null);

        $regionsHash = sha1($regions->join(','));
        $cacheKey = sprintf('vendors:slug:%s:%s:%s', $product->slug, $regionsHash, $includeTax ? 'tax' : 'notax');

        $payload = Cache::get($cacheKey);

        if (! $payload) {
            $payload = Cache::lock("lock:{$cacheKey}", 10)->block(3, function () use ($cacheKey, $product, $regions, $includeTax) {
                if ($existing = Cache::get($cacheKey)) {
                    return $existing;
                }

                $sub = DB::table('region_prices as rp2')
                    ->select('rp2.sku_region_id', DB::raw('MAX(rp2.recorded_at) as latest'))
                    ->when($includeTax !== null, fn ($q) => $q->where('rp2.tax_inclusive', $includeTax))
                    ->groupBy('rp2.sku_region_id');

                $rows = DB::table('sku_regions as sr')
                    ->joinSub($sub, 'latest_prices', function ($join) {
                        $join->on('latest_prices.sku_region_id', '=', 'sr.id');
                    })
                    ->join('region_prices as rp', function ($join) {
                        $join->on('rp.sku_region_id', '=', 'sr.id')
                            ->on('rp.recorded_at', '=', 'latest_prices.latest');
                    })
                    ->where('sr.product_id', $product->id)
                    ->when($regions->isNotEmpty(), fn ($q) => $q->whereIn('sr.region_code', $regions->all()))
                    ->select([
                        'sr.region_code',
                        'sr.retailer',
                        'sr.currency',
                        'rp.fiat_amount',
                        'rp.btc_value',
                        'rp.tax_inclusive',
                        'rp.recorded_at',
                    ])
                    ->get();

                $grouped = collect($rows)->groupBy('region_code')->sortKeys();

                $regionsPayload = $grouped->map(function (Collection $group, string $region) {
                    $sorted = $group->sortBy('btc_value');
                    $min = (float) ($sorted->first()->btc_value ?? 0);
                    $max = (float) ($sorted->last()->btc_value ?? 0);
                    $retailers = $sorted->map(function ($row) use ($min) {
                        $btc = (float) $row->btc_value;
                        $delta = $btc - $min;
                        $pct = $min > 0 ? ($delta / $min) * 100 : 0.0;

                        return [
                            'retailer' => (string) $row->retailer,
                            'currency' => (string) $row->currency,
                            'fiat' => round((float) $row->fiat_amount, 2),
                            'btc' => round($btc, 8),
                            'recorded_at' => Carbon::parse($row->recorded_at)->toIso8601String(),
                            'delta_btc' => round($delta, 8),
                            'delta_pct' => round($pct, 2),
                            'is_best' => abs($delta) < 1e-12,
                        ];
                    })->values();

                    $spread = max(0.0, $max - $min);
                    $spreadPct = $min > 0 ? ($spread / $min) * 100 : 0.0;
                    $best = $retailers->first()['retailer'] ?? null;

                    return [
                        'region' => $region,
                        'retailers' => $retailers->all(),
                        'summary' => [
                            'min_btc' => round($min, 8),
                            'max_btc' => round($max, 8),
                            'spread_btc' => round($spread, 8),
                            'spread_pct' => round($spreadPct, 2),
                            'best_retailer' => $best,
                            'retailer_count' => $retailers->count(),
                            'sample_count' => $group->count(),
                        ],
                    ];
                })->values();

                $payload = [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'include_tax' => $includeTax,
                    'meta' => [
                        'unit' => 'BTC',
                        'regions' => $regions->isNotEmpty() ? $regions->all() : $grouped->keys()->values()->all(),
                        'updated_at' => now()->toIso8601String(),
                        'cache_ttl' => 600,
                    ],
                    'regions' => $regionsPayload->all(),
                ];

                Cache::put($cacheKey, $payload, now()->addMinutes(10));

                return $payload;
            });
        }

        $response = response()->json($payload);
        $response->setEtag(sha1(json_encode($payload)));
        $response->header('Cache-Control', 'public, max-age=600');

        return $response;
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
