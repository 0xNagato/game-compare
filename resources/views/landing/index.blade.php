@extends('layouts.app')

@section('title', 'Game Compare · BTC-Priced Games at a Glance')

@section('content')
<section class="hero relative overflow-hidden rounded-3xl bg-slate-900/90 p-6 text-white {{ $heroImage ? '' : 'hero--fallback' }}" @if($heroImage) style="background-image:url('{{ $heroImage }}'); background-size:cover; background-position:center;" @endif>
  <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm"></div>
  <div class="relative max-w-6xl mx-auto grid md:grid-cols-2 gap-6 items-center">
    <div>
      <h1 class="text-5xl font-extrabold">Game pricing, visual first.</h1>
      <p class="mt-2 text-white/70">Realtime BTC snapshots · 100+ regions · platform badges</p>
      <div class="mt-4 flex gap-3">
        <a href="{{ url('/admin') }}" class="px-4 py-2 rounded-xl bg-white text-black font-semibold">Open Admin</a>
        <a href="{{ route('compare') }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition">Explore Compare</a>
      </div>
    </div>
    <div class="relative h-48 rounded-2xl overflow-hidden border border-white/10 bg-black/40">
      <div class="absolute inset-0 bg-linear-to-br from-slate-950/60 via-slate-900/40 to-transparent"></div>
      <div class="absolute inset-0 p-4 flex flex-col justify-end">
        <div class="text-sm text-white/80 mb-1">Now trending</div>
        <div class="text-lg font-medium space-x-4">
          @foreach($featuredProducts->take(6) as $g)
            <span class="inline-block opacity-80">{{ $g['name'] }}</span>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="relative mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <x-kpi label="Tracked products" :value="$metrics['products']"/>
    <x-kpi label="Regions watched" :value="$metrics['regions']"/>
    <x-kpi label="Price points (24h)" :value="$metrics['snapshots24h']"/>
  </div>
</section>

@if(!empty($trendingConsoles))
  <section class="mt-10 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-white/90 text-xl font-semibold">Console watchlist</h2>
      <span class="text-xs uppercase tracking-wide text-white/50">BTC-normalized hardware snapshot · refreshed every 30 min</span>
    </div>

    @foreach($trendingConsoles as $group)
      @php
        $consoles = $group['consoles'] ?? [];
      @endphp
      @if(empty($consoles))
        @continue
      @endif
      <div class="glass-panel glass-border rounded-3xl p-5 space-y-4 bg-slate-900/60">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-white">{{ $group['label'] }}</h3>
            <p class="text-xs text-white/60">{{ $group['family_label'] }} family · aggregated from latest price windows</p>
          </div>
          <span class="inline-flex items-center gap-2 text-xs text-amber-200/80 bg-amber-900/30 px-3 py-1 rounded-full border border-amber-300/30">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
              <path d="M10 3a1 1 0 0 1 .894.553l5 10A1 1 0 0 1 15 15H5a1 1 0 0 1-.894-1.447l5-10A1 1 0 0 1 10 3Zm0 3.618L6.382 13h7.236L10 6.618Z" />
            </svg>
            Normalized to BTC
          </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          @foreach($consoles as $console)
            @php
              $summary = $console['price_summary'] ?? null;
              $analytics = $console['analytics'] ?? [];
              $avgBtc = $summary && isset($summary['avg_btc']) ? number_format($summary['avg_btc'], 6) . ' BTC' : null;
              $bestRegion = $analytics['best_region'] ?? ($summary['best_region'] ?? null);
              $regionCount = $analytics['region_count'] ?? count($console['region_codes'] ?? []);
              $sampleCount = $analytics['sample_count'] ?? null;
              $updatedHuman = $analytics['window_end_human'] ?? null;
              $platformLabel = $console['platform_label'] ?? ($console['primary_platform_family'] ?? 'Console');
              $regionCountLabel = $regionCount ? number_format($regionCount) . ' regions' : 'No regions tracked yet';
              $sampleLabel = $sampleCount ? number_format($sampleCount) . ' samples' : null;
            @endphp
            <a href="{{ route('compare', ['focus' => $console['slug']]) }}" class="watchlist-card group relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/80">
              @if(!empty($console['image']))
                <img src="{{ $console['image'] }}" alt="{{ $console['name'] }} art" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
              @else
                <div class="watchlist-placeholder absolute inset-0"></div>
              @endif
              <div class="absolute inset-0 bg-linear-to-t from-black/85 via-black/30 to-transparent"></div>
              <div class="relative z-10 flex h-full flex-col justify-between p-4 gap-3">
                <div>
                  <div class="text-xs uppercase tracking-wide text-white/60">{{ $platformLabel }}</div>
                  <div class="mt-1 text-lg font-semibold text-white line-clamp-2">{{ $console['name'] }}</div>
                </div>
                <div class="space-y-2 text-xs text-white/70">
                  @if($avgBtc)
                    <div class="flex flex-wrap items-center gap-2 text-sm text-emerald-200">
                      <span class="font-semibold">{{ $avgBtc }}</span>
                      @if($bestRegion)
                        <span class="text-white/60">Best · {{ strtoupper($bestRegion) }}</span>
                      @endif
                    </div>
                  @else
                    <div class="text-sm text-white/70">Pricing window warming up…</div>
                  @endif
                  <div class="flex flex-wrap gap-2 text-[11px] text-white/50">
                    <span>{{ $regionCountLabel }}</span>
                    @if($sampleLabel)
                      <span>• {{ $sampleLabel }}</span>
                    @endif
                    @if($updatedHuman)
                      <span>• {{ $updatedHuman }}</span>
                    @endif
                  </div>
                </div>
                <div class="inline-flex items-center gap-2 text-[11px] uppercase tracking-wide text-amber-200/90">
                  <span>View compare</span>
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" fill="none" class="w-3 h-3">
                    <path d="M3 2.5 8.5 6 3 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </div>
              </div>
            </a>
          @endforeach
        </div>
      </div>
    @endforeach
  </section>
@endif

<h2 class="mt-8 mb-3 text-white/90 text-xl font-semibold">Live spotlight</h2>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] items-start">
  <div id="gameGrid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($featuredProducts as $g)
      @php
        $cover = $g['image'] ?? $g['trailer_thumbnail'] ?? null;
      @endphp
      <article
        class="game-card group relative rounded-2xl overflow-hidden bg-black/30 shadow-lg"
        data-slug="{{ $g['slug'] }}"
        data-name="{{ $g['name'] }}"
        data-img="{{ $cover ?? '' }}"
  data-video="{{ $g['trailer_play_url'] ?? $g['trailer_url'] ?? '' }}"
        onclick="CardPlayer.focusCard(this)"
        onmouseenter="CardPlayer.peek(this)"
        onmouseleave="CardPlayer.unpeek(this)"
        role="button"
        tabindex="0"
        aria-label="Play {{ $g['name'] }} trailer"
      >
        @if($cover)
          <img class="card-cover absolute inset-0 w-full h-full object-cover" src="{{ $cover }}" alt="{{ $g['name'] }} cover">
        @else
          <div class="card-placeholder absolute inset-0"></div>
        @endif
        <div class="card-shine"></div>
        <div class="card-dim"></div>
        @if($g['trailer_url'])
          <button type="button" class="card-play" onclick="event.stopPropagation(); CardPlayer.playTrailer(this.closest('.game-card'));" title="Watch trailer">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
              <path d="M8 5.14v13.72c0 .79.86 1.27 1.54.84l10.24-6.86a1 1 0 0 0 0-1.68L9.54 4.3A1 1 0 0 0 8 5.14Z" />
            </svg>
            <span>Play trailer</span>
          </button>
        @endif
  <footer class="card-footer absolute inset-x-0 bottom-0 p-3 bg-linear-to-t from-black/75 to-transparent">
          <div class="text-white font-semibold text-sm truncate">{{ $g['name'] }}</div>
          <div class="mt-1 flex items-center gap-2 text-[11px] text-white/70">
            @if($g['platform'])
              <span class="badge">{{ $g['platform'] }}</span>
            @endif
            @if(!empty($g['price_summary']['avg_btc']))
              <span>{{ number_format($g['price_summary']['avg_btc'], 6) }} BTC</span>
            @endif
          </div>
        </footer>
      </article>
    @endforeach
  </div>

  <aside id="pricePanel" class="hidden sticky top-4 rounded-2xl glass-panel glass-border p-4 text-white space-y-4">
    <div class="flex items-center gap-4">
      <img id="ppCover" class="w-16 h-20 rounded-lg object-cover bg-black/40" alt="">
      <div>
        <h3 id="ppTitle" class="text-lg font-bold">—</h3>
        <div id="ppRegion" class="text-xs text-white/70">—</div>
      </div>
    </div>
    <div class="text-sm space-y-1">
      <div>Latest price (BTC): <span id="ppPrice" class="font-semibold">—</span></div>
      <div id="ppChange" class="text-xs text-white/70">Trend warming up</div>
    </div>
    <div id="ppSpark" class="h-16"></div>
    <a id="ppMore" href="#" class="inline-flex items-center gap-2 text-xs text-amber-300 hover:text-amber-200">See full comparison →</a>
  </aside>
</div>

@if($gallery->isNotEmpty())
  <h2 class="mt-10 mb-3 text-white/90 text-xl font-semibold">Global feed preview</h2>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @foreach($gallery as $tile)
      <div class="relative aspect-4/5 overflow-hidden rounded-2xl bg-slate-800/70 border border-white/5">
        <img src="{{ $tile['image'] }}" alt="{{ $tile['name'] }} cover" loading="lazy"
             class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 hover:scale-105">
        <div class="absolute inset-x-0 bottom-0 p-3 bg-linear-to-t from-black/70 to-transparent">
          <div class="text-xs uppercase tracking-wide text-white/70">{{ $tile['platform'] ?? 'Unknown Platform' }}</div>
          <div class="text-sm font-semibold text-white line-clamp-1">{{ $tile['name'] }}</div>
        </div>
      </div>
    @endforeach
  </div>
@endif

<style>
  .game-card{position:relative;transform:translateZ(0);transition:transform .25s ease;cursor:pointer;}
  .game-card:hover{transform:translateY(-4px);}
  .game-card .card-dim{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.45),rgba(0,0,0,0) 55%);opacity:0;transition:opacity .25s ease;}
  .game-card .card-shine{position:absolute;inset:-200% -60% -60% -60%;background:radial-gradient(60% 60% at 50% 35%,rgba(0,255,220,.15),transparent 60%),repeating-linear-gradient(transparent 0 2px,rgba(0,255,220,.08) 3px 3.4px);mix-blend-mode:screen;opacity:0;transition:opacity .25s ease;pointer-events:none;}
  .game-card:hover .card-shine{opacity:.6;}
  .game-card.is-focused{outline:1px solid rgba(0,255,220,.35);box-shadow:0 16px 40px rgba(15,118,110,.35);}
  .game-card.is-focused .card-dim{opacity:.12;}
  .game-card.is-muted .card-dim{opacity:.4;}
  .holo-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .25s ease,filter .25s ease;filter:saturate(1.1) contrast(1.05) drop-shadow(0 0 12px rgba(16,185,129,.2));border-radius:inherit;}
  .game-card.has-video .holo-video.show{opacity:1;}
  .game-card.has-video.is-muted .holo-video{filter:saturate(.85) brightness(.85);}
  .badge{display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .45rem;border-radius:6px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.12);text-transform:uppercase;letter-spacing:.05em;}
  .card-placeholder{background:linear-gradient(135deg,rgba(15,118,110,.35),rgba(14,165,233,.25));filter:blur(0);}
  .hero--fallback{background:radial-gradient(circle at top left,rgba(14,165,233,.25),rgba(15,23,42,.9) 55%);}
  .watchlist-card{min-height:220px;box-shadow:0 14px 40px rgba(15,23,42,.45);transition:transform .25s ease, box-shadow .25s ease;}
  .watchlist-card:hover{transform:translateY(-6px);box-shadow:0 18px 50px rgba(250,204,21,.25);}
  .watchlist-placeholder{background:linear-gradient(135deg,rgba(250,204,21,.2),rgba(59,130,246,.25));}
  .card-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1rem;border-radius:9999px;background:rgba(6,95,70,.85);color:#fff;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;box-shadow:0 14px 45px rgba(6,95,70,.45);transition:transform .2s ease,opacity .2s ease;background-blend-mode:screen;}
  .card-play:hover{transform:translate(-50%,-50%) scale(1.05);}
  .card-play svg{flex-shrink:0;}
  .game-card:not([data-video]) .card-play{display:none;}
  .game-card.is-playing .card-play{opacity:0;pointer-events:none;}
  .card-cover,.card-placeholder{z-index:0;transition:opacity .25s ease;}
  .card-dim{z-index:2;}
  .card-shine{z-index:3;}
  .card-play{z-index:4;}
  .card-footer{z-index:5;transition:opacity .25s ease;}
  .holo-video{z-index:1;}
  .game-card.is-playing .card-cover,.game-card.is-playing .card-placeholder{opacity:0;}
  .game-card.is-playing .card-dim{opacity:0;}
  .game-card.is-playing .card-footer{opacity:0;}
</style>

@push('scripts')
<script>
  const CardPlayer = (() => {
    const compareCache = new Map();
    const CACHE_TTL_MS = 5 * 60 * 1000;
    const INLINE_VIDEO_PATTERN = /\.(mp4|webm|m4v|mov)(\?.*)?$/i;

    function canInlinePlay(url) {
      if (!url) {
        return false;
      }

      const normalized = url.toLowerCase();

      if (normalized.startsWith('blob:')) {
        return true;
      }

      if (normalized.includes('youtube.com') || normalized.includes('youtu.be') || normalized.includes('vimeo.com')) {
        return false;
      }

      const sanitized = normalized.split('#')[0];
      const withoutQuery = sanitized.split('?')[0];

      if (INLINE_VIDEO_PATTERN.test(withoutQuery)) {
        return true;
      }

      return INLINE_VIDEO_PATTERN.test(sanitized);
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const card = entry.target;
        if (!card.classList.contains('has-video')) {
          return;
        }
        const video = card.querySelector('video');
        if (!video) {
          return;
        }
        if (entry.isIntersecting && card.classList.contains('is-playing')) {
          video.play().catch(() => {});
        } else {
          video.pause();
        }
      });
    }, { threshold: 0.3 });

    function ensureVideo(card) {
      const existing = card.querySelector('video');
      if (existing) {
        return existing;
      }

      const src = card.dataset.video;
      if (!src || !canInlinePlay(src)) {
        return null;
      }

      const video = document.createElement('video');
      video.className = 'holo-video';
      video.src = src;
      video.muted = true;
      video.setAttribute('muted', '');
      video.loop = true;
      video.playsInline = true;
      video.autoplay = false;
      video.preload = 'metadata';
      video.controls = false;

      if (card.dataset.img) {
        video.poster = card.dataset.img;
      }

      video.oncanplay = () => video.classList.add('show');
      video.onerror = () => {
        video.remove();
        card.classList.remove('has-video');
      };

      card.appendChild(video);
      card.classList.add('has-video');
      observer.observe(card);

      return video;
    }

    function setHeroBackground(url) {
      const hero = document.querySelector('.hero');
      if (!hero) {
        return;
      }
      if (url) {
        hero.style.setProperty('background-image', `url('${url}')`);
        hero.classList.remove('hero--fallback');
      } else {
        hero.style.removeProperty('background-image');
        hero.classList.add('hero--fallback');
      }
    }

    async function updatePanel(card) {
      const panel = document.getElementById('pricePanel');
      if (!panel) {
        return;
      }
      const slug = card.dataset.slug;
      const name = card.dataset.name;
      const img = card.dataset.img;
      panel.querySelector('#ppTitle').textContent = name;
      panel.querySelector('#ppCover').src = img || '';
      panel.querySelector('#ppPrice').textContent = 'Loading…';
      panel.querySelector('#ppRegion').textContent = 'Fetching latest window';
      panel.querySelector('#ppSpark').innerHTML = '';
      panel.querySelector('#ppMore').href = `/compare?focus=${encodeURIComponent(slug)}`;
      panel.dataset.slug = slug;
      panel.classList.remove('hidden');

      try {
        let json;
        const cached = compareCache.get(slug);
        const now = Date.now();

        if (cached && now - cached.timestamp < CACHE_TTL_MS) {
          json = cached.payload;
        } else {
          const response = await fetch(`/api/games/${slug}/compare`, { headers: { Accept: 'application/json' } });
          if (!response.ok) {
            throw new Error('Request failed');
          }
          json = await response.json();
          compareCache.set(slug, { payload: json, timestamp: now });
        }

        if (panel.dataset.slug !== slug) {
          return;
        }
        const regions = Array.isArray(json.regions) ? json.regions : [];
        if (regions.length) {
          const sorted = regions.slice().sort((a, b) => (a.value_btc ?? 0) - (b.value_btc ?? 0));
          const best = sorted[0];
          panel.querySelector('#ppPrice').textContent = Number(best.value_btc ?? 0).toFixed(6) + ' BTC';
          panel.querySelector('#ppRegion').textContent = `Best region · ${best.code}`;
        } else {
          panel.querySelector('#ppPrice').textContent = '—';
          panel.querySelector('#ppRegion').textContent = 'No regional data yet';
        }
        panel.querySelector('#ppSpark').innerHTML = '<svg viewBox="0 0 100 24" preserveAspectRatio="none"><polyline fill="none" stroke="rgba(16,185,129,.85)" stroke-width="2" points="0,18 10,12 20,15 30,8 40,11 50,6 60,10 70,7 80,9 90,5 100,7" /></svg>';
      } catch (error) {
        if (panel.dataset.slug !== slug) {
          return;
        }
        panel.querySelector('#ppPrice').textContent = '—';
        panel.querySelector('#ppRegion').textContent = 'Offline';
        panel.querySelector('#ppSpark').innerHTML = '';
      }
    }

    function muteOthers(current) {
      document.querySelectorAll('.game-card.is-focused').forEach((card) => {
        if (card === current) {
          return;
        }
        card.classList.remove('is-focused');
        card.classList.add('is-muted');
        card.classList.remove('is-playing');
        const video = card.querySelector('video');
        if (video) {
          video.pause();
          video.currentTime = 0;
          video.muted = true;
          video.setAttribute('muted', '');
          video.controls = false;
        }
      });
    }

    function focusCard(card) {
      card.classList.add('is-focused');
      card.classList.remove('is-muted');
      card.classList.remove('is-playing');
      muteOthers(card);
      setHeroBackground(card.dataset.img);
      updatePanel(card);
      const video = card.querySelector('video');
      if (video) {
        video.pause();
        video.currentTime = 0;
        video.muted = true;
        video.setAttribute('muted', '');
        video.controls = false;
      }
    }

    function playTrailer(card) {
      const videoUrl = card.dataset.video;
      if (!videoUrl) {
        return;
      }

      const wasPlaying = card.classList.contains('is-playing');
      const inlineCapable = canInlinePlay(videoUrl);

      focusCard(card);

      if (wasPlaying) {
        const existing = card.querySelector('video');
        if (existing) {
          existing.pause();
          existing.currentTime = 0;
          existing.muted = true;
          existing.setAttribute('muted', '');
          existing.controls = false;
        }
        card.classList.remove('is-playing');
        return;
      }

      if (!inlineCapable) {
        window.open(videoUrl, '_blank', 'noopener');
        return;
      }

      const ensuredVideo = ensureVideo(card);

      if (!ensuredVideo) {
        window.open(videoUrl, '_blank', 'noopener');
        return;
      }

      card.classList.add('is-playing');
      card.classList.remove('is-muted');
      ensuredVideo.muted = false;
      ensuredVideo.removeAttribute('muted');
      ensuredVideo.controls = true;
      ensuredVideo.currentTime = 0;

      ensuredVideo.play().catch(() => {
        card.classList.remove('is-playing');
        ensuredVideo.muted = true;
        ensuredVideo.setAttribute('muted', '');
        ensuredVideo.controls = false;
        window.open(videoUrl, '_blank', 'noopener');
      });
    }

    function peek() {}

    function unpeek(card) {
      if (!card.classList.contains('is-focused')) {
        card.classList.remove('is-muted');
      }
    }

    return { focusCard, peek, unpeek, playTrailer };
  })();

  document.addEventListener('keydown', (event) => {
    if (!['Enter', ' '].includes(event.key)) {
      return;
    }
    const active = document.activeElement;
    if (active && active.classList.contains('game-card')) {
      event.preventDefault();
      CardPlayer.focusCard(active);
    }
  });

  window.addEventListener('DOMContentLoaded', () => {
    const firstCard = document.querySelector('.game-card');
    if (firstCard) {
      CardPlayer.focusCard(firstCard);
    }
  });
</script>
@endpush
@endsection
