<?php

declare(strict_types=1);

use App\Models\GiantBombGame;
use Illuminate\Support\Carbon;

it('upserts the Giant Bomb catalogue into the mirror table', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'giantbomb_');

    $initialPayload = [
        '3030-99901' => [
            'guid' => '3030-99901',
            'name' => 'Sample Giant Bomb Game',
            'slug' => 'Sample Giant Bomb Game',
            'site_detail_url' => 'https://www.giantbomb.com/sample-giant-bomb-game/3030-99901/',
            'deck' => 'A test entry from Giant Bomb.',
            'description' => '<p>Primary description.</p>',
            'image' => [
                'super_url' => 'https://cdn.test/giantbomb/sample-super.jpg',
                'small_url' => 'https://cdn.test/giantbomb/sample-small.jpg',
            ],
            'platforms' => [
                ['name' => 'PC'],
                ['name' => 'PlayStation 5'],
            ],
            'aliases' => "Sample Alias\nSample Two",
        ],
        '3030-99902' => [
            'guid' => '3030-99902',
            'name' => 'Secondary Giant Bomb Game',
            'site_detail_url' => 'https://www.giantbomb.com/secondary-giant-bomb-game/3030-99902/',
            'image' => [
                'original_url' => 'https://cdn.test/giantbomb/secondary-original.jpg',
            ],
            'platforms' => [
                ['name' => 'Xbox Series X'],
            ],
        ],
    ];

    file_put_contents($path, json_encode($initialPayload, JSON_THROW_ON_ERROR));

    $this->artisan('catalogue:import-giantbomb', ['source' => $path])->assertExitCode(0);

    $primary = GiantBombGame::where('guid', '3030-99901')->firstOrFail();
    $secondary = GiantBombGame::where('guid', '3030-99902')->firstOrFail();

    expect($primary->name)->toBe('Sample Giant Bomb Game');
    expect($primary->slug)->toBe('sample-giant-bomb-game');
    expect($primary->primary_image_url)->toBe('https://cdn.test/giantbomb/sample-super.jpg');
    expect($primary->platforms)->toBe(['PC', 'PlayStation 5']);
    expect($primary->aliases)->toBe(['Sample Alias', 'Sample Two']);
    expect($secondary->primary_image_url)->toBe('https://cdn.test/giantbomb/secondary-original.jpg');
    expect($secondary->platforms)->toBe(['Xbox Series X']);

    $previousUpdatedAt = $primary->updated_at;
    $previousSecondarySyncedAt = $secondary->last_synced_at;

    Carbon::setTestNow(Carbon::now()->addMinutes(5));

    $updatedPayload = [
        '3030-99901' => [
            'guid' => '3030-99901',
            'name' => 'Sample Giant Bomb Game Updated',
            'slug' => 'Sample Giant Bomb Game Updated',
            'image' => [
                'super_url' => 'https://cdn.test/giantbomb/sample-super-new.jpg',
            ],
            'platforms' => [
                ['name' => 'PC'],
            ],
        ],
    ];

    file_put_contents($path, json_encode($updatedPayload, JSON_THROW_ON_ERROR));

    $this->artisan('catalogue:import-giantbomb', ['source' => $path])->assertExitCode(0);

    $primary->refresh();
    $secondary->refresh();

    expect($primary->name)->toBe('Sample Giant Bomb Game Updated');
    expect($primary->primary_image_url)->toBe('https://cdn.test/giantbomb/sample-super-new.jpg');
    expect($primary->platforms)->toBe(['PC']);
    expect($primary->updated_at->greaterThan($previousUpdatedAt))->toBeTrue();
    expect($secondary->last_synced_at)->toBeNull();

    unlink($path);
    Carbon::setTestNow();
});

it('supports dry runs without mutating the database', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'giantbomb_');
    $payload = [
        '3030-11111' => [
            'guid' => '3030-11111',
            'name' => 'Dry Run Game',
            'image' => [
                'super_url' => 'https://cdn.test/dry-run.jpg',
            ],
        ],
    ];

    file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

    $this->artisan('catalogue:import-giantbomb', ['source' => $path, '--dry-run' => true])->assertExitCode(0);

    expect(GiantBombGame::query()->count())->toBe(0);

    unlink($path);
});

it('captures primary video metadata when present in the payload', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'giantbomb_');

    $payload = [
        '3030-424242' => [
            'guid' => '3030-424242',
            'name' => 'Video Showcase Game',
            'videos' => [
                [
                    'name' => 'Launch Trailer',
                    'guid' => '2300-100001',
                    'high_url' => 'https://videos.example/launch-trailer-high.mp4',
                    'hd_url' => 'https://videos.example/launch-trailer-hd.mp4',
                    'embed_player' => '<iframe src="https://videos.example/embed/launch"></iframe>',
                ],
                [
                    'name' => 'Developer Diary',
                    'guid' => '2300-100002',
                    'high_url' => 'https://videos.example/dev-diary-high.mp4',
                ],
            ],
        ],
    ];

    file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

    $this->artisan('catalogue:import-giantbomb', ['source' => $path])->assertExitCode(0);

    $game = GiantBombGame::where('guid', '3030-424242')->firstOrFail();

    expect($game->primary_video_name)->toBe('Launch Trailer')
        ->and($game->primary_video_high_url)->toBe('https://videos.example/launch-trailer-high.mp4')
        ->and($game->primary_video_hd_url)->toBe('https://videos.example/launch-trailer-hd.mp4')
        ->and($game->video_count)->toBe(2)
        ->and($game->videos)->toEqual([
            [
                'name' => 'Launch Trailer',
                'guid' => '2300-100001',
                'high_url' => 'https://videos.example/launch-trailer-high.mp4',
                'hd_url' => 'https://videos.example/launch-trailer-hd.mp4',
            ],
            [
                'name' => 'Developer Diary',
                'guid' => '2300-100002',
                'high_url' => 'https://videos.example/dev-diary-high.mp4',
            ],
        ]);

    unlink($path);
});
