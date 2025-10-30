<?php

namespace Database\Seeders;

use App\Models\GameAlias;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'The Legend of Zelda: Tears of the Kingdom',
                'platform_family' => 'nintendo',
                'platforms' => [
                    ['code' => 'switch', 'name' => 'Nintendo Switch', 'family' => 'nintendo'],
                ],
                'genres' => ['action-adventure' => 'Action Adventure', 'open-world' => 'Open World'],
                'release_date' => '2023-05-12',
                'popularity_score' => 0.92,
                'rating' => 96,
                'freshness_score' => 0.82,
                'synopsis' => 'Embark on a sprawling adventure across the skies and surface of Hyrule, crafting new abilities to fend off a looming calamity.',
                'external_ids' => ['rawg' => 'the-legend-of-zelda-tears-of-the-kingdom'],
                'metadata' => ['publisher' => 'Nintendo', 'developer' => 'Nintendo EPD'],
                'aliases' => [
                    ['provider' => 'rawg', 'id' => 'the-legend-of-zelda-tears-of-the-kingdom', 'title' => 'The Legend of Zelda: Tears of the Kingdom'],
                    ['provider' => 'thegamesdb', 'id' => '65000', 'title' => 'Zelda: Tears of the Kingdom'],
                ],
                'media' => [
                    ['url' => 'https://example.com/images/totk-cover.jpg', 'is_primary' => true, 'width' => 1200, 'height' => 1600, 'quality' => 0.94],
                ],
            ],
            [
                'name' => "Marvel's Spider-Man 2",
                'platform_family' => 'playstation',
                'platforms' => [
                    ['code' => 'ps5', 'name' => 'PlayStation 5', 'family' => 'playstation'],
                ],
                'genres' => ['action-adventure' => 'Action Adventure', 'superhero' => 'Superhero'],
                'release_date' => '2023-10-20',
                'popularity_score' => 0.89,
                'rating' => 94,
                'freshness_score' => 0.76,
                'synopsis' => 'Swing across Marvel’s New York as Peter Parker and Miles Morales while mastering symbiote powers to stop new threats.',
                'external_ids' => ['rawg' => 'marvels-spider-man-2'],
                'metadata' => ['publisher' => 'Sony Interactive Entertainment', 'developer' => 'Insomniac Games'],
                'aliases' => [
                    ['provider' => 'rawg', 'id' => 'marvels-spider-man-2', 'title' => "Marvel's Spider-Man 2"],
                    ['provider' => 'thegamesdb', 'id' => '70002', 'title' => 'Spider-Man 2'],
                ],
                'media' => [
                    ['url' => 'https://example.com/images/spiderman2-cover.jpg', 'is_primary' => true, 'width' => 1180, 'height' => 1560, 'quality' => 0.91],
                ],
            ],
            [
                'name' => 'Starfield',
                'platform_family' => 'xbox',
                'platforms' => [
                    ['code' => 'xbox-series', 'name' => 'Xbox Series X|S', 'family' => 'xbox'],
                    ['code' => 'pc', 'name' => 'PC', 'family' => 'pc'],
                ],
                'genres' => ['rpg' => 'Role Playing', 'sci-fi' => 'Sci-Fi'],
                'release_date' => '2023-09-06',
                'popularity_score' => 0.85,
                'rating' => 88,
                'freshness_score' => 0.78,
                'synopsis' => 'Explore the Settled Systems as a customizable explorer in Bethesda’s first new universe in 25 years.',
                'external_ids' => ['rawg' => 'starfield'],
                'metadata' => ['publisher' => 'Bethesda Softworks', 'developer' => 'Bethesda Game Studios'],
                'aliases' => [
                    ['provider' => 'rawg', 'id' => 'starfield', 'title' => 'Starfield'],
                    ['provider' => 'nexarda', 'id' => '2781', 'title' => 'Starfield'],
                ],
                'media' => [
                    ['url' => 'https://example.com/images/starfield-cover.jpg', 'is_primary' => true, 'width' => 1200, 'height' => 1600, 'quality' => 0.9],
                ],
            ],
        ];

        foreach ($products as $entry) {
            $slug = Str::slug($entry['name']);
            $uid = hash('sha256', Str::lower($entry['name']).'|'.$entry['release_date'].'|'.$entry['platform_family']);

            $product = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $entry['name'],
                    'platform' => $entry['platforms'][0]['name'] ?? null,
                    'category' => 'Game',
                    'release_date' => $entry['release_date'],
                    'metadata' => $entry['metadata'],
                    'slug' => $slug,
                    'uid' => $uid,
                    'primary_platform_family' => $entry['platform_family'],
                    'popularity_score' => $entry['popularity_score'],
                    'rating' => $entry['rating'],
                    'freshness_score' => $entry['freshness_score'],
                    'external_ids' => $entry['external_ids'],
                    'synopsis' => $entry['synopsis'],
                ]
            );

            foreach ($entry['platforms'] as $platformData) {
                $platform = Platform::query()->updateOrCreate(
                    ['code' => $platformData['code']],
                    [
                        'name' => $platformData['name'],
                        'family' => $platformData['family'],
                    ]
                );

                $product->platforms()->syncWithoutDetaching([$platform->id]);
            }

            foreach ($entry['genres'] as $slugKey => $name) {
                $genre = Genre::query()->updateOrCreate(
                    ['slug' => $slugKey],
                    ['name' => $name]
                );

                $product->genres()->syncWithoutDetaching([$genre->id]);
            }

            foreach ($entry['aliases'] as $alias) {
                GameAlias::query()->updateOrCreate(
                    [
                        'provider' => $alias['provider'],
                        'provider_game_id' => $alias['id'],
                    ],
                    [
                        'product_id' => $product->id,
                        'alias_title' => $alias['title'],
                    ]
                );
            }

            foreach ($entry['media'] as $index => $media) {
                $product->media()->updateOrCreate(
                    [
                        'source' => 'seed',
                        'external_id' => $slug.'-'.$index,
                    ],
                    [
                        'url' => $media['url'],
                        'media_type' => 'image',
                        'is_primary' => $media['is_primary'],
                        'width' => $media['width'],
                        'height' => $media['height'],
                        'quality_score' => $media['quality'],
                        'attribution' => 'Seeded Preview',
                        'license' => 'CC-BY-4.0',
                        'metadata' => ['seeded' => true],
                        'fetched_at' => now(),
                    ]
                );
            }
        }
    }
}