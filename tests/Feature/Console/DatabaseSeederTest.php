<?php

namespace Tests\Feature\Console;

use App\Models\Currency;
use App\Models\DatasetSnapshot;
use App\Models\PriceSeriesAggregate;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_portfolio_dataset(): void
    {
        Queue::fake();

        $this->artisan('db:seed')->assertExitCode(0);

        Queue::assertNothingPushed();

        $this->assertGreaterThanOrEqual(7, Currency::count());
        $this->assertDatabaseHas('currencies', ['code' => 'USD']);
        $this->assertDatabaseHas('currencies', ['code' => 'BTC', 'is_crypto' => true]);

        $this->assertDatabaseHas('tax_profiles', ['region_code' => 'US']);
        $this->assertDatabaseHas('tax_profiles', ['region_code' => 'GB']);

        $this->assertGreaterThan(0, Product::count());
        $this->assertDatabaseHas('products', ['slug' => 'the-legend-of-zelda-tears-of-the-kingdom']);

        $this->assertGreaterThan(0, SkuRegion::count());
        $this->assertGreaterThan(0, RegionPrice::count());

        $aggregate = PriceSeriesAggregate::first();
        $this->assertNotNull($aggregate);
        $this->assertTrue($aggregate->avg_btc > 0);

        $snapshot = DatasetSnapshot::query()->where('kind', 'price_ingest')->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('succeeded', $snapshot->status);
        $this->assertGreaterThan(0, $snapshot->row_count);
    }
}
