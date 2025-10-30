<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameDetailResource;
use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\RegionPrice;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameDetailController extends Controller
{
    public function __invoke(Request $request, string $uid): JsonResponse
    {
        $product = Product::query()
            ->with([
                'platforms',
                'genres',
                'media' => fn ($relation) => $relation
                    ->orderByDesc('is_primary')
                    ->orderByDesc('quality_score')
                    ->orderByDesc('fetched_at'),
                'skuRegions.currency',
                'skuRegions.country',
                'skuRegions.regionPrices' => fn ($relation) => $relation->orderByDesc('recorded_at')->limit(1),
            ])
            ->where('uid', $uid)
            ->orWhere('slug', $uid)
            ->firstOrFail();

        $series = $this->buildPriceSeries($product->id);
        $regions = $this->buildRegionComparison($product->id);
        $offers = $this->buildOffers($product->skuRegions);

        $resource = new GameDetailResource($product, [
            'price_series' => $series,
            'region_compare' => $regions,
            'offers' => $offers,
        ]);

        return $resource->response($request)->setStatusCode(200);
    }

    /**
     * @return list<array{date:string,btc_value:float,fiat_avg:float}>
     */
    protected function buildPriceSeries(int $productId): array
    {
        return PriceSeriesAggregate::query()
            ->where('product_id', $productId)
            ->where('bucket', 'daily')
            ->where('window_start', '>=', now()->subDays(30))
            ->orderBy('window_start')
            ->get()
            ->map(fn (PriceSeriesAggregate $row) => [
                'date' => $row->window_start->toDateString(),
                'btc_value' => (float) $row->avg_btc,
                'fiat_avg' => (float) $row->avg_fiat,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{region_code:string,median_price:float,btc_value:float}>
     */
    protected function buildRegionComparison(int $productId): array
    {
        $series = PriceSeriesAggregate::query()
            ->selectRaw('region_code, AVG(avg_fiat) as avg_price, AVG(avg_btc) as avg_btc')
            ->where('product_id', $productId)
            ->where('bucket', 'daily')
            ->where('window_start', '>=', now()->subDays(30))
            ->groupBy('region_code')
            ->orderByRaw('AVG(avg_fiat) asc')
            ->get();

        return $series->map(fn ($row) => [
            'region_code' => $row->region_code,
            'median_price' => (float) $row->avg_price,
            'btc_value' => (float) $row->avg_btc,
        ])->values()->all();
    }

    /**
     * @param  EloquentCollection<int,\App\Models\SkuRegion>  $regions
     * @return list<array<string, mixed>>
     */
    protected function buildOffers(EloquentCollection $regions): array
    {
        return $regions
            ->map(function ($skuRegion) {
                /** @var RegionPrice|null $latest */
                $latest = $skuRegion->regionPrices->first();

                if (! $latest) {
                    return null;
                }

                return [
                    'retailer' => $skuRegion->retailer,
                    'region_code' => $skuRegion->region_code,
                    'currency' => $skuRegion->currency?->code ?? $skuRegion->currency,
                    'price' => (float) $latest->fiat_amount,
                    'btc_value' => (float) $latest->btc_value,
                    'last_change' => optional($latest->recorded_at)?->toIso8601String(),
                    'url' => $skuRegion->metadata['url'] ?? null,
                    'verified' => ! empty($skuRegion->metadata['verified_at']),
                    'country' => $skuRegion->country?->code,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
