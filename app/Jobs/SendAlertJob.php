<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\Alerts\AlertNotifier;
use App\Support\Concerns\HasIdempotencyKey;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAlertJob implements ShouldQueue
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
        public Alert $alert,
        public int $regionPriceId,
        public array $context = [],
        public ?string $idempotencyKey = null,
    ) {
        $this->onQueue('notify');
        $this->idempotencyKey ??= sprintf(
            'alert:%d:%d',
            $this->alert->id,
            $this->regionPriceId
        );
    }

    public int $tries = 5;

    public int $timeout = 60;

    public function backoff(): array
    {
        return collect([30, 60, 120, 240, 480])
            ->map(static fn (int $seconds): int => max(15, $seconds + random_int(-15, 15)))
            ->all();
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    public function handle(AlertNotifier $notifier): void
    {
        Log::info('send_alert_job.started', [
            'alert_id' => $this->alert->id,
            'region_price_id' => $this->regionPriceId,
            'job' => $this->uniqueId(),
        ]);

        $notifier->notify($this->alert, $this->regionPriceId, $this->context);

        Log::info('send_alert_job.finished', [
            'alert_id' => $this->alert->id,
            'job' => $this->uniqueId(),
        ]);
    }
}
