<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorSyncState extends Model
{
    protected $fillable = [
        'provider',
        'last_full_sync_at',
        'last_incremental_sync_at',
        'vendor_token',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_full_sync_at' => 'datetime',
        'last_incremental_sync_at' => 'datetime',
        'metadata' => 'array',
    ];
}
