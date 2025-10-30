<?php

namespace App\Jobs;

use App\Services\PriceIngestion\PriceIngestionManager;
use App\Support\Concerns\HasIdempotencyKey;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchPricesJob implements ShouldQueue
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
        public string $provider,
        public array $context = [],
        public ?string $idempotencyKey = null,
    ) {
        $this->onQueue('fetch');
        $this->idempotencyKey ??= sprintf(
            'fetch:%s:%s',
            $provider,
            $context['window'] ?? now()->format(DateTimeInterface::ATOM)
        );
    }

    public int $tries = 5;

    public int $timeout = 300;

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

    public function handle(PriceIngestionManager $manager): void
    {
        // Allow provider-specific temporary option overrides for targeted ingests
        // Supported keys per provider implementations:
        // - nexarda: options.products
        // - itad: options.requests
        // - pricecharting: options.catalog
        // - steam_store/playstation_store/microsoft_store/nintendo_eshop: options.apps/options.product_ids/options.title_ids/options.catalog_queries
        $overrides = [
            'games' => 'games',            // generic
            'products' => 'products',      // nexarda
            'requests' => 'requests',      // itad
            'catalog' => 'catalog',        // pricecharting
            'apps' => 'apps',              // steam
            'product_ids' => 'product_ids', // microsoft
            'title_ids' => 'title_ids',    // nintendo
            'catalog_queries' => 'catalog_queries', // playstation
        ];

        foreach ($overrides as $ctxKey => $optKey) {
            if (isset($this->context[$ctxKey]) && is_array($this->context[$ctxKey])) {
                config()->set("pricing.providers.{$this->provider}.options.{$optKey}", $this->context[$ctxKey]);
            }
        }

        Log::info('fetch_prices_job.started', [
            'provider' => $this->provider,
            'context' => $this->context,
            'job' => $this->uniqueId(),
        ]);

        $manager->ingest($this->provider, $this->context);

        Log::info('fetch_prices_job.finished', [
            'provider' => $this->provider,
            'job' => $this->uniqueId(),
        ]);
    }
}
