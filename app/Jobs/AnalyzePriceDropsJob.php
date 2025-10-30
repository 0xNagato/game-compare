<?php

namespace App\Jobs;

use App\Services\Alerts\PriceDropAnalyzer;
use App\Support\Concerns\HasIdempotencyKey;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzePriceDropsJob implements ShouldQueue
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
        $this->onQueue('analyze');
        $this->idempotencyKey ??= sprintf(
            'analyze:%s',
            $context['window'] ?? now()->format(DateTimeInterface::ATOM)
        );
    }

    public int $tries = 5;

    public int $timeout = 180;

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

    public function handle(PriceDropAnalyzer $analyzer): void
    {
        Log::info('analyze_price_drop_job.started', [
            'context' => $this->context,
            'job' => $this->uniqueId(),
        ]);

        $analyzer->analyze($this->context);

        Log::info('analyze_price_drop_job.finished', [
            'job' => $this->uniqueId(),
        ]);
    }
}
