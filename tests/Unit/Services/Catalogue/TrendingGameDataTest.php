<?php

use App\Services\Catalogue\DTOs\TrendingGameData;
use Carbon\CarbonImmutable;

it('builds source-aware metadata for GiantBomb', function (): void {
    $payload = [
        'id' => 3030,
        'name' => 'Test GiantBomb Game',
        'original_release_date' => '2024-03-15',
        'expected_release_year' => 2024,
        'site_detail_url' => 'https://www.giantbomb.com/test-giantbomb-game/3030-3030/',
        'platforms' => [
            ['name' => 'PC'],
            ['name' => 'PlayStation 5'],
        ],
        'genres' => [
            ['name' => 'Action'],
            ['name' => 'Adventure'],
        ],
        'aliases' => "Test GiantBomb Game\nTestGame",
        'image' => ['original_url' => 'https://cdn.giantbomb.com/test.jpg'],
        'number_of_user_reviews' => 420,
    ];

    $game = TrendingGameData::fromGiantBomb($payload);

    expect($game->source())->toBe('giantbomb');
    expect($game->releasedAt)->toEqual(CarbonImmutable::parse('2024-03-15'));

    $metadata = $game->metadata();

    expect($metadata)
        ->toHaveKey('source', 'giantbomb')
        ->toHaveKey('giantbomb_id', 3030)
        ->toHaveKey('giantbomb_url', 'https://www.giantbomb.com/test-giantbomb-game/3030-3030/')
        ->and($metadata['platforms'])->toContain('PC', 'PlayStation 5')
        ->and($metadata['genres'])->toContain('Action', 'Adventure');

    expect($metadata['aliases'] ?? null)
        ->toBeArray()
        ->toContain('Test GiantBomb Game', 'TestGame');
});

it('builds source-aware metadata for Nexarda', function (): void {
    $payload = [
        'id' => 2781,
        'title' => 'Starfield',
        'slug' => 'starfield',
        'release_date' => '2023-09-06',
        'platforms' => ['PC', 'Xbox Series X|S'],
        'genres' => [
            ['name' => 'RPG'],
            ['name' => 'Sci-Fi'],
        ],
        'stores' => ['Steam', 'Xbox Store'],
        'score' => 88,
        'age_rating' => 'M',
        'website' => 'https://www.nexarda.com/game/starfield',
        'summary' => 'Explore the settled systems.',
    ];

    $game = TrendingGameData::fromNexarda($payload);

    expect($game->source())->toBe('nexarda');
    expect($game->releasedAt)->toEqual(CarbonImmutable::parse('2023-09-06'));

    $metadata = $game->metadata();

    expect($metadata)
        ->toHaveKey('source', 'nexarda')
        ->toHaveKey('nexarda_id', 2781)
        ->toHaveKey('nexarda_slug', 'starfield')
        ->toHaveKey('nexarda_url', 'https://www.nexarda.com/game/starfield')
        ->and($metadata['platforms'])->toContain('PC', 'Xbox Series X|S')
        ->and($metadata['genres'])->toContain('RPG', 'Sci-Fi')
        ->and($metadata['stores'])->toContain('Steam', 'Xbox Store');

    expect($metadata['score'] ?? null)->toBe(88);
});
