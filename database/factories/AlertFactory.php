<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'region_code' => $this->faker->countryCode(),
            'threshold_btc' => $this->faker->randomFloat(8, 0.0001, 0.1),
            'comparison_operator' => $this->faker->randomElement(['below', 'above']),
            'channel' => $this->faker->randomElement(['email', 'discord']),
            'is_active' => true,
            'settings' => [
                'frequency' => 'immediate',
            ],
        ];
    }
}
