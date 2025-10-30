<?php

namespace App\Services;

use App\Models\RateLimit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TokenBucketRateLimiter
{
    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public function attempt(string $provider, float $maxRps, int $burst): array
    {
        return DB::transaction(function () use ($provider, $maxRps, $burst) {
            $limit = RateLimit::query()->find($provider);

            $now = CarbonImmutable::now();
            $tokens = $limit?->tokens ?? $burst;
            $lastRefill = $limit?->last_refill_at ? CarbonImmutable::parse($limit->last_refill_at) : $now;

            $elapsed = max(0.0, (float) $lastRefill->diffInSeconds($now));
            $refilled = min($burst, $tokens + ($elapsed * $maxRps));

            if ($refilled >= 1) {
                $tokens = $refilled - 1;
                $allowed = true;
                $retryAfter = 0;
            } else {
                $allowed = false;
                $needed = 1 - $refilled;
                $retryAfter = (int) ceil($needed / max($maxRps, 0.001));
                $tokens = $refilled;
            }

            RateLimit::query()->updateOrCreate(
                ['provider' => $provider],
                [
                    'tokens' => min($burst, $tokens),
                    'last_refill_at' => $now,
                ]
            );

            return [
                'allowed' => $allowed,
                'retry_after' => $retryAfter,
            ];
        }, 5);
    }
}
