<?php

namespace App\Console\Commands;

use App\Jobs\BuildSeriesJob;
use App\Jobs\FetchOffersForProductJob;
use App\Jobs\FetchProductMediaJob;
use App\Jobs\FetchTopGamesJob;
use App\Models\Product;
use App\Services\Catalogue\CatalogueAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyAndSeedFromProviders extends Command
{
    protected $signature = 'providers:verify-and-seed
        {--limit=1200 : Number of products to ingest}
        {--window=180 : Trending window days}
        {--families=xbox,playstation,nintendo,pc : Platform families to include}
        {--regions=US,GB,EU,CA : Regions to ingest prices for}
        {--chunk=100 : Dispatch batch size when enqueueing jobs}
        {--seed-per-run=400 : How many catalogue entries to attempt per aggregation pass}
        {--min-per-family= : Comma list of family:min pairs (e.g., "nintendo:100,playstation:100,xbox:100")}
        {--max-per-family= : Comma list of family:max pairs to cap selection per family}
        {--targets-per-family= : Comma list of family:exact pairs (sets both min and max)}
        {--skip-verify : Skip HTTP provider probes}
        {--seed-known : When catalogue is short, seed from config/thegamesdb_mirror.php list}
        {--dry-run : Verify endpoints only, do not seed or dispatch jobs}
    ';

    protected $description = 'Verify provider endpoints work, then seed catalogue + fetch prices & media for selected products.';

    public function handle(CatalogueAggregator $aggregator): int
    {
        // 0) Load and validate docs/api_providers.json for drift (presence + basic shape)
        $registry = $this->loadProviderRegistry();
        $this->validateRequiredProviders($registry, ['rawg', 'giantbomb', 'nexarda', 'itad', 'pricecharting']);

        // Optional: advise on ITAD OAuth could be added here if needed; proceed with API key flow by default.

        // 1) Verify core endpoints for providers unless skipped
        if (! $this->option('skip-verify')) {
            $this->info('Verifying provider endpoints...');
            $probes = $this->buildProbeList();
            $failed = $this->probeEndpoints($probes);

            // Classify failures:
            // - ENV: missing .env keys → warn only (non-blocking)
            // - ITAD: external aggregator often rate-limited → warn only (non-blocking)
            // - Others: treat as blocking
            $envFailures = $failed->filter(fn (array $f) => ($f['method'] ?? '') === 'ENV');
            $itadFailures = $failed->filter(fn (array $f) => str_contains($f['url'] ?? '', 'isthereanydeal.com'));
            $blocking = $failed->reject(function (array $f) use ($envFailures, $itadFailures) {
                if (($f['method'] ?? '') === 'ENV') {
                    return true;
                }
                if (str_contains($f['url'] ?? '', 'isthereanydeal.com')) {
                    return true;
                }

                return false;
            });

            if ($blocking->isNotEmpty()) {
                $this->error('One or more provider probes failed:');
                $blocking->each(fn ($f) => $this->line(sprintf(' - %s %s => %s', $f['method'], $f['url'], $f['error'])));

                return self::FAILURE;
            }

            if ($envFailures->isNotEmpty() || $itadFailures->isNotEmpty()) {
                $this->warn('Some non-blocking probes failed (continuing):');
                $envFailures->each(fn ($f) => $this->line(sprintf(' - %s %s => %s', $f['method'], $f['url'], $f['error'])));
                $itadFailures->each(fn ($f) => $this->line(sprintf(' - %s %s => %s', $f['method'], $f['url'], $f['error'])));
            } else {
                $this->info('All probes responded successfully.');
            }
        } else {
            $this->comment('Skipping provider verification (per --skip-verify).');
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete.');

            return self::SUCCESS;
        }

        // 2) Aggregate products (latest first preference) and persist via existing job pipeline
        $limit = max(1, (int) $this->option('limit'));
        $windowDays = max(30, (int) $this->option('window'));
        $families = collect(explode(',', (string) $this->option('families')))
            ->map(fn ($v) => trim(strtolower($v)))
            ->filter()
            ->values();

        $this->info(sprintf('Aggregating up to %d products (window: %d days)...', $limit, $windowDays));
        // Try to grow catalogue to at least the requested size for the selected families
        $this->ensureCatalogueSize($aggregator, $limit, $windowDays, $families);

        // 3) Select seeded products by families and newest first
        $regions = collect(explode(',', (string) $this->option('regions')))->map(fn ($r) => strtoupper(trim($r)))->filter()->values()->all();

        $chunk = max(10, (int) $this->option('chunk'));
        $this->info(sprintf('Dispatching price + media ingestion in batches of %d...', $chunk));

        // Optional minimum/maximum/exact per-family quotas
        $minPerFamily = $this->parsePairsOption((string) $this->option('min-per-family'));
        $maxPerFamily = $this->parsePairsOption((string) $this->option('max-per-family'));
        $targetsPerFamily = $this->parsePairsOption((string) $this->option('targets-per-family'));
        // If exact targets provided, they override both min and max for those families
        if (! empty($targetsPerFamily)) {
            foreach ($targetsPerFamily as $fam => $cnt) {
                $minPerFamily[$fam] = $cnt;
                $maxPerFamily[$fam] = $cnt;
            }
        }
        $planIds = $this->buildDispatchPlan(limit: $limit, families: $families, minPerFamily: $minPerFamily, maxPerFamily: $maxPerFamily);

        $processed = 0;
        collect($planIds)
            ->chunk($chunk)
            ->each(function ($ids) use (&$processed, $limit, $regions) {
                $products = Product::query()->whereIn('id', $ids->all())->get();
                foreach ($products as $product) {
                    if ($processed >= $limit) {
                        break;
                    }

                    if (blank($product->category)) {
                        $product->category = $this->inferCategory($product->name, $product->platform, $product->primary_platform_family);
                        $product->save();
                    }

                    FetchOffersForProductJob::dispatch($product->id, $regions);
                    FetchProductMediaJob::dispatch($product->id, ['query' => $product->name]);
                    BuildSeriesJob::dispatch($product->id);

                    $processed++;
                }

                $this->line(sprintf('  → dispatched %d total...', $processed));
            });

        $this->info(sprintf('Seeding plan dispatched (%d products).', $processed));
        if ($processed < $limit) {
            $this->warn(sprintf('Requested %d but only %d products matched filters in the catalogue. To ingest more, expand families or run catalogue seeding jobs.', $limit, $processed));
        }

        return self::SUCCESS;
    }

    /**
     * Parse --min-per-family option like "nintendo:100,playstation:100,xbox:100".
     *
     * @return array<string,int>
     */
    protected function parsePairsOption(string $input): array
    {
        $result = [];
        if (trim($input) === '') {
            return $result;
        }
        foreach (explode(',', $input) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || ! str_contains($pair, ':')) {
                continue;
            }
            [$fam, $cnt] = array_map('trim', explode(':', $pair, 2));
            if ($fam !== '' && is_numeric($cnt)) {
                $result[strtolower($fam)] = max(0, (int) $cnt);
            }
        }
        return $result;
    }

    /**
     * Build a list of product IDs satisfying min-per-family quotas, then fill the rest.
     *
     * @param \Illuminate\Support\Collection<int,string> $families
     * @param array<string,int> $minPerFamily
     * @param array<string,int> $maxPerFamily
     * @return array<int,int> Product IDs
     */
    protected function buildDispatchPlan(int $limit, Collection $families, array $minPerFamily, array $maxPerFamily = []): array
    {
        $selected = collect();
        $selectedCounts = [];

        // 1) Satisfy explicit per-family minimums
        foreach ($minPerFamily as $family => $min) {
            if ($min <= 0) continue;

            // Respect top-level families filter if provided
            if ($families->isNotEmpty() && ! $families->contains($family)) {
                continue;
            }

            $cap = $maxPerFamily[$family] ?? null;
            $take = $cap !== null ? min($min, $cap) : $min;

            $ids = Product::query()
                ->where('primary_platform_family', $family)
                ->orderByDesc('popularity_score')
                ->orderByDesc('updated_at')
                ->limit($take)
                ->pluck('id');

            if ($ids->isEmpty()) {
                $this->warn(sprintf('Family quota requested (%s:%d) but no products available.', $family, $take));
            }

            $selected = $selected->merge($ids);
            $selectedCounts[$family] = ($selectedCounts[$family] ?? 0) + $ids->count();
            if ($selected->count() >= $limit) {
                return $selected->unique()->take($limit)->values()->all();
            }
        }

        // 2) Fill remainder from the overall families filter (including PC if present)
        $remaining = max(0, $limit - $selected->unique()->count());
        if ($remaining > 0) {
            // Pull a pool larger than remaining to allow applying caps client-side
            $poolSize = $remaining * 5;
            $poolIds = Product::query()
                ->when($families->isNotEmpty(), fn ($q) => $q->whereIn('primary_platform_family', $families->all()))
                ->whereNotIn('id', $selected->unique()->values()->all())
                ->orderByDesc('popularity_score')
                ->orderByDesc('updated_at')
                ->limit($poolSize)
                ->pluck('id');

            // Iterate pool and add while respecting per-family max caps
            foreach ($poolIds as $id) {
                if ($selected->unique()->count() >= $limit) {
                    break;
                }
                $family = Product::query()->where('id', $id)->value('primary_platform_family');
                $cap = $maxPerFamily[$family] ?? null;
                $current = $selectedCounts[$family] ?? 0;
                if ($cap !== null && $current >= $cap) {
                    continue; // skip, cap reached
                }
                $selected->push($id);
                $selectedCounts[$family] = $current + 1;
            }
        }

        return $selected->unique()->take($limit)->values()->all();
    }

    /**
     * @return Collection<int, array{method:string,url:string,params:array}>
     */
    /**
     * @return Collection<int, array{method:string,url:string,params:array<string, mixed>}>  
     */
    protected function buildProbeList(): Collection
    {
        $list = collect();

        // RAWG probe (popular games)
        if (config('media.providers.rawg.enabled', true)) {
            if (blank(config('media.providers.rawg.options.api_key'))) {
                $list->push([
                    'method' => 'ENV',
                    'url' => 'RAWG_API_KEY',
                    'params' => [],
                ]);
            }
            $list->push([
                'method' => 'GET',
                'url' => rtrim(config('media.providers.rawg.options.base_url', 'https://api.rawg.io/api'), '/').'/games',
                'params' => array_filter([
                    'key' => config('media.providers.rawg.options.api_key'),
                    'page_size' => 1,
                ]),
            ]);
        }

        // GiantBomb probe (search)
        if (config('media.providers.giantbomb.enabled', true)) {
            if (blank(config('media.providers.giantbomb.options.api_key'))) {
                $list->push([
                    'method' => 'ENV',
                    'url' => 'GIANTBOMB_API_KEY',
                    'params' => [],
                ]);
            }
            $list->push([
                'method' => 'GET',
                'url' => rtrim(config('media.providers.giantbomb.options.base_url', 'https://www.giantbomb.com/api'), '/').'/search/',
                'params' => [
                    'api_key' => config('media.providers.giantbomb.options.api_key'),
                    'format' => 'json',
                    'query' => 'Zelda',
                    'resources' => 'game',
                    'limit' => 1,
                ],
            ]);
        }

        // NEXARDA probe (media)
        if (config('media.providers.nexarda.enabled', true)) {
            $nexardaApiKey = config('media.providers.nexarda.options.api_key');
            if (blank($nexardaApiKey)) {
                    $list->push([
                        'method' => 'ENV',
                        // Accept either standard or CATALOGUE_* naming
                        'url' => 'NEXARDA_API_KEY|CATALOGUE_NEXARDA_API_KEY',
                        'params' => [],
                    ]);
            }
            $list->push([
                'method' => 'GET',
                // Use lightweight status endpoint to avoid 404s
                'url' => rtrim(config('media.providers.nexarda.options.base_url', 'https://www.nexarda.com/api/v3'), '/').'/status',
                'params' => [],
            ]);
            // Quick search probe (only if API key is provided)
            if (filled($nexardaApiKey)) {
                $list->push([
                    'method' => 'GET',
                    'url' => rtrim(config('media.providers.nexarda.options.base_url', 'https://www.nexarda.com/api/v3'), '/').'/search',
                    'params' => array_filter([
                        'api_key' => $nexardaApiKey,
                        'type' => 'games',
                        'q' => 'zelda',
                        'limit' => 1,
                    ]),
                ]);
            }
        }

        // ITAD probe (search)
        if (config('pricing.providers.itad.enabled', true)) {
            if (blank(config('pricing.providers.itad.options.api_key'))) {
                $list->push([
                    'method' => 'ENV',
                    'url' => 'ITAD_API_KEY',
                    'params' => [],
                ]);
            }
            $list->push([
                'method' => 'GET',
                // Use current v1 search endpoint (may still require auth; treated as non-blocking)
                'url' => 'https://api.isthereanydeal.com/games/search/v1',
                'params' => [
                    'title' => 'zelda',
                    'results' => 1,
                ],
            ]);
        }

        // NEXARDA pricing probe (feed)
        if (config('pricing.providers.nexarda.enabled', true)) {
            $nexardaApiKey = config('pricing.providers.nexarda.options.api_key');
            if (blank($nexardaApiKey)) {
                    $list->push([
                        'method' => 'ENV',
                        // Accept either standard or CATALOGUE_* naming
                        'url' => 'NEXARDA_API_KEY|CATALOGUE_NEXARDA_API_KEY',
                        'params' => [],
                    ]);
            }
            // Only probe feed if API key is present to avoid unnecessary HTTP failures
            if (filled($nexardaApiKey)) {
                $list->push([
                    'method' => 'GET',
                    'url' => rtrim(config('pricing.providers.nexarda.options.base_url', 'https://www.nexarda.com/api/v3'), '/').'/feed',
                    'params' => array_filter([
                        'key' => $nexardaApiKey,
                        'limit' => 1,
                    ]),
                ]);
            }
        }

        return $list;
    }

    /**
     * @param  Collection<int, array{method:string,url:string,params:array}>  $probes
     * @return Collection<int, array{method:string,url:string,error:string}>
     */
    /**
     * @param  Collection<int, array{method:string,url:string,params:array<string, mixed>}>  $probes
     * @return Collection<int, array{method:string,url:string,error:string}>
     */
    protected function probeEndpoints(Collection $probes): Collection
    {
        $failed = collect();

        foreach ($probes as $probe) {
            $method = strtoupper($probe['method']);
            $url = $probe['url'];
            $params = $probe['params'] ?? [];

            // Treat ENV entries as configuration validation, not HTTP calls
            if ($method === 'ENV') {
                // Support multiple candidates separated by '|'
                $candidates = array_map('trim', explode('|', (string) $url));
                $ok = false;
                foreach ($candidates as $key) {
                    $val = env($key);
                    if (is_string($val) && $val !== '') {
                        $ok = true;
                        break;
                    }
                }
                if (! $ok) {
                    $failed->push(['method' => 'ENV', 'url' => $url, 'error' => 'Missing required .env key (checked: '.implode(', ', $candidates).')']);
                }
                continue;
            }

            try {
                $resp = Http::timeout(12)
                    ->withUserAgent(config('media.providers.giantbomb.options.user_agent', 'GameCompareBot/1.0'))
                    ->retry(1, 200)
                    ->withTelemetry('probe')
                    ->acceptJson()
                    ->{$method === 'GET' ? 'get' : 'get'}($url, $params);
                if ($resp->failed()) {
                    $failed->push(['method' => $method, 'url' => $url, 'error' => 'HTTP '.$resp->status()]);
                }
            } catch (\Throwable $e) {
                Log::warning('providers.probe_failed', ['url' => $url, 'error' => $e->getMessage()]);
                $failed->push(['method' => $method, 'url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return $failed;
    }

    protected function inferCategory(?string $name, ?string $platform, ?string $family): string
    {
        $hay = strtolower(($name ?? '').' '.($platform ?? '').' '.($family ?? ''));
        $hardwareHints = ['console', 'controller', 'headset', 'dock', 'joy-con', 'pro controller', 'dualshock', 'dual sense', 'dualshock', 'series x', 'series s', 'ps5', 'ps4', 'xbox', 'switch oled', 'playstation 5', 'xbox series x', 'xbox series s'];
        foreach ($hardwareHints as $hint) {
            if (str_contains($hay, $hint)) {
                return 'Hardware';
            }
        }

        return 'Game';
    }

    /**
     * Ensure the catalogue has at least $limit products for the specified families.
     */
    protected function ensureCatalogueSize(CatalogueAggregator $aggregator, int $limit, int $windowDays, Collection $families): void
    {
        $current = Product::query()
            ->when($families->isNotEmpty(), fn ($q) => $q->whereIn('primary_platform_family', $families->all()))
            ->count();

        if ($current >= $limit) {
            return;
        }

        $needed = $limit - $current;
        $seedPerRunOpt = (int) $this->option('seed-per-run');
        $maxPerRun = max(50, $seedPerRunOpt > 0 ? $seedPerRunOpt : (int) config('catalogue.trending_seed_limit', 200));
        $attempts = 0;
        $maxAttempts = 10; // allow more sweeping attempts
        $noGainStreak = 0;

        while ($needed > 0 && $attempts < $maxAttempts) {
            $attempts++;
            $perRun = min($maxPerRun, $needed);
            // Temporarily bump the seed settings
            $rawgKey = config('media.providers.rawg.options.api_key');
            config([
                'catalogue.trending_seed_limit' => $perRun,
                // Expand the window with each attempt to include older titles
                'catalogue.trending_seed_window_days' => $windowDays + (($attempts - 1) * 365),
                // Sweep through sources: paginate mirror and nexarda across attempts; allow RAWG to always fetch when key is present
                'catalogue.sources.rawg.always_fetch' => filled($rawgKey),
                // If GiantBomb key is configured, allow it to fetch even if remaining is 0
                'catalogue.sources.giantbomb.always_fetch' => filled(config('media.providers.giantbomb.options.api_key')),
                // Broaden NEXARDA intake by lifting the minimum score filter
                'catalogue.sources.nexarda.min_score' => 0,
                'catalogue.sources.thegamesdb_mirror.offset' => max(0, ($attempts - 1)) * (int) (config('catalogue.sources.thegamesdb_mirror.limit', 150) ?: 150),
                'catalogue.sources.nexarda.page' => ($attempts),
                'catalogue.sources.nexarda.offset' => max(0, ($attempts - 1)) * (int) (config('catalogue.sources.nexarda.limit', 150) ?: 150),
                // Also sweep NEXARDA feed if enabled
                'catalogue.sources.nexarda_feed.page' => ($attempts),
                'catalogue.sources.nexarda_feed.offset' => max(0, ($attempts - 1)) * (int) (config('catalogue.sources.nexarda_feed.limit', 150) ?: 150),
            ]);

            (new FetchTopGamesJob)->handle(new \App\Services\TokenBucketRateLimiter(), $aggregator, app('db'));

            $after = Product::query()
                ->when($families->isNotEmpty(), fn ($q) => $q->whereIn('primary_platform_family', $families->all()))
                ->count();

            $gained = $after - $current;
            $current = $after;
            $needed = max(0, $limit - $current);

            $this->line(sprintf('  → catalogue size now %d (+%d); need %d more...', $current, max(0, $gained), $needed));
            $noGainStreak = $gained <= 0 ? $noGainStreak + 1 : 0;
            if ($noGainStreak >= 2) {
                // After two consecutive no-gain attempts, likely exhausted
                break;
            }
        }

        // If still short, run family-focused passes to boost console coverage
        if ($needed > 0 && $families->isNotEmpty()) {
            foreach ($families as $family) {
                if ($needed <= 0) break;

                $perRun = min($maxPerRun, $needed);

                config([
                    'catalogue.trending_seed_limit' => $perRun,
                    // Favor older windows to pull in back catalogue for consoles
                    'catalogue.trending_seed_window_days' => $windowDays + 365,
                    // Focus TGDB mirror on this family; and give Nexarda a platform hint
                    'catalogue.sources.thegamesdb_mirror.family' => $family,
                    'catalogue.sources.nexarda.platform' => $family,
                    'catalogue.sources.nexarda_feed.page' => 2,
                    'catalogue.sources.nexarda_feed.offset' => 0,
                    // Temporarily reduce RAWG influence to avoid PC-heavy skew during focused pass
                    'catalogue.sources.rawg.enabled' => false,
                    'catalogue.sources.giantbomb.always_fetch' => filled(config('media.providers.giantbomb.options.api_key')),
                    // Keep broad intake
                    'catalogue.sources.nexarda.min_score' => 0,
                ]);

                (new FetchTopGamesJob)->handle(new \App\Services\TokenBucketRateLimiter(), $aggregator, app('db'));

                $after = Product::query()
                    ->when($families->isNotEmpty(), fn ($q) => $q->whereIn('primary_platform_family', $families->all()))
                    ->count();

                $gained = $after - $current;
                $current = $after;
                $needed = max(0, $limit - $current);
                $this->line(sprintf('  → focused %s pass: catalogue size now %d (+%d); need %d more...', $family, $current, max(0, $gained), $needed));
            }

            // Re-enable RAWG after focused passes
            config(['catalogue.sources.rawg.enabled' => true]);
        }

        // Optional fallback: seed from known curated list when requested
        if ($needed > 0 && $this->option('seed-known')) {
            $added = $this->seedFromKnownList($families, $needed);
            $current += $added;
            $needed = max(0, $limit - $current);
            $this->line(sprintf('  → seeded %d from curated list; catalogue now %d; need %d more...', $added, $current, $needed));
        }
    }

    /**
     * Seed products from config/thegamesdb_mirror.php curated list to quickly increase catalogue size.
     */
    protected function seedFromKnownList(Collection $families, int $targetAdd): int
    {
        $items = (array) (config('thegamesdb_mirror.games') ?? []);
        if ($items === []) {
            return 0;
        }

        $added = 0;
        foreach ($items as $entry) {
            if ($added >= $targetAdd) {
                break;
            }
            if (! is_array($entry)) {
                continue;
            }
            $title = (string) ($entry['title'] ?? '');
            $slug = (string) ($entry['slug'] ?? '');
            $platform = (string) ($entry['platform'] ?? '');
            $category = (string) ($entry['category'] ?? 'Game');

            if ($title === '' || $slug === '') {
                continue;
            }

            $family = $this->determinePlatformFamilyFromString($platform);
            if ($families->isNotEmpty() && ($family === null || ! $families->contains($family))) {
                continue; // skip if outside requested families
            }

            $exists = Product::query()->where('slug', $slug)->exists();
            if ($exists) {
                continue;
            }

            $product = new Product();
            $product->slug = $slug;
            $product->name = $title;
            $product->platform = $platform;
            $product->category = $category;
            $product->metadata = ['source' => 'curated_list'];
            $product->uid = hash('sha256', strtolower($title).'|'.('unknown').'|'.($family ?? 'unknown'));
            $product->primary_platform_family = $family ?? 'pc';
            $product->popularity_score = 0.6;
            $product->rating = 80;
            $product->freshness_score = 0.5;
            $product->external_ids = [];
            $product->synopsis = null;
            $product->save();

            $added++;
        }

        return $added;
    }

    protected function determinePlatformFamilyFromString(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $normalized = strtolower($platform);

        return match (true) {
            str_contains($normalized, 'playstation') || str_contains($normalized, 'ps') => 'playstation',
            str_contains($normalized, 'xbox') => 'xbox',
            str_contains($normalized, 'switch') || str_contains($normalized, 'nintendo') || str_contains($normalized, 'wii') => 'nintendo',
            str_contains($normalized, 'pc') || str_contains($normalized, 'windows') || str_contains($normalized, 'steam') => 'pc',
            default => null,
        };
    }

    /**
     * Load docs/api_providers.json and return provider index keyed by `key`.
     *
     * @return array{list: array<int, array<string, mixed>>, index: array<string, array<string, mixed>>}
     */
    protected function loadProviderRegistry(): array
    {
        $path = base_path('docs/api_providers.json');
        if (! is_file($path)) {
            $this->warn('docs/api_providers.json not found. Skipping registry validation.');

            return ['list' => [], 'index' => []];
        }

        $json = file_get_contents($path);
        $data = json_decode($json ?: '[]', true);
        if (! is_array($data)) {
            $this->warn('api_providers.json could not be parsed as JSON.');

            return ['list' => [], 'index' => []];
        }

        // Flatten any top-level object with known keys into a unified list
        $list = [];
        if (array_is_list($data)) {
            $list = $data;
        } else {
            foreach (['providers', 'aggregators'] as $segment) {
                if (isset($data[$segment]) && is_array($data[$segment])) {
                    foreach ($data[$segment] as $item) {
                        if (is_array($item)) {
                            $list[] = $item;
                        }
                    }
                }
            }
        }

        $index = [];
        foreach ($list as $item) {
            if (is_array($item) && isset($item['key']) && is_string($item['key'])) {
                $index[$item['key']] = $item;
            }
        }

        return ['list' => $list, 'index' => $index];
    }

    /**
     * @param array{list: array<int, array<string, mixed>>, index: array<string, array<string, mixed>>} $registry
     * @param array<int, string> $requiredKeys
     */
    protected function validateRequiredProviders(array $registry, array $requiredKeys): void
    {
        $missing = collect($requiredKeys)->reject(fn ($key) => isset($registry['index'][$key]))->values();
        if ($missing->isNotEmpty()) {
            $this->warn('api_providers.json is missing required keys: '.implode(',', $missing->all()));
        }
    }
}