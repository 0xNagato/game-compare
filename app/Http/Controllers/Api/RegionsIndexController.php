<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkuRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RegionsIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cacheKey = 'regions:index';

        $regions = Cache::remember($cacheKey, now()->addHours(6), function () {
            return SkuRegion::query()
                ->select('region_code')
                ->selectRaw('COUNT(DISTINCT product_id) as product_count')
                ->selectRaw('COUNT(DISTINCT retailer) as retailer_count')
                ->groupBy('region_code')
                ->orderBy('region_code')
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->region_code,
                    'product_count' => (int) $row->product_count,
                    'retailer_count' => (int) $row->retailer_count,
                ])
                ->values()
                ->all();
        });

        $payload = [
            'data' => $regions,
            'meta' => [
                'count' => count($regions),
                'cache_ttl' => 21_600,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ];

        $response = response()->json($payload);
        $response->setEtag(sha1(json_encode($payload)));
        $response->header('Cache-Control', 'public, max-age=21600');

        return $response;
    }
}
