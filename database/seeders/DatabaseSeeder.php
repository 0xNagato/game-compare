<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            TaxProfileSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            DemoTrailerSeeder::class,
            TgdbMirrorProductSeeder::class,
            SkuRegionSeeder::class,
            ExchangeRateSeeder::class,
            RegionPriceSeeder::class,
            PriceSeriesAggregateSeeder::class,
            DatasetSnapshotSeeder::class,
        ]);
    }
}