<?php

namespace App\Services\Alerts;

use App\Jobs\SendAlertJob;
use App\Models\Alert;
use App\Models\RegionPrice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class PriceDropAnalyzer
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function analyze(array $context = []): void
    {
        $windowMinutes = (int) Arr::get($context, 'window_minutes', 60);
        $threshold = (float) Arr::get($context, 'drop_percentage', 5);
        $since = now()->subMinutes($windowMinutes);

        $prices = RegionPrice::query()
            ->where('recorded_at', '>=', $since)
            ->with('skuRegion.product')
            ->get();

        $alerts = Alert::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(static function (Alert $alert) {
                return sprintf('%d:%s', $alert->product_id, $alert->region_code);
            });

        $prices->groupBy(fn (RegionPrice $price) => $price->skuRegion->product_id)
            ->each(function ($group, $productId) use ($alerts, $threshold): void {
                $groupByRegion = $group->groupBy(fn (RegionPrice $price) => $price->skuRegion->region_code);

                foreach ($groupByRegion as $regionCode => $series) {
                    $sorted = $series->sortBy('recorded_at');
                    $earliest = $sorted->first();
                    $latest = $sorted->last();

                    if (! $latest || ! $earliest) {
                        continue;
                    }

                    $change = $earliest->btc_value > 0
                        ? (($latest->btc_value - $earliest->btc_value) / $earliest->btc_value) * 100
                        : 0;

                    if ($change >= 0 || abs($change) < $threshold) {
                        continue;
                    }

                    $alert = $alerts->get(sprintf('%d:%s', $productId, $regionCode));

                    if (! $alert) {
                        continue;
                    }

                    SendAlertJob::dispatch(
                        alert: $alert,
                        regionPriceId: $latest->id,
                        context: [
                            'change_percentage' => round($change, 2),
                            'earliest_price_id' => $earliest->id,
                            'previous_btc' => (float) $earliest->btc_value,
                            'previous_fiat' => (float) $earliest->fiat_amount,
                            'previous_recorded_at' => $earliest->recorded_at?->toIso8601String(),
                            'latest_fiat' => (float) $latest->fiat_amount,
                            'latest_btc' => (float) $latest->btc_value,
                            'latest_price_id' => $latest->id,
                        ]
                    );
                }
            });

        Log::info('price_drop_analysis.completed', [
            'window_minutes' => $windowMinutes,
            'drop_threshold' => $threshold,
            'price_count' => $prices->count(),
        ]);
    }
}
