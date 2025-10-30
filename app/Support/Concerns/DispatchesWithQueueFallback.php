<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait DispatchesWithQueueFallback
{
    protected function dispatchWithQueueFallback(callable $queueDispatch, callable $syncDispatch, string $context): void
    {
        try {
            $queueDispatch();
        } catch (Throwable $exception) {
            if (! $this->isQueueConnectionException($exception)) {
                throw $exception;
            }

            Log::warning("{$context}.queue_connection_failed", [
                'error' => $exception->getMessage(),
            ]);

            $syncDispatch();
        }
    }

    protected function isQueueConnectionException(Throwable $exception): bool
    {
        if (is_a($exception, 'RedisException')) {
            return true;
        }

        if (is_a($exception, 'Predis\\Connection\\ConnectionException')) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'connection refused');
    }
}
