<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Game Compare')</title>
    <meta name="description" content="Global video game and console pricing, normalized to BTC with rich visuals and live metrics.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=line-clamp"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <style>
        .glass-panel {
            background: linear-gradient(135deg, rgba(15,23,42,0.85), rgba(30,64,175,0.45));
            border: 1px solid rgba(148,163,184,0.25);
            box-shadow: 0 18px 45px rgba(15,23,42,0.35);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }
        .glass-border {
            border: 1px solid rgba(148,163,184,0.25);
        }
        /* Light theme overrides */
        :root[data-theme="light"] body { background: #f8fafc; color: #0f172a; }
        :root[data-theme="light"] .glass-panel {
            background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(226,232,240,0.8));
            border-color: rgba(15,23,42,0.08);
            box-shadow: 0 10px 25px rgba(2,6,23,0.08);
        }
        :root[data-theme="light"] .glass-border { border-color: rgba(15,23,42,0.12); }
        :root[data-theme="light"] .app-header a, :root[data-theme="light"] .app-header nav a { color:#0f172a; }
        :root[data-theme="light"] .app-header .meta { color:#334155; }
        :root[data-theme="light"] .app-header .admin-btn { color:#0f172a; border-color: rgba(15,23,42,0.25); }
        :root[data-theme="light"] .app-header .admin-btn:hover { background: rgba(15,23,42,0.05); }
    </style>
    @yield('head')
</head>
<body class="bg-slate-950 text-white font-['Space_Grotesk',sans-serif]">
    <div class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-50 px-6 pt-6 app-header">
            <div class="glass-panel glass-border rounded-3xl px-6 py-4 w-full flex items-center justify-between gap-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3 text-white font-semibold text-lg tracking-tight">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-amber-400 text-slate-900 font-bold">GC</span>
                    <span>Game Compare</span>
                </a>
                <nav class="hidden md:flex items-center gap-6 text-sm text-white/70">
                    <a href="{{ route('compare') }}" class="hover:text-amber-300 transition">Compare</a>
                    <a href="{{ url('/#charts') }}" class="hover:text-amber-300 transition">Charts</a>
                    <a href="{{ url('/#about') }}" class="hover:text-amber-300 transition">About</a>
                </nav>
                <form id="globalHeaderSearch" class="hidden lg:flex items-center gap-2 flex-1 max-w-md mx-2" role="search" aria-label="Search">
                    <input type="search" id="globalSearchInput" class="w-full rounded-xl bg-white/5 border border-white/10 px-4 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-amber-400" placeholder="Search games, consoles, platforms…" autocomplete="off">
                    <button type="submit" class="px-3 py-2 rounded-lg bg-amber-400 text-black text-sm font-semibold hover:bg-amber-300 transition">Search</button>
                </form>
                <div class="flex items-center gap-3 text-xs text-white/60">
                    <button id="themeToggle" type="button" class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-white/15 hover:bg-white/10 transition" aria-label="Toggle theme" title="Toggle light/dark">
                        <svg id="iconSun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M12 4.5a1 1 0 0 1 1 1V7a1 1 0 1 1-2 0V5.5a1 1 0 0 1 1-1Zm0 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5-3.5a1 1 0 0 1 1 1V13a1 1 0 1 1-2 0v-.5a1 1 0 0 1 1-1ZM5.5 12a1 1 0 0 1 1-1H7a1 1 0 1 1 0 2h-.5a1 1 0 0 1-1-1Zm9.95-5.45a1 1 0 0 1 1.41 0l1.06 1.06a1 1 0 1 1-1.41 1.42L15.45 7.97a1 1 0 0 1 0-1.42ZM6.58 16.03a1 1 0 0 1 0-1.42l1.06-1.06a1 1 0 0 1 1.41 1.42L8 16.03a1 1 0 0 1-1.41 0Zm11.84 0a1 1 0 0 1-1.41 0l-1.06-1.06a1 1 0 1 1 1.41-1.42l1.06 1.06a1 1 0 0 1 0 1.42ZM8 7.97a1 1 0 0 1-1.41 0L5.53 6.91a1 1 0 0 1 1.41-1.42L8 6.55a1 1 0 0 1 0 1.42Z"/></svg>
                        <svg id="iconMoon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 hidden"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 1 0 9.79 9.79Z"/></svg>
                    </button>
                    <span class="hidden sm:inline meta">BTC normalized ingest • queues live</span>
                    <a href="{{ url('/admin/login') }}" class="admin-btn px-4 py-2 rounded-full border border-amber-400/60 text-amber-300 hover:bg-amber-400/10 transition">Admin</a>
                </div>
            </div>
        </header>
        <main class="flex-1">
            @yield('content')
        </main>
        <footer class="px-6 pb-10 mt-10">
            <div class="glass-panel glass-border rounded-3xl px-6 py-6 w-full text-sm text-white/70 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-white font-semibold">BTC-normalized pricing intelligence</div>
                    <div class="text-white/60">Powered by FX feeds &amp; price APIs (ITAD, PriceCharting, eBay, RAWG, Giant Bomb, TheGamesDB, Wikimedia Commons).</div>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span>Queues &amp; Horizon dashboards ready for recruiters.</span>
                    <a href="{{ url('/admin/login') }}" class="px-4 py-2 rounded-full border border-white/20 hover:bg-white/5 transition text-white">Admin login</a>
                </div>
            </div>
        </footer>
    </div>
        <script>
            // Theme init
            (function() {
                const saved = localStorage.getItem('theme');
                const prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
                const theme = saved || (prefersLight ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
                function updateIcons() {
                    const sun = document.getElementById('iconSun');
                    const moon = document.getElementById('iconMoon');
                    if (!sun || !moon) return;
                    const t = document.documentElement.getAttribute('data-theme');
                    if (t === 'light') { sun.classList.add('hidden'); moon.classList.remove('hidden'); }
                    else { sun.classList.remove('hidden'); moon.classList.add('hidden'); }
                }
                updateIcons();
                const btn = document.getElementById('themeToggle');
                if (btn) {
                    btn.addEventListener('click', function() {
                        const current = document.documentElement.getAttribute('data-theme') || 'dark';
                        const next = current === 'light' ? 'dark' : 'light';
                        document.documentElement.setAttribute('data-theme', next);
                        localStorage.setItem('theme', next);
                        updateIcons();
                    });
                }
                // Header search -> redirect to compare with ?search=
                const form = document.getElementById('globalHeaderSearch');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const input = document.getElementById('globalSearchInput');
                        const term = (input?.value || '').trim();
                        if (!term) return;
                        window.location.href = `{{ route('compare') }}?search=${encodeURIComponent(term)}`;
                    });
                }
            })();
        </script>
        @stack('scripts')
</body>
</html>
