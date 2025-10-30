<div class="space-y-6">
    <x-filament::section icon="heroicon-o-bolt" heading="Pipeline Overview">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($statusBreakdown as $stat)
                @php
                    $badgeClasses = match ($stat['color']) {
                        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
                        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                        'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-300',
                    };
                @endphp

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</span>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClasses }}">
                            {{ $stat['message'] }}
                        </span>
                    </div>
                    <div class="mt-4 flex items-end gap-2">
                        <p class="text-3xl font-semibold text-slate-900 dark:text-slate-50">{{ number_format($stat['count']) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            @if ($successRate !== null)
                <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-400/10 dark:text-emerald-300">
                    <x-filament::icon icon="heroicon-o-chart-pie" class="h-4 w-4" />
                    <span>24h success rate: {{ $successRate }}%</span>
                </div>
            @else
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" />
                    <span>Awaiting first completed snapshot.</span>
                </div>
            @endif

            @if ($latestSnapshot)
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                    <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4 text-amber-500" />
                    <span class="font-semibold text-slate-900 dark:text-slate-100">Latest snapshot</span>
                    <span>#{{ $latestSnapshot['id'] }}</span>
                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $latestSnapshot['status'] }}</span>
                    @if ($latestSnapshot['provider_label'])
                        <span class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $latestSnapshot['provider_label'] }}</span>
                    @endif
                    <span>Rows: {{ number_format($latestSnapshot['row_count']) }}</span>
                    @if ($latestSnapshot['duration'])
                        <span>Duration: {{ $latestSnapshot['duration'] }}</span>
                    @endif
                    @if ($latestSnapshot['finished_at_human'])
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $latestSnapshot['finished_at_human'] }}</span>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section icon="heroicon-o-server" heading="Provider Usage" class="lg:col-span-2">
            @if ($providerUsage->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    No providers have been invoked yet. Kick off a pricing ingest run to populate this dashboard.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Provider</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Daily Calls</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Calls</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Queue</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Rate Limit</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Last Call</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Health</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950">
                            @foreach ($providerUsage as $usage)
                                @php
                                    $healthClasses = match ($usage['health']) {
                                        'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
                                        'stale' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $usage['label'] }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $usage['provider'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ number_format($usage['daily_calls']) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ number_format($usage['total_calls']) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $usage['queue'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                        @if ($usage['rate_limit_per_minute'])
                                            {{ $usage['rate_limit_per_minute'] }} / min
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                        @if ($usage['last_called_at'])
                                            <div>{{ $usage['last_called_human'] }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $usage['last_called_at']->toDateTimeString() }}</div>
                                        @else
                                            <span>Never</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $healthClasses }}">
                                            {{ Str::title($usage['health']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-chart-bar" heading="Ingestion Throughput (last 24h)">
            @if ($throughputSeries['total'] === 0)
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    No region price snapshots captured in the last 24 hours.
                </div>
            @else
                <div class="flex items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                    <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4 text-amber-500" />
                    <span>Total snapshots: {{ number_format($throughputSeries['total']) }}</span>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach ($throughputSeries['points'] as $point)
                        @php
                            $max = max($throughputSeries['max'], 1);
                            $percent = $point['count'] > 0 ? max(4, ($point['count'] / $max) * 100) : 0;
                        @endphp
                        <div class="flex items-center gap-3">
                            <span class="w-28 shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $point['label'] }}</span>
                            <div class="relative h-2 flex-1 rounded-full bg-slate-200 dark:bg-slate-800">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-amber-500" style="width: {{ $percent }}%;"></div>
                            </div>
                            <span class="w-10 text-right text-sm font-medium text-slate-700 dark:text-slate-200">{{ $point['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section icon="heroicon-o-queue-list" heading="Recent Snapshots">
        @if ($recentSnapshots->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                No dataset snapshots logged yet.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Snapshot</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Provider</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Rows</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Started</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Finished</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-950">
                        @foreach ($recentSnapshots as $snapshot)
                            <tr>
                                <td class="px-4 py-3 align-top">
                                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">#{{ $snapshot['id'] }} · {{ Str::headline($snapshot['kind']) }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ $snapshot['status'] }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $snapshot['provider_label'] ?? $snapshot['provider'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ number_format($snapshot['row_count']) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($snapshot['started_at'])
                                        <div>{{ $snapshot['started_at']->toDateTimeString() }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $snapshot['started_at_human'] }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($snapshot['finished_at'])
                                        <div>{{ $snapshot['finished_at']->toDateTimeString() }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $snapshot['finished_at_human'] }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $snapshot['duration'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
