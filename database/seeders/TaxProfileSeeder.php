<?php

namespace Database\Seeders;

use App\Models\TaxProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TaxProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['region_code' => 'US', 'vat_rate' => 0.0, 'notes' => 'Digital purchases vary by state; treat as tax exclusive.'],
            ['region_code' => 'GB', 'vat_rate' => 0.20, 'notes' => 'Standard VAT rate for UK store pricing.'],
            ['region_code' => 'CA', 'vat_rate' => 0.13, 'notes' => 'Approximate HST for Ontario.'],
            ['region_code' => 'EU', 'vat_rate' => 0.21, 'notes' => 'Representative EU VAT for digital goods.'],
            ['region_code' => 'JP', 'vat_rate' => 0.10, 'notes' => 'Japan consumption tax.'],
        ];

        foreach ($profiles as $profile) {
            TaxProfile::query()->updateOrCreate(
                ['region_code' => $profile['region_code']],
                TaxProfile::factory()->state([
                    'region_code' => $profile['region_code'],
                    'vat_rate' => $profile['vat_rate'],
                    'effective_from' => Carbon::now()->subYear()->toDateString(),
                    'notes' => $profile['notes'],
                ])->make()->toArray()
            );
        }
    }
}