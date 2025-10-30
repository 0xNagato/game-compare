<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-chart-bar" :heading="$productName ?? 'Live Price Series'">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-gray-50">{{ $headline }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subheadline }}</p>
            </div>

            @if ($lastUpdated)
                <span class="ml-auto inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                    Last refreshed {{ $lastUpdated }}
                </span>
            @endif
        </div>

        <div class="mt-6">
            @if ($chart)
                {!! $chart->container() !!}
            @else
                <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Aggregated price series have not been generated yet. Dispatch <code>BuildAggregatesJob</code> or seed demo data to bring this chart to life.
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

@once
    @if ($chart)
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
        @endpush
    @endif
@endonce

@if ($chart)
    @push('scripts')
        {!! $chart->script() !!}
    @endpush
@endif
