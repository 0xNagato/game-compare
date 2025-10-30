<?php

namespace App\Services\GameData;

use App\Services\GameData\Contracts\GameProvider;
use App\Services\GameData\Contracts\RateLimitedProvider;
use App\Services\GameData\DTO\FetchContextDTO;
use App\Services\GameData\DTO\GameDetailDTO;
use App\Services\GameData\DTO\GameUid;
use App\Services\GameData\DTO\MediaBundleDTO;
use App\Services\GameData\DTO\MediaScopeDTO;
use App\Services\GameData\DTO\OfferCollectionDTO;
use App\Services\GameData\DTO\OfferFilterDTO;
use App\Services\GameData\DTO\PlatformCollectionDTO;
use App\Services\GameData\DTO\TopQueryDTO;
use App\Services\GameData\DTO\TopResultDTO;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class ProviderManager
{
    /**
     * @var Collection<int, class-string<GameProvider>>
     */
    protected Collection $providers;

    public function __construct(
        protected readonly Container $container,
        protected readonly CacheRepository $cache,
        iterable $providers,
    ) {
        $this->providers = collect($providers)->map(fn (string $concrete) => $concrete);
    }

    public function searchTop(TopQueryDTO $query): TopResultDTO
    {
        $provider = $this->selectProvider('top_search');

        return $provider->searchTop($query);
    }

    public function fetchGame(GameUid $uid, ?FetchContextDTO $context = null): GameDetailDTO
    {
        $provider = $this->selectProvider('game_detail');

        return $provider->fetchGame($uid, $context);
    }

    public function fetchMedia(GameUid $uid, ?MediaScopeDTO $scope = null): MediaBundleDTO
    {
        $provider = $this->selectProvider('media_fetch', preferMediaQuality: true);

        return $provider->fetchMedia($uid, $scope);
    }

    public function listPlatforms(): PlatformCollectionDTO
    {
        $provider = $this->selectProvider('platform_list');

        return $provider->listPlatforms();
    }

    public function fetchOffers(GameUid $uid, ?OfferFilterDTO $filter = null): OfferCollectionDTO
    {
        $provider = $this->selectProvider('offer_fetch');

        return $provider->fetchOffers($uid, $filter);
    }

    protected function selectProvider(string $intent, bool $preferMediaQuality = false): GameProvider
    {
        $ranked = $this->providers
            ->map(fn (string $concrete) => $this->container->make($concrete))
            ->filter(fn (GameProvider $provider) => $this->quotaAllows($provider))
            ->map(function (GameProvider $provider) use ($preferMediaQuality) {
                $health = $this->providerHealth($provider);

                $score = $preferMediaQuality
                    ? (0.5 * $health->mediaQuality + 0.3 * $health->successRate + 0.2 * $health->quotaRemaining)
                    : (0.4 * $health->successRate + 0.4 * $health->quotaRemaining + 0.2 * $health->mediaQuality);

                return [$provider, $score, $health];
            })
            ->sortByDesc(fn (array $result) => $result[1])
            ->values();

        if ($ranked->isEmpty()) {
            throw new RuntimeException('No providers available for intent ['.$intent.'].');
        }

        /** @var GameProvider $provider */
        [$provider] = $ranked->first();

        $this->reserveQuota($provider);

        return $provider;
    }

    protected function quotaAllows(GameProvider $provider): bool
    {
        if (! $provider instanceof RateLimitedProvider) {
            return true;
        }

        $profile = $provider->quotaProfile();

        $keyTokens = sprintf('provider:%s:tokens', $provider->providerKey());
        $keyRefill = sprintf('provider:%s:refill', $provider->providerKey());

        $now = microtime(true);
        $lua = <<<'LUA'
local tokens = tonumber(redis.call('get', KEYS[1]) or 0)
local last_refill = tonumber(redis.call('get', KEYS[2]) or ARGV[1])
local rate = tonumber(ARGV[2])
local burst = tonumber(ARGV[3])
local elapsed = tonumber(ARGV[1]) - last_refill
if elapsed > 0 then
  local accrued = math.floor(elapsed * rate)
  tokens = math.min(burst, tokens + accrued)
end
if tokens <= 0 then
  redis.call('set', KEYS[2], ARGV[1])
  return 0
end
redis.call('set', KEYS[1], tokens - 1)
redis.call('set', KEYS[2], ARGV[1])
return 1
LUA;

        $allowed = Redis::eval($lua, 2, $keyTokens, $keyRefill, $now, $profile->maxRequestsPerSecond, $profile->burst);

        return (int) $allowed === 1;
    }

    protected function reserveQuota(GameProvider $provider): void
    {
        // Placeholder for metrics/logging; actual reservation handled in quotaAllows()
    }

    protected function providerHealth(GameProvider $provider): ProviderHealth
    {
        $cacheKey = sprintf('provider:%s:health', $provider->providerKey());

        /** @var ProviderHealth|null $cached */
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof ProviderHealth) {
            return $cached;
        }

        $health = ProviderHealth::default();

        $this->cache->put($cacheKey, $health, now()->addSeconds(60));

        return $health;
    }
}
