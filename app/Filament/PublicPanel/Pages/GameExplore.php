<?php

namespace App\Filament\PublicPanel\Pages;

use App\Models\Product;
use App\Support\ProductPresenter;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class GameExplore extends Page
{
    protected static ?string $slug = '';

    protected string $view = 'filament.public.pages.game-explore';

    protected static ?string $title = 'Discover Games';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $games = [];

    /**
     * @var array<int, string>
     */
    public array $platformFilters = [];

    /**
     * @var array<int, string>
     */
    public array $categoryFilters = [];

    public string $search = '';

    public string $platform = '';

    public string $category = '';

    public bool $onlyHighlights = false;

    public function mount(): void
    {
        $products = Product::query()
            ->select([
                'id',
                'name',
                'slug',
                'platform',
                'category',
                'release_date',
                'updated_at',
                'rating',
                'popularity_score',
            ])
            ->with([
                'media' => fn ($query) => $query
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->limit(6),
                'skuRegions:id,product_id,region_code',
            ])
            ->orderByDesc('release_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $aggregateMap = ProductPresenter::aggregateMap($products);

        $this->games = $products
            ->map(function (Product $product) use ($aggregateMap): array {
                $aggregateSet = $aggregateMap->get($product->id);
                $presented = ProductPresenter::present($product, $aggregateSet);

                $presented['rating'] = $product->rating !== null
                    ? round((float) $product->rating, 2)
                    : null;
                $presented['popularity_score'] = $product->popularity_score !== null
                    ? round((float) $product->popularity_score, 3)
                    : null;

                return $presented;
            })
            ->values()
            ->all();

        $this->platformFilters = $products
            ->pluck('platform')
            ->filter()
            ->map(fn ($platform) => trim((string) $platform))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->categoryFilters = $products
            ->pluck('category')
            ->filter()
            ->map(fn ($category) => trim((string) $category))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredGamesProperty(): array
    {
        return Collection::make($this->games)
            ->filter(function (array $game): bool {
                if ($this->onlyHighlights && (($game['popularity_score'] ?? 0) < 0.7)) {
                    return false;
                }

                if ($this->platform !== '') {
                    $platform = mb_strtolower((string) ($game['platform'] ?? ''));
                    if ($platform !== mb_strtolower($this->platform)) {
                        return false;
                    }
                }

                if ($this->category !== '') {
                    $category = mb_strtolower((string) ($game['category'] ?? ''));
                    if ($category !== mb_strtolower($this->category)) {
                        return false;
                    }
                }

                if ($this->search !== '') {
                    $term = mb_strtolower($this->search);
                    $haystack = implode(' ', array_map(function ($value) {
                        return mb_strtolower((string) $value);
                    }, [
                        $game['name'] ?? '',
                        $game['platform'] ?? '',
                        $game['category'] ?? '',
                    ]));

                    if (! str_contains($haystack, $term)) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }
}
