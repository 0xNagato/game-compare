<?php

namespace App\Filament\Pages;

use App\Models\DatasetSnapshot;
use App\Models\ProviderUsage;
use App\Models\RegionPrice;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

class IngestionDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Ingestion Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Ingestion Dashboard';

    protected string $view = 'filament.pages.ingestion-dashboard';

    public function getViewData(): array
    {
        $summary = $this->snapshotSummary();

        return [
            'statusBreakdown' => $summary['statusBreakdown'],
            'successRate' => $summary['successRate'],
            'latestSnapshot' => $summary['latestSnapshot'],
            'recentSnapshots' => $this->recentSnapshots(),
            'providerUsage' => $this->providerUsage(),
            'throughputSeries' => $this->throughputSeries(),
        ];
    }

    protected function snapshotSummary(): array
    {
        $counts = DatasetSnapshot::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $windowStart = Carbon::now()->subHours(24);

        $recent = DatasetSnapshot::query()
            ->where('created_at', '>=', $windowStart)
            ->get(['id', 'status']);

        $totalRecent = $recent->count();
        $successfulRecent = $recent->where('status', 'succeeded')->count();

        $statusBreakdown = collect(['running', 'succeeded', 'failed', 'pending'])->map(function (string $status) use ($counts) {
            $message = match ($status) {
                'running' => 'Currently executing jobs',
                'succeeded' => 'All-time completions',
                'failed' => 'Needs review',
                default => 'Waiting in queue',
            };

            $color = match ($status) {
                'running' => 'warning',
                'succeeded' => 'success',
                'failed' => 'danger',
                default => 'gray',
            };

            return [
                'status' => $status,
                'label' => Str::of($status)->headline(),
                'count' => (int) $counts->get($status, 0),
                'message' => $message,
                'color' => $color,
            ];
        })->values();

        $successRate = $totalRecent > 0
            ? round(($successfulRecent / $totalRecent) * 100, 1)
            : null;

        $latestSnapshot = DatasetSnapshot::query()
            ->orderByDesc('started_at')
            ->first();

        return [
            'statusBreakdown' => $statusBreakdown,
            'successRate' => $successRate,
            'latestSnapshot' => $this->formatSnapshot($latestSnapshot),
        ];
    }

    protected function recentSnapshots(): Collection
    {
        return DatasetSnapshot::query()
            ->orderByDesc('started_at')
            ->limit(10)
            ->get()
            ->map(fn (DatasetSnapshot $snapshot) => $this->formatSnapshot($snapshot));
    }

    protected function providerUsage(): Collection
    {
        return ProviderUsage::query()
            ->orderBy('provider')
            ->get()
            ->map(function (ProviderUsage $usage) {
                $config = config(sprintf('pricing.providers.%s', $usage->provider));
                $label = $config['label'] ?? Str::headline(str_replace('_', ' ', $usage->provider));
                $rateLimit = $config['rate_limit_per_minute'] ?? null;
                $queue = $config['queue'] ?? null;

                $health = match (true) {
                    $usage->last_called_at === null => 'idle',
                    $usage->last_called_at->lt(Carbon::now()->subHours(2)) => 'stale',
                    default => 'active',
                };

                return [
                    'provider' => $usage->provider,
                    'label' => $label,
                    'queue' => $queue,
                    'total_calls' => (int) $usage->total_calls,
                    'daily_calls' => (int) $usage->daily_calls,
                    'rate_limit_per_minute' => $rateLimit,
                    'last_called_at' => $usage->last_called_at,
                    'last_called_human' => $usage->last_called_at?->diffForHumans(),
                    'daily_window' => $usage->daily_window,
                    'health' => $health,
                ];
            });
    }

    protected function throughputSeries(): array
    {
        $windowStart = Carbon::now()->subHours(23)->startOfHour();
        $buckets = [];

        for ($i = 0; $i < 24; $i++) {
            $bucket = $windowStart->copy()->addHours($i);
            $key = $bucket->format('Y-m-d H:00');

            $buckets[$key] = [
                'bucket' => $bucket,
                'label' => $bucket->format('M d · H:00'),
                'count' => 0,
            ];
        }

        RegionPrice::query()
            ->where('recorded_at', '>=', $windowStart)
            ->chunkById(500, function ($prices) use (&$buckets): void {
                foreach ($prices as $price) {
                    $bucketKey = $price->recorded_at->copy()->startOfHour()->format('Y-m-d H:00');

                    if (isset($buckets[$bucketKey])) {
                        $buckets[$bucketKey]['count']++;
                    }
                }
            });

        $points = array_values($buckets);
        $total = array_sum(array_column($points, 'count'));
        $max = empty($points) ? 0 : max(array_column($points, 'count'));

        return [
            'points' => $points,
            'total' => $total,
            'max' => $max,
            'windowStart' => $windowStart,
        ];
    }

    protected function formatSnapshot(?DatasetSnapshot $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        $startedAt = $snapshot->started_at;
        $finishedAt = $snapshot->finished_at;

        $duration = null;

        if ($startedAt && $finishedAt) {
            $duration = $finishedAt->shortAbsoluteDiffForHumans($startedAt, 2);
        }

        return [
            'id' => $snapshot->id,
            'kind' => $snapshot->kind,
            'provider' => $snapshot->provider,
            'provider_label' => $this->resolveProviderLabel($snapshot->provider),
            'status' => $snapshot->status,
            'row_count' => $snapshot->row_count,
            'context' => $snapshot->context ?? [],
            'error_details' => $snapshot->error_details,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'started_at_human' => $startedAt?->diffForHumans(),
            'finished_at_human' => $finishedAt?->diffForHumans(),
            'duration' => $duration,
        ];
    }

    protected function resolveProviderLabel(?string $provider): ?string
    {
        if ($provider === null) {
            return null;
        }

        $config = config(sprintf('pricing.providers.%s', $provider));

        if (! $config) {
            return Str::headline(str_replace('_', ' ', $provider));
        }

        return $config['label'] ?? Str::headline(str_replace('_', ' ', $provider));
    }
}
