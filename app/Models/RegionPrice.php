<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionPrice extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'recorded_at' => 'datetime',
        'tax_inclusive' => 'boolean',
        'fiat_amount' => 'decimal:2',
        'local_amount' => 'decimal:2',
        'btc_value' => 'decimal:8',
        'fx_rate_snapshot' => 'decimal:6',
        'btc_rate_snapshot' => 'decimal:8',
        'raw_payload' => 'array',
    ];

    /**
     * @return BelongsTo<SkuRegion, static>
     */
    public function skuRegion(): BelongsTo
    {
        return $this->belongsTo(SkuRegion::class);
    }

    /**
     * @return BelongsTo<Currency, static>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Country, static>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getLocalAmountAttribute(?string $value): ?string
    {
        /** @var string|null $fiat */
        $fiat = $this->fiat_amount;
        return $value ?? $fiat;
    }
}
