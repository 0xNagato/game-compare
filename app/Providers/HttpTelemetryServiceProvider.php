<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HttpTelemetryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Add a macro to attach telemetry middleware to any PendingRequest
        PendingRequest::macro('withTelemetry', function (string $source = 'default') {
            /** @var PendingRequest $this */
            if (! (bool) config('http_telemetry.enabled', true)) {
                return $this;
            }

            $sampleRate = (float) config('http_telemetry.sample_rate', 1.0);
            $shouldSample = $sampleRate >= 1.0 || (mt_rand() / mt_getrandmax()) <= $sampleRate;
            if (! $shouldSample) {
                return $this;
            }

            $redactHeaders = array_filter(array_map('strtolower', config('http_telemetry.redact.headers', [])));
            $redactQuery = array_filter(array_map('strtolower', config('http_telemetry.redact.query', [])));

            return $this->withMiddleware(function (callable $handler) use ($source, $redactHeaders, $redactQuery) {
                return function ($request, array $options) use ($handler, $source, $redactHeaders, $redactQuery) {
                    $start = microtime(true);
                    $method = strtoupper($request->getMethod());
                    $uri = (string) $request->getUri();

                    // Extract query params for logging (redacted)
                    $parsed = parse_url($uri);
                    $queryParams = [];
                    if (is_array($parsed) && isset($parsed['query'])) {
                        parse_str($parsed['query'], $queryParams);
                    }
                    $queryParams = collect($queryParams)
                        ->mapWithKeys(function ($v, $k) use ($redactQuery) {
                            return [strtolower((string) $k) => in_array(strtolower((string) $k), $redactQuery, true) ? 'REDACTED' : $v];
                        })->all();

                    $logBase = [
                        'http.source' => $source,
                        'http.method' => $method,
                        'http.url' => $uri,
                        'http.query' => config('http_telemetry.include_query', true) ? $queryParams : null,
                    ];

                    // Add request headers if enabled
                    if (config('http_telemetry.include_request_headers', true)) {
                        $headers = [];
                        foreach ($request->getHeaders() as $k => $v) {
                            $headers[strtolower($k)] = in_array(strtolower($k), $redactHeaders, true) ? 'REDACTED' : implode(',', $v);
                        }
                        $logBase['http.req_headers'] = $headers;
                    }

                    return $handler($request, $options)->then(function ($response) use ($start, $logBase, $redactHeaders, $source) {
                        $ms = (int) round((microtime(true) - $start) * 1000);
                        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
                        $level = $status !== null && $status >= 400
                            ? config('http_telemetry.error_level', 'warning')
                            : config('http_telemetry.success_level', 'info');

                        $payload = $logBase + [
                            'http.status' => $status,
                            'http.duration_ms' => $ms,
                        ];

                        if (config('http_telemetry.include_response_headers', true) && method_exists($response, 'getHeaders')) {
                            $headers = [];
                            foreach ($response->getHeaders() as $k => $v) {
                                $headers[strtolower($k)] = in_array(strtolower($k), $redactHeaders, true) ? 'REDACTED' : implode(',', $v);
                            }
                            $payload['http.res_headers'] = $headers;
                        }

                        // Highlight throttling
                        if ($status === 429) {
                            $payload['rate_limited'] = true;
                        }

                        // Route to source-specific log files via the http_telemetry channel
                        Log::channel('http_telemetry')->log($level, 'http.telemetry', $payload);

                        // Lightweight rolling counters in Redis (1-hour TTL)
                        if ($status !== null) {
                            try {
                                if ($status === 429) {
                                    $key = sprintf('http:telemetry:lh:%s:429', $source);
                                    Redis::incr($key);
                                    Redis::expire($key, 3600);
                                } elseif ($status >= 500 && $status < 600) {
                                    $key = sprintf('http:telemetry:lh:%s:5xx', $source);
                                    Redis::incr($key);
                                    Redis::expire($key, 3600);
                                }
                            } catch (\Throwable $e) {
                                // ignore telemetry storage errors
                            }
                        }

                        return $response;
                    });
                };
            });
        });
    }
}
