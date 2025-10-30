<?php

namespace App\Services\Media\Support;

use BadMethodCallException;
use Illuminate\Support\Facades\Cache;

class RawgRateLimiter
{
    public static function await(int $allowedPerMinute): void
    {
        if ($allowedPerMinute <= 0) {
            return;
        }

        $intervalMicros = (int) ceil(60_000_000 / max(1, $allowedPerMinute));

        try {
            Cache::lock('rawg:throttle-lock', 5)->block(5, function () use ($intervalMicros): void {
                $nextAllowedKey = 'rawg:throttle:next';
                $nowMicros = (int) floor(microtime(true) * 1_000_000);
                $nextAllowedMicros = (int) Cache::get($nextAllowedKey, $nowMicros);

                if ($nextAllowedMicros > $nowMicros) {
                    usleep($nextAllowedMicros - $nowMicros);
                    $nowMicros = (int) floor(microtime(true) * 1_000_000);
                }

                $nextMicros = $nowMicros + $intervalMicros;
                Cache::put($nextAllowedKey, $nextMicros, now()->addSeconds(10));
            });
        } catch (BadMethodCallException $exception) {
            // Fallback for cache stores without lock support.
            usleep($intervalMicros);
        }
    }
}
