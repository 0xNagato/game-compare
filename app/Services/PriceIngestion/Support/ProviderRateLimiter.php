<?php

namespace App\Services\PriceIngestion\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ProviderRateLimiter
{
    public function __construct(private readonly string $prefix = 'price_ingest:rate_limit:') {}

    public function consume(
        string $provider,
        int $tokens,
        int $dailyLimit,
        ?int $perMinuteLimit = null,
        ?Carbon $now = null,
    ): bool {
        $now ??= now();

        if ($tokens <= 0) {
            return true;
        }

        $dailyLimit = max($dailyLimit, 1);
        $perMinuteLimit = $perMinuteLimit !== null && $perMinuteLimit > 0 ? $perMinuteLimit : null;

        $lock = Cache::lock($this->lockKey($provider), 5);

        try {
            $lock->block(3);
        } catch (LockTimeoutException) {
            return false;
        }

        try {
            $dailyKey = $this->dailyKey($provider, $now);
            $minuteKey = $this->minuteKey($provider, $now);

            $currentDaily = (int) Cache::get($dailyKey, 0);
            $currentMinute = (int) Cache::get($minuteKey, 0);

            if ($currentDaily + $tokens > $dailyLimit) {
                return false;
            }

            if ($perMinuteLimit !== null && $currentMinute + $tokens > $perMinuteLimit) {
                return false;
            }

            $dailyExpiration = $now->copy()->endOfDay()->addMinutes(5);
            $minuteExpiration = $now->copy()->addMinutes(2);

            Cache::put($dailyKey, $currentDaily + $tokens, $dailyExpiration);
            Cache::put($minuteKey, $currentMinute + $tokens, $minuteExpiration);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{daily: int, per_minute: int}
     */
    public function usage(string $provider, ?Carbon $now = null): array
    {
        $now ??= now();

        return [
            'daily' => (int) Cache::get($this->dailyKey($provider, $now), 0),
            'per_minute' => (int) Cache::get($this->minuteKey($provider, $now), 0),
        ];
    }

    protected function lockKey(string $provider): string
    {
        return $this->prefix.$provider.':lock';
    }

    protected function dailyKey(string $provider, Carbon $now): string
    {
        return $this->prefix.$provider.':daily:'.$now->format('Ymd');
    }

    protected function minuteKey(string $provider, Carbon $now): string
    {
        return $this->prefix.$provider.':minute:'.$now->format('YmdHi');
    }
}
