<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VendorCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('cache.default', 'array');
    }

    public function test_vendor_compare_returns_latest_per_retailer_grouped_by_region(): void
    {
        $product = Product::factory()->create();

        // US region with three retailers
        $srSteam = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'Steam',
            'currency' => 'USD',
        ]);
        $srGog = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'GOG',
            'currency' => 'USD',
        ]);
        $srGmg = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'Green Man Gaming',
            'currency' => 'USD',
        ]);

        // Latest snapshots (tax inclusive)
        RegionPrice::factory()->for($srSteam, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 10:00:00'),
            'fiat_amount' => 50.00,
            'btc_value' => 0.00070,
            'tax_inclusive' => true,
        ]);
        RegionPrice::factory()->for($srGog, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 10:05:00'),
            'fiat_amount' => 48.00,
            'btc_value' => 0.00068, // best
            'tax_inclusive' => true,
        ]);
        RegionPrice::factory()->for($srGmg, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 10:10:00'),
            'fiat_amount' => 54.00,
            'btc_value' => 0.00074,
            'tax_inclusive' => true,
        ]);

        // GB region with two retailers
        $srGbSteam = SkuRegion::factory()->for($product)->create([
            'region_code' => 'GB',
            'retailer' => 'Steam',
            'currency' => 'GBP',
        ]);
        $srGbGog = SkuRegion::factory()->for($product)->create([
            'region_code' => 'GB',
            'retailer' => 'GOG',
            'currency' => 'GBP',
        ]);

        RegionPrice::factory()->for($srGbSteam, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 11:00:00'),
            'fiat_amount' => 39.99,
            'btc_value' => 0.00060, // best
            'tax_inclusive' => true,
        ]);
        RegionPrice::factory()->for($srGbGog, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 11:01:00'),
            'fiat_amount' => 41.99,
            'btc_value' => 0.00062,
            'tax_inclusive' => true,
        ]);

        $response = $this->getJson(sprintf(
            '/api/compare/vendors?product_id=%d&regions=US,GB&include_tax=true',
            $product->id
        ));

        $response->assertOk();

        $response->assertJsonStructure([
            'product_id',
            'include_tax',
            'meta' => ['unit', 'regions', 'updated_at', 'cache_ttl'],
            'regions' => [
                [
                    'region',
                    'retailers' => [
                        ['retailer', 'currency', 'fiat', 'btc', 'recorded_at', 'delta_btc', 'delta_pct', 'is_best'],
                    ],
                    'summary' => ['min_btc', 'max_btc', 'spread_btc', 'spread_pct', 'best_retailer', 'retailer_count', 'sample_count'],
                ],
            ],
        ]);

        $json = $response->json();

        // Assert US grouping
        $us = collect($json['regions'])->firstWhere('region', 'US');
        $this->assertNotNull($us);
        $this->assertSame('GOG', $us['summary']['best_retailer']);
        $this->assertSame(0.00068, (float) $us['summary']['min_btc']);

        $steamEntry = collect($us['retailers'])->firstWhere('retailer', 'Steam');
        $this->assertSame(0.00002, round((float) $steamEntry['delta_btc'], 5));
        $this->assertTrue(collect($us['retailers'])->firstWhere('retailer', 'GOG')['is_best']);

        // Assert GB grouping
        $gb = collect($json['regions'])->firstWhere('region', 'GB');
        $this->assertSame('Steam', $gb['summary']['best_retailer']);
        $this->assertSame(2, $gb['summary']['retailer_count']);
    }
}
