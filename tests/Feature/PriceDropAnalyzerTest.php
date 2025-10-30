<?php

use App\Jobs\SendAlertJob;
use App\Models\Alert;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Models\User;
use App\Services\Alerts\PriceDropAnalyzer;
use Illuminate\Support\Facades\Bus;

it('dispatches alert job when drop threshold exceeded', function () {
    Bus::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $skuRegion = SkuRegion::factory()
        ->for($product)
        ->create([
            'region_code' => 'US',
        ]);

    Alert::factory()
        ->for($user)
        ->for($product)
        ->create([
            'region_code' => 'US',
            'channel' => 'email',
        ]);

    RegionPrice::factory()->for($skuRegion)->create([
        'recorded_at' => now()->subMinutes(90),
        'fiat_amount' => 500,
        'btc_value' => 0.01500000,
    ]);

    RegionPrice::factory()->for($skuRegion)->create([
        'recorded_at' => now(),
        'fiat_amount' => 400,
        'btc_value' => 0.00700000,
    ]);

    $analyzer = app(PriceDropAnalyzer::class);
    $analyzer->analyze([
        'window_minutes' => 120,
        'drop_percentage' => 10,
    ]);

    Bus::assertDispatched(SendAlertJob::class, function (SendAlertJob $job) {
        expect($job->context)->toHaveKeys([
            'change_percentage',
            'earliest_price_id',
            'previous_btc',
            'previous_fiat',
            'previous_recorded_at',
            'latest_fiat',
        ]);

        expect($job->context['change_percentage'])->toBeLessThan(0);

        return true;
    });
});
