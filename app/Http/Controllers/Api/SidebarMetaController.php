<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class SidebarMetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $genres = Genre::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Genre $genre) => [
                'slug' => $genre->slug,
                'name' => $genre->name,
                'count' => $genre->products_count,
            ])
            ->values();

        $platforms = Platform::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get()
            ->groupBy('family')
            ->map(fn ($group) => [
                'family' => $group->first()->family,
                'count' => $group->sum('products_count'),
                'platforms' => $group->map(fn ($platform) => [
                    'code' => $platform->code,
                    'name' => $platform->name,
                    'count' => $platform->products_count,
                ])->values(),
            ])
            ->values();

        $freshness = Product::query()
            ->selectRaw('
                SUM(CASE WHEN freshness_score >= 0.75 THEN 1 ELSE 0 END) as hot,
                SUM(CASE WHEN freshness_score BETWEEN 0.5 AND 0.749 THEN 1 ELSE 0 END) as warm,
                SUM(CASE WHEN freshness_score < 0.5 THEN 1 ELSE 0 END) as legacy
            ')
            ->first();

        return response()->json([
            'genres' => $genres,
            'platform_families' => $platforms,
            'freshness_segments' => [
                'hot' => (int) ($freshness->hot ?? 0),
                'warm' => (int) ($freshness->warm ?? 0),
                'legacy' => (int) ($freshness->legacy ?? 0),
            ],
        ]);
    }
}
