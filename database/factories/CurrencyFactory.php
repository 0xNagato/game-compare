<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->currencyCode());

        return [
            'code' => $code,
            'name' => $code,
            'symbol' => $this->faker->randomElement(['$', '€', '£', '¥', '₩', '₽', 'A$', 'R$', null]),
            'decimals' => $code === 'JPY' ? 0 : 2,
            'is_crypto' => false,
        ];
    }

    public function withCode(string $code): self
    {
        $upper = strtoupper($code);

        return $this->state(fn () => [
            'code' => $upper,
            'name' => $upper,
            'decimals' => $upper === 'JPY' ? 0 : 2,
        ]);
    }
}
