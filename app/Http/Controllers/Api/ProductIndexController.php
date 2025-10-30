<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ProductPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ProductIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'platform' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', 'in:release_desc,release_asc,rating_desc,rating_asc,popularity_desc,popularity_asc,updated_desc,updated_asc'],
        ])->validate();

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Product::query()
            ->with([
                'skuRegions:id,product_id,region_code',
                'media' => fn ($relation) => $relation
                    ->orderByDesc('fetched_at')
                    ->orderByDesc('id')
                    ->limit(8),
            ])
            ->when(! empty($validated['category']), function ($builder) use ($validated) {
                $builder->where('category', $validated['category']);
            })
            ->when(! empty($validated['platform']), function ($builder) use ($validated) {
                $builder->where('platform', $validated['platform']);
            });

        // Sorting strategy
        $sort = $validated['sort'] ?? 'release_desc';
        switch ($sort) {
            case 'release_asc':
                $query->orderBy('release_date')->orderBy('id');
                break;
            case 'rating_desc':
                $query->orderByDesc('rating')->orderByDesc('release_date')->orderByDesc('id');
                break;
            case 'rating_asc':
                $query->orderBy('rating')->orderByDesc('release_date')->orderBy('id');
                break;
            case 'popularity_desc':
                $query->orderByDesc('popularity_score')->orderByDesc('release_date')->orderByDesc('id');
                break;
            case 'popularity_asc':
                $query->orderBy('popularity_score')->orderByDesc('release_date')->orderBy('id');
                break;
            case 'updated_desc':
                $query->orderByDesc('updated_at')->orderByDesc('id');
                break;
            case 'updated_asc':
                $query->orderBy('updated_at')->orderBy('id');
                break;
            case 'release_desc':
            default:
                $query->orderByDesc('release_date')->orderByDesc('updated_at')->orderByDesc('id');
                break;
        }

        if (! empty($validated['search'])) {
            $term = $validated['search'];
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('platform', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            });
        }

        $products = $query->cursorPaginate(
            $perPage,
            ['*'],
            'cursor',
            $validated['cursor'] ?? null
        );

        $items = collect($products->items());
        $aggregateMap = ProductPresenter::aggregateMap($items);

        $data = $items->map(function (Product $product) use ($aggregateMap) {
            $aggregateSet = $aggregateMap->get($product->id);

            return ProductPresenter::present($product, $aggregateSet);
        })->values()->all();

        $payload = [
            'data' => $data,
            'meta' => [
                'per_page' => $perPage,
                'next_cursor' => optional($products->nextCursor())->encode(),
                'prev_cursor' => optional($products->previousCursor())->encode(),
                'cache_ttl' => 900,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ];

        $response = response()->json($payload);
        $response->setEtag(sha1(json_encode($payload)));
        $response->header('Cache-Control', 'public, max-age=900');

        return $response;
    }
}
