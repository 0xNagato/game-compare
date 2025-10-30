@extends('layouts.app')

@section('title', ($product['name'] ?? 'Game') . ' · GameCompare')

@section('head')
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <style>
    .media-hero { position:relative; overflow:hidden; border-radius:1.5rem; min-height:420px; }
    .media-hero img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; filter:brightness(0.85) contrast(1.05) saturate(1.05); }
    .media-hero .overlay { position:absolute; inset:0; background:linear-gradient(180deg, rgba(2,6,23,0.35) 0%, rgba(2,6,23,0.85) 60%, rgba(2,6,23,0.98) 100%); }
    .media-hero .overlay:before { content:''; position:absolute; inset:0; background:radial-gradient(120% 100% at 50% 0%, rgba(2,6,23,0.3) 0%, rgba(2,6,23,0) 60%); pointer-events:none; }
    #trailerEmbed iframe, #trailerEmbed video { width:100%; height:100%; border:0; border-radius:inherit; background:#000; }
    #trailerEmbed video { object-fit:cover; }
    .chip-toggle { border:1px solid rgba(148,163,184,0.5); border-radius:999px; padding:0.4rem 1rem; font-size:0.8rem; cursor:pointer; transition:all .2s ease; display:inline-flex; align-items:center; gap:0.35rem; }
    .hero-title { text-shadow:0 1px 2px rgba(0,0,0,0.8); }
    .description-content { white-space:pre-wrap; }
  </style>
@endsection

@section('content')
  <div class="px-4 md:px-6 xl:px-10 2xl:px-12 py-8 sm:py-10 w-full max-w-none space-y-10">
    <a href="{{ route('compare') }}" class="inline-flex items-center gap-2 text-xs uppercase tracking-wide text-white/60 hover:text-amber-300">
      <span aria-hidden="true">←</span> Back to compare
    </a>

    <header class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] xl:grid-cols-[1.3fr_0.9fr] 2xl:grid-cols-[1.4fr_0.8fr] items-start">
      <div class="media-hero border border-white/10 shadow-xl">
  <img id="coverImage" src="{{ $product['image'] }}" alt="{{ $product['name'] }} cover" onerror="this.onerror=null;this.src='{{ asset('images/placeholders/game-cover.svg') }}'">
        <div id="trailerEmbed" class="absolute inset-0 hidden"></div>
        <div class="overlay"></div>
        <div class="relative h-full w-full p-6 flex flex-col justify-end">
          <div class="text-xs uppercase tracking-wide text-white/60">Now viewing</div>
          <h1 class="mt-1 text-3xl sm:text-4xl font-extrabold hero-title">{{ $product['name'] }}</h1>
          <div class="mt-3 flex items-center gap-2 flex-wrap text-sm text-white/70">
            <span class="px-2 py-1 rounded bg-white/10 border border-white/10">{{ $product['platform'] ?? 'Multi-platform' }}</span>
            @if(!empty($product['category']))
              <span class="px-2 py-1 rounded bg-white/10 border border-white/10">{{ $product['category'] }}</span>
            @endif
            @if(!empty($product['region_codes']))
              <span class="px-2 py-1 rounded bg-emerald-500/10 border border-emerald-400/30 text-emerald-200">{{ count($product['region_codes']) }} regions</span>
            @endif
          </div>
          <div class="mt-4 flex items-center gap-3">
            <button id="playTrailerBtn" type="button" class="chip-toggle border border-white/20 bg-white/5 hover:bg-white/10 transition {{ empty($product['trailer_url']) ? 'hidden' : '' }}">Play trailer</button>
            <button id="stopTrailerBtn" type="button" class="chip-toggle border border-white/20 bg-white/5 hover:bg-white/10 transition hidden">Stop trailer</button>
          </div>
        </div>
      </div>

      <aside class="bg-slate-900/80 border border-white/10 rounded-3xl p-6 space-y-4">
        <div>
          <div class="text-sm uppercase tracking-wide text-white/60">At a glance</div>
          <div class="mt-2 text-sm text-white/70">
            <div><span class="text-white/50">Platform:</span> <span id="metaPlatform">{{ $product['platform'] ?? 'Multi-platform' }}</span></div>
            @if(!empty($product['category']))
              <div><span class="text-white/50">Category:</span> <span id="metaCategory">{{ $product['category'] }}</span></div>
            @endif
            <div id="metaUpdatedAt" class="text-white/50"></div>
          </div>
        </div>

        <div>
          <div class="text-sm uppercase tracking-wide text-white/60">Best region (30d)</div>
          <div class="mt-2 text-lg font-semibold" id="bestRegionLine">Loading…</div>
        </div>

        <div>
          <div class="text-sm uppercase tracking-wide text-white/60">Offers</div>
          <div id="offersList" class="mt-2 space-y-2 text-sm text-white/80">
            <div class="text-white/60">Loading…</div>
          </div>
        </div>
      </aside>
    </header>

    <!-- Trailer + Description section -->
    <section class="grid gap-6 lg:grid-cols-2">
      <div class="bg-slate-900/80 border border-white/10 rounded-3xl overflow-hidden">
        <div class="px-6 pt-6 flex items-center justify-between gap-3 flex-wrap">
          <h3 class="text-lg font-semibold">Trailer</h3>
          <div class="text-xs text-white/60">Embed from YouTube/Vimeo or direct video</div>
        </div>
        <div id="trailerPanel" class="mt-3">
          <div class="w-full" style="aspect-ratio:16/9; background:#000; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.6)">No trailer available.</div>
        </div>
        <div class="p-6">
          <div class="flex items-center gap-3">
            <button id="panelPlayBtn" type="button" class="chip-toggle border border-white/20 bg-white/5 hover:bg-white/10 transition">Play</button>
            <button id="panelStopBtn" type="button" class="chip-toggle border border-white/20 bg-white/5 hover:bg-white/10 transition hidden">Stop</button>
          </div>
        </div>
      </div>

      <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-6">
        <h3 class="text-lg font-semibold">About</h3>
        <div id="gameDescription" class="mt-3 text-white/80 description-content">Loading description…</div>
      </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-2">
      <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-6">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
          <div>
            <h3 class="text-lg font-semibold">BTC price history</h3>
            <p class="text-sm text-white/60">30 days</p>
          </div>
        </div>
        <div id="historyChart" style="height:360px"></div>
      </div>

      <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-6">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
          <div>
            <h3 class="text-lg font-semibold">Regional comparison</h3>
            <p class="text-sm text-white/60">Average BTC over 30d</p>
          </div>
        </div>
        <div id="regionsChart" style="height:360px"></div>
      </div>
    </section>
  </div>
@endsection

@push('scripts')
<script>
  const bootstrap = @json($product);
  const playBtn = document.getElementById('playTrailerBtn');
  const stopBtn = document.getElementById('stopTrailerBtn');
  const trailerHost = document.getElementById('trailerEmbed');
  const panelHost = document.getElementById('trailerPanel');
  const panelPlay = document.getElementById('panelPlayBtn');
  const panelStop = document.getElementById('panelStopBtn');
  const cover = document.getElementById('coverImage');
  const bestRegionLine = document.getElementById('bestRegionLine');
  const offersList = document.getElementById('offersList');
  const descBox = document.getElementById('gameDescription');

  function appendUrlParam(url, key, value) {
    try {
      const parsed = new URL(url);
      parsed.searchParams.set(key, value);
      return parsed.toString();
    } catch (error) {
      return url.includes('?') ? `${url}&${key}=${value}` : `${url}?${key}=${value}`;
    }
  }

  function deriveTrailerSource(url, { autoplay = false } = {}) {
    if (typeof url !== 'string' || url.trim() === '') return null;
    const trimmed = url.trim();
    const yt = trimmed.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([\w-]{6,})/i);
    if (yt) {
      const videoId = yt[1];
      const params = new URLSearchParams({ autoplay: autoplay ? '1' : '0', rel: '0', playsinline: '1', modestbranding: '1' });
      if (autoplay) params.set('mute', '1');
      return { kind: 'youtube', src: `https://www.youtube.com/embed/${videoId}?${params.toString()}` };
    }
    const vm = trimmed.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
    if (vm) {
      const suffix = autoplay ? '?autoplay=1&muted=1' : '';
      return { kind: 'iframe', src: `https://player.vimeo.com/video/${vm[1]}${suffix}` };
    }
    const ext = trimmed.split('?')[0].split('.').pop()?.toLowerCase();
    if (ext && ['mp4','webm','ogg'].includes(ext)) {
      return { kind: 'video', src: trimmed, autoplay };
    }
    return { kind: 'iframe', src: autoplay ? appendUrlParam(trimmed,'autoplay','1') : trimmed };
  }

  function stopTrailer() {
    if (!trailerHost) return;
    trailerHost.innerHTML = '';
    trailerHost.classList.add('hidden');
    stopBtn?.classList.add('hidden');
    playBtn?.classList.remove('hidden');
    cover?.classList.remove('opacity-0');
  }

  function playTrailer() {
    const url = bootstrap.trailer_url;
    if (!trailerHost || !url) return;
    const src = deriveTrailerSource(url, { autoplay: true });
    let node;
    if (src.kind === 'video') {
      const video = document.createElement('video');
      video.src = src.src;
      video.controls = true;
      video.playsInline = true;
      video.muted = true;
      video.autoplay = true;
      video.addEventListener('loadeddata', () => video.play().catch(() => {}), { once: true });
      node = video;
    } else {
      const iframe = document.createElement('iframe');
      iframe.src = src.src;
      iframe.title = `${bootstrap.name} trailer`;
      iframe.allowFullscreen = true;
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.referrerPolicy = 'strict-origin';
      iframe.loading = 'lazy';
      node = iframe;
    }
    trailerHost.innerHTML = '';
    trailerHost.appendChild(node);
    trailerHost.classList.remove('hidden');
    playBtn?.classList.add('hidden');
    stopBtn?.classList.remove('hidden');
    cover?.classList.add('opacity-0');
  }

  playBtn?.addEventListener('click', (e) => { e.preventDefault(); playTrailer(); });
  stopBtn?.addEventListener('click', (e) => { e.preventDefault(); stopTrailer(); });

  function renderTrailerPanel({ autoplay = false } = {}) {
    if (!panelHost) return;
    const url = bootstrap.trailer_url;
    const container = document.createElement('div');
    container.style.width = '100%';
    container.style.aspectRatio = '16/9';
    container.style.background = '#000';
    panelHost.innerHTML = '';
    if (!url) {
      container.style.display = 'flex';
      container.style.alignItems = 'center';
      container.style.justifyContent = 'center';
      container.style.color = 'rgba(255,255,255,0.6)';
      container.textContent = 'No trailer available.';
      panelHost.appendChild(container);
      panelPlay?.classList.add('hidden');
      panelStop?.classList.add('hidden');
      return;
    }
    const src = deriveTrailerSource(url, { autoplay });
    let node;
    if (src.kind === 'video') {
      const video = document.createElement('video');
      video.src = src.src;
      video.controls = true;
      video.playsInline = true;
      video.muted = autoplay;
      if (autoplay) video.autoplay = true;
      node = video;
    } else {
      const iframe = document.createElement('iframe');
      iframe.src = src.src;
      iframe.title = `${bootstrap.name || bootstrap.title || 'Trailer'}`;
      iframe.allowFullscreen = true;
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.referrerPolicy = 'strict-origin';
      iframe.loading = 'lazy';
      node = iframe;
    }
    node.style.width = '100%';
    node.style.height = '100%';
    node.style.border = '0';
    node.style.display = 'block';
    container.appendChild(node);
    panelHost.appendChild(container);
    if (autoplay) {
      panelPlay?.classList.add('hidden');
      panelStop?.classList.remove('hidden');
    } else {
      panelPlay?.classList.remove('hidden');
      panelStop?.classList.add('hidden');
    }
  }

  panelPlay?.addEventListener('click', (e) => { e.preventDefault(); renderTrailerPanel({ autoplay: true }); });
  panelStop?.addEventListener('click', (e) => { e.preventDefault(); renderTrailerPanel({ autoplay: false }); });

  async function fetchDetail() {
    try {
      const res = await fetch(`/api/games/${encodeURIComponent(bootstrap.slug)}`, { headers: { Accept: 'application/json' } });
      const json = await res.json();
      drawBestRegion(json);
      drawHistory(json);
      drawRegions(json);
      drawOffers(json);
      drawSynopsis(json);
    } catch (e) {
      bestRegionLine.textContent = 'Unavailable right now';
    }
  }

  function drawBestRegion(json) {
    const regions = Array.isArray(json.region_compare) ? json.region_compare : [];
    if (!regions.length) { bestRegionLine.textContent = 'No recent data'; return; }
    const best = regions.slice().sort((a,b) => (a.btc_value||0) - (b.btc_value||0))[0];
    bestRegionLine.textContent = `${best.region_code} · ${Number(best.btc_value||0).toFixed(6)} BTC`;
  }

  function drawHistory(json) {
    const series = Array.isArray(json.price_series) ? json.price_series : [];
    const data = series.map((row) => [row.date, Number(row.btc_value||0)]);
    const options = {
      chart: { type: 'area', height: 360, toolbar: { show: false }, animations: { enabled: false } },
      series: [{ name: 'BTC', data }],
      xaxis: { type: 'category', labels: { show: true }, axisTicks: { show: false }, axisBorder: { show: false } },
      yaxis: { labels: { formatter: (val) => Number(val).toFixed(6) } },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.4, opacityTo: 0.1 } },
      grid: { borderColor: 'rgba(255,255,255,0.08)' },
      colors: ['#fbbf24'],
    };
    try {
      const chart = new ApexCharts(document.querySelector('#historyChart'), options);
      chart.render();
    } catch (e) {}
  }

  function drawRegions(json) {
    const regions = Array.isArray(json.region_compare) ? json.region_compare : [];
    const categories = regions.map((r) => r.region_code);
    const data = regions.map((r) => Number(r.btc_value||0));
    const options = {
      chart: { type: 'bar', height: 360, toolbar: { show: false }, animations: { enabled: false } },
      plotOptions: { bar: { horizontal: true, barHeight: '70%' } },
      series: [{ name: 'BTC', data }],
      xaxis: { categories, labels: { formatter: (val) => Number(val).toFixed(6) } },
      dataLabels: { enabled: false },
      grid: { borderColor: 'rgba(255,255,255,0.08)' },
      colors: ['#60a5fa'],
    };
    try {
      const chart = new ApexCharts(document.querySelector('#regionsChart'), options);
      chart.render();
    } catch (e) {}
  }

  function drawOffers(json) {
    const offers = Array.isArray(json.offers) ? json.offers : [];
    if (!offers.length) { offersList.innerHTML = '<div class="text-white/60">No live offers yet.</div>'; return; }

    // Group offers by region code, sort each group by cheapest BTC first
    const byRegion = offers.reduce((acc, o) => {
      const code = (o.region_code || 'UNK').toUpperCase();
      (acc[code] ||= []).push(o);
      return acc;
    }, {});
    Object.keys(byRegion).forEach((code) => {
      byRegion[code].sort((a,b) => Number(a.btc_value||0) - Number(b.btc_value||0));
    });

    const sections = Object.keys(byRegion).sort().map((code) => {
      const list = byRegion[code];
      const total = list.length;
      const best = list[0];
      const visible = list.slice(0,5).map(offerRow).join('');
      const hidden = total > 5 ? list.slice(5).map(offerRow).join('') : '';
      const bestLine = `${Number(best.btc_value||0).toFixed(6)} BTC` + (best.currency ? ` · ${Number(best.price||0).toFixed(2)} ${best.currency}` : '');
      const moreBtn = total > 5 ? `<button type="button" class="mt-2 text-xs text-amber-300 hover:text-amber-200 underline" data-toggle-region="${code}">Show all ${total} offers</button>` : '';
      return `
        <div class="rounded-xl border border-white/10 p-3">
          <div class="flex items-center justify-between text-sm">
            <div><span class="font-semibold">${code}</span> <span class="text-white/50">(${total} retailer${total>1?'s':''})</span></div>
            <div class="text-white/80">Best · ${bestLine}</div>
          </div>
          <div class="mt-2 space-y-2">
            ${visible}
            <div class="hidden" data-more-for="${code}">${hidden}</div>
            ${moreBtn}
          </div>
        </div>
      `;
    }).join('');

    offersList.innerHTML = sections;

    // Toggle reveal for additional offers
    offersList.querySelectorAll('[data-toggle-region]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const code = btn.getAttribute('data-toggle-region');
        const more = offersList.querySelector(`[data-more-for="${CSS.escape(code)}"]`);
        if (more) {
          more.classList.remove('hidden');
          btn.remove();
        }
      });
    });

    function offerRow(o) {
      const btc = Number(o.btc_value||0).toFixed(6);
      const fiat = o.currency ? `${Number(o.price||0).toFixed(2)} ${o.currency}` : null;
      const right = fiat ? `${fiat} · ${btc} BTC` : `${btc} BTC`;
      return `
        <a href="${o.url || '#'}" target="_blank" rel="noopener noreferrer" class="block p-3 rounded-lg border border-white/10 hover:bg-white/5 transition">
          <div class="flex items-center justify-between gap-3">
            <div class="text-sm"><span class="font-semibold">${o.retailer || 'Retailer'}</span></div>
            <div class="text-sm">${right}</div>
          </div>
        </a>
      `;
    }
  }

  function drawSynopsis(json) {
    if (!descBox) return;
    const synopsis = (json && typeof json.synopsis === 'string' && json.synopsis.trim()) ? json.synopsis.trim() : null;
    descBox.textContent = synopsis || 'No description available.';
  }

  // Initialize trailer panel (no autoplay by default)
  renderTrailerPanel({ autoplay: false });
  fetchDetail();
</script>
@endpush
