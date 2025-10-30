<?php

namespace App\Services\PriceIngestion\Providers;

class MicrosoftStoreProvider extends AbstractProviderStub
{
    protected function providerKey(): string
    {
        return 'microsoft_store';
    }

    protected function providerName(): string
    {
        return 'Microsoft/Xbox Store';
    }

    protected function loggableOptionKeys(): array
    {
        return ['markets', 'languages', 'product_ids'];
    }

    protected function providerMeta(): array
    {
        return [
            'kind' => 'digital',
            'platforms' => ['Xbox', 'PC (Microsoft Store)'],
            'regions' => [
                'US',
                'CA',
                'MX',
                'BR',
                'GB',
                'IE',
                'DE',
                'FR',
                'IT',
                'ES',
                'PT',
                'NL',
                'BE',
                'SE',
                'NO',
                'DK',
                'FI',
                'PL',
                'CZ',
                'JP',
                'KR',
                'CN',
                'HK',
                'TW',
                'SG',
                'AU',
                'NZ',
                'IN',
                'ZA',
                'AE',
                'SA',
            ],
            'supports_history' => false,
            'auth' => null,
            'rate_limit_note' => 'DisplayCatalog JSON; Microsoft suggests <20 req/sec and caching.',
            'docs' => 'Community references',
            'notes' => 'DisplayCatalog products endpoint returns price and availability per market.',
        ];
    }
}
