<?php

namespace Database\Factories;

use App\Models\DatasetSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetSnapshot>
 */
class DatasetSnapshotFactory extends Factory
{
    protected $model = DatasetSnapshot::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'running', 'succeeded', 'failed']);
        $start = $this->faker->dateTimeBetween('-1 day', '-1 hour');
        $finish = in_array($status, ['succeeded', 'failed'], true)
            ? $this->faker->dateTimeBetween($start, 'now')
            : null;

        return [
            'kind' => $this->faker->randomElement(['price_ingest', 'fx_refresh', 'aggregate_build']),
            'provider' => $this->faker->randomElement(['steam', 'coingecko', 'demo']),
            'status' => $status,
            'started_at' => $start,
            'finished_at' => $finish,
            'row_count' => $this->faker->numberBetween(0, 10000),
            'context' => [
                'notes' => $this->faker->sentence(),
            ],
            'error_details' => $status === 'failed' ? $this->faker->paragraph() : null,
        ];
    }
}
