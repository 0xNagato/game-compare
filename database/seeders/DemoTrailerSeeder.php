<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoTrailerSeeder extends Seeder
{
    public function run(): void
    {
        // Attach a Wikimedia Commons trailer to Zelda for demo inline playback via proxy
        $product = Product::query()->where('slug', 'the-legend-of-zelda-tears-of-the-kingdom')->first();

        if (! $product) {
            return;
        }

        // Big Buck Bunny trailer as a safe, freely redistributable sample (WebM)
        $videoUrl = 'https://upload.wikimedia.org/wikipedia/commons/transcoded/7/70/Big_Buck_Bunny_Trailer_400p.ogv/Big_Buck_Bunny_Trailer_400p.ogv.360p.webm';
        $thumbUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/70/Big_Buck_Bunny_Trailer_400p.ogv/320px--Big_Buck_Bunny_Trailer_400p.ogv.jpg';

        $product->media()->updateOrCreate(
            [
                'source' => 'wikimedia',
                'external_id' => 'demo-trailer-webm',
            ],
            [
                'media_type' => 'video',
                'title' => 'Demo Trailer (WebM)',
                'caption' => 'Sample trailer for inline playback testing',
                'url' => $videoUrl,
                'thumbnail_url' => $thumbUrl,
                'attribution' => 'Wikimedia Commons',
                'license' => 'CC BY 3.0',
                'license_url' => 'https://creativecommons.org/licenses/by/3.0/',
                'is_primary' => false,
                'width' => 640,
                'height' => 360,
                'quality_score' => 0.8,
                'fetched_at' => now(),
                'metadata' => [
                    'demo' => true,
                ],
            ]
        );
    }
}