<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'release_date' => 'date',
        'metadata' => 'array',
        'external_ids' => 'array',
    ];

    /**
     * @return HasMany<SkuRegion, static>
     */
    public function skuRegions(): HasMany
    {
        return $this->hasMany(SkuRegion::class);
    }

    /**
     * @return HasMany<Alert, static>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return HasMany<ProductMedia, static>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    /**
     * @return HasMany<GameAlias, static>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(GameAlias::class);
    }

    /**
     * @return BelongsToMany<Platform, static>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'game_platform')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Genre, static>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'game_genre')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Console, static>
     */
    public function consoles(): HasMany
    {
        return $this->hasMany(Console::class);
    }

    /**
     * @return HasMany<VideoGame, static>
     */
    public function videoGames(): HasMany
    {
        return $this->hasMany(VideoGame::class);
    }

    public function coverMedia(): ?ProductMedia
    {
        /** @var ProductMedia|null $media */
        $media = $this->media()
            ->orderByDesc('fetched_at')
            ->orderByDesc('created_at')
            ->first();
        return $media;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}