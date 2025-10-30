<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductPresenter;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class GamePageController
{
    public function __invoke(Product $product): View
    {
        $payload = Cache::remember(
            sprintf('game:detail:bootstrap:%d', $product->id),
            now()->addMinutes(5),
            function () use ($product): array {
                $aggregateCollection = ProductPresenter::aggregateMap([$product]);
                $aggregateSet = $aggregateCollection->get($product->id);

                return ProductPresenter::present($product, $aggregateSet);
            }
        );

        return view('games.show', [
            'product' => $payload,
        ]);
    }
}
