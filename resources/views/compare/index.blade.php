@extends('layouts.app')

@section('head')
  <style>
    #compareBackdrop {
      position: relative;
    }
    #compareBackdrop::before,
    #compareBackdrop::after {
      content: '';
      position: absolute;
      inset: -15% auto auto -25%;
      width: clamp(240px, 35vw, 520px);
      aspect-ratio: 1;
      border-radius: 9999px;
      background: radial-gradient(circle at center, hsl(var(--p)) 0%, transparent 70%);
      filter: blur(110px);
      opacity: 0.45;
      z-index: 0;
      pointer-events: none;
      transform: translate3d(0, 0, 0);
    }
    #compareBackdrop::after {
      inset: auto -20% -20% auto;
      width: clamp(260px, 38vw, 560px);
      background: radial-gradient(circle at center, hsl(var(--s)) 0%, transparent 65%);
      opacity: 0.35;
    }
    .compare-hero {
      position: relative;
      overflow: hidden;
    }
    .compare-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(120% 140% at 90% 0%, rgba(255, 255, 255, 0.05) 0%, transparent 55%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.08), transparent 68%);
      pointer-events: none;
      mix-blend-mode: screen;
    }
    .glimmer-border {
      border: 1px solid rgba(var(--bc-rgb), 0.15);
      backdrop-filter: blur(16px);
      background-color: rgba(var(--b3-rgb), 0.72);
    }
    .stat-grid {
      display: grid;
      gap: 1.25rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .stat-card {
      position: relative;
      overflow: hidden;
      border-radius: 1.25rem;
      padding: 1.75rem;
      border: 1px solid rgba(var(--bc-rgb), 0.12);
      background: linear-gradient(145deg, rgba(var(--b1-rgb), 0.82), rgba(var(--b2-rgb), 0.68));
      box-shadow: 0 18px 45px -25px rgba(0, 0, 0, 0.55);
      transition: transform 200ms ease, border-color 200ms ease;
    }
    .stat-card:hover {
      transform: translateY(-6px);
      border-color: rgba(var(--p-rgb), 0.35);
    }
    .stat-card::after {
      content: '';
      position: absolute;
      inset: -40% 60% auto -30%;
      height: 160%;
      background: radial-gradient(circle at center, rgba(var(--p-rgb), 0.25), transparent 60%);
      opacity: 0.6;
      pointer-events: none;
    }
    .compare-table thead th { position: sticky; top: 0; z-index: 30; backdrop-filter: blur(16px); }
    .compare-table tbody tr:nth-child(odd) { background-color: transparent; }
    .glass-panel {
      border: 1px solid rgba(var(--bc-rgb), 0.12);
      background-color: rgba(var(--b2-rgb), 0.62);
      backdrop-filter: blur(14px);
      border-radius: 1.5rem;
      box-shadow: 0 22px 60px -35px rgba(0, 0, 0, 0.6);
    }
    .view-toggle-group {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
    }
    .lookbook-wrapper {
      position: relative;
      overflow-x: auto;
      display: grid;
      grid-auto-flow: column;
      gap: 1.5rem;
      padding: 1.75rem 0 1.25rem;
      scroll-snap-type: x mandatory;
      mask-image: linear-gradient(90deg, transparent 0%, rgba(0, 0, 0, 0.351) 6%, black 94%, transparent 100%);
    }
    .lookbook-wrapper::-webkit-scrollbar {
      display: none;
    }
    .lookbook-card {
      scroll-snap-align: start;
      min-width: clamp(280px, 68vw, 420px);
      border-radius: 1.75rem;
      border: 1px solid rgba(var(--bc-rgb), 0.16);
      overflow: hidden;
      background: linear-gradient(160deg, rgba(34, 36, 45, 0.85), rgba(18, 20, 27, 0.92));
      box-shadow: 0 45px 90px -50px rgba(0, 0, 0, 0.85);
      display: flex;
      flex-direction: column;
    }
    .lookbook-card img {
      width: 100%;
      height: clamp(200px, 40vh, 320px);
      object-fit: cover;
      filter: saturate(1.08) contrast(1.05);
    }
    .lookbook-card-details {
      padding: 1.75rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .lookbook-card-details .badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    .lookbook-card-details p {
      color: rgba(255, 255, 255, 0.72);
    }
    .lookbook-card-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.75rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.5);
    }
    .tools-overlay {
      position: fixed;
      inset: 0;
      background: rgba(12, 16, 22, 0.55);
      backdrop-filter: blur(4px);
      opacity: 0;
      visibility: hidden;
      transition: opacity 200ms ease;
      z-index: 30;
    }
    .tools-overlay.open {
      opacity: 1;
      visibility: visible;
    }
    .tools-drawer {
      position: fixed;
      top: 0;
      right: 0;
      height: 100vh;
      width: min(92vw, 360px);
      transform: translateX(100%);
      transition: transform 250ms ease;
      z-index: 40;
      padding: 2rem 2rem 3rem;
      background-color: rgba(12, 15, 20, 0.92);
      backdrop-filter: blur(16px);
      display: flex;
      flex-direction: column;
      gap: 2rem;
    }
    .tools-drawer.open {
      transform: translateX(0);
    }
    .top-control-bar {
      position: absolute;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 35;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
    }
    .top-control-bar .btn,
    .top-control-bar .join > * {
      backdrop-filter: blur(6px);
    }
    body.tools-drawer-open {
      overflow: hidden;
    }
    @media (min-width: 1024px) {
      .tools-overlay { display: none !important; }
      .tools-drawer {
        position: static;
        transform: none !important;
        height: auto;
        width: auto;
        padding: 0;
        background-color: transparent;
        backdrop-filter: none;
        gap: 0;
      }
      .top-control-bar {
        top: 2rem;
        right: 2rem;
      }
    }
  </style>
@endsection

@section('content')
  @php
    $prioritizedMatches = collect($crossReferenceMatches)
      ->sortByDesc(fn ($match) => !empty($match['image']))
      ->values()
      ->all();

    $normalize = static function (?string $value): ?string {
      if (! is_string($value) || trim($value) === '') {
        return null;
      }

      $clean = preg_replace('/(\[[^\]]*\]|\([^)]*\))/u', ' ', $value) ?? $value;

      $normalized = \Illuminate\Support\Str::of($clean)
        ->ascii()
        ->lower()
        ->replaceMatches('/[^a-z0-9]+/u', ' ')
        ->squish()
        ->value();

      return $normalized !== '' ? $normalized : null;
    };

    $initialNormalized = $normalize($initialProduct['slug'] ?? null)
      ?? $normalize($initialProduct['name'] ?? null);

    $matchingMatchWithImage = null;
    if ($initialNormalized) {
      $matchingMatchWithImage = collect($prioritizedMatches)
        ->first(function ($match) use ($initialNormalized, $normalize) {
          if (empty($match['image'])) {
            return false;
          }

          if (! empty($match['normalized'])) {
            return $match['normalized'] === $initialNormalized;
          }

          $normalizedName = $normalize($match['name'] ?? null);

          return $normalizedName !== null && $normalizedName === $initialNormalized;
        });
    }

    $heroImage = $initialProduct['image'] ?? ($matchingMatchWithImage['image'] ?? null);
    $heroImageSourceName = $initialProduct['name'] ?? ($matchingMatchWithImage['name'] ?? 'Featured game');
    $heroImageIsPlaceholder = $heroImage === null;

    if ($heroImageIsPlaceholder) {
      $heroImage = asset('images/placeholders/game-cover.svg');
      $heroImageAlt = trim($heroImageSourceName ? $heroImageSourceName.' placeholder cover art' : 'Game cover art placeholder');
    } else {
      $heroImageAlt = trim($heroImageSourceName ? $heroImageSourceName.' cover art' : 'Game cover art');
    }
  @endphp
  <div id="compareBackdrop" class="relative">
    <div class="relative z-10 max-w-[1400px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-12">
      <div id="toolsOverlay" class="tools-overlay lg:hidden" aria-hidden="true"></div>
      <div class="grid grid-cols-12 gap-x-10 gap-y-12">
        <section class="col-span-12 compare-hero rounded-3xl border border-base-content/10 bg-neutral-900 shadow-[0_45px_90px_-45px_rgba(16,18,23,0.65)]">
          <div class="top-control-bar">
            <button id="toolsToggle" type="button" class="btn btn-sm btn-primary btn-circle" aria-label="Open quick tools">
              <span class="text-lg">☰</span>
            </button>
            <div id="viewToggleGroup" class="join">
              <button type="button" class="btn btn-sm join-item btn-ghost" data-view="list">Editorial list</button>
              <button type="button" class="btn btn-sm join-item btn-primary btn-active" data-view="carousel">Lookbook carousel</button>
            </div>
          </div>
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr),minmax(280px,330px)] items-stretch">
          <div class="p-10 lg:p-12 space-y-6">
            <div class="flex items-center gap-3 text-xs uppercase tracking-[0.32em] text-base-content/60">
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-base-content/10 bg-base-100/80">⚡️</span>
              <span>Live portfolio playground</span>
            </div>
            <div class="space-y-4">
              <a href="{{ route('home') }}" class="btn btn-ghost btn-xs w-fit uppercase tracking-wide border border-base-content/20">← Showcase</a>
              <h1 class="text-4xl sm:text-5xl font-black leading-tight">
                Compare global price stories with cinematic clarity.
              </h1>
              <p class="text-base-content/70 text-lg max-w-3xl">
                We braid Nexarda digital storefronts, collector-grade physical guides, and our BTC-normalised aggregates so you can surface cross-region spreads in a single glance. Every dataset is cached locally, primed for smooth exploration.
              </p>
            </div>
            <div class="grid gap-6 sm:grid-cols-[auto,1fr]">
              <div class="flex flex-col items-center gap-2">
                <div class="avatar">
                  <div class="mask mask-squircle w-28 h-28 shadow-2xl">
                    <img src="{{ $heroImage }}" alt="{{ $heroImageAlt }}">
                  </div>
                </div>
                @if($heroImageIsPlaceholder)
                  <span class="badge badge-ghost badge-sm uppercase tracking-wide text-xs">Cover art pending</span>
                @endif
              </div>
              <div class="space-y-3">
                <h2 class="text-2xl font-semibold">{{ $initialProduct['name'] }}</h2>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                  <span class="badge badge-outline badge-primary">{{ $initialProduct['platform'] ?? 'Multi-platform' }}</span>
                  @if(! empty($initialProduct['category']))
                    <span class="badge badge-outline badge-secondary">{{ $initialProduct['category'] }}</span>
                  @endif
                  <span class="badge badge-outline badge-accent">{{ count($initialProduct['region_codes'] ?? []) }} regions observed</span>
                </div>
                @if(($initialProduct['price_summary']['avg_btc'] ?? null) !== null)
                  <dl class="grid gap-3 text-sm text-base-content/70 sm:grid-cols-2">
                    <div class="rounded-xl bg-base-100/60 px-4 py-3 border border-base-content/10">
                      <dt class="uppercase tracking-wide text-xs text-base-content/50">Avg price</dt>
                      <dd class="text-base font-semibold text-success/90">{{ number_format((float) $initialProduct['price_summary']['avg_btc'], 6) }} BTC</dd>
                    </div>
                    <div class="rounded-xl bg-base-100/60 px-4 py-3 border border-base-content/10">
                      <dt class="uppercase tracking-wide text-xs text-base-content/50">Top region</dt>
                      <dd class="text-base font-semibold">{{ $initialProduct['price_summary']['best_region'] }}</dd>
                      <span class="text-xs text-base-content/50">Samples · {{ number_format((int) $initialProduct['price_summary']['sample_count']) }}</span>
                    </div>
                  </dl>
                @else
                  <p class="text-sm text-base-content/60">Aggregation is still warming. Jump to another spotlight entry to see BTC spreads instantly.</p>
                @endif
              </div>
            </div>
          </div>

          <aside id="toolsPanel" class="tools-drawer" aria-hidden="true">
            <div class="glass-panel h-full space-y-6 p-8 lg:p-10">
              <div class="flex items-center justify-between lg:hidden">
                <h3 class="text-lg font-semibold tracking-wide uppercase text-base-content/70">Quick tools</h3>
                <button id="toolsClose" type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="Close quick tools">
                  <span class="text-xl">×</span>
                </button>
              </div>
              <h3 class="hidden lg:block text-lg font-semibold tracking-wide uppercase text-base-content/70">Quick tools</h3>
              <div class="form-control">
                <label class="label">
                  <span class="label-text">Default comparison region</span>
                </label>
                <select class="select select-bordered select-sm" id="regionPicker">
                  @foreach($regionOptions as $region)
                    <option value="{{ $region }}">{{ $region }}</option>
                  @endforeach
                </select>
                <span class="label-text-alt mt-2">Prefills microsite charts so demos stay consistent.</span>
              </div>
              <div class="form-control">
                <label class="label">
                  <span class="label-text">Jump to spotlight title</span>
                </label>
                <input type="search" id="heroSearchInput" class="input input-bordered input-sm" placeholder="Search spotlight titles" list="heroSpotlightList">
                <datalist id="heroSpotlightList">
                  @foreach($spotlight as $product)
                    <option value="{{ $product['name'] }}"></option>
                  @endforeach
                </datalist>
                <span class="label-text-alt mt-2">Great for filming walkthroughs or routing teammates.</span>
              </div>
            </div>
          </aside>
        </div>
      </section>

  <section aria-label="Cross reference stats" class="col-span-12 space-y-6">
        <div class="stat-grid">
          <article class="stat-card">
            <div class="flex items-center gap-4">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/20 text-primary text-2xl">🎮</span>
              <div>
                <p class="text-sm uppercase tracking-widest text-base-content/60">Matched titles</p>
                <p class="text-3xl font-bold" id="statTotal">{{ number_format($crossReferenceStats['total']) }}</p>
                <p class="text-sm text-base-content/60">Giant Bomb ↔ Nexarda ↔ Price Guide</p>
              </div>
            </div>
          </article>
          <article class="stat-card">
            <div class="flex items-center gap-4">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-success/20 text-success text-2xl">☁️</span>
              <div>
                <p class="text-sm uppercase tracking-widest text-base-content/60">Digital ready</p>
                <p class="text-3xl font-bold" id="statDigital">{{ number_format($crossReferenceStats['digital']) }}</p>
                <p class="text-sm text-base-content/60">Nexarda currencies indexed</p>
              </div>
            </div>
          </article>
          <article class="stat-card">
            <div class="flex items-center gap-4">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-info/20 text-info text-2xl">📦</span>
              <div>
                <p class="text-sm uppercase tracking-widest text-base-content/60">Physical ready</p>
                <p class="text-3xl font-bold" id="statPhysical">{{ number_format($crossReferenceStats['physical']) }}</p>
                <p class="text-sm text-base-content/60">Price Guide touch points</p>
              </div>
            </div>
          </article>
          <article class="stat-card">
            <div class="flex items-center gap-4">
              <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-warning/20 text-warning text-2xl">🔗</span>
              <div>
                <p class="text-sm uppercase tracking-widest text-base-content/60">Dual coverage</p>
                <p class="text-3xl font-bold" id="statBoth">{{ number_format($crossReferenceStats['both']) }}</p>
                <p class="text-sm text-base-content/60">Bridging digital + physical</p>
              </div>
            </div>
          </article>
        </div>
      </section>

        <section aria-label="Cross reference explorer" class="col-span-12 space-y-8">
          <div class="glass-panel p-8 space-y-6">
          <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="w-full xl:max-w-2xl">
              <label class="label px-0">
                <span class="label-text text-sm font-semibold uppercase tracking-wide">Search catalogue</span>
              </label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-base-content/40">🔍</span>
                <input type="search" id="crossRefSearch" class="input input-bordered pl-10 w-full" placeholder="Search by title, slug, console, or retailer">
              </div>
            </div>
            <div class="flex flex-wrap gap-4 items-start">
              <label class="form-control w-48">
                <span class="label">
                  <span class="label-text text-xs uppercase tracking-wide">Platform</span>
                </span>
                <select id="platformFilter" class="select select-bordered select-sm">
                  <option value="">All</option>
                  @foreach($crossReferencePlatforms as $platform)
                    <option value="{{ $platform }}">{{ $platform }}</option>
                  @endforeach
                </select>
              </label>
              <label class="form-control w-40">
                <span class="label">
                  <span class="label-text text-xs uppercase tracking-wide">Currency</span>
                </span>
                <select id="currencyFilter" class="select select-bordered select-sm">
                  <option value="">All</option>
                  @foreach($crossReferenceCurrencies as $currency)
                    <option value="{{ $currency }}">{{ $currency }}</option>
                  @endforeach
                </select>
              </label>
              <div class="flex flex-col gap-2" id="availabilityButtons">
                <span class="label-text text-xs uppercase tracking-wide">Availability</span>
                <div class="join">
                  <button class="btn btn-xs join-item btn-primary" data-filter="all">All</button>
                  <button class="btn btn-xs join-item btn-ghost" data-filter="digital">Digital</button>
                  <button class="btn btn-xs join-item btn-ghost" data-filter="physical">Physical</button>
                  <button class="btn btn-xs join-item btn-ghost" data-filter="both">Both</button>
                </div>
              </div>
            </div>
          </div>
          @if(($crossReferenceStats['displayed'] ?? 0) < ($crossReferenceStats['total'] ?? 0))
            <div class="alert alert-info text-sm" role="status">
              <div>
                Showing the first {{ number_format($crossReferenceStats['displayed']) }} of {{ number_format($crossReferenceStats['total']) }} matched titles. Increase CROSS_REFERENCE_FRONTEND_LIMIT to raise the initial load window.
              </div>
            </div>
          @endif
        </div>

  <div class="rounded-3xl border border-base-content/10 bg-base-100/95 shadow-[0_35px_80px_-40px_rgba(13,18,28,0.7)]">
          <div id="listWrapper">
            <div class="overflow-x-auto">
              <table class="table table-zebra compare-table" id="crossRefTable">
                <thead class="bg-base-200/70">
                  <tr>
                    <th class="text-xs uppercase tracking-wide">Title</th>
                    <th class="text-xs uppercase tracking-wide">Digital offers</th>
                    <th class="text-xs uppercase tracking-wide">Physical guide</th>
                  </tr>
                </thead>
                <tbody id="crossRefTableBody">
                  @foreach($prioritizedMatches as $match)
                    <tr data-name="{{ strtolower($match['name']) }}">
                      <td>
                        <div class="flex items-start gap-4">
                          <div class="avatar hidden sm:inline-flex">
                            <div class="mask mask-squircle w-20 h-20 shadow-md">
                              <img src="{{ $match['image'] ?? asset('images/placeholders/game-cover.svg') }}" alt="{{ $match['name'] }} artwork">
                            </div>
                          </div>
                          <div class="space-y-1">
                            <div class="font-semibold text-base-content text-lg">{{ $match['name'] }}</div>
                            <div class="flex flex-wrap gap-2 text-xs">
                              @foreach($match['platforms'] ?? [] as $platform)
                                <span class="badge badge-ghost badge-sm">{{ $platform }}</span>
                              @endforeach
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs text-base-content/60">
                              @if($match['has_digital'])
                                <span class="badge badge-success badge-outline badge-xs">Digital</span>
                              @endif
                              @if($match['has_physical'])
                                <span class="badge badge-info badge-outline badge-xs">Physical</span>
                              @endif
                            </div>
                          </div>
                        </div>
                      </td>
                      <td>
                        @if(!empty($match['digital']))
                          <div class="space-y-2">
                            <div class="flex flex-wrap gap-2">
                              @foreach(($match['digital']['currencies'] ?? []) as $currency)
                                <span class="badge badge-outline @if($match['digital']['best'] && $currency['code'] === ($match['digital']['best']['code'] ?? null) && $currency['amount'] === ($match['digital']['best']['amount'] ?? null)) badge-warning @endif">
                                  {{ $currency['code'] }} · {{ $currency['formatted'] ?? number_format((float) $currency['amount'], 2) }}
                                  @if(($currency['discount'] ?? null) !== null)
                                    <span class="ml-1 text-xs">-{{ $currency['discount'] }}%</span>
                                  @endif
                                </span>
                              @endforeach
                            </div>
                            @php
                              $digitalLink = $match['digital']['url'] ?? (isset($match['digital']['slug']) ? 'https://nexarda.com/game/'.ltrim($match['digital']['slug'], '/') : null);
                            @endphp
                            @if($digitalLink)
                              <a href="{{ $digitalLink }}" target="_blank" rel="noreferrer" class="link link-primary text-xs">View on Nexarda</a>
                            @endif
                          </div>
                        @else
                          <span class="badge badge-ghost">No digital pricing yet</span>
                        @endif
                      </td>
                      <td>
                        @if(!empty($match['physical']))
                          <div class="flex flex-wrap gap-2">
                            @foreach($match['physical'] as $physical)
                              <span class="badge badge-outline">
                                {{ $physical['console'] ?? 'Console' }} · {{ $physical['formatted_price'] ?? '—' }}
                              </span>
                            @endforeach
                          </div>
                          @if($match['best_physical'])
                            <div class="text-xs text-base-content/60 mt-2">Lowest · {{ $match['best_physical']['console'] ?? 'Unknown' }} @ {{ $match['best_physical']['formatted_price'] ?? '$—' }}</div>
                          @endif
                        @else
                          <span class="badge badge-ghost">No physical pricing yet</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
          <div id="carouselWrapper" class="hidden px-6">
            <div class="lookbook-wrapper" id="crossRefCarousel" aria-live="polite" role="list"></div>
          </div>
          <div class="p-6" id="crossRefEmptyState" hidden>
            <div class="alert alert-warning">
              <span>No results match the current filters. Try widening your search.</span>
            </div>
          </div>
          <div class="px-6 pb-6 text-sm text-base-content/70" id="crossRefFooterSummary"></div>
        </div>
      </section>
    </div>
  </div>
@endsection
@php
  $compareBootstrap = [
    'initialProduct' => $initialProduct,
    'regionOptions' => $regionOptions,
    'spotlight' => $spotlight,
  'crossReferenceMatches' => $prioritizedMatches,
    'crossReferenceStats' => $crossReferenceStats,
    'crossReferencePlatforms' => $crossReferencePlatforms,
    'crossReferenceCurrencies' => $crossReferenceCurrencies,
  ];
@endphp

@push('scripts')
<script>
  const compareBootstrap = @json($compareBootstrap);

  const allMatches = Array.isArray(compareBootstrap.crossReferenceMatches) ? compareBootstrap.crossReferenceMatches : [];
  const tableBody = document.getElementById('crossRefTableBody');
  const emptyState = document.getElementById('crossRefEmptyState');
  const toolsPanel = document.getElementById('toolsPanel');
  const toolsOverlay = document.getElementById('toolsOverlay');
  const toolsToggle = document.getElementById('toolsToggle');
  const toolsClose = document.getElementById('toolsClose');
  const searchInput = document.getElementById('crossRefSearch');
  const platformFilter = document.getElementById('platformFilter');
  const currencyFilter = document.getElementById('currencyFilter');
  const availabilityButtons = document.getElementById('availabilityButtons');
  const footerSummary = document.getElementById('crossRefFooterSummary');
  const statTotal = document.getElementById('statTotal');
  const statDigital = document.getElementById('statDigital');
  const statPhysical = document.getElementById('statPhysical');
  const statBoth = document.getElementById('statBoth');
  const placeholderImage = @json(asset('images/placeholders/game-cover.svg'));
  const listWrapper = document.getElementById('listWrapper');
  const carouselWrapper = document.getElementById('carouselWrapper');
  const carouselTrack = document.getElementById('crossRefCarousel');
  const viewToggleGroup = document.getElementById('viewToggleGroup');

  const toolsState = {
    open: false,
  };

  function setToolsState(open) {
    toolsState.open = !!open;
    toolsPanel?.classList.toggle('open', toolsState.open);
    toolsOverlay?.classList.toggle('open', toolsState.open);
    document.body.classList.toggle('tools-drawer-open', toolsState.open);
    if (toolsPanel) {
      const hide = !toolsState.open && window.matchMedia('(max-width: 1023px)').matches;
      toolsPanel.setAttribute('aria-hidden', hide ? 'true' : 'false');
    }
    if (toolsOverlay) {
      toolsOverlay.setAttribute('aria-hidden', toolsState.open ? 'false' : 'true');
    }
  }

  function openTools() {
    if (toolsState.open) {
      return;
    }
    setToolsState(true);
  }

  function closeTools() {
    if (!toolsState.open) {
      return;
    }
    setToolsState(false);
  }

  toolsToggle?.addEventListener('click', () => {
    if (toolsState.open) {
      closeTools();
    } else {
      openTools();
    }
  });

  toolsOverlay?.addEventListener('click', closeTools);
  toolsClose?.addEventListener('click', closeTools);

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeTools();
    }
  });

  window.matchMedia('(min-width: 1024px)').addEventListener('change', () => {
    setToolsState(false);
  });

  setToolsState(false);

  const state = {
    query: '',
    platform: '',
    currency: '',
    availability: 'all',
    view: 'carousel',
  };

  function setView(view) {
    const next = view === 'carousel' ? 'carousel' : 'list';
    state.view = next;

    listWrapper?.classList.toggle('hidden', state.view !== 'list');
    carouselWrapper?.classList.toggle('hidden', state.view !== 'carousel');

    if (viewToggleGroup) {
      viewToggleGroup.querySelectorAll('button[data-view]').forEach((button) => {
        const isActive = button.dataset.view === state.view;
        button.classList.toggle('btn-primary', isActive);
        button.classList.toggle('btn-ghost', !isActive);
        button.classList.toggle('btn-active', isActive);
      });
    }
  }

  viewToggleGroup?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-view]');
    if (!button) {
      return;
    }

    setView(button.dataset.view);
    applyFilters();
  });

  function normalize(text) {
    return (text || '').toString().toLowerCase();
  }

  function matchesAvailability(row) {
    switch (state.availability) {
      case 'digital':
        return !!row.has_digital && !row.has_physical;
      case 'physical':
        return !!row.has_physical && !row.has_digital;
      case 'both':
        return !!row.has_digital && !!row.has_physical;
      default:
        return true;
    }
  }

  function matchesPlatform(row) {
    if (!state.platform) {
      return true;
    }

    return Array.isArray(row.platforms) && row.platforms.some((platform) => normalize(platform) === normalize(state.platform));
  }

  function matchesCurrency(row) {
    if (!state.currency) {
      return true;
    }

    const currencies = Array.isArray(row?.digital?.currencies) ? row.digital.currencies : [];
    return currencies.some((currency) => normalize(currency.code) === normalize(state.currency));
  }

  function matchesQuery(row) {
    if (!state.query) {
      return true;
    }

    const q = normalize(state.query);
    if (normalize(row.name).includes(q)) {
      return true;
    }

    if (normalize(row.normalized).includes(q)) {
      return true;
    }

    const platforms = (row.platforms || []).map(normalize);
    if (platforms.some((platform) => platform.includes(q))) {
      return true;
    }

    if (normalize(row?.digital?.slug).includes(q)) {
      return true;
    }

    if (normalize(row?.digital?.name).includes(q)) {
      return true;
    }

    return false;
  }

  function renderDigitalCell(row) {
    if (!row.digital) {
      return '<span class="badge badge-ghost">No digital pricing yet</span>';
    }

    const currencies = Array.isArray(row.digital.currencies) ? row.digital.currencies : [];
    const best = row.digital.best || null;
    const items = currencies.map((currency) => {
      const highlight = best && currency.code === best.code && currency.amount === best.amount;
      const discount = currency.discount !== null && currency.discount !== undefined ? `<span class="ml-1 text-xs">-${currency.discount}%</span>` : '';
      const price = currency.formatted || Number(currency.amount || 0).toFixed(2);
      return `<span class="badge badge-outline ${highlight ? 'badge-warning' : ''}">${currency.code} · ${price}${discount}</span>`;
    }).join('');

    const linkHref = row?.digital?.url
      || (row?.digital?.slug ? `https://nexarda.com/game/${row.digital.slug.replace(/^\//, '')}` : null);
    const link = linkHref ? `<a href="${linkHref}" target="_blank" rel="noreferrer" class="link link-primary text-xs mt-2 inline-flex">View on Nexarda</a>` : '';

    return `<div class="space-y-2"><div class="flex flex-wrap gap-2">${items}</div>${link}</div>`;
  }

  function renderPhysicalCell(row) {
    if (!Array.isArray(row.physical) || !row.physical.length) {
      return '<span class="badge badge-ghost">No physical pricing yet</span>';
    }

    const badges = row.physical.map((entry) => {
      const consoleName = entry.console || 'Console';
      const price = entry.formatted_price || '—';
      return `<span class="badge badge-outline">${consoleName} · ${price}</span>`;
    }).join('');

    const best = row.best_physical;
    const bestLine = best ? `<div class="text-xs text-base-content/60 mt-2">Lowest · ${(best.console || 'Unknown')} @ ${(best.formatted_price || '$—')}</div>` : '';

    return `<div class="flex flex-col"> <div class="flex flex-wrap gap-2">${badges}</div>${bestLine}</div>`;
  }

  function renderRows(rows) {
    tableBody.innerHTML = rows.map((row) => {
      const image = row.image || placeholderImage;
      const platforms = Array.isArray(row.platforms) ? row.platforms : [];
      const platformBadges = platforms.map((platform) => `<span class="badge badge-ghost badge-sm">${platform}</span>`).join('');
      const availability = [
        row.has_digital ? '<span class="badge badge-success badge-outline badge-xs">Digital</span>' : '',
        row.has_physical ? '<span class="badge badge-info badge-outline badge-xs">Physical</span>' : '',
      ].filter(Boolean).join(' ');

      return `
        <tr>
          <td>
            <div class="flex items-start gap-4">
              <div class="avatar hidden sm:inline-flex">
                <div class="mask mask-squircle w-20 h-20"><img src="${image}" alt="${row.name} artwork"></div>
              </div>
              <div class="space-y-1">
                <div class="font-semibold text-base-content">${row.name}</div>
                <div class="flex flex-wrap gap-2 text-xs">${platformBadges}</div>
                <div class="flex flex-wrap gap-2 text-xs text-base-content/60">${availability}</div>
              </div>
            </div>
          </td>
          <td>${renderDigitalCell(row)}</td>
          <td>${renderPhysicalCell(row)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderCarousel(rows) {
    if (!carouselTrack) {
      return;
    }

    if (!rows.length) {
      carouselTrack.innerHTML = '';
      return;
    }

    const cards = rows.map((row) => {
      const image = row.image || placeholderImage;
      const platforms = Array.isArray(row.platforms) ? row.platforms : [];
      const primaryPlatform = platforms[0] || 'Multi-platform';
      const currencies = Array.isArray(row?.digital?.currencies) ? row.digital.currencies : [];
      const bestDigital = row?.digital?.best || null;
      const highlightedDigital = bestDigital ? currencies.find((currency) => currency.code === bestDigital.code && currency.amount === bestDigital.amount) : null;
      const digitalPrice = highlightedDigital
        ? `${highlightedDigital.formatted || Number(highlightedDigital.amount || 0).toFixed(2)} ${highlightedDigital.code}`
        : null;
      const physicalBest = row?.best_physical || null;
      const physicalPrice = physicalBest?.formatted_price || null;
      const availability = [
        row.has_digital ? '<span class="badge badge-success badge-outline badge-sm">Digital</span>' : '',
        row.has_physical ? '<span class="badge badge-info badge-outline badge-sm">Physical</span>' : '',
      ].filter(Boolean).join('');
      const digitalLink = row?.digital?.url
        || (row?.digital?.slug ? `https://nexarda.com/game/${row.digital.slug.replace(/^\//, '')}` : null);
      const currencyCopy = currencies.length ? `${currencies.length} currency${currencies.length === 1 ? '' : 's'}` : 'Curated spread';

      const blurbs = [];
      if (digitalPrice) {
        blurbs.push(`Digital from ${digitalPrice}`);
      }
      if (physicalPrice) {
        const consoleName = physicalBest?.console || 'Collector';
        blurbs.push(`Physical ${consoleName} ${physicalPrice}`);
      }

      const priceLine = blurbs.join(' · ');
      const action = digitalLink ? `<a href="${digitalLink}" target="_blank" rel="noreferrer" class="btn btn-sm btn-primary btn-outline">Shop digital</a>` : '';

      return `
        <article class="lookbook-card" role="listitem">
          <img src="${image}" alt="${row.name} cover art">
          <div class="lookbook-card-details">
            <div class="lookbook-card-meta">
              <span>${primaryPlatform}</span>
              <span>${currencyCopy}</span>
            </div>
            <h3 class="text-2xl font-semibold tracking-tight text-white">${row.name}</h3>
            <div class="badges">${availability || '<span class="badge badge-ghost badge-sm">Preview</span>'}</div>
            ${priceLine ? `<p class="text-sm leading-relaxed">${priceLine}</p>` : ''}
            ${action ? `<div class="flex items-center gap-3">${action}</div>` : ''}
          </div>
        </article>
      `;
    }).join('');

    carouselTrack.innerHTML = cards;
  }

  function updateStats(rows) {
    const statsBaseline = compareBootstrap.crossReferenceStats || {};
    const totalAvailable = Number.isFinite(statsBaseline.total) ? statsBaseline.total : null;
    const loadedCount = Number.isFinite(statsBaseline.displayed) ? statsBaseline.displayed : allMatches.length;
    const displayLimit = Number.isFinite(statsBaseline.display_limit) ? statsBaseline.display_limit : loadedCount;

    const total = rows.length;
    const digital = rows.filter((row) => row.has_digital).length;
    const physical = rows.filter((row) => row.has_physical).length;
    const both = rows.filter((row) => row.has_digital && row.has_physical).length;

    if (statTotal) statTotal.textContent = total.toLocaleString();
    if (statDigital) statDigital.textContent = digital.toLocaleString();
    if (statPhysical) statPhysical.textContent = physical.toLocaleString();
    if (statBoth) statBoth.textContent = both.toLocaleString();

    if (footerSummary) {
      const summaryPrefix = totalAvailable && totalAvailable > loadedCount
        ? `Showing ${total.toLocaleString()} of ${loadedCount.toLocaleString()} loaded matches (limit ${displayLimit.toLocaleString()}). Total catalogue matches: ${totalAvailable.toLocaleString()}.`
        : `Showing ${total.toLocaleString()} matches.`;

      footerSummary.textContent = `${summaryPrefix} Digital: ${digital.toLocaleString()} · Physical: ${physical.toLocaleString()} · Both: ${both.toLocaleString()}.`;
    }
  }

  function applyFilters() {
    const filtered = allMatches.filter((row) => matchesAvailability(row) && matchesPlatform(row) && matchesCurrency(row) && matchesQuery(row));

    filtered.sort((a, b) => {
      const aHasImage = a?.image ? 1 : 0;
      const bHasImage = b?.image ? 1 : 0;
      if (bHasImage !== aHasImage) {
        return bHasImage - aHasImage;
      }
      return normalize(a?.name).localeCompare(normalize(b?.name));
    });

    if (!filtered.length) {
      tableBody.innerHTML = '';
      if (carouselTrack) {
        carouselTrack.innerHTML = '';
      }
      emptyState.hidden = false;
      updateStats(filtered);
      setView(state.view);
      return;
    }

    emptyState.hidden = true;
    renderRows(filtered);
    renderCarousel(filtered);
    updateStats(filtered);
    setView(state.view);
  }

  function setActiveAvailability(target) {
    Array.from(availabilityButtons.querySelectorAll('button')).forEach((button) => {
      button.classList.toggle('btn-active', button === target);
    });
  }

  searchInput?.addEventListener('input', (event) => {
    state.query = event.target.value;
    applyFilters();
  });

  platformFilter?.addEventListener('change', (event) => {
    state.platform = event.target.value;
    applyFilters();
  });

  currencyFilter?.addEventListener('change', (event) => {
    state.currency = event.target.value;
    applyFilters();
  });

  availabilityButtons?.addEventListener('click', (event) => {
    const target = event.target.closest('button[data-filter]');
    if (!target) {
      return;
    }
    state.availability = target.dataset.filter;
    setActiveAvailability(target);
    applyFilters();
  });

  setView(state.view);
  applyFilters();
</script>
@endpush

