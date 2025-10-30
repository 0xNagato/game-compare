<?php

namespace App\Services\ExchangeRate;

use App\Models\DatasetSnapshot;
use App\Models\ExchangeRate;
use App\Services\PriceIngestion\Exceptions\ProviderException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExchangeRateSynchronizer
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function synchronize(array $context = []): void
    {
        $provider = Arr::get($context, 'provider', config('pricing.fx.provider', 'coingecko'));
        $pairs = $this->normalizePairs($context['pairs'] ?? config('pricing.fx.pairs', []));

        $snapshot = DatasetSnapshot::create([
            'kind' => 'fx_refresh',
            'provider' => $provider,
            'status' => 'running',
            'started_at' => now(),
            'context' => [
                'pairs' => $pairs->all(),
                'provider' => $provider,
            ],
        ]);

        try {
            if ($pairs->isEmpty()) {
                throw new RuntimeException('No FX pairs configured for synchronization.');
            }

            $rates = match ($provider) {
                'coingecko' => $this->fetchFromCoingecko($pairs),
                default => throw new ProviderException("Unsupported FX provider [{$provider}]."),
            };

            $rates->each(function (array $payload): void {
                ExchangeRate::create($payload);
            });

            $snapshot->update([
                'status' => 'succeeded',
                'finished_at' => now(),
                'row_count' => $rates->count(),
                'context' => array_merge($snapshot->context ?? [], [
                    'row_count' => $rates->count(),
                ]),
            ]);
        } catch (Throwable $exception) {
            $snapshot->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_details' => $exception->getMessage(),
            ]);

            Log::error('exchange_rates.sync_failed', [
                'error' => $exception->getMessage(),
                'snapshot_id' => $snapshot->id,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  Collection<int, array<string, string>>  $pairs
     * @return Collection<int, array<string, mixed>>
     */
    protected function fetchFromCoingecko(Collection $pairs): Collection
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->retry(3, 200)
            ->get('https://api.coingecko.com/api/v3/exchange_rates');

        if ($response->failed()) {
            throw new ProviderException('Failed to fetch FX data from CoinGecko.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['rates'] ?? null)) {
            throw new ProviderException('Unexpected CoinGecko payload structure.');
        }

        $rates = collect($payload['rates'])
            ->mapWithKeys(function ($details, $code) {
                if (! is_array($details)) {
                    return [];
                }

                return [
                    Str::upper((string) $code) => [
                        'value' => (float) ($details['value'] ?? 0),
                        'unit' => $details['unit'] ?? null,
                        'type' => $details['type'] ?? null,
                        'name' => $details['name'] ?? null,
                    ],
                ];
            });

        $capturedAt = now();

        return $pairs
            ->map(function (array $pair) use ($rates, $capturedAt): ?array {
                $base = $pair['base'];
                $quote = $pair['quote'];

                $computed = $this->calculateRateFromCoingecko($rates, $base, $quote);

                if ($computed === null) {
                    Log::warning('exchange_rates.missing_pair', [
                        'base' => $base,
                        'quote' => $quote,
                    ]);

                    return null;
                }

                [$rate, $meta] = $computed;

                return [
                    'base_currency' => $base,
                    'quote_currency' => $quote,
                    'rate' => $rate,
                    'fetched_at' => $capturedAt,
                    'provider' => 'coingecko',
                    'metadata' => $meta,
                ];
            })
            /** @var Collection<int, array<string, mixed>|null> $rows */
            ->filter(fn ($row): bool => is_array($row))
            /** @var Collection<int, array<string, mixed>> $rows */
            ->values();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $rates
     * @return array{0: float, 1: array<string, mixed>}|null
     */
    protected function calculateRateFromCoingecko(Collection $rates, string $base, string $quote): ?array
    {
        if ($base === $quote) {
            return [1.0, ['source' => 'coingecko_exchange_rates', 'note' => 'identity']];
        }

        $baseValue = $rates->get($base)['value'] ?? null;
        $quoteValue = $rates->get($quote)['value'] ?? null;

        if ($baseValue === null || $quoteValue === null || $baseValue == 0.0) {
            return null;
        }

        if ($quote === 'BTC') {
            $rate = round(1 / $baseValue, 8);
        } elseif ($base === 'BTC') {
            $rate = round($quoteValue, 8);
        } else {
            $rate = round($quoteValue / $baseValue, 8);
        }

        return [
            $rate,
            array_filter([
                'source' => 'coingecko_exchange_rates',
                'base_value' => $baseValue,
                'quote_value' => $quoteValue,
                'base_unit' => $rates->get($base)['unit'] ?? null,
                'quote_unit' => $rates->get($quote)['unit'] ?? null,
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $pairs
     * @return Collection<int, array{base: string, quote: string}>
     */
    protected function normalizePairs(array $pairs): Collection
    {
        return collect($pairs)
            ->map(function ($pair) {
                return [
                    'base' => Str::upper((string) Arr::get($pair, 'base')),
                    'quote' => Str::upper((string) Arr::get($pair, 'quote')),
                ];
            })
            ->filter(fn (array $pair) => filled($pair['base']) && filled($pair['quote']))
            ->unique(fn (array $pair) => $pair['base'].'_'.$pair['quote'])
            ->values();
    }
}
