<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => Str::headline($name),
            'platform' => $this->faker->randomElement(['PlayStation', 'Xbox', 'Nintendo', 'PC']),
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numerify('###')),
            'category' => $this->faker->randomElement(['Game', 'Console', 'Accessory']),
            'release_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'metadata' => [
                'publisher' => $this->faker->company(),
            ],
        ];
    }
}
