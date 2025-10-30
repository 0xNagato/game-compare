<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\LocalCurrency;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'US', 'name' => 'United States', 'currency' => 'USD', 'region' => 'North America'],
            ['code' => 'CA', 'name' => 'Canada', 'currency' => 'CAD', 'region' => 'North America'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP', 'region' => 'Europe'],
            ['code' => 'EU', 'name' => 'Eurozone', 'currency' => 'EUR', 'region' => 'Europe'],
            ['code' => 'JP', 'name' => 'Japan', 'currency' => 'JPY', 'region' => 'Asia Pacific'],
            ['code' => 'AU', 'name' => 'Australia', 'currency' => 'AUD', 'region' => 'Oceania'],
        ];

        foreach ($countries as $entry) {
            $currency = Currency::query()->where('code', $entry['currency'])->first();

            if (! $currency) {
                continue;
            }

            $country = Country::query()->firstOrCreate([
                'code' => $entry['code'],
            ], [
                'name' => $entry['name'],
                'currency_id' => $currency->id,
                'region' => $entry['region'],
            ]);

            LocalCurrency::firstOrCreate(
                ['currency_id' => $currency->id, 'code' => $country->code.'_'.$currency->code],
                ['name' => $country->code.' '.$currency->code]
            );
        }
    }
}
