<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\LocalCurrency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            'USD' => ['name' => 'US Dollar', 'symbol' => 'USD', 'decimals' => 2, 'is_crypto' => false],
            'EUR' => ['name' => 'Euro', 'symbol' => 'EUR', 'decimals' => 2, 'is_crypto' => false],
            'GBP' => ['name' => 'British Pound', 'symbol' => 'GBP', 'decimals' => 2, 'is_crypto' => false],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => 'JPY', 'decimals' => 0, 'is_crypto' => false],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'AUD', 'decimals' => 2, 'is_crypto' => false],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CAD', 'decimals' => 2, 'is_crypto' => false],
            'BTC' => ['name' => 'Bitcoin', 'symbol' => 'BTC', 'decimals' => 8, 'is_crypto' => true],
        ];

        foreach ($currencies as $code => $attributes) {
            if (Currency::query()->where('code', $code)->exists()) {
                continue;
            }

            $currency = Currency::factory()->create(array_merge(
                ['code' => $code],
                $attributes
            ));

            LocalCurrency::firstOrCreate(
                ['currency_id' => $currency->id, 'code' => 'GLOBAL_'.$code],
                ['name' => 'Global '.$code]
            );
        }
    }
}
