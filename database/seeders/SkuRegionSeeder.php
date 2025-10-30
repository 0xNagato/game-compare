<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SkuRegion;
use Illuminate\Database\Seeder;

class SkuRegionSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'the-legend-of-zelda-tears-of-the-kingdom' => [
                ['country' => 'US', 'retailer' => 'Nintendo eShop', 'currency' => 'USD', 'sku' => 'HAC-A-JEALA', 'url' => 'https://www.nintendo.com/us/store/products/the-legend-of-zelda-tears-of-the-kingdom-switch/', 'verified_at' => now()],
                ['country' => 'GB', 'retailer' => 'Nintendo Store UK', 'currency' => 'GBP', 'sku' => 'NINTENDO-UK-ZELDA-TOTK', 'url' => 'https://store.nintendo.co.uk/en/zelda-totk', 'verified_at' => now()],
                ['country' => 'JP', 'retailer' => 'Nintendo Store JP', 'currency' => 'JPY', 'sku' => 'HAC-A-JEXCZ', 'url' => 'https://store-jp.nintendo.com/list/software/70010000063782.html'],
            ],
            'marvels-spider-man-2' => [
                ['country' => 'US', 'retailer' => 'PlayStation Store', 'currency' => 'USD', 'sku' => 'UP9000-PPSA03083', 'url' => 'https://store.playstation.com/en-us/product/UP9000-PPSA03083_00-MARVELSSPIDER2', 'verified_at' => now()],
                ['country' => 'GB', 'retailer' => 'PlayStation Store UK', 'currency' => 'GBP', 'sku' => 'EP9000-PPSA03084', 'url' => 'https://store.playstation.com/en-gb/product/EP9000-PPSA03084_00-MARVELSSPIDER2'],
            ],
            'starfield' => [
                ['country' => 'US', 'retailer' => 'Xbox Store', 'currency' => 'USD', 'sku' => 'CNV-00001', 'url' => 'https://www.xbox.com/en-US/games/store/starfield', 'verified_at' => now()],
                ['country' => 'EU', 'retailer' => 'Xbox Store EU', 'currency' => 'EUR', 'sku' => 'CNV-EU-00001', 'url' => 'https://www.xbox.com/en-GB/games/store/starfield'],
            ],
        ];

        foreach ($map as $slug => $regions) {
            $product = Product::query()->where('slug', $slug)->first();

            if (! $product) {
                continue;
            }

            foreach ($regions as $entry) {
                $currency = Currency::query()->where('code', $entry['currency'])->first();
                $country = Country::query()->where('code', $entry['country'])->first();

                if (! $currency || ! $country) {
                    continue;
                }

                SkuRegion::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'region_code' => $country->code,
                        'retailer' => $entry['retailer'],
                    ],
                    SkuRegion::factory()->state([
                        'product_id' => $product->id,
                        'region_code' => $country->code,
                        'retailer' => $entry['retailer'],
                        'currency' => $currency->code,
                        'currency_id' => $currency->id,
                        'country_id' => $country->id,
                        'sku' => $entry['sku'],
                        'metadata' => array_filter([
                            'store_id' => strtolower(str_replace(' ', '_', $entry['retailer'])),
                            'url' => $entry['url'] ?? null,
                            'verified_at' => isset($entry['verified_at']) ? $entry['verified_at']->toIso8601String() : null,
                        ]),
                    ])->make()->toArray()
                );
            }
        }
    }
}