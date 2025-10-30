<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Support\Analytics\TrendlineCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductPriceInsightsController extends Controller
{
    public function compare(Product $product): JsonResponse
    {
        $payload = Cache::remember(
            sprintf('landing:compare:%d', $product->id),
            now()->addMinutes(5),
            fn () => RegionPrice::query()
                ->select([
                    'sku_regions.region_code as region_code',
                    DB::raw('AVG(region_prices.btc_value) as btc_average'),
                ])
                ->join('sku_regions', 'region_prices.sku_region_id', '=', 'sku_regions.id')
                ->where('sku_regions.product_id', $product->id)
                ->where('region_prices.recorded_at', '>=', now()->subDays(3))
                ->groupBy('sku_regions.region_code')
                ->orderBy('sku_regions.region_code')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->region_code,
                    'value_btc' => round((float) $row->btc_average, 8),
                ])
                ->values()
        );

        return response()->json([
            'regions' => $payload,
            'meta' => [
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function history(Product $product): JsonResponse
    {
        $series = Cache::remember(
            sprintf('landing:history:%d', $product->id),
            now()->addMinutes(10),
            fn () => RegionPrice::query()
                ->select([
                    'sku_regions.region_code as region_code',
                    DB::raw('DATE(region_prices.recorded_at) as day'),
                    DB::raw('AVG(region_prices.btc_value) as btc_average'),
                ])
                ->join('sku_regions', 'region_prices.sku_region_id', '=', 'sku_regions.id')
                ->where('sku_regions.product_id', $product->id)
                ->where('region_prices.recorded_at', '>=', now()->subDays(30))
                ->groupBy('sku_regions.region_code', DB::raw('DATE(region_prices.recorded_at)'))
                ->orderBy('day')
                ->get()
                ->groupBy('region_code')
                ->map(function ($group, $code) {
                    $points = $group->map(fn ($row) => [
                        (string) $row->day,
                        round((float) $row->btc_average, 8),
                    ])->values();

                    return [
                        'region' => $code,
                        'points' => $points,
                        'trend' => TrendlineCalculator::fromPoints($points),
                    ];
                })
                ->values()
        );

        return response()->json([
            'series' => $series,
            'meta' => [
                'unit' => 'BTC',
            ],
        ]);
    }
}
