<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ProviderCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        // Seed live catalogue and prices/media from providers after verifying endpoints.
        // Note: This will make external HTTP calls. Ensure API keys are set in .env.
        Artisan::call('providers:verify-and-seed', [
            '--limit' => 40,
            '--window' => 180,
            '--families' => 'xbox,playstation,nintendo,pc',
            '--regions' => 'US,GB,EU,CA',
        ]);
    }
}