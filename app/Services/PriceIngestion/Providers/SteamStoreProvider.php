<?php

namespace App\Services\PriceIngestion\Providers;

class SteamStoreProvider extends AbstractProviderStub
{
    protected function providerKey(): string
    {
        return 'steam_store';
    }

    protected function providerName(): string
    {
        return 'Steam Store';
    }

    protected function loggableOptionKeys(): array
    {
        return ['apps', 'regions', 'currencies'];
    }

    protected function providerMeta(): array
    {
        return [
            'kind' => 'digital',
            'platforms' => ['PC'],
            'regions' => [
                'US',
                'CA',
                'GB',
                'DE',
                'FR',
                'IT',
                'ES',
                'NL',
                'SE',
                'PL',
                'AU',
                'NZ',
                'JP',
                'KR',
                'SG',
                'HK',
                'TW',
                'BR',
                'MX',
                'ZA',
            ],
            'supports_history' => false,
            'auth' => null,
            'rate_limit_note' => 'Undocumented HTML/JSON combos; limit to <30 req/min and cache per region.',
            'docs' => 'Community references',
            'notes' => 'Real-time store price per market via cc/currency params.',
        ];
    }
}