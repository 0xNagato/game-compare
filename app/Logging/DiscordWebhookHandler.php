<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Minimal Discord webhook handler for http.telemetry mirroring.
 * Sends a compact message to the configured webhook URL.
 */
class DiscordWebhookHandler extends AbstractProcessingHandler
{
    protected string $webhookUrl;
    protected bool $includeContext;

    public function __construct(string $webhookUrl, int|string|Level $level = Level::Warning, bool $bubble = true, bool $includeContext = true)
    {
        parent::__construct($level, $bubble);
        $this->webhookUrl = $webhookUrl;
        $this->includeContext = $includeContext;
    }

    protected function write(LogRecord $record): void
    {
        $ctx = $record->context ?? [];
        $src = $ctx['http.source'] ?? ($ctx['http']['source'] ?? 'unknown');
        $method = $ctx['http.method'] ?? ($ctx['http']['method'] ?? null);
        $url = $ctx['http.url'] ?? ($ctx['http']['url'] ?? null);
        $status = $ctx['http.status'] ?? ($ctx['http']['status'] ?? null);
        $dur = $ctx['http.duration_ms'] ?? ($ctx['http']['duration_ms'] ?? null);

        $content = sprintf('[%s] %s %s → %s (%sms) level=%s',
            (string) $src,
            $method ? strtoupper((string) $method) : 'REQ',
            is_string($url) ? $url : '-',
            is_scalar($status) ? (string) $status : '-',
            is_scalar($dur) ? (string) $dur : '-',
            $record->level->getName()
        );

        $payload = [
            'content' => $content,
        ];

        // Optional minimal fields for operators
        if ($this->includeContext) {
            $extras = [];
            if (isset($ctx['rate_limited']) && $ctx['rate_limited']) {
                $extras[] = 'rate_limited=true';
            }
            if ($extras) {
                $payload['content'] .= ' ['.implode(' ', $extras).']';
            }
        }

        $this->postJson($payload);
    }

    protected function postJson(array $payload): void
    {
        try {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'timeout' => 5,
                ],
            ];
            $context = stream_context_create($opts);
            @file_get_contents($this->webhookUrl, false, $context);
        } catch (\Throwable $e) {
            // Silently ignore mirroring failures
        }
    }
}
