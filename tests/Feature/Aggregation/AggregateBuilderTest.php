<?php

namespace Tests\Feature\Aggregation;

use App\Models\DatasetSnapshot;
use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Services\Aggregation\AggregateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AggregateBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_daily_rollups_and_primes_cache(): void
    {
        config()->set('cache.default', 'array');
        Carbon::setTestNow('2025-10-10 12:00:00');

        $product = Product::factory()->create([
            'slug' => 'test-product',
        ]);

        $skuUs = SkuRegion::factory()->for($product)->create([
            'region_code' => 'US',
            'retailer' => 'Steam',
            'currency' => 'USD',
        ]);

        $skuCa = SkuRegion::factory()->for($product)->create([
            'region_code' => 'CA',
            'retailer' => 'GameBillet',
            'currency' => 'CAD',
        ]);

        RegionPrice::factory()->for($skuUs)->create([
            'recorded_at' => Carbon::parse('2025-10-08 05:00:00'),
            'fiat_amount' => 50,
            'btc_value' => 0.0008,
            'tax_inclusive' => true,
            'fx_rate_snapshot' => 1.0,
            'btc_rate_snapshot' => 0.000016,
        ]);

        RegionPrice::factory()->for($skuUs)->create([
            'recorded_at' => Carbon::parse('2025-10-08 09:15:00'),
            'fiat_amount' => 45,
            'btc_value' => 0.0007,
            'tax_inclusive' => true,
            'fx_rate_snapshot' => 1.0,
            'btc_rate_snapshot' => 0.000014,
        ]);

        RegionPrice::factory()->for($skuUs)->create([
            'recorded_at' => Carbon::parse('2025-10-09 13:00:00'),
            'fiat_amount' => 40,
            'btc_value' => 0.00065,
            'tax_inclusive' => false,
            'fx_rate_snapshot' => 1.0,
            'btc_rate_snapshot' => 0.000013,
        ]);

        RegionPrice::factory()->for($skuCa)->create([
            'recorded_at' => Carbon::parse('2025-10-08 11:25:00'),
            'fiat_amount' => 70,
            'btc_value' => 0.0012,
            'tax_inclusive' => false,
            'fx_rate_snapshot' => 1.25,
            'btc_rate_snapshot' => 0.000017,
        ]);

        RegionPrice::factory()->for($skuCa)->create([
            'recorded_at' => Carbon::parse('2025-10-08 23:10:00'),
            'fiat_amount' => 72,
            'btc_value' => 0.00125,
            'tax_inclusive' => false,
            'fx_rate_snapshot' => 1.25,
            'btc_rate_snapshot' => 0.000017,
        ]);

        $builder = app(AggregateBuilder::class);
        $builder->build([
            'product_id' => $product->id,
            'bucket' => 'day',
            'from' => '2025-10-08',
            'to' => '2025-10-09',
        ]);

        $this->assertDatabaseCount('price_series_aggregates', 3);

        $usDay = PriceSeriesAggregate::where('region_code', 'US')
            ->where('tax_inclusive', true)
            ->whereDate('window_start', '2025-10-08')
            ->firstOrFail();

        $this->assertEqualsWithDelta(0.00070, (float) $usDay->min_btc, 0.00000001);
        $this->assertEqualsWithDelta(0.00080, (float) $usDay->max_btc, 0.00000001);
        $this->assertEqualsWithDelta(0.00075, (float) $usDay->avg_btc, 0.00000001);
        $this->assertSame('45.00', $usDay->min_fiat);
        $this->assertSame('50.00', $usDay->max_fiat);
        $this->assertSame('47.50', $usDay->avg_fiat);
        $this->assertSame(2, $usDay->sample_count);
        $this->assertSame(['retailer_count' => 1], $usDay->metadata);

        $snapshot = DatasetSnapshot::where('kind', 'aggregate_build')->firstOrFail();
        $this->assertSame('succeeded', $snapshot->status);
        $this->assertSame(3, $snapshot->row_count);

        $regionsHashTax = sha1('US');
        $seriesKeyTax = sprintf(
            'series:%s:%s:%s:%s:%s:%s',
            $product->id,
            'day',
            $regionsHashTax,
            '2025-10-08',
            '2025-10-09',
            'tax'
        );

        $cachedTaxSeries = Cache::get($seriesKeyTax);
        $this->assertNotNull($cachedTaxSeries);
        $this->assertSame($product->id, $cachedTaxSeries['product_id']);
        $this->assertSame('day', $cachedTaxSeries['bucket']);
        $this->assertTrue($cachedTaxSeries['include_tax']);
        $this->assertCount(1, $cachedTaxSeries['series']);

        $regionsHashNoTax = sha1('CA,US');
        $seriesKeyNoTax = sprintf(
            'series:%s:%s:%s:%s:%s:%s',
            $product->id,
            'day',
            $regionsHashNoTax,
            '2025-10-08',
            '2025-10-09',
            'notax'
        );

        $cachedNoTaxSeries = Cache::get($seriesKeyNoTax);
        $this->assertNotNull($cachedNoTaxSeries);
        $this->assertFalse($cachedNoTaxSeries['include_tax']);
        $this->assertCount(2, $cachedNoTaxSeries['series']);

        $mapKey = sprintf('map:%s:mean_btc:%s:%s', $product->id, '2d', 'notax');
        $cachedMap = Cache::get($mapKey);
        $this->assertNotNull($cachedMap);
        $this->assertSame('mean_btc', $cachedMap['stat']);
        $this->assertSame('2d', $cachedMap['window']);
        $this->assertFalse($cachedMap['include_tax']);
        $this->assertCount(2, $cachedMap['regions']);

        Carbon::setTestNow();
    }
}
