<?php

namespace App\Support\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TrendlineCalculator
{
    /**
    * @param  Collection<int, array{0: string|\DateTimeInterface, 1: float|int}>|array<int, array{0: string|\DateTimeInterface, 1: float|int}>  $points
     * @return array{
     *     points: array<int, array{0: string, 1: float}>,
     *     slope_per_day: float,
     *     absolute_change: float,
     *     percent_change: float|null,
     *     direction: string,
     *     count: int,
     *     r_squared: float
     * }
     */
    public static function fromPoints(Collection|array $points): array
    {
        $collection = $points instanceof Collection ? $points : collect($points);

        $filtered = $collection->values();

        $count = $filtered->count();

        if ($count < 2) {
            return self::defaults($filtered);
        }

        /** @var array{0: string|\DateTimeInterface, 1: float|int}|null $first */
        $first = $filtered->first();
        if ($first === null) {
            return self::defaults($filtered);
        }
        $firstDate = $first[0] instanceof \DateTimeInterface
            ? Carbon::instance($first[0])->copy()
            : Carbon::parse((string) $first[0]);
    $baseTimestamp = (int) $firstDate->startOfDay()->timestamp;

        $xs = [];
        $ys = [];
        $labels = [];

        foreach ($filtered as $point) {
            $label = $point[0] instanceof \DateTimeInterface
                ? $point[0]->format('Y-m-d')
                : (string) $point[0];

            $date = Carbon::parse($label)->startOfDay();
            $x = (((int) $date->timestamp) - $baseTimestamp) / 86400;
            $y = (float) $point[1];

            $xs[] = $x;
            $ys[] = $y;
            $labels[] = $label;
        }

        $sumX = array_sum($xs);
        $sumY = array_sum($ys);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $sumXY += $xs[$i] * $ys[$i];
            $sumX2 += $xs[$i] * $xs[$i];
            $sumY2 += $ys[$i] * $ys[$i];
        }

        $denominator = ($count * $sumX2) - ($sumX * $sumX);

        if (abs($denominator) < 1e-9) {
            return self::defaults($filtered);
        }

        $slope = (($count * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $count;
        $meanY = $sumY / $count;

        $ssTot = 0.0;
        $ssRes = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $predicted = $intercept + ($slope * $xs[$i]);
            $ssTot += ($ys[$i] - $meanY) ** 2;
            $ssRes += ($ys[$i] - $predicted) ** 2;
        }

        $startValue = $intercept + ($slope * $xs[0]);
        $endValue = $intercept + ($slope * $xs[$count - 1]);
        $change = $endValue - $startValue;
        $percent = abs($startValue) > 1e-9 ? ($change / abs($startValue)) * 100 : null;
        $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');

        $rSquared = $ssTot > 0 ? max(0, min(1, 1 - ($ssRes / $ssTot))) : 1.0;

        return [
            'points' => [
                [$labels[0], round($startValue, 8)],
                [$labels[$count - 1], round($endValue, 8)],
            ],
            'slope_per_day' => round($slope, 8),
            'absolute_change' => round($change, 8),
            'percent_change' => $percent !== null ? round($percent, 4) : null,
            'direction' => $direction,
            'count' => $count,
            'r_squared' => round($rSquared, 6),
        ];
    }

    /**
     * @param  Collection<int, array{0: string|\DateTimeInterface, 1: float|int}>  $points
     * @return array{
     *     points: array<int, array{0: string, 1: float}>,
     *     slope_per_day: float,
     *     absolute_change: float,
     *     percent_change: float|null,
     *     direction: string,
     *     count: int,
     *     r_squared: float
     * }
     */
    protected static function defaults(Collection $points): array
    {
        if ($points->isEmpty()) {
            $label = now()->toDateString();
            $value = 0.0;
        } else {
            /** @var array{0: string|\DateTimeInterface, 1: float|int} $point */
            $point = $points->first();
            $label = $point[0] instanceof \DateTimeInterface
                ? $point[0]->format('Y-m-d')
                : (string) $point[0];
            $value = (float) $point[1];
        }

        return [
            'points' => [
                [$label, round($value, 8)],
                [$label, round($value, 8)],
            ],
            'slope_per_day' => 0.0,
            'absolute_change' => 0.0,
            'percent_change' => null,
            'direction' => 'flat',
            'count' => $points->count(),
            'r_squared' => 0.0,
        ];
    }
}
