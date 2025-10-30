<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->countryCode());

        return [
            'code' => $code,
            'name' => $this->faker->country(),
            'currency_id' => Currency::factory(),
            'region' => $this->faker->randomElement([
                'North America',
                'Europe',
                'Asia',
                'South America',
                'Africa',
                'Oceania',
                null,
            ]),
        ];
    }

    public function withCode(string $code): self
    {
        $upper = strtoupper($code);

        return $this->state(fn () => [
            'code' => $upper,
            'name' => $upper,
        ]);
    }

    public function forCurrency(Currency|Factory $currency): self
    {
        return $this->for($currency, 'currency');
    }
}
