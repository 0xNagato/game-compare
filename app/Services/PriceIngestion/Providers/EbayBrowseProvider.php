<?php

namespace App\Services\PriceIngestion\Providers;

class EbayBrowseProvider extends AbstractProviderStub
{
    protected function providerKey(): string
    {
        return 'ebay_browse';
    }

    protected function providerName(): string
    {
        return 'eBay Browse API';
    }

    protected function loggableOptionKeys(): array
    {
        return ['oauth_app_id', 'category_ids', 'markets'];
    }

    protected function providerMeta(): array
    {
        return [
            'kind' => 'marketplace_listings',
            'platforms' => ['All'],
            'regions' => ['US', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES', 'AU', 'NZ', 'JP', 'SG', 'HK'],
            'supports_history' => 'sold_items_query',
            'auth' => 'oauth',
            'rate_limit_note' => 'Browse API default: 5,000 calls/day, peak 5 req/sec.',
            'docs' => 'https://developer.ebay.com/api-docs/buy/browse/overview.html',
            'notes' => 'Returns live listings with price, currency, shipping, taxes, and seller details.',
        ];
    }
}
