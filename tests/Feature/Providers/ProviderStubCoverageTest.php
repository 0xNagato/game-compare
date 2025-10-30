<?php

use App\Services\PriceIngestion\Providers\EbayBrowseProvider;
use App\Services\PriceIngestion\Providers\MicrosoftStoreProvider;
use App\Services\PriceIngestion\Providers\PlayStationStoreProvider;
use App\Services\PriceIngestion\Providers\SteamStoreProvider;
use App\Services\PriceIngestion\Providers\TheGamesDbProvider;

$differentRegionMessage = 'Provider stub regions and config regions must stay in sync when coverage changes.';

dataset('provider_stubs', [
    ['steam_store', SteamStoreProvider::class],
    ['playstation_store', PlayStationStoreProvider::class],
    ['microsoft_store', MicrosoftStoreProvider::class],
    ['ebay_browse', EbayBrowseProvider::class],
    ['thegamesdb', TheGamesDbProvider::class],
]);

it('keeps provider stub metadata aligned with config coverage', function (string $providerKey, string $providerClass) use ($differentRegionMessage) {
    $options = config("pricing.providers.{$providerKey}.options", []);

    $payload = app($providerClass)->fetchDeals($options);

    $metaRegions = $payload['meta']['regions'] ?? [];
    $configRegions = config("pricing.providers.{$providerKey}.regions", []);

    expect($metaRegions)->toEqualCanonicalizing($configRegions, $differentRegionMessage);
})->with('provider_stubs');
