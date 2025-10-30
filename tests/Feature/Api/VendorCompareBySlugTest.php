<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VendorCompareBySlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('cache.default', 'array');
    }

    public function test_vendor_compare_by_slug_returns_vendor_payload(): void
    {
        $product = Product::factory()->create(['slug' => 'sluggy-product']);

        $srUsA = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'Retail A',
            'currency' => 'USD',
        ]);
        $srUsB = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'Retail B',
            'currency' => 'USD',
        ]);

        RegionPrice::factory()->for($srUsA, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 10:00:00'),
            'fiat_amount' => 49.00,
            'btc_value' => 0.00067, // best
            'tax_inclusive' => true,
        ]);
        RegionPrice::factory()->for($srUsB, 'skuRegion')->create([
            'recorded_at' => Carbon::parse('2025-10-26 10:05:00'),
            'fiat_amount' => 52.00,
            'btc_value' => 0.00070,
            'tax_inclusive' => true,
        ]);

        $response = $this->getJson(sprintf('/api/games/%s/vendors?include_tax=true', $product->slug));

        $response->assertOk();
        $response->assertJsonPath('product_slug', 'sluggy-product');
        $response->assertJsonCount(1, 'regions');
        $json = $response->json();
        $us = collect($json['regions'])->firstWhere('region', 'US');
        $this->assertSame(2, $us['summary']['retailer_count']);
        $this->assertSame('Retail A', $us['summary']['best_retailer']);
    }
}
