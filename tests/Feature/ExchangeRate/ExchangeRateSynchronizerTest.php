<?php

namespace Tests\Feature\ExchangeRate;

use App\Models\DatasetSnapshot;
use App\Models\ExchangeRate;
use App\Services\ExchangeRate\ExchangeRateSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronizes_rates_from_coingecko(): void
    {
        Http::fake([
            'https://api.coingecko.com/api/v3/exchange_rates' => Http::response([
                'rates' => [
                    'btc' => [
                        'name' => 'Bitcoin',
                        'unit' => 'BTC',
                        'value' => 1,
                        'type' => 'crypto',
                    ],
                    'usd' => [
                        'name' => 'US Dollar',
                        'unit' => '$',
                        'value' => 60000,
                        'type' => 'fiat',
                    ],
                    'gbp' => [
                        'name' => 'Pound Sterling',
                        'unit' => '£',
                        'value' => 48000,
                        'type' => 'fiat',
                    ],
                    'cad' => [
                        'name' => 'Canadian Dollar',
                        'unit' => '$',
                        'value' => 81000,
                        'type' => 'fiat',
                    ],
                ],
            ], 200),
        ]);

        $pairs = [
            ['base' => 'USD', 'quote' => 'BTC'],
            ['base' => 'GBP', 'quote' => 'BTC'],
            ['base' => 'USD', 'quote' => 'GBP'],
            ['base' => 'USD', 'quote' => 'CAD'],
        ];

        $synchronizer = app(ExchangeRateSynchronizer::class);
        $synchronizer->synchronize([
            'pairs' => $pairs,
            'provider' => 'coingecko',
        ]);

        Http::assertSentCount(1);

        $usdBtc = ExchangeRate::where('base_currency', 'USD')
            ->where('quote_currency', 'BTC')
            ->firstOrFail();
        $this->assertSame('0.00001667', $usdBtc->rate);

        $gbpBtc = ExchangeRate::where('base_currency', 'GBP')
            ->where('quote_currency', 'BTC')
            ->firstOrFail();
        $this->assertSame('0.00002083', $gbpBtc->rate);

        $usdGbp = ExchangeRate::where('base_currency', 'USD')
            ->where('quote_currency', 'GBP')
            ->firstOrFail();
        $this->assertSame('0.80000000', $usdGbp->rate);

        $usdCad = ExchangeRate::where('base_currency', 'USD')
            ->where('quote_currency', 'CAD')
            ->firstOrFail();
        $this->assertSame('1.35000000', $usdCad->rate);

        $snapshot = DatasetSnapshot::where('kind', 'fx_refresh')->firstOrFail();
        $this->assertSame('succeeded', $snapshot->status);
        $this->assertSame(4, $snapshot->row_count);
        $this->assertSame('coingecko', $snapshot->provider);
        $this->assertSame(4, count($snapshot->context['pairs'] ?? []));
    }
}
