<?php

namespace App\Services\Media\Synchronizers;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Media\DTOs\ProductMediaData;
use App\Services\Media\Providers\TheGamesDbProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TheGamesDbSynchronizer
{
    public function __construct(private readonly TheGamesDbProvider $provider) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function syncProduct(Product $product, array $context = []): int
    {
        $media = $this->provider->fetch($product, $context);

        if ($media->isEmpty()) {
            return 0;
        }

        $synced = 0;

        $media->each(function (ProductMediaData $item) use ($product, &$synced): void {
            $attributes = [
                'product_id' => $product->id,
                'source' => $item->source,
                'external_id' => $item->externalId,
            ];

            $values = array_merge($item->toArray(), [
                'product_id' => $product->id,
                'fetched_at' => now(),
            ]);

            try {
                ProductMedia::query()->updateOrCreate($attributes, $values);
                $synced++;
            } catch (\Throwable $exception) {
                Log::warning('media.thegamesdb.sync_failed', [
                    'product_id' => $product->id,
                    'source' => $item->source,
                    'external_id' => $item->externalId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        return $synced;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<string, mixed>  $context
     */
    public function syncMany(Collection $products, array $context = []): int
    {
        return $products
            ->filter()
            ->sum(fn (Product $product) => $this->syncProduct($product, $context));
    }
}
