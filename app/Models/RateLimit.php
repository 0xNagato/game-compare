<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    protected $primaryKey = 'provider';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'tokens',
        'last_refill_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tokens' => 'float',
        'last_refill_at' => 'datetime',
    ];
}
