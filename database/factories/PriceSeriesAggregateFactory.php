<?php

namespace Database\Factories;

use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PriceSeriesAggregate>
 */
class PriceSeriesAggregateFactory extends Factory
{
    protected $model = PriceSeriesAggregate::class;

    public function definition(): array
    {
        $start = Carbon::now()->subDay()->startOfDay();

        return [
            'product_id' => Product::factory(),
            'region_code' => $this->faker->randomElement(['US', 'CA', 'GB']),
            'bucket' => 'day',
            'window_start' => $start,
            'window_end' => (clone $start)->addDay(),
            'tax_inclusive' => true,
            'min_btc' => 0.0005,
            'max_btc' => 0.0008,
            'avg_btc' => 0.00065,
            'min_fiat' => 30,
            'max_fiat' => 60,
            'avg_fiat' => 45,
            'sample_count' => 5,
            'metadata' => [
                'retailer_count' => $this->faker->numberBetween(1, 3),
            ],
        ];
    }

    public function forProduct(Product $product): self
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    public function taxInclusive(bool $flag): self
    {
        return $this->state(fn () => ['tax_inclusive' => $flag]);
    }
}
