<?php

namespace App\Services\PriceIngestion\Providers;

class PlayStationStoreProvider extends AbstractProviderStub
{
    protected function providerKey(): string
    {
        return 'playstation_store';
    }

    protected function providerName(): string
    {
        return 'PlayStation Store';
    }

    protected function loggableOptionKeys(): array
    {
        return ['locales', 'catalog_queries'];
    }

    protected function providerMeta(): array
    {
        return [
            'kind' => 'digital',
            'platforms' => ['PlayStation 4', 'PlayStation 5'],
            'regions' => [
                'US',
                'CA',
                'MX',
                'BR',
                'AR',
                'CL',
                'PE',
                'GB',
                'IE',
                'DE',
                'FR',
                'ES',
                'IT',
                'PT',
                'NL',
                'BE',
                'SE',
                'NO',
                'DK',
                'FI',
                'PL',
                'CZ',
                'AE',
                'SA',
                'IN',
                'JP',
                'KR',
                'SG',
                'HK',
                'TW',
                'AU',
                'NZ',
            ],
            'supports_history' => false,
            'auth' => null,
            'rate_limit_note' => 'Scraped/unofficial APIs; keep under 20 req/min per locale and rotate.',
            'docs' => 'Community articles/libs',
            'notes' => 'Locale-specific catalog and pricing endpoints for PSN.',
        ];
    }
}
