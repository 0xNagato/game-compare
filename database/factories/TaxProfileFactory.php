<?php

namespace Database\Factories;

use App\Models\TaxProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxProfile>
 */
class TaxProfileFactory extends Factory
{
    protected $model = TaxProfile::class;

    public function definition(): array
    {
        return [
            'region_code' => $this->faker->countryCode(),
            'vat_rate' => $this->faker->randomFloat(2, 0, 25),
            'effective_from' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'notes' => $this->faker->sentence(),
        ];
    }
}
