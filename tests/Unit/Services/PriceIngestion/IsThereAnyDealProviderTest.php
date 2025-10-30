<?php

namespace Tests\Unit\Services\PriceIngestion;

use App\Services\PriceIngestion\Providers\IsThereAnyDealProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsThereAnyDealProviderTest extends TestCase
{
    public function test_fetch_deals_transforms_itad_offers(): void
    {
        $searchResponse = [
            'data' => [
                'results' => [
                    [
                        'title' => 'Starfield',
                        'plain' => 'starfield',
                    ],
                ],
            ],
        ];

        $usdPrices = [
            'data' => [
                'starfield' => [
                    'list' => [
                        [
                            'price_new' => 49.99,
                            'price_old' => 69.99,
                            'price_cut' => 28,
                            'url' => 'https://store.steampowered.com/app/1716740',
                            'timestamp' => 1_700_000_000,
                            'shop' => [
                                'id' => 'steam',
                                'slug' => 'steam',
                                'name' => 'Steam',
                            ],
                        ],
                        [
                            'price_new' => 47.99,
                            'price_old' => 59.99,
                            'url' => 'https://www.humblebundle.com/store/starfield',
                            'timestamp' => 1_699_000_000,
                            'shop' => [
                                'id' => 'humblestore',
                                'slug' => 'humblestore',
                                'name' => 'Humble Store',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $gbpPrices = [
            'data' => [
                'starfield' => [
                    'list' => [
                        [
                            'price_new' => 44.99,
                            'price_old' => 59.99,
                            'url' => 'https://store.steampowered.com/app/1716740',
                            'timestamp' => 1_700_100_000,
                            'shop' => [
                                'id' => 'steam',
                                'slug' => 'steam',
                                'name' => 'Steam',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $eurPrices = [
            'data' => [
                'starfield' => [
                    'list' => [
                        [
                            'price_new' => 52.99,
                            'price_old' => 69.99,
                            'url' => 'https://store.steampowered.com/app/1716740',
                            'timestamp' => 1_700_200_000,
                            'shop' => [
                                'id' => 'steam',
                                'slug' => 'steam',
                                'name' => 'Steam',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake(function ($request) use ($searchResponse, $usdPrices, $gbpPrices, $eurPrices) {
            $url = $request->url();

            if (str_contains($url, '/v02/search/search/')) {
                return Http::response($searchResponse, 200);
            }

            if (str_contains($url, '/v01/game/prices/')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
                $country = $query['country'] ?? null;

                return match ($country) {
                    'gb' => Http::response($gbpPrices, 200),
                    'de' => Http::response($eurPrices, 200),
                    default => Http::response($usdPrices, 200),
                };
            }

            return Http::response([], 404);
        });

        $provider = new IsThereAnyDealProvider;

        $result = $provider->fetchDeals([
            'api_key' => 'test-key',
            'default_regions' => [
                ['currency' => 'USD', 'country' => 'us', 'region_code' => 'US'],
                ['currency' => 'GBP', 'country' => 'gb', 'region_code' => 'GB'],
                ['currency' => 'EUR', 'country' => 'de', 'region_code' => 'DE'],
            ],
            'requests' => [
                [
                    'title' => 'Starfield',
                    'product' => [
                        'title' => 'Starfield',
                        'slug' => 'starfield',
                        'platform' => 'PC',
                        'category' => 'Game',
                    ],
                    'store_map' => [
                        'steam' => [
                            'USD' => ['store_id' => 'itad_steam_usd', 'region_code' => 'US'],
                            'GBP' => ['store_id' => 'itad_steam_gbp', 'region_code' => 'GB'],
                            'EUR' => ['store_id' => 'itad_steam_eur', 'region_code' => 'DE'],
                        ],
                        'humblestore' => [
                            'USD' => ['store_id' => 'itad_humble_usd', 'region_code' => 'US'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);

        $entry = $result['results'][0];

        $this->assertSame('Starfield', $entry['game']['title']);
        $this->assertSame('starfield', $entry['game']['slug']);
        $this->assertFalse($result['meta']['stub']);

        $dealStoreIds = collect($entry['deals'])->pluck('store_id');
        $this->assertContains('itad_steam_usd', $dealStoreIds);
        $this->assertContains('itad_steam_gbp', $dealStoreIds);
        $this->assertContains('itad_humble_usd', $dealStoreIds);

        $steamUsdDeal = collect($entry['deals'])->firstWhere('store_id', 'itad_steam_usd');
        $this->assertSame(49.99, $steamUsdDeal['sale_price']);
        $this->assertSame(69.99, $steamUsdDeal['normal_price']);
        $this->assertSame('USD', $steamUsdDeal['currency']);
        $this->assertSame('US', $steamUsdDeal['region_code']);
    }
}
