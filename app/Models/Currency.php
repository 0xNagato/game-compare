<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_crypto' => 'boolean',
    ];

    public function localCurrencies(): HasMany
    {
        return $this->hasMany(LocalCurrency::class);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }

    public function regionPrices(): HasMany
    {
        return $this->hasMany(RegionPrice::class);
    }
}
