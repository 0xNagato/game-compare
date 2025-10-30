<?php

namespace App\Services\TheGamesDb;

use App\Models\VendorHttpCache;
use App\Services\TheGamesDb\Exceptions\TheGamesDbApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TheGamesDbApiClient
{
    private const PROVIDER_KEY = 'thegamesdb';

    public function __construct(
        private readonly ?string $publicKey = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $userAgent = null,
        private readonly int $timeout = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function searchByName(string $name, bool $usePrivateKey = false, array $params = [], ?string $cacheKey = null): ?array
    {
        $query = array_merge([
            'name' => $name,
        ], $params);

        return $this->request('Games/ByGameName', $query, $usePrivateKey, $cacheKey ?? 'games.by_name:'.md5($name));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function updatesSince(?Carbon $since, array $params = [], ?string $cacheKey = null): ?array
    {
        $query = $params;

        if ($since) {
            $query['since'] = $since->toIso8601String();
        }

        return $this->request('Games/Updates', $query, false, $cacheKey ?? 'games.updates');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function byPlatform(int $platformId, bool $usePrivateKey = false, array $params = [], ?string $cacheKey = null): ?array
    {
        $query = array_merge([
            'id' => $platformId,
        ], $params);

        return $this->request('Games/ByPlatformID', $query, $usePrivateKey, $cacheKey ?? 'games.by_platform:'.$platformId);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function byIds(array $ids, bool $usePrivateKey = false, array $params = [], ?string $cacheKey = null): ?array
    {
        $filtered = collect($ids)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($filtered === []) {
            return null;
        }

        $query = array_merge([
            'id' => implode(',', $filtered),
        ], $params);

        return $this->request('Games/ByGameID', $query, $usePrivateKey, $cacheKey ? $cacheKey.':'.md5(implode(',', $filtered)) : null);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    protected function request(string $endpoint, array $params, bool $usePrivateKey, ?string $cacheKey = null): ?array
    {
        $baseUrl = rtrim($this->baseUrl ?? config('services.thegamesdb.base_url', 'https://api.thegamesdb.net'), '/');
        $key = $this->resolveApiKey($usePrivateKey);

        $query = array_filter(array_merge($params, [
            'apikey' => $key,
        ]), static fn ($value) => $value !== null && $value !== '' && $value !== []);

        $headers = [];
        $cache = null;

        if ($cacheKey) {
            $cache = VendorHttpCache::firstOrNew([
                'provider' => self::PROVIDER_KEY,
                'endpoint' => $cacheKey,
            ]);

            if ($cache->exists) {
                if ($cache->etag) {
                    $headers['If-None-Match'] = $cache->etag;
                }

                if ($cache->last_modified_at) {
                    $headers['If-Modified-Since'] = $cache->last_modified_at->toRfc7231String();
                }
            }
        }

        $headers['Accept'] = 'application/json';

        if ($this->userAgent || config('services.thegamesdb.user_agent')) {
            $headers['User-Agent'] = $this->userAgent ?? config('services.thegamesdb.user_agent');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->baseUrl($baseUrl)
                ->withHeaders($headers)
                ->get($this->normalizeEndpoint($endpoint), $query);
        } catch (ConnectionException $exception) {
            throw new TheGamesDbApiException('TheGamesDB API request failed due to a connection error.', previous: $exception);
        } catch (RequestException $exception) {
            throw new TheGamesDbApiException('TheGamesDB API request failed.', previous: $exception);
        }

        if ($response->status() === 304) {
            if ($cache) {
                $cache->last_checked_at = now();
                $cache->save();
            }

            return null;
        }

        if ($response->failed()) {
            $this->logFailure($endpoint, $query, $response);

            throw new TheGamesDbApiException(sprintf('TheGamesDB API request failed with status %s.', $response->status()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new TheGamesDbApiException('TheGamesDB API response payload was invalid.');
        }

        if ($cache) {
            $cache->etag = $response->header('ETag');
            $cache->last_modified_at = $this->parseLastModifiedHeader($response);
            $cache->last_checked_at = now();
            $cache->metadata = array_filter([
                'request_query' => $query,
                'endpoint' => $endpoint,
            ]);
            $cache->save();
        }

        return $payload;
    }

    protected function normalizeEndpoint(string $endpoint): string
    {
        return ltrim($endpoint, '/');
    }

    protected function parseLastModifiedHeader(Response $response): ?Carbon
    {
        $value = $response->header('Last-Modified');

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function resolveApiKey(bool $usePrivateKey): string
    {
        $keys = $usePrivateKey
            ? [
                $this->privateKey,
                config('services.thegamesdb.private_key'),
                env('THEGAMESDB_PRIVATE_KEY'),
            ]
            : [
                $this->publicKey,
                config('services.thegamesdb.public_key'),
                env('THEGAMESDB_PUBLIC_KEY'),
                $this->privateKey,
                config('services.thegamesdb.private_key'),
                env('THEGAMESDB_PRIVATE_KEY'),
            ];

        $key = collect($keys)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->first();

        if (! $key) {
            throw new TheGamesDbApiException('A TheGamesDB API key is required.');
        }

        return $key;
    }

    protected function logFailure(string $endpoint, array $query, Response $response): void
    {
        Log::warning('thegamesdb.api_failure', [
            'endpoint' => $endpoint,
            'query' => $query,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }
}
