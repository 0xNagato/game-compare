<?php

namespace Tests\Unit\Services\Catalogue;

use App\Services\Catalogue\TrendingGameImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrendingGameImporterTest extends TestCase
{
    public function test_it_fetches_trending_games_from_rawg(): void
    {
        config()->set('media.providers.rawg.options.api_key', 'test-key');

        Http::fakeSequence()
            ->push($this->rawgResponse([
                $this->rawgItem('game-one', 'Game One'),
                $this->rawgItem('game-two', 'Game Two'),
            ], 'https://api.rawg.io/api/games?page=2'), 200)
            ->push($this->rawgResponse([
                $this->rawgItem('game-three', 'Game Three'),
            ]), 200)
            ->whenEmpty(Http::response($this->rawgResponse(), 200));

        $importer = app(TrendingGameImporter::class);
        $games = $importer->fetch(3, 90);

        $this->assertCount(3, $games);

        $first = $games->first();
        $this->assertSame('Game One', $first->name);
        $this->assertSame('game-one', $first->slug);
        $this->assertSame('PC', $first->primaryPlatform());
        $this->assertSame('Game', $first->toProductAttributes()['category']);
    }

    private function rawgResponse(array $items = [], ?string $next = null): array
    {
        return [
            'results' => $items,
            'next' => $next,
        ];
    }

    private function rawgItem(string $slug, ?string $name = null): array
    {
        $name ??= Str::headline($slug);

        return [
            'id' => random_int(1, 999999),
            'slug' => $slug,
            'name' => $name,
            'released' => '2024-10-01',
            'platforms' => [
                ['platform' => ['name' => 'PC']],
                ['platform' => ['name' => 'PlayStation 5']],
            ],
            'genres' => [
                ['name' => 'Action'],
            ],
            'stores' => [
                ['store' => ['name' => 'Steam']],
            ],
            'rating' => 4.5,
            'metacritic' => 86,
            'playtime' => 12,
            'esrb_rating' => ['name' => 'Mature'],
            'tags' => [
                ['name' => 'Multiplayer'],
            ],
        ];
    }
}
