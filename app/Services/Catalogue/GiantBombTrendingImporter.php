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

class GiantBombTrendingImporter
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $userAgent = null,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, TrendingGameData>
     */
    public function fetch(int $limit = 90, ?int $windowDays = null, array $options = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $apiKey = $this->apiKey() ?? throw new RuntimeException('GiantBomb API key is not configured.');
        $baseUrl = rtrim($this->baseUrl() ?? 'https://www.giantbomb.com/api', '/');
        $userAgent = $this->userAgent() ?? 'GameCompareBot/1.0 (portfolio use)';

        $windowDays = $windowDays ?? (int) config('catalogue.trending_seed_window_days', 180);
        $start = Carbon::now()->subDays(max(30, $windowDays))->toDateString();
        $end = Carbon::now()->toDateString();

        $pageLimit = min(100, max(1, $limit));

        $query = array_filter([
            'api_key' => $apiKey,
            'format' => 'json',
            'limit' => $pageLimit,
            'sort' => $options['sort'] ?? 'number_of_user_reviews:desc',
            'filter' => $options['filter'] ?? sprintf('original_release_date:%s|%s', $start, $end),
            'field_list' => $options['field_list'] ?? implode(',', [
                'id',
                'name',
                'deck',
                'aliases',
                'original_release_date',
                'expected_release_year',
                'expected_release_month',
                'expected_release_day',
                'site_detail_url',
                'platforms',
                'genres',
                'image',
                'number_of_user_reviews',
            ]),
        ]);

        Log::info('catalogue.sources.giantbomb_request', [
            'url' => rtrim($baseUrl, '/').'/games/',
            'params' => Arr::except($query, ['api_key']),
            'limit' => $limit,
        ]);

        $response = Http::timeout($this->timeout)
            ->retry(2, 250)
            ->baseUrl($baseUrl)
            ->withHeaders([
                'User-Agent' => $userAgent,
            ])
            ->withTelemetry('catalogue.giantbomb')
            ->acceptJson()
            ->get('/games/', $query);

        if ($response->failed()) {
            throw new RuntimeException(sprintf('GiantBomb trending request failed with status %s.', $response->status()));
        }

        $results = Arr::get($response->json(), 'results', []);

        if (! is_array($results)) {
            return collect();
        }

        $minReviews = (int) ($options['min_user_reviews'] ?? 0);

        return collect($results)
            ->filter(fn ($item) => is_array($item))
            ->filter(function (array $item) use ($minReviews) {
                if ($minReviews <= 0) {
                    return true;
                }

                $reviews = (int) Arr::get($item, 'number_of_user_reviews', 0);

                return $reviews >= $minReviews;
            })
            ->map(fn (array $item) => TrendingGameData::fromGiantBomb($item))
            ->filter(fn (TrendingGameData $game) => Str::of($game->slug)->isNotEmpty())
            ->values()
            ->take($limit);
    }

    private function apiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        return config('media.providers.giantbomb.options.api_key') ?: env('GIANTBOMB_API_KEY');
    }

    private function baseUrl(): ?string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        return config('media.providers.giantbomb.options.base_url') ?: env('GIANTBOMB_BASE_URL');
    }

    private function userAgent(): ?string
    {
        if ($this->userAgent !== null) {
            return $this->userAgent;
        }

        return config('media.providers.giantbomb.options.user_agent') ?: env('GIANTBOMB_USER_AGENT');
    }
}