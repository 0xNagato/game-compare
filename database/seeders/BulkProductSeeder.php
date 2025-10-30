<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BulkProductSeeder extends Seeder
{
    public function run(): void
    {
        $families = [
            'xbox' => 'Xbox Series X|S',
            'playstation' => 'PlayStation 5',
            'nintendo' => 'Nintendo Switch',
            'pc' => 'PC',
        ];

        $target = 350; // create ~350 additional demo products
        $created = 0;

        for ($i = 1; $i <= $target; $i++) {
            $famKeys = array_keys($families);
            $family = $famKeys[$i % count($famKeys)];
            $platformName = $families[$family];

            $name = 'Demo Game '.str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $slug = Str::slug($name.'-'.$family);
            $releaseDate = now()->subDays(rand(0, 2000))->toDateString();
            $uid = hash('sha256', Str::lower($name).'|'.$releaseDate.'|'.$family);

            // Skip if already exists (by slug or uid)
            if (Product::query()->where('slug', $slug)->orWhere('uid', $uid)->exists()) {
                continue;
            }

            Product::query()->create([
                'slug' => $slug,
                'name' => $name,
                'platform' => $platformName,
                'category' => 'Game',
                'release_date' => $releaseDate,
                'metadata' => ['source' => 'bulk_seeder'],
                'uid' => $uid,
                'primary_platform_family' => $family,
                'popularity_score' => round(mt_rand(100, 950) / 1000, 3),
                'rating' => mt_rand(60, 96),
                'freshness_score' => round(mt_rand(100, 1000) / 1000, 3),
                'external_ids' => [],
                'synopsis' => null,
            ]);

            $created++;
        }

        // Optional: echo into logs
        echo "BulkProductSeeder created {$created} products.".PHP_EOL;
    }
}
