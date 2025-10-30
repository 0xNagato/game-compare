<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SkuRegion extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Product, static>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<RegionPrice, static>
     */
    public function regionPrices(): HasMany
    {
        return $this->hasMany(RegionPrice::class);
    }

    /**
     * @return HasOne<RegionPrice, static>
     */
    public function latestPrice(): HasOne
    {
        return $this->hasOne(RegionPrice::class)->latestOfMany('recorded_at');
    }

    /**
     * @return BelongsTo<Country, static>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Currency, static>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
