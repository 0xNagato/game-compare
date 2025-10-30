<?php

namespace Database\Seeders;

use App\Models\DatasetSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatasetSnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $snapshots = [
            [
                'kind' => 'price_ingest',
                'provider' => 'nexarda',
                'status' => 'succeeded',
                'row_count' => 24,
                'context' => [
                    'provider_queue' => 'fetch',
                    'products' => 5,
                    'regions' => ['US', 'GB', 'EU'],
                ],
            ],
            [
                'kind' => 'fx_refresh',
                'provider' => 'coingecko',
                'status' => 'succeeded',
                'row_count' => 11,
                'context' => [
                    'pairs' => ['USD/BTC', 'EUR/BTC', 'GBP/BTC'],
                ],
            ],
            [
                'kind' => 'aggregate_build',
                'provider' => 'internal',
                'status' => 'running',
                'row_count' => 0,
                'context' => [
                    'bucket' => 'day',
                ],
            ],
        ];

        foreach ($snapshots as $entry) {
            $start = Carbon::now()->subMinutes(30);
            $finish = in_array($entry['status'], ['succeeded', 'failed'], true)
                ? Carbon::now()->subMinutes(5)
                : null;

            DatasetSnapshot::query()->updateOrCreate(
                ['kind' => $entry['kind'], 'provider' => $entry['provider']],
                DatasetSnapshot::factory()->state([
                    'kind' => $entry['kind'],
                    'provider' => $entry['provider'],
                    'status' => $entry['status'],
                    'row_count' => $entry['row_count'],
                    'context' => $entry['context'],
                    'started_at' => $start,
                    'finished_at' => $finish,
                    'error_details' => null,
                ])->make()->toArray()
            );
        }
    }
}