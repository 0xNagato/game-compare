<?php

namespace Database\Seeders;

use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PriceSeriesAggregateSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'product' => 'the-legend-of-zelda-tears-of-the-kingdom',
                'region_code' => 'US',
                'min_fiat' => 59.99,
                'max_fiat' => 69.99,
                'avg_fiat' => 62.49,
                'min_btc' => 0.00096,
                'max_btc' => 0.00112,
                'avg_btc' => 0.00102,
            ],
            [
                'product' => 'marvels-spider-man-2',
                'region_code' => 'US',
                'min_fiat' => 59.99,
                'max_fiat' => 69.99,
                'avg_fiat' => 64.99,
                'min_btc' => 0.00095,
                'max_btc' => 0.00112,
                'avg_btc' => 0.00101,
            ],
            [
                'product' => 'starfield',
                'region_code' => 'US',
                'min_fiat' => 54.99,
                'max_fiat' => 69.99,
                'avg_fiat' => 62.49,
                'min_btc' => 0.00095,
                'max_btc' => 0.0012,
                'avg_btc' => 0.00105,
            ],
        ];

        $windowStart = Carbon::now()->subDay()->startOfDay();
        $windowEnd = (clone $windowStart)->addDay();

        foreach ($entries as $entry) {
            $product = Product::query()->where('slug', $entry['product'])->first();

            if (! $product) {
                continue;
            }

            PriceSeriesAggregate::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'region_code' => $entry['region_code'],
                    'bucket' => 'day',
                    'window_start' => $windowStart,
                    'tax_inclusive' => true,
                ],
                PriceSeriesAggregate::factory()->forProduct($product)->state([
                    'region_code' => $entry['region_code'],
                    'bucket' => 'day',
                    'window_start' => $windowStart,
                    'window_end' => $windowEnd,
                    'tax_inclusive' => true,
                    'min_fiat' => $entry['min_fiat'],
                    'max_fiat' => $entry['max_fiat'],
                    'avg_fiat' => $entry['avg_fiat'],
                    'min_btc' => $entry['min_btc'],
                    'max_btc' => $entry['max_btc'],
                    'avg_btc' => $entry['avg_btc'],
                    'sample_count' => 6,
                    'metadata' => ['retailer_count' => 2],
                ])->make()->toArray()
            );
        }
    }
}