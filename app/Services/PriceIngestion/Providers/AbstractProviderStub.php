<?php

namespace App\Services\PriceIngestion\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

abstract class AbstractProviderStub
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function fetchDeals(array $options = []): array
    {
        $payload = [
            'results' => [],
            'meta' => array_merge([
                'provider' => $this->providerKey(),
                'provider_name' => $this->providerName(),
                'stub' => true,
                'message' => 'Provider stub invoked; implement fetchDeals() for live ingestion.',
                'sample_result_schema' => $this->sampleResultSchema(),
            ], $this->providerMeta()),
        ];

        Log::channel('daily')->info('price_ingest.provider_stub_invoked', [
            'provider' => $this->providerKey(),
            'options' => Arr::only($options, $this->loggableOptionKeys()),
            'meta' => $payload['meta'],
        ]);

        return $payload;
    }

    /**
     * @return list<string>
     */
    protected function loggableOptionKeys(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerMeta(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sampleResultSchema(): array
    {
        return [
            'game' => [
                'title' => 'Example Title',
                'slug' => 'example-title',
                'platform' => 'Platform',
                'category' => 'Game|Hardware',
                'external_id' => 'external-identifier',
            ],
            'deals' => [
                [
                    'store_id' => 'store-identifier',
                    'deal_id' => 'deal-identifier',
                    'sale_price' => '19.99',
                    'original_price' => '59.99',
                    'currency' => 'USD',
                    'last_change' => now()->timestamp,
                    'extras' => [],
                ],
            ],
        ];
    }

    abstract protected function providerKey(): string;

    abstract protected function providerName(): string;
}
