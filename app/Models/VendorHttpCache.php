<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorHttpCache extends Model
{
    protected $fillable = [
        'provider',
        'endpoint',
        'etag',
        'last_modified_at',
        'last_checked_at',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_modified_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];
}
