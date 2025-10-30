@props([
    'title' => 'Browse the freshest global game drops',
    'subtitle' => 'Tap into live price intelligence, compare BTC-adjusted offers, and watch cinematic trailers',
    'backLink' => null,
])

<div class="px-6 lg:px-10 xl:px-12 py-10 sm:py-12 max-w-7xl mx-auto space-y-8">
    <!-- Header Section -->
    <header class="space-y-4">
        @if($backLink)
            <a href="{{ $backLink }}" class="inline-flex items-center gap-2 text-xs uppercase tracking-wide text-white/60 hover:text-amber-300 transition">
                <span aria-hidden="true">←</span> {{ $slot }}
            </a>
        @endif
        
        <div>
            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight text-white">{{ $title }}</h1>
            <p class="mt-3 text-lg text-white/70 max-w-xl">{{ $subtitle }}</p>
        </div>
    </header>
</div>
