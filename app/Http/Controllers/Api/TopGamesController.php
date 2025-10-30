<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TopGamesRequest;
use App\Http\Resources\TopGameResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TopGamesController extends Controller
{
    public function __invoke(TopGamesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        $query = Product::query()
            ->with([
                'platforms',
                'genres',
                'media' => fn ($relation) => $relation
                    ->orderByDesc('is_primary')
                    ->orderByDesc('quality_score')
                    ->orderByDesc('fetched_at'),
            ])
            ->whereNotNull('uid');

        if ($filters['platforms']) {
            $query->whereHas('platforms', fn ($platformQuery) => $platformQuery
                ->whereIn('code', $filters['platforms']));
        }

        if ($filters['genres']) {
            $query->whereHas('genres', fn ($genreQuery) => $genreQuery
                ->whereIn('slug', $filters['genres']));
        }

        if ($filters['query']) {
            $term = '%'.str_replace(' ', '%', strtolower($filters['query'])).'%';
            $query->where(function ($builder) use ($term) {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            });
        }

        $products = $query
            ->orderByDesc('popularity_score')
            ->orderByDesc('rating')
            ->orderByDesc('freshness_score')
            ->limit($filters['limit'])
            ->get();

        return TopGameResource::collection($products)->additional([
            'meta' => [
                'generated_at' => now(),
                'filters' => array_filter([
                    'platforms' => $filters['platforms'],
                    'genres' => $filters['genres'],
                    'query' => $filters['query'],
                ]),
            ],
        ]);
    }
}
