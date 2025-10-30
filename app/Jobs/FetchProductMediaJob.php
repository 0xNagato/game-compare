<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Media\ProductMediaAggregator;
use App\Support\Concerns\HasIdempotencyKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchProductMediaJob implements ShouldQueue
{
    use Dispatchable;
    use HasIdempotencyKey;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $productId,
        public array $context = [],
        public ?string $idempotencyKey = null,
    ) {
        $this->onQueue('media');

        $this->idempotencyKey ??= sprintf('media:%s:%s', $productId, sha1(json_encode($context)));
    }

    public function backoff(): array
    {
        return [30, 90, 180];
    }

    public function handle(ProductMediaAggregator $aggregator): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            Log::warning('media.product_missing', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $results = $aggregator->fetchAndStore($product, $this->context);

        Log::info('media.job_completed', [
            'product_id' => $product->id,
            'fetched' => $results->count(),
        ]);
    }
}
