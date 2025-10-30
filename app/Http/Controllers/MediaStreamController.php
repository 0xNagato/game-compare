<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController
{
    /**
     * Stream a remote video through a signed proxy with Range support.
     *
     * Route: /media/play/{name}.{ext}?src={base64url(source)}
     */
    public function play(Request $request, string $name, string $ext)
    {
        if (! ($this->proxyEnabled())) {
            return response('Media proxy disabled', 404);
        }

        $src = (string) $request->query('src', '');
        if ($src === '') {
            return response('Missing src', 400);
        }

        $sourceUrl = $this->base64urlDecode($src);
        if (! $sourceUrl || ! filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            return response('Invalid src', 400);
        }

        // Restrict to allowed hosts to avoid open-proxy abuse
        if (! $this->isAllowedHost($sourceUrl)) {
            return response('Forbidden host', 403);
        }

        // Only allow common video extensions (route regex also enforces this)
        $ext = strtolower($ext);
        $mime = $this->mimeForExt($ext);

        $range = $request->header('Range');

        try {
            $client = Http::timeout((int) config('media.proxy.timeout', 20))
                ->retry(1, 200)
                ->withHeaders(array_filter([
                    'Range' => $range,
                    'Accept' => 'video/*,application/octet-stream;q=0.9,*/*;q=0.8',
                ]))
                ->withOptions([
                    'stream' => true,
                    'decode_content' => false,
                    // forward Range requests efficiently
                ]);

            $upstream = $client->get($sourceUrl);

            if ($upstream->status() >= 400) {
                return response('Upstream error', 502);
            }

            $psr = $upstream->toPsrResponse();
            $body = $psr->getBody();

            $headers = [
                'Content-Type' => $psr->getHeaderLine('Content-Type') ?: $mime,
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=900',
                'Content-Disposition' => 'inline; filename="'.addslashes($name).'.'.$ext.'"',
            ];

            // Pass-through critical size/partial headers when present
            foreach (['Content-Length', 'Content-Range'] as $key) {
                $val = $psr->getHeaderLine($key);
                if ($val !== '') {
                    $headers[$key] = $val;
                }
            }

            $status = $psr->getStatusCode(); // 200 or 206 typically

            $response = new StreamedResponse(function () use ($body) {
                while (! $body->eof()) {
                    echo $body->read(8192);
                    if (connection_aborted()) {
                        break;
                    }
                }
                $body->close();
            }, $status, $headers);

            return $response;
        } catch (\Throwable $e) {
            Log::warning('media.proxy_failed', [
                'src' => $sourceUrl ?? 'invalid',
                'error' => $e->getMessage(),
            ]);

            return response('Proxy failure', 502);
        }
    }

    protected function proxyEnabled(): bool
    {
        return (bool) config('media.proxy.enabled', true);
    }

    protected function isAllowedHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = strtolower($host);
        $allowed = (array) config('media.proxy.allowed_hosts', []);
        foreach ($allowed as $rule) {
            $rule = strtolower((string) $rule);
            if ($rule === '') continue;
            if ($rule[0] === '.') {
                // suffix match (e.g., .giantbomb.com)
                if (str_ends_with($host, $rule)) return true;
            } elseif ($rule === '*' || $rule === $host) {
                return true;
            } elseif (str_starts_with($rule, '*.' )) {
                // wildcard subdomain
                $suffix = substr($rule, 1); // remove leading '*'
                if (str_ends_with($host, $suffix)) return true;
            }
        }

        return false;
    }

    protected function mimeForExt(string $ext): string
    {
        return match ($ext) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }

    protected function base64urlDecode(string $input): ?string
    {
        $b64 = strtr($input, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        return $decoded === false ? null : $decoded;
    }
}
