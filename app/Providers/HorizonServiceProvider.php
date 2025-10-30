<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            return $request->user() !== null;
        });
    }
}
