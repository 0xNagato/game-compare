<?php

namespace Tests\Feature\Ingestion;

use App\Models\DatasetSnapshot;
use App\Models\ProviderUsage;
use App\Services\PriceIngestion\PriceIngestionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\FakePriceProvider;
use Tests\TestCase;

class ProviderRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotates_enabled_providers_and_tracks_usage(): void
    {
        config()->set('pricing.providers.fake_a', [
            'label' => 'Fake Provider A',
            'class' => FakePriceProvider::class,
            'queue' => 'fetch',
            'regions' => ['US'],
            'enabled' => true,
            'options' => [],
        ]);

        config()->set('pricing.providers.fake_b', [
            'label' => 'Fake Provider B',
            'class' => FakePriceProvider::class,
            'queue' => 'fetch',
            'regions' => ['US'],
            'enabled' => true,
            'options' => [],
        ]);

        $manager = app(PriceIngestionManager::class);

        $manager->ingestWithRotation(['fake_a', 'fake_b']);
        $manager->ingestWithRotation(['fake_a', 'fake_b']);
        $manager->ingestWithRotation(['fake_a', 'fake_b']);

        $usageA = ProviderUsage::where('provider', 'fake_a')->firstOrFail();
        $usageB = ProviderUsage::where('provider', 'fake_b')->firstOrFail();

        $this->assertSame(2, $usageA->total_calls);
        $this->assertSame(1, $usageB->total_calls);
        $this->assertSame($usageA->daily_calls, $usageA->total_calls);
        $this->assertSame($usageB->daily_calls, $usageB->total_calls);
        $this->assertNotNull($usageA->daily_window);

        $snapshots = DatasetSnapshot::where('provider', 'fake_a')->orWhere('provider', 'fake_b')->get();
        $this->assertCount(3, $snapshots);
        $this->assertSame('fake_a', $snapshots->first()->provider);
    }
}
