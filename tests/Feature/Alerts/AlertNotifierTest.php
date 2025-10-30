<?php

use App\Mail\PriceAlertMail;
use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\RegionPrice;
use App\Models\SkuRegion;
use App\Models\User;
use App\Services\Alerts\AlertNotifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\assertDatabaseCount;

it('queues an email alert and records the delivery log', function () {
    Mail::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Test Game']);
    $skuRegion = SkuRegion::factory()->for($product)->create([
        'region_code' => 'US',
        'retailer' => 'Steam',
        'currency' => 'USD',
    ]);
    $price = RegionPrice::factory()->for($skuRegion)->create([
        'fiat_amount' => 19.99,
        'btc_value' => 0.00035,
        'recorded_at' => now(),
    ]);

    $alert = Alert::factory()
        ->for($user)
        ->for($product)
        ->state([
            'channel' => 'email',
            'region_code' => 'US',
            'threshold_btc' => 0.00040,
            'comparison_operator' => 'below',
        ])->create();

    $alertContext = [
        'change_percentage' => -52.5,
        'previous_btc' => 0.00050,
        'previous_fiat' => 29.99,
        'previous_recorded_at' => now()->subHour()->toIso8601String(),
        'earliest_price_id' => $price->id,
        'latest_fiat' => 19.99,
    ];

    $notifier = app(AlertNotifier::class);
    $notifier->notify($alert, $price->id, $alertContext);

    Mail::assertQueued(PriceAlertMail::class, function (PriceAlertMail $mail) use ($alert, $price) {
        return $mail->alert->is($alert) && $mail->price->is($price);
    });

    assertDatabaseCount('notification_logs', 1);

    $log = NotificationLog::firstOrFail();
    expect($log->status)->toBe('sent');
    expect($log->response_payload)->toMatchArray([
        'channel' => 'email',
        'queued' => true,
    ]);
    expect($log->response_code)->toBeNull();
});

it('posts to discord webhook and stores the response metadata', function () {
    Http::fake([
        'https://discord.test/*' => Http::response('', 204),
    ]);

    config()->set('services.discord.alert_webhook', 'https://discord.test/webhook');

    $user = User::factory()->create([
        'discord_id' => '1234567890',
    ]);
    $product = Product::factory()->create(['name' => 'Test Game']);
    $skuRegion = SkuRegion::factory()->for($product)->create([
        'region_code' => 'US',
        'retailer' => 'Steam',
        'currency' => 'USD',
    ]);
    $price = RegionPrice::factory()->for($skuRegion)->create([
        'fiat_amount' => 29.99,
        'btc_value' => 0.00075,
        'recorded_at' => now(),
    ]);

    $alert = Alert::factory()
        ->for($user)
        ->for($product)
        ->state([
            'channel' => 'discord',
            'region_code' => 'US',
            'threshold_btc' => 0.00080,
            'comparison_operator' => 'below',
        ])->create();

    $alertContext = [
        'change_percentage' => -25.0,
        'previous_btc' => 0.001,
        'previous_fiat' => 49.99,
        'previous_recorded_at' => now()->subHours(2)->toIso8601String(),
        'earliest_price_id' => $price->id,
    ];

    $notifier = app(AlertNotifier::class);
    $notifier->notify($alert, $price->id, $alertContext);

    $capturedPayload = null;

    Http::assertSent(function (Request $request) use (&$capturedPayload) {
        $capturedPayload = $request->data();

        return str_contains($request->url(), 'discord.test/webhook');
    });

    assertDatabaseCount('notification_logs', 1);
    $log = NotificationLog::firstOrFail();

    expect($log->status)->toBe('sent');
    expect($log->response_code)->toBe(204);
    expect($log->response_payload)->toBe([]);

    $fieldNames = collect($capturedPayload['embeds'][0]['fields'] ?? [])->pluck('name')->all();

    expect($fieldNames)->toContain('Change');
    expect($fieldNames)->toContain('Previous BTC');
    expect($fieldNames)->toContain('Previous Price');
});

it('records failures when discord webhook configuration is missing', function () {
    Http::fake();

    $user = User::factory()->create([
        'discord_id' => '24680',
    ]);
    $product = Product::factory()->create(['name' => 'Test Game']);
    $skuRegion = SkuRegion::factory()->for($product)->create([
        'region_code' => 'US',
        'retailer' => 'Steam',
        'currency' => 'USD',
    ]);
    $price = RegionPrice::factory()->for($skuRegion)->create([
        'fiat_amount' => 39.99,
        'btc_value' => 0.00095,
        'recorded_at' => now(),
    ]);

    $alert = Alert::factory()
        ->for($user)
        ->for($product)
        ->state([
            'channel' => 'discord',
            'region_code' => 'US',
            'threshold_btc' => 0.001,
            'comparison_operator' => 'below',
        ])->create();

    config()->set('services.discord.alert_webhook', null);

    $alertContext = [
        'change_percentage' => -12.5,
        'previous_btc' => 0.0012,
        'previous_fiat' => 59.99,
        'previous_recorded_at' => now()->subHours(3)->toIso8601String(),
        'earliest_price_id' => $price->id,
    ];

    $notifier = app(AlertNotifier::class);

    expect(fn () => $notifier->notify($alert, $price->id, $alertContext))->toThrow(RuntimeException::class);

    assertDatabaseCount('notification_logs', 1);

    $log = NotificationLog::firstOrFail();
    expect($log->status)->toBe('failed');
    expect($log->response_payload)->toMatchArray([
        'error' => 'Discord webhook URL is not configured.',
    ]);
});
