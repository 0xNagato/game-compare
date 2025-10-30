<?php

return [
    'enabled' => env('HTTP_TELEMETRY_ENABLED', true),
    'sample_rate' => (float) env('HTTP_TELEMETRY_SAMPLE', 1.0), // 0.0 - 1.0
    'success_level' => env('HTTP_TELEMETRY_SUCCESS_LEVEL', 'info'), // debug|info|notice
    'error_level' => env('HTTP_TELEMETRY_ERROR_LEVEL', 'warning'), // warning|error|critical
    'include_request_headers' => env('HTTP_TELEMETRY_INCLUDE_REQ_HEADERS', true),
    'include_response_headers' => env('HTTP_TELEMETRY_INCLUDE_RES_HEADERS', true),
    'include_query' => env('HTTP_TELEMETRY_INCLUDE_QUERY', true),
    'include_body' => env('HTTP_TELEMETRY_INCLUDE_BODY', false), // be careful with secrets
    'redact' => [
        'headers' => array_map('strtolower', explode(',', (string) env('HTTP_TELEMETRY_REDACT_HEADERS', 'authorization,x-api-key,x-api_key,api-key'))),
        'query' => array_map('strtolower', explode(',', (string) env('HTTP_TELEMETRY_REDACT_QUERY', 'key,token,api_key'))),
    ],
];
