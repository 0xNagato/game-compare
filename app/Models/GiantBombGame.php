<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GiantBombGame extends Model
{
    protected $fillable = [
        'guid',
        'giantbomb_id',
        'name',
        'slug',
        'site_detail_url',
        'deck',
        'description',
        'platforms',
        'aliases',
        'primary_image_url',
        'image_super_url',
        'image_small_url',
        'image_original_url',
    'primary_video_name',
    'primary_video_high_url',
    'primary_video_hd_url',
    'video_count',
    'videos',
        'normalized_name',
        'payload_hash',
        'last_synced_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'platforms' => 'array',
        'aliases' => 'array',
        'videos' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $game): void {
            if (! $game->slug) {
                $game->slug = Str::slug($game->name ?? '');
            }
        });
    }
}
