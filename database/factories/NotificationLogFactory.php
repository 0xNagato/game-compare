<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(['email', 'discord']);

        return [
            'alert_id' => Alert::factory(),
            'channel' => $channel,
            'recipient' => $channel === 'email'
                ? $this->faker->safeEmail()
                : $this->faker->regexify('discord_user_\d{4}'),
            'payload_hash' => Str::random(64),
            'status' => $this->faker->randomElement(['pending', 'sent', 'failed', 'skipped']),
            'response_code' => $this->faker->numberBetween(200, 500),
            'response_payload' => [
                'trace_id' => Str::uuid()->toString(),
            ],
            'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ];
    }
}
