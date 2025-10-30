<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderUsage extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_called_at' => 'datetime',
        'daily_window' => 'date',
    ];
}
