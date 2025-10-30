<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSeriesAggregate extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'tax_inclusive' => 'boolean',
        'min_btc' => 'decimal:18',
        'max_btc' => 'decimal:18',
        'avg_btc' => 'decimal:18',
        'min_fiat' => 'decimal:2',
        'max_fiat' => 'decimal:2',
        'avg_fiat' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Product, static>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
