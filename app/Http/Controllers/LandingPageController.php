<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Support\ProductPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LandingPageController
{
    private const CONSOLE_FAMILY_GROUPS = [
        'switch' => [
            'label' => 'Nintendo Switch',
            'families' => ['nintendo'],
            'limit' => 8,
        ],
        'playstation' => [
            'label' => 'PlayStation 5',
            'families' => ['playstation'],
            'limit' => 8,
        ],
        'xbox' => [
            'label' => 'Xbox Series X|S',
            'families' => ['xbox'],
            'limit' => 8,
        ],
    ];

    public function __invoke(): View
    {
        /**
         * @var Collection<int, array{slug:string,name:string,platform:?string,image:?string,attribution:?string}> $featured
         */
        $featured = Cache::remember('landing:featured-products', now()->addMinutes(10), function (): Collection {
            $products = Product::query()
                ->select(['id', 'name', 'slug', 'platform', 'category', 'updated_at'])
                ->whereHas('media', fn ($query) => $query->whereIn('media_type', ['image', 'video']))
                ->with([
                    'media' => fn ($query) => $query
                        ->whereIn('media_type', ['image', 'video'])
                        ->latest('fetched_at')
                        ->latest('id')
                        ->limit(12),
                    'skuRegions:id,product_id,region_code',
                ])
                ->latest('updated_at')
                ->limit(24)
                ->get();

            $aggregateMap = ProductPresenter::aggregateMap($products);

            return $products
                ->map(function (Product $product) use ($aggregateMap): array {
                    $aggregateSet = $aggregateMap->get($product->id);

                    return ProductPresenter::present($product, $aggregateSet);
                })
                ->filter(fn (array $item): bool => filled($item['image']) || filled($item['trailer_thumbnail']))
                ->values();
        });

        $metrics = Cache::remember('landing:metrics', now()->addMinutes(15), function (): array {
            return [
                'products' => Product::count(),
                'regions' => SkuRegion::query()->distinct('region_code')->count('region_code'),
                'snapshots24h' => RegionPrice::query()
                    ->where('recorded_at', '>=', now()->subDay())
                    ->count(),
            ];
        });

        $heroCandidate = $featured->first(fn (array $item) => filled($item['image']) || filled($item['trailer_thumbnail'])) ?? $featured->first();
        $heroImage = $heroCandidate['image'] ?? $heroCandidate['trailer_thumbnail'] ?? null;

        $spotlight = $featured->take(12);
        $gallery = $featured->skip(12)->take(20);

        if ($gallery->isEmpty()) {
            $gallery = $spotlight;
        }

        return view('landing.index', [
            'heroImage' => $heroImage,
            'featuredProducts' => $spotlight,
            'gallery' => $gallery,
            'metrics' => $metrics,
            'trendingConsoles' => $this->trendingByConsole(),
        ]);
    }

    /**
     * @return array<string, array{label:string,families:array<int,string>,family_label:string,consoles:array<int,array<string,mixed>>}>
     */
    protected function trendingByConsole(): array
    {
        return Cache::remember('landing:console-watchlist:v1', now()->addMinutes(30), function (): array {
            $groups = [];

            foreach (self::CONSOLE_FAMILY_GROUPS as $key => $config) {
                $group = $this->buildConsoleGroup($key, $config);

                if ($group !== null) {
                    $groups[$key] = $group;
                }
            }

            return $groups;
        });
    }

    protected function buildConsoleGroup(string $key, array $config): ?array
    {
        $familyValues = collect($config['families'] ?? $config['family'] ?? [$key])
            ->flatMap(function ($value) {
                if (is_array($value)) {
                    return $value;
                }

                return [$value];
            })
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($familyValues->isEmpty()) {
            $familyValues = collect([strtolower($key)]);
        }

        $familyLabels = $familyValues
            ->map(fn ($family) => ucwords(str_replace(['-', '_'], ' ', $family)))
            ->values();

        $limit = (int) ($config['limit'] ?? 8);

        if ($limit <= 0) {
            $limit = 6;
        }

        $categoryCandidates = [
            'Console',
            'Hardware',
            'Platform',
        ];

        $products = Product::query()
            ->select([
                'id',
                'name',
                'slug',
                'platform',
                'category',
                'primary_platform_family',
                'popularity_score',
                'freshness_score',
                'rating',
                'updated_at',
            ])
            ->whereIn('category', $categoryCandidates)
            ->whereNotNull('slug')
            ->whereIn('primary_platform_family', $familyValues->all())
            ->with([
                'media' => fn ($query) => $query
                    ->whereIn('media_type', ['image', 'video'])
                    ->orderByDesc('is_primary')
                    ->orderByDesc('quality_score')
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->limit(8),
                'skuRegions:id,product_id,region_code',
            ])
            ->orderByDesc('popularity_score')
            ->orderByDesc('freshness_score')
            ->orderByDesc('rating')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $aggregateMap = ProductPresenter::aggregateMap($products);

        $items = $products
            ->map(function (Product $product) use ($aggregateMap): array {
                /** @var Collection<int, mixed>|null $aggregates */
                $aggregates = $aggregateMap->get($product->id);

                $presented = ProductPresenter::present($product, $aggregates);

                $latestWindow = null;

                if ($aggregates instanceof Collection && $aggregates->isNotEmpty()) {
                    $latestWindow = $aggregates
                        ->pluck('window_end')
                        ->filter(fn ($value) => $value instanceof Carbon)
                        ->max();
                }

                $priceSummary = $presented['price_summary'] ?? null;
                $regionCount = $priceSummary['region_count'] ?? count($presented['region_codes'] ?? []);
                $sampleCount = $priceSummary['sample_count'] ?? null;

                $familyLabel = $product->primary_platform_family
                    ? ucwords(str_replace(['-', '_'], ' ', $product->primary_platform_family))
                    : 'Console';

                $presented['primary_platform_family'] = $product->primary_platform_family;
                $presented['platform_label'] = $product->platform ?: $familyLabel;
                $presented['analytics'] = [
                    'region_count' => $regionCount,
                    'sample_count' => $sampleCount,
                    'best_region' => $priceSummary['best_region'] ?? null,
                    'avg_btc' => $priceSummary['avg_btc'] ?? null,
                    'window_end' => $latestWindow instanceof Carbon ? $latestWindow->toIso8601String() : null,
                    'window_end_human' => $latestWindow instanceof Carbon ? $latestWindow->diffForHumans() : null,
                    'has_pricing' => $priceSummary !== null,
                ];

                return $presented;
            })
            ->values();

        return [
            'label' => $config['label'] ?? ucfirst($key),
            'families' => $familyValues->all(),
            'family_label' => $familyLabels->implode(' / '),
            'consoles' => $items->all(),
        ];
    }
}
