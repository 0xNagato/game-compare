<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        $base = $this->faker->randomElement(['USD', 'EUR', 'GBP', 'NGN', 'JPY']);
        $quote = $this->faker->randomElement(['BTC', 'USD', 'EUR']);

        return [
            'base_currency' => $base,
            'quote_currency' => $quote,
            'rate' => $this->faker->randomFloat(8, 0.0001, 2000),
            'fetched_at' => $this->faker->dateTimeBetween('-12 hours', 'now'),
            'provider' => $this->faker->randomElement(['coingecko', 'exchangerateapi']),
            'metadata' => [
                'source' => 'test',
            ],
        ];
    }
}
