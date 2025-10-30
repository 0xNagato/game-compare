<?php

namespace App\Services\Catalogue;

use App\Services\Catalogue\DTOs\TrendingGameData;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NexardaTrendingImporter
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly int $timeout = 12,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, TrendingGameData>
     */
    public function fetch(int $limit = 60, ?int $windowDays = null, array $options = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $apiKey = $this->apiKey();
        $baseUrl = rtrim($this->baseUrl() ?? 'https://www.nexarda.com/api/v3', '/');

        $windowDays = $windowDays ?? (int) config('catalogue.trending_seed_window_days', 180);
        $start = Carbon::now()->subDays(max(30, $windowDays))->toDateString();

        $client = Http::timeout($this->timeout)
            ->retry(2, 250)
            ->withTelemetry('catalogue.nexarda')
            ->acceptJson()
            ->baseUrl($baseUrl);

        if ($apiKey) {
            $client = $client->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);
        }

        $query = array_filter([
            'key' => $apiKey,
            'limit' => min(100, max(1, $limit)),
            'sort' => $options['sort'] ?? 'popularity_desc',
            'min_release_date' => $options['min_release_date'] ?? $start,
            'platform' => $options['platform'] ?? null,
            // Support simple pagination parameters if provided by caller
            'page' => $options['page'] ?? null,
            'offset' => $options['offset'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        Log::info('catalogue.sources.nexarda_request', [
            'url' => rtrim($baseUrl, '/').'/games/list',
            'params' => Arr::except($query, ['key']),
            'limit' => $limit,
        ]);

        $response = $client->get('/games/list', $query);

        if ($response->failed()) {
            throw new RuntimeException(sprintf('Nexarda trending request failed with status %s.', $response->status()));
        }

        $payload = $response->json();

        $items = Arr::get($payload, 'data.items', Arr::get($payload, 'data', Arr::get($payload, 'results', [])));

        if (is_array($items) && Arr::isAssoc($items)) {
            $items = [$items];
        }

        if (! is_array($items)) {
            return collect();
        }

        $minScore = (float) ($options['min_score'] ?? 0);

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->filter(function (array $item) use ($minScore) {
                if ($minScore <= 0) {
                    return true;
                }

                $score = (float) Arr::get($item, 'score', Arr::get($item, 'rating', 0));

                return $score >= $minScore;
            })
            ->map(fn (array $item) => TrendingGameData::fromNexarda($item))
            ->filter(fn (TrendingGameData $game) => Str::of($game->slug)->isNotEmpty())
            ->values()
            ->take($limit);
    }

    private function apiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        $configured = config('media.providers.nexarda.options.api_key') ?: env('NEXARDA_API_KEY');

        return filled($configured) ? $configured : null;
    }

    private function baseUrl(): ?string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        return config('media.providers.nexarda.options.base_url') ?: env('NEXARDA_BASE_URL');
    }
}
