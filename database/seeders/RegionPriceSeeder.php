<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class RegionPriceSeeder extends Seeder
{
    public function run(): void
    {
        $priceMap = [
            [
                'product' => 'the-legend-of-zelda-tears-of-the-kingdom',
                'region' => 'US',
                'retailer' => 'Nintendo eShop',
                'prices' => [
                    ['fiat' => 69.99, 'days_ago' => 5],
                    ['fiat' => 59.99, 'days_ago' => 2],
                    ['fiat' => 59.99, 'days_ago' => 0],
                ],
            ],
            [
                'product' => 'the-legend-of-zelda-tears-of-the-kingdom',
                'region' => 'GB',
                'retailer' => 'Nintendo Store UK',
                'prices' => [
                    ['fiat' => 49.99, 'days_ago' => 5],
                    ['fiat' => 44.99, 'days_ago' => 1],
                ],
            ],
            [
                'product' => 'marvels-spider-man-2',
                'region' => 'US',
                'retailer' => 'PlayStation Store',
                'prices' => [
                    ['fiat' => 69.99, 'days_ago' => 3],
                    ['fiat' => 59.99, 'days_ago' => 0],
                ],
            ],
            [
                'product' => 'starfield',
                'region' => 'US',
                'retailer' => 'Xbox Store',
                'prices' => [
                    ['fiat' => 69.99, 'days_ago' => 4],
                    ['fiat' => 54.99, 'days_ago' => 0],
                ],
            ],
        ];

        foreach ($priceMap as $entry) {
            $product = Product::query()->where('slug', $entry['product'])->first();

            if (! $product) {
                continue;
            }

            $skuRegion = SkuRegion::query()
                ->where('product_id', $product->id)
                ->where('region_code', $entry['region'])
                ->where('retailer', $entry['retailer'])
                ->first();

            if (! $skuRegion) {
                continue;
            }

            $btcRate = $this->findRate($skuRegion->currency, 'BTC') ?? 0.00001;
            $fxRate = $this->resolveUsdRate($skuRegion->currency) ?? 1.0;

            foreach ($entry['prices'] as $price) {
                $recordedAt = Carbon::now()->subDays($price['days_ago'])->setHour(9);

                RegionPrice::query()->updateOrCreate(
                    [
                        'sku_region_id' => $skuRegion->id,
                        'recorded_at' => $recordedAt,
                    ],
                    [
                        'fiat_amount' => $price['fiat'],
                        'local_amount' => $price['fiat'],
                        'btc_value' => round($price['fiat'] * $btcRate, 8),
                        'tax_inclusive' => true,
                        'fx_rate_snapshot' => $fxRate,
                        'btc_rate_snapshot' => $btcRate,
                        'currency_id' => $skuRegion->currency_id,
                        'country_id' => $skuRegion->country_id,
                        'raw_payload' => [
                            'provider' => 'seed_data',
                            'list_price' => $price['fiat'],
                        ],
                    ]
                );
            }
        }
    }

    protected function findRate(string $base, string $quote): ?float
    {
        return ExchangeRate::query()
            ->where('base_currency', strtoupper($base))
            ->where('quote_currency', strtoupper($quote))
            ->orderByDesc('fetched_at')
            ->value('rate');
    }

    protected function resolveUsdRate(string $currency): ?float
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return 1.0;
        }

        $direct = $this->findRate($currency, 'USD');

        if ($direct !== null) {
            return $direct;
        }

        $inverse = $this->findRate('USD', $currency);

        if ($inverse === null || $inverse == 0.0) {
            return null;
        }

        return round(1 / $inverse, 6);
    }
}