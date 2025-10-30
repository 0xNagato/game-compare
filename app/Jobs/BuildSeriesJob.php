<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Aggregation\AggregateBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuildSeriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $productId)
    {
        $this->onQueue('aggregate');
    }

    public function backoff(): int
    {
        return 60;
    }

    public function handle(AggregateBuilder $builder): void
    {
        $product = Product::query()->with('skuRegions')->find($this->productId);

        if (! $product) {
            Log::notice('series.build_skipped_missing_product', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $regions = $product->skuRegions
            ->pluck('region_code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $context = [
            'product_id' => $product->id,
            'bucket' => 'day',
            'from' => now()->subDays(30)->startOfDay()->toIso8601String(),
            'to' => now()->endOfDay()->toIso8601String(),
            'regions' => $regions,
        ];

        $builder->build($context);

        Log::info('series.build_completed', [
            'product_id' => $product->id,
            'regions' => $regions,
        ]);
    }
}
