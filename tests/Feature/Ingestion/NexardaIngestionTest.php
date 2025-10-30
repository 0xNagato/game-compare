<?php

namespace Tests\Feature\Ingestion;

use App\Models\DatasetSnapshot;
use App\Models\ExchangeRate;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Services\PriceIngestion\PriceIngestionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

class NexardaIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingests_lowest_prices_from_nexarda_in_multiple_currencies(): void
    {
        config()->set('pricing.providers.nexarda.options.products', [
            [
                'id' => 2781,
                'title' => 'Six Days in Fallujah',
                'slug' => 'six-days-in-fallujah',
                'platform' => 'PC',
                'category' => 'Game',
                'regions' => [
                    ['currency' => 'GBP', 'region_code' => 'GB'],
                    ['currency' => 'USD', 'region_code' => 'US'],
                ],
            ],
        ]);

        config()->set('pricing.providers.nexarda.options.default_regions', [
            ['currency' => 'GBP', 'region_code' => 'GB'],
            ['currency' => 'USD', 'region_code' => 'US'],
        ]);
        config()->set('pricing.providers.nexarda.options.api_key', 'test-nexarda-key');

        Http::fake([
            'https://www.nexarda.com/api/v3/prices*' => function ($request) {
                Assert::assertSame('test-nexarda-key', $request->data()['key'] ?? null);
                Assert::assertSame('test-nexarda-key', $request->header('X-Api-Key')[0] ?? null);

                $currency = strtoupper($request->data()['currency'] ?? 'USD');

                $price = $currency === 'GBP' ? 12.61 : 14.99;
                $highest = $currency === 'GBP' ? 32.99 : 39.99;

                return Http::response([
                    'success' => true,
                    'code' => 'game_prices_found',
                    'message' => 'Mocked prices',
                    'info' => [
                        'id' => 2781,
                        'name' => 'Six Days in Fallujah',
                        'slug' => '/games/six-days-in-fallujah-(2781)',
                        'cover' => 'https://cdn.example/cover.png',
                        'banner' => 'https://cdn.example/banner.png',
                        'release' => 1687392000,
                    ],
                    'prices' => [
                        'currency' => $currency,
                        'lowest' => $price,
                        'highest' => $highest,
                        'max_discount' => 35,
                        'list' => [
                            [
                                'url' => 'https://www.nexarda.com/redirect/mock',
                                'price' => $price,
                                'store' => [
                                    'name' => $currency === 'GBP' ? 'Eneba' : 'Amazon',
                                ],
                                'coupon' => [
                                    'price_without' => $highest,
                                ],
                            ],
                        ],
                    ],
                ]);
            },
        ]);

        ExchangeRate::factory()->create([
            'base_currency' => 'GBP',
            'quote_currency' => 'BTC',
            'rate' => 0.000016,
        ]);

        ExchangeRate::factory()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'BTC',
            'rate' => 0.000014,
        ]);

        ExchangeRate::factory()->create([
            'base_currency' => 'GBP',
            'quote_currency' => 'USD',
            'rate' => 1.27,
        ]);

        ExchangeRate::factory()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'GBP',
            'rate' => 0.78,
        ]);

        $manager = app(PriceIngestionManager::class);
        $manager->ingest('nexarda');

        Http::assertSentCount(2);

        $this->assertDatabaseCount('region_prices', 2);
        $this->assertDatabaseCount('sku_regions', 2);
        $this->assertDatabaseHas('dataset_snapshots', [
            'kind' => 'price_ingest',
            'provider' => 'nexarda',
            'status' => 'succeeded',
        ]);

        $gbpRegion = SkuRegion::where('currency', 'GBP')->firstOrFail();
        $gbpPrice = RegionPrice::where('sku_region_id', $gbpRegion->id)->firstOrFail();

        $this->assertSame('Eneba UK (Via NEXARDA)', $gbpRegion->retailer);
        $this->assertSame('GBP', $gbpRegion->currency);
        $this->assertSame('GB', $gbpRegion->region_code);
        $this->assertNotNull($gbpRegion->currency_id);
        $this->assertNotNull($gbpRegion->country_id);
        $this->assertSame('12.61', (string) $gbpPrice->fiat_amount);
        $this->assertSame('12.61', (string) $gbpPrice->local_amount);
        $this->assertNotNull($gbpPrice->currency_id);
        $this->assertSame($gbpRegion->currency_id, $gbpPrice->currency_id);
        $this->assertSame($gbpRegion->country_id, $gbpPrice->country_id);
        $this->assertEqualsWithDelta(0.00020176, (float) $gbpPrice->btc_value, 0.00000001);
        $this->assertEquals('1.270000', (string) $gbpPrice->fx_rate_snapshot);

        $usdRegion = SkuRegion::where('currency', 'USD')->firstOrFail();
        $usdPrice = RegionPrice::where('sku_region_id', $usdRegion->id)->firstOrFail();

        $this->assertSame('Amazon (Via NEXARDA)', $usdRegion->retailer);
        $this->assertSame('USD', $usdRegion->currency);
        $this->assertSame('US', $usdRegion->region_code);
        $this->assertNotNull($usdRegion->currency_id);
        $this->assertNotNull($usdRegion->country_id);
        $this->assertSame('14.99', (string) $usdPrice->fiat_amount);
        $this->assertSame('14.99', (string) $usdPrice->local_amount);
        $this->assertSame($usdRegion->currency_id, $usdPrice->currency_id);
        $this->assertSame($usdRegion->country_id, $usdPrice->country_id);
        $this->assertEqualsWithDelta(0.00020986, (float) $usdPrice->btc_value, 0.00000001);

        $snapshot = DatasetSnapshot::where('provider', 'nexarda')->firstOrFail();
        $this->assertGreaterThan(0, $snapshot->row_count);
    }
}
