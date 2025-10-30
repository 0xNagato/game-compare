<?php

namespace App\Http\Controllers;

use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\SkuRegion;
use App\Services\Catalogue\PriceCrossReferencer;
use App\Support\ProductPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ComparePageController
{
    public function __construct(private readonly PriceCrossReferencer $priceCrossReferencer)
    {
    }

    public function __invoke(): View
    {
        /**
         * @var Collection<int, array{id:int,name:string,slug:string,platform:?string,category:?string,image:?string,region_codes:array<int,string>,updated_at:?string}> $spotlight
         */
        $spotlight = Cache::remember('compare:spotlight-products', now()->addMinutes(15), function (): Collection {
            $products = Product::query()
                ->select(['id', 'name', 'slug', 'platform', 'category', 'release_date', 'updated_at'])
                ->with([
                    'media' => fn ($query) => $query
                        ->orderByDesc('fetched_at')
                        ->orderByDesc('id')
                        ->limit(8),
                    'skuRegions:id,product_id,region_code',
                ])
                ->whereHas('media', fn ($query) => $query->where('media_type', 'image'))
                ->orderByDesc('release_date')
                ->orderByDesc('updated_at')
                ->limit(24)
                ->get();

            $aggregates = ProductPresenter::aggregateMap($products);

            return $products
                ->map(function (Product $product) use ($aggregates): array {
                    $aggregateSet = $aggregates->get($product->id);

                    return ProductPresenter::present($product, $aggregateSet);
                })
                ->filter(fn (array $payload) => ! empty($payload['image']))
                ->values();
        });

        $initialProduct = $spotlight->first() ?? Cache::remember('compare:initial-product', now()->addMinutes(10), function () {
            $product = Product::query()
                ->select(['id', 'name', 'slug', 'platform', 'category', 'release_date', 'updated_at'])
                ->with([
                    'media' => fn ($query) => $query
                        ->orderByDesc('fetched_at')
                        ->orderByDesc('id')
                        ->limit(8),
                    'skuRegions:id,product_id,region_code',
                ])
                ->whereHas('media', fn ($query) => $query->where('media_type', 'image'))
                ->orderByDesc('release_date')
                ->orderByDesc('updated_at')
                ->first();

            if (! $product) {
                return null;
            }

            $aggregateCollection = ProductPresenter::aggregateMap([$product]);
            $aggregateSet = $aggregateCollection->get($product->id);

            $presented = ProductPresenter::present($product, $aggregateSet);

            return ! empty($presented['image']) ? $presented : null;
        });

        if (! $initialProduct) {
            abort(404, 'No products available for comparison.');
        }

        $regionOptions = Cache::remember('compare:regions', now()->addHours(6), function () {
            $regions = SkuRegion::query()
                ->select('region_code')
                ->distinct()
                ->orderBy('region_code')
                ->pluck('region_code');

            if ($regions->isEmpty()) {
                $regions = PriceSeriesAggregate::query()
                    ->select('region_code')
                    ->distinct()
                    ->orderBy('region_code')
                    ->pluck('region_code');
            }

            return $regions
                ->map(fn ($code) => strtoupper((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();
        });

        $crossReferenceMatches = $this->priceCrossReferencer
            ->build()
            ->filter(function (array $row) {
                return ($row['has_digital'] ?? false) || ($row['has_physical'] ?? false);
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $displayLimit = max(1, (int) config('catalogue.cross_reference.frontend_limit', 600));
        $displayMatches = $crossReferenceMatches->take($displayLimit);

        $matchStats = [
            'total' => $crossReferenceMatches->count(),
            'displayed' => $displayMatches->count(),
            'display_limit' => $displayLimit,
            'digital' => $crossReferenceMatches->where('has_digital', true)->count(),
            'physical' => $crossReferenceMatches->where('has_physical', true)->count(),
            'both' => $crossReferenceMatches
                ->filter(fn (array $row) => ($row['has_digital'] ?? false) && ($row['has_physical'] ?? false))
                ->count(),
        ];

        $platformFilters = $crossReferenceMatches
            ->flatMap(fn (array $row) => $row['platforms'] ?? [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $currencyFilters = $crossReferenceMatches
            ->flatMap(function (array $row) {
                return collect($row['digital']['currencies'] ?? [])->pluck('code');
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('compare.index', [
            'initialProduct' => $initialProduct,
            'spotlight' => $spotlight->all(),
            'regionOptions' => $regionOptions,
            'crossReferenceMatches' => $displayMatches->values()->all(),
            'crossReferenceStats' => $matchStats,
            'crossReferencePlatforms' => $platformFilters,
            'crossReferenceCurrencies' => $currencyFilters,
        ]);
    }
}