<?php

namespace Database\Factories;

use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegionPrice>
 */
class RegionPriceFactory extends Factory
{
    protected $model = RegionPrice::class;

    public function definition(): array
    {
        $fiat = $this->faker->randomFloat(2, 10, 500);
        $btcRate = $this->faker->randomFloat(8, 0.000001, 0.1);

        return [
            'sku_region_id' => SkuRegion::factory(),
            'recorded_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'fiat_amount' => $fiat,
            'local_amount' => $fiat,
            'btc_value' => round($fiat * $btcRate, 8),
            'tax_inclusive' => $this->faker->boolean(70),
            'fx_rate_snapshot' => $this->faker->randomFloat(6, 0.1, 1500),
            'btc_rate_snapshot' => $btcRate,
            'currency_id' => null,
            'country_id' => null,
            'raw_payload' => [
                'provider' => $this->faker->randomElement(['steam', 'amazon', 'bestbuy']),
            ],
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (RegionPrice $price): void {
                $this->hydrateFromSkuRegion($price);
            })
            ->afterCreating(function (RegionPrice $price): void {
                $this->hydrateFromSkuRegion($price);

                if ($price->isDirty()) {
                    $price->save();
                }
            });
    }

    protected function hydrateFromSkuRegion(RegionPrice $price): RegionPrice
    {
        $skuRegion = $price->skuRegion ?: SkuRegion::find($price->sku_region_id);

        if ($skuRegion) {
            $price->forceFill([
                'currency_id' => $price->currency_id ?? $skuRegion->currency_id,
                'country_id' => $price->country_id ?? $skuRegion->country_id,
            ]);
        }

        $price->forceFill([
            'local_amount' => $price->fiat_amount,
        ]);

        return $price;
    }
}
