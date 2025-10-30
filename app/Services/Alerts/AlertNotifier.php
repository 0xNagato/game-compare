<?php

namespace App\Services\Alerts;

use App\Mail\PriceAlertMail;
use App\Models\Alert;
use App\Models\NotificationLog;
use App\Models\RegionPrice;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class AlertNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(Alert $alert, int $regionPriceId, array $context = []): void
    {
        $payloadHash = $this->hashPayload($alert->id, $regionPriceId, $context);

        if ($this->notificationAlreadySent($alert, $payloadHash)) {
            Log::info('alert_notification.skipped_duplicate', [
                'alert_id' => $alert->id,
                'payload_hash' => $payloadHash,
            ]);

            return;
        }

        $regionPrice = RegionPrice::findOrFail($regionPriceId);

        $log = NotificationLog::create([
            'alert_id' => $alert->id,
            'channel' => $alert->channel,
            'recipient' => $this->resolveRecipient($alert),
            'payload_hash' => $payloadHash,
            'status' => 'pending',
        ]);

        try {
            $response = match ($alert->channel) {
                'discord' => $this->sendDiscordNotification($alert, $regionPrice, $context),
                'email' => $this->sendEmailNotification($alert, $regionPrice, $context),
                default => throw new RuntimeException('Unsupported alert channel.'),
            };

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'response_code' => $response['code'] ?? null,
                'response_payload' => $response['payload'] ?? [],
            ]);
        } catch (RuntimeException $exception) {
            $log->update([
                'status' => 'failed',
                'sent_at' => now(),
                'response_payload' => [
                    'error' => $exception->getMessage(),
                ],
                'response_code' => $exception->getCode() ?: null,
            ]);

            Log::error('alert_notification.failed', [
                'alert_id' => $alert->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function sendDiscordNotification(Alert $alert, RegionPrice $price, array $context): array
    {
        $webhookUrl = config('services.discord.alert_webhook');

        if (! $webhookUrl) {
            throw new RuntimeException('Discord webhook URL is not configured.');
        }

        $payload = $this->buildDiscordPayload($alert, $price, $context);

        /** @var Response $response */
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($webhookUrl, $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Discord webhook failed with status %d',
                $response->status()
            ));
        }

        return [
            'code' => $response->status(),
            'payload' => $this->formatResponsePayload($response),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function sendEmailNotification(Alert $alert, RegionPrice $price, array $context): array
    {
        $mailable = new PriceAlertMail($alert, $price, $context);

        Mail::to($alert->user->email, $alert->user->name)->queue($mailable);

        Log::info('email_notification.queued', [
            'alert_id' => $alert->id,
            'region_price_id' => $price->id,
        ]);

        return [
            'code' => null,
            'payload' => [
                'channel' => 'email',
                'queued' => true,
            ],
        ];
    }

    protected function resolveRecipient(Alert $alert): ?string
    {
        return $alert->channel === 'email'
            ? $alert->user->email
            : $alert->user->discord_id;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function hashPayload(int $alertId, int $regionPriceId, array $context): string
    {
        return hash('sha256', json_encode([
            'alert_id' => $alertId,
            'region_price_id' => $regionPriceId,
            'context' => $context,
        ], JSON_THROW_ON_ERROR));
    }

    protected function notificationAlreadySent(Alert $alert, string $payloadHash): bool
    {
        return NotificationLog::query()
            ->where('alert_id', $alert->id)
            ->where('payload_hash', $payloadHash)
            ->where('created_at', '>=', now()->subHours(1))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildDiscordPayload(Alert $alert, RegionPrice $price, array $context): array
    {
        $product = $price->skuRegion->product;
        $skuRegion = $price->skuRegion;

        $fiat = number_format((float) $price->fiat_amount, 2);
        $btc = number_format((float) $price->btc_value, 8);
        $threshold = number_format((float) $alert->threshold_btc, 8);
        $currency = $skuRegion->currency;
        $region = $skuRegion->region_code;

        $embedFields = [
            [
                'name' => 'Retailer',
                'value' => $skuRegion->retailer,
                'inline' => true,
            ],
            [
                'name' => 'Region',
                'value' => $region,
                'inline' => true,
            ],
            [
                'name' => 'Threshold',
                'value' => sprintf('%s BTC (%s)', $threshold, $alert->comparison_operator),
                'inline' => true,
            ],
        ];

        if (array_key_exists('change_percentage', $context)) {
            $embedFields[] = [
                'name' => 'Change',
                'value' => sprintf('%s%%', number_format((float) $context['change_percentage'], 2)),
                'inline' => true,
            ];
        }

        if (array_key_exists('previous_btc', $context)) {
            $embedFields[] = [
                'name' => 'Previous BTC',
                'value' => number_format((float) $context['previous_btc'], 8).' BTC',
                'inline' => true,
            ];
        }

        if (array_key_exists('previous_fiat', $context) && $context['previous_fiat'] !== null) {
            $embedFields[] = [
                'name' => 'Previous Price',
                'value' => sprintf('%s %s', $currency, number_format((float) $context['previous_fiat'], 2)),
                'inline' => true,
            ];
        }

        if (! empty($context['previous_recorded_at'])) {
            $embedFields[] = [
                'name' => 'Previous Snapshot',
                'value' => (string) $context['previous_recorded_at'],
                'inline' => false,
            ];
        }

        $embedFields = array_values(array_filter($embedFields, static fn ($field) => $field !== null));

        $descriptionLines = [
            sprintf('Current price: **%s %s** (%s BTC)', $currency, $fiat, $btc),
            sprintf('Recorded at: %s', $price->recorded_at->toDateTimeString()),
        ];

        if (array_key_exists('change_percentage', $context)) {
            $descriptionLines[] = sprintf(
                'Change vs previous: %s%%',
                number_format((float) $context['change_percentage'], 2)
            );
        }

        if (! empty($context['previous_recorded_at'])) {
            $descriptionLines[] = sprintf('Previous snapshot: %s', $context['previous_recorded_at']);
        }

        return array_filter([
            'content' => $this->resolveRecipient($alert)
                ? sprintf('<@%s> price alert triggered!', $this->resolveRecipient($alert))
                : null,
            'embeds' => [
                [
                    'title' => sprintf('%s • %s', $product->name, $region),
                    'description' => implode("\n", $descriptionLines),
                    'color' => 0x5865F2,
                    'fields' => $embedFields,
                    'footer' => [
                        'text' => sprintf('Alert #%d', $alert->id),
                    ],
                ],
            ],
        ]);
    }

    protected function formatResponsePayload(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        $body = $response->body();

        if (blank($body)) {
            return [];
        }

        return ['raw' => $body];
    }
}
