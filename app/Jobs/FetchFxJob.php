<?php

namespace App\Jobs;

use App\Services\ExchangeRate\ExchangeRateSynchronizer;
use App\Support\Concerns\HasIdempotencyKey;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchFxJob implements ShouldQueue
{
    use Dispatchable;
    use HasIdempotencyKey;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public array $context = [],
        public ?string $idempotencyKey = null,
    ) {
        $this->onQueue('fx');
        $this->idempotencyKey ??= sprintf(
            'fx:%s',
            $context['window'] ?? now()->format(DateTimeInterface::ATOM)
        );
    }

    public int $tries = 5;

    public int $timeout = 120;

    public function backoff(): array
    {
        return collect([30, 60, 120, 240, 480])
            ->map(static fn (int $seconds): int => max(15, $seconds + random_int(-15, 15)))
            ->all();
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(ExchangeRateSynchronizer $synchronizer): void
    {
        $this->applyJitter();

        Log::info('fetch_fx_job.started', [
            'context' => $this->context,
            'job' => $this->uniqueId(),
        ]);

        $synchronizer->synchronize($this->context);

        Log::info('fetch_fx_job.finished', [
            'job' => $this->uniqueId(),
        ]);
    }

    protected function applyJitter(): void
    {
        $window = (int) ($this->context['jitter_seconds'] ?? 45);

        if ($window <= 0 || app()->environment('testing')) {
            return;
        }

        sleep(random_int(1, $window));
    }
}
