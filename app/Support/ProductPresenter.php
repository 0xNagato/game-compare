<?php

namespace App\Support;

use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class ProductPresenter
{
    /**
     * Build a map of product_id => aggregates collection for the given products or IDs.
     *
     * @param  iterable<int, Product|int|string>  $products
     * @return Collection<int, Collection<int, PriceSeriesAggregate>>
     */
    public static function aggregateMap(iterable $products): Collection
    {
        $ids = collect($products)
            ->map(function ($product) {
                if ($product instanceof Product) {
                    return $product->id;
                }

                if (is_numeric($product)) {
                    return (int) $product;
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $rows = PriceSeriesAggregate::query()
            ->whereIn('product_id', $ids)
            ->where('window_start', '>=', now()->copy()->subDays(90))
            ->orderByDesc('window_start')
            ->get();

        /** @var Collection<int, PriceSeriesAggregate> $rows */
        return $rows->groupBy('product_id');
    }

    /**
     * Present a product for public API consumption.
     *
     * @param  Collection<int, PriceSeriesAggregate>|null  $aggregates
     * @return array{
     *   id:int,
     *   name:string|null,
     *   slug:string|null,
     *   platform:string|null,
     *   category:string|null,
     *   release_date:string|null,
     *   rating:float|null,
     *   popularity_score:float|null,
     *   image:string|null,
     *   trailer_url:string|null,
    *   trailer_thumbnail:string|null,
    *   trailer_play_url:string|null,
     *   region_codes: array<int, string>,
     *   price_summary: array{
     *     best_region:string,
     *     avg_btc:float,
     *     avg_fiat:float,
     *     sample_count:int,
     *     window_start:string|null,
     *     window_end:string|null,
     *     region_count:int,
     *     trend: array{
     *       direction:string,
     *       delta_btc:float,
     *       percent_change:float|null,
     *       previous_window_start:string|null,
     *       previous_window_end:string|null
     *     }
     *   }|null,
     *   updated_at:string|null
     * }
     */
    public static function present(Product $product, ?Collection $aggregates = null): array
    {
        $mediaItems = $product->getRelationValue('media');

        if (! $mediaItems instanceof Collection) {
            $mediaItems = collect();
        }

        $cover = self::resolveCoverMedia($mediaItems);
        $trailer = self::resolveTrailerMedia($mediaItems);

    $imageUrl = $cover?->thumbnail_url ?: $cover?->url;

        if (! $imageUrl && $trailer) {
            $imageUrl = $trailer->thumbnail_url ?: $trailer->url;
        }

    $regionCodes = self::regionCodes($product, $aggregates);
        $priceSummary = self::priceSummary($aggregates);
    $trailerPlayUrl = self::proxyPlayUrl($trailer?->url);

    $rd = $product->release_date;

    return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'platform' => $product->platform,
            'category' => $product->category,
            'release_date' => $rd instanceof \DateTimeInterface ? $rd->format('Y-m-d') : (is_string($rd) && $rd !== '' ? $rd : null),
            'rating' => $product->rating !== null ? (float) $product->rating : null,
            'popularity_score' => $product->popularity_score !== null ? (float) $product->popularity_score : null,
            'image' => $imageUrl,
            'trailer_url' => $trailer?->url,
            'trailer_thumbnail' => $trailer?->thumbnail_url ?: $trailer?->url,
            'trailer_play_url' => $trailerPlayUrl,
            'region_codes' => $regionCodes,
            'price_summary' => $priceSummary,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, ProductMedia>  $mediaItems
     */
    protected static function resolveCoverMedia(Collection $mediaItems): ?ProductMedia
    {
        return $mediaItems
            ->sortByDesc('fetched_at')
            ->sortByDesc('id')
            ->firstWhere('media_type', 'image')
            ?? $mediaItems->sortByDesc('fetched_at')->sortByDesc('id')->first();
    }

    /**
     * @param  Collection<int, ProductMedia>  $mediaItems
     */
    protected static function resolveTrailerMedia(Collection $mediaItems): ?ProductMedia
    {
        return $mediaItems
            ->sortByDesc('fetched_at')
            ->sortByDesc('id')
            ->firstWhere('media_type', 'video');
    }

    /**
     * @param  Collection<int, PriceSeriesAggregate>|null  $aggregates
     * @return array<int, string>
     */
    protected static function regionCodes(Product $product, ?Collection $aggregates = null): array
    {
        $regions = $product->getRelationValue('skuRegions');

        if ($regions instanceof Collection && $regions->isNotEmpty()) {
            return $regions
                ->pluck('region_code')
                ->filter()
                ->map(static fn ($code) => strtoupper((string) $code))
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        if ($aggregates instanceof Collection && $aggregates->isNotEmpty()) {
            return $aggregates
                ->pluck('region_code')
                ->filter()
                ->map(static fn ($code) => strtoupper((string) $code))
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  Collection<int, PriceSeriesAggregate>|null  $aggregates
     * @return array{
     *   best_region:string,
     *   avg_btc:float,
     *   avg_fiat:float,
     *   sample_count:int,
     *   window_start:string|null,
     *   window_end:string|null,
     *   region_count:int,
     *   trend: array{
     *     direction:string,
     *     delta_btc:float,
     *     percent_change:float|null,
     *     previous_window_start:string|null,
     *     previous_window_end:string|null
     *   }
     * }|null
     */
    protected static function priceSummary(?Collection $aggregates = null): ?array
    {
        if (! ($aggregates instanceof Collection) || $aggregates->isEmpty()) {
            return null;
        }

        $latestWindow = $aggregates->max('window_start');

        if ($latestWindow === null) {
            return null;
        }

        $windowRows = $aggregates
            ->filter(static fn (PriceSeriesAggregate $aggregate) => $aggregate->window_start == $latestWindow);

        if ($windowRows->isEmpty()) {
            return null;
        }

        $best = $windowRows
            ->sortBy(static fn (PriceSeriesAggregate $aggregate) => (float) $aggregate->avg_btc)
            ->first();

        if (! $best) {
            return null;
        }

        $trend = [
            'direction' => 'flat',
            'delta_btc' => 0.0,
            'percent_change' => null,
            'previous_window_start' => null,
            'previous_window_end' => null,
        ];

        $previousWindowRow = $aggregates
            ->filter(static function (PriceSeriesAggregate $aggregate) use ($best) {
                if ($aggregate->region_code !== $best->region_code) {
                    return false;
                }

                if ($best->window_start === null || $aggregate->window_start === null) {
                    return false;
                }

                return $aggregate->window_start < $best->window_start;
            })
            ->sortByDesc('window_start')
            ->first();

        if ($previousWindowRow instanceof PriceSeriesAggregate) {
            $previousAvg = (float) $previousWindowRow->avg_btc;
            $currentAvg = (float) $best->avg_btc;
            $delta = $currentAvg - $previousAvg;

            $trend['delta_btc'] = $delta;
            $trend['percent_change'] = $previousAvg !== 0.0 ? ($delta / $previousAvg) * 100.0 : null;
            $trend['previous_window_start'] = $previousWindowRow->window_start?->toIso8601String();
            $trend['previous_window_end'] = $previousWindowRow->window_end?->toIso8601String();

            $epsilon = 0.0000005;

            if ($delta < -$epsilon) {
                $trend['direction'] = 'down';
            } elseif ($delta > $epsilon) {
                $trend['direction'] = 'up';
            }
        }

        return [
            'best_region' => strtoupper((string) $best->region_code),
            'avg_btc' => (float) $best->avg_btc,
            'avg_fiat' => (float) $best->avg_fiat,
            'sample_count' => (int) $best->sample_count,
            'window_start' => $best->window_start?->toIso8601String(),
            'window_end' => $best->window_end?->toIso8601String(),
            'region_count' => $windowRows->pluck('region_code')->filter()->unique()->count(),
            'trend' => $trend,
        ];
    }

    protected static function proxyPlayUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        // Only proxy if the URL looks like a direct video we can inline
        $lower = strtolower($url);
        if (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be') || str_contains($lower, 'vimeo.com')) {
            return null; // external viewers will be opened in new tab
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $inlineExts = ['mp4', 'webm', 'm4v', 'mov'];
        if (! in_array($ext, $inlineExts, true)) {
            return null;
        }

        // Build signed proxy URL with a friendly filename in path for client-side detection
        $filename = 'trailer.'.$ext;
        $src = self::base64urlEncode($url);

        try {
            return URL::signedRoute('media.play', ['name' => 'trailer', 'ext' => $ext, 'src' => $src]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function base64urlEncode(string $value): string
    {
        $b64 = base64_encode($value);
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }
}
