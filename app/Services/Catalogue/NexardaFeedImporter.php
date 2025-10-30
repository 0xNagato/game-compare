<?php

namespace App\Services\Catalogue;

use App\Services\Catalogue\DTOs\TrendingGameData;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NexardaFeedImporter
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly int $timeout = 12,
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return Collection<int, TrendingGameData>
     */
    public function fetch(int $limit = 200, array $options = []): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        // Prefer local offline catalogue file when available to avoid network and respect user's request
        $localPath = $options['file']
            ?? (config('catalogue.nexarda.local_catalogue_file') ?: null)
            ?? base_path('nexarda_product_catalogue.json');
        if (is_string($localPath) && $localPath !== '' && is_file($localPath)) {
            try {
                $json = json_decode((string) file_get_contents($localPath), true);
            } catch (\Throwable $e) {
                $json = null;
            }

            if (is_array($json)) {
                // The catalogue may be under various keys; normalize to a flat items array
                $candidates = [
                    Arr::get($json, 'items'),
                    Arr::get($json, 'data.items'),
                    Arr::get($json, 'games'),
                    Arr::get($json, 'consoles'),
                    $json,
                ];

                $items = collect($candidates)
                    ->filter(fn ($v) => is_array($v))
                    ->flatMap(fn ($arr) => is_array($arr) && Arr::isAssoc($arr) ? [$arr] : $arr)
                    ->filter(fn ($v) => is_array($v))
                    ->values();

                if ($items->isNotEmpty()) {
                    Log::info('catalogue.sources.nexarda_local_catalogue', [
                        'path' => $localPath,
                        'count' => $items->count(),
                    ]);

                    return $items
                        ->take($limit)
                        ->map(fn (array $item) => TrendingGameData::fromNexarda($item))
                        ->values();
                }
            }
        }

        $apiKey = $this->apiKey();
        $baseUrl = rtrim($this->baseUrl() ?? 'https://www.nexarda.com/api/v3', '/');

        $client = Http::timeout($this->timeout)
            ->retry(2, 250)
            ->withTelemetry('catalogue.nexarda_feed')
            ->acceptJson()
            ->baseUrl($baseUrl);

        // Some deployments may allow header-based auth; main feed requires the `key` query parameter.
        if ($apiKey) {
            $client = $client->withHeaders(['X-Api-Key' => $apiKey]);
        }

        $query = array_filter([
            'key' => $apiKey,
            'limit' => min(200, max(1, $limit)),
            'page' => $options['page'] ?? null,
            'offset' => $options['offset'] ?? null,
            'since' => $options['since'] ?? null, // ISO timestamp to get recent changes
        ], fn ($v) => $v !== null && $v !== '');

        $response = $client->get('/feed', $query);
        if ($response->failed()) {
            Log::warning('catalogue.sources.nexarda_feed_failed', [
                'status' => $response->status(),
                'params' => Arr::except($query, ['key']),
            ]);
            return collect();
        }

        $payload = $response->json();
    $items = Arr::get($payload, 'data.items', Arr::get($payload, 'games', Arr::get($payload, 'data', Arr::get($payload, 'results', []))));

        if (is_array($items) && Arr::isAssoc($items)) {
            $items = [$items];
        }

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => TrendingGameData::fromNexarda($item))
            ->values()
            ->take($limit);
    }

    private function apiKey(): ?string
    {
        if ($this->apiKey !== null) {
            return $this->apiKey;
        }

        // Prefer dedicated FEED key if provided (via config/catalogue.php)
        $configured = config('catalogue.nexarda.feed_key')
            ?: config('media.providers.nexarda.options.api_key')
            ?: env('NEXARDA_API_KEY');

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
