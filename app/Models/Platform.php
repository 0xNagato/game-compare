<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Platform extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * @return BelongsToMany<Product, static>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'game_platform')->withTimestamps();
    }
}