<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SkuRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkuRegion>
 */
class SkuRegionFactory extends Factory
{
    protected $model = SkuRegion::class;

    public function definition(): array
    {
        $region = strtoupper($this->faker->countryCode());
        $currencyCode = $this->faker->randomElement(['USD', 'EUR', 'GBP', 'NGN', 'JPY', 'CAD', 'AUD']);

        $currency = Currency::query()->firstOrCreate(
            ['code' => $currencyCode],
            [
                'name' => $currencyCode,
                'decimals' => $currencyCode === 'JPY' ? 0 : 2,
                'is_crypto' => false,
            ]
        );

        $country = Country::query()->firstOrCreate(
            ['code' => $region],
            [
                'name' => $region,
                'currency_id' => $currency->id,
            ]
        );

        if ($country->currency_id !== $currency->id) {
            $country->currency()->associate($currency);
            $country->save();
        }

        return [
            'product_id' => Product::factory(),
            'region_code' => $region,
            'retailer' => $this->faker->randomElement(['Amazon', 'BestBuy', 'PlayStation Store', 'Steam']),
            'currency' => $currencyCode,
            'currency_id' => $currency->id,
            'country_id' => $country->id,
            'sku' => strtoupper($region).'-'.$this->faker->unique()->bothify('####'),
            'is_active' => true,
            'metadata' => [
                'locale' => $this->faker->locale(),
            ],
        ];
    }
}
