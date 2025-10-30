<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now()->subMinutes(5);

        $pairs = [
            ['base' => 'USD', 'quote' => 'BTC', 'rate' => 0.000016],
            ['base' => 'EUR', 'quote' => 'BTC', 'rate' => 0.000015],
            ['base' => 'GBP', 'quote' => 'BTC', 'rate' => 0.000013],
            ['base' => 'JPY', 'quote' => 'BTC', 'rate' => 0.00000011],
            ['base' => 'AUD', 'quote' => 'BTC', 'rate' => 0.000010],
            ['base' => 'CAD', 'quote' => 'BTC', 'rate' => 0.000012],
            ['base' => 'USD', 'quote' => 'EUR', 'rate' => 0.92],
            ['base' => 'USD', 'quote' => 'GBP', 'rate' => 0.79],
            ['base' => 'USD', 'quote' => 'JPY', 'rate' => 149.50],
            ['base' => 'USD', 'quote' => 'AUD', 'rate' => 1.52],
            ['base' => 'USD', 'quote' => 'CAD', 'rate' => 1.36],
        ];

        foreach ($pairs as $pair) {
            ExchangeRate::query()->updateOrCreate(
                [
                    'base_currency' => $pair['base'],
                    'quote_currency' => $pair['quote'],
                ],
                ExchangeRate::factory()->state([
                    'base_currency' => $pair['base'],
                    'quote_currency' => $pair['quote'],
                    'rate' => $pair['rate'],
                    'fetched_at' => $now,
                    'provider' => 'coingecko',
                    'metadata' => ['seed' => true],
                ])->make()->toArray()
            );
        }
    }
}