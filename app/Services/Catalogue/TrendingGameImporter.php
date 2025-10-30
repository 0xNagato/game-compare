<?php

namespace App\Services\Catalogue;

use App\Services\Catalogue\DTOs\TrendingGameData;
use App\Services\Media\Support\RawgRateLimiter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TrendingGameImporter
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @return Collection<int, TrendingGameData>
     */
    public function fetch(int $limit = 200, ?int $windowDays = null): Collection
    {
        $apiKey = $this->apiKey() ?? throw new RuntimeException('RAWG API key is not configured.');
        $baseUrl = rtrim($this->baseUrl() ?? 'https://api.rawg.io/api', '/');

        if ($limit <= 0) {
            return collect();
        }

        $perPage = min(40, max(1, $limit));
        $windowDays = $windowDays ?? (int) config('catalogue.trending_seed_window_days', 180);

        $start = Carbon::now()->subDays(max(30, $windowDays))->toDateString();
        $end = Carbon::now()->toDateString();

        $results = collect();
        $page = 1;

        while ($results->count() < $limit) {
            $remaining = $limit - $results->count();
            $pageSize = min($perPage, $remaining);

            RawgRateLimiter::await($this->rateLimitPerMinute());

            $query = [
                'ordering' => '-added',
                'dates' => sprintf('%s,%s', $start, $end),
                'page' => $page,
                'page_size' => $pageSize,
                'key' => $apiKey,
            ];

            Log::info('catalogue.sources.rawg_request', [
                'url' => rtrim($baseUrl, '/').'/games',
                'params' => Arr::except($query, ['key']),
                'remaining' => $remaining,
            ]);

            $response = Http::timeout($this->timeout)
                ->retry(2, 300)
                ->baseUrl($baseUrl)
                ->withTelemetry('catalogue.rawg')
                ->acceptJson()
                ->get('/games', $query);

            if ($response->failed()) {
                // Treat transient failures as empty and continue with other sources
                Log::warning('catalogue.sources.rawg_failed', [
                    'status' => $response->status(),
                    'params' => Arr::except($query, ['key']),
                ]);
                break; // stop paging RAWG for this pass
            }

            $payload = $response->json();
            $items = data_get($payload, 'results', []);

            if (! is_array($items) || empty($items)) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $game = TrendingGameData::fromRawg($item);

                if ($results->contains(fn (TrendingGameData $existing) => Str::lower($existing->slug) === Str::lower($game->slug))) {
                    continue;
                }

                $results->push($game);

                if ($results->count() >= $limit) {
                    break 2;
                }
            }

            $page++;

            if (! data_get($payload, 'next')) {
                break;
            }
        }

        return $results;
    }

    private function apiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        return config('media.providers.rawg.options.api_key') ?: env('RAWG_API_KEY');
    }

    private function baseUrl(): ?string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        return config('media.providers.rawg.options.base_url') ?: env('RAWG_BASE_URL');
    }

    private function rateLimitPerMinute(): int
    {
        return (int) (config('media.providers.rawg.options.rate_limit_per_minute') ?: env('RAWG_REQS_PER_MIN', 0));
    }
}
