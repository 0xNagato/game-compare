<?php

namespace Tests\Feature\Api;

use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\SkuRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('cache.default', 'array');
    }

    public function test_products_endpoint_returns_cursor_paginated_results(): void
    {
        $product = Product::factory()->create([
            'name' => 'Super Console',
            'slug' => 'super-console',
            'platform' => 'Console',
        ]);

        SkuRegion::factory()->for($product)->create(['region_code' => 'US']);
        SkuRegion::factory()->for($product)->create(['region_code' => 'CA']);

        $response = $this->getJson('/api/products?per_page=10');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Super Console');
        $response->assertJsonPath('data.0.region_codes', ['CA', 'US']);
        $response->assertHeader('Cache-Control', 'max-age=900, public');
    }

    public function test_compare_endpoint_serves_cached_series_payload(): void
    {
        $product = Product::factory()->create();

        $windowStart1 = Carbon::parse('2025-10-08')->startOfDay();
        $windowStart2 = Carbon::parse('2025-10-09')->startOfDay();

        PriceSeriesAggregate::factory()->forProduct($product)->create([
            'region_code' => 'US',
            'tax_inclusive' => true,
            'window_start' => $windowStart1,
            'window_end' => (clone $windowStart1)->addDay(),
            'avg_btc' => 0.00075,
            'avg_fiat' => 47.50,
            'sample_count' => 2,
            'metadata' => ['retailer_count' => 1],
        ]);

        PriceSeriesAggregate::factory()->forProduct($product)->create([
            'region_code' => 'US',
            'tax_inclusive' => false,
            'window_start' => $windowStart2,
            'window_end' => (clone $windowStart2)->addDay(),
            'avg_btc' => 0.00065,
            'avg_fiat' => 40,
            'sample_count' => 1,
            'metadata' => ['retailer_count' => 1],
        ]);

        PriceSeriesAggregate::factory()->forProduct($product)->create([
            'region_code' => 'CA',
            'tax_inclusive' => false,
            'window_start' => $windowStart1,
            'window_end' => (clone $windowStart1)->addDay(),
            'avg_btc' => 0.0012,
            'avg_fiat' => 70,
            'sample_count' => 2,
            'metadata' => ['retailer_count' => 1],
        ]);

        $response = $this->getJson(sprintf(
            '/api/compare?product_id=%d&regions=US,CA&from=2025-10-08&to=2025-10-09&include_tax=false',
            $product->id
        ));

        $response->assertOk();
        $response->assertJsonPath('product_id', $product->id);
        $response->assertJsonCount(2, 'series');
        $response->assertJsonStructure([
            'series' => [
                [
                    'region',
                    'points',
                    'fiat_points',
                    'trend' => [
                        'points',
                        'slope_per_day',
                        'absolute_change',
                        'percent_change',
                        'direction',
                        'count',
                        'r_squared',
                    ],
                    'fiat_trend' => [
                        'points',
                        'slope_per_day',
                        'absolute_change',
                        'percent_change',
                        'direction',
                        'count',
                        'r_squared',
                    ],
                    'sample_count',
                ],
            ],
        ]);
        $response->assertHeader('Cache-Control', 'max-age=900, public');
    }

    public function test_map_endpoint_returns_choropleth_payload(): void
    {
        $product = Product::factory()->create();
        $windowStart = Carbon::parse('2025-10-08')->startOfDay();

        PriceSeriesAggregate::factory()->forProduct($product)->create([
            'region_code' => 'US',
            'tax_inclusive' => false,
            'window_start' => $windowStart,
            'window_end' => (clone $windowStart)->addDay(),
            'avg_btc' => 0.0008,
            'avg_fiat' => 55,
            'sample_count' => 3,
            'metadata' => ['retailer_count' => 1],
        ]);

        $response = $this->getJson(sprintf(
            '/api/map/choropleth?product_id=%d&window=2d&include_tax=false&from=2025-10-08&to=2025-10-09',
            $product->id
        ));

        $response->assertOk();
        $response->assertJsonPath('stat', 'mean_btc');
        $response->assertJsonCount(1, 'regions');
        $response->assertHeader('Cache-Control', 'max-age=1800, public');
    }

    public function test_regions_endpoint_is_cached(): void
    {
        $product = Product::factory()->create();
        SkuRegion::factory()->for($product)->create(['region_code' => 'US']);

        $response = $this->getJson('/api/regions');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertHeader('Cache-Control', 'max-age=21600, public');
    }

    public function test_geo_endpoint_serves_geojson_file(): void
    {
        $geoDirectory = storage_path('app/geo');
        File::ensureDirectoryExists($geoDirectory);

        $payload = json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ]);

        File::put($geoDirectory.'/countries.json', $payload);

        $response = $this->get('/api/geo/countries');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Cache-Control', 'max-age=86400, public');

        File::delete($geoDirectory.'/countries.json');
    }
}
